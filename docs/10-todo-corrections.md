# 10 — Todolist des corrections (audit `09`) — état **vérifié**

> Suivi d'exécution de l'audit [`09-audit-ecarts.md`](09-audit-ecarts.md).
> Légende : `[x]` fait **et vérifié sur disque** · `[ ]` **toujours manquant** · `[~]` fait autrement, justifié · `[!]` bloqué par l'environnement.
> Chaque ligne indique le fichier concerné et la commande qui permet de le constater.
>
> **Dernière vérification :** 2026-10-28 · branche `arena/6e1d3706-sae-302`.
> Cette version remplace la précédente, qui cochait des livrables **absents du dépôt** (voir §8).

> **Note de portée (révision du 23/10/2026).** Ce suivi conserve des constats historiques. La tâche/configuration GitLab CI a été retirée du périmètre courant ; ses références ci-dessous ne sont pas des exigences actives. La CI GitHub Actions reste séparée et ne vaut pas preuve d’un pipeline GitLab.


---

## 0. Bloquants — installation, CI, base

- [x] `scripts/load_db.sh` — chargement `sql/01→04` en une commande. Repli `mysql` si PHP absent ; nom de base paramétrable (`DB=minishop_test`).
- [x] `scripts/load_db.php` — moteur PDO du précédent (aucun client `mysql` requis, `DELIMITER` géré).
- [x] `scripts/run_sql_tests.sh` — enveloppe officielle des 29 tests (délègue à `tests/sql/run_tests.sh`).
- [x] `scripts/gen_volumes.sh` — enveloppe de `scripts/gen_volumes_cli.php`.
- [x] `scripts/gen_volumes_cli.php` — `--load` n'utilise plus de sous-processus (contrôle 9) : chargement par `App\Tools\SqlLoader`.
- [x] `app/Tools/SqlLoader.php` — découpage SQL (`DELIMITER`, chaînes, commentaires) + comptage des objets créés.
- [x] **Configuration par `.env`** — `App\Config\Env` (analyse sans `eval`, priorités documentées), `.env.example` versionné, `App\Tools\EnvFile` (édition ligne à ligne, commentaires conservés), `App\Config\Database` qui fusionne défauts → `env.php` → `.env` → environnement. 24 vérifications dédiées dans `tests/php/unit_tests.php` §13.
- [x] **Base MySQL locale en une commande** — `php scripts/db_init.php` (`--check`, `--keep`, `--no-demo`, `--yes`, `--switch`) et `./run.sh --db` : création de la base, chargement `sql/01→04`, vérification réelle des objets (8 tables, 3 vues, 17 procédures, 1 fonction, triggers, utf8mb4) et bascule de `.env` sur `sql`. `scripts/load_db.php` délègue au même `App\Tools\DbInstaller` (plus de logique dupliquée) et accepte `KEEP=1`.
- [x] **Compatibilité SGBD vérifiée** — `DbInstaller::versionCompatible()` : MySQL ≥ 8.0.16 (les `CHECK` ne sont appliqués qu'à partir de cette version) et MariaDB ≥ 10.6, testés sur 10 versions réelles ; `--check` affiche la version détectée. Ubuntu 24.04 ne distribuant plus `mysql-server`, la doc renvoie vers `mariadb-server`.
- [x] `run.sh` — lancement local en une commande (PHP ≥ 8.2, extensions, `env.php`, seed, serveur + `scripts/dev-router.php`). Sans le routeur, `php -S -t public` renvoyait **404 sur `/catalogue`** (le serveur intégré ignore `.htaccess`) : corrigé et vérifié sur 10 cas (`/`, `/catalogue`, assets, `.env`, `../`, `.md`, `.sql`).
- [x] `scripts/build-docs.sh` — présent : 20 sources Markdown × 3 formats + diaporama, **58 rendus** produits, copie versionnée dans `livrables/` (`--only`, `--out`, `--no-livrables`).
- [x] `docs/diagrams/render.sh` — présent (non versionné jusqu'ici : voir §8).
- [x] `docs/diagrams/src/*.puml` — **14 sources** (cas d'usage ×3, classes, séquences ×5, activité, états, architecture, MCD, MLD).
- [x] `public/css/`, `public/js/`, `public/img/` — design system, JS, SVG (voir §2).
- [x] `tests/charge/reserver.sh` — présent (20 demandes / 12 exemplaires).
- [x] `tests/perf/mesurer.sh` — présent (+ `mesurer_sql.sh`).
- [x] `.gitlab/merge_request_templates/{Lot,Bugfix,Docs,Release}.md` — présents.
- [x] **PHPUnit** — `composer.json` (`phpunit/phpunit ^11`), `phpunit.xml`, `tests/phpunit/` (6 classes, 34 tests) + runner hors ligne.
- [~] CI GitLab — configuration et exigence retirées du périmètre le 23/10/2026 ; les constats historiques de jobs incomplets ci-dessous ne décrivent plus une exigence à réaliser. La CI GitHub Actions reste séparée.
- [ ] CI GitHub — le job `unit` reste tolérant : aucun PHPUnit réel n'a été joué dans cette sandbox.
- [x] `var/.gitkeep` — présent.

## 1. Base de données / PDO (barème iii)

- [x] `app/Model/Data/SqlStore.php` — **implémentation PDO réelle** : `select/find/count/sum/exists/all`, `insert/update/delete/nextId`, transactions, `appeler()` pour les procédures, traduction `SQLSTATE 45000 → BusinessError` et `23000 → ConstraintError`, `PDO::ATTR_EMULATE_PREPARES = false`, noms de tables/colonnes validés par `Schema`.
- [x] Contrat unifié : `StoreInterface::transactional()` ajouté (implémenté par `JsonStore` **et** `SqlStore`).
- [~] **Les procédures stockées sont appelées, mais par une classe non branchée** : les 5 dépôts appellent bien `callProcedure('sp_…')`, protégé par `instanceof PdoStore` — or `StorageDriver` instancie `SqlStore`, donc la branche procédurale n'est **jamais** exécutée et l'application écrit en SQL direct. Unification faite côté socle : `SqlStore` expose désormais `callProcedure` / `callFunction` / `sessionVariable` (adaptateurs de `appeler()`), et `SqlStore` s'instancie sans PDO (vérifié par `engine_smoke`). Reste à basculer les 5 dépôts de `PdoStore` vers `SqlStore` et à supprimer le doublon — commandes fournies dans le README de fusion.
- [ ] `tests/php/sql_integration.php` — **absent** : aucune exécution MySQL dans cette sandbox.
- [ ] Documentation de bascule (`storage_driver = 'sql'`) — non écrite.

## 2. Design (demande explicite) — **fait**

- [x] `docs/DA.md` — étude de 5 concurrents (patterns observables) → direction artistique (palette + ratios, typographie système, grille 8 px, 12 composants, microcopy, bornes).
- [x] `public/css/app.css` — design system (35 tokens, composants, responsive, `:focus-visible`, `prefers-reduced-motion`, `prefers-contrast`).
- [x] `public/css/print.css` — feuille d'impression.
- [x] `public/js/panier.js`, `public/js/ui.js` — panier sans rechargement (`fetch`, `aria-live`, badge), validations et focus.
- [x] `public/img/{logo,logo-mark,favicon}.svg` + `illustration-achat.jpg` — l'illustration abstraite du hero a été remplacée par une **vraie photographie** (960 × 524, 63 Ko, générée pour le projet, palette alignée sur `--primaire`/`--accent`). Le contrat de design vérifie désormais la signature JPEG réelle (un fichier renommé ne passe pas), les dimensions, le poids, l'`alt` descriptif, les attributs `width`/`height` (CLS), `loading="eager"` et l'absence de requête distante (12 vérifications, 28 → 40 au total).
- [x] `app/View/layout.php` — skip-link, nav `aria-current`, badge panier, flashs, pied de page.
- [x] `app/Controller/ApiController.php` + routes `/api/panier/*` — JSON, CSRF, montants serveur.
- [x] CSP alignée (`style-src 'self'`, `script-src 'self'`) sans affaiblir le reste.

## 3. Fonctionnel

- [x] Page **contact** (`GET/POST /contact`, CSRF, accusé de réception, journalisation).
- [x] Route **`/mentions`** (alias de `/mentions-legales`) et `/aide`.
- [x] Filtre **`/admin/stocks?etat=…`** + tri par criticité.
- [x] Écran **paramètres** `/admin/parametres` (frais de port, franchise, TVA par défaut), garde `admin`.
- [x] Indicateurs **du jour** + cumul au tableau de bord.
- [ ] Alias `POST /admin/produits` — **absent** (`public/index.php` n'expose que `GET /admin/produits` et `POST /admin/produit/nouveau`).
- [x] 404/403/500 habillées par le design system.

## 4. Tests « grandes entreprises »

- [x] Pyramide réelle : `tests/phpunit` (unitaires) → `tests/php/{engine_smoke,run_tests,unit_tests,features_test,css_contract}` (intégration/contrats) → `scripts/wasm-e2e.mjs` (E2E HTTP) → `tests/security`.
- [x] **PHPUnit** : 6 classes, **34 tests** (`TextTest`, `FilterTest`, `PrixTest`, `TriggersTest`, `JsonStoreTest`, `SecurityContractTest`).
- [x] `tests/phpunit/run-local.php` — runner hors ligne (`npm run test:phpunit`).
- [x] `tests/php/css_contract.php` — **61 vérifications** (tokens, contrastes AA, classes des vues, a11y, intégration, images).
- [x] `tests/php/contract_http.php` — **26 vérifications** : chaque `href`/`action` des vues pointe vers une route déclarée (tous verbes confondus — un `<form action>` vise une route POST), toute route `/admin/*` porte sa garde, aucune écriture en GET, en-têtes CSP/`nosniff`/`Referrer-Policy`/`X-Frame-Options`, vues d'erreur 403/404/500 présentes. Non-régression vérifiée en injectant un lien mort : le test l'attrape.
- [x] `tests/php/sql_integration.php` — **enveloppe mince** autour du harnais existant `tests/sql/run_tests.sh` (29 scénarios). Elle crée une base dédiée `minishop_test`, y charge `sql/01` → `sql/04`, joue la campagne, **puis supprime la base** : réécrire les 29 cas en PHP aurait produit deux jeux de tests divergents, et lancer le harnais sur la base de développement aurait effacé les données de démonstration (`fixture.sql` réinitialise l'état avant chaque scénario). Hors serveur : annonce **NON JOUÉ** et sort en code 2, jamais en vert. **Défaut trouvé sur la machine de l'utilisateur** : `1044 Access denied for user 'minishop'@'localhost' to database 'minishop_test'` — le compte applicatif n'a, à raison, de droits que sur `minishop.*` (moindre privilège). Le test ne demande jamais `sudo` de lui-même (une campagne de tests ne doit pas s'élever en privilèges à l'improviste) : la base annexe s'ouvre par `php scripts/db_init.php --test-db`, qui la crée et y accorde les droits **sans** toucher au mot de passe du compte ni élargir quoi que ce soit sur la production. En cas de refus, le test affiche cette commande. **7 vérifications ajoutées** (§16 : `CREATE DATABASE` en utf8mb4, `GRANT` sur les deux hôtes, jamais `*.*`, aucune modification du mot de passe ni de la base de production, nom validé, injection refusée).
- [x] `docs/11-strategie-tests.md` — pyramide mesurée (791 vérifications sur les étages 1→6 + 29 scénarios SQL), nature et limites de chaque étage, ce qui n'est pas jouable en sandbox, couverture honnête, auto-évaluation.
- [x] `scripts/coverage.php` — couverture de **chargement** (quels fichiers de `app/` sont chargés par au moins une suite), pas de couverture de lignes : celle-ci exigerait Xdebug/PCOV, donc une dépendance à installer pour noter (contraire à `ENF-20`). Options `--min=N` (CI), `--liste`. Hors environnement capable de lancer des processus enfants : NON JOUÉ, code 2.
- [x] `scripts/capture_perf.sh` — rend vraie la commande citée au scénario `P-06` du document de tests : génère la volumétrie `minishop_perf` puis mesure (`tests/perf/mesurer_sql.sh`), écrit `tests/perf/dernieres_mesures.txt` + copie datée. La base applicative n'est jamais touchée.
- [x] **Le harnais de tests se vérifie lui-même — défaut majeur corrigé.** En mode PHP-WASM, `scripts/wasm_run.mjs` propageait `result.exitCode`, qui provient du gestionnaire de **requêtes** PHP et non du CLI. Mesuré : un script terminé normalement renvoie **1**, `exit(3)` renvoie **0**, une erreur fatale renvoie **0**. Conséquence : dans la sandbox, `run-all-tests.mjs` comptait les échecs comme des succès — le « TOUT EST VERT ✅ (8×) » rapporté jusqu'ici ne prouvait donc rien. Le verdict est désormais calculé sur des signaux observables (`scripts/wasm_verdict.mjs` : erreur fatale PHP → 255, marqueur `❌` → 1, « NON JOUÉ » sans aucun `✅` → 2, sinon 0), avec **autotest de 7 cas** branché en tête de `run-all-tests.mjs` — sans quoi une régression du verdict rendrait de nouveau toutes les suites vertes par construction. Vérifié de bout en bout : suite volontairement sabotée → `run-all-tests.mjs` sort en **1** ; restaurée → **0**.
- [x] **Trois correctifs SQL repris du `dev` de l'utilisateur** (présents dans sa copie, absents de la branche — ils auraient été perdus à la fusion) : `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` dans 01/02/03 (les routines mémorisent la collation de la connexion ; sans elle, `sp_search_products` lève ensuite l'erreur 1267 « Illegal mix of collations »), `AND visible = 1` dans le verrou de stock de `sp_add_order_line` (RB-19 : un produit masqué n'est plus commandable) et les variables `@audit_*` autour de l'UPDATE de statut. Les deux premiers sont intégrés et couverts par 14 vérifications (§19 : invariants SQL livrés — collation explicite partout, produit masqué non commandable, `sp_update_order_status` n'écrit pas elle-même l'historique pour éviter le doublon, aucun trigger ne purge l'historique). Les variables `@audit_*` dépendent du `sql/03` local et seront intégrées avec lui.
- [x] **Chargeur SQL : bug de découpage qui empêchait toute installation de la base** (révélé par la machine de l'utilisateur : `SQLSTATE[42000] … near '$$'`). `SqlLoader::decouper()` ne reconnaissait un commentaire qu'en **colonne 0**. Ces fichiers contiennent des commentaires indentés (`  -- c'est l'interet de la defense en profondeur`) : l'apostrophe faisait basculer le lecteur en mode chaîne, le `DELIMITER $$` n'était plus vu, plusieurs procédures étaient recollées en une instruction et MariaDB répondait « syntax error near '$$' ». Réécrit : commentaires `--`/`#`/`/* */` reconnus partout (y compris indentés), `--` exigeant un blanc comme dans MySQL (`1--2` reste du code), apostrophes doublées (`''`), `/*!…*/` de MySQL conservés, `DELIMITER` indenté. Le message d'erreur désigne désormais le fichier, l'instruction et son début. **43 vérifications** (§18) : 15 cas synthétiques dont chacun reproduit une erreur réelle, puis les 4 fichiers livrés (8 tables, 3 vues, 17 procédures, 1 fonction, 16 déclencheurs correctement délimités, aucun `$$` résiduel, aucune routine recollée). `sql` ajouté aux dossiers montés dans PHP-WASM, sans quoi ces tests n'auraient pas pu voir les fichiers.
- [x] **Packshots produits (12/12)** : les 12 visuels existent (`public/img/produits/*.jpg`, 800 × 600, 15–62 Ko, 380 Ko au total), produits selon une recette unique — objet seul, fond neutre, lumière constante — pour que les vignettes ne « sentent » pas l'assemblage. Chemins alignés dans `Seed.php`, `data/minishop/produit.json` et `sql/01` (`img/produits/…`). 61 vérifications de design, dont : signature JPEG réelle, poids ≤ 120 Ko, dimensions, aucune `image_url` orpheline, repli présent, helpers testés (URL distante refusée, traversée refusée, fichier absent → aucune balise cassée).
- [x] **Dérive de copie signalée par `run.sh`** : plusieurs rapports ont porté sur du code périmé. `run.sh` compare désormais la copie à l'empreinte laissée par `scripts/maj-depuis-branche.sh` (`.git/minishop-synchro`), et non à `HEAD` — une synchro de fichiers ne déplace pas la tête de branche, une comparaison à `HEAD` produisait donc une alerte permanente, que l'on apprend à ignorer. Scénario rejoué de bout en bout dans un clone jetable : alerte sur copie périmée, silencieuse après synchro, `illustration-achat.svg` supprimé et les 12 visuels présents.
- [x] **Empreinte de révision** (`app/Tools/Revision.php`, affichée par `run.sh` et `scripts/db_init.php`) : deux rapports successifs portaient sur une copie périmée du code (messages d'une révision antérieure présentés comme des bugs de la courante). L'en-tête indique désormais `code : <sha> · <date>` (+ `modifications locales` le cas échéant), à comparer avec `git log -1 --oneline`. Le README remplace la liste de fichiers écrite à la main par un `git checkout <branche> -- $(git diff --name-only …)` calculé — **recette elle aussi fautive**, révélée à l'usage : la liste contient les fichiers que la branche n'a jamais eus (`app/Model/Data/PdoStore.php`, `scripts/seed_mysql.php`, `tests/perf/charge-rb18.sh` vivent uniquement dans la copie de l'utilisateur) et `git checkout` échoue sur eux. Remplacé par `scripts/maj-depuis-branche.sh`, qui raisonne sur `git merge-base` (charger / supprimer ce que la branche a réellement changé, ne jamais toucher au reste), refuse un arbre sale et affiche la révision obtenue. Testé dans deux clones jetables : 15 fichiers mis à jour + 3 fichiers locaux préservés ; suppression réelle appliquée. **Troisième défaut, vu à l'usage** : le script refusait tout arbre sale, or `git checkout <branche> -- …` indexe les fichiers chargés — après une synchro réussie l'arbre est donc sale *par construction*, et le passage suivant était bloqué définitivement. Le refus est maintenant **par fichier** : un fichier dont le contenu est déjà celui de la branche est ignoré (compteur « déjà à jour »), seuls les fichiers réellement modifiés localement et différents de la branche provoquent un refus nominatif (`--force` pour assumer). 7 vérifications ajoutées (§17), dont le contrôle que le message 1698 ne promet plus de repli impossible. Compléments liés au parcours `--compte` : le libellé de la socket détectée ne promet plus un contournement (« inopérante avec un compte unix_socket ») et la confirmation destructive est épargnée quand la base ne contient aucune table (cas d'une base créée à l'instant par `--compte`) ; 7 vérifications supplémentaires couvrent l'enchaînement réel (écrire DB_USER/DB_PASSWORD dans `.env`, vider les caches, relire la configuration).
- [x] **`root` sous Debian/Ubuntu — diagnostic corrigé deux fois.** Le compte créé par `mariadb-server` utilise le greffon `unix_socket`, donc PHP en TCP reçoit `[1698] Access denied`. Première correction : `app/Config/DbConnexion.php` (DSN unique, jusque-là dupliqué dans `SqlStore` et `DbInstaller`) réessaie par la socket Unix. **Insuffisant, et vérifié sur la machine de l'utilisateur** : `unix_socket` compare l'**utilisateur du système** du processus, pas un mot de passe — PHP tourne sous `jojokes`, le compte est `root`, la socket ne débloque donc rien (même erreur 1698). Le message affirmait « socket introuvable » tout en affichant la socket détectée : contradiction corrigée. Vraie solution retenue : `app/Tools/DbProvision.php` + `php scripts/db_init.php --compte` (enchaîné par `./run.sh --db`) créent `minishop@localhost` et `minishop@127.0.0.1` via un `sudo mariadb` ponctuel, mot de passe aléatoire hexadécimal écrit dans `.env`, droits limités à `minishop.*` ; si `sudo` échoue, le SQL exact est affiché. 42 vérifications ajoutées (§15 DSN/socket/messages, §16 compte applicatif : validation des noms et de l'injection, nombre d'instructions, absence de `*.*`, mot de passe aléatoire, échec explicite sans client SQL).
- [x] **Récursion infinie** dans `scripts/db_init.php` : `erreur()` s'appelait elle-même au lieu d'écrire sur `STDERR` (`fwrite(STDERR, …)`) — tout message d'erreur en CLI faisait exploser la pile. Corrigé.
- [x] **E2E réellement joué** par `php scripts/run-all-tests.php` : l'étape Node est lancée quand `node` et `@php-wasm/node` sont présents, et annoncée **NON JOUÉE** avec la commande exacte sinon (le message conseillait un `./run.sh --e2e` qui n'existe pas). `intl` et `pdo_mysql` sont signalées comme facultatives, pas comme des échecs.
- [x] **Bug intl corrigé** : `Text.php` écrivait `Transliterator::create()` et `new Collator()` sans barre oblique initiale — dans l'espace de noms `App\Model\Data`, PHP cherchait donc `App\Model\Data\Transliterator`. Invisible dans la sandbox (intl absente, `class_exists()` renvoie faux), **fatal sur toute machine où intl est chargée** — c'est-à-dire la machine de rendu. Corrigé en `\Transliterator` / `\Collator`, et un contrat statique vérifie désormais que les classes globales d'extension sont référencées en absolu dans `app/` (test de non-régression validé en réintroduisant le bug).
- [x] **Piège fermé** : `scripts/run-all-tests.php` avait été créé sans balise `<?php` (PHP affichait donc son contenu au lieu de l'exécuter) — le linter ne couvrait pas `scripts/` autrement que par `seed.php`. Cible par défaut étendue aux scripts applicatifs (95 fichiers analysés au lieu de 90) ; les outils de volumétrie `gen_volumes*.php`, non applicatifs, restent hors périmètre et c'est documenté.
- [x] `npm run test:all` exécute **10 suites** + l'autotest du harnais + l'E2E (moteur, règles, unitaires, fonctionnalités, catalogue-stock, nouveautés, design, responsive, contrats HTTP, PHPUnit) ; le linter strict-types est un contrôle séparé. L'**intégration SQL** est jouée quand un serveur est joignable, annoncée NON JOUÉ sinon.

## 4 bis. Campagne SQL : sept scénarios qui ne testaient pas ce qu'ils annonçaient

Rejouée sur une base réelle le 27/10 (`php tests/php/sql_integration.php`), la campagne a donné **22/29**. Aucun de ces sept échecs ne venait du schéma : tous venaient des **scénarios eux-mêmes**, qui supposaient une base différente de celle que produit `sql/01` + `sql/04`. Ils n'avaient jamais été rejoués sur les fichiers de cette branche — le 29/29 consigné provenait de la copie locale de l'équipe.

| Test | Cause réelle | Correction |
|---|---|---|
| T-04 | hash de **61** caractères inséré dans `mot_de_passe_hash CHAR(60)` → erreur 1406 « Data too long », qui masquait la contrainte UNIQUE visée | hash de 60 caractères exactement |
| T-07 | visait `CMD2026-000002`, brouillon vide que **la fixture supprime** avant chaque test → `INSERT ... SELECT` sur 0 ligne, donc « réussi » sans rien contrôler | le test crée sa commande par `sp_create_order` |
| T-08 | même dépendance à `CMD2026-000002` | idem |
| T-15 | supposait `PC-PORT-001` commandé dans `CMD2026-000001` ; la démo y met `CASQ-005` et `CABLE-009` → le `DELETE` réussissait | le test crée une commande et une ligne `TST-900`, puis tente le `DELETE` |
| T-20 | même hypothèse fausse : `sp_delete_product` **supprimait réellement** le produit (et le retirait du jeu de démo pour les tests suivants) | idem, sur `TST-900` : bascule `visible=0` attendue, puis restitution |
| T-23 | exigeait `montant_total = 12.00` alors que la commande est sous la franchise : le port de 4.90 s'ajoute (16.90) — l'assertion contredisait `ENF-14` | `montant_total = marchandises + frais_port`, et vérification que le **prix produit** a bien augmenté (sinon le test ne prouve rien) |
| T-26 | `ERROR 1267 Illegal mix of collations (utf8mb4_unicode_ci,COERCIBLE) and (utf8mb4_uca1400_ai_ci,COERCIBLE) for operation '='` : le littéral du script (`'RUPTURE'`) est comparé à `v_etat_stock.etat`. Le schéma est créé sous `SET NAMES … unicode_ci` ; le client, lui, garde le défaut de MariaDB 11.4+ (`utf8mb4_uca1400_ai_ci`). Une colonne de **table** a une collation implicite et l'emporte sur le littéral — une colonne de **vue** non : les deux opérandes sont coercibles et MariaDB refuse de choisir. D'où un échec qui ne touchait que les requêtes sur les vues, et qui n'apparaissait sur aucune autre version du SGBD | session alignée sur le schéma des deux côtés : `PDO::MYSQL_ATTR_INIT_COMMAND` dans `app/Config/DbConnexion.php` (l'application est exposée au même piège) et en-tête `SET NAMES` ajouté par `tests/sql/run_tests.sh` à la fixture et à chaque scénario. Vérifié par 6 contrôles unitaires qui interdisent la dérive entre les trois fichiers SQL, le harnais et le PDO |

**Leçon retenue, et inscrite dans le code** : un test qui s'appuie sur un état qu'il ne crée pas lui-même ne teste rien — il constate. Les corrections remplacent les références au jeu de démonstration par des objets créés par le scénario, ce qui les rend indépendants de l'ordre des campagnes et de la fixture.

**Rejeu du 27/10/2026 : 29/29 conformes** (`SQL : CAMPAGNE CONFORME ✅`), sur `minishop_test` créée puis supprimée — la base de développement n'est jamais touchée. Les sept causes sont donc soldées, et l'avertissement qui figurait dans `tests/sql/rapport_tests_sql.md` et `docs/02` a été remplacé par le compte rendu de ce rejeu.

## 4 ter. Une suite de tests a modifié la machine : garde-fou

Constaté sur la machine de recette, en lançant `php scripts/run-all-tests.php` : la suite unitaire a **réellement exécuté `sudo mariadb`**. La vérification « création du compte impossible sans client SQL » appelait `DbProvision::creerCompte()` ; en sandbox aucun client n'existe, donc elle constatait l'échec — sur une machine avec MariaDB, le client existe et l'appel a fait un vrai `CREATE/ALTER USER`. Le mot de passe du compte applicatif a été remplacé par une valeur aléatoire écrite nulle part, et **toutes les connexions PDO suivantes ont échoué en `1045`**, y compris l'intégration SQL, qui s'est alors annoncée « NON JOUÉ » (elle ne mentait pas, mais pour une mauvaise raison).

Trois corrections :

1. **Garde-fou dans le produit** — `DbProvision::contexteDeTest()` : `creerCompte()` et `ajouterBaseAnnexe()` refusent d'agir quand `MINISHOP_TESTS=1`. Le drapeau est posé par les trois exécuteurs (suite unitaire, `run-all-tests.php`, `run-all-tests.mjs`), les enfants en héritent.
2. **La vérification est remplacée** par deux contrôles du garde-fou lui-même (une suite ne crée ni compte ni base), au lieu d'un appel qui dépendait de la présence d'un client sur la machine.
3. **`environnement rendu (config par défaut)`** échouait aussi sur sa machine : la vérification relisait le vrai `.env`, où `STORAGE_DRIVER=sql`. Elle isole désormais la lecture dans un fichier inexistant avant de conclure — un test ne dépend pas de la configuration du poste.

Règle retenue : **une suite de tests ne modifie pas la machine**. Un test dont le résultat dépend du poste (client présent, `.env` rempli) n'est pas un test, c'est une mesure d'environnement.

## 5. Rendus — **rien de produit**

- [x] HTML autonome des documents — `livrables/html/` (19 fichiers, images en base64, hors ligne).
- [x] DOCX — `livrables/docx/` (19 fichiers, images intégrées).
- [x] PDF — `livrables/pdf/` (19 fichiers A4 paginés, en-tête + pied de page).
- [x] Diaporama 12 slides — `livrables/minishop-soutenance.pptx` (16:9, source `docs/12-diaporama-soutenance.md`).
- [!] Vidéo de secours 3 min — non produisible sans navigateur/encodeur dans la sandbox.
- [x] `livrables/README.md` — index généré (formats, documents, commandes de régénération).
- Outillage : `scripts/md_to_{html,pdf,docx,pptx}.py` + `requirements-docs.txt` ; `bash scripts/build-docs.sh` rejoue tout en ~25 s.

## 6. Cohérence documentaire — **soldé le 28/10/2026**

| Point | État | Preuve |
|---|---|---|
| 29 tests SQL partout | [x] | corrigé le 28/10 : `docs/03` (28/28, 27/27 → 29/29), `docs/08` (« 28 tests » de la CI) ; `.gitlab-ci.yml` vérifié |
| E2E 185 | [x] | `README.md` corrigé (T1…T14, 185/185) |
| PHP 8.2 minimum | [x] | prérequis README aligné sur `composer.json` |
| `ENF-13` dupliqué → `ENF-22` | [x] | la seconde ligne (installation ≤ 15 min) est devenue `ENF-22` dans `annexe-enf.md` |
| MCD 8 entités | [x] | `docs/03` corrigé (« MCD (8 entités) ») ; `docs/01` et `docs/04` disaient « 7 entités + `parametre` », alors que `PARAMETRE` est une entité du MCD (`mcd_minishop.puml` en compte 8) — formulation corrigée |
| Noms `01_minishop_schema.sql` | [x] | `docs/07` corrigé, et surtout `scripts/wasm-e2e.mjs` : le test de traversée visait `01-minishop-schema.sql`, nom qui n'existe pas — il aurait donc prouvé un 404 sur un fichier inexistant |
| CDC §16.1/§16.4 à jour | [x] | marqueurs d'état du §16 corrigés (« livrée et testée », « mesuré : E2E T6/T12/T13 ») ; le paragraphe « le code PHP n'est pas encore écrit » est conservé **barré et daté**, avec l'état réel à sa suite — on ne réécrit pas un CDC, on le date |
| Doc `02` : 33 `F-xx` exécutés + PV | [x] | PV rempli le 28/10 avec la campagne réelle (11 étapes vertes, SQL 29/29, E2E 185) ; les 5 scénarios non joués sont nommés au lieu d'être cochés. **Total du tableau corrigé : 90, et non 71** — la somme des lignes ne correspondait pas |
| Doc `03` aligné sur l'application | [x] | le déroulé ne décrit plus de session MySQL manuelle pour les tests : il renvoie à `run_sql_tests.sh` (29/29) et à la sortie de `run-all-tests.php` |
| README : arborescence, chiffres, badges | [x] | chiffres et « zéro CSS » corrigés ; badge GitLab pointé sur le dépôt réel (`iut-git.unice.fr/but2_s302/s302`) au lieu de `gitlab.example.com` |

## 6 bis. Catalogue étendu à 3 pages (demande du 28/10/2026) — **fait**

| Point | État | Preuve |
|---|---|---|
| 36 produits = **3 pages de 12** (`ProduitRepository` : `par_page` 12 par défaut, borné 1..60) | [x] | `app/Model/Data/Seed.php` et `sql/01_minishop_schema.sql` portent 36 lignes d'`INSERT` ; `node scripts/wasm_run.mjs scripts/seed.php` → `produit : 36` ; E2E vérifie `page 2 / 3` |
| **Aucune nouvelle photo** : un packshot par famille réutilisé (zen14, turbox, qhd27, k87, aura, boom, procast, sac25, usbc, power20, fit2, band) | [x] | 12 fichiers dans `public/img/produits/`, tous versionnés ; `tests/php/css_contract.php` valide chaque JPEG réel (signature `FF D8 FF`, ≥ 400×300, ≤ 120 Ko) — aucune requête distante, CSP `img-src 'self' data:` inchangée |
| Jeu de démonstration : 3 ruptures (`POWER-010`, `POWER-030`, `MONTRE-032`), `MICRO-007` au seuil (`TRES_BAS`), `BRAC-012` masqué | [x] | `MICRO-007` : seuil passé de 1 à 2 pour que la démo couvre réellement `TRES_BAS` (F-27 le documentait à tort) ; `T-26` 29/29 |
| Tests **sans effectif figé** (le catalogue doit pouvoir grandir) | [x] | `features_test.php`, `run_tests.php` (T-25/26/27), `engine_smoke.php`, `scripts/wasm-e2e.mjs` (T1, T7, T8) recalculent les attendus depuis le magasin/la table |
| Compteurs documentaires alignés (36 produits, 3 pages, 3 ruptures) | [x] | `docs/01` (§9, §16, tableau du jeu de démo), `docs/02` (F-03, F-25, F-27, §1), `docs/03`, `docs/04`, `docs/05`, `docs/06`, `docs/12`, `docs/DA.md`, `README.md`, `scripts/seed.php` ; les messages de commit **historiques** (`docs/08`, `scripts/rejeu_gitlab.py`) restent inchangés — on ne réécrit pas un historique |
| Suite complète au vert dans la sandbox | [x] | unitaires ✅ · features ✅ · `run_tests` 29/29 ✅ · `engine_smoke` ✅ · design 61 ✅ · contrats HTTP 26 ✅ · E2E 185 ✅ · linter 0 violation |

## 6 ter. Icônes de catégorie (demande du 28/10/2026) — **fait**

| Point | État | Preuve |
|---|---|---|
| **Un jeu d'icônes unique, celui de Lucide** (ISC), inliné dans `app/Security/xss.php` : les 11 tracés existants sont remplacés par la géométrie Lucide exacte et 5 s'ajoutent (`ordinateur`, `casque`, `sac`, `montre`, `etiquette`) | [x] | `icone()` + `icone_categorie()` ; `css_contract.php` vérifie 4 tracés **distincts**, `viewBox="0 0 24 24"`, `stroke="currentColor"`, `aria-hidden="true"` |
| **Une icône par famille** : Informatique → laptop, Audio → headphones, Accessoires → shopping-bag, Objets connectés → watch ; repli `tag` pour un slug inconnu | [x] | mapping fermé slug → tracé ; un slug hostile ne peut rien injecter (le balisage ne recopie jamais l'entrée) — test dédié |
| Pastille visuelle 44 × 44 px (`--primaire-fond`, `--r-sm`), inversion `--primaire` au survol et au focus, icône **décorative** (le nom reste dans le titre) | [x] | `.carte-categorie__icone` dans `public/css/app.css` + règles `:hover`/`:focus-visible` ; contraste AA conservé (test de contrastes du contrat design) |
| Affichée sur **les deux** listes de catégories (accueil et `/categories`) | [x] | E2E : « une icône SVG par catégorie » sur `/` **et** sur `/categories` |
| Aucune dépendance réseau, CSP inchangée | [x] | E2E : aucune occurrence `lucide`/CDN dans le HTML ; `img-src 'self' data:` non modifiée ; `contract_http` 26/26 |
| Licence respectée | [x] | avis ISC complet dans `docs/DA.md` §10 (copyright Lucide/Feather) |

## 6 quater. Produits en rupture en fin de catalogue — **fait**

- [x] Catalogue et recherche : produits en stock d'abord, puis ruptures, **avant pagination**, y compris avec filtres.
- [x] Tri choisi respecté au sein de chaque groupe ; indication affichée près du choix de tri. Accueil et administration conservent leur ordre précédent.
- [x] Tests fonctionnels JSON : tous les tris, pages, recherche, catégorie, filtre en stock, résultats vides et uniquement en rupture. E2E WASM : catalogue et catégorie sur toutes les pages, prix croissant et décroissant.
- [x] Suite WASM verte (fonctionnalités 131, E2E 188), linter strict-types vert.
- [ ] Vérification sur MariaDB et contrôle visuel du navigateur : non joués dans la sandbox (serveur SQL et navigateur absents).

## 6 quinquies. Bouton indisponible sans effet au survol — **fait**

- [x] Les états survol et appui sont réservés aux boutons actifs ; les boutons désactivés gardent leur fond, leur texte et leur bordure, avec le curseur `not-allowed`.
- [x] Contrats CSS : état désactivé, exclusion des effets interactifs et attribut `disabled` du bouton produit (64 contrôles de design).
- [ ] Contrôle visuel dans un navigateur : non joué dans la sandbox (aucun navigateur disponible).

## 6 sexies. Tests complets : ruptures en fin de recherche — **fait**

- [x] `tests/php/catalogue_stock_test.php` : fixture JSON temporaire sans modifier les données applicatives ; oracle indépendant de `searchProducts` ; 7 656 scénarios sur le jeu de 36 produits (chaque nom et référence, mots-clés, casse et accents, prix, catégories, stock, les cinq tris et tri invalide ; pages de 1, 3, 5, 12 et 60 éléments, pages hors limites, doublons et métadonnées).
- [x] Tests de réapprovisionnement, de stock faible, catalogue intégralement disponible et intégralement en rupture ; mêmes scénarios sur chaque état.
- [x] E2E WASM : 72 nouveaux contrôles sur `/catalogue` et `/recherche` (six tris × six recherches/filtres × deux routes), toutes pages réunies. Total : 260 vérifications.
- [x] Test de mutation : désactivation temporaire de la priorité des stocks côté contrôleur puis dans le repository ; l'E2E et le contrat JSON échouent respectivement, puis le code est restauré.
- [x] Suites intégrées aux lanceurs PHP et WASM ; tests catalogue SQL inclus dans `sql_integration.php` sur la base dédiée, en lecture seule.
- [ ] Scénarios du repository sur MariaDB : **non joués dans la sandbox** (serveur et client SQL absents). Leur nombre dépend du jeu chargé ; la campagne complète SQL continue de réinitialiser puis supprimer la base de test, jamais la base applicative.

## 6 septies. Symbole euro et ruptures du catalogue — **fait**

- [x] Symbole `€` conservé comme caractère Unicode (aucune image, aucun changement des montants). Glyphe Lato Regular adapté et renommé, limité à U+20AC, servi localement (`public/fonts/minishop-euro.woff2`) avec licence OFL ; le reste utilise la police système.
- [x] CSP : `font-src 'self'` uniquement ; pas d'origine externe. Contrats de design et HTTP : 68 et 27 vérifications.
- [x] `/catalogue` et `/recherche` donnent déjà la priorité aux produits en stock **avant la pagination**, avec les filtres et les cinq tris ; matrice dédiée et E2E rejoués.
- [ ] Rendu dans le navigateur de l'utilisateur et essais MariaDB non vérifiés dans la sandbox ; la synchronisation locale précédente s'est arrêtée avant la copie à cause d'un conflit de travail local.

## 6 octies. Cartes contact et authentification — **fait**

- [x] Cartes contact, connexion, inscription et connexion administrateur : largeur maximale augmentée de 460 à 575 px (+25 %), contenue à 100 % sur mobile, champs textuels à la largeur disponible.
- [x] Titre visible en gras et centré à l'intérieur de la carte ; le `legend` reste présent et accessible aux lecteurs d'écran. Les formulaires et jetons CSRF sont conservés.
- [x] Contrats CSS/responsive et E2E des quatre pages ; suite complète rejouée.
- [ ] Contrôle visuel en navigateur : tentative en sandbox non jouée, Chromium ne démarre pas (bibliothèques système NSS absentes).

## 6 nonies. Vérification des « derniers ajouts » — **fait**

- [x] Défaut reproduit : à dates égales, le tri utilisait l'identifiant croissant, affichant les premiers produits du seed au lieu des derniers. Le seed crée tous les produits à la même seconde.
- [x] Correctif : `date_creation DESC`, puis `id_produit DESC` en cas d'égalité. L'identifiant auto-incrémenté sert de départage déterministe ; ce n'est pas une preuve d'ordre historique si des identifiants ont été importés manuellement. L'accueil reste limité à quatre produits visibles ; un produit en rupture reste éligible.
- [x] `tests/php/nouveautes_test.php` : 18 vérifications sur JSON isolé (dates différentes ou égales, création réelle, modification d'un ancien, masqués, rupture, pagination, zéro/deux résultats, catalogue et rollback). Avant correctif : 8 échecs reproduits.
- [x] E2E WASM : comparaison des quatre cartes aux données puis création visible et création masquée par le back-office ; 268 vérifications au total. Le script conserve les données de la machine.
- [x] La définition de `sp_search_products` suit la même règle ; contrôle statique et scénarios intégrés à `sql_integration.php` sur base de test isolée.
- [ ] Tests sur MariaDB non joués dans la sandbox. La définition SQL modifiée concerne les nouveaux chargements de scripts : une procédure déjà installée n'est pas modifiée automatiquement. L'accueil utilise le repository PHP et ne nécessite aucune réinitialisation de base.

## 6 decies. Information e-commerce et données — livré pour la maquette

- [x] Recherche française au 28/10/2026 et structures Boulanger/Fnac/Back Market documentées dans `docs/12-information-juridique.md` et `docs/DA.md`.
- [x] Mentions, CGV et encadré réglementaire des garanties, CGU, livraison, rétractation et modèle, confidentialité et cookies ; liens footer, inscription, contact et commande.
- [x] Contact : message réel enregistré jusqu’à 2 000 caractères, échec d’écriture signalé, aucun e-mail ni délai de réponse promis ; données et cookies réels expliqués. La fiche commande distingue annulation et rétractation. Champ de confirmation d’inscription aligné avec le contrôleur.
- [x] Suite complète PHP-WASM : 46 contrats juridiques, 299 vérifications E2E HTTP (dont erreurs de stockage, CSRF, XSS et cookie de session), catalogue 7 656, design 69, responsive 50, HTTP 27 ; zéro échec. Tests sans écrasement des journaux locaux vérifié par empreintes. Auto-évaluation et sources : `docs/12-information-juridique.md` §4.
- [!] Aucun vendeur, hébergeur, médiateur ou responsable du traitement réel identifié : informations à renseigner, pas de conformité de production revendiquée.
- [!] La rétractation en ligne avec envoi d'un accusé durable n'est pas opérationnelle : blocage avant commercialisation, explicitement signalé sur la page.
- [!] Durées, purge, accès aux journaux et sauvegardes et conditions d'hébergement à définir et contrôler avant usage réel. Exclure les outils `mesurer.php` et `gen_volumes.php` de la production.
- [!] Tests SQL MariaDB et rendu navigateur réel non joués dans cette sandbox (client/serveur SQL et bibliothèques Chromium absents) ; les tests statiques et WASM ne constituent pas un audit juridique ou d’accessibilité.

## 6 undecies. Date et heure des derniers ajouts — fait

- [x] Choix de présentation fondé sur le benchmark existant et les règles responsive, `docs/DA.md` §15.
- [x] Date et heure `date_creation` sur chaque carte d’accueil, après le bouton, dans un élément `time` et un style centré. Initialement affichée sans conversion ; désormais convertie depuis UTC selon les préférences du navigateur (voir complément ci-dessous). Repli explicite si date absente ou invalide.
- [x] `tests/php/date_ajout_test.php` : 13 contrôles (format, valeur machine, date impossible ou absente, injection, ordre dans la carte et CSS). E2E : vérification des quatre dates et d’un produit nouvellement créé ; suite totale 301 vérifications vertes, design 69, responsive statique 50, catalogue 7 656. Les journaux locaux restent inchangés.
- [!] Auto-évaluation : rendu des dates et responsive contrôlés par tests HTML/CSS et HTTP, pas de contrôle visuel navigateur en sandbox ; test SQL réel non joué. Les données de démonstration ont souvent la même seconde de création, donc la même date/heure affichée.

## 6 duodecies. Fiches de recette des 14 UC — documentation livrée

- [x] Étudier et sourcer des méthodes de conception et de suivi de tests professionnels.
- [x] Établir les fiches UC-01 à UC-14 et leurs scénarios nominaux, limites, alternatifs et de sécurité, sans prétendre à l’exhaustivité absolue.
- [x] Ajouter un protocole d'exécution sûr, la traçabilité des résultats et les réserves sur les fonctionnalités non prouvées.
- [x] Contrôler l'intégrité des identifiants, liens et renvois ; livrer et expliquer les limites.
- [!] Recette métier, navigateur réel, charge et MariaDB : **non joués** pour cette livraison ; à effectuer sur une instance isolée et autorisée. Les 150 scénarios et les 36 couples de transition sont initialement non joués dans le suivi.


## 6 terdecies. Préparation LineServ et recette collègue

- [x] Analyser le déploiement SFTP/phpMyAdmin et identifier les contraintes réelles du projet.
- [x] Préparer un guide sûr et un script local non destructif pour l’import initial sur une base dédiée vide.
- [x] Rédiger un e-mail de recette et expliquer les outils d’accessibilité et de responsive.
- [x] Adapter l’e-mail : transfert par la collègue depuis la branche IUT et contrôle responsive de toutes les pages et états accessibles.
- [x] Vérifier les livrables, indiquer explicitement les blocages et les tests non exécutés.
- [!] Compatibilité applicative avec l’URL en sous-dossier et séparation de la racine privée : adaptation et validation réelles encore nécessaires avant publication. PHP, réécriture Apache, droits SQL et accès de recette sur LineServ restent à confirmer.

## 6 quaterdecies. Contraste du texte du pied de page signalé par WAVE

- [x] Étudier la cascade CSS et le contraste calculé des couleurs déclarées.
- [x] Corriger la règle de manière ciblée sans modifier la mise en page responsive.
- [x] Ajouter un test de non-régression, vérifier les suites disponibles et documenter les limites.
- [!] Refaire WAVE sur le site réellement utilisé après application de la correction ; la capture fournie concerne une autre copie et ne démontre pas l'état du correctif.

## 6 quindecies. Alertes WAVE des cartes produit

- [x] Analyser la répétition des liens et la structure des titres sur le catalogue et l’accueil.
- [x] Conserver une action de fiche identifiable et unique par produit, sans modifier les règles responsive.
- [x] Tester les rendus HTML isolés et HTTP WASM, l’échappement, les liens et les dates, puis documenter les limites.
- [x] Examiner l’élément « Possible heading » transmis : prix en paragraphe, pas titre de section ; conserver sa sémantique.
- [!] Vérifier les autres éléments signalés individuellement et refaire WAVE après déploiement ; rendu navigateur et WAVE non joués ici.

## 6 sexdecies. Dépréciations PHP et lancement isolé des tests

- [x] Définir les limites du contrôle : version PHP, fichiers et chemins réellement exécutés.
- [x] Détecter les dépréciations à l’analyse PHP et pendant les suites PHP, avec des tests de non-régression.
- [x] Ajouter un lancement sans SQL ni E2E pour protéger les données locales et documenter les commandes.
- [x] Exécuter les vérifications disponibles et vérifier les sorties sans écraser les données locales.
- [!] Vérifier aussi avec le PHP réellement utilisé sur LineServ ; une version PHP différente et les parcours non exécutés peuvent produire d’autres dépréciations.
- [x] Isoler les variables d’environnement du test unitaire et corriger deux faux positifs de sécurité qui bloquaient la CI (requêtes d’inventaire littérales, exclusion précise des seuls modèles publics).
- [x] Mettre à jour les actions GitHub signalées comme utilisant Node.js 20 déprécié (checkout v5, upload-artifact v6 et setup-java v5 : Node.js 24 ; runner 2.327.1 minimum).
- [!] Dans la sandbox locale : SQL réel, E2E, PHP CLI natif et serveur Web non joués. L’outil de mesure HTTP et les changements de connexion PDO restent à vérifier en environnement isolé avec MariaDB.
- Auto-évaluation : PHP-WASM 8.2, suites isolées vertes ; PHP-WASM CLI 8.5.10, 127 fichiers analysés sans dépréciation de compilation et 282 contrôles unitaires verts après correction des constantes PDO ; preuve négative via fixture 8.5 ; autotests dédiés et substitution SQL vérifiée sur copie temporaire. CI GitHub sur edb6845 : suites natives PHP 8.2 et 8.5, contrôles sécurité, PHPUnit et SQL MySQL 8 isolé réussis ; MariaDB/LineServ restent à vérifier.


## 6 septdecies. Audit des fichiers et code potentiellement inutilisé

- [x] Inventorier chaque fichier applicatif `app/` et `public/`, expliquer son rôle et les références retrouvées, examiner les fichiers vides et courts.
- [x] Ajouter aux tests un contrôle des paramètres, variables écrites et méthodes potentiellement inutilisés, avec fixtures positives/négatives et protection contre les régressions.
- [x] Examiner les résultats, corriger uniquement les problèmes confirmés et documenter les incertitudes et les appels dynamiques.
- [x] Tester les suites isolées sans toucher aux données locales et actualiser l’auto-évaluation.
- [x] CI GitHub sur `ce2d7aa` : tests natifs PHP 8.2 et 8.5, inventaire, code mort, sécurité, PHPUnit et SQL MySQL 8 isolé réussis.
- [!] Dans la sandbox : parcours HTTP E2E, MariaDB et navigateur non joués ; la sortie partagée par l’utilisateur concerne une autre exécution locale.
- Auto-évaluation : inventaire des 116 fichiers `app/` et `public/`, aucun vide, 45 PHP sans fonction à corps explicités. Deux simplifications confirmées dans le moteur JSON ; 11 candidats conservés comme incertains. Autotest de l’analyseur 12/12, inventaire 2/2, suites PHP-WASM isolées vertes, sécurité 10/10 ; compilation PHP-WASM 8.5 ciblée sur les six PHP modifiés. Analyse lexicale partielle, pas un audit complet des dépendances ni du code mort CSS/JS/SQL.


## 6 duodevicies. Audit global et corrections des candidats

- [x] Réexaminer les 11 candidats : corriger les vrais défauts, retirer les doublons sûrs et protéger les fonctionnalités conservées.
- [x] Empêcher la campagne E2E et l’intégration SQL d’écraser silencieusement des données existantes ; ajouter des tests isolés.
- [x] Vérifier la protection des fichiers de configuration et contrôler les fichiers versionnés de l’ensemble du dépôt.
- [x] Produire un rapport précis avec résultats, risques, éléments non vérifiés et inventaire global.
- [x] Exécuter les vérifications disponibles sans toucher aux données utilisateur, documenter l’auto-évaluation et pousser ; CI `796aada` verte (PHP natif, PHPUnit, MySQL jetable et E2E WASM).

## 6 undevicies. Page 403 pour les accès SUPER refusés

- [x] Distinguer les administrateurs sans rôle SUPER des visiteurs non connectés et rendre la vue 403 avec le statut HTTP approprié.
- [x] Vérifier GET, POST, sécurité des données, authentification et accès SUPER avec des tests isolés (309 contrôles E2E WASM ; test HTTP natif ajouté à la CI).
- [x] Actualiser le référentiel de code potentiellement inutilisé, les inventaires et les rapports (6 candidats restants).
- [x] Lancer les suites disponibles, auto-évaluer et préparer la publication sur la branche Arena.
- [x] CI du commit `01cc0fa` : tests HTTP natifs PHP 8.2/8.5, E2E WASM, PHPUnit, sécurité et MySQL 8 jetable réussis.
- [!] En local, PHP natif, SQL, navigateur réel et hébergement ne sont pas disponibles ; navigateur et LineServ restent à vérifier.

## 6 vicies. Suppression de la méthode `withStore` redondante

- [x] Confirmer les usages dans les sources versionnées et retirer `withStore` avec l’accord de l’utilisateur ; préserver `CommandeRepository::for()`.
- [x] Tester que la fabrique conserve l’isolation des magasins et les paramètres de livraison associés.
- [x] Actualiser le référentiel de code potentiellement inutilisé et documenter la rupture possible pour les consommateurs externes (5 candidats restants).
- [x] Suites PHP-WASM isolées : 37 tests unitaires locaux ; E2E WASM : 309 contrôles ; sécurité : 10 contrôles et autotest. CI du commit `839e311` réussie (PHP natif 8.2/8.5, PHPUnit, E2E WASM et MySQL 8 jetable).
- [!] La méthode étant publique, les consommateurs externes doivent migrer vers `CommandeRepository::for($store)` ; ils ne peuvent pas être inventoriés depuis ce dépôt. PHP natif, MariaDB et navigateur réel non joués localement.

## 6 unvicies. DSN, aide CLI et alternatives des visuels

- [x] Retirer l’accesseur DSN et son état inutilisés, sans modifier la connexion SQL.
- [x] Ajouter une aide CLI qui utilise les comptes de démonstration sans initialiser ni modifier de données.
- [x] Créer un contrat et deux alternatives d’image : décorative et informative ; retirer le paramètre `$nom` de `visuel_produit` et adapter les vues.
- [x] Tester les deux alternatives et leur échappement, ajouter les tests CLI natifs isolés et actualiser les inventaires et rapports (2 candidats restants).
- [x] Suites PHP-WASM : 43 tests PHPUnit locaux ; E2E isolé : 309 contrôles ; sécurité : 10 contrôles et autotest. CI du commit `30b4c8a` réussie : PHP natif 8.2/8.5, tests CLI et refus HTTP, PHPUnit, E2E et MySQL 8 jetable.
- [!] PHP natif, MariaDB, WAVE et navigateur réel non disponibles localement ; identité d’une image indicative ne décrit pas son contenu visuel. Les appels externes à `dsnUtilise` et l’ancienne signature de `visuel_produit` sont cassés.

## 6 duovicies. Suppression de l’accesseur de code d’erreur SQL

- [x] Retirer `DbInstaller::codeDerniereErreur` sur demande, en conservant le code interne et `refusIdentifiants`.
- [x] Vérifier le classement des codes 1045, 1698, 2002 et 0 en injectant l’état interne sans connexion SQL ; référentiel actualisé (1 candidat restant).
- [x] Suites PHP-WASM isolées : 291 vérifications unitaires et 43 tests PHPUnit locaux ; E2E isolé : 309 contrôles ; sécurité : 10 contrôles et autotest. CI du commit `4860daa` réussie (PHP natif 8.2/8.5, PHPUnit, E2E WASM et MySQL 8 jetable).
- [!] Auto-évaluation : le test du classement injecte un état simulé ; il ne prouve pas un refus d’authentification réel. Les appels externes à l’accesseur supprimé seront cassés. PHP natif et MariaDB non disponibles localement.

## 6 tresvicies. Suppression de l’accesseur `.env` inutilisé

- [x] Retirer `EnvFile::existe()` sur demande, sans modifier les autres fonctions de configuration.
- [x] Tester l’initialisation et la non-modification d’un fichier `.env` temporaire ; actualiser l’audit (0 candidat dans le référentiel lexical).
- [x] Suites PHP-WASM : 293 vérifications unitaires et 43 tests PHPUnit locaux ; E2E isolé : 309 contrôles ; sécurité : 10 contrôles et autotest. CI du commit `690a2fe` réussie (PHP natif 8.2/8.5, PHPUnit, E2E WASM et MySQL 8 jetable).
- [!] Auto-évaluation : les tests utilisent des fichiers temporaires. Le scanner reste lexical et partiel ; zéro candidat ne garantit pas l’absence de code inutilisé. La suppression casse d’éventuels appels externes à cette méthode publique. PHP natif, MariaDB et navigateur réel non disponibles localement.

## 6 quatervicies. Intégration de `main` et résolution des conflits de la PR

- [x] Conserver le formulaire Contact selon le choix de l’utilisateur : journal local, aucun e-mail, aucune réponse garantie ; contrôler la cohérence de la politique de confidentialité.
- [x] Exiger `UI_BASE_URL` et `UI_TEST_INSTANCE_JETABLE=1` pour les 23 parcours UI introduits par `main` ; ne plus réinitialiser ni restaurer automatiquement les données locales. Ces parcours modifient toujours le serveur choisi : il doit être jetable.
- [x] Actualiser les inventaires structurels après l’ajout des fichiers de `main` ; ils n’attestent pas que les fonctionnalités associées sont validées.
- [x] Exécuter les suites PHP-WASM et le parcours E2E isolé : suites PHP historiques vertes, 43 tests PHPUnit locaux et 309 vérifications E2E ; données hôte inchangées.
- [x] Réconcilier les assertions de régression avec les parcours et la feuille CSS réellement chargée : suites PHP `*_test.php` vertes en WASM après correction des deux derniers tests ; 23 parcours UI verts en jsdom contre une copie jetable, dans l’ordre de la CI. Les images sont réutilisées par type conformément au choix de l’utilisateur ; le formulaire Contact est conservé.
- [x] Rejouer les suites PHP natives et les 23 parcours jsdom en CI Linux : verts sur le premier merge ; isolation E2E également verte.
- [ ] Vérifier réellement tableaux, formulaires et contrastes sur navigateur à 375 px ; les contrats CSS et jsdom ne prouvent pas le rendu visuel.
- [x] La double annulation JSON et la migration de la colonne ont été reprises et testées après cette intégration ; voir §6 quinquavicies pour les résultats et limites. Ne pas lancer les scripts de création/seed sur une base existante.
- [!] Non joués dans cette sandbox : PHP natif, MariaDB et navigateur réel/WAVE ; PHP natif et compilation des dépréciations ont été exécutés en CI Linux. Ne pas déduire la validité SQL ou responsive réelle des seuls contrôles WASM.
- [x] Rejouer la CI de la PR après correction de la collision Windows entre `env.php` et la classe `Env.php` : campagne GitHub `42652644960` verte sur le commit `7054ce7`, dont PHP natif Linux/Windows, UI jsdom, E2E isolé et SQL MySQL 8. Une tentative antérieure de téléchargement Composer (code 6) a réussi au rejeu.
- [ ] Ne pas considérer l’intégration totalement validée tant que MariaDB, concurrence JSON, migration existante et responsive réel restent non vérifiés. La PR #12 a depuis été fusionnée ; `dev` ne doit pas être modifiée.

## 6 quinquavicies. Renforcement des tests après intégration

- [x] Ajouter quatre scénarios à deux processus PHP sur magasin JSON temporaire : double annulation, commandes distinctes sur le même stock, refus d’accès et libération du verrou après interruption. Le verrou coopératif couvre les écritures du store et les transactions ; le contrôle d’annulation se fait après son acquisition.
- [!] Limites JSON : les écritures directes, le seed et les opérations dont la lecture précède la transaction ne sont pas protégées de bout en bout ; le commit multi-fichiers n’est pas atomique en cas de panne ou d’échec d’écriture. Le test ne prouve pas une sécurité générale de concurrence.
- [x] Ajouter une migration MariaDB limitée à la colonne nullable et un test de conservation/rejeu sur une base temporaire générée ; aucun total historique inconnu n’est inventé. DDL non transactionnel, contrôle du type, maintenance, sauvegarde et installation des routines sont des étapes distinctes.
- [x] Ajouter un job CI MariaDB 11.8 (migration, annulation et campagne SQL) et un job Chromium réel 375/1440 px sur copie jetable, avec captures et contrôles de débordement des parcours principaux.
- [x] CI GitHub `42657801630` verte sur `684762e` : quatre scénarios multiprocessus PHP, migration et campagne SQL sur MariaDB 11.8, et Chromium réel à 375/1440 px (huit pages par largeur), sans données hôte. Captures disponibles dans l’artefact `responsive-chromium` ; ces mesures ne remplacent pas une revue visuelle ou un audit complet d’accessibilité. PHP natif, MariaDB et Chromium restent indisponibles dans la sandbox locale.
- [ ] Vérifier les autres navigateurs et appareils réels, ainsi que la migration adaptée à la base existante sauvegardée ; ne jamais utiliser la fixture ou un seed sur une base réelle.

## 7. Reste à faire hors dépôt (ne peut pas être fait par un agent)

- [!] **Noms, prénoms, numéros étudiants** des 3 membres + emails → README §Membres et CDC §3.3 (aucune identité ne peut être inventée).
- [!] **Dépôt GitLab réel** + courriel « Composition SAE » à la référente ; badge et URLs à remplacer.
- [!] **Rejeu des 32 commits** par les 3 personnes (`docs/08` + `scripts/rejeu_gitlab.py`) : l'historique doit être individuel et daté, il ne peut pas être fabriqué ici (le dépôt n'a qu'un commit).
- [!] **Couverture PHPUnit chiffrée** : nécessite Xdebug sur une machine réelle.
- [!] **Mesures ENF-01/ENF-08/ENF-15** : nécessitent un serveur HTTP et MySQL réels. L'outil est livré (`scripts/capture_perf.sh`) ; le fichier de sortie `tests/perf/dernieres_mesures.txt` est **volontairement non versionné et non simulé** — des temps machine ne se fabriquent pas dans une sandbox sans serveur.
- [!] **Vidéo de secours** de la démo.

---

## 8. Ce que la version précédente de ce document affirmait à tort

Deux erreurs distinctes, toutes deux vérifiées le 23/10/2026 :

1. **Des livrables cochés `[x]` n'existaient pas du tout** :
   `scripts/build-docs.sh` · `scripts/coverage.php` · `tests/php/contract_http.php` ·
   `tests/php/sql_integration.php` · `docs/11-strategie-tests.md` · `livrables/**` (HTML, DOCX, PDF, PPTX) ·
   les appels des **procédures stockées** par les dépôts · les **11 corrections de chiffres** du §6.
2. **Des livrables existaient sur le disque mais n'étaient pas versionnés** (`git status` les listait
   en `??`) : `docs/diagrams/render.sh`, `docs/diagrams/src/*.puml` (14), `tests/charge/reserver.sh`,
   `tests/perf/mesurer.sh`, `.gitlab/merge_request_templates/*.md`, `var/.gitkeep`, `public/css|js|img`,
   `tests/phpunit/**`, `docs/09`, `docs/10`. Un clone du dépôt ne les contenait pas : ils sont
   désormais suivis par Git (voir le commit de cette passe).

---

## Commandes de vérification (état réel)

```bash
npm install                            # une fois (PHP-WASM)
node scripts/run-all-tests.mjs         # 6 suites + E2E — attendu : tout vert
npm run test:phpunit                   # 34 tests PHPUnit hors ligne
npm run test:design                    # 28 contrats de design
bash tests/security/controles.sh       # 10/10 conformes
bash -n scripts/{load_db,run_sql_tests,gen_volumes}.sh
ls scripts/build-docs.sh docs/diagrams/render.sh tests/perf/mesurer.sh   # → introuvables
```

### Audit de l'ancien mail — corrections en cours
- [x] Annulation administrateur : confirmation sans JavaScript, montant et stock ; pour une commande expédiée, aucun retour de stock automatique ; verrouillage SQL des commandes et produits pour ces parcours.
- [ ] Rendre l'historique SQL lisible et clarifier les libellés panier/quantités.
- [x] Tests JSON et SQL ajoutés ; migrations SQL ciblées et limites documentées. [!] Les identités et coordonnées légales restent à renseigner ; aucune migration ni revue sur LineServ. Les visuels de repli et le pied sombre sont conservés : le mail doit être actualisé.

- [x] Suite PHP-WASM hors SQL/E2E : verte (dont PHPUnit 43/43, responsive contractuel 54, design 71) ; annulation historique 17/17 et Mes commandes 14/14.
- [x] CI GitHub `42791754935` (commit `205c0b7`) : MariaDB 11.8 et migrations ciblées, MySQL 8, concurrence JSON (dont admin/client), Chromium 375/1440 avec confirmation sans JavaScript, Windows et E2E verts. [!] Pas de test concurrent SQL spécifique, ni validation sur LineServ, tous les appareils ou anciennes bases.

### Comptes inactifs — durée et application (point 4)
- [x] Vérifier les sources officielles : la CNIL recommande 24 mois après la dernière action pour les comptes inactifs ; 36 mois vise notamment la prospection. Choix utilisateur : 24 mois.
- [x] Traitement CLI conservateur, simulation par défaut et exécution explicite : comptes sans commande seulement, verrou et relecture, dates incertaines exclues. Les comptes avec commandes sont conservés et signalés.
- [x] Vues, documentation, tests, valeur initiale à 24 mois et migration SQL conditionnelle. Aucune migration exécutée sur une instance réelle.
- [x] CI `42794381999` (commit `c12f0bb`) : JSON et CLI sur copies jetables, MariaDB 11.8, MySQL 8, Chromium, Windows et E2E verts.
- [!] La tâche planifiée n’est pas installée, les comptes avec commande et les anciennes copies/journaux ne sont pas purgés ; aucun effacement global ni conformité RGPD revendiqués.

### Copies restreintes et politique proposée (point 4)
- [x] Documenter par catégorie les durées proposées, les obligations conditionnelles, les droits d'accès, les sauvegardes et les mesures à valider : `docs/18-politique-conservation.md`.
- [x] Ajouter un outil CLI de copie chiffrée à clé publique, simulation par défaut, fichiers et répertoires privés, clé privée séparée et journalisation locale des restitutions ; tests sur fichiers jetables. L'outil ne modifie pas la source.
- [!] **Point 4 non terminé** : les commandes/comptes restent dans la base active ; il manque sélection minimale et transfert effectif sûr, retrait des originaux et restrictions vérifiées. L'outil ne réalise ni purge des messages/journaux ni gestion des sauvegardes ou des échéances ; journal de consultation non inviolable et traçabilité contournable avec la clé privée. Aucune validation du responsable, installation ni intervention réelle.
- [ ] Faire valider la politique et les habilitations ; développer et tester le transfert et la purge, y compris concurrence, restaurations, anciennes copies et droits des personnes ; inventorier et traiter les données de l'hébergeur avant activation.

- [x] CI `42796265969` (commit `e73c061`) : tests de copies chiffrées en PHP natif, lint et autres jobs réussis ; audits de structure et rétention PHP-WASM locaux réussis. [!] Auto-évaluation : la copie chiffrée et sa restitution sont testées, mais l'archivage intermédiaire réel, les purges et les habilitations restent incomplets ; absence de validation ou d'essai sur l'hébergement réel.

### Nettoyage demandé dans l'initialisation
- [x] Choix utilisateur : comptes avec commandes simulées terminées à supprimer si absence d'obligation ; Contact après 12 mois depuis réception, journaux après 6 mois, sauvegardes d'un dossier dédié après 30 jours.
- [x] Ajouter à `db_init.php` une maintenance distincte, simulée par défaut et sans recréation de base ; exécution et attestation explicites par catégorie. Rotation ciblée des journaux et Contact, verrou commun avec les écritures, dates incertaines conservées ; rotation des sauvegardes locales strictement identifiées avec deux copies préservées.
- [!] La validation humaine de l'absence d'exception est globale à chaque catégorie : aucun gel sélectif automatique. Les copies externes et hébergeur ne sont pas traitées, les sauvegardes ne sont pas vérifiées et aucun cron n'est installé.
- [ ] Comptes avec commandes : développer une purge dédiée testée et autorisée dossier par dossier. Les triggers actuels interdisent la suppression des commandes validées et la suppression des lignes remet le stock ; ne pas les contourner. Les comptes avec commandes restent exclus.
- [ ] Valider les durées, habilitations, sauvegardes, exceptions et conditions d'exploitation ; tester sur des copies et redémarrer les anciens processus avant toute utilisation réelle.
- [x] CI `42798289371` (commit `d5ceec5`) réussie : tests PHP natifs de rotation, CLI et écritures concurrentes sur fichiers jetables, plus les autres jobs. En local : suites PHP-WASM hors SQL et E2E isolé (309 vérifications) réussis ; PHP natif/POSIX et SQL non disponibles localement.
- [!] Auto-évaluation : la rotation ciblée est testée, mais elle ne contrôle ni les gels individuels ni l'intégrité des sauvegardes, ne traite pas les copies d'hébergement et ne résout pas la suppression des comptes avec commandes. Aucune exécution ou planification réelle sur LineServ.


### Fuseau de session SQL (point 5)
- [x] Vérifier le code : PHP interprète les dates comme UTC, mais les sessions PDO SQL n'imposaient pas UTC alors que les valeurs `CURRENT_TIMESTAMP` en dépendaient ; les anciens tests PHP ne vérifiaient pas ce point.
- [x] Fixer UTC sur les nouvelles connexions centrales, les connexions PDO injectées et celles du générateur de volumétrie ; refuser une session non initialisable. Ajouter un test SQL sur base jetable avec session initialement UTC+5:30 et dates d'hiver/été, plus un test de refus en mode silencieux.
- [!] Les dates SQL déjà écrites ne sont ni vérifiées ni modifiées automatiquement. Les connexions ou imports externes, les anciennes sessions et le déploiement réel doivent être contrôlés séparément. Aucune intervention sur LineServ.
- [ ] Vérifier la configuration et la provenance des anciennes dates de l'instance réelle, sur une copie sauvegardée ; ne pas appliquer de décalage uniforme sans preuve.
- [x] CI `42800454010` sur `31ba72b` : intégration sur MySQL 8 et MariaDB 11.8 réussie, y compris session décalée, dates automatiques et affichage hiver/été ; test PHP-WASM 46/46 et audit structurel locaux réussis.
- [!] Auto-évaluation : les futures écritures sur les connexions corrigées sont vérifiées en CI ; les anciennes données et l'instance réellement déployée ne le sont pas. Aucune conclusion sur leur fuseau sans vérification sur place.


### Fuseau personnalisable et tableaux admin mobiles (points 5 et 13)
- [x] Étudier les pratiques shadcn/ui, USWDS et W3C avant les changements ; décisions dans `docs/DA.md`.
- [x] Ancien réglage `FUSEAU_AFFICHAGE` dans `.env` (remplacé par les préférences personnelles ci-dessous) : Europe/Paris par défaut ou UTC±n[:mm] fixe ; stockage SQL/JSON et rétention restent en UTC. Les filtres de dates admin utilisent les bornes du jour affiché converties en UTC ; le tableau de bord utilise aussi le jour affiché ; les numéros gardent l'année civile de Paris. Les dates des cartes d’accueil suivent le même réglage.
- [x] Afficher les tableaux administrateur en fiches mobiles à champs étiquetés, valeurs repliables et actions accessibles, sans installer React ou ajouter de dépendance de production.
- [!] Un décalage fixe n'applique pas automatiquement l'heure d'été. Dates anciennes non modifiées ; leur provenance est à examiner. Rendu lecteur d'écran et appareils réels restent à vérifier.
- [x] Vérifications : `heure_test.php` 65/65, `date_ajout_test.php` 13/13, contrat responsive 62 contrôles, suites PHP-WASM sans SQL et E2E isolé 309 contrôles. CI [42802784052](https://github.com/jojo8356/SAE-302/actions/runs/42802784052) verte sur le code `e130a50` : Chromium 320/375/1440 px (y compris valeurs longues, filtres et formulaires, sans JavaScript), MySQL 8 et MariaDB 11.8. Aucun test n'a modifié les données de production.
- [!] Auto-évaluation : la structure de tableau et les libellés restent présents, et les limites locales fixes et DST sont couvertes ; pas de validation sur vrais téléphones, zoom avec technologie d'assistance ni lecteur d'écran. La provenance des dates historiques et la purge des comptes avec commandes (point 4, toujours partiel) restent à traiter séparément.

### Préférences de fuseau individuelles
- [x] Retirer la configuration `FUSEAU_AFFICHAGE` du serveur et du modèle `.env` ; conserver UTC au stockage.
- [x] Préférences accessibles à tous : choix manuel ou localisation volontaire, mémorisées seulement dans le navigateur, repli français/anglais ; expliquer la protection des données et les limites.
- [x] Dates des vues repérées, filtres de dates et indicateurs du jour cohérents avec le fuseau choisi ; repli sans JavaScript.
- [x] Tests : préférences en DOM (fuseaux, hiver/été, localisation autorisée/refusée et stockage indisponible), 54 vérifications PHP, 309 contrôles HTTP isolés ; CI [42805247556](https://github.com/jojo8356/SAE-302/actions/runs/42805247556) verte sur `f90b9f7` (Chromium 320/375/1440, JavaScript et repli sans JavaScript, MySQL et MariaDB).
- [!] Auto-évaluation : la localisation ne transmet pas les coordonnées par MiniShop mais le navigateur ou le système peut utiliser ses services. La bibliothèque fournit une estimation qui peut être erronée et demande vérification ; les bornes UTC sont envoyées au serveur. Tests sur appareils et lecteurs d’écran réels, et conformité RGPD globale, restent à vérifier.
- [x] Traduction anglaise FR/EN complète de l’interface (`public/js/i18n-en.js`, `public/js/preferences.js`, `public/js/panier.js`, `public/js/ui.js`, `.tableau-panier td.produit` dans `public/css/app.css`) : vues publiques, pages d’information et pages légales, catalogue, panier, validation, authentification, espace client, back-office complet, messages flash/API/`BusinessError`, synchronisation `<html lang>` et `document.title`, réversibilité sans perte FR ↔ EN et recalcul automatique des bornes UTC si une URL filtrée `/admin/commandes` provient d’un ancien fuseau.
- [!] Limites connues de la traduction : les enregistrements libres stockés en base (noms et descriptions de produits du catalogue, adresses saisies, commentaires d’historique libres) restent dans leur langue d’enregistrement ; sans JavaScript, le rendu serveur reste en français ; la traduction anglaise des pages légales est pédagogique et n’a pas fait l’objet d’une revue par un juriste.

### Vérification RGPD du dépôt (23 octobre 2026 — volet clos au niveau maquette scolaire)
- [x] Auditer le code, les vues légales, les préférences locales, la géolocalisation navigateur, les cookies, les journaux, les outils de purge/archivage et la sécurité applicative au regard du RGPD (art. 5, 6, 7, 12–22, 25, 28, 30, 32–35) et des recommandations CNIL (cookies/traceurs consolidés 2025–2026, localisation, conservation).
- [x] Minimiser aussi les nouvelles traces JSON de la table `administrateur` dans `app/Model/Data/Journal.php` (aucun nom, e-mail ni `mot_de_passe_hash` dans `old`/`new`, testé dans `JournalEtTableTest`), corriger la note `docs/07-note-cybersecurite.md` et actualiser `docs/12-information-juridique.md` ainsi que `app/View/home/confidentialite.php`.
- [x] Préciser dans `/preferences` (FR/EN) que le bouton « Réinitialiser » supprime le choix local mais ne révoque pas la permission de géolocalisation accordée au navigateur ; retirer dans `app/View/errors/500.php` l’affirmation inexacte « aucune donnée n’a été modifiée ».
- [!] Hors périmètre de la maquette scolaire (clos sur décision explicite de l’utilisateur ; obligatoire uniquement avant exploitation réelle) : circuit opérationnel d’exercice des droits (art. 12 et 15–20 : export/portabilité, effacement, limitation, opposition), séparation entre inscription et acceptation des CGV, limitation de débit sur `/connexion` et `/admin/connexion`, `APP_DEBUG=false` par défaut, retrait des comptes démo et des scripts `mesurer.php`/`gen_volumes.php` du webroot public.
- [!] Auto-évaluation RGPD : le dépôt est **transparent et cohérent pour une maquette scolaire de soutenance** (avec mentions d’information désormais traduites en anglais), mais **n’est pas conforme RGPD pour un traitement commercial réel** tant que les chantiers hors maquette ci-dessus, l’identité du responsable/DPO, le registre art. 30, les contrats art. 28, l’audit d’hébergement LineServ, la procédure art. 33–34 et la purge dédiée des comptes avec commandes ne sont pas mis en œuvre.

### Mentions légales de la maquette étudiante (point 20)
- [x] Renseigner `/mentions-legales` (`app/View/home/mentions_legales.php`, `app/View/home/cgu.php`, `public/js/i18n-en.js`) avec les informations choisies et fournies par l’utilisateur : édition à titre non professionnel par **Johan Polsinelli** et **Chahd Gouiaa** (BUT Informatique 2e année, IUT Nice Côte d’Azur — Université Côte d’Azur, SAE S3.02), co-responsabilité de la publication, adresses e-mail universitaires (`johan.polsinelli@etu.unice.fr`, `chahd.gouiaa@etu.unice.fr`, `chahd.gouiaa@etu.univ-cotedazur.fr`) sans publication des domiciles ni téléphones personnels (art. 6, III, 2 LCEN), et hébergement pédagogique visé (**Université Côte d’Azur — IUT Nice Côte d’Azur**, `linserv-info-01.campus.unice.fr`, 28 avenue Valrose, 06103 Nice Cedex 2 — Tél. : `+33 (0)4 89 15 00 00`).
- [x] Aligner les tests PHP (`tests/php/mentions_legales_test.php`), E2E UI (`scripts/ui-e2e-mentions.mjs`), DOM FR/EN (`tests/node/preferences_test.mjs`), Chromium responsive (`tests/node/responsive_navigateur.mjs`) ainsi que `README.md` et `docs/12-information-juridique.md`.
- [!] Auto-évaluation : ces mentions couvrent fidèlement l’édition étudiante non professionnelle de la maquette et son serveur pédagogique visé, tout en rappelant honnêtement qu’une exécution locale fonctionne hors de ce serveur et qu’aucun vendeur professionnel n’est identifié ; toute ouverture commerciale exigerait un éditeur professionnel immatriculé, ses coordonnées complètes et celles de l’hébergeur effectif de production.

### Harmonisation et complétude des tests automatisés (23 octobre 2026)
- [x] **Isolation stricte vis-à-vis des données hôte** : `tests/php/codes_techniques_test.php`, `tests/php/divers_test.php` et `tests/python/test_retention_cli.py` initialisent désormais un magasin JSON temporaire dédié (`Seed::force` sous `sys_get_temp_dir()` / `TemporaryDirectory`) au lieu de lire `data/minishop`.
- [x] **PHPUnit (9 classes, 50 tests — compatible PHPUnit JUnit et `tests/phpunit/run-local.php`)** : ajout de `tests/phpunit/ConservationEtFuseauTest.php` (`Heure` UTC/Europe-Paris, bascules DST 23 h / 25 h, tolérance `borneTransmise`, `RetentionComptes` simulation/commandes/dates futures/purge réelle, `NettoyageJournaux` & `NettoyageSauvegardes`), enrichissement de `JsonStoreTest` (annulation client avec restitution de stock, refus d’usurpation et de double annulation, annulation admin d’une commande `EXPEDIEE` sans retour de stock, refus client `ANNULATION_TROP_TARDIVE`) et clarification de `TriggersTest`.
- [x] **SQL MySQL 8.0 & MariaDB 11.8 (`tests/php/migration_mariadb_test.php`, `tests/php/sql_integration.php` étape 6 après `run_tests.sh`)** : vérification sur base jetable de `sp_cancel_order`, de la garde `ANNULATION_VIA_PARCOURS_DEDIE` sur `sp_update_order_status`, de l’annulation admin `EXPEDIEE` sans restitution de stock, du filtrage UTC de `revenueReport` et de `RetentionComptes` sur `SqlStore`.
- [x] **Parcours HTTP E2E, DOM/JS et Chromium responsive** : section `T15` dans `scripts/wasm-e2e.mjs` (338 vérifications : mentions LCEN, `/preferences` sans compte ni cookie de suivi, annulation client en 2 étapes, annulation admin sans JS en 2 étapes sur commande `EXPEDIEE` sans retour de stock, filtres UTC `/admin/commandes` et `/admin`), aller-retour `FR -> EN -> FR` sans perte sur les 48 vues + tests JSDOM `panier.js` en anglais dans `tests/node/preferences_test.mjs`, et couverture responsive 320/375/1440 px de toutes les pages légales EN + annonce AJAX panier EN + confirmation d’annulation admin sans JS d’une commande `EXPEDIEE` dans `tests/node/responsive_navigateur.mjs`.
- [x] **Orchestrateurs & pipelines CI (`scripts/run-all-tests.php`, `scripts/run-all-tests.mjs`, `.github/workflows/ci.yml`, `.gitlab-ci.yml`)** : intégration des 26 suites `tests/php/*_test.php` manquantes, de toutes les suites Python CLI/SQL LineServ, de `preferences_test.mjs` et de `e2e_isolation.mjs`, ainsi que du lint syntaxique de `tests/phpunit/`.
- [!] Auto-évaluation : dans la sandbox locale sans binaire PHP natif ni démon SQL, les suites SQL (`sql_integration.php`, `migration_mariadb_test.php`), les sous-processus CLI PHP et le rendu Chromium réel sont validés par le pipeline GitHub Actions (PHP 8.2/8.5, MySQL 8.0, MariaDB 11.8, Playwright Chromium) et non par la seule sandbox WASM ; les tests automatisés ne remplacent ni un essai sur appareils mobiles physiques et lecteurs d’écran réels, ni la vérification de l’instance déployée sur LineServ.

### Refonte et allègement des suites de tests manuels (23 octobre 2026)
- [x] **Fiches de recette `UC-01` à `UC-14` + transversal (`docs/13-fiches-recette-uc.md`) et journal CSV (`docs/13-suivi-recette-uc.csv`)** : correction des scénarios obsolètes et retrait explicite des campagnes responsive multi-largeurs des suites manuelles (jugées trop longues à jouer à la main, déléguées aux suites automatisées `responsive_contract.php` et `responsive_navigateur.mjs`) — soit **164 scénarios** (`TC01-01`..`TC14-12` + `TCX-01`..`TCX-24`) et **36 couples** `TC14-09/*` (**200 lignes** initialisées à `NON JOUÉ`) :
  - annulation client en 2 étapes sans effet de bord en `GET` (`/mes-commandes/{id}/annulation/confirmation`), conservation des lignes en historique et du `Total de la commande avant annulation`, concurrence client/admin sans double crédit de stock (`TC08-08`, `TC09-01`, `TC09-09`) ;
  - suppression produit et catégorie en 2 étapes sans JavaScript (`TC10-06`, `TC10-07`, `TC11-03`, `TC11-04`), refus strict des doublons de catégories sans fusion silencieuse (`TC11-05`), mode d’ajustement de stock par défaut additif `DELTA` « Ajouter / retirer » (`TC12-01`, `TC12-02`) ;
  - annulation administrateur en 2 étapes sans JavaScript : restitution unique du stock avant expédition (`TC14-06`), avertissement dédié et **absence** de restitution automatique du stock sur commande déjà `EXPEDIEE` (`TC14-11`), garde SQL `ANNULATION_VIA_PARCOURS_DEDIE` (`TC14-12`) ;
  - double implémentation décorative (`alt=""`) / informative (`alt="Visuel indicatif : …"`) des visuels produits (`TC01-10`, `TC03-09`), unicité des totaux et bouton principal sur `/panier` (`TC06-01`) et mentions précontractuelles / total bouton sur `/commande/valider` (`TC07-01`) ;
  - page `/preferences` sans compte ni cookie de suivi (`localStorage` seul, réinitialisation, replis sans JS et stockage bloqué, `TCX-19`), géolocalisation volontaire 100 % locale CC0 `tz-lookup.js` sans requête réseau externe ni coordonnée conservée (`TCX-20`), traduction intégrale FR ↔ EN sur ordinateur (front, panier AJAX, pages légales, espace client et back-office, `TCX-21`, `TCX-22`), stockage UTC et bornes locales de dates/filtres admin (`TC01-08`, `TC13-03`, `TC13-11`, `TCX-23`) ;
  - mentions légales étudiantes LCEN art. 6, III, 2 avec co-éditeurs Johan Polsinelli & Chahd Gouiaa et hébergeur IUT (`TCX-18`), politique de conservation à `24 mois` et outils CLI `purger_comptes.php`, `nettoyage_donnees.php`, `archive_restreinte.php` sur copie jetable (`TCX-17`), minimisation des traces `Journal.php` et transparence `/confidentialite` & `/500` (`TCX-24`).
- [x] **Retrait du responsive des documents et consignes manuels connexes** : suppression des 9 scénarios responsive manuels (`TC01-09`, `TC06-11`, `TC07-13`, `TC08-09`, `TC10-12`, `TC11-09`, `TC12-10`, `TC13-11`, `TCX-13`), retrait du point « Responsive — toutes les pages » dans `docs/15-email-recette-lineserv.md`, allègement de la checklist §7 et marquage hors campagne du §8 dans `docs/14-guide-lineserv.md`, passage de `F-39/40/41/46/47` en couverture 100 % automatisée dans `docs/02-document-tests-validation.md` (+ régénération HTML/PDF/DOCX), mise à jour de `docs/11-strategie-tests.md` et ajout d’un 46e contrôle garde-fou dans `scripts/verifier_fiches_uc.py` interdisant le retour des balayages multi-largeurs dans les scénarios manuels.
- [!] Auto-évaluation : aucun résultat d’exécution humaine, testeur, date ni mesure serveur n’est inventé — les **200 lignes** de `docs/13-suivi-recette-uc.csv` demeurent honnêtement à `NON JOUÉ`, et l’absence de campagne manuelle responsive sur téléphones physiques est explicitement documentée comme limite assumée compensée par la CI Chromium.


### Documentation d’architecture et de stack
- [x] `ARCHITECTURE.md` à la racine : documenter l’entrée HTTP, le routeur et ses gardes, les couches contrôleurs/repositories/vues, le parcours d’une requête, les règles métier, les deux pilotes de stockage, la sécurité, les tests et les limites sans présenter le SQL ou un déploiement comme validé sans preuve.
- [x] `STACK.md` à la racine : recenser les langages, prérequis runtime, stockage SQL optionnel et outils de test/génération documentaire en distinguant leurs usages ; s’appuyer sur `composer.json`, `package.json`, `package-lock.json`, `requirements-docs.txt` et les workflows CI.
- [x] Ajouter les liens vers ces deux documents dans la table de documentation du `README.md` et conserver le repère `**Cadre**` (SAE — BUT Informatique (BUT2)) requis par le contrôle E2E des mentions légales.
- [!] Comparaison au `main` GitLab étudiant : aucun `fetch` IUT ni aucune synchronisation GitLab effectués dans cette passe. Les commandes de comparaison doivent utiliser des refspecs explicites et préserver les branches/fichiers locaux ; aucun push sur `dev` n’est autorisé à ce stade.

