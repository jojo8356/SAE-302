# MiniShop — site e-commerce + back-office (SAE)

Projet pédagogique : catalogue, panier, commandes, espace d'administration — **PHP 8 / MySQL (PDO) /
procédures stockées / déclencheurs / architecture MVC** — avec sa base de données conçue, normalisée,
testée, et son cahier des charges complet.

| | |
|---|---|
| **Cadre** | SAE — BUT Informatique / Licence 3, Université Côte d'Azur |
| **Groupe** | 3 étudiants (prénoms/noms/numéros dans §Membres ci-dessous) |
| **Référente** | Thanh-Phuong Nguyen — `thanh-phuong.nguyen@univ-cotedazur.fr` |
| **Documents** | [`docs/01-cahier-des-charges-MiniShop.md`](docs/01-cahier-des-charges-MiniShop.md) (spécification **et** conception de base de données) · [`docs/02-document-tests-validation.md`](docs/02-document-tests-validation.md) · [`docs/03-support-soutenance.md`](docs/03-support-soutenance.md) · [`docs/04-conception-bd-et-sql.md`](docs/04-conception-bd-et-sql.md) (livrable n°3 autonome : MCD, MLD, normalisation, analyse + **liens vers les scripts SQL livrés en fichiers**) · [`docs/05-wbs-projet.md`](docs/05-wbs-projet.md) (WBS : 6 divisions, 16 lots, 86 tâches individuelles, charges bottom-up, plan de charge, Gantt, chemin critique) |
| **Scripts SQL** | [`sql/01_minishop_schema.sql`](sql/01_minishop_schema.sql) (DDL + données) · [`sql/02_minishop_procedures.sql`](sql/02_minishop_procedures.sql) (1 fonction + 17 procédures) · [`sql/03_minishop_triggers.sql`](sql/03_minishop_triggers.sql) (16 déclencheurs) · [`sql/04_minishop_demo.sql`](sql/04_minishop_demo.sql) (démo via `CALL`) — chaîne validée de zéro sur MariaDB 11.8.6 |
| **État** | base de données + 29 tests SQL **exécutés avec succès** (règles RB-01…RB-20, vues, frais de port) ; application PHP spécifiée (code à produire aux lots L8→L13 du planning) |

## Prérequis

- PHP **8.1+** avec les extensions `pdo_mysql`, `mbstring`, `json`, `intl`
- MySQL **8.0.16+** (les contraintes `CHECK` sont appliquées depuis cette version) **ou MariaDB 10.6+**
- Apache 2.4 avec `mod_rewrite` (ou le serveur embarqué PHP pour un essai rapide)
- client `mysql` / `mariadb` en ligne de commande, `bash`
- facultatif : `pandoc` + `plantuml` (rendu des documents et des diagrammes), `phpunit`, `phpcs`

## Installation (5 commandes)

```bash
# 1. cloner et placer le projet
git clone <URL_DU_DEPOT> minishop && cd minishop

# 2. configuration (jamais commitée)
cp app/Config/env.example.php app/Config/env.php      # puis éditer db_user / db_pass

# 3. créer la base, les tables, les 16 triggers, la fonction + 17 procédures et le jeu de démonstration
DB_USER=root DB_PASS='...' ./scripts/load_db.sh

# 4. vérifier que la base est saine (29 tests de règles de gestion, vues et frais de port)
DB_USER=root DB_PASS='...' ./scripts/run_sql_tests.sh   # 29 tests, attendu : « Tests conformes : 29 / 29 »

# 5. lancer (développement) — en production : DocumentRoot = public/
php -S localhost:8000 -t public
#   http://localhost:8000/            (front-office)
#   http://localhost:8000/admin        (back-office, compte admin requis)
```

Le script `load_db.sh` encapsule les fichiers contenant des procédures/triggers avec un
`DELIMITER $$` (le client `mysql` n'accepte pas `DELIMITER` en mode batch). Sous MySQL Workbench ou
phpMyAdmin, ouvrir et exécuter `sql/01 → 02 → 03 → 04` dans cet ordre fonctionne aussi.

## Comptes de démonstration

| Rôle | Email | Mot de passe (démo uniquement) |
|---|---|---|
| Client | `alice@example.com` | `Demo2026!` |
| Client | `bruno@example.com` | `Demo2026!` |
| Client | `carla@example.com` | `Demo2026!` |
| Administrateur (rôle `SUPER`) | `admin@minishop.fr` | `Admin2026!` |

Les mots de passe sont stockés **hachés** (`password_hash()` bcrypt cost 12) dans `sql/01_minishop_schema.sql`.
Pour en régénérer : `php -r 'echo password_hash("MonMotDePasse", PASSWORD_BCRYPT, ["cost"=>12]), "\n";'`.
Ne rejouez **pas** `sql/04_minishop_demo.sql` sur une installation exposée (commandes de démonstration) ;
`scripts/deploy.sh` régénère des hashes aléatoires pour la démo publique.

## Contenu de la base (vérifiable en 1 requête)

```sql
SELECT 'tables' objet, COUNT(*) n FROM information_schema.tables    WHERE table_schema = DATABASE()
UNION ALL SELECT 'procedures', COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE'
UNION ALL SELECT 'triggers',   COUNT(*) FROM (SELECT DISTINCT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()) t;
```

8 tables · 3 vues · 1 fonction + 17 procédures stockées · 16 déclencheurs · 12 produits · 4 catégories · 3 clients · 1 admin · 4 commandes de démonstration (dont une qui paie 4,90 EUR de frais de port).

## État réel du dépôt (à lire avant de chercher un fichier)

| Zone | Statut | Comment le vérifier |
|---|---|---|
| `sql/`, `scripts/`, `tests/sql/`, `docs/` (CDC, tests, soutenance, annexes, diagrammes) | **livrés et exécutés** | `./scripts/load_db.sh` puis `./scripts/run_sql_tests.sh` → 29/29 |
| `tests/security/controles.sh`, `tests/charge/reserver.sh`, `tests/perf/mesurer.sh` | **scripts livrés** ; les deux derniers supposent l'application en service | `bash -n` passe ; exécution utile dès le lot L9 |
| `app/**` hors `app/Config/env.example.php`, `public/**`, `tests/php/**` | **à produire aux lots L8 → L13** (le cahier des charges les spécifie : classes, méthodes, routes, conventions, tests) | §9.2 (arborescence cible), §16.1 (ligne iii du barème), §13.3 (lots) |
| `tests/sql/out/*.log` | non versionnés (régénérables) — seul `tests/sql/rapport_tests_sql.md` est versionné, preuve de recette à l'appui | `.gitignore`, §14.1 règle 7 |

## Tests livrés

| Commande | Ce qu'elle prouve |
|---|---|
| `./scripts/run_sql_tests.sh` | 29 tests des règles de gestion RB-01…RB-20, des 3 vues et des frais de port, **dont 15 attaquent la base en SQL direct** (contournement de l'application) ; rapport dans `tests/sql/rapport_tests_sql.md` |
| `bash tests/security/controles.sh` | 10 contrôles statiques (aucune concaténation SQL, échappement systématique, session durcie, CSRF sur les méthodes mutatives…) |
| `bash tests/charge/reserver.sh` | pas de survente : 20 demandes simultanées pour 12 exemplaires → 12 commandes, 8 refus |
| `php -l` + `phpunit` (si installé) | syntaxe et tests unitaires des modèles/services |
| `docs/diagrams/render.sh` | les 14 diagrammes (cas d'utilisation, classes, séquences, activités, états, MCD, MLD, architecture) se régénèrent depuis les sources `.puml` |
| `bash tests/perf/mesurer.sh` | `ENF-01` : p95 par URL (`ab` ou repli `curl`), sortie 1 si p95 ≥ 500 ms |
| `./scripts/build-docs.sh` | régénère **tout** l'assemblage documentaire : 14 diagrammes depuis les `.puml`, annexe SQL recopiée depuis `sql/*` (source de vérité), campagne de tests rejouée, exports Word (`dist/*.docx`, images incrustées) |

## Arborescence

```
app/Config, app/Controller, app/Model, app/Repository, app/Security, app/View
docs/{01-cahier-des-charges, 02-document-tests-validation, 03-support-soutenance, 04-conception-bd-et-sql, 05-wbs-projet, 06-todolist}, docs/annexes, docs/diagrams/{src/*.puml, *.png}
public/{index.php, .htaccess, css, js, img}
scripts/{load_db.sh, run_sql_tests.sh, gen_volumes.sh, deploy.sh, build-docs.sh}
sql/{01_minishop_schema.sql, 02_minishop_procedures.sql, 03_minishop_triggers.sql, 04_minishop_demo.sql}
tests/{sql/** (manifest, fixture, tNN_*.sql, rapport), security/controles.sh, charge/reserver.sh, perf/mesurer.sh, php/**}
var/log  ·  .gitignore  ·  .gitlab-ci.yml (6 jobs)
```

## Documents et rendus (les diagrammes ne s'affichent PAS dans un aperçu sans réseau)

> **Si tu ne vois pas `dist/`** : ce dossier est listé dans `.gitignore` (artefact régénérable, §14.1) et donc masqué par certains explorateurs/aperçus. Une **copie visible** est maintenue dans **`livrables/`** à la racine (`livrables/*.docx`, `livrables/html/*.html`), régénérée à chaque `bash scripts/build-docs.sh`.

`bash scripts/build-docs.sh` produit, dans `dist/` (répertoire généré, hors Git) :

| Rendu | Chemin | Image des diagrammes |
|---|---|---|
| Markdown (source de vérité, éditable) | `docs/01-…-MiniShop.md` | **chemins relatifs** → non affichés dans un visualiseur sandboxé sans accès réseau ; ouvrir le `.docx` ou le `.html` pour les voir |
| Word | `dist/01-cahier-des-charges-MiniShop.docx` | images **intégrées** (14 figures) |
| HTML autonome | `dist/html/01-cahier-des-charges-MiniShop.html` | images **intégrées en base64** — lisible hors ligne, projetable, aucune dépendance |
| PNG / SVG | `docs/diagrams/*.{png,svg}` | sources rendues par `docs/diagrams/render.sh` (PlantUML) |

Si un aperçu affiche une image cassée là où il y a un `![…](docs/diagrams/…png)`, le fichier existe :
vérifier avec `ls docs/diagrams/*.png \| wc -l` (attendu **14**) et ouvrir `dist/html/01-cahier-des-charges-MiniShop.html`.

## Membres et rôles (traçabilité GitLab)

| Personne | Rôle (RACI du CDC §3.3) | Zones principales | Commits |
|---|---|---|---|
| Étudiant·e A | chef de projet · back-office | `app/Controller/`, `README.md`, `.gitlab-ci.yml` | ≥ 20 |
| Étudiant·e B | modèle et base de données | `sql/`, `app/Repository/`, `tests/sql/` | ≥ 20 |
| Étudiant·e C | présentation, sécurité, tests | `app/View/`, `public/js/`, `docs/02-*` | ≥ 20 |

Chaque personne committe aussi dans les deux autres zones (au moins 5 commits chacun) : `git shortlog -sne main`
doit montrer une participation équilibrée, c'est un critère de notation individuel.

## Sauvegarde / restauration (à faire avant une démo)

```bash
mysqldump --single-transaction --routines --triggers -u root -p minishop > backup.sql   # --routines --triggers : OBLIGATOIRE ici
mysql -u root -p minishop < backup.sql
```

Sans `--routines --triggers`, une restauration « normale » perd la fonction, les 17 procédures et les 16 déclencheurs :
les règles `RB-03`, `RB-06`, `RB-11`, `RB-18` cesseraient silencieusement d'être appliquées.

## Licence

Projet pédagogique — code et documents fournis tels quels, usage pédagogique uniquement.
