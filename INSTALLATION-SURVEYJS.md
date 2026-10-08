# Installation de la mise à jour SurveyJS

Cette livraison met à jour le projet existant `questionnaire_positionnement`. Elle conserve les comptes, les rôles, les anciennes questions et les résultats historiques. Elle ne remplace pas la base par une base vide.

## Installer dans XAMPP

1. Conserver les sauvegardes livrées et faire une nouvelle sauvegarde si le site a été utilisé depuis leur création. Arrêter temporairement l’accès des utilisateurs pendant la copie.
2. Extraire l’archive. Copier le contenu du dossier `questionnaire_positionnement` dans `C:\xampp\htdocs\questionnaire_positionnement`, en remplaçant les fichiers de même nom. **Conserver votre fichier actuel `config\database.php`, vos dossiers `vendor`, `node_modules` et vos fichiers téléchargés dans `uploads`.** Ne pas supprimer ces dossiers. Les bibliothèques SurveyJS nécessaires à cette version sont fournies dans `assets\survey\vendor`.
3. Démarrer MySQL et Apache dans XAMPP. Dans PowerShell, exécuter :

```powershell
Set-Location C:\xampp\htdocs\questionnaire_positionnement
& C:\xampp\php\php.exe tools\install_surveyjs.php 11 1 --publish
```

`11` est le thème de positionnement retrouvé dans votre projet ; `1` est l’administrateur existant. Si ces identifiants ont changé, utiliser les identifiants réels. L’outil vérifie leur existence. Il crée les quatre tables SurveyJS si nécessaire et importe le questionnaire. Il n’efface aucune ancienne question, réponse ou passation. Une nouvelle publication archive la définition précédemment publiée, sans supprimer les résultats qui y sont associés.

4. Se connecter normalement en administrateur ou formateur. Ouvrir [le créateur](http://localhost:8080/questionnaire_positionnement/survey_creator.php?theme_id=11). Les menus d’administration et formateur contiennent désormais les liens « Création de questionnaire » et « Résultats et corrections SurveyJS ».
5. Avec un compte stagiaire, ouvrir [le questionnaire](http://localhost:8080/questionnaire_positionnement/questionnaire.php?theme_id=11). Les affectations habituelles passent par le même formulaire. Les thèmes sans questionnaire SurveyJS publié continuent à utiliser le formulaire historique.

Pour importer en brouillon, omettre `--publish`, puis publier depuis le créateur. Si le même fichier source a déjà été importé, l’outil ne le réimporte pas ; ouvrir sa définition dans le créateur pour changer son état.

## Vérifications après installation

- Enregistrer une modification dans le créateur : le numéro de version enregistré doit apparaître.
- Dans la première situation 1b.2 : mail = 1 point, courrier = 2 points, SMS = 3 points, sur 3. Ce sont des notes, jamais des rangs.
- Terminer une passation : un résultat provisoire apparaît avec les critères à corriger.
- Corriger une réponse depuis « Résultats et corrections SurveyJS » : la note et la compétence concernée se recalculent.
- Vérifier que les anciennes passations restent accessibles et que les comptes existants se connectent toujours.

Le contrôle du moteur peut aussi être lancé :

```powershell
& C:\xampp\php\php.exe tools\test_survey_engine.php
```

## Contenu importé et correction

L’import contient 148 entrées d’évaluation issues des critères du Word, 150 questions SurveyJS (le formulaire de stock contient sept champs) et 129 pages, introduction comprise. Le détail se trouve dans `database\seeds\source-manifest.json`. Les sous-questions d’un exercice partagé restent regroupées dans leur contexte original ; certaines réponses sont saisies dans un champ rédigé commun au critère.

Les listes de choix simples identifiées sans ambiguïté utilisent des boutons radio ou des cases à cocher. Le stock utilise un formulaire à plusieurs champs ; l’exercice 10.2 utilise un classement. Les autres exercices, textes, tableaux et illustrations sont présentés avec des champs de réponse rédigée. La correction est manuelle sauf la première situation 1b.2 confirmée par vous. Le corrigé Word conserve ses couleurs et sa mise en page et reste téléchargeable par les formateurs autorisés.

Les situations 2 et 3 de 1b.2 restent à corriger manuellement : les chiffres du corrigé sont ambigus. Les critères 1a.7 et 1a.8 partagent une seule note sur 3, pour éviter de doubler leur barème. Les critères 1d.2 et 1d.3 sont qualitatifs, sans maximum numérique inventé : le formateur valide avec une note technique de 0 et inscrit son appréciation dans le commentaire. Les exercices pratiques et les tests externes restent évalués par le formateur ; aucune question provenant d’une annexe absente ou d’un site externe n’a été inventée.

Le total numérique importé est de 491 points. Il exclut les deux critères qualitatifs. Un résultat demeure provisoire tant qu’un critère nécessite une correction. Les seuils « Maitrisé / A optimiser » suivent ici l’obtention de la totalité des points ; les barèmes originaux restent affichés au correcteur. Le commentaire conserve les appréciations qualitatives.

## Utiliser le créateur universel

Les propriétés de notation sont dans la catégorie « Notation » : code de question, compétence, note maximale, méthode de correction, barème source et correction réservée au formateur. Les points de chaque choix sont modifiables sur l’option correspondante.

- `choice` : points du choix sélectionné.
- `sumChoices` : somme des choix sélectionnés, limitée au maximum.
- `exactMatch` : réponse exacte ; pour les cases à cocher, l’ordre des choix ne compte pas.
- `rating` : valeur numérique limitée au maximum.
- `ranking` : ordre attendu sous forme de tableau JSON, par exemple `["a","b","c"]` ; crédit partiel facultatif.
- `matching` : correspondances sous forme d’objet JSON, par exemple `{"ligne1":"a","ligne2":"b"}` ; crédit partiel facultatif.
- `keywords` : suggestion de points à partir de mots clés ; validation humaine obligatoire.
- `manual` : correction humaine.
- `none` : aucune notation.

Les panneaux dynamiques sont enregistrés comme réponses composites et se corrigent manuellement. Les téléversements de fichiers ne sont pas intégrés à cette mise à jour ; privilégier les réponses rédigées et les évaluations pratiques. Les conditions SurveyJS pilotent l’affichage, mais le calcul conserve le barème de toutes les questions de la définition. Pour des parcours conditionnels avec des maxima différents, créer des questionnaires distincts. Les déclencheurs, redirections et valeurs calculées ne sont pas conservés lors de l’enregistrement.

Les nouvelles passations utilisent une définition immuable, mémorisée à l’ouverture du formulaire. Enregistrer dans le créateur crée une nouvelle version ; une passation déjà commencée garde son ancienne version. Les réponses doivent être envoyées dans la journée et avant expiration de la session PHP. Elles ne sont pas sauvegardées automatiquement avant « Terminer ».

## Accès et données

Les connexions et rôles existants sont réutilisés. Un formateur doit avoir accès au thème entier pour modifier un questionnaire SurveyJS qui couvre ce thème. Pour corriger une passation, il doit aussi avoir accès au stagiaire. Les comptes limités à certains chapitres ne gagnent aucun nouvel accès au reste du thème.

Les notes sont calculées par PHP à partir de la définition enregistrée. Les réponses envoyées par le navigateur ne déterminent ni le barème ni la compétence. Les clés de correction ne sont pas envoyées au formulaire stagiaire. Les écritures utilisent un contrôle de session et une protection CSRF.

Le dossier `database` doit rester protégé par son `.htaccess` et Apache doit autoriser cette protection. Les exports PDF historiques ne sont pas adaptés aux nouvelles passations SurveyJS ; utiliser la page de résultat et l’impression du navigateur. Les anciens exports continuent de fonctionner pour les anciennes passations.

Le créateur SurveyJS fourni est la version 3.2.0 déjà installée dans votre projet. Son bandeau de licence reste visible si aucune licence n’est configurée. Informations de l’éditeur : [licence SurveyJS](https://surveyjs.io/licensing).

## Revenir à la version précédente

Arrêter les nouvelles passations, restaurer les fichiers de la sauvegarde du projet, puis rétablir la base depuis `database-before-surveyjs.sql` avec phpMyAdmin ou le client MySQL. Une restauration complète supprime les nouvelles données créées depuis la sauvegarde : faire d’abord une sauvegarde de l’état courant. Pour conserver les nouveaux résultats, archiver la définition publiée dans le créateur plutôt que restaurer la base.

La sauvegarde du projet exclut les dépendances inchangées `vendor` et `node_modules` ; conserver leurs dossiers existants. Les fichiers cachés de protection sont inclus dans l’archive finale.
