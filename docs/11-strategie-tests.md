# 11 — Stratégie de tests

Ce document ne remplace pas le [document de tests et de validation](02-document-tests-validation.md) (cas détaillés, registre d'anomalies, PV de recette) : il explique **comment** la vérification est construite, **pourquoi** à ce niveau-là, et **ce qu'elle ne prouve pas**. C'est le document qu'on lit quand on se demande « vos tests, ils servent à quoi ? ».

> **Portée historique — coupure documentaire 23/10/2026.** Les volumes et résultats d’archive sans run rattaché ne valent pas preuve sur le checkout examiné. Le compte rendu du run du 23/10, `node scripts/run-all-tests.mjs --sans-sql --sans-e2e`, rapporte un code de sortie 0 via PHP-WASM 8.2 : T-01…T-29 sur JSON (29/29), 297 vérifications, 57 tests PHPUnit et linter strict (167 fichiers, 0 violation). Il excluait explicitement SQL et E2E ; l’analyse de compilation avec PHP CLI natif n’était pas jouée. Le SHA testé n’est pas conservé dans cette trace, qui ne vaut donc pas preuve sur le checkout actuel.
>
> **Addendum — exécutions du 15/11/2026 sur le commit `b8edfc9`** (branche `arena/10afc76d-sae-302`). `node scripts/run-all-tests.mjs --sans-sql` a terminé avec le code 0 via PHP-WASM 8.2 : T-01…T-29 sur JSON (29/29), 297 vérifications, 57 tests PHPUnit, linter strict (167 fichiers, 0 violation) et E2E HTTP isolé (338 vérifications, fichiers hôte témoins inchangés). `node tests/node/preferences_test.mjs` a également terminé avec le code 0 (48 vues bilingues FR/EN, 45 messages métier). Ce run excluait SQL et PHP CLI natif.
>
> **Addendum final — campagne complète sur VM Debian 13 des 16–17/11/2026** (branche `arena/b5a4074b-sae-302`, commits `f9b614f` et `cb0f814`). Sur VM native (**PHP 8.4.27 cli**, **MariaDB 11.8.6**) :
> - `php scripts/generer_pv.php` (`f9b614f`, `docs/pv-recette.md`) a terminé avec le code **0**, **60/60 étapes vertes** et **0 warning PHP**, incluant l’intégration SQL (`sql_integration.php`, **29/29**), le parcours HTTP complet sous SQL (`http_sql_e2e.php`, **105/105**), la concurrence HTTP 20/12 (`concurrence_http.php`, **8/8**), l’E2E JSON isolé (`338/338`), `preferences_test.mjs` (`48` vues FR/EN), PHPUnit (`57/57`), le linter strict (`171` fichiers, `0` violation) et les contrôles statiques de sécurité (`11/11`).
> - `bash scripts/run_perf_vm.sh` (`cb0f814`, `tests/perf/dernieres_mesures.txt`) sur la base isolée `minishop_perf` (`236` produits, `1 004` commandes) a mesuré : SQL (100 it.) **p95 ≤ 20,6 ms**, écriture métier `sp_add_order_line` **44 ms**, et HTTP (200 it. × 6 parcours) **100 % HTTP 200**, **0 erreur**, **p95 global = 6 ms** (`ENF-01 < 500 ms` conforme).
> - Les **200 entrées manuelles** de `docs/13-suivi-recette-uc.csv` restent distinctement **`NON JOUÉ`**, et aucun test manuel sur LineServ, déploiement ni validation pédagogique n’est revendiqué.

## 1. Le principe : la pyramide des tests et son protocole

La pyramide des tests décrit les niveaux et les protocoles visés. Les volumes ci-dessous sont des chiffres de couverture décrite, pas tous des résultats d’exécution. Le run historique du 23/10 (`--sans-sql --sans-e2e`) excluait SQL et E2E ; le run distinct du 15/11 (`--sans-sql`) inclut l’E2E HTTP isolé sous PHP-WASM, mais pas l’intégration SQL/PDO ni un navigateur réel. Les résultats sont séparés en §4 et dans `docs/02`/`docs/19`.

| Étage | Nature | Suite | Volume | Durée |
|---|---|---|---|---|
| 0 | Analyse statique & audits documentaires | `scripts/strict_types_lint.php` + `tests/security/controles.sh` (11 contrôles statiques inscrits) + `scripts/verifier_fiches_uc.py` + `tests/python/test_audit_structurel.py` + `scripts/wasm_verdict.mjs` | Inventaire de contrôles ; les résultats exécutés pour cette révision sont consignés séparément dans `docs/19`, sans en déduire le passage d’une suite globale | non mesurée ici |
| 1 | Unitaires purs | `tests/php/unit_tests.php` + `tests/phpunit/**` (classes de tests versionnées) | Suites définies dans le dépôt ; aucun résultat PHP natif n’est revendiqué dans cette revue | non mesurée ici |
| 2 | Contrats statiques & métier ciblés | `contract_http.php`, `css_contract.php`, `responsive_contract.php` + suites spécialisées `tests/php/*_test.php` | Protocoles présents ; l’exécution des suites PHP exige un run natif daté et rattaché au SHA | non mesurée ici |
| 3 | Règles de gestion sur le moteur | `tests/php/run_tests.php` (`T-01` → `T-29` du CDC §6), `tests/php/engine_smoke.php`, `tests/php/json_concurrency_stress.php` | 29 scénarios référencés ; leur résultat doit être établi par un rejeu daté sur le SHA concerné | non mesurée ici |
| 4 | Fonctionnalités via dépôts & fixtures isolées | `tests/php/features_test.php` (UC-01 → UC-14), `catalogue_stock_test.php`, `nouveautes_test.php` (fixtures prévues en magasins temporaires) | Périmètre de scénarios annoncé dans cette archive ; les nombres de cas ne sont pas des résultats de passage | non mesurée ici |
| 5 | Intégration SQL réelle & migrations | `tests/php/sql_integration.php` (`tests/sql/run_tests.sh` et assertions PDO) + `tests/php/migration_mariadb_test.php` | Validée sur VM Debian 13 (`f9b614f`, `docs/pv-recette.md`) : SQL **29/29** et migrations MariaDB **vertes** sur base temporaire isolée | validée sur base isolée (`minishop_test`), distincte de LineServ |
| 6 | Parcours HTTP E2E, HTTP SQL, DOM FR↔EN & CLI Python | `http_sql_e2e.php` + `concurrence_http.php` + `scripts/wasm-e2e.mjs` + `tests/node/` + `tests/python/` | Run VM `17/11` (`f9b614f`) : `http_sql_e2e` **105/105**, `concurrence_http` **8/8**, E2E JSON **338/338**, `preferences_test` **48 vues FR/EN**, Python **42 tests** | validé automatiquement ; navigateur humain et LineServ non joués |
| 6 bis | Navigateur réel headless (CI) | `tests/node/responsive_navigateur.mjs` (Playwright Chromium, largeurs prévues 320, 375 et 1440 px) | Protocole et contrat statique (`responsive_contract.php`) ; exécution Playwright conditionnée au binaire Chromium (absent de cette VM sans `node_modules`) | contrat CSS/HTML vérifié ; Chromium réel à vérifier en CI |
| 7 | Performance & volumétrie | `scripts/run_perf_vm.sh` → `scripts/gen_volumes_cli.php` + `tests/perf/{mesurer_sql,mesurer}.sh` | Validée sur VM Debian 13 (`cb0f814`, `minishop_perf` : 236 produits, 1 004 commandes, `tests/perf/dernieres_mesures.txt`) : SQL **p95 ≤ 20,6 ms**, HTTP **p95 = 6 ms**, **0 erreur** | validée sur VM locale (`ENF-01 < 500 ms`) ; `P-03` HTTP non bouclé séparément |
| 8 | Suites de recette manuelle humaine | [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) + [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) + [`docs/14-guide-lineserv.md`](14-guide-lineserv.md) | **164 scénarios** (`UC-01..14` + `TCX-01..24`, hors responsive multi-largeurs) + **36 couples** = **200 lignes `NON JOUÉ`** | campagne humaine |

**Total** : les volumes détaillés figurent ci-dessus ; les scénarios catalogue varient avec le nombre de produits. La matrice catalogue MariaDB reste à jouer.

Pourquoi cette forme, et pas 400 tests unitaires avec des doublures partout : l'essentiel du sujet est une **base de données** (8 entités métier, 9 tables SQL avec la table technique d’audit, 18 procédures, 16 déclencheurs, 1 fonction, 3 vues). Un test unitaire avec un `PDO` simulé prouverait seulement que le PHP appelle une chaîne de caractères. La stratégie vise donc à contrôler les règles là où elles vivent : étage 3 sur le moteur, étage 5 sur MariaDB. La présence de ces étages dans le plan ne prouve pas leur exécution ni la conformité du parcours applicatif.

## 2. Les trois natures de test, et ce qu'elles distinguent

### 2.1 Tests de règles (étage 3) — le moteur, sans HTTP

`tests/php/run_tests.php` exécute les 29 scénarios `T-01` → `T-29` du cahier des charges sur `app/Model/Data/JsonStore.php`, qui réimplémente contraintes, déclencheurs et transactions. Les runs PHP-WASM du 23/10 et du 15/11 ont rapporté **29/29** sur cette voie JSON. Cela établit le résultat de ces scénarios sur le magasin JSON dans les exécutions citées, pas leur comportement sur le pilote SQL/MySQL/PDO par défaut.

`tests/php/engine_smoke.php` vise à contrôler les hypothèses de cette réimplémentation : API des pilotes (`JSON` et `SQL`), absence de connexion sans PDO, absence de concaténation SQL et comportement de `LIKE`. Ces contrôles exigent un résultat de run daté et rattaché au SHA avant d'être présentés comme réussis.

### 2.2 Intégration SQL (étage 5) — protocole à rejouer pour établir le résultat

`tests/php/sql_integration.php` est conçu comme une **enveloppe mince** autour de `tests/sql/run_tests.sh` : le protocole crée une base temporaire dédiée `minishop_test`, y charge `sql/01` → `sql/04`, contrôle le repository sur `SqlStore` et la procédure de recherche, joue les 29 scénarios, puis supprime la base. Si les runs restreints du 23/10 (`--sans-sql --sans-e2e`) et du 15/11 (`--sans-sql`) ont exclu SQL, la campagne complète sur VM Debian 13 du **17/11/2026** (`f9b614f`, `docs/pv-recette.md`) a exécuté `sql_integration.php` (**29/29**), `http_sql_e2e.php` (**105/105**) et `concurrence_http.php` (**8/8**) avec le code de sortie **0**.

Depuis l’audit global, cette enveloppe refuse par défaut une base existante et refuse toujours le nom `DB_NAME` configuré. `--reinitialiser-base-existante` autorise explicitement la suppression : serveur de test jetable uniquement. La protection du nom ne détecte pas les bases utilisées par d’autres applications. Le harnais shell appelé directement ne bénéficie pas de ces garde-fous.

Le mot « mince » est important : réécrire les 29 cas en PHP aurait produit deux jeux de tests qui divergent au premier changement de schéma. Le harnais shell est la référence ; le PHP n'ajoute que l'isolation.

L'isolation est le point délicat : `tests/sql/fixture.sql` réinitialise l'état **avant chaque scénario** — lancée sur la base de développement, la campagne effacerait les commandes de démonstration. L'enveloppe refuse le nom de base applicative configuré ; cela ne protège pas les bases des autres applications. `--garder` permet d'inspecter le résultat.

Une note d’archive rapporte un refus `1044 Access denied` lors de la création de `minishop_test` par un compte applicatif limité à `minishop.*`. Ce récit n’est pas rattaché ici à un run daté/SHA et n’est pas une preuve de l’environnement actuel. Le protocole décrit une création explicite par `php scripts/db_init.php --test-db`, sans élargir les droits sur la base applicative ; à vérifier sur serveur jetable avant toute réinitialisation.

Comportement attendu : sans client `mariadb`/`mysql` dans le `PATH`, ou sans serveur joignable, le harnais doit annoncer **NON JOUÉ** et sortir en code 2. Les deux runs mentionnés ci-dessus ont délibérément sauté cette enveloppe (`--sans-sql`) ; ils ne vérifient donc pas ce comportement ni ne valent intégration SQL.

### 2.3 Tests de bout en bout (étage 6) — ce que l'utilisateur voit

Le run du 15/11 a exécuté `tests/node/e2e_isolation.mjs` : parcours HTTP via le front controller dans PHP-WASM, sans navigateur ni serveur HTTP réel, **338 vérifications réussies** et fichiers hôte témoins inchangés. `tests/node/preferences_test.mjs`, exécuté séparément le même jour, a validé le DOM FR/EN sur 48 vues et 45 messages métier. Ces résultats portent sur les parcours et le stockage JSON isolés ; ils ne valident ni PDO/MySQL, ni un serveur web natif, ni LineServ ou un navigateur réel.

## 3. Les tests de contrat — la partie qui attrape ce que les autres laissent passer

Trois suites sont prévues pour analyser des artefacts **sans exécuter l'application**. Elles visent une catégorie de défaut que ni les tests unitaires ni l'E2E ne ciblent directement : la **cohérence entre deux artefacts écrits séparément**. Leur présence ne signifie pas qu'elles ont été rejouées.

| Suite | Ce qu'elle vise à contrôler | Exemple de défaut recherché |
|---|---|---|
| `contract_http.php` (27 assertions décrites) | vise à contrôler les liens/actions vers des routes déclarées, les gardes `/admin/*`, l’absence d’écriture en `GET` et les en-têtes | un `<a href="/commande/suivi">` après renommage de route ; une route admin sans `exigerAdmin()` |
| `css_contract.php` (69 assertions décrites) | vise à contrôler les jetons du design system, les contrastes AA et les sources de la CSP | une couleur écrite en dur qui contourne le jeton ; un `style=` inline bloqué par la CSP |
| `responsive_contract.php` (50 assertions décrites) | vise à contrôler la mise en page de 320 px à 1536 px selon WCAG 1.4.10 : largeur, tableaux défilants, cibles tactiles | une grille `min-width: 900px` qui provoque un défilement horizontal sur mobile |

Ces tests sont **statiques** : ils analysent les fichiers et ne remplacent pas une validation de navigateur. Un scénario Chromium headless (`tests/node/responsive_navigateur.mjs`, largeurs 320, 375 et 1440 px) est décrit séparément ; aucune exécution CI ou validation multi-largeurs n’est revendiquée ici.

## 4. État des suites jouées et non jouées en sandbox

PHP CLI, Composer, MySQL/MariaDB et navigateur réel ne sont pas disponibles dans cette sandbox. Le runtime PHP-WASM a été fourni par les dépendances Node installées. Le run restreint du 23/10 (`node scripts/run-all-tests.mjs --sans-sql --sans-e2e`) a réussi hors SQL/E2E. Le run distinct du 15/11 (`node scripts/run-all-tests.mjs --sans-sql`) s’est terminé avec le code 0 et a inclus l’E2E HTTP isolé. Les deux runs excluent l’intégration SQL ; aucun ne constitue une CI native, une validation PDO/MySQL ou une recette navigateur.

| Suite | État dans cette révision | Limite |
|---|---|---|
| Suites PHP non SQL dans `run-all-tests.mjs` | Run du 15/11 réussi via PHP-WASM 8.2 avec `--sans-sql` ; T-01…T-29 JSON 29/29, 297 vérifications unitaires, 57 tests PHPUnit, linter strict 167 fichiers/0 violation. Le run du 23/10 (`--sans-sql --sans-e2e`) est distinct et excluait aussi l’E2E. | Pas de PHP CLI natif ; l’analyse de compilation native est NON JOUÉE. Aucun résultat PDO/MySQL. |
| Contrôles Python/documentaires du runner | Réussis selon les sorties de `docs/19` | Ne constituent pas une CI externe ni une recette humaine |
| `sql_integration.php` et scripts SQL | NON JOUÉ ; explicitement exclus avec `--sans-sql` | Client/serveur MySQL/MariaDB absents ; aucune base temporaire n’a été créée |
| E2E HTTP isolé | Run du 15/11 via PHP-WASM : 338 vérifications réussies, fichiers hôte témoins inchangés. Le run du 23/10 l’excluait avec `--sans-e2e`. | Stockage JSON isolé ; pas de PDO/MySQL, navigateur réel, serveur web natif ou LineServ |
| `coverage.php` | NON JOUÉ | PHP CLI absent |
| `capture_perf.sh` et mesures de charge | NON JOUÉ | serveur et cible de mesure absents |
| Navigateur réel, lecteur d'écran | NON JOUÉ | aucune recette humaine revendiquée |

Le contrôle `node scripts/wasm_verdict.mjs` a passé 9/9 cas dans le même run. Cela vérifie les verdicts de l’adaptateur WASM ; le résultat demeure limité aux options choisies et ne prouve ni SQL/PDO, ni une exécution PHP native, ni un navigateur réel.

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

Le script `scripts/wasm_verdict.mjs` décrit un classement à partir de signaux observables : erreur fatale ou de syntaxe PHP → 255 ; marqueur « ❌ » → 1 ; « NON JOUÉ » sans « ✅ » → 2 ; sinon → 0. Le contrôle dédié `node scripts/wasm_verdict.mjs` a passé **9/9 cas de verdict** le 23/10. Ce run historique de l’orchestrateur utilisait `--sans-sql --sans-e2e` ; le run du 15/11 a utilisé `--sans-sql`, terminé avec le code 0 et inclus l’E2E HTTP isolé. Aucun des deux ne valide SQL/PDO ni l’analyse de compilation avec PHP CLI natif.

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

**Ce que les preuves établissent (mise à jour du 17/11/2026), avec leurs limites.** Outre le run JSON/PHP-WASM du 15/11 (`338` vérifications), la campagne sur VM Debian 13 des **16–17/11/2026** (`f9b614f` / `cb0f814`) a validé l’exécution PHP 8.4 native et PDO/MariaDB 11.8.6 : `60/60` étapes (`docs/pv-recette.md`, `0` warning PHP), `sql_integration.php` (`29/29`), `http_sql_e2e.php` (`105/105`), `concurrence_http.php` (`8/8`, 20 acteurs / 12 unités) et performances `run_perf_vm.sh` (SQL p95 ≤ `20,6 ms`, HTTP p95 = `6 ms`, `tests/perf/dernieres_mesures.txt`).

**Ce qui reste hors périmètre automatique, et qu’on assume.**

1. **Pas de couverture de lignes.** Assumé pour ne pas ajouter de dépendance ; le substitut (fichiers chargés) est plus faible et le dit.
2. **Les tests de contrat sont statiques.** Une expression régulière sur un gabarit n'est pas un navigateur : un lien construit dynamiquement échappe au contrôle (la suite l'annonce, elle ne le juge pas).
3. **Pas d'accessibilité humaine ni de lecteur d’écran automatisés.** Contrastes et cibles tactiles sont calculés ; la navigation au lecteur d'écran reste manuelle.
4. **Fiabilité du verdict.** Le contrôle dédié `node scripts/wasm_verdict.mjs` vérifie 9/9 cas pour PHP-WASM ; côté PHP natif, `generer_pv.php` enregistre l’intégralité des sorties et codes de retour dans `logs/pv-2026-11-17-114957.log`.
5. **Charge HTTP et écriture métier mesurées sur VM locale uniquement.** `concurrence_http.php` (20 acteurs / 12 unités) et `run_perf_vm.sh` (HTTP p95 = `6 ms`, `sp_add_order_line` = `44 ms`) ont été validés sur la même VM Debian 13, et non sur LineServ.
6. **Recette humaine et cible LineServ distinctes.** Les **200 scénarios manuels** (`docs/13-suivi-recette-uc.csv`) restent `NON JOUÉ` ; aucun déploiement sur LineServ ni validation pédagogique n’est revendiqué.

L’archive mentionne aussi une mesure de 17 ms pour `sp_revenue_report` (anomalie A-08). Faute de run, SHA, matériel, jeu de données et sortie rattachés ici, cette valeur reste historique et ne vaut pas mesure actuelle.


## Information juridique, mentions légales LCEN et protection des données

`tests/php/information_juridique_test.php` et `tests/php/mentions_legales_test.php` définissent des contrôles automatisés de navigation, contenu, cookies/stockage local, mentions et information précontractuelle. Le run restreint du 23/10 excluait l’E2E ; l’E2E HTTP isolé a été joué séparément le 15/11 via PHP-WASM, sans navigateur réel. Ni ces scripts ni les résultats décrits dans `docs/02` ne constituent un avis juridique ou une validation humaine. Détails et réserves dans `docs/12-information-juridique.md`.


## Dates UTC, fuseau horaire local et traduction intégrale FR ↔ EN

- Les suites `date_ajout_test.php`, `heure_test.php` et `ConservationEtFuseauTest.php` décrivent des contrôles de dates/fuseaux ; aucun des runs cités ne valide des timestamps réellement stockés par MySQL/PDO, puisque SQL est explicitement exclu.
- `tests/node/preferences_test.mjs` a réussi séparément le 15/11 : traduction FR ↔ EN sur 48 vues, 45 messages métier, choix manuel de langue/fuseau, `localStorage` et tests DOM.
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

Le code et les scénarios versionnés décrivent un refus HTTP 403 pour un administrateur sans rôle SUPER et une redirection pour un visiteur non connecté. Le run E2E PHP-WASM du 15/11 a réussi 338 vérifications, couvrant les parcours d’accès HTTP présents dans `scripts/wasm-e2e.mjs`. Il s’agit de l’application sur stockage JSON isolé : pas de serveur PHP natif, de CI, de PDO/MySQL ou de navigateur réel. La vérification d’un rendu navigateur reste à effectuer séparément.

## Articulation avec les suites de tests manuels (`docs/13-*`)

Même avec **15 jobs CI automatisés**, une campagne humaine reste indispensable sur ordinateur pour valider :
1. les parcours métier complets, la navigation au clavier et les confirmations explicites en deux étapes (avec et sans JavaScript) dans un navigateur réel ;
2. l'expérience réelle de la boîte de dialogue navigateur de permission de géolocalisation sur `/preferences` et la qualité linguistique perçue des pages traduites en anglais ;
3. l'hébergement cible **LineServ** (sous-dossier éventuel, séparation des fichiers privés, absence de scripts de développement exposés) et la recette manuelle sur la base réellement utilisée par l'application de production, pendant une fenêtre pré-démo autorisée ; cette même base doit être restaurée à l'état de démonstration avant la démo. `minishop_test` reste réservée aux tests SQL automatisés de GitHub CI et n'est pas la cible manuelle.

**Périmètre de recette manuelle proposé :** les contrôles responsive multi-largeurs (`320/375/768/1440 px`, cartes mobiles `data-label`, portrait/paysage) sont décrits dans les suites automatisées `tests/php/responsive_contract.php` et `tests/node/responsive_navigateur.mjs`. Leur présence n’établit pas qu’ils ont été rejoués ; aucune validation humaine exhaustive sur téléphones physiques n’est revendiquée.

L'ensemble des vérifications humaines retenues est formalisé dans [`docs/13-fiches-recette-uc.md`](13-fiches-recette-uc.md) (**164 scénarios** sur `UC-01` à `UC-14` et `TCX-01` à `TCX-24`, plus les **36 couples** de la matrice `RB-11`) et son journal [`docs/13-suivi-recette-uc.csv`](13-suivi-recette-uc.csv) (**200 lignes** initialisées à `NON JOUÉ`). L'intégrité structurelle et la synchronisation de ces deux fichiers avec le code (`Triggers::TRANSITIONS`, routes, fichiers cités et absence de retour des scénarios responsive manuels) sont vérifiées automatiquement par `python3 scripts/verifier_fiches_uc.py` dans les orchestrateurs de tests et en CI.

