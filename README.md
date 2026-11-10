# MiniShop — site e-commerce + back-office (SAE)

[![CI — GitHub (dev)](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml/badge.svg)](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml) [![CI — GitLab (prod)](https://iut-git.unice.fr/but2_s302/s302/badges/main/pipeline.svg)](https://iut-git.unice.fr/but2_s302/s302/-/pipelines)

Projet pédagogique : catalogue, panier, commandes, espace d'administration — **PHP 8 / MySQL (PDO) /
procédures stockées / déclencheurs / architecture MVC** — avec sa base de données conçue, normalisée,
testée, et son cahier des charges complet.

> **CI/CD dev vs prod** : le dev avant la prod se fait sur **GitHub** (`.github/workflows/ci.yml` — adaptation de `.gitlab-ci.yml`), la prod/notée reste sur **GitLab** (`.gitlab-ci.yml`). Les deux pipelines exécutent les mêmes 6 jobs (php-lint, style PSR-12, security-scan, base-tests mysql:8.0, unit phpunit, docs PlantUML) avec les mêmes images (`php:8.2`, `mysql:8.0`, `openjdk:17`). `concurrency/cancel-in-progress` reproduit `interruptible: true`.
>
> **Règle « pas de re-run »** : si la CI/CD est **verte avant le merge** (PR/MR au statut ✅), elle n'a **pas besoin d'être relancée après le merge**. Le run déclenché sur `main` par un merge est un **relais de traçabilité**, pas une vérification : mêmes 6 jobs, mêmes images, **même arbre de fichiers** (GitHub Actions exécute déjà le workflow `pull_request` sur le commit de fusion `refs/pull/<n>/merge`, GitLab CI sur le *merge result*). Un re-run manuel — `gh run rerun <id>`, bouton « Re-run jobs », « Retry » GitLab — ne peut donc rien révéler de neuf et coûte 5 à 8 min de machine (service `mysql:8.0` compris) par MR. On relance **uniquement** si `main` a avancé depuis le run de la PR, si le run a été **annulé** (`cancel-in-progress`), si le code a été corrigé puis poussé (c'est alors le `push` qui relance), ou en cas de **panne d'infrastructure** (miroir PlantUML, `apt`, registre Docker) — règle complète et tableau des 6 exceptions : **CDC §14.2**.

| | |
|---|---|
| **Cadre** | SAE — BUT Informatique (BUT2), Université Côte d'Azur |
| **Groupe** | 3 étudiants (prénoms/noms/numéros dans §Membres ci-dessous) |
| **Référente** | Thanh-Phuong Nguyen — `thanh-phuong.nguyen@univ-cotedazur.fr` |
| **Documents** | [`docs/01-cahier-des-charges-MiniShop.md`](docs/01-cahier-des-charges-MiniShop.md) (spécification **et** conception de base de données) · [`docs/02-document-tests-validation.md`](docs/02-document-tests-validation.md) · [`docs/03-support-soutenance.md`](docs/03-support-soutenance.md) · [`docs/04-conception-bd-et-sql.md`](docs/04-conception-bd-et-sql.md) (livrable n°3 autonome : MCD, MLD, normalisation, analyse + **liens vers les scripts SQL livrés en fichiers**) · [`docs/05-wbs-projet.md`](docs/05-wbs-projet.md) (WBS : 6 divisions, 16 lots, 86 tâches individuelles, charges bottom-up, plan de charge, Gantt, chemin critique) · [`docs/08-fiche-suivi-commits.md`](docs/08-fiche-suivi-commits.md) (fiche de suivi des 32 commits + script de rejeu vers un nouveau GitLab) |
| **Scripts SQL** | [`sql/01_minishop_schema.sql`](sql/01_minishop_schema.sql) (DDL + données) · [`sql/02_minishop_procedures.sql`](sql/02_minishop_procedures.sql) (1 fonction + 17 procédures) · [`sql/03_minishop_triggers.sql`](sql/03_minishop_triggers.sql) (16 déclencheurs) · [`sql/04_minishop_demo.sql`](sql/04_minishop_demo.sql) (démo via `CALL`) — chaîne validée de zéro sur MariaDB 11.8.6 |
| **État** | base de données + 29 tests SQL **exécutés avec succès** (règles RB-01…RB-20, vues, frais de port) ; application PHP **livrée** (MVC, design system, suite PHPUnit, E2E HTTP 301 vérifications (WASM)) ; écarts restants suivis dans [`docs/10-todo-corrections.md`](docs/10-todo-corrections.md) |

## Audit global

Le [rapport d’audit global](docs/17-audit-global-projet.md) et son [inventaire des fichiers versionnés](docs/17-inventaire-projet.csv) distinguent corrections, vérifications et limites.

## Informations légales et données (maquette)

Le pied de page donne accès aux mentions légales, CGV pédagogiques, livraison, rétractation,
garanties, confidentialité et cookies. **Aucune vente, livraison ou paiement réel** : l'identité
du vendeur et de l'hébergeur, le médiateur, les durées de conservation effectives et d'autres
informations restent à renseigner. La fonction légale de rétractation en ligne avec accusé durable
**n'est pas opérationnelle** : ne pas utiliser ce projet pour vendre en l'état. L'audit, les sources,
le détail des cookies réellement identifiés et la checklist figurent dans
[`docs/12-information-juridique.md`](docs/12-information-juridique.md).

## Aide locale en ligne de commande

```bash
php scripts/aide.php --help
php scripts/aide.php --comptes-demo
```

Ces commandes ne se connectent à aucune base et ne modifient pas les données. La seconde affiche les **identifiants initiaux** du jeu de démonstration JSON : ils peuvent avoir été changés ou être absents de l’installation courante. Ne pas laisser ces comptes actifs sur une instance accessible sans protection.

## Prérequis

- PHP **8.2+** avec les extensions `pdo_mysql`, `mbstring`, `json`, `intl`
- MySQL **8.0.16+** (les contraintes `CHECK` sont appliquées depuis cette version) **ou MariaDB 10.6+**
- Apache 2.4 avec `mod_rewrite` (ou le serveur embarqué PHP pour un essai rapide)
- client `mysql` / `mariadb` en ligne de commande, `bash`
- facultatif : `pandoc` + `plantuml` (rendu des documents et des diagrammes), `phpunit`, `phpcs`

## Installation (5 commandes)

**Attention :** les commandes de création/réinitialisation de base ci-dessous et
le harnais SQL direct peuvent détruire des données. Ne les exécuter que sur un
serveur de développement jetable, après sauvegarde et vérification de la cible.

```bash
# 1. cloner et placer le projet
git clone <URL_DU_DEPOT> minishop && cd minishop

# 2. configuration locale (jamais commitée)
cp .env.example .env        # puis éditer DB_USER / DB_PASSWORD si vous passez en MySQL

# 3. créer la base MySQL locale : 8 tables, 3 vues, 1 fonction, 17 procédures,
#    16 triggers et le jeu de démonstration (36 produits, 3 clients)
./run.sh --db               # = php scripts/db_init.php --switch  (bascule .env sur sql)
#   autre base   : DB_NAME=minishop_test php scripts/db_init.php --yes
#   sans démo    : php scripts/db_init.php --no-demo
#   diagnostic   : php scripts/db_init.php --check

# 4. Tests SQL : uniquement sur serveur jetable ; voir la section Tests.
# L’enveloppe refuse une base de test existante par défaut.
# Ne pas lancer le harnais SQL direct sur une base contenant des données à conserver.

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

8 tables · 3 vues · 1 fonction + 17 procédures stockées · 16 déclencheurs · 36 produits · 4 catégories · 3 clients · 1 admin · 4 commandes de démonstration (dont une qui paie 4,90 EUR de frais de port).

## État réel du dépôt (à lire avant de chercher un fichier)

| Zone | Statut | Comment le vérifier |
|---|---|---|
| `app/**`, `public/**`, `data/`, `scripts/seed.php` | **application livrée et testée** (PHP pur, moteur JSON, **design system CSS/JS + SVG**) | `npm run test:all` → 10 suites PHP vertes + E2E WASM 268 ; contrat catalogue JSON : 7 656 scénarios sur le jeu actuel |
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
| Unitaires | `tests/php/unit_tests.php` | `Text` (fold/slug/collation), échappement, CSRF, **matrice RB-11 complète** (36 couples), `Filter`, `PanierSession`, prix TTC, journal, **`.env`**, versions SGBD, **DSN et repli socket Unix** | ✅ 282/282 |
| Fonctionnalités | `tests/php/features_test.php` | **UC-01…UC-14** via les repositories (catalogue, compte, panier, commande, annulation, back-office, indicateurs) | ✅ 131/131 |
| Catalogue : ruptures en dernier | `tests/php/catalogue_stock_test.php` | noms et références, recherche, filtres, tris et pages ; scénarios en stock, en rupture et réapprovisionnement ; fixture JSON jetable | ✅ 7 656 scénarios sur le jeu actuel |
| Derniers ajouts | `tests/php/nouveautes_test.php` | dates, égalités, visibilité, pagination, création, modification et rupture ; fixture JSON isolée | ✅ 18/18 |
| Contrats de design | `tests/php/css_contract.php` | tokens de `docs/DA.md`, contrastes WCAG calculés, classes des vues réellement définies, `prefers-reduced-motion`, feuille d'impression, **illustration du hero** (signature JPEG, dimensions, poids, `alt`, CLS) | ✅ 69/69 |
| Contrat responsive | `tests/php/responsive_contract.php` | 320 px → 1536 px : paliers en `em`, menu repliable, tableaux accessibles, panier en cartes, `dvh`, encoches, `pointer: coarse`, container queries | ✅ 50/50 |
| Contrats HTTP | `tests/php/contract_http.php` | chaque `href`/`action` des vues pointe vers une route déclarée (tous verbes : un `<form action>` vise une route POST), garde sur toutes les routes `/admin/*`, aucune écriture en `GET`, en-têtes CSP / `nosniff` / `Referrer-Policy` / `X-Frame-Options`, vues 403/404/500 | ✅ 28/28 |
| Unitaires PHPUnit | `tests/phpunit/` | `Text`, `Filter`, moteur JSON (contraintes, transactions), matrice RB-11, prix TTC, contrats de sécurité — exécutés hors ligne par `run-local.php` | ✅ 34/34 |
| Intégration SQL | `tests/php/sql_integration.php` | crée une base de test absente, charge `sql/01` → `sql/04`, vérifie le catalogue via `SqlStore`, rejoue les **29 scénarios**, puis supprime cette base ; refuse par défaut une base existante et toujours `DB_NAME` | ✅ 29/29 (avec serveur) / ⏭ NON JOUÉ sinon |
| Autotest du harnais | `scripts/wasm_verdict.mjs` | le verdict d'une suite ne peut pas venir du `exitCode` de PHP-WASM (mesuré : 1 pour un script normal, 0 pour `exit(3)`, 0 sur erreur fatale) : 7 cas vérifient le calcul réel (0 / 1 / 2 / 255) | ✅ 7/7 |
| E2E HTTP | `scripts/wasm-e2e.mjs` | T1…T14 : requêtes au front controller sous PHP-WASM (cookies + CSRF, sans navigateur) — pages publiques, auth, commande 488,40 €, annulation, back-office, cloisonnement, catalogue avancé, compte, panier (plafond/re-tri/prix falsifié), CRUD admin, filtres + audit + journal, **sécurité offensive** (injection, XSS stocké, IDOR, fixation de session, traversées, escalation), rôles RB-13, contact/API/paramètres, pages juridiques, cookies et dates des derniers ajouts | ✅ 301/301 |

### Lancer les tests sans toucher à SQL ni à l’E2E

Prérequis : PHP CLI **8.2 ou plus**, extension `mbstring`, et **Python 3**. Aucun paquet npm n’est nécessaire pour ces commandes. Dans le dossier du projet :

```bash
php -v
php -m | grep -i '^mbstring$'
python3 --version

# Analyse PHP de compilation, sans exécuter les fichiers et sans base :
python3 scripts/verifier_deprecations.py

# Suites PHP + contrôle des dépréciations, sans SQL ni E2E :
php scripts/run-all-tests.php --sans-sql --sans-e2e
```

Un résultat vert indique **« aucune détectée à l’analyse »** pour la version PHP affichée et **« TOUT EST VERT »** pour les suites exécutées. SQL et E2E doivent être indiqués **NON JOUÉ** : ils ne sont pas validés par cette commande. Une dépréciation détectée ou un test en échec fait sortir le runner en code 1. Si PHP est absent, l’analyse seule annonce **NON JOUÉ** et sort en code 2. Vérifier avec chaque version PHP réellement utilisée, notamment celle de l’hébergement.

L’analyse compile les fichiers PHP dans `app`, `public`, `scripts`, `tests/php` et `tests/phpunit`, sans exécuter leur code ; elle détecte aussi un message `Deprecated` quand `php -l` renvoie quand même 0. Les suites PHP sont lancées avec un détecteur d’`E_DEPRECATED` et `E_USER_DEPRECATED`, même masquées par `@` : une dépréciation observée fait échouer la suite. Les autotests utilisent des fichiers temporaires, y compris une **copie isolée** du schéma SQL, sans serveur SQL. Limites : code non exécuté, gestionnaire d’erreur PHP remplacé par le code testé, dépendances exclues et autres langages ne sont pas couverts ; le PHP CLI local peut différer de celui du serveur Web. Les dépréciations à l’exécution de l’E2E HTTP ne sont pas couvertes par ce garde.

### Audit du code et des fichiers

Le runner ci-dessus vérifie aussi les nouveaux candidats au code inutilisé et l’inventaire des fichiers applicatifs versionnés. Pour consulter **tous** les candidats déjà identifiés, sans modifier les fichiers :

```bash
php scripts/code_mort.php --rapport
```

Résultat : des **candidats à examiner**, pas une preuve qu’on peut les supprimer. Les paramètres non cités, certaines variables écrites une seule fois et les fonctions/méthodes sans autre référence textuelle sont contrôlés ; appels dynamiques ou externes, variables globales, code JS/CSS et chemins non joués ne sont pas couverts exhaustivement. Lorsqu’un candidat est résolu ou qu’un nouveau candidat est jugé nécessaire, mettre à jour `scripts/code_mort_reference.json` **après revue**, pas pour masquer une alerte. L’[audit fichier par fichier](docs/16-audit-code-mort.md) explique le rôle des fichiers `app/` et `public/`, y compris les petits fichiers et les vues sans méthodes.

**Attention :** sans `--sans-sql`, le runner crée puis supprime une base de test ; il refuse désormais une base existante et refuse la base configurée dans `DB_NAME`. L’option SQL `--reinitialiser-base-existante` est **destructive**, à utiliser uniquement après vérification sur un serveur jetable. Les autres scripts SQL directs peuvent aussi effacer des données. L’E2E, lui, crée ses données dans le système de fichiers PHP-WASM jetable, sans charger ni restaurer le dossier `data/` de l’hôte. Avec Node et PHP-WASM déjà installés, `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` offre aussi ce mode ; sans PHP natif, il signale l’analyse de compilation **NON JOUÉE** et joue les suites PHP sous WASM 8.2. Ne pas interpréter cela comme une validation des versions PHP plus récentes.

Commandes individuelles existantes : `npm test`, `npm run test:units`, `npm run test:features`, `npm run test:design`, `npm run test:responsive`, `npm run test:http`, `npm run test:phpunit`, `npm run test:e2e`, `npm run test:sql`, `npm run coverage`, `npm run perf`. Les commandes SQL et performance nécessitent un environnement isolé ; les tests lancés directement hors runner ne bénéficient pas automatiquement du garde des dépréciations.

**Suite routeur de développement** (`tests/php/dev_router_test.php`, hors WASM) : lance le vrai `php -S … scripts/dev-router.php` en sous-processus — démarrage depuis un dossier étranger en chemins absolus (cas XAMPP) et depuis la racine en chemins relatifs (commande ci-dessus), fichiers réels de `public/` servis/exécutés directement, 404 du frontal, 7 traversées sans fuite, aide en exécution directe. PHP natif requis (`php tests/php/dev_router_test.php`, ou `npm run test:router`) ; en sandbox PHP-WASM elle se déclare SKIP. Elle est bloquante en CI GitHub, sur **Linux et Windows** (job `dev-router`, `MINISHOP_ROUTER_TEST_STRICT=1`).

> **Intégration de `main` en cours :** les descriptions de régression ci-dessous proviennent de versions différentes et doivent encore être réconciliées avec les vues et CSS effectives. Les commandes `npm run test:ui...` historiques peuvent être absentes. Les scripts `scripts/ui-e2e-*.mjs` modifient le serveur cible : ne les lancer que sur une instance jetable dédiée, avec `UI_BASE_URL` et `UI_TEST_INSTANCE_JETABLE=1`. Aucun seed ou restauration de données utilisateur n’est lancé automatiquement par ces scripts.

**Régression « doublon de catégorie »** (Admin > Catégories, EF-ADM-03) : créer une catégorie de nom déjà utilisé est **refusé** (`NOM_DEJA_UTILISE`, slug en collision : `SLUG_CATEGORIE_DEJA_UTILISE`), en création comme en renommage, sans toucher à la catégorie existante — l'ancien comportement fusionnait en silence et écrasait sa description. Verrouillé à deux niveaux : suite Repository dédiée `tests/php/categorie_doublon_test.php` (29 vérifications, `npm run test:categorie`, incluse dans `test:all`) et **E2E UI à clics réels** `scripts/ui-e2e-categories.mjs` (20 vérifications, `npm run test:ui`) qui remplit les formulaires et clique les boutons des vraies pages (connexion admin → création « Audio »/« test » → refus affiché, Audio intacte ; création témoin puis suppression par l'UI), contre `wasm-serve` en local ou `UI_BASE_URL` sur un serveur déjà lancé. En CI GitHub, le job `ui-e2e` rejoue les deux contre `php -S` natif.

**Régression « bouton Valider ma commande »** (Panier, UC-06 → UC-07) : le CTA du panier était un simple lien bleu souligné, illisible et indiscernable — c'est désormais un vrai `<button type="submit" class="bouton-principal">` (formulaire GET vers `/commande/valider`), stylé par `public/minishop.css` — l'unique feuille du site : fond orange foncé `#9a3412`, texte blanc (contraste 7,3:1), focus clavier visible ; la CSP passe à `style-src 'self'` (toujours aucun style en ligne) et « Continuer mes achats » reste un lien secondaire. Verrouillé par `tests/php/panier_cta_test.php` (rendu réel de la vue, feuille et CSP, 20 vérifications) et par l'E2E UI `scripts/ui-e2e-panier.mjs` (23 vérifications, `npm run test:ui-panier`) : catalogue → fiche produit → ajout → panier → clic sur le bouton → connexion → page de validation, le tout par clics. Le job CI `ui-e2e` rejoue l'ensemble sur `php -S` natif (données re-seedées).

**Régression « page À propos »** (EF-GEN-05) : la page parlait aux développeurs (chemins de fichiers, noms de classes, codes RB-…) et affirmait que la boutique « ne fonctionne ni avec MySQL, ni avec aucune requête SQL » — formulation trompeuse pour le sujet PHP/MySQL/PDO et fausse après la bascule MySQL de l'hébergement final ; elle annonçait en outre « 4 paramètres » en n'en citant que 3. La page présente désormais la boutique aux visiteurs (catalogue en chiffres, règles pratiques) avec une formulation technique honnête — cible MySQL via PDO, moteur intégré seulement pour l'étape de démonstration — et rend les paramètres **depuis la base** (`ParametreRepository::tous()`), donc le nombre annoncé est par construction le nombre affiché : livraison 4,90 €, offerte dès 80 €, TVA 20 %, conservation des données 36 mois. Verrouillé par `tests/php/apropos_test.php` (41 vérifications : rendu réel, données du seed, jargon banni, formulation MySQL/PDO) et l'E2E UI `scripts/ui-e2e-apropos.mjs` (31 vérifications, `npm run test:ui-apropos`), tous deux dans `test:all` et le job CI `ui-e2e`.

**Régression « heures décalées »** (historique, détail et listes de commandes) : les horodatages naissaient en UTC (`date_default_timezone_set('UTC')` dans `app/bootstrap.php`, `date()` partout) et `date_fr()` les réaffichait tels quels — une commande passée à 19h15 à Paris s'affichait 18h15 (17h15 en été). Correctif : un gestionnaire unique, `app/Tools/Heure.php` (`App\Tools\Heure`) — **stockage en UTC** au format historique `Y-m-d H:i:s` (aucune migration, les données existantes étaient déjà en UTC), **affichage converti en Europe/Paris** par `DateTimeImmutable`/`DateTimeZone` (règles IANA : CET = UTC+1 l'hiver, CEST = UTC+2 l'été, transitions les derniers dimanches de mars et d'octobre — aucun calcul « +1/+2 » manuel). `date_fr()`, point d'affichage unique des vues, délègue au gestionnaire ; tous les sites de génération (moteur JSON, triggers d'historique, seed, journal, dernière connexion, année du numéro de commande) passent par lui, insensibles au `date.timezone` du php.ini. Verrouillé par `tests/php/heure_test.php` (45 vérifications : le cas signalé 18h15 UTC → 19h15 Paris en hiver comme en été, transitions de mars/octobre y compris l'heure répétée, aller-retour stockage, fuseau serveur hostile) et l'E2E UI `scripts/ui-e2e-heure.mjs` (14 vérifications, `npm run test:ui-heure`) : achat complet par clics, l'heure affichée (détail, piste des statuts, historique) confrontée à une référence indépendante, le `Intl` Europe/Paris du navigateur.

**Régression « adresse de livraison incomplète »** (page « Valider ma commande », UC-07) : le champ « Adresse de livraison complète » était prérempli avec la seule rue du profil (« 12 rue de France »), sans code postal ni ville, et la commande enregistrait cette adresse incomplète. Cause : le profil stocke rue, code postal et ville dans trois colonnes séparées (EF-CLI-01), la commande n'enregistre qu'une seule chaîne. Correctif : `ClientRepository::adresseLivraisonComplete()` compose « rue, CP Ville » depuis le profil (parties absentes omises proprement, profil sans adresse → champ vide), `CommandeController` la transmet à la vue, qui préremplit désormais l'adresse complète — toujours modifiable avant confirmation. Verrouillé par `tests/php/adresse_livraison_test.php` (25 vérifications : composition du cas signalé « 12 rue de France, 06000 Nice », profils partiels/vides, rendu réel de la vue, adresse réellement stockée par la commande créée) — suite volontairement statique (données du seed, magasin temporaire), sans E2E navigateur pour ce signalement.

**Régression « annulation sans confirmation »** (détail d'une commande, UC-09 ; suppressions du back-office, UC-10/11) : un clic sur « Annuler cette commande » annulait aussitôt la commande, sans « Êtes-vous sûr ? », alors que l'action est définitive — et ce bouton, comme « Supprimer » dans l'admin, avait le même style sombre par défaut que les boutons de validation. Correctif : pour les trois actions définitives (annuler une commande, supprimer un produit, supprimer une catégorie), le bouton de la liste/du détail n'exécute plus rien — c'est un GET vers une page de confirmation « Êtes-vous sûr ? » (aucun JavaScript, CSP oblige) annonçant le caractère définitif et identifiant l'élément ; seul le POST historique, protégé CSRF, exécute depuis cette page, et « Non, revenir… » ramène en arrière sans rien changer. Les boutons d'action définitive portent la classe `.bouton-danger` (`public/minishop.css` : fond rouge `#b91c1c`, texte blanc, contraste 6,5:1, hover et focus clavier) — visiblement distincts du bouton principal orange et des validations ordinaires. Verrouillé par `tests/php/actions_destructrices_test.php` (33 vérifications : rendus réels des listes, du détail et des trois pages de confirmation, contraste calculé, routes et gardes des contrôleurs, annulation métier toujours unique et définitive) et l'E2E UI `scripts/ui-e2e-annulation.mjs` (27 vérifications, `npm run test:ui-annulation`) : commande créée par clics → « Annuler » → « Non » (commande intacte) → « Annuler » → « Oui » (Annulée, bouton disparu) ; même aller-retour pour la suppression d'une catégorie témoin côté admin. L'E2E catégories historique traverse désormais aussi la confirmation lors de son nettoyage.

**Régression « stocks : le mode par défaut écrase le stock »** (Admin > Stocks, UC-12, EF-ADM-04) : le mode proposé par défaut était « Fixer à » (SET, valeur 0) — en voulant ajouter 3 unités à un stock de 5, un clic sur « Appliquer » sans toucher au mode passait le stock à 3 au lieu de 8, l'intention « +3 » étant lue comme une valeur absolue. Correctif, suivant la proposition du signalement : le formulaire propose « Ajouter / retirer » (DELTA, renommé depuis « Varier de ») en première option pré-sélectionnée, et « Fixer à » (SET) reste disponible en choix explicite ; le contrôleur applique le même défaut sûr — un mode absent vaut DELTA, SET seulement si explicitement demandé. Le repository (`adjustStock`, RB-03, motif obligatoire) ne change pas. Verrouillé par `tests/php/stocks_defaut_test.php` (15 vérifications : select rendu avec défaut DELTA, scénario métier exact stock 5 + 3 = 8, « Fixer à » explicite conservé, défaut du contrôleur) et l'E2E UI `scripts/ui-e2e-stocks.mjs` (13 vérifications, `npm run test:ui-stocks`) : quantité 3 et motif saisis **sans toucher au mode** → stock augmenté de 3 (ligne à stock réel, 60 → 63 lors de l'épreuve), puis « Fixer à » explicite pour remettre le stock initial.

**Régression « pied de page : pages manquantes »** (EF-GEN-05, test F-24 du document de validation) : le pied de page ne reliait que « Mentions légales » et « Conditions générales de vente » ; les pages Aide, Livraison, Confidentialité et Rétractation attendues par la liste de tests — et la page Contact citée par EF-GEN-05 — n'existaient pas. Cinq pages statiques visiteur ont été créées (`aide`, `livraison`, `confidentialite`, `retractation`, `contact`, routes GET publiques) avec des contenus cohérents avec le fonctionnement réel du site (port 4,90 € offert dès 80 € et figé à la validation, adresse complète du profil, rétractation 14 jours matérialisée par l'annulation avant expédition, mots de passe hachés, conservation des données 36 mois, paiement simulé sans donnée bancaire), et le pied de page du gabarit relie désormais les huit entrées : Aide · Livraison · Rétractation · Confidentialité · Contact · À propos · Mentions légales · Conditions générales de vente. Verrouillé par `tests/php/pages_pied_de_page_test.php` (54 vérifications : rendu réel des cinq pages, aucun jargon affiché, huit liens du pied de page, routes publiques, cohérence Aide/Livraison/Rétractation/CGV/Confidentialité) et l'E2E UI `scripts/ui-e2e-pied-de-page.mjs` (38 vérifications, `npm run test:ui-pied-de-page`) : en visiteur anonyme, chaque lien du pied de page (accueil et catalogue) mène à sa page en HTTP 200 avec son titre, et les renvois internes (Aide → Livraison, Rétractation → CGV) fonctionnent.

**Régression « catalogue : recherche et filtres mal placés »** (UC-02/03, EF-VIS-02/03) : la recherche et les filtres se retrouvaient après les produits et la pagination — sur mobile, il fallait défiler toute la liste avant de pouvoir filtrer (et, panneau déplié en permanence, les produits disparaissaient du premier écran). Correctif : le bloc recherche/filtres est en haut de la page, replié derrière un bouton « Filtrer » (élément `<details>`/`<summary>` natif, aucun JavaScript — CSP oblige —, zone tactile dédiée dans `public/minishop.css`), fermé par défaut et **ouvert automatiquement dès qu'un filtre est actif** (le bouton annonce alors « Filtrer (actifs) ») ; les critères restent conservés dans l'URL et les champs pré-remplis. Verrouillé par `tests/php/catalogue_filtres_test.php` (20 vérifications : ordre haut → produits → pagination dans le rendu, panneau fermé/ouvert selon les critères — le tri par défaut ne compte pas comme un filtre —, page Recherche identique, viewport mobile) et l'E2E UI format mobile `scripts/ui-e2e-catalogue.mjs` (17 vérifications, `npm run test:ui-catalogue`) : ordre de lecture (panneau avant le premier produit), tap sur « Filtrer », recherche « CASQ » appliquée par clic avec réouverture automatique du panneau, puis retour au catalogue complet refermé. jsdom n'ayant pas de moteur de mise en page, le « format mobile » y est vérifié structurellement (ordre DOM, panneau replié, viewport), le parcours reste un vrai parcours cliqué.

**Régression « fiche produit : valeurs du tableau Caractéristiques cachées »** (n° 11, UC-03, EF-VIS-04) : le tableau « Caractéristiques » de la fiche produit n'affichait que les libellés (Prix HT, TVA, Prix TTC, Disponibilité), les valeurs étant repoussées à droite, hors écran, derrière une barre de défilement — alors que pour deux colonnes, tout doit tenir. Correctif : la fiche présente un vrai tableau à deux colonnes (libellé / valeur) pour ces quatre caractéristiques, et l'unique feuille du site le dimensionne pour tenir en entier à toute largeur (`.tableau-caracteristiques` dans `public/minishop.css` : largeur 100 %, `table-layout: fixed`, colonne des libellés à 40 %, `overflow-wrap: break-word` pour replier les valeurs longues) — aucun défilement horizontal, même sur téléphone. Les informations restent celles du moteur (prix HT/TVA/TTC, RB-16 ; disponibilité et état de stock en temps réel), et le parcours de la fiche est inchangé (description, ajout au panier plafonné au stock ou mention de rupture). Verrouillé par `tests/php/fiche_produit_test.php` (23 vérifications : rendu réel des fiches en stock et en rupture, quatre libellés avec leurs valeurs exactes, structure 2×4 du tableau, emplacement avant description et ajout au panier, règles CSS servies) et l'E2E UI `scripts/ui-e2e-produit.mjs` (20 vérifications, `npm run test:ui-produit`) : catalogue → première fiche → les quatre valeurs lues dans le tableau (montants en euros, pourcentage de TVA, état de disponibilité explicite), feuille servie vérifiée, ajout au panier présent si et seulement si le produit est en stock. jsdom n'ayant pas de moteur de mise en page, la tenue en page y est vérifiée par ce qui la garantit : structure deux colonnes complète dans le DOM et règles CSS effectivement servies.

**Régression « panier : tableau mal mis en page »** (signalement n° 12, UC-06, EF-VIS-03) : dans le panier, le titre « Panier — N article(s) » était coupé sur plusieurs lignes, les prix et les totaux semblaient alignés à droite pendant que la quantité et les boutons restaient à gauche, et les lignes « Sous-total (marchandises TTC) » / « Frais de port » avaient des fonds gris décalés. Correctif : le tableau du panier reçoit une mise en forme explicite et cohérente dans l'unique feuille du site (`.tableau-panier` dans `public/minishop.css`) : légende sur une seule ligne et calée à gauche, libellés et contenus alignés à gauche partout — sauf les montants, en-têtes et valeurs ensemble à droite —, libellé du sous-total sur une ligne, et rangées du pied (sous-total, frais de port, total) partageant le même fond pleine largeur. Le comportement du panier est inchangé (quantités plafonnées côté serveur, retrait, bouton « Valider ma commande »). Verrouillé par `tests/php/panier_tableau_test.php` (20 vérifications : rendu réel du panier — légende, classes `montant` sur les en-têtes et les montants, rangées du pied et règles CSS servies — et rendu du panier vide) et par l'E2E UI `scripts/ui-e2e-panier-tableau.mjs` (17 vérifications, `npm run test:ui-panier-tableau`) : catalogue → fiche → ajout → panier, quantité passée de 1 à 2 par clic avec total recalculé, bouton principal intact. jsdom n'ayant pas de moteur de mise en page, la tenue en page y est prouvée par la structure du DOM et les règles CSS effectivement servies — exactement ce qui garantit l'affichage navigateur.

**Régression « Admin > Commandes : colonne Statut coupée »** (signalement n° 13, UC-13, EF-ADM-06) : dans le back-office, le tableau des commandes défilait horizontalement et la colonne Statut — la plus utile pour piloter les commandes — était coupée à droite, le tableau n'ayant aucune mise en forme dédiée et s'étalant selon son contenu (dates complètes, noms de clients). Correctif : le tableau reçoit une mise en page tenue en page dans l'unique feuille du site (`.tableau-commandes` dans `public/minishop.css`) : largeur 100 %, répartition fixe des six colonnes via `<colgroup>` (Numéro 15 %, Date 19 %, Client 23 %, Statut 17 %, Montant 14 %, Détail 12 % — total 100 %), libellé de statut jamais coupé et en gras, montants alignés à droite ; ce sont les contenus longs (client) qui se replient. Aucun défilement horizontal. Le pilotage est inchangé (filtres cumulables, lien « Ouvrir → » vers le détail, transitions de statut sur la page détail). Verrouillé par `tests/php/commandes_tableau_test.php` (17 vérifications : rendu réel de la liste — colgroup, en-têtes, statuts rendus en entier, montants, liens détail, filtres — liste vide, et règles CSS dont les largeurs totalisant 100 %) et par l'E2E UI `scripts/ui-e2e-commandes-tableau.mjs` (22 vérifications, `npm run test:ui-commandes-tableau`) : une cliente crée une commande par clics, l'admin se connecte, lit le tableau (chaque statut en entier dans sa cellule), filtre par numéro et ouvre le détail. jsdom n'ayant pas de moteur de mise en page, la tenue en page y est prouvée par la structure du DOM et les règles CSS effectivement servies — exactement ce qui garantit l'affichage navigateur.

**Régression « Admin > Catégories : page qui défile, bouton de droite coupé »** (signalement n° 14, UC-11, EF-ADM-03) : dans le back-office, avec une fenêtre d'environ 1 000 px, toute la page Catégories défilait horizontalement et le bouton à droite était coupé (à 768 et 1 440 px, c'était bon) — l'application ne contient aucun bouton « Menu » : le bouton coupé est celui de la colonne Actions (« Supprimer », et avant lui « Enregistrer »). Cause : le tableau des catégories n'avait aucune mise en forme dédiée, et le formulaire d'édition en ligne (trois champs côte à côte dans la première colonne) l'étalait selon son contenu, repoussant Actions hors page. Correctif : le tableau reçoit une mise en page tenue en page dans l'unique feuille du site (`.tableau-categories` dans `public/minishop.css`) : largeur 100 %, répartition fixe des cinq colonnes via `<colgroup>` (Édition 32 %, Slug 15 %, Produits 11 %, Description 26 %, Actions 16 % — total 100 %), champs d'édition à la largeur de leur colonne et empilés en grille, bouton « Supprimer » en une pièce dans sa colonne. La page tient dans la fenêtre à toute largeur — donc aussi à ~1 000 px. La gestion est inchangée (création avec refus des doublons, édition en ligne, suppression protégée par RB-14 avec confirmation). Verrouillé par `tests/php/categories_tableau_test.php` (16 vérifications : rendu réel de la liste — colgroup, en-têtes, formulaires d'édition complets, boutons Supprimer, création, alerte d'erreur — et règles CSS dont les largeurs totalisant 100 %) et par l'E2E UI `scripts/ui-e2e-categories-tableau.mjs` (18 vérifications, `npm run test:ui-categories-tableau`) : connexion admin, lecture du tableau, édition d'une description par le formulaire de la ligne puis remise en état. jsdom n'ayant pas de moteur de mise en page ni de largeur de fenêtre significative, la tenue à ~1 000 px comme à 768 ou 1 440 px y est prouvée par ce qui la garantit : la structure du DOM et les règles CSS effectivement servies, dont la somme fait exactement la largeur de la page.

**Régression « Produit/Référence alignées à droite sous des titres à gauche »** (signalement n° 15, EF-VIS-03, EF-ADM-04/05) : sur trois écrans — le panier, Admin > Stocks et les alertes du tableau de bord — les colonnes Produit et Référence (des libellés) apparaissaient alignées à droite sous des titres alignés à gauche : les valeurs ne tombaient pas sous leurs en-têtes, les alignements n'étant explicités sur aucun des trois écrans (défauts du navigateur pour les stocks et les alertes). Correctif : dans l'unique feuille du site, les colonnes Produit/Référence portent des classes dédiées (`.produit`, `.reference`) alignées à gauche, en-têtes et cellules ensemble, sur les trois tableaux (`.tableau-panier`, `.tableau-stocks`, `.tableau-alertes`) — la règle lue partout devient : libellés à gauche, montants à droite (au panier, seuls les montants gardent leur alignement à droite). Aucun comportement ne change (quantités du panier, ajustements de stock avec le mode par défaut du signalement n° 8, alertes et renvoi vers les stocks). Verrouillé par `tests/php/alignement_colonnes_test.php` (16 vérifications : les trois vues rendues pour de vrai — en-têtes et cellules en classes produit/reference, montants inchangés, ajustement et renvoi intacts — et règles CSS des trois tableaux) et par l'E2E UI `scripts/ui-e2e-alignement.mjs` (20 vérifications, `npm run test:ui-alignement`) : panier rempli par clics, stocks lus côté admin, stock témoin passé à zéro pour faire apparaître son alerte au tableau de bord puis remis en état. jsdom n'ayant pas de moteur de mise en page, l'alignement y est prouvé par ce qui le garantit : les classes d'alignement dans le DOM rendu et les règles CSS effectivement servies.

**Régression « formulaires mal alignés : produit, catégories, produits, catalogue »** (signalement n° 16, EF-ADM-01/02/03, EF-VIS-03) : quatre désordres de formulaires — le formulaire produit alignait chaque champ après son libellé, à une abscisse différente, avec « Description » en bas de sa zone de texte ; dans Catégories, les trois champs de modification n'avaient pas d'étiquette et l'aide « Le slug est généré automatiquement… », logée dans la légende du cadre, débordait ; dans Produits, « Catégorie : » finissait une ligne et sa liste ouvrait seule la suivante ; dans le catalogue vers 1 000 px, la grille des filtres se désorganisait, « Prix TTC maximum » seul sur la deuxième ligne. Correctif : des rangées étiquette + champ explicites et inséparables dans l'unique feuille du site — formulaire produit en grille libellé/champ à deux colonnes alignée au départ (`.formulaire-produit` : tous les champs commencent au même endroit, « Description » en haut de sa zone), aide de création des catégories devenue un paragraphe dans le cadre avec une légende courte et étiquettes accessibles (`aria-label`) sur les trois champs de modification, couples libellé/champ en blocs `.champ` (libellé au-dessus, inséparables) pour le filtre Produits et les filtres du catalogue, ces derniers en grille régulière à deux colonnes avec « Prix TTC minimum » et « Prix TTC maximum » chacun complets et côte à côte à toute largeur. Aucun comportement ne change (création/édition/suppression de produit, édition de catégorie, filtres conservés dans l'URL, panneau « Filtrer »). Verrouillé par `tests/php/formulaires_admin_test.php` (22 vérifications : les quatre vues rendues pour de vrai — rangées liées for = id, légende courte et aide dans le cadre, aria-label, blocs champ, ordre des filtres, boutons — et règles CSS) et par l'E2E UI `scripts/ui-e2e-formulaires.mjs` (26 vérifications, `npm run test:ui-formulaires`) : produit témoin créé par le formulaire puis filtré et supprimé proprement, description de catégorie éditée via son champ étiqueté puis remise en état, filtre prix du catalogue appliqué par clic. Les suites catalogue (n° 10) et catégories (n° 14) ont vu leurs attentes de balise `<form>` alignées sur les nouvelles classes. jsdom n'ayant pas de moteur de mise en page ni de largeur de fenêtre significative, l'alignement y est prouvé par ce qui le garantit : les rangées et blocs du DOM rendu et les règles CSS effectivement servies.

**Régression « divers : panier, Top 5, photos, description, pied de page, contact »** (signalement n° 17, EF-VIS-03/04, EF-ADM-00, EF-GEN-05) : six points vérifiés un à un. (1) Doublon sous-total/port/total « dans le panier (tableau + Récapitulatif) » : dans le panier, le trio ne figure qu'une fois — le pied du tableau du signalement n° 12 ; le « Récapitulatif » est celui de la page de validation, exigé avant paiement (art. L221-5) — les tests comptent désormais les occurrences (une par page et par libellé) pour qu'aucun doublon ne revienne sur aucune des deux pages. (2) Le « Top 5 produits » vide du tableau de bord affichait une liste vide muette : un message explicite (« Aucune vente enregistrée pour le moment… ») le remplace, la liste ne reparait qu'avec des ventes. (3) Photos : la fiche n'affichait AUCUNE photo (EF-VIS-04 la prévoit) — elle affiche maintenant le visuel propre à chaque produit (`.photo-produit`), avec un fichier distinct par produit dans `public/img/` (12 visuels SVG générés, un par produit du seed, `image_url` du seed et du jeu de démonstration alignés) et la CSP élargie à `img-src 'self'` (toujours rien d'externe). (4) Description : quand le produit n'en a pas, la section affichait un paragraphe vide — une phrase de repli (« Aucune description n'est disponible pour ce produit pour le moment. ») la remplace. (5) Pied de page : il reçoit un fond clair et un texte presque noir explicites dans l'unique feuille (`footer` : fond `#f5f5f4`, texte `#1c1917`, liens `#9a3412`) — la mention « MiniShop » n'est plus un gris sur fond nuit, contrastes calculés ≥ 4,5:1 (WCAG AA) pour le texte comme pour les liens. (6) Contact : titre et paragraphes partagent l'alignement à gauche écrit (`.page-contact`). Verrouillé par `tests/php/divers_test.php` (19 vérifications : vues rendues — comptage des libellés de totaux sur panier et validation, Top 5 vide/rempli, photo et repli de description, présence et unicité des 12 visuels sur disque, CSP — et contrastes WCAG calculés en PHP) et par l'E2E UI `scripts/ui-e2e-divers.mjs` (23 vérifications, `npm run test:ui-divers`) : parcours cliente (panier puis validation, unicité du trio), deux fiches et leurs photos distinctes servies en 200, produit sans description créé côté admin puis fiche éprouvée et produit supprimé, Top 5 jamais muet, pied de page et Contact sur règles servies.

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
scripts/{load_db.sh, load_db.php, dev-router.php, run_sql_tests.sh, deploy.php, build-docs.sh, md_to_{html,pdf,docx,pptx}.py (rendus documentaires), screenshots.mjs (captures responsive), e2e.mjs (E2E dans WASM jetable), data_snapshot.mjs (ancien utilitaire à ne pas employer sur des données à conserver), gen_volumes.php (wrapper dev)}
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

> Les paragraphes de régression ci-dessous décrivent des états historiques ; pour le fonctionnement actuel et les limites SQL, voir « Annulation administrateur et mise à jour SQL » en fin de fichier.

**Régression « codes techniques visibles »** (signalement n° 18) : le client et l'équipe voyaient des codes internes — `PORT_OFFERT_A_PARTIR_DE_80.00` et `LIVRAISON_STANDARD` (panier, récapitulatif, validation), « Tracé par trg_history_statut », « Création tracée par trg_history_creation » (historiques de commande), `v_etat_stock` (stocks) et les références RB-xx partout (RB-06, RB-11, RB-18/20, RB-16, RB-19, RB-03, RB-12, RB-14…), jusque dans les CGV et les mentions légales ; deux numéros affichés étaient faux (suppression de catégorie notée RB-14 alors que `sql/02` la rattache à RB-07, seuil d'alerte noté RB-03, règle du stock non négatif — retirer les codes supprime aussi ces erreurs). Correctif : tous les textes AFFICHÉS parlent français — motifs de port (« livraison standard (4,90 €) », « frais de port offerts dès 80,00 € de marchandises », calculés depuis les paramètres), commentaires stockés dans l'historique (« Création de la commande », « Changement de statut le… »), messages d'erreur métier et intitulés des vues cliente et back-office ; les codes restent dans les commentaires de code, les journaux et les tests, jamais à l'écran. « Varier de » (stocks) avait déjà disparu au signalement n° 8 (« Ajouter / retirer ») — les tests le reverrouillent. Verrouillé par `tests/php/codes_techniques_test.php` (32 vérifications : une vingtaine de vues rendues passées au crible des motifs interdits, messages `BusinessError`, motifs `computeShipping`, commentaires des déclencheurs) et par l'E2E UI `scripts/ui-e2e-codes.mjs` (23 vérifications, `npm run test:ui-codes`) : parcours panier → commande → historique, pages légales et tout le back-office scannés en direct.

**Régression « CGV article 4 : annulation ≠ rétractation »** (signalement n° 19, art. L221-18 du code de la consommation) : l'article 4 des CGV s'intitulait « Droit de rétractation » et disait « Conformément au droit de rétractation, le client peut annuler sa commande tant qu'elle n'a pas été expédiée » — juridiquement faux : pour un achat à distance, le droit de rétractation court **14 jours à compter de la réception** du bien ; ce que MiniShop propose avant l'expédition est une **annulation avant expédition**, facilité de la boutique. Correctif (rédactionnel, la règle métier ne change pas) : article 4 renommé « Annulation avant expédition » — mécanisme réel décrit (avant expédition, stock restitué, port compris soldé), droit légal explicitement distingué avec son point de départ (14 jours à compter de la réception, art. L221-18, renvoi à la page Rétractation) et rappel qu'aucune rétractation ni aucun retour n'est mis en œuvre sur cette maquette pédagogique ; même phrase corrigée sous le bouton d'annulation du détail de commande (« annulation avant expédition, CGV art. 4 ») ; page Rétractation restructurée pour ne plus confondre les deux notions (« ne se confond pas avec le droit de rétractation »). Verrouillé par `tests/php/cgv_retractation_test.php` (19 vérifications : vues CGV/Rétractation/détail/aide/livraison rendues et relues, plus le comportement réel — annulation acceptée avant expédition avec stock restitué, refusée après avec `COMMANDE_NON_ANNULABLE`) et par l'E2E UI `scripts/ui-e2e-retractation.mjs` (13 vérifications, `npm run test:ui-retractation`) : CGV et Rétractation lues comme un client, parcours commande complet jusqu'à l'écran de confirmation d'annulation, aide et livraison cohérentes. Les attentes de `scripts/ui-e2e-pied-de-page.mjs` qui verrouillaient l'ancien titre fautif ont été alignées.

**Mentions légales :** les coordonnées de l’éditeur et de l’hébergeur restent à renseigner et vérifier pour tout déploiement réel. Le formulaire de démonstration ne remplace pas un moyen de contact professionnel.

**Contact de démonstration :** le formulaire est conservé. Le nom, l’e-mail et le message sont enregistrés localement dans `var/log/contact.jsonl` ; aucun e-mail n’est envoyé et aucune réponse n’est garantie. La page de confidentialité décrit cette conservation sans promettre de purge automatique ; les coordonnées du responsable restent à renseigner. Le formulaire ne garantit pas le traitement d’une demande de droits. Les parcours UI doivent cibler exclusivement une instance de test jetable.

**Régression « annulée : 0 article, 0,00 € »** (signalement n° 22, RB-18, RB-20, RB-15) : après une annulation, la commande affichait 0 article et 0,00 € — le 0,00 € est conforme à RB-20 (plus rien à facturer), mais les lignes étaient **supprimées**, ce qui restituait le stock et rendait la formule RB-15 (montant = lignes + port) vraie à zéro ; le client ne voyait plus ce qu'il avait commandé. Correctif : les lignes sont **conservées comme historique** (noms, prix figés, quantités), le stock est restitué explicitement ligne à ligne, port et montant restent soldés à 0, et le total d'origine (port compris) est mémorisé dans la nouvelle colonne `commande.montant_avant_annulation` ; le déclencheur RB-15 (PHP et SQL) admet l'état soldé (ANNULEE et montant 0) comme seul écart à la formule et continue de refuser tout autre montant incohérent, y compris de relever un montant soldé. La spécification SQL est amendée en cohérence (`sql/01` colonne, `sql/02` sp_cancel_order ne supprime plus les lignes, `sql/03` exemption soldée). Affichage : détail client et admin montrent les articles, le « Total de la commande avant annulation » puis le « Montant soldé (annulée — rien à payer) » ; « Mes commandes » recompte les articles. Verrouillé par `tests/php/annulation_historique_test.php` (12 vérifications : moteur — lignes conservées, stock exact, soldes, mémoire du total, seconde annulation refusée, RB-15 toujours armée — et vues rendues depuis les vraies données) et par l'E2E UI `scripts/ui-e2e-annulation-historique.mjs` (9 vérifications, `npm run test:ui-annulation-historique`) : achat, annulation réelle par les écrans de confirmation, détail rouvert et liste relue. Le verrou historique de `tests/php/features_test.php` (« lignes purgées ») a été actualisé en « lignes conservées comme historique ».

**Régression « Valider ma commande à 375 px »** (signalement n° 23, EF-CLI-07, art. L221-5) : dans le récapitulatif de validation, libellés et montants n'étaient jamais visibles ensemble à 375 px — ce tableau était le seul du parcours sans classe ni règle CSS, il se dimensionnait à son contenu et débordait de l'écran, la colonne des montants hors champ ; le client pouvait confirmer sans voir clairement le total. (Les RB-18/RB-06/RB-20 sous le bouton n'existent plus depuis le signalement n° 18 — reverrouillé ici.) Correctif : le tableau devient `.tableau-recapitulatif` — largeur 100 %, répartition fixe par colgroup (produit 38 %, prix 22 %, quantité 13 %, total 27 %), montants à droite sur une ligne, libellés du pied libres de passer à la ligne dans leur zone, soit libellé ET montant côte à côte à toute largeur sans défilement horizontal — et le bouton de confirmation affiche le total (« Confirmer et payer : 16,90 € (simulé) ») pour que le montant soit sous les yeux au moment de décider, sans réintroduire de doublon de libellés (verrou du signalement n° 17). Verrouillé par `tests/php/valider_recap_test.php` (15 vérifications : page rendue — classe, colgroup, libellé+montant par ligne, bouton — et feuille relue — 100 % fixe, largeurs totalisant 100 %, montants nowrap à droite, aucun overflow-x) et par l'E2E UI `scripts/ui-e2e-valider.mjs` (11 vérifications, `npm run test:ui-valider`) : parcours d'achat réel jusqu'à la validation, lignes du pied relues et recomposées (sous-total + port = total), CSS servie contrôlée, total repris au bouton.

**Régression « Créer un compte : erreur = tout retaper »** (signalement n° 24, EF-VIS-06, RB-01) : après une erreur d'inscription, la case d'acceptation des CGV repartait décochée (nom, prénom et email, eux, étaient bien conservés — seuls les mots de passe sont vidés, comme attendu) ; et le site ne signalait qu'une erreur à la fois : l'unicité de l'email n'était contrôlée qu'à la création du compte, après toutes les autres validations — une confirmation différente + un email déjà utilisé n'affichaient donc que la confirmation, puis l'email au second envoi. Les étoiles des champs obligatoires existaient déjà (même convention que « Mon compte »), sauf sur la case CGV, pourtant obligatoire. Correctif : le contrôleur signale toutes les erreurs en une fois — le doublon d'email est contrôlé dès la validation via `ClientRepository::emailExiste` (même normalisation que la connexion, même message que la création, dont le garde-fou reste armé) — et le formulaire repart avec nom, prénom, email et case CGV dans l'état saisi, seuls les mots de passe vidés ; la case CGV porte l'étoile. Verrouillé par `tests/php/inscription_erreurs_test.php` (12 vérifications : formulaire rendu avec valeurs conservées, case cochée, étoiles, erreurs listées ensemble ; `emailExiste` sur magasin semé, casse et espaces compris ; garde-fou de création intact ; câblage du contrôleur relu) et par l'E2E UI `scripts/ui-e2e-inscription.mjs` (9 vérifications, `npm run test:ui-inscription`) : envoi fautif réel (confirmation différente + email de démonstration déjà utilisé + CGV cochée) avec les deux erreurs dès le premier envoi, correction progressive sans va-et-vient, compte créé dès l'email libéré.

**Régression « Mes commandes à 375 px : montant et Détail cachés, légende coupée »** (signalement n° 25, EF-CLI-08, UC-08) : sur « Mes commandes », le tableau défilait et le montant comme le lien « Détail » restaient cachés à droite ; la légende « Historique de vos commandes (prix figés à l'achat) » était coupée au lieu de passer à la ligne (le « — RB-06 » cité de mémoire a disparu au signalement n° 18). Cause : ce tableau était le dernier du parcours sans aucune règle dédiée — pas même une classe — donc dimensionné à son contenu (numéro, date complète avec l'heure), colonnes de droite hors écran et légende aussi large que le tableau. Correctif : le tableau devient `.tableau-mes-commandes` — largeur 100 %, répartition fixe par colgroup (numéro 20 %, date 22 %, statut 21 %, articles 10 %, montant 16 %, détail 11 %), montant (en-tête et valeurs) à droite sur une ligne, légende alignée à gauche et libre de passer à la ligne ; il n'y a plus aucun défilement horizontal, montant et « Détail » sont visibles à 375 px. Verrouillé par `tests/php/mes_commandes_tableau_test.php` (14 vérifications : page rendue — classe, colgroup, légende entière sans code, montants et liens par ligne, liste vide — et feuille relue — 100 % fixe, largeurs totalisant 100 %, légende sans ligne forcée, montants nowrap à droite, aucune règle `table` générique) et par l'E2E UI `scripts/ui-e2e-mes-commandes.mjs` (12 vérifications, `npm run test:ui-mes-commandes`) : achat complet en vrai parcours puis « Mes commandes » relue (commande listée, montant formaté, lien Détail) et CSS servie contrôlée.

### Tests supplémentaires : concurrence, migration et navigateur réel

La CI lance `tests/python/test_annulation_concurrente.py` avec deux processus PHP sur un dossier temporaire : double annulation, commandes partageant un stock, refus d'accès et libération du verrou après arrêt d'un processus. Le store JSON utilise un verrou coopératif par dossier pour ses écritures et transactions ; le contrôle d'annulation se fait à l'intérieur de la transaction. **Ce n'est pas une garantie générale de concurrence ou de reprise sur panne** : les opérations qui lisent avant leur transaction, les modifications directes des fichiers et les scripts de seed ne sont pas protégés de bout en bout ; un arrêt au milieu d'un commit multi-fichiers peut laisser un état partiel. Ne pas supprimer `.minishop.lock` pendant que le site tourne. Pour une utilisation concurrente réelle, préférer un SGBD transactionnel.

La migration `sql/migrations/001_montant_avant_annulation_mariadb.sql` ajoute uniquement la colonne nullable sur MariaDB et ne reconstitue pas les anciens montants inconnus. La CI construit une ancienne base peuplée **jetable**, joue la migration deux fois et vérifie les données, l'annulation et les refus. Ce script ne met pas à jour les procédures et déclencheurs : leur déploiement est une étape séparée. Le DDL a un commit implicite ; avant toute migration réelle, prévoir maintenance, sauvegarde et restauration testée, et contrôler le type de toute colonne préexistante. Ne pas lancer les scripts de création ou seed sur une base partagée.

La CI exécute aussi `tests/node/responsive_navigateur.mjs` avec Chromium à 375 et 1440 pixels, sur une copie temporaire avec comptes de démonstration : catalogue, produit, panier, validation, historique et tableaux administrateur. Elle vérifie les débordements et champs hors écran, et conserve des captures de diagnostic. Ce n'est pas un audit complet d'accessibilité, ni une validation de tous les navigateurs et appareils. Les paquets Playwright et Chromium sont installés **dans la CI** ; aucune installation npm n'est requise pour utiliser l'application PHP.


### Annulation administrateur et mise à jour SQL

L’annulation administrateur demande un motif et une confirmation explicite sans JavaScript.
Pour une commande non expédiée elle conserve les lignes et le montant initial, solde
le montant et les frais, et restitue le stock. Pour une commande déjà expédiée,
le montant est soldé mais le stock ne revient pas automatiquement : il faut
vérifier le retour physique avant un ajustement manuel tracé. Ce fonctionnement
ne réalise ni paiement ni remboursement réel.

Sur une base existante, sauvegarder et vérifier le schéma et les privilèges,
puis tester d’abord sur une copie isolée. La colonne de montant initial nécessite
la migration `001` si elle manque. Les migrations ciblées `002_historique_lisible.sql`
et `003_statut_protege.sql` remplacent respectivement deux déclencheurs d’historique
et la procédure de changement de statut. Leur DDL entraîne des commits implicites :
prévoir une fenêtre sans écritures et vérifier les objets après installation.
La procédure générique SQL refuse désormais l’annulation directe ; utiliser le
parcours applicatif administrateur ou `sp_cancel_order` pour une annulation client.
Les anciens commentaires génériques correspondant au format connu sont rendus lisibles à l’affichage sans
modifier les archives ; les anciens montants inconnus ne sont pas inventés.
Les tests SQL de ce changement sont séquentiels : ils ne prouvent pas tous les
scénarios de concurrence ni la sécurité des autres écritures. Les changements de
statut effectués directement en SQL sans les variables de session applicatives
sont tracés comme « SYSTEME ».
Il n’y a pas eu de migration sur une base réelle. Les coordonnées légales restent
à fournir et à vérifier ; le contact est un formulaire de démonstration sans envoi
de courrier. Le pied sombre et l’image de repli sont conservés volontairement :
ils rendent l’ancien mail périmé, sans être des erreurs fonctionnelles.

### Comptes inactifs : traitement de la maquette

La valeur initiale choisie pour le compte est **24 mois**. La CNIL recommande
un délai de deux ans depuis la dernière action pour les comptes inactifs ;
les trois ans cités dans son référentiel concernent notamment la prospection,
et ne sont pas une obligation générale pour tous les comptes. Le responsable
du traitement reste chargé de définir les durées et leurs justifications :
[questions-réponses CNIL](https://www.cnil.fr/fr/questions-reponses-sur-les-referentiels-relatifs-la-gestion-des-activites-commerciales-et-des),
[RGPD article 5](https://eur-lex.europa.eu/legal-content/FR/TXT/HTML/?uri=CELEX%3A32016R0679),
[code de commerce L123-22](https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000005634379/LEGISCTA000006161250/).

`scripts/purger_comptes.php` est **une simulation par défaut** ; `--executer`
supprime les seuls comptes sans commande dont la création et la dernière activité
connue dépassent le délai configuré, avec deux jours de marge. Une activité de
session authentifiée actualise la date au plus une fois par heure. Une date
manquante, future ou invalide est conservée pour examen, de même que tout compte
lié à une commande : ces comptes ne sont **ni supprimés ni archivés** par le
script. Des interactions hors session, comme un message Contact non lié, ne sont
pas prises en compte. Les traces anciennes, le journal Contact, les sauvegardes
et les autres copies ne sont pas purgés ; les nouvelles entrées d'audit de compte
ne contiennent plus ses coordonnées ni son condensat de mot de passe. Ce n'est
pas une procédure complète d'effacement RGPD.

L'exécution automatique **n'est pas activée par le code seul** : l'exploitant
doit configurer une tâche planifiée de son hébergement, avec le PHP et le fichier
`.env` de cette instance, et surveiller ses erreurs et les comptes exclus. Après
sauvegarde vérifiée et simulation, elle peut lancer régulièrement la commande
avec `--executer`. Le traitement est CLI uniquement, jamais exposé en HTTP.
Ne pas installer cette tâche sur une instance existante avant d'avoir examiné les
résultats et défini le traitement des commandes, journaux et sauvegardes. La
migration `004_conservation_comptes_24_mois.sql`, à appliquer volontairement,
ne change que l'ancien défaut de 36 en 24 et préserve les valeurs personnalisées.
La configuration JSON existante doit être revue séparément. Une analyse des dates
anciennes et du fuseau SQL est nécessaire avant toute activation réelle.

La [politique de conservation proposée](docs/18-politique-conservation.md) et
l'outil CLI `scripts/archive_restreinte.php` préparent des copies chiffrées à
accès limité. Ces copies ne retirent rien de la base active : elles ne constituent
pas un archivage intermédiaire complet. La politique, les habilitations, le
transfert, les purges et les sauvegardes restent à valider et mettre en œuvre.
