# 11 — Stratégie de tests

Ce document ne remplace pas le [document de tests et de validation](02-document-tests-validation.md) (cas détaillés, registre d'anomalies, PV de recette) : il explique **comment** la vérification est construite, **pourquoi** à ce niveau-là, et **ce qu'elle ne prouve pas**. C'est le document qu'on lit quand on se demande « vos tests, ils servent à quoi ? ».

> **Portée historique — coupure documentaire 23/10/2026.** Le compte rendu historique du 23/10 (`node scripts/run-all-tests.mjs --sans-sql --sans-e2e`) excluait explicitement SQL et E2E et ne comportait pas de SHA ; il ne constitue pas une preuve sur l’état courant.
>
> **État validé — campagne complète sur VM Debian 13 des 16–17/11/2026** (branche `arena/b5a4074b-sae-302`, commits `f9b614f` et `cb0f814`). Sur VM native (**PHP 8.4.27 cli**, **MariaDB 11.8.6**) :
> - `php scripts/generer_pv.php` (`f9b614f`, `docs/pv-recette.md`) a terminé avec le code **0**, **60/60 étapes vertes** et **0 warning PHP**, incluant l’intégration SQL (`sql_integration.php`, **29/29**), le parcours HTTP complet sous SQL (`http_sql_e2e.php`, **105/105**), la concurrence HTTP 20/12 (`concurrence_http.php`, **8/8**), l’E2E JSON isolé (`338/338`), `preferences_test.mjs` (`48` vues FR/EN), PHPUnit (`57/57`), le linter strict (`171` fichiers, `0` violation) et les contrôles statiques de sécurité (`11/11`).
> - `bash scripts/run_perf_vm.sh` (`cb0f814`, `tests/perf/dernieres_mesures.txt`) sur la base isolée `minishop_perf` (`236` produits, `1 004` commandes) a mesuré : SQL (100 it.) **p95 ≤ 20,6 ms**, écriture métier `sp_add_order_line` **44 ms**, et HTTP (200 it. × 6 parcours) **100 % HTTP 200**, **0 erreur**, **p95 global = 6 ms** (`ENF-01 < 500 ms` conforme).
> - Les **200 entrées manuelles** de `docs/13-suivi-recette-uc.csv` restent distinctement **`NON JOUÉ`**, et aucun test manuel sur LineServ, déploiement ni validation pédagogique n’est revendiqué.

## 1. Le principe : la pyramide des tests et son protocole

La pyramide des tests décrit les niveaux et les protocoles du projet. Les résultats complets sur VM Debian 13 (`f9b614f` / `cb0f814`) sont détaillés en §4, dans `docs/02`, `docs/19`, `docs/pv-recette.md` et `tests/perf/dernieres_mesures.txt`.

| Étage | Nature | Suite | Volume / état vérifié (`17/11/2026`) | Limites maintenues |
|---|---|---|---|---|
| 0 | Analyse statique & audits documentaires | `scripts/strict_types_lint.php` + `tests/security/controles.sh` + `scripts/verifier_fiches_uc.py` + `tests/python/` + `scripts/wasm_verdict.mjs` | Linter **171** fichiers (`0` violation), sécurité **11/11**, fiches UC **46/46**, `wasm_verdict` **9/9**, Python **42** tests (**22 OK / 20 skipped** sans PHP natif) | Ne remplacent pas une recette humaine |
| 1 | Unitaires purs | `tests/php/unit_tests.php` + `tests/phpunit/**` | **297 vérifications** unitaires + **57/57 tests PHPUnit** verts sur VM (`f9b614f`) | Exécution isolée sur fixtures |
| 2 | Contrats statiques & métier ciblés | `contract_http.php`, `css_contract.php`, `responsive_contract.php` + suites spécialisées `tests/php/*_test.php` | **27** HTTP + **69** CSS + **50** responsive + suites métier verts sur VM (`60/60`, `f9b614f`) | Contrats statiques et PHP, hors navigateur réel |
| 3 | Règles de gestion sur le moteur | `tests/php/run_tests.php` (`T-01` → `T-29` du CDC §6), `tests/php/engine_smoke.php`, `tests/php/json_concurrency_stress.php` | **29/29** scénarios sur `JsonStore` + stress concurrence verts (`60/60`, `f9b614f`) | Pilote JSON ; l’équivalent SQL est vérifié à l’étage 5 |
| 4 | Fonctionnalités via dépôts & fixtures isolées | `tests/php/features_test.php` (UC-01 → UC-14), `catalogue_stock_test.php`, `nouveautes_test.php` | Verts sur VM (`60/60`, `f9b614f`) ; `2 550` scénarios catalogue | Magasins temporaires isolés |
| 5 | Intégration SQL réelle & migrations | `tests/php/sql_integration.php` (`tests/sql/run_tests.sh` et assertions PDO) + `tests/php/migration_mariadb_test.php` | Validée sur VM Debian 13 (`f9b614f`, `docs/pv-recette.md`) : SQL **29/29** et migrations MariaDB **vertes** sur base temporaire isolée | validée sur base isolée (`minishop_test`), distincte de LineServ |
| 6 | Parcours HTTP E2E, HTTP SQL, DOM FR↔EN & CLI Python | `http_sql_e2e.php` + `concurrence_http.php` + `scripts/wasm-e2e.mjs` + `tests/node/` + `tests/python/` | Run VM `17/11` (`f9b614f`) : `http_sql_e2e` **105/105**, `concurrence_http` **8/8**, E2E JSON **338/338**, `preferences_test` **48 vues FR/EN**, Python **42 tests** | validé automatiquement ; navigateur humain et LineServ non joués |
| 6 bis | Navigateur réel headless (CI) | `tests/node/responsive_navigateur.mjs` (Playwright Chromium, largeurs prévues 320, 375 et 1440 px) | Protocole et contrat statique (`responsive_contract.php`) ; exécution Playwright conditionnée au binaire Chromium (absent de cette VM sans `node_modules`) | contrat CSS/HTML vérifié ; Chromium réel à vérifier en CI |
| 7 | Performance & volumétrie | `scripts/run_perf_vm.sh` → `scripts/gen_volumes_cli.php` + `tests/perf/{mesurer_sql,mesurer}.sh` | Validée sur VM Debian 13 (`cb0f814`, `minishop_perf` : 236 produits, 1 004 commandes, `tests/perf/dernieres_mesures.txt`) : SQL **p95 ≤ 20,6 ms**, HTTP **p95 = 6 ms**, **0 erreur** | validée sur VM locale (`ENF-01 < 500 ms`) ; `P-03` HTTP non bouclé séparément |
| 8 | Suites de recette manuelle humaine | [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) + [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) + [`docs/14-guide-lineserv.md`](14-guide-lineserv.md) | **164 scénarios** (`UC-01..14` + `TCX-01..24`, hors responsive multi-largeurs) + **36 couples** = **200 lignes `NON JOUÉ`** | campagne humaine |

**Total** : les volumes détaillés figurent ci-dessus ; les scénarios catalogue varient avec le nombre de produits. La matrice catalogue MariaDB reste à jouer.

Pourquoi cette forme, et pas 400 tests unitaires avec des doublures partout : l'essentiel du sujet est une **base de données** (8 entités métier, 9 tables SQL avec la table technique d’audit, 18 procédures, 16 déclencheurs, 1 fonction, 3 vues). Un test unitaire avec un `PDO` simulé prouverait seulement que le PHP appelle une chaîne de caractères. La stratégie vise donc à contrôler les règles là où elles vivent : étage 3 sur le moteur, étage 5 sur MariaDB. La présence de ces étages dans le plan ne prouve pas leur exécution ni la conformité du parcours applicatif.

## 2. Les trois natures de test, et ce qu'elles distinguent

### 2.1 Tests de règles (étage 3) — le moteur, sans HTTP

`tests/php/run_tests.php` exécute les 29 scénarios `T-01` → `T-29` du cahier des charges sur `app/Model/Data/JsonStore.php`, qui réimplémente contraintes, déclencheurs et transactions (**29/29** dans les runs PHP-WASM et VM). Le comportement réel sur le pilote SQL/MySQL/PDO par défaut est validé séparément par l’étage 5 (`sql_integration.php`, **29/29** sous MariaDB 11.8.6).

`tests/php/engine_smoke.php` vise à contrôler les hypothèses de cette réimplémentation : API des pilotes (`JSON` et `SQL`), absence de connexion sans PDO, absence de concaténation SQL et comportement de `LIKE`. Ces contrôles exigent un résultat de run daté et rattaché au SHA avant d'être présentés comme réussis.

### 2.2 Intégration SQL (étage 5) — protocole à rejouer pour établir le résultat

`tests/php/sql_integration.php` est conçu comme une **enveloppe mince** autour de `tests/sql/run_tests.sh` : le protocole crée une base temporaire dédiée `minishop_test`, y charge `sql/01` → `sql/04`, contrôle le repository sur `SqlStore` et la procédure de recherche, joue les 29 scénarios, puis supprime la base. Lors de la campagne complète sur VM Debian 13 du **17/11/2026** (`f9b614f`, `docs/pv-recette.md`), `sql_integration.php` (**29/29**), `http_sql_e2e.php` (**105/105**) et `concurrence_http.php` (**8/8**) ont été exécutés avec le code de sortie **0**.

Depuis l’audit global, cette enveloppe refuse par défaut une base existante et refuse toujours le nom `DB_NAME` configuré. `--reinitialiser-base-existante` autorise explicitement la suppression : serveur de test jetable uniquement. La protection du nom ne détecte pas les bases utilisées par d’autres applications. Le harnais shell appelé directement ne bénéficie pas de ces garde-fous.

Le mot « mince » est important : réécrire les 29 cas en PHP aurait produit deux jeux de tests qui divergent au premier changement de schéma. Le harnais shell est la référence ; le PHP n'ajoute que l'isolation.

L'isolation est le point délicat : `tests/sql/fixture.sql` réinitialise l'état **avant chaque scénario** — lancée sur la base de développement, la campagne effacerait les commandes de démonstration. L'enveloppe refuse le nom de base applicative configuré ; cela ne protège pas les bases des autres applications. `--garder` permet d'inspecter le résultat.

Une note d’archive rapporte un refus `1044 Access denied` lors de la création de `minishop_test` par un compte applicatif limité à `minishop.*`. Ce récit n’est pas rattaché ici à un run daté/SHA et n’est pas une preuve de l’environnement actuel. Le protocole décrit une création explicite par `php scripts/db_init.php --test-db`, sans élargir les droits sur la base applicative ; à vérifier sur serveur jetable avant toute réinitialisation.

Comportement attendu : sans client `mariadb`/`mysql` dans le `PATH`, ou sans serveur joignable, le harnais annonce **NON JOUÉ** et sort en code 2 (ce qui permet à `run-all-tests.mjs --sans-sql --sans-e2e` de tourner dans un conteneur dépourvu de MariaDB, tandis que la campagne VM `run-all-tests.php` joue l’intégralité des suites).

### 2.3 Tests de bout en bout (étage 6) — ce que l'utilisateur voit

Sur VM Debian 13 (`f9b614f`, `docs/pv-recette.md`), `http_sql_e2e.php` a validé **105/105** contrôles HTTP de bout en bout sous MariaDB/PDO et `concurrence_http.php` **8/8** contrôles en parallèle (20 acteurs / 12 unités). Côté PHP-WASM et DOM, `tests/node/e2e_isolation.mjs` (**338** vérifications sur stockage JSON isolé) et `tests/node/preferences_test.mjs` (**48** vues FR/EN et **45** messages métier) sont également verts. Ces campagnes automatisées ne remplacent pas une recette humaine navigateur sur LineServ.

## 3. Les tests de contrat — la partie qui attrape ce que les autres laissent passer

Trois suites sont prévues pour analyser des artefacts **sans exécuter l'application**. Elles visent une catégorie de défaut que ni les tests unitaires ni l'E2E ne ciblent directement : la **cohérence entre deux artefacts écrits séparément**. Leur présence ne signifie pas qu'elles ont été rejouées.

| Suite | Ce qu'elle vise à contrôler | Exemple de défaut recherché |
|---|---|---|
| `contract_http.php` (27 assertions décrites) | vise à contrôler les liens/actions vers des routes déclarées, les gardes `/admin/*`, l’absence d’écriture en `GET` et les en-têtes | un `<a href="/commande/suivi">` après renommage de route ; une route admin sans `exigerAdmin()` |
| `css_contract.php` (69 assertions décrites) | vise à contrôler les jetons du design system, les contrastes AA et les sources de la CSP | une couleur écrite en dur qui contourne le jeton ; un `style=` inline bloqué par la CSP |
| `responsive_contract.php` (50 assertions décrites) | vise à contrôler la mise en page de 320 px à 1536 px selon WCAG 1.4.10 : largeur, tableaux défilants, cibles tactiles | une grille `min-width: 900px` qui provoque un défilement horizontal sur mobile |

Ces tests sont **statiques** : ils analysent les fichiers et ne remplacent pas une validation de navigateur. Un scénario Chromium headless (`tests/node/responsive_navigateur.mjs`, largeurs 320, 375 et 1440 px) est décrit séparément ; aucune exécution CI ou validation multi-largeurs n’est revendiquée ici.

## 4. Résultats sur VM Debian 13 et exécutions en conteneur isolé

Dans un conteneur sans PHP CLI ni MariaDB, `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` exécute les suites PHP-WASM et Python ; sur la VM Debian 13 native (**PHP 8.4.27 cli**, **MariaDB 11.8.6**) aux commits `f9b614f` et `cb0f814`, l’ensemble complet de la chaîne a été exécuté (`docs/pv-recette.md` et `tests/perf/dernieres_mesures.txt`) :

| Vérification | État vérifié (`17/11/2026`, VM Debian 13) | Limites maintenues |
|---|---|---|
| Suites PHP & qualité (`run-all-tests.php`) | **60/60 étapes vertes** (`f9b614f`, `0` warning PHP) : T-01…T-29 JSON **29/29**, PHPUnit **57/57**, linter strict **171** fichiers/`0` violation, sécurité **11/11** | Aucun navigateur humain ni recette LineServ |
| Contrôles Python/documentaires | **42 tests** (**22 OK**, **20 skipped** en conteneur sans PHP ; verts sous VM PHP 8.4), `verifier_fiches_uc.py` **46/46** | Ne constituent pas une recette humaine |
| `sql_integration.php` et scripts SQL | **Joué ✅ (`29/29` sous MariaDB 11.8.6)**, `17` vérifications dépôt SQL, `2 550` scénarios catalogue | Base temporaire isolée (`minishop_test`), distincte de LineServ |
| `http_sql_e2e.php`, `concurrence_http.php` & E2E JSON | **Joué ✅ (`105/105` HTTP SQL, `8/8` concurrence 20/12, `338/338` E2E JSON isolé)** ; `preferences_test.mjs` **48 vues FR/EN** | Navigateur réel humain et LineServ non joués |
| `coverage.php` | Disponible avec PHP CLI natif (chargement des classes de `app/`) | Mesure de chargement de fichiers, pas de couverture de lignes |
| `run_perf_vm.sh` et mesures de charge | **Joué ✅ (`cb0f814`, `minishop_perf`)** : SQL **p95 ≤ 20,6 ms**, écriture **44 ms**, HTTP **p95 global = 6 ms**, `0` erreur | Client/serveur sur la même VM ; `P-03` HTTP non bouclé séparément |
| Navigateur réel, lecteur d'écran, CSV manuel | **`NON JOUÉ`** (**200 entrées** dans `docs/13-suivi-recette-uc.csv`) | Aucune recette manuelle humaine revendiquée |

Le contrôle `node scripts/wasm_verdict.mjs` vérifie en outre **9/9 cas** sur les verdicts de l’adaptateur WASM.

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

Le script `scripts/wasm_verdict.mjs` décrit un classement à partir de signaux observables : erreur fatale ou de syntaxe PHP → 255 ; marqueur « ❌ » → 1 ; « NON JOUÉ » sans « ✅ » → 2 ; sinon → 0. Le contrôle dédié `node scripts/wasm_verdict.mjs` vérifie **9/9 cas de verdict**. Côté exécution native, `scripts/generer_pv.php` enregistre l’intégralité des sorties et codes de retour dans `logs/pv-*.log` (`60/60` étapes vertes sur `f9b614f`).

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

**Ce que les preuves établissent (mise à jour du 17/11/2026), avec leurs limites.** La campagne sur VM Debian 13 des **16–17/11/2026** (`f9b614f` / `cb0f814`) a validé l’exécution PHP 8.4 native et PDO/MariaDB 11.8.6 : `60/60` étapes (`docs/pv-recette.md`, `0` warning PHP), `sql_integration.php` (`29/29`), `http_sql_e2e.php` (`105/105`), `concurrence_http.php` (`8/8`, 20 acteurs / 12 unités), E2E JSON isolé (`338/338`), `preferences_test.mjs` (`48` vues FR/EN) et performances `run_perf_vm.sh` (SQL p95 ≤ `20,6 ms`, HTTP p95 = `6 ms`, `tests/perf/dernieres_mesures.txt`).

**Ce qui reste hors périmètre automatique, et qu’on assume.**

1. **Pas de couverture de lignes.** Assumé pour ne pas ajouter de dépendance ; le substitut (fichiers chargés) est plus faible et le dit.
2. **Les tests de contrat sont statiques.** Une expression régulière sur un gabarit n'est pas un navigateur : un lien construit dynamiquement échappe au contrôle (la suite l'annonce, elle ne le juge pas).
3. **Pas d'accessibilité humaine ni de lecteur d’écran automatisés.** Contrastes et cibles tactiles sont calculés ; la navigation au lecteur d'écran reste manuelle.
4. **Fiabilité du verdict.** Le contrôle dédié `node scripts/wasm_verdict.mjs` vérifie 9/9 cas pour PHP-WASM ; côté PHP natif, `generer_pv.php` enregistre l’intégralité des sorties et codes de retour dans `logs/pv-2026-11-17-114957.log`.
5. **Charge HTTP et écriture métier mesurées sur VM locale uniquement.** `concurrence_http.php` (20 acteurs / 12 unités) et `run_perf_vm.sh` (HTTP p95 = `6 ms`, `sp_add_order_line` = `44 ms`) ont été validés sur la même VM Debian 13, et non sur LineServ.
6. **Recette humaine et cible LineServ distinctes.** Les **200 scénarios manuels** (`docs/13-suivi-recette-uc.csv`) restent `NON JOUÉ` ; aucun déploiement sur LineServ ni validation pédagogique n’est revendiqué.

L’archive mentionne aussi une mesure de 17 ms pour `sp_revenue_report` (anomalie A-08). Faute de run, SHA, matériel, jeu de données et sortie rattachés ici, cette valeur reste historique et ne vaut pas mesure actuelle.


## Information juridique, mentions légales LCEN et protection des données

`tests/php/information_juridique_test.php` et `tests/php/mentions_legales_test.php` définissent des contrôles automatisés de navigation, contenu, cookies/stockage local, mentions et information précontractuelle (exécutés dans la campagne VM `60/60` et l’E2E isolé `338/338`, sans navigateur humain). Ni ces scripts ni les résultats décrits dans `docs/02` ne constituent un avis juridique ou une validation humaine. Détails et réserves dans `docs/12-information-juridique.md`.


## Dates UTC, fuseau horaire local et traduction intégrale FR ↔ EN

- Les suites `date_ajout_test.php`, `heure_test.php`, `ConservationEtFuseauTest.php` et `sql_integration.php` (`29/29` sur MariaDB 11.8.6) valident les dates UTC (`time_zone = '+00:00'`), les conversions d’heure d’hiver/été et la rétention.
- `tests/node/preferences_test.mjs` valide la traduction FR ↔ EN sur 48 vues, 45 messages métier, choix manuel de langue/fuseau, `localStorage` et tests DOM.
- `tests/node/responsive_navigateur.mjs` définit des contrôles Chromium headless aux largeurs **320, 375 et 1440 px**, avec et sans JavaScript (complémentaires de `responsive_contract.php` et exécutables en CI lorsque Playwright/Chromium est installé).


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

Le code et les scénarios versionnés assurent un refus HTTP 403 pour un administrateur sans rôle SUPER et une redirection pour un visiteur non connecté ; ces parcours sont validés tant dans l’E2E JSON isolé (`338/338`) que sous MariaDB/PDO dans `http_sql_e2e.php` (`105/105`, `docs/pv-recette.md`). La vérification manuelle d’un rendu navigateur reste distincte.

## Articulation avec les suites de tests manuels (`docs/13-*`)

Même avec **15 jobs CI automatisés**, une campagne humaine reste indispensable sur ordinateur pour valider :
1. les parcours métier complets, la navigation au clavier et les confirmations explicites en deux étapes (avec et sans JavaScript) dans un navigateur réel ;
2. l'expérience réelle de la boîte de dialogue navigateur de permission de géolocalisation sur `/preferences` et la qualité linguistique perçue des pages traduites en anglais ;
3. l'hébergement cible **LineServ** (sous-dossier éventuel, séparation des fichiers privés, absence de scripts de développement exposés) et la recette manuelle sur la base réellement utilisée par l'application de production, pendant une fenêtre pré-démo autorisée ; cette même base doit être restaurée à l'état de démonstration avant la démo. `minishop_test` reste réservée aux tests SQL automatisés de GitHub CI et n'est pas la cible manuelle.

**Périmètre de recette manuelle proposé :** les contrôles responsive multi-largeurs (`320/375/768/1440 px`, cartes mobiles `data-label`, portrait/paysage) sont décrits dans les suites automatisées `tests/php/responsive_contract.php` et `tests/node/responsive_navigateur.mjs`. Leur présence n’établit pas qu’ils ont été rejoués ; aucune validation humaine exhaustive sur téléphones physiques n’est revendiquée.

L'ensemble des vérifications humaines retenues est formalisé dans [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) (**164 scénarios** sur `UC-01` à `UC-14` et `TCX-01` à `TCX-24`, plus les **36 couples** de la matrice `RB-11`) et son journal [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) (**200 lignes** initialisées à `NON JOUÉ`). L'intégrité structurelle et la synchronisation de ces deux fichiers avec le code (`Triggers::TRANSITIONS`, routes, fichiers cités et absence de retour des scénarios responsive manuels) sont vérifiées automatiquement par `python3 scripts/verifier_fiches_uc.py` dans les orchestrateurs de tests et en CI.

