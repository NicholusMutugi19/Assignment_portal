# Assignment Portal

A full-featured PHP & MySQL assignment submission portal with separate lecturer and student dashboards, secure file uploads, deadline enforcement, and grading.

---

## Quick Start

### Requirements
- PHP 8.0+ (with `fileinfo`, `pdo_mysql` extensions)
- MySQL 8.0+ or MariaDB 10.5+
- Apache with `mod_rewrite` enabled (or Nginx with PHP-FPM)
- XAMPP / WAMP / MAMP works perfectly

---

### 1. Database Setup

```bash
mysql -u root -p < database/schema.sql
```

This creates the `assignment_portal` database, all tables, and sample data including:
- 1 lecturer account
- 3 student accounts
- 3 courses with enrollments
- 4 sample assignments

---

### 2. Configure Database Credentials

Edit `src/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'assignment_portal');
define('DB_USER', 'root');       // ← your MySQL user
define('DB_PASS', '');           // ← your MySQL password
```

Also update `APP_URL` to match your server:
```php
define('APP_URL', 'http://localhost/assignment_portal/public');
```

---

### 3. Set Upload Directory Permissions

```bash
chmod -R 755 public/uploads/
```

On Linux/Mac with Apache:
```bash
chown -R www-data:www-data public/uploads/
```

---

### 4. Point Web Server to `public/`

**Apache Virtual Host:**
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/assignment_portal/public
    ServerName assignment-portal.local
    <Directory /path/to/assignment_portal/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**XAMPP (quick setup):** Place the whole `assignment_portal/` folder inside `htdocs/`, then visit:
`http://localhost/assignment_portal/public/`

---

### 5. Demo Credentials

| Role     | Email                      | Password    |
|----------|----------------------------|-------------|
| Lecturer | lecturer@portal.ac.ke      | password123 |
| Student  | alice@student.ac.ke        | password123 |
| Student  | brian@student.ac.ke        | password123 |
| Student  | carol@student.ac.ke        | password123 |

---

## Features

### Lecturer
- ✅ Dashboard with submission stats
- ✅ Create assignments with deadlines
- ✅ Attach assignment brief (PDF/DOC/ZIP)
- ✅ Configure late submission policy + penalty %
- ✅ View all submissions per assignment
- ✅ Inline grading + feedback
- ✅ Edit assignments (title, deadline, status)

### Student
- ✅ Dashboard showing pending / graded assignments
- ✅ Live deadline countdown
- ✅ Drag-and-drop file upload
- ✅ MIME type validation (client hint + server magic bytes)
- ✅ Late submission warning with penalty display
- ✅ View grades and lecturer feedback
- ✅ Submission history

### Security
- ✅ CSRF protection on all POST forms
- ✅ Role-based access control
- ✅ `is_uploaded_file()` verification
- ✅ Magic-byte MIME detection (not browser header)
- ✅ Filename sanitisation + random prefix storage
- ✅ PHP execution blocked in upload directories
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ `htmlspecialchars()` on all output (XSS prevention)

---

## File Upload Specs

| Property | Value |
|---|---|
| Max file size | 10 MB |
| Allowed types | PDF, DOC, DOCX, ZIP |
| MIME validation | Server-side magic bytes |
| Storage | `public/uploads/submissions/` |
| Naming | `{uniqid}_{sanitised_name}.ext` |

---

## Project Structure

```
assignment_portal/
├── database/schema.sql
├── docs/technical_report.md
├── public/                  ← Web root
│   ├── auth/
│   ├── lecturer/
│   ├── student/
│   ├── css/ js/ uploads/
│   └── index.php
└── src/
    ├── config/
    ├── middleware/
    └── models/
```

---

## Troubleshooting

**Upload fails with "Could not save file"**
→ Check that `public/uploads/submissions/` is writable by the web server user.

**Blank page / 500 error**
→ Enable PHP error display: add `ini_set('display_errors', 1);` to `src/config/database.php` temporarily.

**CSS not loading**
→ Verify `APP_URL` in `src/config/database.php` matches your actual URL.

**"DB Connection failed"**
→ Confirm MySQL is running and credentials in `src/config/database.php` are correct.
