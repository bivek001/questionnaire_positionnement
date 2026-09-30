# Organization validation / Vérification de l’organisation

Prepared on 1 October 2026 from the supplied `questionnaire_positionnement.rar`.

PASS:

- Original archive paths inspected for traversal and symbolic/hard links before extraction.
- Complete `vendor/` libraries and `uploads/` media retained as local files.
- No filesystem symbolic links, directory junctions or Git checkout in the delivered folder.
- Runtime PHP/JS/CSS checked for hard-coded external machine/project paths.
- 92 application PHP files passed syntax checks. Historical backup PHP and third-party source were excluded from the application syntax count.
- Dompdf, PHPMailer and Google Client autoload from the copied project's own `vendor/` directory.
- Homepage, Admin/Professor/Trainee login and French registration pages opened from the relocated copy without detected PHP errors.
- Moved asset diagnostic finds local project assets.
- Current database export restored into an isolated empty database; all 25 table data sets matched the source and 14 activity triggers were restored.
- Migration PHP scripts' local configuration/helper paths updated for `database/migrations/`.

Remaining checks when you install this organized copy:

- Verify Apache rejects access to private `database/`, `config/`, `documentation/` and `tools/` folders. `.htaccess` files are included, but the temporary PHP server does not enforce them.
- Repeat authenticated authoring, grading, notification, upload and PDF workflows on your chosen server. These were tested for the preceding corrective installation; this organization task used page smoke tests and local-library verification.
- Configure database/mail/base URL on any different computer.

The running XAMPP folder and database were not changed by this organization task. The export was read-only; restoration tests used an isolated database. Step 152 was not started.
