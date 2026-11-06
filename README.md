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

### Lancement : Linux d'abord, Windows/XAMPP en appoint

Le serveur cible (démo et production) est **sous Linux** : Apache 2.4,
`DocumentRoot = public/`, et `public/.htaccess` fait foi pour le routage.
Windows/XAMPP n'est **pas** une cible de déploiement — le lancement
ci-dessous n'est qu'une commodité de développement, supportée au même
titre que `php -S` sous Linux, sans MySQL/Apache à configurer (le moteur
livré est le moteur JSON, cf. « État réel du dépôt »).

```bash
# Routeur optionnel (émule le .htaccess sous php -S : fichiers réels de
# public/ servis directement, le reste passe par public/index.php).
# scripts/dev-router.php résout ses chemins depuis son propre dossier :
# aucun réglage XAMPP/Apache n'est nécessaire.
php -S localhost:8000 -t public scripts/dev-router.php
```

> ⚠️ Les deux échecs Windows signalés venaient de la commande, pas du
> code : (1) « `Fatal error: Failed opening required
> scripts/dev-router.php` » = le fichier n'existait pas dans le dépôt
> (il est livré désormais) **ou** la commande n'était pas lancée depuis
> la racine du projet — `scripts/…` et `-t public` sont relatifs au
> dossier courant ; (2) « le serveur démarre puis s'arrête sans
> message » = même cause côté `-t public`. Depuis PowerShell, se placer
> d'abord à la racine, et si `php` n'est pas dans le `PATH` (cas XAMPP),
> l'appeler par son chemin complet :
>
> ```powershell
> cd C:\xampp\htdocs\minishop
> C:\xampp\php\php.exe -S localhost:8000 -t public scripts\dev-router.php
> ```
>
> Variante valable depuis n'importe quel dossier : `-t` et routeur en
> chemins absolus
> (`-t C:\xampp\htdocs\minishop\public C:\xampp\htdocs\minishop\scripts\dev-router.php`).
> Exécuter `php scripts\dev-router.php` seul ne lance aucun serveur :
> le fichier affiche désormais l'aide au lieu de s'arrêter en silence.

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
| `app/**`, `public/**`, `data/`, `scripts/seed.php` | **application livrée et testée** (PHP pur, moteur JSON, zéro SQL ; une seule feuille CSS, `public/minishop.css`, limitée au bouton d'action principal du panier) | `npm run test:all` → suites vertes (cf. « Tests livrés ») |
| `app/Model/Data/` | moteur « JSON-comme-SGBD » : schéma, contraintes (NOT NULL/ENUM/CHECK/UNIQUE/FK), triggers, transactions, vues, journal JSONL — piloté par l'énumération `StorageDriver` (JSON actif, SQL en stub pour la fusion finale) | `node scripts/wasm_run.mjs tests/php/engine_smoke.php` |
| `tests/php/**`, `scripts/wasm-e2e.mjs`, `scripts/run-all-tests.mjs` | **livrés et exécutés** (voir « Tests livrés » ci-dessus) | `npm run test:all` |
| `sql/`, `tests/sql/`, `docs/` (CDC, tests, soutenance, annexes, diagrammes) | **livrés** ; `sql/` = référence comportementale du moteur JSON | `./scripts/run_sql_tests.sh` → 29/29 |
| `tests/security/controles.sh`, `tests/charge/reserver.sh`, `tests/perf/mesurer.sh` | **scripts livrés** ; les deux derniers supposent l'application en service | `bash -n` passe |
| `tests/sql/out/*.log` | non versionnés (régénérables) — seul `tests/sql/rapport_tests_sql.md` est versionné, preuve de recette à l'appui | `.gitignore`, §14.1 règle 7 |

## Tests livrés

L'application est livrée avec **cinq suites, toutes vertes**, exécutables d'un bloc :

```bash
npm run test:all        # ou : node scripts/run-all-tests.mjs
```

> 🛡️ **Sécurité** : la note complète « failles testées → corrections → code » (S-01…S-10, SEC-01…SEC-13, limites assumées) est dans **`docs/07-note-cybersecurite.md`** ; les attaques correspondantes sont rejouées en continu par les batteries T6/T12/T13 de la suite E2E.

| Suite | Fichier | Couverture | Verdict |
|---|---|---|---|
| Linter strict types | `scripts/strict_types_lint.php` | règle `declare_strict_types` de php-cs-fixer + types natifs « PHPStan level 9 » (params, retours, propriétés) + **règle maison : opérateur ternaire interdit** (`? :` et `?:`, remplacés par `if/else` ; `??` et types nullables `?int` autorisés) sur **tout le livrable PHP** | ✅ 0 violation / 79 fichiers |
| Moteur JSON | `tests/php/engine_smoke.php` | contraintes, triggers, transactions, journal du moteur | ✅ vert |
| Règles de gestion | `tests/php/run_tests.php` | T-01…T-29 du document de tests (RB-01…RB-20, vues, frais de port) | ✅ 29/29 |
| Unitaires | `tests/php/unit_tests.php` | `Text` (fold/slug/collation), échappement, CSRF, **matrice RB-11 complète** (36 couples), `Filter`, `PanierSession`, prix TTC, journal | ✅ 98/98 |
| Fonctionnalités | `tests/php/features_test.php` | **UC-01…UC-14** via les repositories (catalogue, compte, panier, commande, annulation, back-office, indicateurs) | ✅ 108/108 |
| E2E HTTP | `scripts/wasm-e2e.mjs` | T1…T13 : parcours réels (cookies + CSRF) — pages publiques, auth, commande 488,40 €, annulation, back-office, cloisonnement, catalogue avancé, compte, panier (plafond/re-tri/prix falsifié), CRUD admin, filtres + audit + journal, **sécurité offensive** (injection, XSS stocké, IDOR, fixation de session, traversées, escalation), rôles RB-13 | ✅ 150/150 |

Commandes individuelles : `npm test` (règles), `npm run test:units`, `npm run test:features`, `npm run test:e2e` (re-seed puis parcours HTTP ; l'E2E **pollue** `data/minishop` par construction — re-seeder ensuite : `node scripts/wasm_run.mjs scripts/seed.php`).

**Suite routeur de développement** (`tests/php/dev_router_test.php`, hors WASM) : lance le vrai `php -S … scripts/dev-router.php` en sous-processus — démarrage depuis un dossier étranger en chemins absolus (cas XAMPP) et depuis la racine en chemins relatifs (commande ci-dessus), fichiers réels de `public/` servis/exécutés directement, 404 du frontal, 7 traversées sans fuite, aide en exécution directe. PHP natif requis (`php tests/php/dev_router_test.php`, ou `npm run test:router`) ; en sandbox PHP-WASM elle se déclare SKIP. Elle est bloquante en CI GitHub, sur **Linux et Windows** (job `dev-router`, `MINISHOP_ROUTER_TEST_STRICT=1`).

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

Les suites PHP sont **autonomes** (aucun SQL, magasins JSON temporaires par section) : elles s'exécutent telles quelles avec `php tests/php/<suite>.php` sur une machine normale, ou via `node scripts/wasm_run.mjs` en sandbox.

**Qualité de code type « Packagist »** : sur machine normale (avec Composer), `composer cs:check` (php-cs-fixer, config `.php-cs-fixer.php`) et `composer lint:types` (PHPStan level 9 + strict-rules, config `phpstan.neon`) — tous deux en `require-dev`, **aucune dépendance runtime**. Dans la sandbox (Packagist injoignable), `npm run lint:types` rejoue les mêmes vérifications via le tokenizer PHP. Passages du 28/09 : 4 fermetures non typées corrigées, puis **éradication des 156 opérateurs ternaires du livrable** (vues, contrôleurs, dépôts, moteur, tests) remplacés par des `if/else` — règle maison ajoutée au linter.

Compléments livrés par ailleurs : `bash tests/security/controles.sh` (contrôles statiques), `bash tests/charge/reserver.sh` (survente), `bash tests/perf/mesurer.sh` (p95, ENF-01), `docs/diagrams/render.sh` (14 diagrammes), `./scripts/build-docs.sh` (assemblage documentaire complet).

*Historique : la campagne SQL d'origine (`./scripts/run_sql_tests.sh`, 29/29, rapport `tests/sql/rapport_tests_sql.md`) reste jouable — `sql/` sert de référence comportementale au moteur JSON.*

## Arborescence

```
app/Config, app/Controller, app/Model, app/Repository, app/Security, app/View
docs/{01-cahier-des-charges, 02-document-tests-validation, 03-support-soutenance, 04-conception-bd-et-sql, 05-wbs-projet, 06-todolist}, docs/annexes, docs/diagrams/{src/*.puml, *.png}
public/{index.php, .htaccess, css, js, img, gen_volumes.php, mesurer.php (dev-only, navigateur)}
scripts/{load_db.sh, run_sql_tests.sh, deploy.php, build-docs.sh, dev-router.php (routeur php -S, dev), gen_volumes.php (wrapper dev)}
sql/{01_minishop_schema.sql, 02_minishop_procedures.sql, 03_minishop_triggers.sql, 04_minishop_demo.sql}
tests/{sql/** (manifest, fixture, tNN_*.sql, rapport), security/controles.sh, charge/reserver.sh, perf/mesurer_sql.sh, perf/mesurer.php→public/mesurer.php, php/**}
var/log  ·  .gitignore  ·  .gitlab-ci.yml (6 jobs, prod)  ·  .github/workflows/ci.yml (6 jobs + dev-router, dev/GitHub)
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
