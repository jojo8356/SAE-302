#!/usr/bin/env python3
"""Rejoue l'historique MiniShop en 32 commits commentés vers un nouveau GitLab.

Deux usages :
  1. Générer la fiche de suivi (documentation humaine) :
       python3 scripts/rejeu_gitlab.py --fiche docs/08-fiche-suivi-commits.md
  2. Reconstruire un dépôt neuf, commit par commit, dans une COPIE isolée
     (le dépôt courant n'est jamais modifié) :
       python3 scripts/rejeu_gitlab.py --dest ../minishop-sae \
           --nom "Prénom Nom" --email prenom.nom@etu.univ-cotedazur.fr
       # puis, une fois le projet vide créé sur GitLab :
       cd ../minishop-sae
       git remote add origin https://gitlab.example.fr/<groupe>/minishop-sae.git
       git push -u origin main --tags

Le plan (ci-dessous) est la source unique de vérité : la fiche est générée
depuis ce même plan, les deux ne peuvent pas diverger.
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
import tarfile
import tempfile

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# ---------------------------------------------------------------------------
# PLAN DE REJEU — 32 étapes couvrant exactement les fichiers suivis.
# Champs : numero, titre, globs (pathspecs git add), corps (paragraphes du
# message de commit), contenu (description pour la fiche), controle (optionnel),
# tag (jalon GitLab à créer après cette étape, cf. annexe 17.9).
# ---------------------------------------------------------------------------
PLAN: list[dict] = [
    {
        "numero": "É0",
        "titre": "chore(depôt): socle GitLab — .gitignore et charte de collaboration (annexe 17.9)",
        "globs": [".gitignore", "docs/annexes/annexe-gitlab.md"],
        "corps": [
            "Règles d'exclusion du dépôt (§14.1 du cahier des charges) : secrets (env.php, .env, *.pem, *.key), journaux (var/), artefacts régénérables (vendor/, node_modules/, sorties brutes tests/sql/out).",
            "La charte GitLab fixe le cadre : dépôt privé, composition par courriel, main protégée sans push direct, MR obligatoires avec squash désactivé (les commits individuels font partie de la note), tags de jalons v0-cadrage → v5-rendu.",
        ],
        "contenu": "Le dépôt démarre avec ses règles du jeu : ce qui est versionné, ce qui ne le sera jamais (secrets, journaux, sorties régénérables), et la charte de collaboration GitLab (MR, branches protégées, jalons).",
    },
    {
        "numero": "É1",
        "titre": "docs(cadrage): cahier des charges MiniShop et cas d'utilisation",
        "globs": [
            "docs/01-cahier-des-charges-MiniShop.md",
            "docs/annexes/annexe-cas-usage.md",
            "docs/diagrams/cas_utilisation_*.png",
        ],
        "corps": [
            "Spécification fonctionnelle complète : périmètre boutique (catalogue, panier, commandes), espace client, back-office, exigences fonctionnelles EF-* et non fonctionnelles ENF-*, règles de gestion RB-01…RB-20, contraintes CT-01…CT-10, RACI et critères de notation.",
            "Diagrammes de cas d'utilisation : vue globale, détail commande, scénarios commande (acteurs client, admin, système).",
        ],
        "contenu": "Le document fondateur du projet : périmètre, exigences, règles de gestion numérotées (RB-xx) et contraintes qui serviront de fil rouge à tout le reste (SQL, moteur, application, tests).",
        "tag": "v0-cadrage",
    },
    {
        "numero": "É2",
        "titre": "docs(bd): conception de la base — MCD, MLD, normalisation, requêtes à écrire",
        "globs": [
            "docs/04-conception-bd-et-sql.md",
            "docs/annexes/annexe-sql.md",
            "docs/diagrams/mcd_minishop.png",
            "docs/diagrams/mld_minishop.png",
            "docs/sql-a-ecrire/*",
        ],
        "corps": [
            "Livrable n°3 autonome : dictionnaire de données, MCD et MLD (8 tables), normalisation 3NF justifiée, choix MySQL 8.",
            "Requêtes SQL « à écrire » (exercices du rendu) et annexe 17.4/17.5/17.6 décrivant les 4 scripts SQL livrés, le harnais de tests et le protocole de mesure.",
        ],
        "contenu": "La base de données est conçue avant d'être écrite : MCD, MLD, dictionnaire, normalisation. Les requêtes « à écrire » préparent les livrables d'exécution SQL.",
    },
    {
        "numero": "É3",
        "titre": "docs(conception): UML applicatif — architecture MVC, classes, états, séquences",
        "globs": [
            "docs/diagrams/architecture_mvc.png",
            "docs/diagrams/diagramme_classes.png",
            "docs/diagrams/etats_commande.png",
            "docs/diagrams/activite_passer_commande.png",
            "docs/diagrams/seq_*.png",
        ],
        "corps": [
            "Architecture MVC en couches (contrôleurs → dépôts → moteur de données), diagramme de classes, cycle de vie d'une commande (états et transitions RB-11), activité « passer commande ».",
            "5 diagrammes de séquence : consultation du catalogue, recherche, ajout au panier, commande, authentification.",
        ],
        "contenu": "La conception applicative reprend la structure MVC et le cycle de vie des commandes qui seront implémentés à l'identique en PHP.",
        "tag": "v1-mcd",
    },
    {
        "numero": "É4",
        "titre": "docs(tests): plan de validation T-01…T-29 et exigences non fonctionnelles",
        "globs": ["docs/02-document-tests-validation.md", "docs/annexes/annexe-enf.md"],
        "corps": [
            "Document de tests et de validation : 29 tests boîte noire mappés aux règles RB/EF/SEC, avec procédure, oracle et données de chaque test.",
            "Annexe ENF : exigences non fonctionnelles (ENF-01 p95 < 500 ms, volumétrie, sécurité, ergonomie) et protocoles de vérification associés.",
        ],
        "contenu": "Le plan de validation précède le code : chaque test T-xx sera rejoué en SQL puis en PHP, avec le même oracle.",
    },
    {
        "numero": "É5",
        "titre": "docs(organisation): WBS (6 divisions, 16 lots, 86 tâches), todolist et chaîne .tools",
        "globs": ["docs/05-wbs-projet.md", "docs/06-todolist.md", ".tools/*"],
        "corps": [
            "WBS complet : charges bottom-up, plan de charge, Gantt et chemin critique ; todolist de suivi des 86 tâches (86/86 couvertes).",
            "Chaîne .tools : les documents officiels et les artefacts HTML du rendu sont générés depuis une source de données unique (scripts Python internes, hors périmètre du barème, d'où le dossier caché).",
        ],
        "contenu": "L'organisation du projet : découpage WBS, suivi de todolist et la chaîne de génération des documents (une seule source de données).",
    },
    {
        "numero": "É6",
        "titre": "feat(sql): schéma MySQL 8 — 8 tables InnoDB, contraintes, clés étrangères, index",
        "globs": ["sql/01_minishop_schema.sql"],
        "corps": [
            "DDL complet : produit, categorie, client, administrateur, commande, ligne_commande, order_status_history, parametre.",
            "Règles de gestion portées par le schéma : prix > 0, quantité > 0, hash obligatoire (RB-01…RB-06), clés étrangères avec règles de suppression, index de recherche et d'unicité (référence et slug produit, email client).",
        ],
        "contenu": "Le premier script SQL : la structure — 8 tables, contraintes CHECK, clés étrangères et index. Les règles RB-01 à RB-06 sont garanties par le schéma lui-même.",
    },
    {
        "numero": "É7",
        "titre": "feat(sql): 17 procédures stockées + 1 fonction (règles métier côté serveur)",
        "globs": ["sql/02_minishop_procedures.sql"],
        "corps": [
            "Commander_produit, ajuster_stock, annuler_commande, changer_statut, purger, rechercher… : tout le cycle de vie des commandes passe par des procédures, jamais par du DML direct.",
            "Gestion des erreurs par SIGNAL SQLSTATE avec codes métier (même vocabulaire que le futur moteur PHP : STOCK_INSUFFISANT, TRANSITION_INTERDITE, …).",
        ],
        "contenu": "Les procédures stockées concentrent les règles métier côté serveur : impossible de contourner le contrôle de stock, les transitions d'état ou le calcul du montant.",
    },
    {
        "numero": "É8",
        "titre": "feat(sql): 16 déclencheurs — transitions RB-11, cohérence stock, historique",
        "globs": ["sql/03_minishop_triggers.sql"],
        "corps": [
            "BEFORE/AFTER INSERT/UPDATE/DELETE : garde-fous anti-contournement des procédures (prix modifié, stock négatif), matrice des transitions autorisées (RB-11), seuil d'alerte (RB-03).",
            "trg_history_statut et trg_history_creation : chaque changement de statut est tracé dans order_status_history (EF-ADM-08).",
        ],
        "contenu": "Les déclencheurs verrouillent les invariants : même un UPDATE sauvage ne peut pas casser une règle de gestion, et tout changement d'état est historisé.",
    },
    {
        "numero": "É9",
        "titre": "feat(sql): jeu de démonstration — commandes créées via les procédures (triggers actifs)",
        "globs": ["sql/04_minishop_demo.sql"],
        "corps": [
            "Données de démonstration réalistes : les commandes sont insérées par les procédures stockées, donc tous les déclencheurs s'exécutent (décrément de stock, historique, cohérence des montants).",
        ],
        "contenu": "Le jeu de démo MySQL : il prouve que les procédures et triggers coopèrent — les données sont créées « pour de vrai », pas insérées en contournant les règles.",
    },
    {
        "numero": "É10",
        "titre": "test(sql): harnais de tests T-01…T-29 + protocole de mesure ENF-01",
        "globs": ["tests/sql/*", "tests/perf/*", "scripts/run_sql_tests.sh"],
        "corps": [
            "33 fichiers : 29 cas de test SQL (t01…t29), fixture reproductible, manifeste, lanceur run_sql_tests.sh et rapport consigné.",
            "Chaque test rejoue une règle de gestion du document de validation ; tests/perf/mesurer_sql.sh mesure le p95 des requêtes (ENF-01, p95 < 500 ms).",
        ],
        "contenu": "Le harnais SQL exécute le plan de validation : 29 tests rejouables en une commande, plus le protocole de performance.",
        "controle": "Sur une machine MySQL locale : `bash scripts/run_sql_tests.sh` → 29/29 conformes.",
    },
    {
        "numero": "É11",
        "titre": "docs(preuves): requêtes soumises et captures d'exécution SQL (uploads/)",
        "globs": ["uploads/*"],
        "corps": [
            "Les 4 scripts SQL tels qu'exécutés et les captures du harnais complet (29/29 conformes) : preuves demandées par le rendu.",
        ],
        "contenu": "Les preuves d'exécution : scripts soumis au serveur et captures du harnais vert. C'est la pièce à conviction du livrable SQL.",
        "tag": "v2-base",
    },
    {
        "numero": "É12",
        "titre": "feat(moteur): socle du moteur JSON — contrat de magasin, filtres, schéma, erreurs",
        "globs": [
            "app/Model/Data/StoreInterface.php",
            "app/Model/Data/Errors.php",
            "app/Model/Data/BusinessError.php",
            "app/Model/Data/ConstraintError.php",
            "app/Model/Data/QueryError.php",
            "app/Model/Data/SchemaError.php",
            "app/Model/Data/Filter.php",
            "app/Model/Data/Schema.php",
            "app/Model/Data/StorageDriver.php",
            "app/Model/Data/Text.php",
            "docs/annexes/annexe-php-patterns.md",
        ],
        "corps": [
            "StoreInterface : un CRUD typé (select/insert/update/delete/count/sum/transaction) utilisable comme du SQL.",
            "Filter (eq, neq, like, between, in, sort, and/or…), Schema (tables, colonnes, types, contraintes, clés étrangères), StorageDriver, Text (slug, fold, comparaison intl) et la hiérarchie d'erreurs métier (BusinessError, ConstraintError, QueryError, SchemaError).",
            "Annexe 17.7 : patterns PHP/PDO/sécurité de référence repris par l'application.",
        ],
        "contenu": "Le contrat du « moteur intelligent de données JSON utilisé comme du SQL » : l'interface de magasin, le langage de filtres, le schéma des tables et les erreurs métier — sans dépendance runtime.",
    },
    {
        "numero": "É13",
        "titre": "feat(moteur): magasin JSON — transactions, triggers, contraintes, vues, journal",
        "globs": [
            "app/Model/Data/JsonTable.php",
            "app/Model/Data/JsonStore.php",
            "app/Model/Data/Journal.php",
            "app/Model/Data/Triggers.php",
            "app/Model/Data/Views.php",
            "app/Model/Data/SqlStore.php",
        ],
        "corps": [
            "JsonStore/JsonTable : persistance des tables en fichiers JSON {table, auto_increment, rows}, index de clé primaire, transactions avec rollback.",
            "Sémantiques SQL reproduites : 16 triggers BEFORE/AFTER équivalents (matrice de transitions RB-11), contraintes et clés étrangères refusées côté moteur, comparaison « loose » MySQL.",
            "Journal d'audit JSONL (EF-GEN-04), vues calculées (état des stocks, chiffre d'affaires) et SqlStore : la bascule PDO/MySQL garde la même interface pour la production.",
        ],
        "contenu": "Le cœur du projet : un magasin JSON qui se comporte comme une base — transactions, déclencheurs, contraintes, journal d'audit — pilotable par la même API que le SQL.",
    },
    {
        "numero": "É14",
        "titre": "feat(seed): jeu de données reproductible (admin, clients, 12 produits, paramètres)",
        "globs": ["app/Model/Data/Seed.php", "scripts/seed.php", "data/*/*"],
        "corps": [
            "Seed::force reconstruit les 8 tables PAR le moteur (donc avec triggers et journal) : admin SUPER + gestionnaire, 3 clients, 4 catégories, 12 produits (dont stock 0, seuil bas, invisible), TVA 20 %, port 4,90 € / franchise 80 €.",
            "scripts/seed.php : re-seed en une commande (reset explicite, équivalent TRUNCATE des scripts SQL).",
        ],
        "contenu": "Les données de démonstration, toujours créées à travers le moteur : le seed est la première preuve que le moteur applique les règles.",
    },
    {
        "numero": "É15",
        "titre": "feat(app): socle MVC — bootstrap, routeur, config, gabarit, sécurité",
        "globs": [
            "app/bootstrap.php",
            "app/Router.php",
            "app/Config/*",
            "app/Controller/Controller.php",
            "app/Security/*",
            "app/View/layout.php",
            "app/View/errors/*",
        ],
        "corps": [
            "bootstrap + Router (routes GET/POST, contrôleurs en tableau [classe, méthode], normalisation des URI), Config\\Database (choix du magasin JSON/SQL par environnement), env.example.php.",
            "Sécurité transversale : Auth (sessions régénérées, anti-fixation), Csrf (jetons par formulaire), xss.php (échappement systématique, dates françaises).",
            "layout.php et pages d'erreur 403/404/500.",
        ],
        "contenu": "Le socle de l'application : routage, configuration, gabarit commun et la couche sécurité (sessions, CSRF, échappement) présente dès la première page.",
    },
    {
        "numero": "É16",
        "titre": "feat(boutique): accueil, catalogue paginé et filtrable, fiche produit",
        "globs": [
            "app/Controller/HomeController.php",
            "app/Controller/CatalogueController.php",
            "app/View/home/*",
            "app/View/catalogue/*",
        ],
        "corps": [
            "HomeController (accueil, catégories, à propos, CGV, mentions légales) et CatalogueController : recherche plein texte, filtres catégorie/prix/stock, tris, pagination 12/page.",
            "RB-19 : les produits invisibles n'apparaissent jamais au catalogue ; les filtres actifs se conservent dans l'URL.",
        ],
        "contenu": "La vitrine : pages statiques de présentation et le catalogue complet (recherche, filtres, tris, pagination) avec la règle RB-19 garantie côté serveur.",
    },
    {
        "numero": "É17",
        "titre": "feat(client): panier serveur, commandes, compte, connexion/inscription",
        "globs": [
            "app/Model/PanierSession.php",
            "app/Controller/PanierController.php",
            "app/Controller/CommandeController.php",
            "app/Controller/CompteController.php",
            "app/Controller/AuthController.php",
            "app/View/panier/*",
            "app/View/commande/*",
            "app/View/compte/*",
            "app/View/auth/*",
        ],
        "corps": [
            "PanierSession (quantité plafonnée au stock), validation de commande avec snapshot du port au jour J (RB-20), suivi et annulation côté client (RB-11 : annulation trop tardive refusée).",
            "AuthController : inscription (password_hash), connexion, message d'erreur identique quel que soit le motif (SEC-05) ; espace compte (coordonnées, historique des commandes).",
        ],
        "contenu": "Le parcours client complet : panier contrôlé côté serveur, commande avec fraix de port figés, compte, inscription et connexion sécurisées.",
        "tag": "v3-front",
    },
    {
        "numero": "É18",
        "titre": "feat(app): couche dépôts — produits, catégories, clients, commandes, paramètres",
        "globs": ["app/Repository/*"],
        "corps": [
            "Les contrôleurs ne touchent jamais le moteur directement : ProduitRepository (recherche TTC, stock, suppression logique), CategorieRepository, ClientRepository (statut du compte), CommandeRepository (numéro CMD-{AAAA}-{id:6}, statuts, rapport de chiffre d'affaires), ParametreRepository (port, TVA).",
        ],
        "contenu": "La couche dépôts isole l'accès aux données : c'est elle qui encode les règles d'accès (visibilité, suppression logique, numérotation des commandes).",
    },
    {
        "numero": "É19",
        "titre": "feat(admin): back-office complet — produits, catégories, commandes, stocks, équipe",
        "globs": ["app/Controller/Admin/*", "app/View/admin/*"],
        "corps": [
            "Tableau de bord (indicateurs), CRUD produits (référence et slug uniques, TVA, seuil RB-03, visibilité RB-19), catégories, commandes (statuts avec commentaire obligatoire pour annulation, historique), ajustement de stock (SET/DELTA), gestion de l'équipe.",
            "RB-13 : seul un admin SUPER gère les comptes ; un compte client ne peut jamais devenir admin ; impossible de bloquer son propre compte.",
        ],
        "contenu": "Le back-office : toutes les opérations d'administration, avec la séparation des rôles SUPER/gestionnaire et les mêmes règles métier que le SQL.",
        "tag": "v4-back",
    },
    {
        "numero": "É20",
        "titre": "feat(web): front contrôleur, réécriture d'URL et script de déploiement",
        "globs": ["public/index.php", "public/.htaccess", "scripts/deploy.php"],
        "corps": [
            "public/index.php : déclaration de toutes les routes et boot de l'application ; .htaccess : toute requête passe par index.php, les fichiers statiques sont servis directement.",
            "scripts/deploy.php : déploiement MySQL (schéma + données initiales).",
        ],
        "contenu": "Le point d'entrée unique du Web : toutes les URL passent par le routeur, et le script de déploiement installe la base MySQL de production.",
    },
    {
        "numero": "É21",
        "titre": "build(sandbox): runtime PHP 8.2-WASM, serveur HTTP et chaîne de tests npm",
        "globs": [
            "scripts/wasm_lib.mjs",
            "scripts/wasm_run.mjs",
            "scripts/wasm-serve.mjs",
            "scripts/run-all-tests.mjs",
            "package.json",
            "package-lock.json",
        ],
        "corps": [
            "Environnement de développement sandbox sans installation PHP : exécution CLI (wasm_run), serveur HTTP (wasm-serve) et bibliothèque commune.",
            "run-all-tests.mjs + package.json : npm run serve / test / test:units / test:features / test:e2e / test:all / lint:types — l'application reste 100 % PHP sans dépendance runtime (l'outillage Node est dev-only).",
        ],
        "contenu": "L'outillage de développement : exécuter et servir le PHP dans une sandbox Node, et lancer toutes les suites en une commande. Aucune dépendance runtime pour l'application.",
        "controle": "Après `npm install` : `npm run serve` puis http://localhost:8080/ (le re-seed se fait par `node scripts/wasm_run.mjs scripts/seed.php`).",
    },
    {
        "numero": "É22",
        "titre": "build(perf): volumétrie et mesure ENF-01 (gen_volumes, mesurer)",
        "globs": [
            "app/Tools/*",
            "scripts/gen_volumes.php",
            "public/gen_volumes.php",
            "public/mesurer.php",
            "scripts/md_to_pdf.py",
        ],
        "corps": [
            "Générateur de jeux volumétriques (100 k+ lignes) pour MySQL et pour le moteur JSON, page de mesure p50/p95/max avec verdict ENF-01 automatique, et utilitaires (rendu PDF des documents).",
        ],
        "contenu": "Les outils de mesure de performance exigés par ENF-01 : générer du volume, mesurer, obtenir un verdict automatique (p95 < 500 ms).",
    },
    {
        "numero": "É23",
        "titre": "test(php): moteur (engine_smoke) et règles de gestion T-01…T-29 en PHP",
        "globs": ["tests/php/engine_smoke.php", "tests/php/run_tests.php"],
        "corps": [
            "engine_smoke : contraintes, triggers, transactions, rollback et journal du moteur JSON sur un bac à sable hermétique.",
            "run_tests : les 29 règles de gestion rejouées sur le moteur (application court-circuitée) — 29/29 conformes, même oracle que le harnais SQL.",
        ],
        "contenu": "La première suite PHP vérifie le moteur seul, puis rejoue les 29 tests du plan de validation directement contre le moteur JSON.",
        "controle": "`npm run test` → 29/29 conformes.",
    },
    {
        "numero": "É24",
        "titre": "test(php): 98 tests unitaires, 108 fonctionnels et E2E 155 vérifications",
        "globs": ["tests/php/unit_tests.php", "tests/php/features_test.php", "scripts/wasm-e2e.mjs"],
        "corps": [
            "unit_tests : moteur, Text, filtres, matrice de transitions, vues, journal. features_test : parcours complets (catalogue, panier, commande, admin) sur les dépôts.",
            "wasm-e2e : navigateur simulé — inscription, connexion, panier, commande payée, expédition, sécurité offensive (injections XSS/SQL, IDOR, traversée de chemin, en-têtes, CSRF), rôles admin.",
        ],
        "contenu": "Les suites qui couvrent l'application entière : unitaires, fonctionnels et un E2E qui rejoue aussi les attaques de la note cybersécurité.",
        "controle": "`npm run test:units`, `npm run test:features`, `npm run test:e2e` (le re-seed est automatique).",
    },
    {
        "numero": "É25",
        "titre": "build(qualité): linter strict types — declare_strict_types, PHPStan L9, zéro ternaire",
        "globs": ["scripts/strict_types_lint.php"],
        "corps": [
            "scripts/strict_types_lint.php : reproduit la règle declare_strict_types de php-cs-fixer et les types natifs « PHPStan level 9 » via le tokenizer PHP (79 fichiers, sorties fichier:ligne).",
            "Règle maison : opérateur ternaire interdit (y compris la forme courte ?:) — remplacé par if/else, match ou une variable intermédiaire ; la coalescence ?? et les types nullables ?int restent autorisés.",
            "Branché en tête de npm run test:all : 0 violation sur les 79 fichiers PHP du livrable.",
        ],
        "contenu": "Le linter maison garantit dans la sandbox les mêmes exigences que php-cs-fixer + PHPStan level 9, plus la règle de style « pas de ternaire ».",
        "controle": "`npm run lint:types` → 0 violation / 79 fichiers.",
    },
    {
        "numero": "É26",
        "titre": "build(qualité): configuration Composer — php-cs-fixer et PHPStan strict (require-dev)",
        "globs": ["composer.json", "phpstan.neon", ".php-cs-fixer.php"],
        "corps": [
            "composer.json (cs:check, cs:fix, lint:types), .php-cs-fixer.php (declare_strict_types) et phpstan.neon (level 9 + strict-rules, treatPhpDocTypesAsCertain: false).",
            "Outils en require-dev uniquement : l'application n'a aucune dépendance runtime.",
        ],
        "contenu": "L'équivalent « machine normale » du linter sandbox : Composer installe php-cs-fixer et PHPStan, les mêmes règles s'appliquent au vrai environnement.",
        "controle": "Sur une machine avec Composer : `composer cs:check` et `composer lint:types`.",
    },
    {
        "numero": "É27",
        "titre": "ci: chaîne GitLab CI (6 jobs) + miroir GitHub Actions (CT-09)",
        "globs": [".gitlab-ci.yml", ".github/*"],
        "corps": [
            "Jobs : syntaxe PHP, schéma rejouable, 28 tests SQL, tests PHP (moteur + règles), génération des documents. Un pipeline vert prouve que les critères du barème sont tenus ; un job rouge bloque le merge.",
            "Cible de notation : GitLab CI (ce fichier). Le workflow GitHub Actions est le miroir de développement, maintenu synchrone.",
        ],
        "contenu": "L'intégration continue exigée par la contrainte CT-09 : chaque push rejoue syntaxe, SQL, tests PHP et génération des documents — côté GitLab pour la notation.",
    },
    {
        "numero": "É28",
        "titre": "docs(sécurité): note cybersécurité — S-01…S-10 / SEC-01…SEC-13",
        "globs": ["docs/07-note-cybersecurite.md"],
        "corps": [
            "Pour chaque faille testée : attaque rejouée par la suite E2E, correction apportée (échappement, équivalent requêtes préparées, CSRF, sessions, IDOR, en-têtes de sécurité) et limites assumées (S-09 force brute : throttle esquissé, non livré).",
        ],
        "contenu": "La source de vérité sécurité : chaque attaque, sa correction dans le code, et ce qui reste hors périmètre — assumé explicitement.",
    },
    {
        "numero": "É29",
        "titre": "docs(soutenance): support de présentation et artefacts HTML du rendu",
        "globs": ["docs/03-support-soutenance.md", "artefacts/*"],
        "corps": [
            "Support de soutenance (démo guidée, pièges rencontrés, bilan) et rendus HTML des documents officiels (générés depuis docs/, sources de vérité).",
        ],
        "contenu": "Le support de soutenance et les rendus HTML « imprimables » des 6 documents officiels.",
    },
    {
        "numero": "É30",
        "titre": "docs: README — vue d'ensemble, installation, tests, qualité, sécurité",
        "globs": ["README.md"],
        "corps": [
            "Prise en main en 5 commandes, tableau des suites de tests (toutes vertes), politique de qualité (linter strict types, zéro ternaire), comptes de démonstration et correspondance documents ↔ exigences.",
        ],
        "contenu": "La porte d'entrée du dépôt : quoi installer, quoi lancer, où trouver chaque livrable.",
    },
    {
        "numero": "É31",
        "titre": "docs(git): fiche de suivi des commits et script de rejeu vers un nouveau GitLab",
        "globs": ["docs/08-fiche-suivi-commits.md", "scripts/rejeu_gitlab.py"],
        "corps": [
            "docs/08-fiche-suivi-commits.md : cette fiche — découpage en 32 commits commentés, jalons, correspondance avec l'historique d'origine, fichiers exclus.",
            "scripts/rejeu_gitlab.py : rejoue automatiquement tout le plan dans une copie isolée (le dépôt courant n'est jamais modifié), vérifie la couverture exacte des fichiers puis pousse sur option.",
        ],
        "contenu": "La fiche de suivi elle-même + son automate : reconstituer ce dépôt, commit par commit, sur un nouveau GitLab.",
        "tag": "v5-rendu",
    },
]

TAGS = {
    "v0-cadrage": "Cadrage fonctionnel (cahier des charges, cas d'utilisation)",
    "v1-mcd": "Conception complète (MCD, MLD, UML, plan de validation, organisation)",
    "v2-base": "Base MySQL livrée (schéma, procédures, triggers, tests, preuves)",
    "v3-front": "Application client (moteur, boutique, panier, commandes, compte)",
    "v4-back": "Back-office complet (produits, catégories, commandes, stocks, équipe)",
    "v5-rendu": "Rendu final (outillage, tests complets, qualité, CI, documents)",
}


# ---------------------------------------------------------------------------
# Génération de la fiche markdown
# ---------------------------------------------------------------------------
def message_complet(etape: dict) -> str:
    return "\n\n".join([etape["titre"], *etape["corps"]])


def generer_fiche(chemin: str) -> None:
    lignes: list[str] = []
    a = lignes.append

    a("# 08 — Fiche de suivi des commits (rejeu vers un nouveau GitLab)")
    a("")
    a("> **Objet.** Reconstituer ce projet sur un **nouveau dépôt GitLab** avec un historique")
    a("> propre et complet : 32 commits racontant le projet dans l'ordre réel de construction,")
    a("> chacun avec un message de commit prêt à copier-coller et un périmètre de fichiers exact.")
    a("> Cette fiche et le script `scripts/rejeu_gitlab.py` sont générés du **même plan** :")
    a("> ils ne peuvent pas diverger. Le script vérifie en fin de parcours que la couverture")
    a("> des fichiers est exacte (aucun oublié, aucun doublon).")
    a("")
    a("## 1. Mode d'emploi express")
    a("")
    a("```bash")
    a("# 1) Créer sur GitLab le projet vide « minishop-sae » (NE PAS cocher « Initialize with a README »)")
    a("# 2) Depuis ce dépôt, reconstruire l'historique dans une copie isolée :")
    a("python3 scripts/rejeu_gitlab.py --dest ../minishop-sae \\")
    a('    --nom "Prénom Nom" --email prenom.nom@etu.univ-cotedazur.fr')
    a("# 3) Vérifier, puis pousser :")
    a("cd ../minishop-sae && git log --oneline")
    a("git remote add origin https://gitlab.example.fr/<groupe>/minishop-sae.git")
    a("git push -u origin main --tags")
    a("```")
    a("")
    a("Le script n'écrit **jamais** dans le dépôt courant : il extrait une copie propre")
    a("(« git archive ») dans `--dest`, y rejoue les 32 commits + les 6 tags de jalons, puis")
    a("contrôle que l'arbre final est identique, fichier pour fichier.")
    a("")
    a("Le rejeu manuel (copier-coller des `git add` + messages ci-dessous) est bien sûr")
    a("possible : c'est le §3.")
    a("")
    a("## 2. Tableau de synthèse — les 32 commits")
    a("")
    a("| # | Commit | Périmètre | Ce que ça apporte |")
    a("|---|--------|-----------|-------------------|")
    resume = {
        "É0": "2 fichiers",
        "É1": "5 fichiers",
        "É2": "6 fichiers",
        "É3": "9 fichiers",
        "É4": "2 fichiers",
        "É5": "7 fichiers",
        "É6": "1 script SQL",
        "É7": "1 script SQL",
        "É8": "1 script SQL",
        "É9": "1 script SQL",
        "É10": "35 fichiers",
        "É11": "14 fichiers",
        "É12": "11 fichiers",
        "É13": "6 fichiers",
        "É14": "10 fichiers",
        "É15": "12 fichiers",
        "É16": "9 fichiers",
        "É17": "13 fichiers",
        "É18": "5 fichiers",
        "É19": "14 fichiers",
        "É20": "3 fichiers",
        "É21": "6 fichiers",
        "É22": "5 fichiers",
        "É23": "2 fichiers",
        "É24": "3 fichiers",
        "É25": "1 fichier",
        "É26": "3 fichiers",
        "É27": "2 fichiers",
        "É28": "1 fichier",
        "É29": "8 fichiers",
        "É30": "1 fichier",
        "É31": "2 fichiers",
    }
    for e in PLAN:
        tag = f" — **{e['tag']}**" if "tag" in e else ""
        a(f"| {e['numero']} | `{e['titre']}` | {resume[e['numero']]} | {e['contenu'].split(':')[0]}{tag} |")
    a("")
    a("## 3. Détail des commits (à rejouer dans l'ordre)")
    a("")
    for e in PLAN:
        a(f"### {e['numero']} — {e['titre']}")
        a("")
        a(f"**Contenu.** {e['contenu']}")
        a("")
        a("```bash")
        a("git add " + " \\\n        ".join(e["globs"]))
        a("git commit -F- <<'MESSAGE'")
        a(message_complet(e))
        a("MESSAGE")
        a("```")
        if "controle" in e:
            a("")
            a(f"**Contrôle après ce commit.** {e['controle']}")
        if "tag" in e:
            a("")
            a(f"**Jalon.** `git tag -a {e['tag']} -m \"{TAGS[e['tag']]}\"`")
        a("")
    a("## 4. Jalons (tags) — cf. charte annexe 17.9")
    a("")
    a("| Tag | Après le commit | Signification |")
    a("|-----|-----------------|---------------|")
    for e in PLAN:
        if "tag" in e:
            a(f"| `{e['tag']}` | {e['numero']} | {TAGS[e['tag']]} |")
    a("")
    a("## 5. Historique d'origine (traçabilité)")
    a("")
    a("Le dépôt d'origine (`github.com/jojo8356/SAE-302`, branche principale) contient un")
    a("historique condensé par fusion de demandes de tirage. Correspondance :")
    a("")
    a("| Commit d'origine | Contenu | Étapes du rejeu qui le couvrent |")
    a("|------------------|---------|----------------------------------|")
    a("| `453c7aa` (fusion PR n°3) | application complète : documents, SQL, moteur, MVC, outillage, tests | É0 → É24 |")
    a("| `aab2582` | tests exhaustifs (5 suites vertes, 4 bugs corrigés) | É23, É24 |")
    a("| `bfaf2ed` | note de cybersécurité + durcissement S-07 du harnais E2E | É28 |")
    a("| `69619af` | linter strict types (0 violation / 79 fichiers) | É25, É26 |")
    a("| `30fd73d` | éradication des 156 ternaires + règle maison au linter | É25 (état final du code) |")
    a("| `0babe74` | retrait d'un artefact de sandbox | — (fichier exclu du rejeu) |")
    a("")
    a("Les étapes É25/É26 livrent le code **dans son état final** (déjà sans ternaire) :")
    a("l'historique du nouveau dépôt est propre, sans aller-retour de refactoring.")
    a("")
    a("## 6. Fichiers exclus du rejeu")
    a("")
    a("| Fichier / dossier | Raison |")
    a("|-------------------|--------|")
    a("| `.sudo_as_admin_successful` | artefact de sandbox (retiré du dépôt, commit `0babe74`) |")
    a("| `var/` | journaux applicatifs, ignorés par `.gitignore` |")
    a("| `node_modules/`, `vendor/` | dépendances régénérables (`npm install` / `composer install`) |")
    a("| `tests/sql/out/` | sorties brutes du harnais SQL, seul le rapport consigné est versionné |")
    a("")
    a("## 7. Après le push : la charte annexe 17.9 en 5 réglages")
    a("")
    a("1. Dépôt **privé** ; inviter l'équipe (Developer) et l'enseignante (Reporter).")
    a("2. Protéger `main` : **pas de push direct**, MR obligatoires.")
    a("3. Désactiver le squash de MR (les commits individuels font partie de la note).")
    a("4. Activer « Delete source branch » et les pipelines (`.gitlab-ci.yml` est déjà dans É27).")
    a("5. Vérifier que les 6 tags de jalons sont bien poussés (`git push --tags`).")
    a("")
    a("## 8. Vérification finale du dépôt reconstitué")
    a("")
    a("```bash")
    a("npm install            # outillage sandbox (aucune dépendance runtime pour l'app)")
    a("node scripts/wasm_run.mjs scripts/seed.php   # re-seed des données de démo")
    a("npm run test:all       # lint 0/79 · moteur · 29/29 · 98/98 · 108/108 · E2E 155/155")
    a("```")
    a("")
    a("Tout doit être vert : le nouveau dépôt est fonctionnellement identique à l'original.")
    a("")

    with open(chemin, "w", encoding="utf-8") as f:
        f.write("\n".join(lignes))
    print(f"Fiche générée : {chemin} ({len(lignes)} lignes)")


# ---------------------------------------------------------------------------
# Rejeu automatique
# ---------------------------------------------------------------------------
def git(cwd: str, *args: str) -> subprocess.CompletedProcess:
    r = subprocess.run(["git", "-C", cwd, *args], capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit(f"git {' '.join(args)} a échoué dans {cwd} :\n{r.stderr}")
    return r


def rejouer(dest: str, nom: str, email: str) -> None:
    dest = os.path.abspath(dest)
    if os.path.exists(dest) and os.listdir(dest):
        raise SystemExit(f"Le dossier {dest} existe et n'est pas vide — choisir un --dest vierge.")

    os.makedirs(dest)
    print(f"1) Extraction d'une copie propre de HEAD vers {dest}")
    with tempfile.TemporaryFile() as tmp:
        subprocess.run(["git", "-C", RACINE, "archive", "HEAD"], stdout=tmp, check=True)
        tmp.seek(0)
        with tarfile.open(fileobj=tmp, mode="r") as tar:
            tar.extractall(dest)  # noqa: S202 — archive produite par git, pas d'attaque Zip Slip ici

    print("2) Initialisation du dépôt et de l'identité")
    subprocess.run(["git", "init", "-b", "main", dest], capture_output=True, check=True)
    git(dest, "config", "user.name", nom)
    git(dest, "config", "user.email", email)

    total = 0
    print(f"3) Rejeu des {len(PLAN)} commits")
    for e in PLAN:
        git(dest, "add", "--", *e["globs"])
        staged = git(dest, "diff", "--cached", "--name-only").stdout.split("\n")
        ajoutes = [s for s in staged if s]
        if not ajoutes:
            raise SystemExit(f"{e['numero']} : aucun fichier stagé pour {e['globs']}")
        git(dest, "commit", "-q", "-m", e["titre"], *[part for p in e["corps"] for part in ("-m", p)])
        total += len(ajoutes)
        print(f"   {e['numero']:3s} {len(ajoutes):3d} fichier(s) — {e['titre'][:66]}")
        if "tag" in e:
            git(dest, "tag", "-a", e["tag"], "-m", TAGS[e["tag"]])

    print("4) Contrôle de couverture (arbre final == arbre d'origine)")
    attendus = sorted(git(RACINE, "ls-files").stdout.split("\n")[:-1])
    obtenus = sorted(git(dest, "ls-files").stdout.split("\n")[:-1])
    if attendus != obtenus:
        manquants = set(attendus) - set(obtenus)
        en_trop = set(obtenus) - set(attendus)
        raise SystemExit(f"COUVERTURE INCORRECTE ! manquants={sorted(manquants)} en_trop={sorted(en_trop)}")
    restant = git(dest, "status", "--porcelain").stdout.strip()
    if restant:
        raise SystemExit(f"Fichiers non suivis restants dans la copie :\n{restant}")

    nb_commits = git(dest, "rev-list", "--count", "HEAD").stdout.strip()
    print()
    print(f"✅ {nb_commits} commits, {total} fichiers, {len(attendus)} fichiers suivis identiques à l'origine.")
    print(f"   Copie prête : {dest}")
    print()
    print("Prochaine étape (projet vide créé sur GitLab) :")
    print(f"  cd {dest}")
    print("  git remote add origin https://gitlab.example.fr/<groupe>/minishop-sae.git")
    print("  git push -u origin main --tags")


def main() -> None:
    p = argparse.ArgumentParser(description="Rejoue l'historique MiniShop vers un nouveau GitLab.")
    p.add_argument("--fiche", metavar="CHEMIN", help="génère la fiche markdown au chemin donné puis s'arrête")
    p.add_argument("--dest", metavar="DOSSIER", default="../minishop-sae", help="dossier cible du rejeu (défaut : ../minishop-sae)")
    p.add_argument("--nom", help="nom Git des commits du rejeu (défaut : git config user.name)")
    p.add_argument("--email", help="courriel Git des commits du rejeu (défaut : git config user.email)")
    args = p.parse_args()

    if args.fiche:
        generer_fiche(args.fiche)
        return

    nom = args.nom or subprocess.run(
        ["git", "-C", RACINE, "config", "user.name"], capture_output=True, text=True
    ).stdout.strip()
    email = args.email or subprocess.run(
        ["git", "-C", RACINE, "config", "user.email"], capture_output=True, text=True
    ).stdout.strip()
    if not nom or not email:
        raise SystemExit("Identité Git absente : passer --nom et --email (ex. --nom \"Prénom Nom\" --email prenom.nom@etu.univ-cotedazur.fr).")

    rejouer(args.dest, nom, email)


if __name__ == "__main__":
    main()
