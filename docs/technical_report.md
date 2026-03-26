# Assignment Portal — Technical Report

**Course:** Web Technologies (CS301)
**Submission:** Assignment Portal Project
**Stack:** PHP 8+, MySQL 8+, HTML5/CSS3/JS (Vanilla)

---

## 1. File Upload Security Considerations

### 1.1 CGI Principles Applied

The portal implements upload handling using core CGI security principles:

**a) `is_uploaded_file()` verification**
Every uploaded file is verified using PHP's `is_uploaded_file($tmp_name)` before any processing. This prevents attackers from injecting arbitrary file paths (e.g. `/etc/passwd`) into the upload parameter and having the server process them as uploads.

```php
if (!is_uploaded_file($file['tmp_name'])) {
    $this->errors[] = 'Invalid upload source — possible injection attempt.';
    return $this->fail();
}
```

**b) `move_uploaded_file()` — not `copy()` or `rename()`**
Only `move_uploaded_file()` is used to move files from the temp directory. This PHP function additionally verifies the file was uploaded via HTTP POST, providing a double check.

**c) MIME detection via magic bytes (finfo)**
Browser-supplied MIME types (Content-Type header) are completely ignored. Instead, the server reads the file's magic bytes using `finfo_open(FILEINFO_MIME_TYPE)`:

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $tmpPath);
finfo_close($finfo);
```

This prevents attackers from renaming a PHP shell as `malware.pdf` — the magic bytes will reveal the true type.

**d) Extension whitelist (not blacklist)**
Only `['pdf', 'doc', 'docx', 'zip']` are permitted. Blacklisting is unreliable (`.phtml`, `.php7`, etc.).

**e) Filename sanitisation**
Uploaded filenames are stripped of all non-alphanumeric characters before storage:
```php
$name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
```
A random prefix (`uniqid()`) is prepended to prevent filename collisions and enumeration.

**f) PHP execution blocked in upload directories**
`.htaccess` in `public/uploads/` denies all `.php` variants, preventing uploaded file execution even if a malicious file somehow bypassed validation.

**g) Content-Disposition: attachment**
Uploaded files are served with `Content-Disposition: attachment`, preventing inline execution of malicious HTML/SVG/JS files in the browser.

---

## 2. Database Design for Submission Tracking

### 2.1 Entity-Relationship Overview

```
users (1) ──────< enrollments >────── (N) courses
  |                                          |
  |                                    (1) has many
  | (lecturer)                               |
  └──────────────────> assignments (N)──────┘
                            |
                         (1) has many
                            |
                        submissions (N) ──── users (student)
```

### 2.2 Key Design Decisions

**Separate `enrollments` junction table**
Rather than storing course membership in the users table, a many-to-many `enrollments` table enables:
- A student enrolled in multiple courses
- A course with many students
- Efficient JOIN queries for "assignments for this student"

**`UNIQUE KEY (assignment_id, student_id)` on submissions**
This database-level constraint is the last line of defence against duplicate submissions, even if application logic is bypassed:
```sql
UNIQUE KEY unique_submission (assignment_id, student_id)
```

**`is_late` flag stored on submission**
Because late-submission policy could change after the fact, the submission records its own late status at insert time. This creates an immutable audit trail.

**`status` ENUM on both assignments and submissions**
- `assignments.status`: `draft | published | closed`
- `submissions.status`: `submitted | graded | returned`

### 2.3 Join Query for Student Submission Status

The portal uses a single LEFT JOIN query to get all assignments with their submission status in one round trip:

```sql
SELECT
    a.*,
    c.title  AS course_title,
    c.code   AS course_code,
    u.name   AS lecturer_name,
    s.id     AS submission_id,
    s.status AS submission_status,
    s.score  AS submission_score,
    s.is_late,
    s.submitted_at,
    CASE
        WHEN s.id IS NOT NULL                        THEN 'submitted'
        WHEN a.deadline < NOW() AND a.allow_late = 0 THEN 'closed'
        WHEN a.deadline < NOW() AND a.allow_late = 1 THEN 'late'
        ELSE 'pending'
    END AS display_status
FROM   assignments   a
JOIN   courses       c ON c.id  = a.course_id
JOIN   enrollments   e ON e.course_id = a.course_id AND e.student_id = ?
JOIN   users         u ON u.id  = a.lecturer_id
LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
WHERE  a.status != 'draft'
ORDER  BY a.deadline ASC
```

The `LEFT JOIN` ensures unsubmitted assignments still appear. The `CASE` expression derives display state in SQL, avoiding multiple queries.

---

## 3. Code for Deadline Enforcement

Deadline enforcement is implemented at **three layers**:

### Layer 1 — Database (`assignments.deadline` column + DATETIME NOW())
Queries that generate `display_status` use `a.deadline < NOW()` natively in SQL, so the database clock is authoritative.

### Layer 2 — PHP server-side (pre-render + pre-insert)

```php
// Assignment model
public static function isAcceptingSubmissions(array $assignment): bool
{
    if ($assignment['status'] !== 'published') return false;
    if (!self::isPastDeadline($assignment))    return true;   // before deadline
    return (bool) $assignment['allow_late'];                  // past but late OK
}

// submit.php — re-check at POST time (race condition prevention)
if (!Assignment::isAcceptingSubmissions($assignment)) {
    $errors[] = 'Deadline has passed and late submissions are not allowed.';
}
```

The re-check at POST time prevents a race condition where a student loads the form before the deadline but submits after it.

### Layer 3 — JavaScript (UX countdown, non-authoritative)

```javascript
document.querySelectorAll('[data-deadline]').forEach(el => {
    const dl = new Date(el.dataset.deadline).getTime();
    function tick() {
        const diff = dl - Date.now();
        if (diff <= 0) { el.textContent = 'Deadline passed'; return; }
        // ... format and display remaining time
        setTimeout(tick, 30000);
    }
    tick();
});
```

JS only updates the UI label; all enforcement is server-side.

### Late Submission Penalty

```php
// Stored in DB; penalty applied at grading time
'is_late' => $isLate ? 1 : 0,

// Lecturer is shown the is_late flag; deduction is manual or automated
// Suggested automation (in grading controller):
if ($submission['is_late']) {
    $effectiveScore = $score * (1 - $assignment['late_penalty'] / 100);
}
```

---

## 4. MIME Type Validation Implementation

```php
private function detectMime(string $tmpPath, string $ext): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // ZIP magic bytes can map to several MIME types depending on OS/PHP version
        if ($ext === 'zip' &&
            in_array($mime, ['application/octet-stream',
                             'application/x-zip-compressed',
                             'application/zip'], true)) {
            return 'application/zip'; // normalise
        }
        return $mime;
    }
    // Fallback map (when finfo unavailable)
    return ['pdf'=>'application/pdf', 'doc'=>'application/msword',
            'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip'=>'application/zip'][$ext] ?? 'application/octet-stream';
}
```

The normalisation of ZIP MIME types is necessary because `finfo` may return `application/octet-stream` for ZIP on some platforms, while the whitelist contains `application/zip`.

---

## 5. Term Report Component

### 5.1 Storage Management Strategy

**Directory structure**
```
public/uploads/
  assignments/    ← lecturer-attached briefs
  submissions/    ← student work
```

**Naming convention**
`{uniqid}_{sanitised_original_name}.ext`

Example: `67a3f1b2e8c4d_Assignment_Brief_PHP.pdf`

**Rotation / cleanup**
- Submissions are permanent (audit trail).
- A scheduled cron can archive submissions older than N semesters to cold storage (e.g. AWS S3 Glacier) and remove local copies.
- Disk quota monitoring: `du -sh public/uploads/` via cron + alert email when > 80% of partition.

**Size limits enforced at multiple levels**
1. `php.ini` / `.htaccess`: `upload_max_filesize = 10M`, `post_max_size = 12M`
2. PHP `FileUploader`: `$file['size'] > UPLOAD_MAX_SIZE`
3. DB: `file_size INT UNSIGNED` column (informational)

---

### 5.2 Handling Concurrent Submissions

**Race condition scenario:**
Two browser tabs submit the same assignment at the same millisecond.

**Mitigation stack:**

1. **Database unique constraint** (primary defence):
   ```sql
   UNIQUE KEY unique_submission (assignment_id, student_id)
   ```
   The second INSERT will throw `PDOException` with SQLSTATE 23000. The application catches this and returns a user-friendly error.

2. **Application-level duplicate check** (`existsByStudentAndAssignment()`):
   Checked before processing the file upload, avoiding wasted I/O for obvious duplicates.

3. **CSRF token** prevents automated repeat submissions from scripts.

4. **POST-redirect-GET pattern**: After a successful submission, users are redirected to the submissions list. Refreshing the page cannot resubmit.

**File orphan cleanup:**
If the DB insert fails after a file was already moved, the orphaned file is cleaned up:
```php
if ($dbInsertFailed && file_exists($destination)) {
    unlink($destination); // rollback file
}
```

---

### 5.3 Backup and Recovery Considerations

**Database backup**
```bash
# Daily full dump
mysqldump -u root -p assignment_portal \
  --single-transaction --routines --triggers \
  > /backups/db/ap_$(date +%Y%m%d).sql

# Retain 30 days
find /backups/db/ -name "*.sql" -mtime +30 -delete
```

**File backup**
```bash
# Rsync uploads to secondary server nightly
rsync -avz --checksum /var/www/html/assignment_portal/public/uploads/ \
  backup@secondary:/backups/uploads/

# Or S3 sync
aws s3 sync public/uploads/ s3://ap-backups/uploads/ --storage-class STANDARD_IA
```

**Recovery procedure**

| Scenario | Recovery |
|---|---|
| DB corruption | Restore from latest `.sql` dump; re-import |
| Accidental row delete | Point-in-time recovery with binary logs (`mysqlbinlog`) |
| Upload folder deleted | Restore from rsync/S3 backup; paths in DB remain valid |
| Single file lost | Restore individual file from backup by `stored_name` column |

**Transaction safety**
For the submission workflow, a DB transaction wraps the INSERT:
```php
$pdo->beginTransaction();
try {
    // INSERT submission row
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    unlink($movedFile); // also rollback file
    throw $e;
}
```

---

## 6. Project Folder Structure

```
assignment_portal/
├── database/
│   └── schema.sql                  ← Full DB schema + seed data
├── docs/
│   └── technical_report.md         ← This document
├── public/                         ← Web root (point Apache/Nginx here)
│   ├── .htaccess                   ← PHP upload limits + security
│   ├── index.php                   ← Entry point / redirect
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   ├── uploads/
│   │   ├── .htaccess               ← Block PHP execution
│   │   ├── assignments/            ← Lecturer-attached files
│   │   └── submissions/            ← Student work
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   └── logout.php
│   ├── lecturer/
│   │   ├── dashboard.php
│   │   ├── assignments.php
│   │   ├── create_assignment.php
│   │   ├── edit_assignment.php
│   │   ├── submissions.php
│   │   └── view_submission.php
│   └── student/
│       ├── dashboard.php
│       ├── assignments.php
│       ├── submit.php
│       └── submissions.php
└── src/
    ├── config/
    │   ├── database.php            ← Constants (DB creds, upload limits)
    │   └── Database.php            ← PDO singleton
    ├── middleware/
    │   └── Auth.php                ← Session, CSRF, role guard
    └── models/
        ├── Assignment.php          ← CRUD + deadline logic
        ├── Submission.php          ← CRUD + grading
        ├── User.php                ← Auth + course queries
        └── FileUploader.php        ← CGI-principled upload handler
```
