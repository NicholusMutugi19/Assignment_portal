# Education and course audience rollout

## Scope implemented in this stage

- Students can provide Campus/University/College or High School, institution name, and year/form.
- Existing profiles are nullable; students with missing values are redirected to a profile form only after the rollout flag is enabled.
- Lecturers can create custom course drafts, select Campus only / High School only / Both, and publish their own drafts.
- Course discovery, enrollment, assignment lists, assignment submit routes, and assignment downloads check audience eligibility after rollout.
- Course selection adds new enrollments only. It does not delete or rewrite existing enrollment data.
- Existing catalog creation remains available while the feature flag is off; when on, catalog seeding on student page requests is disabled.

## Staging and production procedure

1. Take and verify a database backup using the hosting provider's supported backup process. Preserve a separate copy of any configuration and callback secrets.
2. Restore the backup to a staging clone. Do not use the checked-in database dump as a production migration; it contains destructive `DROP TABLE` statements and snapshot data.
3. Apply `database/migrations/001_education_course_audience.up.sql` once to staging. Inspect users, courses, row counts, and existing enrollments before and after. Existing users remain without education values; existing courses default to `both` and `published`.
4. Deploy application code with `EDUCATION_COURSE_TARGETING_ENABLED=false`. Verify login, registration, catalog, enrollment, assignments, and downloads.
5. In staging, set the flag to `true`; test both education levels, profile completion for an existing student, audience filtering, course ownership restrictions, draft visibility, and direct assignment/download URLs. Verify existing enrollments are not deleted.
6. Only after staging sign-off, repeat the verified backup and migration procedure for production. Set the flag to `true` gradually and monitor PHP/application logs and database errors.

## Feature flag

`EDUCATION_COURSE_TARGETING_ENABLED=false` (default) preserves old page behavior. Set it to `true` only after migration 001 has been applied and verified.

## Rollback

Set `EDUCATION_COURSE_TARGETING_ENABLED=false` and roll back the application release. The down script intentionally does not drop columns or data. New profile values, course audience, and course metadata remain stored. Do not execute `DROP TABLE`/`DROP COLUMN` as a routine rollback.

## Not enabled / not complete in this stage

There is no production-ready Daraja API client/callback, payment reconciliation, admin role/dashboard, lecturer approval enforcement, Meet-session UI, results ranking, export/print, or notifications in this release. These features are not represented as implemented or deployable by this migration. Existing and newly registering lecturers continue to use the current behavior in this stage.

## Hosting uncertainty

No staging credentials or database configuration were available in this workspace. No live or staging migration was run. Verify the deployment host's PHP/MySQL versions and its normal release procedure before production rollout.
