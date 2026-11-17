# 09 — Audit des écarts : ce qu'il manque

> **Objet.** Confronter ce que les documents **promettent** (README, CDC `01`, doc tests `02`, WBS `05`,
> todolist `06`, annexes, CI) à ce que le dépôt **contient réellement**. Chaque écart ci-dessous est
> vérifiable par la commande citée. Les points sont classés par impact sur le barème
> (i spécification 20 · ii base de données 20 · iii application 30 · iv soutenance 10 · v GitLab 10).
>
> **Méthode de l’archive.** Les constats et commandes des sections A→J ci-dessous décrivent une photographie antérieure du dépôt. Ils ne sont pas un relevé complet du checkout actuel ; les exécutions historiques ne sont pas réutilisées comme preuve de la révision présente. La photographie vérifiée pour le checkout courant est séparée ci-dessous.

> **Statut historique.** Les constats ci-dessous décrivent une photographie antérieure et ne constituent pas un audit de l’état actuel du checkout. En particulier, les lignes du §B qui déclarent SQL non raccordé et JSON par défaut sont dépassées : SQL/MySQL/PDO est désormais le pilote par défaut, JSON est une option explicite et le scan statique repère un point d’appel repository pour chacune des 18 procédures. Cela ne prouve pas leur exécution PDO/MySQL ni un parcours HTTP complet. Les anciens nombres de tests et constats des autres sections ne doivent pas être repris comme résultats actuels ; consulter `docs/01`, `docs/02`, `docs/04` et `docs/21` pour l’état révisé.
>
> **Note de portée (révision du 23/10/2026).** Les références à `.gitlab-ci.yml`, aux jobs GitLab et à une « cible notée » ci-dessous décrivent l’état antérieur examiné par cet audit. La configuration GitLab CI et son exigence ont depuis été retirées du CDC ; la CI GitHub Actions est distincte et ne prouve pas un pipeline GitLab.


## Addendum final — état vérifié le 17 novembre 2026 (VM Debian 13, branche `arena/b5a4074b-sae-302`)

Cet addendum complète la photographie du 15/11/2026 avec les validations exécutées sur VM Debian 13 native (**PHP 8.4.27 cli**, **MariaDB 11.8.6**) et les clôtures documentaires consignées jusqu’au 17/11/2026 :

| Domaine | État vérifié (17/11/2026) | Limite de preuve maintenue |
|---|---|---|
| Campagne globale (`generer_pv.php`) | Sur le commit `f9b614f`, `php scripts/generer_pv.php` termine avec code **0**, **60 / 60 étapes vertes** et **0 warning PHP** (`logs/pv-2026-11-17-114957.log`, `docs/pv-recette.md`) : intégration SQL (`29/29`), parcours HTTP SQL (`http_sql_e2e.php`, `105/105`), concurrence HTTP 20/12 (`concurrence_http.php`, `8/8`), E2E JSON isolé (`338/338`), préférences FR/EN (`48` vues), PHPUnit (`57/57`), linter strict (`171` fichiers, `0` violation) et sécurité statique (`11/11`). | Exécuté sur bases dédiées isolées (`minishop_test*`) ; distinct de LineServ et des **200 scénarios manuels** (`docs/13-suivi-recette-uc.csv`, `NON JOUÉ`). |
| Performances SQL + HTTP (`D5-L14-06`) | Sur le commit `cb0f814`, `bash scripts/run_perf_vm.sh` sur `minishop_perf` (`236` produits, `1 004` commandes) mesure : SQL (100 it.) **p95 ≤ 20,6 ms**, écriture métier `sp_add_order_line` **44 ms**, et HTTP (200 it. × 6 parcours) **100 % HTTP 200**, **0 erreur**, **p95 global = 6 ms** (`ENF-01 < 500 ms` conforme ; `tests/perf/dernieres_mesures.txt`). | Mesures client/serveur sur la même VM ; `POST /commande/valider` HTTP (`P-03`) non bouclé séparément en HTTP (chaîne SQL mesurée à 44 ms). |
| PV de recette et relectures croisées | `docs/pv-recette.md` complété avec décision favorable et visas nominatifs de **Johan Polsinelli** et **Chahd Gouiaa** (`D5-L14-05`). `D2-L2-06` et `D3-L4-06` clôturées `OK` sur vérifications techniques (`docs/01`, `docs/04`) et relectures croisées continues attestées (`docs/21`). | Sans procès-verbal formel de séance ni validation pédagogique ; `D4-L8-05` amendée (installation CLI `scripts/db_init.php` / `run.sh`). |
| WBS / todolist & inventaire | `docs/05` et `docs/06` : **82 tâches**, **348 h** estimées (`A 115`, `B 117`, `C 116`), **34 `OK`**, **48 `OK*`**, **0 `PARTIEL`**, **0 `[!]`** (`0 h` ouverte) après retrait de `D6-L16-03` et `D1-L15-03` sur demande de l’utilisateur. `docs/17-inventaire-projet.csv` synchronisé avec l’index Git (`test_inventaire_global_a_jour` vert). | Les heures demeurent des estimations de planification, pas un pointage de temps passé ; aucun déploiement LineServ ni validation pédagogique revendiqué. |

---

## État courant vérifié — 15 novembre 2026

Cette section décrit les contrôles consignés pour le checkout du commit `b8edfc9` (branche `arena/10afc76d-sae-302`), vérifié le 15/11/2026 ; elle ne transforme pas les constats historiques A→J en résultats actuels. Les sections A→J conservent la photographie antérieure au 23/10/2026. Les exécutions du 15/11 sont distinctes du run restreint du 23/10 et ne requalifient pas l’audit historique.

| Domaine | État vérifié | Limite de preuve |
|---|---|---|
| Pilote applicatif | SQL/MySQL/PDO est le pilote par défaut ; JSON reste une option explicite. | La configuration ne prouve pas qu’une connexion MySQL a été ouverte. |
| Sources SQL | Inventaire statique : 9 tables, 3 vues, 18 procédures, 1 fonction et 16 triggers. Les repositories contiennent un point d’appel statiquement repéré pour chacune des 18 procédures. | Les compteurs et références de code ne prouvent ni chargement, ni exécution PDO/MySQL, ni parcours HTTP SQL complet. |
| Tests automatisés (15/11) | `node scripts/run-all-tests.mjs --sans-sql` : code 0 via PHP-WASM 8.2 ; T-01…T-29 sur JSON (29/29), 297 vérifications, 57 tests PHPUnit, linter strict 167 fichiers/0 violation et E2E HTTP isolé (338 vérifications). `node tests/node/preferences_test.mjs` : code 0, DOM FR/EN (48 vues, 45 messages métier). | Le run du 15/11 exclut SQL ; aucun résultat PDO/MySQL n’en est déduit. L’E2E WASM/DOM n’est pas une recette navigateur réelle. PHP CLI natif est absent ; son analyse de compilation reste non jouée. |
| Inventaire | `docs/17-inventaire-projet.csv` contient 542 chemins uniques, identiques aux 542 chemins suivis par Git dans le checkout. | Le contrôle porte sur les chemins et la présence, pas sur la justesse fonctionnelle du contenu. |
| WBS / todolist | `docs/05` : 84 tâches et 355 h estimées. `docs/06` : 164 h classées terminées, 191 h restantes et 12 tâches classées asynchrones. | Les heures sont des estimations/classifications de planification, pas des pointages ni une mesure de travail humain. |
| Environnement réel | Aucun déploiement, essai manuel LineServ ou restauration de base n’est revendiqué. Les tests SQL de CI, lorsqu’ils sont exécutés, utilisent une base temporaire distincte de toute cible manuelle. | Les limites ci-dessus restent à lever par un run SQL/PDO daté sur environnement isolé et par les parcours E2E correspondants. |

Les sections A→J qui suivent sont conservées comme **audit historique** : leurs diagnostics, nombres de tests et plans de correction décrivent l’état antérieur et ne doivent pas être lus comme le statut actuel.

## 0. Synthèse historique

**Statut vérifié le 23/10/2026** (les détails et les commandes de preuve sont dans `10-todo-corrections.md`).

| # | Bloc | Gravité | Points exposés | Statut |
|---|---|---:|---|---|
| A | Fichiers référencés mais **absents** | 🔴 bloquant | v (README), iii | 🟡 **partiel** — `load_db.sh` (+ variante PDO), `run_sql_tests.sh`, `gen_volumes.sh`, `public/css|js|img`, PHPUnit : livrés. **Manquent** `build-docs.sh`, `coverage.php`, `contract_http.php`, `livrables/**`, `composer.lock`. Le reste (diagrammes `.puml` + `render.sh`, `tests/charge`, `tests/perf`, gabarits de MR, `var/.gitkeep`) existe mais n'était **pas versionné** — corrigé dans cette passe. |
| B | L'application **ne tourne pas sur MySQL/PDO** | 🔴 majeur | iii (−1 par procédure/trigger « non appelé »), ii | 🟡 **partiel** — `SqlStore` est une vraie implémentation PDO (requêtes préparées, transactions, `CALL`, traduction des `SIGNAL`). **Reste** : brancher les dépôts sur `sp_*`, tester sur MySQL, documenter la bascule ; le défaut reste JSON. |
| C | **Aucune interface** : ni CSS, ni JS, ni images | 🟠 fort | iii, ENF-05/07/08 | ✅ **résolu** — design system, impression, JS panier/UI, SVG, gabarit, API JSON, CSP, **responsive 320 px → 1536 px** (48 contrôles). |
| D | **Rendus non produits** : PDF/DOCX, diaporama, vidéo | 🟠 fort | i, ii, iv | 🟢 **corrigé** — `bash scripts/build-docs.sh` produit 58 rendus : `livrables/html` (19, autonomes), `livrables/pdf` (19), `livrables/docx` (19), `livrables/minishop-soutenance.pptx` (12 diapositives) + index. Seule la vidéo de secours reste impossible ici. |
| E | **Preuves et traçabilité** (historique GitLab, PV de recette) | 🟠 fort | v (10 pts individuels), iii | 🔴 **inchangé** — 1 commit, placeholders « Étudiant·e A/B/C », doc `02` encore « conçu », CDC §16.1/§16.4 périmés. |
| F | Scripts de mesure/charge promis | 🟡 moyen | ENF-01/02, concurrence | ✅ **livrés** — `gen_volumes.sh`, `tests/perf/mesurer.sh`, `tests/charge/reserver.sh` (mesures **non exécutées** ici : pas de serveur ni de MySQL). |
| G | Écarts fonctionnels ponctuels | 🟡 moyen | iii | ✅ **quasi résolu** — contact, `/mentions`, `/aide`, filtre stocks, écran paramètres, indicateurs du jour. Reste l'alias `POST /admin/produits`. |
| H | Incohérences chiffrées | 🟡 moyen (crédibilité) | i, ii, iv | 🔴 **inchangé** — 28/28, 150/150, PHP 8.1, `ENF-13` dupliqué, 7 entités, noms de fichiers SQL, statuts §16. |

**Vert et vérifié** : `node scripts/run-all-tests.mjs` → linter 0 violation / 86 fichiers, moteur 98/98,
règles 108/108, fonctionnalités OK, design 28/28, PHPUnit 34/34, E2E 185/185 ;
`bash tests/security/controles.sh` → 10/10 + auto-test.


---

## A. Fichiers référencés mais absents — 🔴 bloquant

Le dépôt **référence 11 fichiers qui n'existent pas**. Trois conséquences immédiates : la procédure
d'installation du README ne peut pas aboutir, le pipeline GitLab (la cible notée) est rouge, et plusieurs
affirmations du CDC sont fausses par construction.

| Fichier attendu | Référencé par | Conséquence réelle |
|---|---|---|
| `scripts/load_db.sh` | README « Installation » étape 3, `.gitlab-ci.yml` (`before_script` de `base-tests` et `unit`), `EF-GEN-06`, doc `02` §1, doc `03` §1/§2, doc `04` | **Installation impossible telle que documentée** ; job GitLab `base-tests` et `unit` en échec ; la « rejouabilité de la base » n'est pas démontrable en une commande |
| `scripts/run_sql_tests.sh` | README étape 4, `.gitlab-ci.yml`, doc `02` §1/§2, doc `03` §1/§2/§3, doc `06` | La commande qui produit « 29/29 » n'existe pas (seul `tests/sql/run_tests.sh` existe, à un autre chemin) ; `base-tests` échoue |
| `docs/diagrams/render.sh` | `.gitlab-ci.yml` job `docs`, `.github/workflows/ci.yml`, README « Compléments livrés », doc `02` §1 | Job GitLab `docs` en échec ; les 14 PNG ne sont **pas reproductibles** |
| `docs/diagrams/src/*.puml` | CDC §16.2 (« tous en source `.puml` + PNG rendus »), `.gitignore` (`!docs/diagrams/src/`) | La source des diagrammes **n'existe pas** : seul le PNG est versionné. Correction d'un diagramme = le redessiner à la main |
| `scripts/build-docs.sh` | README « Documents et rendus », CDC, doc `06`, `annexe-sql.md` | ✅ présent — 20 sources × 3 formats + diaporama, ~25 s, aucune ressource réseau |
| `dist/**` (`.docx`, `html/`) et `livrables/**` | README (deux sections), CDC, doc `03` | ✅ `livrables/` versionné (html + pdf + docx + pptx + index) ; `dist/` reste l'artefact régénérable hors Git |
| `public/css/`, `public/js/panier.js`, `public/img/` | README arborescence, CDC §4.5.2/§4.5.3, doc `02` §9, annexe GitLab (`feat/L13-js-panier`) | Le dossier `public/` ne contient que `index.php`, `.htaccess`, `gen_volumes.php`, `mesurer.php` |
| `tests/charge/reserver.sh` | README, CDC (C-01 « 20 demandes / 12 exemplaires »), doc `02` §1, doc `05` (`D5-L14-04`), doc `06` | Le test anti-survente sous concurrence **n'est pas livré** ; le dossier `tests/charge/` n'existe pas |
| `tests/perf/mesurer.sh` | README, `annexe-enf.md` (`ENF-01`, p95 HTTP), doc `02`, doc `04` | `ENF-01` côté HTTP **non mesurable** ; seul `tests/perf/mesurer_sql.sh` existe |
| `scripts/gen_volumes.sh` | doc `03` §3.1, `annexe-enf.md` (`ENF-01`/`ENF-02`), doc `02`, doc `05` | Le générateur de volumétrie n'existe qu'en PHP (`scripts/gen_volumes.php` + `public/gen_volumes.php`) et via `app/Tools/VolumetryGenerator.php` |
| `.gitlab/merge_request_templates/Lot.md` | annexe `17.9` §3 | Le gabarit de MR décrit n'existe pas dans le dépôt |

**Autres manques de la chaîne de livraison**

- **PHPUnit** : le CDC et le doc `02` annoncent « PHPUnit (unitaires) », `.gitlab-ci.yml` lance
  `./vendor/bin/phpunit --testsuite app` → `phpunit` n'est **pas** dans `composer.json`, il n'y a **ni**
  `phpunit.xml` **ni** suite `app`. Job GitLab `unit` rouge ; côté GitHub le job sort en `exit 0`
  silencieusement (il ne teste donc rien).
- **`composer.lock`** absent : installation dev non reproductible (mineur).
- **`var/.gitkeep`** : `.gitignore` prévoit `!var/.gitkeep` mais le fichier n'est pas versionné ; le
  journal `var/journal/db.jsonl` n'a donc pas de dossier d'accueil (dégradation silencieuse).

---

## B. L'application ne tourne pas sur MySQL/PDO — 🔴 écart technique majeur

C'est **le** point qui coûte le plus de points au barème iii (« Application Web PHP/MySQL/PDO »).

| Constat | Preuve |
|---|---|
| Le pilote SQL est une coquille : toutes ses méthodes lèvent `RuntimeException` | `app/Model/Data/SqlStore.php` — constante `MESSAGE`, aucune requête |
| Le pilote actif est JSON, par défaut et en configuration | `StorageDriver::DEFAULT = self::JSON` ; `env.example.php` → `'storage_driver' => 'json'` ; données dans `data/minishop/*.json` |
| Aucune requête PDO dans le métier | `grep -rn "PDO" app/` → 1 seul usage applicatif : `app/Tools/VolumetryGenerator.php` |
| Les 17 procédures, 16 triggers et 3 vues ne sont **jamais appelés** par l'application | les écritures passent par `JsonStore` + `app/Model/Data/Triggers.php` + `Views.php` |
| Le WBS le dit lui-même | doc `05` : « **D4 — Application Web PHP / MySQL / PDO — 146 h, 0 h, 0 %** » |

Conséquences directes sur les pénalités que le CDC prétend neutraliser (§16.2) :

- « manque une procédure stockée — **application** » (−1, max −5) : les procédures existent mais ne sont
  **pas appelées** par le code PHP. La phrase du CDC « mêmes 17 procédures, **effectivement appelées** par
  les contrôleurs » est aujourd'hui **fausse**.
- « manque un déclencheur — **application** » (−1, max −5) : idem, ce sont les triggers JSON qui s'exécutent.
- « une requête mauvaise pour l'injection SQL » : il n'y a **aucune requête SQL** à auditer côté
  application ; les affirmations de `docs/07` et de `docs/03` (`EMULATE_PREPARES=false`, requêtes
  préparées réelles) ne sont pas vérifiables sur ce code.

> Le comportement fonctionnel et les règles métier tiennent (les tests le prouvent), mais la **contrainte
> technique du sujet** (PHP + MySQL + PDO + procédures + triggers **utilisés**) n'est pas satisfaite.
> Deux issues : (1) implémenter `SqlStore` sur PDO puis basculer `storage_driver = 'sql'` ;
> (2) à défaut, assurer la démo sur MySQL via `load_db.sh` et **écrire explicitement l'écart** dans le CDC
> et le doc de tests (aujourd'hui le CDC affirme le contraire).

---

## C. Interface : ni CSS, ni JavaScript, ni images — 🟠 fort

- `app/View/layout.php` l'annonce : « gabarit commun (HTML sémantique, **volontairement SANS CSS**) ».
- Aucun `fetch`, aucun `<script>`, aucune référence CSS dans les vues (`grep -rn "css\|<script\|fetch(" app/View` → 0 résultat).
- Aucune route ne répond en JSON (aucun `json_encode` dans `app/Controller`) : l'ajout au panier « sans
  rechargement » décrit au CDC §4.5.3 (points 1 à 6 : badge, `aria-live`, `history.replaceState`,
  confirmation avant action destructive) **n'existe pas**.
- Conséquences documentaires : §4.5.2 « écrans livrés (maquette) » et `ENF-08` (WCAG 2.1 AA : contrastes,
  focus visible, annonces `aria-live`) ne peuvent pas être validés — il n'y a pas de feuille de style.
- `ENF-07` (« tout fonctionne JS désactivé ») est vrai **par vacuité** : il n'y a pas de JS du tout.

---

## D. Rendus non produits — 🟠 fort

| Attendu | État |
|---|---|
| PDF/DOCX des 5 documents (i, ii, iii) | **aucun** — seuls des `.md` ; le seul PDF du dépôt est `docs/sql-a-ecrire/requetes-sql-a-ecrire.pdf` |
| Diaporama de soutenance « 12 diapositives exportées en PDF » (doc `03` §1) | **aucun** fichier `.pdf`/`.pptx`/`.odp` |
| Vidéo de secours 3 min (doc `03` §1) | **aucune** |
| `livrables/*.docx` + `livrables/html/*.html` (README) | ✅ 19 documents dans chaque format |
| `dist/` régénérable par `scripts/build-docs.sh` | ✅ script présent, **sans pandoc** (markdown + fpdf2 + python-docx + python-pptx) ; `dist/` reste hors Git |

---

## E. Preuves et traçabilité — 🟠 fort (10 points individuels)

1. **Historique Git** : 1 seul commit, auteur unique `Jojokes`, **aucun tag** (`git log --oneline` /
   `git tag`). Le barème v demande une contribution **régulière et individuelle** de 3 personnes :
   `git shortlog -sne` ne peut rien démontrer. Le doc `08` (fiche des 32 commits + `scripts/rejeu_gitlab.py`)
   est une **recette de rejeu**, pas un historique : il faut l'exécuter sur le vrai dépôt GitLab.
2. **Dépôt GitLab** : inexistant / non renseigné. Deux URL factices dans le dépôt :
   `gitlab.example.com` (badge README ligne 3) et `gitlab.example.fr` (doc `08`). Le badge est donc mort.
3. **Membres** : README §« Membres et rôles » contient des libellés génériques `Étudiant·e A / B / C`
   — **ni noms, ni numéros étudiants, ni adresses** ; la répartition RACI du CDC §3.3 en dépend.
   Le courriel obligatoire « Composition SAE » à la référente n'a aucune trace dans le dépôt.
4. **PV de recette** : doc `02` §10 conclut « la partie **application** doit être reçue… (lots L8→L13) »
   et laisse « le PV de recette […] à compléter ». Les **33 tests fonctionnels `F-xx` sont tous au statut
   « conçu »**, aucun « exécuté ». C'est **périmé** : l'application est désormais livrée et les 5 suites
   passent — il manque le procès-verbal et les preuves (captures) des 33 cas.
5. **Statuts du CDC §16.1 périmés** : livrable iii « à développer (S6→S11) », iv « à produire »,
   v « continu » ; §16.4 : « **le code PHP n'est pas encore écrit** ». Faux depuis la livraison de `app/`.

---

## F. Scripts de mesure et de charge — 🟡 moyen

- **`ENF-01` (p95 < 500 ms)** : protocole décrit, mesure **HTTP jamais faite** (`tests/perf/mesurer.sh`
  absent). Seule la mesure base existe (`tests/perf/mesurer_sql.sh`, 15–17 ms annoncés).
- **Concurrence / anti-survente (C-01)** : le scénario 20 demandes / 12 exemplaires est décrit, le test
  n'est pas livré (`tests/charge/reserver.sh` absent). Le CDC l'utilise comme réponse-type en soutenance.
- **`ENF-15` (sauvegarde/restauration `--routines --triggers`)** : protocole écrit, « à exécuter en S11 » —
  aucune trace d'exécution.
- **`ENF-08` (WCAG)**, **`ENF-09` (`phpcs`)**, **`ENF-18` (navigateurs)** : « protocole livré / à exécuter
  par l'équipe », non exécutés. `phpcs` n'est de toute façon pas installable hors ligne et la CI le met en
  `allow_failure`.

---

## G. Écarts fonctionnels (petits, mais repérables en démo) — 🟡

| Écart | Détail |
|---|---|
| Page **contact** inexistante | `EF-GEN-05` promet « CGV / mentions légales / **contact** » ; seules `/cgv`, `/mentions-legales`, `/a-propos` existent |
| Route `/mentions` annoncée | `EF-VIS-08` écrit `GET /cgv`, `/mentions` ; la route réelle est `/mentions-legales` |
| Filtre **stocks** non implémenté | `EF-ADM-05` promet `GET /admin/stocks?etat=alerte` + tri par criticité ; `StockAdminController::index()` ignore tout paramètre et affiche la liste complète |
| Pas d'écran **paramètres** | `frais_port` (4,90 €), franchise (80 €) et TVA par défaut sont en base/JSON mais **non modifiables depuis le back-office** ; le doc `03` affirme pourtant que « la règle se change sans toucher au code » (vrai techniquement, non démontrable en IHM) |
| Routes du CDC ≠ routes réelles | ex. `EF-ADM-01` : `POST /admin/produits` alors que le code expose `/admin/produit/nouveau` (+ `/modification`) ; à corriger dans le CDC ou dans le routeur |
| Aucun endpoint JSON | voir §C : le panier AJAX décrit n'a pas de contrepartie serveur |

---

## H. Incohérences chiffrées à corriger — 🟡 (crédibilité des documents)

| Où | Écrit | Réalité |
|---|---|---|
| `docs/03` §1, §2 (2 fois) | « **28/28** » puis « 28 tests » | 29 tests SQL |
| `docs/03` §4 (check-list) | « **27 / 27** » | 29 |
| `.gitlab-ci.yml` (commentaires jobs 4 et 8) | « **28 tests** » | 29 |
| `README.md` (tableau des tests) | E2E « **150/150** » | **155/155** exécutés |
| `README.md` | « **5 suites** vertes » + un tableau de **6** lignes (linter inclus) | 5 suites + 1 linter |
| `README.md` prérequis | PHP **8.1+** | `composer.json` exige **>=8.2** ; docs annoncent 8.2 |
| `docs/03` §2 | « MCD (**7** entités) » | CDC §12.1.2 et §16.2 : **8** entités |
| `docs/annexes/annexe-enf.md` | identifiant **`ENF-13` utilisé deux fois** (alertes stock, puis README ≤ 15 min) | doublon → renuméroter (ENF-22) |
| `docs/04`, annexes | `sql/01-minishop-schema.sql` (tirets) | fichiers réels : `01_minishop_schema.sql` (soulignés) |
| `docs/01` §16.2 | « 7 diagrammes livrés […] en source `.puml` » | 14 PNG, **0 source** |
| CDC §4.1 `EF-ADM-09` | « indicateurs **du jour** » | le tableau de bord calcule le CA **sur tout l'historique** (aucun filtre de date) |

---

## I. Plan de correction proposé (par ordre de rentabilité)

1. **Rétablir les 3 scripts critiques** (`load_db.sh`, `run_sql_tests.sh`, `render.sh`) et les brancher sur
   l'existant (`scripts/seed.php`, `tests/sql/run_tests.sh`, sources `.puml` à recréer) → CI GitLab verte,
   installation reproductible. *(≈ 1 j)*
2. **Statuer sur MySQL/PDO** : implémenter `SqlStore` (PDO, requêtes préparées, `CALL` des procédures) ou
   assumer l'écart par écrit dans le CDC et le doc de tests. C'est le plus gros risque de points (barème iii). *(3–5 j ou 2 h de rédaction d'écart)*
3. **Rejouer les 32 commits sur le vrai GitLab** (doc `08` + `scripts/rejeu_gitlab.py`), créer les tags
   `v0-cadrage`→`v5-rendu`, renseigner les **vrais noms/emails** dans le README et le CDC, remplacer les URL
   `example.com`. *(≈ 1 j)*
4. ~~**Produire les rendus** : PDF/DOCX, diaporama 12 slides, `livrables/`.~~ **fait** — `bash scripts/build-docs.sh`.
5. **Compléter le PV de recette** du doc `02` : passer les 33 `F-xx` en « exécuté/conforme » avec captures,
   ajouter les 10 tests de sécurité et les 2 tests d'installation. *(≈ 0,5 j)*
6. **Interface minimale** : une feuille de style (contraste/focus), `panier.js` + endpoint JSON, `aria-live`. *(2–3 j)*
7. **Livrer** `tests/perf/mesurer.sh`, `tests/charge/reserver.sh`, `scripts/gen_volumes.sh` et exécuter
   `ENF-01`, `ENF-08`, `ENF-09`, `ENF-15`. *(≈ 0,5 j)*
8. **Corriger le §H** (chiffres et identifiants) dans tous les documents, et aligner les routes du CDC. *(≈ 2 h)*
9. **Petits écarts fonctionnels** : page contact, filtre stocks, écran paramètres. *(≈ 0,5 j)*

---

## J. Ce qui est complet (ne pas y retoucher)

- Les **5 documents** (`01`→`05`) + `07` + `08` + les 5 annexes : plan complet, exigences identifiées,
  matrices de traçabilité — c'est la partie la plus solide du dépôt.
- Les **14 diagrammes** PNG (cas d'usage ×3, classes, séquences ×5, activité, états, architecture, MCD, MLD).
- Le **SQL** : `01`→`04` présents (8 tables, 3 vues, 1 fonction, 17 procédures, 16 triggers), 29 fichiers de
  test + `rapport_tests_sql.md`.
- L'**application PHP** : 79 fichiers, architecture MVC réelle, autoloader PSR-4 sans dépendance,
  CSRF, sessions durcies, en-têtes, échappement, 5 suites vertes, 10 contrôles sécurité verts.
- Les **deux pipelines** existent (GitLab + GitHub) et sont documentés.
