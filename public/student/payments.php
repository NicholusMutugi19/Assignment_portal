<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Payment.php';

Auth::requireRole('student', '/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED || !MPESA_ENABLED) {
    http_response_code(503);
    exit('Course payments are not enabled.');
}
$user = Auth::user();
$payments = Payment::recentForStudent((int)$user['id']);
$pageTitle = 'Course Payments';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Course payments</h1><p class="page-subtitle">Access to paid course resources is granted only after M-Pesa confirms payment.</p></div></div>
<?php if (!$payments): ?><div class="empty-state"><h3>No payment attempts yet</h3><p>Open My Courses to enroll and pay for a priced course.</p><a class="btn btn-primary" href="/student/courses.php">My Courses</a></div>
<?php else: ?><div class="card"><div class="table-wrap"><table><thead><tr><th>Course</th><th>Amount</th><th>Status</th><th>M-Pesa receipt</th><th>Created</th></tr></thead><tbody>
<?php foreach ($payments as $payment): ?><tr><td><?= htmlspecialchars($payment['course_title']) ?></td><td>KES <?= number_format((float)$payment['amount_paid'], 2) ?></td><td><span class="badge badge-<?= $payment['payment_status'] === 'success' ? 'success' : ($payment['payment_status'] === 'failed' ? 'danger' : 'warning') ?>"><?= htmlspecialchars(ucfirst($payment['payment_status'])) ?></span></td><td><?= htmlspecialchars($payment['mpesa_receipt'] ?? '—') ?></td><td><?= htmlspecialchars(date('M j, Y H:i', strtotime($payment['created_at']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
