# 11 — Stratégie de tests

Ce document ne remplace pas le [document de tests et de validation](02-document-tests-validation.md) (cas détaillés, registre d'anomalies, PV de recette) : il explique **comment** la vérification est construite, **pourquoi** à ce niveau-là, et **ce qu'elle ne prouve pas**. C'est le document qu'on lit quand on se demande « vos tests, ils servent à quoi ? ».

## 1. Le principe : la pyramide, appliquée pour de vrai

La pyramide des tests est souvent citée puis contredite par les chiffres. Voici les nôtres, mesurés sur la révision courante (`node scripts/run-all-tests.mjs`).

| Étage | Nature | Suite | Volume | Durée |
|---|---|---|---|---|
| 0 | Analyse statique | `scripts/strict_types_lint.php` (règles php-cs-fixer `declare_strict_types`, types natifs niveau PHPStan 9, interdiction du ternaire) + autotest du harnais `scripts/wasm_verdict.mjs` | **104 fichiers**, 0 violation · **9 cas** de verdict | < 1 s |
| 1 | Unitaires purs | `tests/php/unit_tests.php` (`Text`, `Csrf`, `Filter`, `PanierSession`, `RB-11`…) + `tests/phpunit/**` | **282** vérifications + **34** tests PHPUnit | ~ 6 s |
| 2 | Contrats (sans exécution) | `tests/php/contract_http.php`, `tests/php/css_contract.php`, `tests/php/responsive_contract.php` | **27** + **69** + **50** vérifications | ~ 2 s |
| 3 | Règles de gestion sur le moteur | `tests/php/run_tests.php` (T-01 → T-29 du CDC §6), `tests/php/engine_smoke.php` | **29/29** scénarios, **44** contrôles moteur | ~ 10 s |
| 4 | Fonctionnalités de bout en bout (application court-circuitée) | `tests/php/features_test.php` (UC-01 → UC-14 via les dépôts) | **131** vérifications | ~ 4 s |
| 4 bis | Contrat catalogue / ruptures | `tests/php/catalogue_stock_test.php` sur une fixture JSON isolée | **7 656** scénarios sur le jeu actuel | variable |
| 4 ter | Derniers ajouts | `tests/php/nouveautes_test.php` sur fixture JSON temporaire | **18** vérifications | variable |
| 5 | Intégration SQL réelle | `tests/php/sql_integration.php` → `tests/sql/run_tests.sh` | **29** scénarios SQL + matrice catalogue ; à rejouer sur MariaDB | ~ 30 s |
| 6 | Parcours HTTP complet | `scripts/e2e.mjs` (natif) ; `scripts/wasm-e2e.mjs` (WASM) | **268** WASM via `scripts/e2e.mjs` | ~ 90 s |
| 7 | Performance | `scripts/capture_perf.sh` → `gen_volumes.sh` + `tests/perf/mesurer_sql.sh` | 5 requêtes types + `EXPLAIN` + p95 de la recherche | ~ 60 s |

**Total** : les volumes détaillés figurent ci-dessus ; les scénarios catalogue varient avec le nombre de produits. La matrice catalogue MariaDB reste à jouer.

Pourquoi cette forme, et pas 400 tests unitaires avec des doublures partout : l'essentiel du sujet est une **base de données** (8 entités, 17 procédures, 16 déclencheurs, 1 fonction, 3 vues). Un test unitaire avec un `PDO` simulé prouverait que le PHP appelle bien une chaîne de caractères — c'est-à-dire rien. Les règles de gestion (stock, immutabilité du prix, transitions de statut, total recalculé) sont donc vérifiées **là où elles vivent réellement** : étage 3 sur le moteur, étage 5 sur MariaDB. La pyramide est respectée dans son esprit (beaucoup de tests rapides en bas) mais son sommet est anormalement lourd, et c'est assumé : c'est le sujet.

## 2. Les trois natures de test, et ce qu'elles distinguent

### 2.1 Tests de règles (étage 3) — le moteur, sans HTTP

`tests/php/run_tests.php` exécute les 29 scénarios T-01 → T-29 du cahier des charges sur `app/Model/Data/JsonStore.php`, qui réimplémente contraintes, déclencheurs et transactions. L'intérêt n'est pas de « tester du JSON » mais de **vérifier la règle indépendamment de l'application** : aucun contrôleur n'est instancié, aucune session n'existe. Si quelqu'un contourne le formulaire, la règle tient encore.

`tests/php/engine_smoke.php` (44 contrôles) vérifie les **hypothèses de cette réimplémentation** : que les deux pilotes (`JSON` et `SQL`) exposent la même API, que `SqlStore` n'ouvre pas de connexion sans PDO, qu'il n'assemble aucune requête par concaténation, que `LIKE` ignore casse et accents. Sans cet étage, un test de règle vert sur JSON pourrait passer sur un moteur qui ne se comporte pas pareil.

### 2.2 Tests d'intégration SQL (étage 5) — la seule preuve que le SQL fonctionne

`tests/php/sql_integration.php` est une **enveloppe mince** autour de `tests/sql/run_tests.sh` (qui existait déjà) : il crée une base dédiée `minishop_test`, y charge `sql/01` → `sql/04`, teste également le repository sur `SqlStore` et la procédure de recherche, joue les 29 scénarios, puis **supprime la base**.

Le mot « mince » est important : réécrire les 29 cas en PHP aurait produit deux jeux de tests qui divergent au premier changement de schéma. Le harnais shell est la référence ; le PHP n'ajoute que l'isolation.

L'isolation est le point délicat : `tests/sql/fixture.sql` réinitialise l'état **avant chaque scénario** — lancée sur la base de développement, la campagne effacerait les commandes de démonstration. C'est pourquoi l'enveloppe ne touche jamais la base applicative, et pourquoi `--garder` existe pour inspecter le résultat.

Un détail découvert sur la machine de recette : le compte applicatif `minishop` n'a volontairement de droits que sur `minishop.*` (moindre privilège), donc `CREATE DATABASE minishop_test` est refusé — `1044 Access denied`. Ce n'est pas un bug du test, c'est la politique de droits qui fonctionne. La base annexe s'ouvre donc **explicitement**, par `php scripts/db_init.php --test-db` : elle crée `minishop_test` et y accorde les droits, **sans** toucher au mot de passe du compte ni élargir quoi que ce soit sur la base de production. Le test, lui, ne demande jamais `sudo` de lui-même ; en cas de refus, il affiche la commande à lancer.

Dégradation : sans client `mariadb`/`mysql` dans le `PATH`, ou sans serveur joignable, le test annonce **NON JOUÉ** et sort en code 2. `scripts/run-all-tests.mjs` distingue ce cas d'un échec — un test qu'on ne peut pas jouer doit le dire, pas mentir en vert ni échouer pour de mauvaises raisons.

### 2.3 Tests de bout en bout (étage 6) — ce que l'utilisateur voit

`scripts/e2e.mjs` exécute le parcours WASM : les requêtes passent par le front controller dans le runtime PHP-WASM, sans navigateur ni serveur HTTP réel. Il vérifie les codes HTTP, le contenu des pages, les jetons CSRF et la persistance dans le magasin JSON. 268 vérifications couvrant inscription, connexion, recherche, panier, commande, suivi, administration.

Le point non évident : **le test photographie `data/` avant de le restaurer après**, y compris en cas d'échec. Sans cela, une campagne laisserait des commandes de test dans le jeu de démonstration, et la suivante échouerait sur des stocks que personne n'a modifiés — panne classique, invisible sur une machine neuve, systématique en soutenance.

## 3. Les tests de contrat — la partie qui attrape ce que les autres laissent passer

Trois suites tournent **sans exécuter l'application**. Elles sont rapides, et elles attrapent une catégorie de défaut que ni les tests unitaires ni l'E2E ne voient : la **cohérence entre deux artefacts écrits séparément**.

| Suite | Ce qu'elle garantit | Exemple de défaut attrapé |
|---|---|---|
| `contract_http.php` (27) | Chaque lien et chaque `action=` d'une vue pointe vers une route **déclarée** ; toute route `/admin/*` porte une garde ; aucune écriture en `GET` ; les en-têtes de sécurité sont émis | un `<a href="/commande/suivi">` après renommage de la route ; une route d'administration sans `exigerAdmin()` |
| `css_contract.php` (69) | Les jetons du design system sont réellement définis et utilisés ; les contrastes calculés passent AA ; la CSP n'autorise pas d'origine externe | une couleur écrite en dur qui contourne le jeton ; un `style=` inline bloqué par la CSP |
| `responsive_contract.php` (50) | La mise en page tient de 320 px à 1536 px (WCAG 1.4.10) : pas de largeur fixe, tableaux en conteneur défilant, cibles tactiles ≥ 24 px | une grille `min-width: 900px` qui provoque un défilement horizontal sur mobile |

Ces tests sont **statiques** : ils analysent les fichiers. C'est une limite réelle (aucune validation de navigateur), compensée par la vérification manuelle des paliers 320/768/1024/1536 documentée au §5 du document de tests.

## 4. Ce qui n'est pas joué en sandbox, et pourquoi c'est écrit

La sandbox de développement n'a ni PHP natif, ni MySQL, ni navigateur. Elle exécute les suites PHP via un runtime WASM (`scripts/wasm_run.mjs`, `@php-wasm/node`), ce qui suffit pour les étages 0 à 4 et 6.

| Suite | Sandbox | Machine avec MariaDB | Conséquence |
|---|---|---|---|
| Étages 0–4, 6 (PHP + E2E) | ✅ joués | ✅ joués | — |
| `sql_integration.php` (étage 5) | ⏭ NON JOUÉ (`exec` indisponible) | ✅ joué | joué sur la machine de l'équipe, résultat consigné |
| `coverage.php` | ⏭ NON JOUÉ (lance des processus enfants) | ✅ joué | voir §6 |
| `capture_perf.sh` (étage 7) | ⏭ NON JOUÉ (pas de serveur) | ✅ joué | mesures consignées dans `tests/perf/dernieres_mesures.txt` |
| Navigateur réel, lecteur d'écran | ⏭ NON JOUÉ | partiellement | vérifications manuelles, §5 du doc de tests |

**Règle tenue** : un test non jouable annonce « NON JOUÉ » et sort en code 2 ; il n'est jamais présenté comme vert. Une case cochée sans résultat derrière est un mensonge qui se paie en soutenance.

## 5. La couverture, mesurée honnêtement

`scripts/coverage.php` ne mesure **pas** la couverture de lignes : elle exigerait Xdebug ou PCOV, c'est-à-dire une dépendance à installer pour noter le projet (contraire à `ENF-20`). Il mesure ce qui est vérifiable sans rien installer : **quels fichiers de `app/` sont chargés par au moins une suite**.

Un fichier jamais chargé n'est pas « mal couvert », il n'est **jamais exécuté** : aucune suite ne peut détecter sa régression. C'est le bon signal pour repérer du code mort.

```bash
php scripts/coverage.php              # tableau par suite + pourcentage
php scripts/coverage.php --liste      # fichiers jamais chargés
php scripts/coverage.php --min=80     # échoue sous 80 % (CI)
```

Ce que cette mesure ne dit pas, et qu'il faut dire à sa place : un fichier chargé n'est pas un fichier correctement exercé ; les vues (`app/View`) ne sont chargées qu'à l'affichage, donc leur couverture par les suites PHP est structurellement faible — c'est l'E2E qui les exerce, et il n'entre pas dans ce calcul.

## 6. Le harnais se vérifie lui-même

Un dispositif de test qui ne peut pas échouer ne prouve rien. C'est arrivé, et c'est écrit ici parce que le défaut était invisible.

En mode PHP-WASM, `scripts/wasm_run.mjs` propageait `result.exitCode`. Cette valeur ne vient pas du CLI mais du gestionnaire de **requêtes** PHP. Mesures sur ce dépôt :

| Script | `exitCode` renvoyé |
|---|---|
| terminé normalement | **1** |
| `exit(0)` / `exit(1)` / `exit(3)` | **0** |
| exception non attrapée (erreur fatale) | **0** |

Autrement dit : dans la sandbox, `run-all-tests.mjs` comptait les échecs comme des succès. Le verdict est maintenant calculé sur des signaux observables (`scripts/wasm_verdict.mjs`) :

- erreur fatale ou de syntaxe PHP → 255 ;
- au moins un marqueur « ❌ » dans la sortie → 1 (les suites de ce dépôt n'emploient ce marqueur que sur un contrôle en échec) ;
- « NON JOUÉ » et aucun « ✅ » → 2 ;
- sinon → 0.

Ce module porte son **autotest de 7 cas**, branché en tête de `run-all-tests.mjs`. Sans lui, une régression du verdict rendrait à nouveau toutes les suites vertes par construction — et c'est exactement le genre de panne qui ne se voit jamais, puisqu'elle produit un résultat rassurant.

Vérification de bout en bout : une suite volontairement sabotée fait sortir `run-all-tests.mjs` en **1** ; restaurée, en **0**.

## 7. Comment on lance tout

```bash
# tout, dans l'ordre, arrêt au premier échec
node scripts/run-all-tests.mjs        # sandbox (PHP-WASM)
php scripts/run-all-tests.php         # machine avec PHP natif

# une suite
node scripts/wasm_run.mjs tests/php/run_tests.php
php tests/php/run_tests.php

# SQL réel : la base de test doit exister une fois pour toutes
php scripts/db_init.php --test-db      # crée minishop_test + droits (sudo une fois)
php tests/php/sql_integration.php      # la remplit, rejoue les 29 scénarios, la supprime

# performance base, sur minishop_perf
sudo bash scripts/capture_perf.sh
```

## 8. Auto-évaluation

**Ce qui tient.** Les 29 règles de gestion sont vérifiées deux fois — sur le moteur et sur MariaDB — et 15 des 29 scénarios SQL contournent volontairement l'application pour prouver que la règle ne dépend pas du code PHP. Les parcours utilisateur sont exercés en HTTP réel, pas en appel de méthode. Les tests de contrat couvrent une zone (liens, CSP, responsive) que la pyramide classique laisse vide.

**Ce qui ne tient pas, et qu'on assume.**

1. **Pas de couverture de lignes.** Assumé pour ne pas ajouter de dépendance ; le substitut (fichiers chargés) est plus faible et le dit.
2. **Les tests de contrat sont statiques.** Une expression régulière sur un gabarit n'est pas un navigateur : un lien construit dynamiquement échappe au contrôle (la suite l'annonce, elle ne le juge pas).
3. **Pas d'accessibilité automatisée.** Contrastes et cibles tactiles sont calculés ; la navigation au lecteur d'écran reste manuelle.
4. **Le harnais de tests a menti pendant un temps.** Les campagnes « tout est vert » conduites en sandbox avant la correction du §6 ne prouvaient rien sur les codes de sortie. Les suites ont ensuite été rejouées, et le harnais se vérifie désormais lui-même à chaque exécution. Le point est signalé ici plutôt que corrigé en silence : un rapport de tests qui a été faux une fois doit le dire.
5. **Pas de test de charge HTTP.** `C-01` simule 20 réservations concurrentes sur la base ; l'application sous charge réelle n'est pas mesurée.
6. **La campagne SQL dépend de l'environnement.** Elle n'est pas exécutable en sandbox ; elle a été jouée sur la machine de l'équipe, et le rapport `tests/sql/rapport_tests_sql.md` en est la trace.

Un défaut trouvé par ces tests et **non corrigé** est documenté plutôt que masqué : l'agrégation `sp_revenue_report` reste en `ALL` (anomalie A-08, 17 ms mesurées, sous le seuil `ENF-01`) — corriger aurait ajouté un index de couverture non justifié par la volumétrie retenue.
