# MiniShop — site e-commerce + back-office (SAE)

[![CI — GitHub (dev)](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml/badge.svg)](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml) [![CI — GitLab (prod)](https://gitlab.example.com/minishop/badges/main/pipeline.svg)](https://gitlab.example.com/minishop/-/pipelines)

Projet pédagogique : catalogue, panier, commandes, espace d'administration — **PHP 8 / MySQL (PDO) /
procédures stockées / déclencheurs / architecture MVC** — avec sa base de données conçue, normalisée,
testée, et son cahier des charges complet.

> **CI/CD dev vs prod** : le dev avant la prod se fait sur **GitHub** (`.github/workflows/ci.yml` — adaptation de `.gitlab-ci.yml`), la prod/notée reste sur **GitLab** (`.gitlab-ci.yml`). Les deux pipelines exécutent les mêmes 6 jobs (php-lint, style PSR-12, security-scan, base-tests mysql:8.0, unit phpunit, docs PlantUML) avec les mêmes images (`php:8.2`, `mysql:8.0`, `openjdk:17`). `concurrency/cancel-in-progress` reproduit `interruptible: true`.
>
> **Règle « pas de re-run »** : si la CI/CD est **verte avant le merge** (PR/MR au statut ✅), elle n'a **pas besoin d'être relancée après le merge**. Le run déclenché sur `main` par un merge est un **relais de traçabilité**, pas une vérification : mêmes 6 jobs, mêmes images, **même arbre de fichiers** (GitHub Actions exécute déjà le workflow `pull_request` sur le commit de fusion `refs/pull/<n>/merge`, GitLab CI sur le *merge result*). Un re-run manuel — `gh run rerun <id>`, bouton « Re-run jobs », « Retry » GitLab — ne peut donc rien révéler de neuf et coûte 5 à 8 min de machine (service `mysql:8.0` compris) par MR. On relance **uniquement** si `main` a avancé depuis le run de la PR, si le run a été **annulé** (`cancel-in-progress`), si le code a été corrigé puis poussé (c'est alors le `push` qui relance), ou en cas de **panne d'infrastructure** (miroir PlantUML, `apt`, registre Docker) — règle complète et tableau des 6 exceptions : **CDC §14.2**.

| | |
|---|---|
| **Cadre** | SAE — BUT Informatique / Licence 3, Université Côte d'Azur |
| **Groupe** | 3 étudiants (prénoms/noms/numéros dans §Membres ci-dessous) |
| **Référente** | Thanh-Phuong Nguyen — `thanh-phuong.nguyen@univ-cotedazur.fr` |
| **Documents** | [`docs/01-cahier-des-charges-MiniShop.md`](docs/01-cahier-des-charges-MiniShop.md) (spécification **et** conception de base de données) · [`docs/02-document-tests-validation.md`](docs/02-document-tests-validation.md) · [`docs/03-support-soutenance.md`](docs/03-support-soutenance.md) · [`docs/04-conception-bd-et-sql.md`](docs/04-conception-bd-et-sql.md) (livrable n°3 autonome : MCD, MLD, normalisation, analyse + **liens vers les scripts SQL livrés en fichiers**) · [`docs/05-wbs-projet.md`](docs/05-wbs-projet.md) (WBS : 6 divisions, 16 lots, 86 tâches individuelles, charges bottom-up, plan de charge, Gantt, chemin critique) · [`docs/08-fiche-suivi-commits.md`](docs/08-fiche-suivi-commits.md) (fiche de suivi des 32 commits + script de rejeu vers un nouveau GitLab) |
| **Scripts SQL** | [`sql/01_minishop_schema.sql`](sql/01_minishop_schema.sql) (DDL + données) · [`sql/02_minishop_procedures.sql`](sql/02_minishop_procedures.sql) (1 fonction + 17 procédures) · [`sql/03_minishop_triggers.sql`](sql/03_minishop_triggers.sql) (16 déclencheurs) · [`sql/04_minishop_demo.sql`](sql/04_minishop_demo.sql) (démo via `CALL`) — chaîne validée de zéro sur MariaDB 11.8.6 |
| **État** | base de données + 29 tests SQL **exécutés avec succès** (règles RB-01…RB-20, vues, frais de port) ; application PHP **livrée** (MVC, design system, suite PHPUnit, E2E HTTP 180 vérifications) ; écarts restants suivis dans [`docs/10-todo-corrections.md`](docs/10-todo-corrections.md) |

## Prérequis

- PHP **8.2+** avec les extensions `pdo_mysql`, `mbstring`, `json`, `intl`
- MySQL **8.0.16+** (les contraintes `CHECK` sont appliquées depuis cette version) **ou MariaDB 10.6+**
- Apache 2.4 avec `mod_rewrite` (ou le serveur embarqué PHP pour un essai rapide)
- client `mysql` / `mariadb` en ligne de commande, `bash`
- facultatif : `pandoc` + `plantuml` (rendu des documents et des diagrammes), `phpunit`, `phpcs`

## Installation (5 commandes)

```bash
# 1. cloner et placer le projet
git clone <URL_DU_DEPOT> minishop && cd minishop

# 2. configuration locale (jamais commitée)
cp .env.example .env        # puis éditer DB_USER / DB_PASSWORD si vous passez en MySQL

# 3. créer la base MySQL locale : 8 tables, 3 vues, 1 fonction, 17 procédures,
#    16 triggers et le jeu de démonstration (12 produits, 3 clients)
./run.sh --db               # = php scripts/db_init.php --switch  (bascule .env sur sql)
#   autre base   : DB_NAME=minishop_test php scripts/db_init.php --yes
#   sans démo    : php scripts/db_init.php --no-demo
#   diagnostic   : php scripts/db_init.php --check

# 4. vérifier que la base est saine (29 tests de règles de gestion, vues et frais de port)
DB_USER=root DB_PASS='...' ./scripts/run_sql_tests.sh   # attendu : « Tests conformes : 29 / 29 »

# 5. lancer (développement) — en production : DocumentRoot = public/
./run.sh                    # tout-en-un : vérifie PHP, prépare les données, démarre
PORT=8080 ./run.sh          # autre port (prioritaire sur .env)
./run.sh --check            # diagnostic seul (ne démarre rien)
#   http://localhost:8000/            (front-office)
#   http://localhost:8000/catalogue
#   http://localhost:8000/admin/connexion   (back-office)
```

### Récupérer la dernière version d'une branche

`git checkout <branche> -- fichier1 fichier2 …` avec une liste écrite à la main laisse régulièrement un
fichier périmé derrière soi — et le message d'erreur d'une révision antérieure ressemble alors à un bug
de la révision courante. Deuxième piège, rencontré en vrai : la liste calculée par
`git diff HEAD <branche> --name-only` contient aussi les fichiers que la branche **n'a jamais eus** (ils
n'existent que dans votre copie), et `git checkout` échoue dessus avec « ne correspond à aucun fichier
connu de git ».

```bash
git fetch github
scripts/maj-depuis-branche.sh github/arena/6e1d3706-sae-302
```

Le script ne raisonne que sur la divergence réelle (`git merge-base`) : il charge les fichiers
ajoutés/modifiés, supprime ceux que la branche a réellement supprimés, et **ne touche jamais** aux
fichiers propres à votre copie (par exemple `sql/05_minishop_requetes.sql`, ou le travail de votre
binôme). Le tri se fait fichier par fichier :

- contenu déjà identique à la branche → « déjà à jour », rien à faire (c'est le cas d'une synchro
  précédente : `git checkout` indexe les fichiers chargés, donc l'arbre est sale après coup — un refus
  global aurait bloqué toute mise à jour ultérieure) ;
- modifié localement **et** différent de la branche → refus nominatif, `git stash` ou `--force`.

Il annonce toujours son plan avant d'écrire, et termine par la révision obtenue.

`run.sh` et `php scripts/db_init.php` affichent de leur côté la **révision du code** exécuté
(`code : cf1606a · 2026-10-27`, ou `+ modifications locales`) : à comparer avec
`git log -1 --oneline github/arena/6e1d3706-sae-302` **avant** de signaler un bug.

Enfin, `run.sh` **avertit tout seul** quand la copie est en retard :

```
⚠ copie de travail en retard sur github/arena/… (12 commit(s) que cette copie n'a pas)
```

Le calcul se fait sur l'empreinte laissée par `scripts/maj-depuis-branche.sh`
(`.git/minishop-synchro`) et non sur `HEAD` : une synchro de fichiers ne déplace pas la tête de
branche, donc comparer à `HEAD` allumait une alerte permanente — que l'on finit par ignorer.

> **Ubuntu 24.04 / Debian 12** : le paquet `mysql-server` n'existe plus, il est remplacé par
> `mariadb-server` (`sudo apt install mariadb-server && sudo systemctl enable --now mariadb`).
> C'est une cible supportée (MariaDB ≥ 10.6) : `php scripts/db_init.php --check` affiche la version
> détectée et refuse un serveur trop ancien (MySQL < 8.0.16 ignorerait silencieusement les `CHECK`).

### Configuration : `.env` (et rien d'autre)

Un seul fichier de configuration, **local** et ignoré par Git ; le modèle `.env.example` est versionné.
`run.sh` le crée au premier lancement.

| Clé | Défaut | Rôle |
|---|---|---|
| `STORAGE_DRIVER` | `json` | `json` = moteur JSON natif (aucun service) · `sql` = MySQL/MariaDB via PDO |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | serveur MySQL |
| `DB_NAME` | `minishop` | base utilisée (`[A-Za-z0-9_]+`) |
| `DB_USER` / `DB_PASSWORD` | `root` / *(vide)* | identifiants MySQL |
| `DB_SOCKET` | *(vide)* | socket Unix explicite (`/run/mysqld/mysqld.sock`) ; vide = TCP puis repli socket |
| `DATA_PATH` | *(vide)* | dossier des données du pilote JSON (`data/minishop` par défaut) |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | environnement et erreurs détaillées |
| `PORT` / `HOST` | `8000` / `0.0.0.0` | serveur de développement de `run.sh` |

### Base de données locale

```bash
php scripts/db_init.php --check      # diagnostic (serveur, version, base, objets)
php scripts/db_init.php --yes        # crée la base, les 8 tables, 17 procédures, 16 déclencheurs
php scripts/db_init.php --switch     # passe STORAGE_DRIVER=sql dans .env
./run.sh --db                        # les trois d'un coup, puis démarre le serveur
```

**`root` sous Debian/Ubuntu n'accepte pas le TCP.** Le paquet `mariadb-server` crée `root@localhost`
avec le greffon `unix_socket` : ce greffon ne compare pas un mot de passe, il compare **l'utilisateur du
système** du processus qui se connecte. `sudo mariadb` passe (le shell est root), `php` non (il tourne sous
votre compte) — et aucune option côté PHP ne peut contourner cette décision du SGBD, socket Unix comprise.

`./run.sh --db` règle donc le problème **tout seul**, avec l'option `--compte` :

1. un `sudo mariadb` ponctuel (mot de passe sudo demandé une fois) ;
2. création de `minishop@localhost` et `minishop@127.0.0.1` avec un mot de passe aléatoire hexadécimal ;
3. droits **limités à `minishop.*`** (jamais `*.*`) ;
4. écriture de `DB_USER` / `DB_PASSWORD` dans `.env` (fichier local, ignoré par Git).

```bash
php scripts/db_init.php --compte --yes    # équivalent manuel, sans run.sh
php scripts/db_init.php --check           # vérifier ensuite (connexion + inventaire)
```

Si `sudo` est indisponible, la commande affiche le SQL exact à exécuter à la main, mot de passe compris :
rien n'est caché, rien n'est laissé à moitié fait.

`DB_SOCKET` reste utile dans un seul cas : une socket sur un chemin non standard, ou PHP lancé en root.

Pour travailler avec un compte dédié (recommandé dès que la base n'est plus jetable) :

```sql
-- sudo mariadb
CREATE DATABASE IF NOT EXISTS minishop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'minishop'@'localhost' IDENTIFIED BY 'motdepasse_local';
CREATE USER IF NOT EXISTS 'minishop'@'127.0.0.1' IDENTIFIED BY 'motdepasse_local';
GRANT ALL PRIVILEGES ON minishop.* TO 'minishop'@'localhost';
GRANT ALL PRIVILEGES ON minishop.* TO 'minishop'@'127.0.0.1';
```

```ini
DB_USER=minishop
DB_PASSWORD=motdepasse_local
```

Ordre de priorité : **variables d'environnement réelles** > `.env` > `app/Config/env.php` (ancien format,
encore lu pour compatibilité) > valeurs par défaut du code. L'application ne lit ce fichier qu'à un seul
endroit (`App\Config\Env`, via `App\Config\Database`) : aucun contrôleur ne connaît d'identifiant.

```bash
DB_NAME=minishop_test php scripts/db_init.php --yes    # base jetable pour les essais
STORAGE_DRIVER=json ./run.sh                           # forcer le JSON sans toucher au .env
```

Sans `run.sh` (équivalent manuel) : `php -S localhost:8000 -t public scripts/dev-router.php`.
Le troisième argument est **indispensable** : le serveur intégré de PHP n'applique pas
`public/.htaccess`, donc sans routeur `/catalogue` ou `/produit/…` répondent 404.

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
`php scripts/deploy.php --write-sql --db` régénère des **hashes bcrypt aléatoires** et des
**emails de démo uniques** (domaine `.invalid`, suffixe aléatoire) pour la démo publique
(le script rend la version `.sh` historique obsolète, celle-ci a été supprimée).

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
| `app/**`, `public/**`, `data/`, `scripts/seed.php` | **application livrée et testée** (PHP pur, moteur JSON, **design system CSS/JS + SVG**) | `npm run test:all` → 8 suites vertes (108 + 55 + 48 + 26 + 34 + 29 + 44 + 267 vérifications) + E2E 180 |
| `app/Model/Data/` | moteur « JSON-comme-SGBD » : schéma, contraintes (NOT NULL/ENUM/CHECK/UNIQUE/FK), triggers, transactions, vues, journal JSONL — piloté par l'énumération `StorageDriver` (JSON actif ; `SqlStore` = pilote PDO réel, procédures à brancher) | `node scripts/wasm_run.mjs tests/php/engine_smoke.php` |
| `tests/php/**`, `scripts/wasm-e2e.mjs`, `scripts/run-all-tests.mjs` | **livrés et exécutés** (voir « Tests livrés » ci-dessus) | `npm run test:all` |
| `sql/`, `tests/sql/`, `docs/` (CDC, tests, soutenance, annexes, diagrammes) | **livrés** ; `sql/` = référence comportementale du moteur JSON | `./scripts/run_sql_tests.sh` → 29/29 |
| `tests/security/controles.sh`, `tests/charge/reserver.sh`, `tests/perf/mesurer.sh`, `scripts/capture_perf.sh` | **scripts livrés** ; la charge et les mesures supposent l'application en service | `bash -n` passe |
| `tests/sql/out/*.log` | non versionnés (régénérables) — seul `tests/sql/rapport_tests_sql.md` est versionné, preuve de recette à l'appui | `.gitignore`, §14.1 règle 7 |

## Tests livrés

L'application est livrée avec **huit suites, toutes vertes**, exécutables d'un bloc :

```bash
npm run test:all        # ou : node scripts/run-all-tests.mjs
```

> 🛡️ **Sécurité** : la note complète « failles testées → corrections → code » (S-01…S-10, SEC-01…SEC-13, limites assumées) est dans **`docs/07-note-cybersecurite.md`** ; les attaques correspondantes sont rejouées en continu par les batteries T6/T12/T13 de la suite E2E.

| Suite | Fichier | Couverture | Verdict |
|---|---|---|---|
| Linter strict types | `scripts/strict_types_lint.php` | règle `declare_strict_types` de php-cs-fixer + types natifs « PHPStan level 9 » (params, retours, propriétés) + **règle maison : opérateur ternaire interdit** (`? :` et `?:`, remplacés par `if/else` ; `??` et types nullables `?int` autorisés) sur **tout le livrable PHP** | ✅ 0 violation / 100 fichiers |
| Moteur JSON | `tests/php/engine_smoke.php` | contraintes, triggers, transactions, journal du moteur | ✅ vert |
| Règles de gestion | `tests/php/run_tests.php` | T-01…T-29 du document de tests (RB-01…RB-20, vues, frais de port) | ✅ 29/29 |
| Unitaires | `tests/php/unit_tests.php` | `Text` (fold/slug/collation), échappement, CSRF, **matrice RB-11 complète** (36 couples), `Filter`, `PanierSession`, prix TTC, journal, **`.env`**, versions SGBD, **DSN et repli socket Unix** | ✅ 267/267 |
| Fonctionnalités | `tests/php/features_test.php` | **UC-01…UC-14** via les repositories (catalogue, compte, panier, commande, annulation, back-office, indicateurs) | ✅ 108/108 |
| Contrats de design | `tests/php/css_contract.php` | tokens de `docs/DA.md`, contrastes WCAG calculés, classes des vues réellement définies, `prefers-reduced-motion`, feuille d'impression, **illustration du hero** (signature JPEG, dimensions, poids, `alt`, CLS) | ✅ 55/55 |
| Contrat responsive | `tests/php/responsive_contract.php` | 320 px → 1536 px : paliers en `em`, menu repliable, tableaux accessibles, panier en cartes, `dvh`, encoches, `pointer: coarse`, container queries | ✅ 48/48 |
| Contrats HTTP | `tests/php/contract_http.php` | chaque `href`/`action` des vues pointe vers une route déclarée (tous verbes : un `<form action>` vise une route POST), garde sur toutes les routes `/admin/*`, aucune écriture en `GET`, en-têtes CSP / `nosniff` / `Referrer-Policy` / `X-Frame-Options`, vues 403/404/500 | ✅ 26/26 |
| Unitaires PHPUnit | `tests/phpunit/` | `Text`, `Filter`, moteur JSON (contraintes, transactions), matrice RB-11, prix TTC, contrats de sécurité — exécutés hors ligne par `run-local.php` | ✅ 34/34 |
| Intégration SQL | `tests/php/sql_integration.php` | crée `minishop_test`, charge `sql/01` → `sql/04`, rejoue les **29 scénarios** de `tests/sql/run_tests.sh`, supprime la base — la base applicative n'est jamais touchée | ✅ 29/29 (avec serveur) / ⏭ NON JOUÉ sinon |
| Autotest du harnais | `scripts/wasm_verdict.mjs` | le verdict d'une suite ne peut pas venir du `exitCode` de PHP-WASM (mesuré : 1 pour un script normal, 0 pour `exit(3)`, 0 sur erreur fatale) : 7 cas vérifient le calcul réel (0 / 1 / 2 / 255) | ✅ 7/7 |
| E2E HTTP | `scripts/wasm-e2e.mjs` | T1…T14 : parcours réels (cookies + CSRF) — pages publiques, auth, commande 488,40 €, annulation, back-office, cloisonnement, catalogue avancé, compte, panier (plafond/re-tri/prix falsifié), CRUD admin, filtres + audit + journal, **sécurité offensive** (injection, XSS stocké, IDOR, fixation de session, traversées, escalation), rôles RB-13, contact/API/paramètres | ✅ 180/180 |

Avec un PHP local, **aucune dépendance npm n'est nécessaire** : `php scripts/run-all-tests.php`
(ou `npm run test:all:native`) lance les 9 suites directement, puis le parcours E2E s'il trouve Node
et `@php-wasm/node` — sinon l'étape est annoncée **NON JOUÉE** avec la commande exacte, jamais comptée
comme verte. `npm run test:all` détecte PHP natif et l'utilise ; il ne bascule sur le runtime WASM que
dans un environnement sans PHP.

Commandes individuelles : `npm test` (règles), `npm run test:units`, `npm run test:features`, `npm run test:design`, `npm run test:responsive`, `npm run test:http`, `npm run test:phpunit`, `npm run test:e2e`, `npm run test:sql` (serveur requis), `npm run coverage`, `npm run perf`. L'E2E est désormais **hermétique** : `scripts/e2e.mjs` photographie `data/minishop`, joue le seed et le parcours HTTP, puis restaure le jeu de démonstration à l'identique — même en cas d'échec (`scripts/data_snapshot.mjs`).

Les suites PHP sont **autonomes** (aucun SQL, magasins JSON temporaires par section) : elles s'exécutent telles quelles avec `php tests/php/<suite>.php` sur une machine normale, ou via `node scripts/wasm_run.mjs` en sandbox.

Deux mesures complémentaires, réservées à une machine avec PHP natif (elles lancent des processus enfants, ce que la sandbox ne permet pas — elles l'annoncent alors **NON JOUÉ** et sortent en code 2) :

```bash
php scripts/coverage.php --liste   # fichiers de app/ jamais chargés par une suite (code mort probable)
php scripts/coverage.php --min=80  # échoue sous 80 % (CI)

sudo bash scripts/capture_perf.sh  # volumétrie minishop_perf + mesures EXPLAIN / p95
                                   # → tests/perf/dernieres_mesures.txt
```

La stratégie complète — pourquoi cette forme de pyramide, ce que chaque étage prouve, ce qu'il ne prouve pas, et ce qui n'est pas jouable en sandbox — est dans [`docs/11-strategie-tests.md`](docs/11-strategie-tests.md).

**Qualité de code type « Packagist »** : sur machine normale (avec Composer), `composer cs:check` (php-cs-fixer, config `.php-cs-fixer.php`) et `composer lint:types` (PHPStan level 9 + strict-rules, config `phpstan.neon`) — tous deux en `require-dev`, **aucune dépendance runtime**. Dans la sandbox (Packagist injoignable), `npm run lint:types` rejoue les mêmes vérifications via le tokenizer PHP. Passages du 28/09 : 4 fermetures non typées corrigées, puis **éradication des 156 opérateurs ternaires du livrable** (vues, contrôleurs, dépôts, moteur, tests) remplacés par des `if/else` — règle maison ajoutée au linter.

Compléments livrés par ailleurs : `bash tests/security/controles.sh` (contrôles statiques, 10/10), `bash tests/charge/reserver.sh` (survente), `bash tests/perf/mesurer.sh` (p95, ENF-01), `docs/diagrams/render.sh` (14 diagrammes PlantUML), `bash scripts/build-docs.sh` (assemblage documentaire complet : HTML autonome, PDF, Word, diaporama).

## Captures d'écran responsive (preuve visuelle du design)

`scripts/screenshots.mjs` capture **32 pages** à plusieurs largeurs (320, 390, 768, 1024, 1440 px par défaut) dans trois sessions (public, client, back-office), puis produit une galerie HTML et un rapport Markdown. Un audit **WCAG 1.4.10** est injecté dans chaque page : tout élément qui déborde horizontalement est listé (les tableaux de données `.tableau-defilant` et les éléments `position: fixed` sont exclus, conformément à l'exception normative).

```bash
node scripts/wasm-serve.mjs 8080        # ou : ./run.sh  (php -S + routeur)
npm install --save-dev puppeteer        # une seule fois (~150 Mo, Chromium inclus)
npm run shots                           # 5 largeurs, 160 captures
npm run shots -- --all-widths --pdf      # 8 largeurs + PDF d'impression
npm run shots:check                      # diagnostic en 2 s, sans navigateur
```

Sorties : `captures/<page>/<largeur>.png`, `captures/index.html` (galerie), `captures/rapport.md` (débordements, contrastes de mise en page, tailles). Code de sortie `1` dès qu'un débordement ou une page en erreur est détecté → utilisable en CI. `--check` rejoue les 32 routes et les deux connexions sans navigateur : c'est la preuve vérifiable dans un environnement sans Chromium. Options détaillées : `node scripts/screenshots.mjs --help`.

*Historique : la campagne SQL d'origine (`./scripts/run_sql_tests.sh`, 29/29, rapport `tests/sql/rapport_tests_sql.md`) reste jouable — `sql/` sert de référence comportementale au moteur JSON.*

## Arborescence

```
app/Config, app/Controller, app/Model, app/Repository, app/Security, app/View
docs/{01-cahier-des-charges, 02-document-tests-validation, 03-support-soutenance, 04-conception-bd-et-sql, 05-wbs-projet, 06-todolist}, docs/annexes, docs/diagrams/{src/*.puml, *.png}
public/{index.php, .htaccess, css, js, img, gen_volumes.php, mesurer.php (dev-only, navigateur)}
.env.example (configuration versionnée — copier vers .env)  ·  run.sh (lancement local en une commande)
app/Config/Env.php + app/Tools/{DbInstaller,EnvFile,SqlLoader}.php (configuration et base MySQL)
scripts/{load_db.sh, load_db.php, dev-router.php, run_sql_tests.sh, deploy.php, build-docs.sh, md_to_{html,pdf,docx,pptx}.py (rendus documentaires), screenshots.mjs (captures responsive), e2e.mjs + data_snapshot.mjs (E2E hermétique), gen_volumes.php (wrapper dev)}
sql/{01_minishop_schema.sql, 02_minishop_procedures.sql, 03_minishop_triggers.sql, 04_minishop_demo.sql}
tests/{sql/** (manifest, fixture, tNN_*.sql, rapport), security/controles.sh, charge/reserver.sh, perf/mesurer_sql.sh, perf/mesurer.php→public/mesurer.php, php/**}
livrables/{html,pdf,docx,minishop-soutenance.pptx} (rendus versionnés)  ·  var/log  ·  .gitignore
.gitlab-ci.yml (6 jobs, prod)  ·  .github/workflows/ci.yml (6 jobs, dev/GitHub)
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
