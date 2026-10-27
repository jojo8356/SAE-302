# 10 — Todolist des corrections (audit `09`) — état **vérifié**

> Suivi d'exécution de l'audit [`09-audit-ecarts.md`](09-audit-ecarts.md).
> Légende : `[x]` fait **et vérifié sur disque** · `[ ]` **toujours manquant** · `[~]` fait autrement, justifié · `[!]` bloqué par l'environnement.
> Chaque ligne indique le fichier concerné et la commande qui permet de le constater.
>
> **Dernière vérification :** 2026-10-23 · branche `arena/6e1d3706-sae-302`.
> Cette version remplace la précédente, qui cochait des livrables **absents du dépôt** (voir §8).

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
- [ ] CI GitLab — `base-tests` et `unit` ont désormais leurs scripts, mais le job `docs` échoue encore (`render.sh` absent) ; aucun pipeline n'a été exécuté ici.
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
- [x] `public/img/{logo,logo-mark,favicon,illustration-achat}.svg`.
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
- [x] `tests/php/css_contract.php` — 28 vérifications (tokens, contrastes AA, classes des vues, a11y, intégration).
- [ ] `scripts/coverage.php` — **absent** ; `phpunit.xml` ne produit pas de couverture.
- [ ] `tests/php/contract_http.php` — **absent** : les contrats HTTP ne sont couverts que par l'E2E.
- [ ] `docs/11-strategie-tests.md` — **absent**.
- [x] **`root` sous Debian/Ubuntu — diagnostic corrigé deux fois.** Le compte créé par `mariadb-server` utilise le greffon `unix_socket`, donc PHP en TCP reçoit `[1698] Access denied`. Première correction : `app/Config/DbConnexion.php` (DSN unique, jusque-là dupliqué dans `SqlStore` et `DbInstaller`) réessaie par la socket Unix. **Insuffisant, et vérifié sur la machine de l'utilisateur** : `unix_socket` compare l'**utilisateur du système** du processus, pas un mot de passe — PHP tourne sous `jojokes`, le compte est `root`, la socket ne débloque donc rien (même erreur 1698). Le message affirmait « socket introuvable » tout en affichant la socket détectée : contradiction corrigée. Vraie solution retenue : `app/Tools/DbProvision.php` + `php scripts/db_init.php --compte` (enchaîné par `./run.sh --db`) créent `minishop@localhost` et `minishop@127.0.0.1` via un `sudo mariadb` ponctuel, mot de passe aléatoire hexadécimal écrit dans `.env`, droits limités à `minishop.*` ; si `sudo` échoue, le SQL exact est affiché. 42 vérifications ajoutées (§15 DSN/socket/messages, §16 compte applicatif : validation des noms et de l'injection, nombre d'instructions, absence de `*.*`, mot de passe aléatoire, échec explicite sans client SQL).
- [x] **Récursion infinie** dans `scripts/db_init.php` : `erreur()` s'appelait elle-même au lieu d'écrire sur `STDERR` (`fwrite(STDERR, …)`) — tout message d'erreur en CLI faisait exploser la pile. Corrigé.
- [x] **E2E réellement joué** par `php scripts/run-all-tests.php` : l'étape Node est lancée quand `node` et `@php-wasm/node` sont présents, et annoncée **NON JOUÉE** avec la commande exacte sinon (le message conseillait un `./run.sh --e2e` qui n'existe pas). `intl` et `pdo_mysql` sont signalées comme facultatives, pas comme des échecs.
- [x] **Bug intl corrigé** : `Text.php` écrivait `Transliterator::create()` et `new Collator()` sans barre oblique initiale — dans l'espace de noms `App\Model\Data`, PHP cherchait donc `App\Model\Data\Transliterator`. Invisible dans la sandbox (intl absente, `class_exists()` renvoie faux), **fatal sur toute machine où intl est chargée** — c'est-à-dire la machine de rendu. Corrigé en `\Transliterator` / `\Collator`, et un contrat statique vérifie désormais que les classes globales d'extension sont référencées en absolu dans `app/` (test de non-régression validé en réintroduisant le bug).
- [x] **Piège fermé** : `scripts/run-all-tests.php` avait été créé sans balise `<?php` (PHP affichait donc son contenu au lieu de l'exécuter) — le linter ne couvrait pas `scripts/` autrement que par `seed.php`. Cible par défaut étendue aux scripts applicatifs (95 fichiers analysés au lieu de 90) ; `deploy.php` et `gen_volumes*.php`, antérieurs et non applicatifs, restent hors périmètre et c'est documenté.
- [x] `npm run test:all` exécute **7 suites** + l'E2E (moteur, règles, unitaires, fonctionnalités, design, responsive, PHPUnit) ; le linter strict-types est un contrôle séparé.

## 5. Rendus — **rien de produit**

- [x] HTML autonome des documents — `livrables/html/` (19 fichiers, images en base64, hors ligne).
- [x] DOCX — `livrables/docx/` (19 fichiers, images intégrées).
- [x] PDF — `livrables/pdf/` (19 fichiers A4 paginés, en-tête + pied de page).
- [x] Diaporama 12 slides — `livrables/minishop-soutenance.pptx` (16:9, source `docs/12-diaporama-soutenance.md`).
- [!] Vidéo de secours 3 min — non produisible sans navigateur/encodeur dans la sandbox.
- [x] `livrables/README.md` — index généré (formats, documents, commandes de régénération).
- Outillage : `scripts/md_to_{html,pdf,docx,pptx}.py` + `requirements-docs.txt` ; `bash scripts/build-docs.sh` rejoue tout en ~25 s.

## 6. Cohérence documentaire — **aucune correction appliquée**

| Point | État | Preuve |
|---|---|---|
| 29 tests SQL partout | [ ] | `docs/03` : « 28/28 » (l. 13, 27) et « 27 / 27 » ; `.gitlab-ci.yml` : « 28 tests » |
| E2E 180 | [x] | `README.md` corrigé (T1…T14, 180/180) |
| PHP 8.2 minimum | [x] | prérequis README aligné sur `composer.json` |
| `ENF-13` dupliqué → `ENF-22` | [ ] | `annexe-enf.md` : deux lignes `ENF-13` (l. 20 et 34), aucun `ENF-22` |
| MCD 8 entités | [ ] | `docs/03` l. 23 : « MCD (7 entités) » |
| Noms `01_minishop_schema.sql` | [ ] | `docs/07` et `docs/09` citent encore `01-minishop-schema.sql` |
| CDC §16.1/§16.4 à jour | [ ] | §16.1 : « à développer » / « à produire » ; §16.4 : « le code PHP n'est pas encore écrit » |
| Doc `02` : 33 `F-xx` exécutés + PV | [ ] | 33 lignes « conçu » ; « PV de recette laissé à compléter » ; « lots L8→L13 » |
| Doc `03` aligné sur l'application | [ ] | scénario encore écrit pour un terminal MySQL |
| README : arborescence, chiffres, badges | [~] | chiffres et « zéro CSS » corrigés ; badge GitLab toujours factice (`gitlab.example.com`) |

## 7. Reste à faire hors dépôt (ne peut pas être fait par un agent)

- [!] **Noms, prénoms, numéros étudiants** des 3 membres + emails → README §Membres et CDC §3.3 (aucune identité ne peut être inventée).
- [!] **Dépôt GitLab réel** + courriel « Composition SAE » à la référente ; badge et URLs à remplacer.
- [!] **Rejeu des 32 commits** par les 3 personnes (`docs/08` + `scripts/rejeu_gitlab.py`) : l'historique doit être individuel et daté, il ne peut pas être fabriqué ici (le dépôt n'a qu'un commit).
- [!] **Couverture PHPUnit chiffrée** : nécessite Xdebug sur une machine réelle.
- [!] **Mesures ENF-01/ENF-08/ENF-15** : nécessitent un serveur HTTP et MySQL réels.
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
