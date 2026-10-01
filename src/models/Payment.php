<?php
require_once __DIR__ . '/../config/Database.php';

class Payment
{
    public static function start(int $studentId, int $courseId, string $phone): int
    {
        $course = Database::query(
            "SELECT c.id, c.price, c.audience, c.status, s.education_level
             FROM courses c JOIN users s ON s.id = :student_id
             WHERE c.id = :course_id AND c.status = 'published' AND s.role = 'student'",
            [':student_id' => $studentId, ':course_id' => $courseId]
        )->fetch();
        if (!$course || $course['price'] === null || (float)$course['price'] <= 0) {
            throw new InvalidArgumentException('This course does not have a payable fee configured.');
        }
        if (EDUCATION_COURSE_TARGETING_ENABLED && $course['education_level'] === null) {
            throw new InvalidArgumentException('Complete your education profile before applying for this course.');
        }
        $application = Database::query(
            'SELECT application_status FROM enrollments WHERE student_id = :student_id AND course_id = :course_id',
            [':student_id' => $studentId, ':course_id' => $courseId]
        )->fetch();
        if (!$application || $application['application_status'] === 'rejected') {
            throw new InvalidArgumentException('Apply for this course and wait for approval before paying.');
        }
        if ($application['application_status'] !== 'approved') {
            throw new InvalidArgumentException('Your course application must be approved before payment.');
        }
        if (abs((float)$course['price'] - round((float)$course['price'])) > 0.001) {
            throw new InvalidArgumentException('M-Pesa course fees must be whole Kenyan shillings.');
        }
        $eligible = $course['audience'] === 'both'
            || ($course['education_level'] === 'campus' && $course['audience'] === 'campus_only')
            || ($course['education_level'] === 'high_school' && $course['audience'] === 'high_school_only');
        if (!$eligible) throw new InvalidArgumentException('This course is not available for your education level.');

        $enrollment = Database::query(
            'SELECT access_status FROM enrollments WHERE student_id = :student_id AND course_id = :course_id',
            [':student_id' => $studentId, ':course_id' => $courseId]
        )->fetch();
        if (!$enrollment) {
            Database::query(
                "INSERT INTO enrollments (student_id, course_id, access_status) VALUES (:student_id, :course_id, 'pending_payment')",
                [':student_id' => $studentId, ':course_id' => $courseId]
            );
        }
        $paid = Database::query(
            "SELECT id FROM payments WHERE student_id = :student_id AND course_id = :course_id AND payment_status = 'success' LIMIT 1",
            [':student_id' => $studentId, ':course_id' => $courseId]
        )->fetch();
        if ($paid) throw new InvalidArgumentException('Course access is already active.');
        $pending = Database::query(
            "SELECT id FROM payments WHERE student_id = :student_id AND course_id = :course_id AND payment_status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1",
            [':student_id' => $studentId, ':course_id' => $courseId]
        )->fetch();
        if ($pending) throw new InvalidArgumentException('A payment request is already pending. Check your phone or try again in a few minutes.');
        $amount = (float)$course['price'];
        Database::query(
            "INSERT INTO payments (student_id, course_id, amount_paid, payment_status, phone_number)
             VALUES (:student_id, :course_id, :amount, 'pending', :phone)",
            [':student_id' => $studentId, ':course_id' => $courseId, ':amount' => $amount, ':phone' => $phone]
        );
        $paymentId = (int)Database::getInstance()->lastInsertId();
        Database::query(
            "INSERT INTO audit_logs (admin_id, action, target_type, target_id, before_data, after_data)
             VALUES (NULL, 'payment.initiated', 'payment', :target_id, NULL, :after_data)",
            [':target_id' => (string)$paymentId, ':after_data' => json_encode(['student_id' => $studentId, 'course_id' => $courseId, 'amount' => $amount, 'status' => 'pending'], JSON_THROW_ON_ERROR)]
        );
        return $paymentId;
    }

    public static function attachCheckout(int $paymentId, string $merchantRequestId, string $checkoutRequestId): void
    {
        Database::query(
            'UPDATE payments SET merchant_request_id = :merchant, checkout_request_id = :checkout WHERE id = :id AND payment_status = \'pending\'',
            [':merchant' => $merchantRequestId, ':checkout' => $checkoutRequestId, ':id' => $paymentId]
        );
    }

    public static function failInitiation(int $paymentId): void
    {
        Database::query("UPDATE payments SET payment_status = 'failed' WHERE id = :id AND payment_status = 'pending'", [':id' => $paymentId]);
    }

    public static function logCallback(string $rawPayload, ?string $checkoutRequestId): int
    {
        if (strlen($rawPayload) > 65535) throw new InvalidArgumentException('Callback payload too large.');
        // Store full raw callback in the restricted callback inbox; do not write phone/details to general logs.
        Database::query(
            'INSERT INTO payment_callback_inbox (checkout_request_id, payload) VALUES (:checkout, :payload)',
            [':checkout' => $checkoutRequestId, ':payload' => $rawPayload]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function processCallback(int $inboxId): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $inbox = Database::query('SELECT * FROM payment_callback_inbox WHERE id = :id FOR UPDATE', [':id' => $inboxId])->fetch();
            if (!$inbox || $inbox['processing_status'] === 'processed') {
                $pdo->commit();
                return;
            }
            $body = json_decode($inbox['payload'], true, 512, JSON_THROW_ON_ERROR);
            $callback = $body['Body']['stkCallback'] ?? null;
            if (!is_array($callback) || empty($callback['CheckoutRequestID'])) {
                throw new UnexpectedValueException('Malformed STK callback payload.');
            }
            $payment = Database::query(
                'SELECT * FROM payments WHERE checkout_request_id = :checkout FOR UPDATE',
                [':checkout' => $callback['CheckoutRequestID']]
            )->fetch();
            if (!$payment) throw new RuntimeException('No payment matches the callback CheckoutRequestID.');
            if (!hash_equals((string)$payment['merchant_request_id'], (string)($callback['MerchantRequestID'] ?? ''))) {
                throw new RuntimeException('M-Pesa merchant request identifier does not match.');
            }

            $resultCode = (int)($callback['ResultCode'] ?? -1);
            $metadata = [];
            foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
                if (isset($item['Name'])) $metadata[$item['Name']] = $item['Value'] ?? null;
            }
            $phone = preg_replace('/\D+/', '', (string)($metadata['PhoneNumber'] ?? ''));
            $expectedPhone = preg_replace('/\D+/', '', (string)$payment['phone_number']);
            if (str_starts_with($phone, '0') && strlen($phone) === 10) $phone = '254' . substr($phone, 1);
            if (str_starts_with($expectedPhone, '0') && strlen($expectedPhone) === 10) $expectedPhone = '254' . substr($expectedPhone, 1);
            $isSuccess = $resultCode === 0
                && isset($metadata['Amount'])
                && abs((float)$metadata['Amount'] - (float)$payment['amount_paid']) < 0.001
                && !empty($metadata['MpesaReceiptNumber'])
                && $phone !== '' && hash_equals($expectedPhone, $phone);
            $newStatus = $isSuccess ? 'success' : 'failed';
            $applicationStatus = PORTAL_EXTENSIONS_ENABLED
                ? Database::query('SELECT application_status FROM enrollments WHERE student_id = :student_id AND course_id = :course_id FOR UPDATE', [':student_id' => $payment['student_id'], ':course_id' => $payment['course_id']])->fetchColumn()
                : 'approved';
            if ($payment['payment_status'] !== 'success') {
                Database::query(
                    'UPDATE payments SET payment_status = :status, transaction_id = :transaction_id,
                             mpesa_receipt = :receipt, callback_payload = :payload, callback_received_at = NOW()
                     WHERE id = :id',
                    [
                        ':status' => $newStatus,
                        ':transaction_id' => isset($metadata['MpesaReceiptNumber']) ? (string)$metadata['MpesaReceiptNumber'] : null,
                        ':receipt' => $metadata['MpesaReceiptNumber'] ?? null,
                        ':payload' => $inbox['payload'],
                        ':id' => $payment['id'],
                    ]
                );
                if ($isSuccess && $applicationStatus === 'approved') {
                    Database::query(
                        "UPDATE enrollments SET access_status = 'active', access_granted_at = COALESCE(access_granted_at, NOW())
                         WHERE student_id = :student_id AND course_id = :course_id",
                        [':student_id' => $payment['student_id'], ':course_id' => $payment['course_id']]
                    );
                }
                Database::query(
                    'INSERT INTO audit_logs (admin_id, action, target_type, target_id, before_data, after_data)
                     VALUES (NULL, :action, \'payment\', :target_id, :before_data, :after_data)',
                    [
                        ':action' => 'payment.' . $newStatus,
                        ':target_id' => (string)$payment['id'],
                        ':before_data' => json_encode(['payment_status' => $payment['payment_status']], JSON_THROW_ON_ERROR),
                        ':after_data' => json_encode(['payment_status' => $newStatus, 'receipt' => $metadata['MpesaReceiptNumber'] ?? null], JSON_THROW_ON_ERROR),
                    ]
                );
                } elseif ($isSuccess && $applicationStatus === 'approved') {
                // A prior callback may have marked success before enrollment was
                // added. Idempotently re-apply access after verifying same txn.
                Database::query(
                    "UPDATE enrollments SET access_status = 'active', access_granted_at = COALESCE(access_granted_at, NOW())
                     WHERE student_id = :student_id AND course_id = :course_id",
                    [':student_id' => $payment['student_id'], ':course_id' => $payment['course_id']]
                );
            }
            Database::query(
                "UPDATE payment_callback_inbox SET processing_status = 'processed', attempts = attempts + 1,
                 processed_at = NOW(), last_error = NULL WHERE id = :id",
                [':id' => $inboxId]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Database::query(
                "UPDATE payment_callback_inbox SET processing_status = 'pending', attempts = attempts + 1,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL LEAST(3600, POW(2, LEAST(attempts, 10))) SECOND),
                 last_error = :error WHERE id = :id",
                [':error' => mb_substr($e->getMessage(), 0, 500), ':id' => $inboxId]
            );
            throw $e;
        }
    }

    public static function failStalePending(int $minutes = 30): int
    {
        $minutes = max(5, min(240, $minutes));
        $stmt = Database::query(
            "UPDATE payments SET payment_status = 'failed'
               WHERE payment_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL " . $minutes . ' MINUTE'
        );
        return $stmt->rowCount();
    }

    public static function processDueCallbacks(int $limit = 50): int
    {
        $rows = Database::query(
            "SELECT id FROM payment_callback_inbox
             WHERE processing_status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
             ORDER BY received_at ASC LIMIT " . max(1, min(200, $limit))
        )->fetchAll();
        $processed = 0;
        foreach ($rows as $row) {
            try {
                self::processCallback((int)$row['id']);
                $processed++;
            } catch (Throwable $e) {
                error_log('Payment callback retry failed: inbox_id=' . (int)$row['id'] . ' error=' . $e->getMessage());
            }
        }
        return $processed;
    }

    public static function recentForStudent(int $studentId): array
    {
        return Database::query(
            'SELECT p.*, c.title AS course_title FROM payments p JOIN courses c ON c.id = p.course_id
             WHERE p.student_id = :student_id ORDER BY p.created_at DESC LIMIT 50',
            [':student_id' => $studentId]
        )->fetchAll();
    }
}
