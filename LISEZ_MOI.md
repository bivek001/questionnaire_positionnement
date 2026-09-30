# Questionnaire Positionnement — Commencer ici

Application PHP de cours et de questionnaires pour Administrateur, Professeur et Stagiaire, avec interface français/anglais.

**Ce dossier contient tous les fichiers du projet, les bibliothèques et les médias fournis. Il ne dépend pas du dossier Codex, d’un dépôt Git, d’un raccourci ou d’un autre dossier d’application.** Apache/PHP et MySQL/MariaDB restent nécessaires.

## Sur votre ordinateur actuel

1. Conservez cette copie organisée séparément pendant la vérification. Le projet actuellement installé n’a pas été modifié par cette réorganisation.
2. Pour la mettre en service, arrêtez Apache et conservez d’abord votre ancien dossier. Extrayez le dossier complet dans `htdocs/questionnaire_positionnement`. Ne fusionnez pas les deux dossiers : les anciens exports SQL et sauvegardes resteraient à leurs anciens emplacements.
3. Gardez votre base existante. Vérifiez `config/database.php`. **N’importez pas d’export par-dessus votre base actuelle.**
4. Démarrez Apache et MySQL. Ouvrez `http://localhost:8080/questionnaire_positionnement/`, avec votre port habituel.
5. Vérifiez les connexions des trois rôles, la création des cours, les questionnaires, les notes, les notifications et les PDF.

## Organisation

- `admin/` : gestion par l’administrateur.
- `professor/` : cours autorisés, tentatives et correction.
- Pages PHP à la racine : espace stagiaire et pages publiques.
- `assets/`, `includes/`, `languages/` : présentation, fonctions communes et traductions.
- `config/` : configuration de la base et de la notation.
- `uploads/` : médias. `vendor/` : bibliothèques intégrées.
- `database/` : export portable, migrations, sauvegardes et anciens exports.
- `documentation/` : installation, guide et historique.
- `tools/` : vérifications facultatives.

Les dossiers privés sont protégés par `.htaccess` sous Apache. Conservez ces protections et activez leur prise en compte. Le serveur de développement PHP ne les applique pas ; ne rendez pas ce dossier public avec ce serveur. Configurez des restrictions équivalentes avec tout autre serveur.

Les exports et sauvegardes contiennent des données personnelles et des hachages de mots de passe. Gardez l’archive privée. Le fonctionnement de l’application ne dépend pas de ces sauvegardes.

Les URL de l’application, les médias et l’interface bilingue sont conservés. L’étape 152 n’a pas été commencée.
