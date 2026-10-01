<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/User.php';

class OnlineAssignment
{
    public static function create(int $courseId, int $lecturerId, int $creatorId, array $data, array $questions): int
    {
        if (!$questions) throw new InvalidArgumentException('Add at least one question.');
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            if (!User::canManageCourse($creatorId, $courseId)) throw new RuntimeException('Course access denied.');
            $maxScore = 0.0;
            foreach ($questions as $q) $maxScore += (float)$q['points'];
            Database::query(
                "INSERT INTO assignments (course_id, lecturer_id, title, description, max_score, deadline, allow_late, late_penalty, status, assignment_type, tutor_name)
                 VALUES (:course_id, :owner_id, :title, :description, :max_score, :deadline, :allow_late, :late_penalty, :status, 'online', :tutor_name)",
                [
                    ':course_id' => $courseId, ':owner_id' => $lecturerId,
                    ':title' => trim($data['title']), ':description' => trim($data['description']),
                    ':max_score' => $maxScore, ':deadline' => $data['deadline'],
                    ':allow_late' => (int)$data['allow_late'], ':late_penalty' => (float)$data['late_penalty'],
                    ':status' => $data['status'], ':tutor_name' => trim($data['tutor_name']) ?: null,
                ]
            );
            $assignmentId = (int)$pdo->lastInsertId();
            foreach ($questions as $position => $question) {
                $options = array_values(array_filter(array_map('trim', $question['options'] ?? []), static fn($value) => $value !== ''));
                $type = $question['type'];
                if (in_array($type, ['single_choice', 'multiple_choice'], true) && (count($options) < 2 || !$question['correct'])) {
                    throw new InvalidArgumentException('Each choice question needs at least two options and a correct answer.');
                }
                if ($type === 'single_choice' && count($question['correct']) !== 1) {
                    throw new InvalidArgumentException('Single-choice questions need exactly one correct option.');
                }
                if ($type !== 'text' && array_diff($question['correct'], array_keys($options))) {
                    throw new InvalidArgumentException('Correct answers must refer to an existing option.');
                }
                Database::query(
                    'INSERT INTO assignment_questions (assignment_id, question_text, question_type, points, position) VALUES (:assignment_id, :question, :type, :points, :position)',
                    [':assignment_id' => $assignmentId, ':question' => trim($question['text']), ':type' => $type, ':points' => (float)$question['points'], ':position' => $position]
                );
                $questionId = (int)$pdo->lastInsertId();
                foreach ($options as $optionPosition => $optionText) {
                    $selected = in_array($optionPosition, $question['correct'], true) ? 1 : 0;
                    Database::query(
                        'INSERT INTO assignment_question_options (question_id, option_text, is_correct, position) VALUES (:question_id, :option_text, :is_correct, :position)',
                        [':question_id' => $questionId, ':option_text' => $optionText, ':is_correct' => $selected, ':position' => $optionPosition]
                    );
                }
            }
            $pdo->commit();
            return $assignmentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function questionsForStudent(int $assignmentId): array
    {
        $questions = Database::query(
            'SELECT id, question_text, question_type, points, position FROM assignment_questions WHERE assignment_id = :id ORDER BY position',
            [':id' => $assignmentId]
        )->fetchAll();
        foreach ($questions as &$question) {
            $question['options'] = Database::query(
                'SELECT id, option_text, position FROM assignment_question_options WHERE question_id = :id ORDER BY position',
                [':id' => $question['id']]
            )->fetchAll();
        }
        unset($question);
        return $questions;
    }

    public static function responsesForSubmission(int $submissionId): array
    {
        return Database::query(
            "SELECT q.id AS question_id, q.question_text, q.question_type, q.points,
                    r.response_text, r.selected_option_ids, r.awarded_score,
                    GROUP_CONCAT(CASE WHEN o.is_correct = 1 THEN o.option_text END SEPARATOR ' | ') AS correct_answers
             FROM assignment_responses r
             JOIN assignment_questions q ON q.id = r.question_id
             LEFT JOIN assignment_question_options o ON o.question_id = q.id
             WHERE r.submission_id = :submission_id
             GROUP BY q.id, q.question_text, q.question_type, q.points,
                      r.response_text, r.selected_option_ids, r.awarded_score
             ORDER BY q.position",
            [':submission_id' => $submissionId]
        )->fetchAll();
    }

    public static function submit(int $assignmentId, int $studentId, array $answers, bool $isLate = false): int
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $assignment = Database::query("SELECT * FROM assignments WHERE id = :id AND assignment_type = 'online' AND status = 'published' FOR UPDATE", [':id' => $assignmentId])->fetch();
            if (!$assignment) throw new RuntimeException('Online assignment is unavailable.');
            if (!User::studentCanAccessCourse($studentId, (int)$assignment['course_id'])) throw new RuntimeException('You are not eligible to access this course.');
            if (strtotime($assignment['deadline']) < time() && !(bool)$assignment['allow_late']) throw new RuntimeException('The deadline has passed.');
            $existing = Database::query('SELECT id FROM submissions WHERE assignment_id = :aid AND student_id = :sid', [':aid' => $assignmentId, ':sid' => $studentId])->fetch();
            if ($existing) throw new RuntimeException('You have already submitted this assignment.');
            $questions = Database::query('SELECT * FROM assignment_questions WHERE assignment_id = :aid ORDER BY position', [':aid' => $assignmentId])->fetchAll();
            $autoScore = 0.0;
            $hasText = false;
            $responses = [];
            foreach ($questions as $question) {
                $raw = $answers[$question['id']] ?? null;
                if ($question['question_type'] === 'text') {
                    $hasText = true;
                    $responses[] = [$question, trim((string)$raw), []];
                    continue;
                }
                $selected = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : ($raw === null ? [] : [$raw]))));
                $validOptions = Database::query('SELECT id, is_correct FROM assignment_question_options WHERE question_id = :qid', [':qid' => $question['id']])->fetchAll();
                $correct = [];
                $valid = [];
                foreach ($validOptions as $option) {
                    $valid[] = (int)$option['id'];
                    if ((int)$option['is_correct'] === 1) $correct[] = (int)$option['id'];
                }
                if (array_diff($selected, $valid)) throw new InvalidArgumentException('Invalid answer option.');
                sort($selected); sort($correct);
                if ($selected === $correct) $autoScore += (float)$question['points'];
                $responses[] = [$question, null, $selected];
            }
            Database::query(
                'INSERT INTO submissions (assignment_id, student_id, file_path, original_name, file_size, mime_type, submitted_at, is_late, score, status)
                 VALUES (:aid, :sid, :path, :name, 0, :mime, NOW(), :late, :score, :status)',
                [
                    ':aid' => $assignmentId, ':sid' => $studentId, ':path' => 'online:' . $assignmentId,
                    ':name' => 'Online assignment responses', ':mime' => 'text/plain',
                    ':late' => $isLate ? 1 : 0,
                    ':score' => $hasText ? null : $autoScore, ':status' => 'submitted',
                ]
            );
            $submissionId = (int)$pdo->lastInsertId();
            $earned = 0.0;
            foreach ($responses as [$question, $text, $selected]) {
                $questionScore = null;
                if ($question['question_type'] !== 'text') {
                    $correct = array_map('intval', array_column(Database::query(
                        'SELECT id FROM assignment_question_options WHERE question_id = :qid AND is_correct = 1',
                        [':qid' => $question['id']]
                    )->fetchAll(), 'id'));
                    $selectedIds = array_map('intval', $selected);
                    sort($correct); sort($selectedIds);
                    $questionScore = $selectedIds === $correct ? (float)$question['points'] : 0.0;
                    $earned += $questionScore;
                }
                Database::query(
                    'INSERT INTO assignment_responses (submission_id, question_id, response_text, selected_option_ids, awarded_score) VALUES (:submission_id, :question_id, :response_text, :selected, :score)',
                    [
                        ':submission_id' => $submissionId, ':question_id' => $question['id'],
                        ':response_text' => $text,
                        ':selected' => $selected ? json_encode($selected, JSON_THROW_ON_ERROR) : null,
                        ':score' => $questionScore,
                    ]
                );
            }
            if (!$hasText) {
                Database::query(
                    'UPDATE submissions SET score = :score, graded_by = :graded_by, graded_at = NOW() WHERE id = :id',
                    [':score' => $earned, ':graded_by' => (int)$assignment['lecturer_id'], ':id' => $submissionId]
                );
            }
            $pdo->commit();
            return $submissionId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
