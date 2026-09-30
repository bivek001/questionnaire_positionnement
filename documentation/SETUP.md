# Installation and portability / Installation et portabilité

## Required services / Services nécessaires

Use the currently working XAMPP stack or a compatible PHP 8.2+ and MariaDB/MySQL installation. Enable PDO MySQL, mbstring, DOM/libxml, fileinfo, and the extensions used by the bundled Composer libraries. `vendor/` is already included; downloading Composer packages is unnecessary for this copy. Keep `composer.lock` for future controlled library updates.

Utilisez votre installation XAMPP fonctionnelle ou un serveur compatible PHP 8.2+ et MariaDB/MySQL. Les bibliothèques sont déjà présentes dans `vendor/`.

## Existing database / Base existante

Keep the existing database and configure `config/database.php`. The organized application's runtime uses that database, not an SQL file. No data import is required merely to use the organized folder. Existing EN/FR dictionaries and DB-authored course content remain intact.

Conservez votre base actuelle et vérifiez `config/database.php`. Aucun import n’est nécessaire pour utiliser le dossier organisé.

## New computer / Nouvel ordinateur

1. Copy the whole `questionnaire_positionnement` folder into the new server's document root. Start PHP/Apache and MySQL/MariaDB.
2. Create an **empty** database named `questionnaire_db`, with `utf8mb4` encoding, or use another name and adjust `config/database.php`.
3. Import `database/exports/questionnaire_db.sql` into that empty database using phpMyAdmin. It is a complete private schema/data/trigger snapshot; it does not create or select the database for you. Keep it out of public download access.
4. Set host, database name, username and password in `config/database.php`. Ensure the importing/database account can create the included triggers. Different MySQL/MariaDB versions may require compatibility review.
5. If the export already contains the corrective columns and triggers, no migration is needed. For an older database, use the idempotent `database/migrations/20260930_package.php` or `.sql` after backing it up; do not import a historical dump over it.
6. Configure mail/base URL for password-recovery delivery as described in `professor/recovery_config.php`. Environment variables refer to service configuration, not another application folder. Verify the Admin and Trainee mail/reset settings before relying on email delivery on the new computer.
7. Check the three role logins, authoring, chapter scope, submissions, grading and PDF generation. Do not assume previous live-computer checks prove the new computer works.

Sur un nouvel ordinateur : créez une base **vide**, importez `database/exports/questionnaire_db.sql`, configurez `config/database.php`, puis vérifiez les trois rôles et les PDF. N’importez jamais cet export par-dessus une base en cours d’utilisation.

## Optional checks / Vérifications facultatives

From the project folder:

```powershell
& .\tools\Check-Project.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

For an older database only, from the project folder:

```powershell
& 'C:\xampp\php\php.exe' .\database\migrations\20260930_package.php
```

`database/migrations/normalize_paragraphs.php` is optional and read-only by default; `--apply` changes encoded paragraph rows after a backup. The runtime already sanitizes them for display.

These example executable paths belong to XAMPP; the project source does not hard-code another project folder. Use your PHP executable's path on a different computer.

## Private file protection / Protection des fichiers privés

`database/`, `documentation/`, `tools/` and `config/` deny HTTP access using their `.htaccess` files. Apache must permit access-control overrides. Confirm an unauthenticated request for `database/exports/questionnaire_db.sql` is rejected before making the application available to others. Do not publish this private data archive. The source archive preserves the old backups under `database/backups/`; they are for recovery, not execution.

Step 152 is still on hold; this is a folder-organization and portability delivery.
