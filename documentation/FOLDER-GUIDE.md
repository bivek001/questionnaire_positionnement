# Folder guide / Guide des dossiers

## Runtime / Application

- Root: `index.php`, login/register/recovery pages, course navigation, questionnaires, submission, history and results. Existing page URLs stay the same.
- `admin/`: themes/content/questions, account management, assignments, results, grading and PDF endpoints.
- `professor/`: active-account authentication, course scope/authoring, authorized trainee attempts, grading and PDFs.
- `includes/`: reusable PHP helpers. `course_access.php` scopes result totals; `safe_rich_content.php` sanitizes rich HTML; `question_support.php` stores automatic suggestions; `notifications.php` renders role inboxes; `result_pdf.php` creates PDFs.
- `config/`: database connection and deterministic automatic scoring settings. Professor mail/base-URL configuration remains with the professor recovery module.
- `languages/`: English/French UI labels. Database course content is not auto-translated.
- `assets/`: CSS and shared JavaScript, including password visibility and question-choice controls.
- `uploads/`: supplied media files. This folder must be writable for new uploads.
- `vendor/`: complete bundled third-party dependencies. Composer autoload resolves files inside this project.

## Supporting material / Documents et données

- `database/exports/questionnaire_db.sql`: current complete private database snapshot for restoration into an empty database.
- `database/migrations/`: idempotent schema updates and optional paragraph normalization.
- `database/history/sql-exports/`: original uploaded SQL exports, preserved as history. Do not use them as the default installation database.
- `database/backups/installation-snapshot-20260930-234234/`: original pre-installation safety snapshot, including original changed files. These copies are not loaded by the application.
- `documentation/history/`: historical corrective-package notes and old Git helpers, preserved as `.txt` where appropriate. Their old paths describe past work, not current dependencies.
- `tools/`: optional checks and diagnostics, excluded from public HTTP access.

## Page entry points / Pages principales

| Role | Login | Main area |
|---|---|---|
| Admin / Administrateur | `admin/login.php` | `admin/index.php` |
| Professor / Professeur | `professor/login.php` | `professor/index.php` |
| Trainee / Stagiaire | `trainee_login.php` | `index.php` |

Admin accounts and trainee results have distinct pages. Professor result authorization follows course content rather than explicit trainee assignment. Full result PDFs require whole-theme access for professors.

## Safe maintenance / Maintenance

Keep `vendor/`, `uploads/`, `config/`, `includes/`, `languages/` and role folders together. Back up the database and media before replacing application files. Do not move individual runtime PHP pages without also reviewing their includes, redirects and URLs.

The delivered ZIP contains regular files and folders, without `.git`, editor metadata, symbolic links or external directory junctions. Nothing in the runtime requires the original RAR or the Codex workspace. The folder can be copied elsewhere together with its database export, then configured for that server.
