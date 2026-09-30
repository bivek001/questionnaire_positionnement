# Corrective package — Step 152 remains on hold

Prepared from the latest local source at `C:\xampp\htdocs\questionnaire_positionnement` on 30 September 2026. The live MySQL service was unavailable during inspection. Schema verification used the project's latest saved export, `updated sql/20260929 questionnaire_db.sql`, plus two isolated MariaDB 10.4.32 databases. The live database and installed project have not been modified.

## Install in one operation

1. Extract `questionnaire-corrective-package.zip` into a folder outside `htdocs`. Keep `Install-CorrectivePackage.ps1`, `Backup-Database.php`, `changed-files.json`, and the `questionnaire_positionnement` folder together.
2. Start XAMPP MySQL. Close questionnaire editing/submission pages during installation.
3. Run the installer in PowerShell:

```powershell
& .\Install-CorrectivePackage.ps1 -ProjectPath 'C:\xampp\htdocs\questionnaire_positionnement' -PhpPath 'C:\xampp\php\php.exe'
```

The installer checks every changed file against the inspected baseline, saves the original changed files and a database schema/data/trigger snapshot beside the extracted package, runs the idempotent migration against your existing database configuration, and then copies the corrected files. It stops before copying if MySQL is unavailable, the migration fails, or existing files have changed. Review the reported file instead of bypassing a baseline mismatch.

The package does not replace `config/database.php`, `vendor`, `uploads`, `.git`, or your existing SQL exports. Keep the installed dependencies and media. The included source folder is an update for the existing application, rather than a fresh installation.

## Migration order and alternative installation

1. Required: `migrations/20260930_package.php` OR its equivalent `migrations/20260930_package.sql`, once before installing the new application files. Do not run both unless verifying idempotence. The installer runs the PHP version automatically. To use phpMyAdmin instead, select the existing `questionnaire_db` and import the SQL file. Use an account permitted to ALTER, CREATE TABLE, CREATE/DROP TRIGGER. Existing rows and grades are not regraded or rewritten.
2. Copy the application files after successful migration. Use the installer to avoid copying files individually. The SQL file adds missing question support fields, response suggestion fields, scope indexes, and persistent notifications. Notification recipients span three account tables, so no invalid polymorphic FK is introduced.
3. Optional: `migrations/normalize_paragraphs.php` reports encoded paragraph rows without writing. After reviewing its count, `--apply` sanitizes/normalizes those rows. This is unnecessary for correct display: editor, write paths and renders already normalize safely. Back up before applying it. Intentional `<pre>`/`<code>` examples remain escaped.

No older migration or database dump needs to be reimported. Never import the saved export over the live database to install this update.

## Behavior

- Active professors create themes and immediately receive a NULL-chapter whole-theme grant. Whole-theme grants allow descendants and shared theme editing. Chapter grants allow that chapter and its descendants, not shared theme editing or creating sibling chapters.
- Result access follows course scope for every trainee, including available and unassigned attempts. Chapter-only attempts require at least one response in the authorized chapter; outside answers, grades and full totals are excluded. A full PDF requires whole-theme access. Chapter-only questionnaire-assignment metadata is not exposed by the status page.
- Print and PDF actions require a completed, corrected **Final** attempt with all open answers human reviewed. Provisional, Initial and Exit attempts display the reason printing is unavailable. This retains the existing Final PDF rule. Buttons and download endpoints use the same eligibility.
- Open criteria: one equally weighted line per criterion, `|` separates acceptable alternatives. Unicode lowercase, accent folding and punctuation/space normalization precede whole-word/phrase matching. For `k` matched criteria among `n`, use `0` if `k=0`, otherwise `ceil(4*k/n)/4`; multiply by maximum points and round to two decimals. Thus bands are exactly 0/25/50/75/100%. For five criteria, coverage 1/5→25%, 2/5→50%, 3/5→75%, 4/5→100%, 5/5→100%. Authors see this rule in EN/FR. Keywords test textual coverage; rubric prose is reviewer guidance. With no criteria, an exact normalized model-answer match suggests full points; other answers require manual review.
- Suggestions and context are saved once at submission. Provisional points initialize `awarded_points`; `graded_at` remains NULL until human review. Reviewers see matched/missing criteria and can override. Later automatic calls cannot overwrite a human grade. Human grading recalculates the full attempt total; `corrected_at` is first set when all open answers have been reviewed and the attempt is completed. Existing used questions cannot be structurally rewritten in ways that corrupt historical answers; create a new question for a revised assessment.
- Admin navigation separates account management from results. Profile edits retain email/username validation and professor activation. Password fields set a new hash with confirmation; no existing password is recoverable/displayed, and outstanding reset tokens are invalidated after an Admin password reset.
- Persistent alerts cover account/password changes, assignments, completion, grading, course-access changes and new course content. Admin has a shared activity inbox; professors receive course-scoped activity alerts, trainees receive their own alerts. No passwords, answers or sensitive profile values appear in messages. Read actions require POST and CSRF; links use an allowlist and destination authorization.
- All password-entry pages load the shared show/hide control, with accessible labels, `aria-pressed` and `aria-controls`; default input type remains password. Existing EN/FR labels and DB-authored content are retained. Older Admin forms and trainee submission now enforce CSRF.

## PASS — isolated automated verification

- PHP syntax: all 92 application PHP files, plus the database snapshot helper.
- Shared UI JavaScript syntax.
- 59 HTTP checks: role dashboards, authoring pages, EN/FR pages, inactive professor rejection, unrelated theme/chapter/attempt ID tampering, scoped result display, authorized grading for an unassigned trainee, automatic whole-theme assignment, parent-ID tampering, account resets and notification CSRF/read actions.
- 14 additional HTTP checks: Admin encoded HTML save/preview, trainee course rendering, open question/criteria editing, multiple correct choices, real available-questionnaire submission, professor reset alerts, actual Admin/Professor PDF generation and print eligibility, chapter-only direct PDF denial after correction.
- Deterministic scoring bands, accents/decomposed accents and whole-word boundaries; human grade never overwritten; separate saved suggested/provisional points; correct attempt totals and pending/corrected state.
- Double-encoded paragraph normalization, script/event/javascript URL/SVG removal, and intentional code preservation.
- Notification recipient scoping, no outside-chapter grading alert, password hashing, token invalidation, and mark-read recipient isolation.
- PHP migration run twice successfully. SQL migration tested twice on a legacy schema missing every new question/response column. Paragraph data, historic attempt totals and awarded grades remained unchanged.
- Database snapshot helper completed on the isolated database; Admin and Professor generated real `%PDF` downloads using the installed Dompdf dependency.
- One-step installer completed on an isolated copy of the inspected application, including baseline checks, safety snapshot, migration and file installation. Installer PowerShell syntax passed.

## MANUAL TEST — after installation on your running XAMPP

Base URL: `http://localhost:8080/questionnaire_positionnement/` (use your configured Apache port).

| URL/action | Expected |
|---|---|
| `professor/themes.php` → Create theme | New theme immediately editable by its creator; other professors cannot access it. |
| `professor/manage_content.php`, `structure.php`, `questions.php`, `edit_question.php?id=...` | Create/edit the hierarchy and options in scope. Change IDs to another theme/chapter: denied. Test media uploads with real image/audio/video files. |
| `professor/trainees.php` → trainee → attempt | Unassigned trainees' course attempts appear; chapter-only view omits outside content and full totals. |
| `admin/manage_content.php` | Paste formatted paragraph content, save/reopen, and confirm formatting in both editor and preview. Check the trainee questionnaire and course page. |
| `questionnaire.php?theme_id=...`, assigned questionnaire | Submit 0/1/2/3/4 criteria matches; verify initial points, grader details, human override and total recalculation. Old attempts keep their historic grades. |
| `admin/attempt_details.php?id=...`, `professor/attempt_details.php?id=...` | Completed/corrected Final result has Print/Download. Pending/Initial/Exit explains unavailability. Chapter-only direct PDF access remains denied. Visually check the PDF and browser print layout. |
| `admin/trainees.php`, `admin/results.php` | Account management and results are separate navigation destinations. |
| `admin/edit_account.php?role=professor&id=...`, `role=trainee` | Edit profile, reject duplicate email/username, reset with matching password, retain professor activation behavior, affected user receives alert. |
| Admin/professor/trainee dashboard header | Badge/dropdown shows activity; mark one/all read; reload and confirm persistence. No sensitive values in alerts. |
| Login/register/change/reset and Admin set-password forms | Show/hide works with mouse and keyboard, correct EN/FR labels, password hidden initially. Test reset expiration and one-use tokens. |
| Representative pages at phone/tablet width | Forms, tables and notification dropdown fit; rich editor toolbar and Add/Remove Option work. |

Browser visual checks, actual media uploads, email delivery, live DB compatibility and installation are MANUAL TEST items; they were not presented as automated passes. Step 152 remains on hold until these checks pass.

## Changed files compared with the inspected live source

- `admin/assign_questionnaire.php`
- `admin/attempt_details.php`
- `admin/delete_trainee.php`
- `admin/edit_account.php`
- `admin/edit_question.php`
- `admin/edit_theme.php`
- `admin/forgot_password.php`
- `admin/levels.php`
- `admin/login.php`
- `admin/manage_content.php`
- `admin/manage_pages.php`
- `admin/professor_content.php`
- `admin/professor_trainees.php`
- `admin/professors.php`
- `admin/questions.php`
- `admin/reset_password.php`
- `admin/themes.php`
- `assets/js/ui.js`
- `change_password.php`
- `config/open_grading.php`
- `forgot_password.php`
- `includes/course_access.php`
- `includes/notifications.php`
- `includes/question_support.php`
- `includes/result_pdf.php`
- `index.php`
- `languages/en.php`
- `languages/fr.php`
- `migrations/20260930_package.php`
- `migrations/20260930_package.sql`
- `notifications.php`
- `professor/attempt_details.php`
- `professor/download_result_pdf.php`
- `professor/expansion_bootstrap.php`
- `professor/index.php`
- `professor/login.php`
- `professor/trainee_question_status.php`
- `professor/trainee_results.php`
- `professor/trainees.php`
- `questionnaire.php`
- `register.php`
- `reset_password.php`
- `submit.php`
- `trainee_login.php`

The full source update also carries the already-present authoring, sanitization, account management, grading and navigation components reused by this correction. `changed-files.json` supplies exact paths and baseline/package SHA-256 hashes for installation review.
