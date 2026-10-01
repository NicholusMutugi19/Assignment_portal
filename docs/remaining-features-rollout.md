# Remaining portal features — staged rollout

## Features included in this code stage

- Optional student course fees and M-Pesa STK Push entry point.
- An unauthenticated Daraja callback endpoint protected by an environment token; callbacks are persisted to a retry inbox and processed idempotently.
- Paid course resources are checked against a successful payment for assignment lists, assignment submission, and downloads. Existing enrollment rows are not deleted; new paid-course enrollments default to pending payment.
- Lecturer approval status for new lecturer registrations when extensions are enabled; existing lecturer rows are approved by the migration. Approval decisions and user/course admin changes are audited.
- Basic role administration, lecturer approvals, user listing, course suspension, payment listing and dashboard analytics.
- Google Meet session creation and student view with enrollment, audience, published-course and successful-payment filtering.
- Portal-native assignments with short-answer, single-choice and multiple-choice questions, automatic scoring for choice questions, and manual grading for text.
- Course result ranking, CSV and browser print/PDF view; students only query their own published course results.
- Earlier education profile and course-audience stage remains separate and opt-in.

## Required migrations

1. Back up production and restore a staging clone. Do not use `database_dump.sql` as a migration: it contains destructive drop statements and snapshot user data.
2. Apply `001_education_course_audience.up.sql` once if not already applied.
3. Apply `002_remaining_features.up.sql` once. It adds nullable course prices. Existing course prices remain NULL; do not infer or backfill prices. Existing lecturers are marked approved.
4. Run staging verification and inspect existing enrollment, submission, and grade counts before and after. No live DB was reachable from the implementation workspace; neither migration was executed here.

## Rollout order

- Deploy code with `PORTAL_EXTENSIONS_ENABLED=false`, `MPESA_ENABLED=false`, and `EDUCATION_COURSE_TARGETING_ENABLED=false`.
- Verify legacy registration, login, assignments, grading, and existing enrollments.
- Enable `PORTAL_EXTENSIONS_ENABLED=true` in staging; exercise admin approvals, tutor access, online assignment creation/submission, Meet links, result privacy, fee setup and paid-resource access.
- Configure all Daraja secrets for sandbox, callback token, callback route and HTTPS; set `MPESA_ENABLED=true` in sandbox only. Test success, user cancellation, duplicate callback, delayed callback retry, amount mismatch, phone mismatch, and receipt uniqueness.
- After staging sign-off, enable flags gradually in production and observe error logs and payment callback inbox retry counts.
- Only use `MPESA_ENV=production` after Safaricom production credentials and callback URL have been provisioned and verified.

## Environment variables

- `PORTAL_EXTENSIONS_ENABLED=false` by default; enable only after migration 002.
- `EDUCATION_COURSE_TARGETING_ENABLED=false` by default; enable only after migration 001.
- `MPESA_ENABLED=false` by default; separate kill switch for payment initiation/callback processing.
- `MPESA_ENV=sandbox` or `production`.
- `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_CALLBACK_URL`, `MPESA_CALLBACK_SECRET` must be supplied by deployment secret storage, never committed.
- PHP cURL extension required. OAuth token caching uses APCu when available; otherwise token is fetched per call.

## Access / payment policy

Students may enroll before payment. New paid-course enrollment rows use `pending_payment`. Course resource visibility is determined by a successful payment record, not by a client-supplied amount or enrollment status. The checkout amount is read from the course fee in the DB. Existing course fees are NULL and remain free/unpriced until set by an authorized course owner. Changing a course from free to paid locks its resources for users with no successful payment; review this policy with users before enabling fees on existing courses.

The callback endpoint token is a shared secret, not a provider signature. It must be long/random and stored securely. For production, prefer a verified provider signature, network/proxy restrictions where Safaricom supports them, and reconciliation with the Daraja transaction query API; do not treat the shared token alone as proof that a callback originated from Safaricom.

## Rollback

Disable `MPESA_ENABLED`, `PORTAL_EXTENSIONS_ENABLED`, and education targeting, then roll back the application code. Down migration files are intentionally application-only and do not delete collected payments, profiles, answers, audits, or approval history. Do not drop added columns/tables as a routine rollback. Take a backup before any separately approved data-retention cleanup.

## Limitations to verify before production

- No live or staging database migration or Daraja transaction has been executed from this workspace.
- Admin bootstrap: provision the first `admin` account through a reviewed database operator procedure; public self-registration does not allow the admin role.
- Payment callback token is a shared query-token safeguard, not a provider cryptographic signature. Add Safaricom-supported verification/reconciliation and edge protections before live-money use.
- No background worker is installed. The callback inbox records exponential retry timing; arrange a scheduled worker to re-process pending callback records or monitor and process them operationally.
- The course session/result/admin interfaces are included in this stage but require full staging integration/security testing before production.
