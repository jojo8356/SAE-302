# 11 — Stratégie de tests

Ce document ne remplace pas le [document de tests et de validation](02-document-tests-validation.md) (cas détaillés, registre d'anomalies, PV de recette) : il explique **comment** la vérification est construite, **pourquoi** à ce niveau-là, et **ce qu'elle ne prouve pas**. C'est le document qu'on lit quand on se demande « vos tests, ils servent à quoi ? ».

> **Portée des preuves — coupure documentaire 23/10/2026.** Les volumes et résultats d’archive sans run rattaché ne valent pas preuve sur le working tree examiné. Le 23/10, `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` a terminé avec succès via PHP-WASM 8.2 : les suites activées hors SQL/E2E sont vertes (notamment T-01…T-29 en JSON, 297 vérifications unitaires et 57 tests PHPUnit). Cette commande exclut explicitement l’intégration SQL et l’E2E ; l’analyse de compilation avec PHP CLI natif est également non jouée. PHP CLI, Composer, MySQL/MariaDB et navigateur réel n’étaient pas disponibles ; aucune validation PDO/MySQL, recette manuelle ou mesure cible n’est revendiquée.

## 1. Le principe : la pyramide des tests et son protocole

La pyramide des tests décrit les niveaux et les protocoles visés. Les volumes ci-dessous sont des chiffres de couverture décrite, pas tous des résultats d’exécution. Le résultat du run restreint `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` du 23/10 est consigné en §4 et dans `docs/19` ; il n’inclut ni intégration SQL ni E2E.

| Étage | Nature | Suite | Volume | Durée |
|---|---|---|---|---|
| 0 | Analyse statique & audits documentaires | `scripts/strict_types_lint.php` + `tests/security/controles.sh` (11 contrôles statiques inscrits) + `scripts/verifier_fiches_uc.py` + `tests/python/test_audit_structurel.py` + `scripts/wasm_verdict.mjs` | Inventaire de contrôles ; les résultats exécutés pour cette révision sont consignés séparément dans `docs/19`, sans en déduire le passage d’une suite globale | non mesurée ici |
| 1 | Unitaires purs | `tests/php/unit_tests.php` + `tests/phpunit/**` (classes de tests versionnées) | Suites définies dans le dépôt ; aucun résultat PHP natif n’est revendiqué dans cette revue | non mesurée ici |
| 2 | Contrats statiques & métier ciblés | `contract_http.php`, `css_contract.php`, `responsive_contract.php` + suites spécialisées `tests/php/*_test.php` | Protocoles présents ; l’exécution des suites PHP exige un run natif daté et rattaché au SHA | non mesurée ici |
| 3 | Règles de gestion sur le moteur | `tests/php/run_tests.php` (`T-01` → `T-29` du CDC §6), `tests/php/engine_smoke.php`, `tests/php/json_concurrency_stress.php` | 29 scénarios référencés ; leur résultat doit être établi par un rejeu daté sur le SHA concerné | non mesurée ici |
| 4 | Fonctionnalités via dépôts & fixtures isolées | `tests/php/features_test.php` (UC-01 → UC-14), `catalogue_stock_test.php`, `nouveautes_test.php` (fixtures prévues en magasins temporaires) | Périmètre de scénarios annoncé dans cette archive ; les nombres de cas ne sont pas des résultats de passage | non mesurée ici |
| 5 | Intégration SQL réelle & migrations | `tests/php/sql_integration.php` (`tests/sql/run_tests.sh` et assertions PDO) + `tests/php/migration_mariadb_test.php` | Scénarios et migrations à exécuter sur une base temporaire ; aucun rejeu SQL/MySQL/MariaDB n’est revendiqué pour cette révision | non mesurée ici |
| 6 | Parcours HTTP E2E, DOM bilingue FR↔EN & CLI Python | `scripts/wasm-e2e.mjs` + suites `scripts/ui-e2e-*.mjs` + `tests/node/` + `tests/python/` | Scénarios et fichiers de test présents ; les volumes de l’archive ne sont pas des résultats de passage sur ce working tree | non mesurée ici |
| 6 bis | Navigateur réel headless (CI) | `tests/node/responsive_navigateur.mjs` (Playwright Chromium, largeurs prévues 320, 375 et 1440 px) | Protocole décrit ; aucun résultat de navigateur réel ou de CI n’est revendiqué pour cette révision | non mesurée ici |
| 7 | Performance & volumétrie | `scripts/capture_perf.sh` → `gen_volumes.sh` + `tests/perf/mesurer_sql.sh` | Protocole de mesure ; aucun p95 cible ni résultat de performance actuel n’est établi ici | non mesurée ici |
| 8 | Suites de recette manuelle humaine | [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) + [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) + [`docs/14-guide-lineserv.md`](14-guide-lineserv.md) | **164 scénarios** (`UC-01..14` + `TCX-01..24`, hors responsive multi-largeurs) + **36 couples** = **200 lignes `NON JOUÉ`** | campagne humaine |

**Total** : les volumes détaillés figurent ci-dessus ; les scénarios catalogue varient avec le nombre de produits. La matrice catalogue MariaDB reste à jouer.

Pourquoi cette forme, et pas 400 tests unitaires avec des doublures partout : l'essentiel du sujet est une **base de données** (8 entités métier, 9 tables SQL avec la table technique d’audit, 18 procédures, 16 déclencheurs, 1 fonction, 3 vues). Un test unitaire avec un `PDO` simulé prouverait seulement que le PHP appelle une chaîne de caractères. La stratégie vise donc à contrôler les règles là où elles vivent : étage 3 sur le moteur, étage 5 sur MariaDB. La présence de ces étages dans le plan ne prouve pas leur exécution ni la conformité du parcours applicatif.

## 2. Les trois natures de test, et ce qu'elles distinguent

### 2.1 Tests de règles (étage 3) — le moteur, sans HTTP

`tests/php/run_tests.php` est conçu pour exécuter les 29 scénarios `T-01` → `T-29` du cahier des charges sur `app/Model/Data/JsonStore.php`, qui réimplémente contraintes, déclencheurs et transactions. L'objectif est de contrôler les règles indépendamment des contrôleurs et des sessions ; la présence du harnais ne démontre pas qu'elles ont été rejouées ou qu'elles tiennent dans le checkout examiné.

`tests/php/engine_smoke.php` vise à contrôler les hypothèses de cette réimplémentation : API des pilotes (`JSON` et `SQL`), absence de connexion sans PDO, absence de concaténation SQL et comportement de `LIKE`. Ces contrôles exigent un résultat de run daté et rattaché au SHA avant d'être présentés comme réussis.

### 2.2 Intégration SQL (étage 5) — protocole à rejouer pour établir le résultat

`tests/php/sql_integration.php` est conçu comme une **enveloppe mince** autour de `tests/sql/run_tests.sh` : le protocole prévoit de créer une base temporaire dédiée `minishop_test`, d’y charger `sql/01` → `sql/04`, de contrôler le repository sur `SqlStore` et la procédure de recherche, de jouer les 29 scénarios, puis de supprimer la base. La commande passée le 23/10 comportait `--sans-sql` : cette enveloppe et ces scripts n’ont donc pas été exécutés et aucun rejeu PDO/MySQL de cette révision n’est revendiqué.

Depuis l’audit global, cette enveloppe refuse par défaut une base existante et refuse toujours le nom `DB_NAME` configuré. `--reinitialiser-base-existante` autorise explicitement la suppression : serveur de test jetable uniquement. La protection du nom ne détecte pas les bases utilisées par d’autres applications. Le harnais shell appelé directement ne bénéficie pas de ces garde-fous.

Le mot « mince » est important : réécrire les 29 cas en PHP aurait produit deux jeux de tests qui divergent au premier changement de schéma. Le harnais shell est la référence ; le PHP n'ajoute que l'isolation.

L'isolation est le point délicat : `tests/sql/fixture.sql` réinitialise l'état **avant chaque scénario** — lancée sur la base de développement, la campagne effacerait les commandes de démonstration. L'enveloppe refuse le nom de base applicative configuré ; cela ne protège pas les bases des autres applications. `--garder` permet d'inspecter le résultat.

Une note d’archive rapporte un refus `1044 Access denied` lors de la création de `minishop_test` par un compte applicatif limité à `minishop.*`. Ce récit n’est pas rattaché ici à un run daté/SHA et n’est pas une preuve de l’environnement actuel. Le protocole décrit une création explicite par `php scripts/db_init.php --test-db`, sans élargir les droits sur la base applicative ; à vérifier sur serveur jetable avant toute réinitialisation.

Comportement attendu : sans client `mariadb`/`mysql` dans le `PATH`, ou sans serveur joignable, le harnais doit annoncer **NON JOUÉ** et sortir en code 2. Le run `--sans-sql` du 23/10 a délibérément sauté cette enveloppe ; il ne vérifie donc pas ce comportement ni ne vaut intégration SQL.

### 2.3 Tests de bout en bout (étage 6) — ce que l'utilisateur voit

`scripts/e2e.mjs` décrit un parcours WASM où les requêtes passent par le front controller dans PHP-WASM, sans navigateur ni serveur HTTP réel. Il vise à contrôler codes HTTP, contenu, jetons CSRF et persistance JSON ; le volume de 268 vérifications est une mention de l’archive, pas un résultat revendiqué pour le working tree examiné.

Le protocole vise à isoler les données dans un système de fichiers PHP-WASM temporaire ; `tests/node/e2e_isolation.mjs` est prévu pour contrôler, sur copie temporaire, que des fichiers hôte factices restent inchangés. La présence de ces tests ne vaut pas rejeu.

## 3. Les tests de contrat — la partie qui attrape ce que les autres laissent passer

Trois suites sont prévues pour analyser des artefacts **sans exécuter l'application**. Elles visent une catégorie de défaut que ni les tests unitaires ni l'E2E ne ciblent directement : la **cohérence entre deux artefacts écrits séparément**. Leur présence ne signifie pas qu'elles ont été rejouées.

| Suite | Ce qu'elle vise à contrôler | Exemple de défaut recherché |
|---|---|---|
| `contract_http.php` (27 assertions décrites) | vise à contrôler les liens/actions vers des routes déclarées, les gardes `/admin/*`, l’absence d’écriture en `GET` et les en-têtes | un `<a href="/commande/suivi">` après renommage de route ; une route admin sans `exigerAdmin()` |
| `css_contract.php` (69 assertions décrites) | vise à contrôler les jetons du design system, les contrastes AA et les sources de la CSP | une couleur écrite en dur qui contourne le jeton ; un `style=` inline bloqué par la CSP |
| `responsive_contract.php` (50 assertions décrites) | vise à contrôler la mise en page de 320 px à 1536 px selon WCAG 1.4.10 : largeur, tableaux défilants, cibles tactiles | une grille `min-width: 900px` qui provoque un défilement horizontal sur mobile |

Ces tests sont **statiques** : ils analysent les fichiers et ne remplacent pas une validation de navigateur. Un scénario Chromium headless (`tests/node/responsive_navigateur.mjs`, largeurs 320, 375 et 1440 px) est décrit séparément ; aucune exécution CI ou validation multi-largeurs n’est revendiquée ici.

## 4. Ce qui n'est pas joué en sandbox, et pourquoi c'est écrit

PHP CLI, Composer, MySQL/MariaDB et navigateur réel ne sont pas disponibles dans cette sandbox. Le runtime PHP-WASM a toutefois été fourni par les dépendances Node installées pour ce contrôle. Le 23/10, `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` s’est terminé avec le code 0 : toutes les suites activées hors SQL/E2E ont réussi via PHP-WASM 8.2. Ce résultat restreint ne constitue pas une CI native ni une validation globale des parcours SQL.

| Suite | État dans cette révision | Limite |
|---|---|---|
| Suites PHP non SQL dans `run-all-tests.mjs` | Réussies via PHP-WASM 8.2 avec `--sans-sql --sans-e2e` ; T-01…T-29 JSON conformes, 297 vérifications unitaires et 57 tests PHPUnit verts | Pas de PHP CLI natif ; l’analyse de compilation native est NON JOUÉE |
| Contrôles Python/documentaires du runner | Réussis selon les sorties de `docs/19` | Ne constituent pas une CI externe ni une recette humaine |
| `sql_integration.php` et scripts SQL | NON JOUÉ ; explicitement exclus avec `--sans-sql` | Client/serveur MySQL/MariaDB absents ; aucune base temporaire n’a été créée |
| E2E HTTP | NON JOUÉ ; explicitement exclu avec `--sans-e2e` | Pas de parcours HTTP déclaré dans ce run |
| `coverage.php` | NON JOUÉ | PHP CLI absent |
| `capture_perf.sh` et mesures de charge | NON JOUÉ | serveur et cible de mesure absents |
| Navigateur réel, lecteur d'écran | NON JOUÉ | aucune recette humaine revendiquée |

Le contrôle `node scripts/wasm_verdict.mjs` a passé 9/9 cas dans le même run. Cela vérifie les verdicts de l’adaptateur WASM ; le résultat complet demeure limité aux options choisies et ne prouve ni SQL, ni E2E, ni l’analyse avec PHP CLI natif.

## 5. La couverture, mesurée honnêtement

`scripts/coverage.php` ne mesure **pas** la couverture de lignes : elle exigerait Xdebug ou PCOV, c'est-à-dire une dépendance à installer pour noter le projet (contraire à `ENF-20`). Il mesure ce qui est vérifiable sans rien installer : **quels fichiers de `app/` sont chargés par au moins une suite**.

Un fichier jamais chargé n'est pas « mal couvert », il n'est **jamais exécuté** : aucune suite ne peut détecter sa régression. C'est le bon signal pour repérer du code mort.

```bash
php scripts/coverage.php              # tableau par suite + pourcentage
php scripts/coverage.php --liste      # fichiers jamais chargés
php scripts/coverage.php --min=80     # échoue sous 80 % (CI)
```

Ce que cette mesure ne dit pas, et qu'il faut dire à sa place : un fichier chargé n'est pas un fichier correctement exercé ; les vues (`app/View`) ne sont chargées qu'à l'affichage, donc leur couverture par les suites PHP est structurellement faible — c'est l'E2E qui les exerce, et il n'entre pas dans ce calcul.

## 6. Le verdict du harnais et ses limites

Un dispositif de test qui ne peut pas échouer ne prouve rien. Cette archive rapporte qu’un défaut du verdict PHP-WASM aurait pu traiter des sorties d’échec comme des succès ; ce récit n’est pas rattaché ici à un run daté/SHA.

Le script `scripts/wasm_verdict.mjs` décrit un classement à partir de signaux observables : erreur fatale ou de syntaxe PHP → 255 ; marqueur « ❌ » → 1 ; « NON JOUÉ » sans « ✅ » → 2 ; sinon → 0. Il a passé **9/9 cas de verdict**, et l’orchestrateur restreint `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` a terminé avec le code 0 le 23/10. Cette réussite ne couvre pas les suites SQL et E2E sautées, ni PHP CLI natif.

Les scénarios de sabotage/restauration de l’orchestrateur décrits dans l’archive restent à rattacher à un run et un SHA avant de les présenter comme résultat.

## 7. Comment on lance tout

```bash
# tout, dans l'ordre, arrêt au premier échec
node scripts/run-all-tests.mjs        # sandbox (PHP-WASM)
php scripts/run-all-tests.php         # machine avec PHP natif

# une suite
node scripts/wasm_run.mjs tests/php/run_tests.php
php tests/php/run_tests.php

# SQL et performance : uniquement sur un serveur de test jetable.
# L'intégration refuse une base déjà existante par défaut ; voir la section 2.2.
# Les outils SQL et de performance directs peuvent réinitialiser leurs cibles.
```

## 8. Auto-évaluation

**Ce que la stratégie vise à établir, sous réserve de rejeux datés.** Les scénarios `T-01`…`T-29` ciblent les règles sur le moteur ; les scénarios SQL directs visent à contrôler les contraintes sans passer par l’application. Les parcours E2E décrivent des appels HTTP en runtime WASM. Ces objectifs et scripts présents ne prouvent pas que les règles, l’intégration SQL ou les parcours HTTP ont réussi dans la révision examinée.

**Ce qui ne tient pas, et qu'on assume.**

1. **Pas de couverture de lignes.** Assumé pour ne pas ajouter de dépendance ; le substitut (fichiers chargés) est plus faible et le dit.
2. **Les tests de contrat sont statiques.** Une expression régulière sur un gabarit n'est pas un navigateur : un lien construit dynamiquement échappe au contrôle (la suite l'annonce, elle ne le juge pas).
3. **Pas d'accessibilité automatisée.** Contrastes et cibles tactiles sont calculés ; la navigation au lecteur d'écran reste manuelle.
4. **Fiabilité du verdict.** L’archive rapporte un défaut antérieur du harnais ; le scénario de sabotage de l’orchestrateur reste à relier à une exécution datée. Le contrôle courant 9/9 porte uniquement sur `wasm_verdict.mjs`.
5. **Pas de test de charge HTTP revendiqué.** `C-01` décrit un protocole de 20 réservations concurrentes ; l'application sous charge réelle n'est pas mesurée dans cette révision.
6. **La campagne SQL dépend de l'environnement.** Aucun rejeu SQL courant n’est revendiqué. Le rapport versionné porte une mention du 27/10/2026, future à la coupure du 23/10 ; elle ne constitue pas une preuve à cette date.

L’archive mentionne aussi une mesure de 17 ms pour `sp_revenue_report` (anomalie A-08). Faute de run, SHA, matériel, jeu de données et sortie rattachés ici, cette valeur reste historique et ne vaut pas mesure actuelle.


## Information juridique, mentions légales LCEN et protection des données

`tests/php/information_juridique_test.php` et `tests/php/mentions_legales_test.php` sont prévus pour contrôler les contrats statiques de navigation, contenu, cookies/stockage local, mentions légales étudiantes, information précontractuelle et design ; ces contrôles ne constituent pas un avis juridique. Le parcours `scripts/wasm-e2e.mjs` et `scripts/ui-e2e-mentions.mjs` décrivent des scénarios pour les pages d'information/légales, `/preferences`, les garanties, la rétractation, le contact, CSRF et XSS. Les volumes consignés dans d’autres versions de cette archive ne sont pas des résultats revendiqués ici ; les exécutions doivent être rattachées à un run daté et à un SHA. Détails et réserves dans `docs/12-information-juridique.md`.


## Dates UTC, fuseau horaire local et traduction intégrale FR ↔ EN

- Les suites `date_ajout_test.php`, `heure_test.php` et `ConservationEtFuseauTest.php` sont prévues pour contrôler le stockage UTC, les conversions `Europe/Paris`/IANA et les bornes des filtres d'administration ; aucune exécution PHP/SQL n’est revendiquée dans cette révision.
- `tests/node/preferences_test.mjs` décrit des cas JSDOM pour `/preferences`, le choix manuel de langue/fuseau, `localStorage`, le parcours `FR -> EN -> FR` et les erreurs métier. Les comptes de vues et d’erreurs figurant dans cette archive sont des volumes documentaires, pas des résultats actuels.
- `tests/node/responsive_navigateur.mjs` définit des contrôles Chromium headless aux largeurs **320, 375 et 1440 px**, avec et sans JavaScript. Aucun run CI ni validation par navigateur réel n’est revendiqué dans cette révision.


## Dépréciations PHP et isolation des tests

- `python3 scripts/verifier_deprecations.py` lance `php -l` sur les fichiers PHP du code applicatif, des scripts et des suites, avec toutes les erreurs affichées. Il relève aussi un `Deprecated` même si PHP rend le code 0. Syntaxe et dépréciations sont vérifiées avec **le PHP CLI réellement lancé**, pas avec une version théorique. Il n'exécute pas les fichiers ni les dépendances externes.
- Les runners des suites PHP chargent `tests/php/deprecations_bootstrap.php` : `E_DEPRECATED` et `E_USER_DEPRECATED` sont signalés même sous `@`, et un marqueur fait échouer la suite. Cela ne couvre que le code réellement exécuté et peut être contourné si ce code remplace le gestionnaire d'erreur. Les parcours E2E HTTP et les fichiers exécutés directement hors runner n'y sont pas inclus.
- Pour éviter la base et les données E2E, utiliser `php scripts/run-all-tests.php --sans-sql --sans-e2e`. Python 3 et PHP CLI 8.2+ avec `mbstring` sont requis ; ni npm ni MariaDB ne sont requis pour ce mode. Sans options, SQL crée puis supprime une base de test et refuse les bases déjà existantes par défaut ; serveur isolé obligatoire. L’E2E ne modifie plus `data/` sur l’hôte.
- En l'absence de PHP CLI natif, le runner Node/WASM annonce l'analyse de compilation NON JOUÉE. Le garde d'exécution y utilise PHP-WASM 8.2 ; cela ne démontre rien sur les dépréciations apparues dans PHP 8.4/8.5. Rejouer avec le PHP de l'hébergeur et celui du serveur web ; les tests n'analysent ni JavaScript/CSS ni bibliothèques externes.
- Les autotests couvrent notamment un faux compilateur qui renvoie 0 malgré un `Deprecated`, une erreur de syntaxe, PHP absent, un dossier vide, les liens symboliques, une dépréciation utilisateur masquée et la réécriture du schéma de démo dans une **copie temporaire**. Aucun serveur SQL n'est contacté par ces autotests.


## Code potentiellement inutilisé et inventaire applicatif

`scripts/code_mort.php` inspecte les tokens des PHP de `app/` et `public/` ; il cherche des références dans ces dossiers ainsi que dans les scripts et tests PHP. Il signale les paramètres non cités, les variables locales écrites une seule fois et les fonctions/méthodes sans autre mention textuelle. Ces signaux sont **à examiner**, pas des preuves de code mort. Les appels dynamiques, l’héritage, la réflexion, les API externes et certains modes de lecture indirecte rendent l’analyse nécessairement incomplète ; JS/CSS/SQL et variables globales ne sont pas analysés de cette façon.

La commande normale est conçue pour comparer les candidats au référentiel `scripts/code_mort_reference.json` ; `--rapport` affiche les candidats et `--json` les exporte. Les suites mentionnées dans cette archive visent des cas positifs/négatifs, appels de callback, propriétés et lectures indirectes, ainsi qu’un scénario CLI natif sur copie temporaire ; leur réussite n’est pas établie par cette description. L’inventaire `docs/16-audit-code-mort.md` documente les fichiers de `app/` et `public/`, sans garantir que leur rôle reste correct après toute modification.

## Page 403 et rôles administrateurs

Le code et les scénarios versionnés décrivent un refus HTTP 403 pour un administrateur sans rôle SUPER et une redirection pour un visiteur non connecté. Des tests HTTP/E2E et un contrôle Python sur serveur natif sont mentionnés dans l’archive, mais aucun run CI ou rejeu applicatif correspondant n’est revendiqué dans cette révision. Ces références ne remplacent pas une vérification dans un navigateur réel.

## Articulation avec les suites de tests manuels (`docs/13-*`)

Même avec **15 jobs CI automatisés**, une campagne humaine reste indispensable sur ordinateur pour valider :
1. les parcours métier complets, la navigation au clavier et les confirmations explicites en deux étapes (avec et sans JavaScript) dans un navigateur réel ;
2. l'expérience réelle de la boîte de dialogue navigateur de permission de géolocalisation sur `/preferences` et la qualité linguistique perçue des pages traduites en anglais ;
3. l'hébergement cible **LineServ** (sous-dossier éventuel, séparation des fichiers privés, absence de scripts de développement exposés) et la recette manuelle sur la base réellement utilisée par l'application de production, pendant une fenêtre pré-démo autorisée ; cette même base doit être restaurée à l'état de démonstration avant la démo. `minishop_test` reste réservée aux tests SQL automatisés de GitHub CI et n'est pas la cible manuelle.

**Périmètre de recette manuelle proposé :** les contrôles responsive multi-largeurs (`320/375/768/1440 px`, cartes mobiles `data-label`, portrait/paysage) sont décrits dans les suites automatisées `tests/php/responsive_contract.php` et `tests/node/responsive_navigateur.mjs`. Leur présence n’établit pas qu’ils ont été rejoués ; aucune validation humaine exhaustive sur téléphones physiques n’est revendiquée.

L'ensemble des vérifications humaines retenues est formalisé dans [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) (**164 scénarios** sur `UC-01` à `UC-14` et `TCX-01` à `TCX-24`, plus les **36 couples** de la matrice `RB-11`) et son journal [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) (**200 lignes** initialisées à `NON JOUÉ`). L'intégrité structurelle et la synchronisation de ces deux fichiers avec le code (`Triggers::TRANSITIONS`, routes, fichiers cités et absence de retour des scénarios responsive manuels) sont vérifiées automatiquement par `python3 scripts/verifier_fiches_uc.py` dans les orchestrateurs de tests et en CI.

