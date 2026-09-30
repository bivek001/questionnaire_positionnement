# Questionnaire Positionnement — Start here

A self-contained PHP questionnaire and course application for Admin, Professor and Trainee, with English/French UI.

**This folder contains all project files, libraries and supplied uploads. No Codex folder, Git checkout, shortcut or another application folder is needed.** PHP, Apache and MySQL/MariaDB remain required services. Uploaded content stays in `uploads/`; libraries stay in `vendor/`.

## Start on the existing computer

1. Keep this organized copy separate while checking it. Your running project has not been changed by this organization task.
2. To use it, stop Apache and preserve your existing project folder first. Extract the complete folder as `htdocs/questionnaire_positionnement`; do not merge it with the old folder, because obsolete SQL/backups would remain exposed at their former locations.
3. Keep your working database. Verify the connection values in `config/database.php`. **Do not import a database export over your existing database.**
4. Start Apache and MySQL, then open `http://localhost:8080/questionnaire_positionnement/` (change the port if needed).
5. Test Admin/Professor/Trainee login, course editing, a questionnaire, grading, notifications and a PDF. Existing data and historical scoring must remain intact.

For a different computer or an empty database, read `documentation/SETUP.md`. For the folder map and routes, read `documentation/FOLDER-GUIDE.md`. French instructions: `LISEZ_MOI.md`.

## Main folders

| Folder | Purpose |
|---|---|
| Root PHP pages | Trainee/public pages and questionnaire submission/results |
| `admin/` | Admin management, account editing, assignments and results |
| `professor/` | Professor authoring, authorized trainee attempts and grading |
| `assets/` | Shared CSS and JavaScript |
| `config/` | Database and automatic grading configuration |
| `includes/` | Shared security, translation, rich HTML, scoring, alerts and PDF helpers |
| `languages/` | EN/FR interface dictionaries |
| `uploads/` | Actual course/question media — keep these files |
| `vendor/` | Bundled Composer libraries — keep these files |
| `database/` | Portable export, migrations and preserved historical backups |
| `documentation/` | Setup, folder guide, validation and historical notes |
| `tools/` | Optional diagnostics and project checks |

The private folders have Apache `.htaccess` access restrictions. Retain them and ensure Apache allows them. A PHP development server does not enforce `.htaccess`; do not publicly serve this bundle with it. On a different web server, apply equivalent access restrictions before serving it.

The database export and backups contain real account data and password hashes. Keep the archive private. Their presence in this folder does not create a runtime dependency: PHP pages do not load backups or SQL exports.

The application pages and bilingual content were retained. The application URLs were not reorganized. Step 152 has not been started.
