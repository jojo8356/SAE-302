# 22 — Audit complet de cohérence et de mise à jour des fichiers du dépôt

**Date de l’audit :** 17 novembre 2026
**Branche auditée :** `arena/b5a4074b-sae-302` (commit de référence analysé : `690da2c`)
**Périmètre inspecté :** ensemble des **551 fichiers versionnés** (`git ls-files`) : documentation (`docs/`, `README.md`, `ARCHITECTURE.md`, `STACK.md`), livrables rendus (`livrables/`, `artefacts/`), outillage (`.tools/`, `scripts/`), code applicatif (`app/`, `public/`, `sql/`), tests (`tests/`) et archives (`uploads/`).

---

## 1. Résumé exécutif

L’audit complet du dépôt montre que **le socle technique (code PHP, SQL, tests, sécurité, scripts VM) est sain et cohérent**, mais qu’**une partie significative de la documentation périphérique, des annexes, du diaporama et de deux artefacts HTML n’a pas encore été alignée** avec les derniers jalons du 17 novembre 2026 :
- la campagne complète sur VM Debian 13 (`f9b614f`, **60/60 étapes**, intégration SQL **29/29**, parcours HTTP SQL **105/105**, concurrence HTTP **8/8**, `docs/pv-recette.md` avec visas nominatifs) ;
- la campagne de performances sur VM Debian 13 (`cb0f814`, SQL p95 ≤ **20,6 ms**, HTTP p95 global = **6 ms**, `tests/perf/dernieres_mesures.txt`) ;
- le périmètre WBS final à **82 tâches / 348 h** (**34 `OK`**, **48 `OK*`**, **0 `PARTIEL`**, **0 `[!]`**) après retrait de `D6-L16-03` et `D1-L15-03` et clôture de `D3-L4-06` et `D5-L14-06`.

| Catégorie d’état | Nombre de constats | Signification |
|---|---:|---|
| **Priorité 1 — Contradictions factuelles ou artefacts générés périmés** | **9 groupes** | Affirmations de type « aucun rejeu PDO/MySQL », anciens totaux (`84 tâches`), `ENF` non actualisées, `artefacts/*.html` antérieurs à leur `.md` source, diaporama PPTX figé au 15/11. |
| **Priorité 2 — Désynchronisations partielles / mentions mixtes** | **10 groupes** | Documents déjà actualisés par un addendum du 17/11 mais conservant des chapôs, tableaux ou libellés au 15/11 (`docs/02`, `docs/05`, `docs/06`, `docs/19`, `docs/21`), ou absents de `livrables/` (`docs/pv-recette.md`). |
| **Priorité 3 — Archives figées ou limites assumées (à conserver ou baliser)** | **14 documents / dossiers** | Documents historiques datés (`docs/07`, `docs/10`, `tests/sql/rapport_tests_sql.md`), fiches manuelles volontairement `NON JOUÉ` (`docs/13-*`), captures/uploads historiques (`uploads/01_minishop_schema.txt`). |
| **Code, SQL, scripts, tests et livrables à jour** | **Majorité du dépôt** | Code `app/`, `public/`, `sql/`, `tests/`, `.github/workflows/ci.yml`, `docs/16-audit-code-mort.md`, `docs/17-inventaire-projet.csv`, `livrables/` pour `01, 04, 06, 09, 17, 19, 21`. |

---

## 2. Méthode de contrôle utilisée

Quatre vérifications automatisées ont été exécutées sur la révision `690da2c` :
1. **Inventaire Git complet** (`git ls-files` : **551 fichiers** ; `test_inventaire_global_a_jour` conforme avec `docs/17-inventaire-projet.csv`).
2. **Comparaison d’horodatage Git** entre chaque source Markdown (`docs/*.md`, `docs/annexes/*.md`) et ses rendus (`artefacts/*.html`, `livrables/html/*.html`, `livrables/pdf/*.pdf`, `livrables/docx/*.docx`, `livrables/minishop-soutenance.pptx`).
3. **Balayage lexical intégral** sur les fichiers texte suivis : anciens effectifs (`83/84/85 tâches`, `350/355/359 h`), tâches retirées (`D6-L16-03`, `D1-L15-03`, `v1.0-rendu`), mentions obsolètes d’installation (`public/install.php`), affirmations « aucun rejeu » / « non exécuté », références figées au `15/11/2026` (`b8edfc9`) ou au `28/10/2026`.
4. **Contrôles de santé structurels et sécurité** : `unittest` (**42** tests : **22 OK**, **20 skipped** faute de PHP CLI natif dans le conteneur), `verifier_fiches_uc.py` (**46/46 OK**), `tests/security/controles.sh` (**11/11 OK**).

---

## 3. Priorité 1 — Fichiers contenant des informations périmées ou incohérentes

### 3.1 Artefacts HTML générés plus anciens que leur source Markdown

| Fichier périmé | Dernier commit artefact | Dernier commit source | Écart constaté |
|---|---|---|---|
| `artefacts/01-cahier-des-charges-MiniShop.html` | `0f182cd` (17/11/2026) | `docs/01-cahier-des-charges-MiniShop.md` @ `690da2c` | Ne contient ni le retrait de `D1-L15-03` (`7d30279`) ni la mise à jour §12.6 (`690da2c`). |
| `artefacts/02-document-tests-validation.html` | `8364cd7` (17/11/2026) | `docs/02-document-tests-validation.md` @ `95d7b02` | Ne contient pas les résultats de performances `D5-L14-06` (`tests/perf/dernieres_mesures.txt`). |

### 3.2 Documents principaux non actualisés au 17/11/2026

| Fichier | Lignes / sections concernées | Contenu obsolète constaté | Mise à jour attendue |
|---|---|---|---|
| `docs/01-cahier-des-charges-MiniShop.md` | L511, L708 (`CT-02`), L737, L1071, L1376, L1641 | Affirme encore « aucun rejeu PDO/MySQL n’est revendiqué » / « reste à valider » alors que §12.6 (L1430) acte désormais la campagne VM du 17/11 (`60/60`, SQL `29/29`, HTTP SQL `105/105`, concurrence `8/8`). | Harmoniser §6, §7.2, §8.1, §12 intro, §12.5 et §14.1 avec §12.6 (en conservant la distinction entre coupure du 23/10, campagne VM du 17/11 et `200` scénarios manuels `NON JOUÉ`). |
| `docs/04-conception-bd-et-sql.md` et `.tools/build_doc04.py` | `docs/04` L340, L460 ; `build_doc04.py` L253 | La section B.3 / C.1 reproduit encore le paragraphe du `15/11/2026` (`b8edfc9`) affirmant « Aucun rejeu SQL de cette révision n’est revendiqué ici » juste au-dessus du nouvel addendum `17/11/2026`. | Fusionner ou préciser explicitement l’historique `15/11` vs `17/11` dans `.tools/build_doc04.py` puis régénérer `docs/04` et `artefacts/04`. |
| `docs/08-fiche-suivi-commits.md` et `scripts/rejeu_gitlab.py` | `docs/08` L9 ; `rejeu_gitlab.py` L474 | Indique « la WBS courante compte **84 tâches** » (elle en compte **82** pour **348 h** depuis `7d30279`) et référence encore `npm run test:all` / `.gitlab-ci.yml` en §7–§8 sans mention de `arena/b5a4074b-sae-302`. | Actualiser la note de portée (« **82 tâches / 348 h** ») dans `scripts/rejeu_gitlab.py` et `docs/08-fiche-suivi-commits.md` (et ses exports `livrables/`). |
| `docs/11-strategie-tests.md` | L7, L20, L40, L66, L88, L133 | Dernier addendum bloqué au `15/11/2026` (`b8edfc9`, `--sans-sql`) : prétend encore que PHP CLI natif, PDO/MySQL et les parcours HTTP SQL n’ont pas été rejoués. | Ajouter un **addendum du 17/11/2026** citant `docs/pv-recette.md` (`60/60`, SQL `29/29`, HTTP SQL `105/105`, concurrence `8/8`) et `tests/perf/dernieres_mesures.txt` (`p95 = 6 ms`). |
| `docs/12-diaporama-soutenance.md` et `livrables/minishop-soutenance.pptx` | Diapos 5, 6, 8, 11, 12 (`8f8fff6`, 16/11/2026) | Affirme dans 5 diapositives que « l’exécution PDO/MySQL n’a pas été rejouée dans cette révision » et « SQL/PDO reste non joué » ; ignore `docs/pv-recette.md` (`60/60`) et les mesures de performance (`p95 = 6 ms`). | Mettre à jour les diapositives 5, 6, 8, 11 et 12 dans `docs/12-diaporama-soutenance.md`, puis régénérer `livrables/minishop-soutenance.pptx` et `livrables/{html,pdf,docx}/12-diaporama-soutenance.*`. |
| `docs/annexes/annexe-enf.md` | L7 (`ENF-01`), L10 (`ENF-04`), L16 (`ENF-10`), L17 (`ENF-11`), L19 (`ENF-13`), L20 (`ENF-14`), L22 (`ENF-16`) | `ENF-01` indique « mesure cible non revendiquée dans cette révision » alors que `D5-L14-06` est validée sur `minishop_perf` (`tests/perf/dernieres_mesures.txt`). Plusieurs `ENF` disent encore « aucun résultat SQL actuel revendiqué ». | Actualiser la colonne **Statut** de `ENF-01` (conforme VM `cb0f814`, SQL p95 ≤ `20,6 ms`, HTTP p95 = `6 ms`), `ENF-04`, `ENF-10` (MariaDB 11.8.6 validée ; MySQL 8 à rejouer en CI), `ENF-11`, `ENF-13`, `ENF-14`, `ENF-16`. |
| `docs/annexes/annexe-php-patterns.md` | L4–L6 | Mentionne « 26/26 tests conformes » et « Ils ne sont pas encore exécutés ici : `php -l` et les tests PHPUnit sont à courir… ». | Préciser qu’il s’agit d’une note de cadrage historique antérieure à l’implémentation, les tests SQL (`29/29`), PHPUnit (`57/57`) et le linter (`171` fichiers) étant désormais exécutés. |

---

## 4. Priorité 2 — Fichiers partiellement désynchronisés ou incomplets

| Fichier | Constat exact | Action recommandée |
|---|---|---|
| `docs/02-document-tests-validation.md` | §8 final intègre bien la campagne VM du `17/11/2026` et `D5-L14-06`, mais le chapô (L16, L36) et le tableau §8 (L243) s’arrêtent à l’addendum du `15/11/2026` (`--sans-sql`). | Mettre à jour L16, L36 et la ligne du tableau L243 pour renvoyer aussi vers les validations VM du `17/11/2026` (`docs/pv-recette.md` et `tests/perf/dernieres_mesures.txt`). |
| `docs/03-support-soutenance.md` | Trame chronométrée cohérente (L1–L73), mais elle ne cite ni `docs/pv-recette.md` (`60/60`) ni `tests/perf/dernieres_mesures.txt` (`p95 = 6 ms`), et L67 mentionne encore l’ancien rapport du `27/10`. | Ajouter ces deux preuves dans §1 (matériel) et §4 (points de vigilance), en les distinguant des `200` entrées manuelles `NON JOUÉ`. |
| `docs/05-wbs-projet.md` et `.tools/build_wbs.py` | Totaux à jour (**82 tâches / 348 h**), mais les libellés de `D3-L5-01` (L204) et `D5-L14-02` (L314) disent encore « aucun rejeu MySQL/MariaDB » / « date future 27/10 ». | Aligner les critères/notes de `D3-L5-01` et `D5-L14-02` dans `.tools/build_wbs.py` avec les preuves déjà actées dans `docs/06-todolist.md` et `docs/19`, puis régénérer `docs/05`. |
| `docs/06-todolist.md` et `.tools/build_todolist.py` | Bilan des statuts exact (**34 `OK`**, **48 `OK*`**, **0 `PARTIEL`**, **0 `[!]`**), mais L4 et L361 datent l’addendum de `docs/19` au `15/11/2026` et `D3-L7-05` (L66, L222) porte encore « aucun rejeu SQL du checkout courant n’est prouvé ». | Actualiser la mention de date (`17/11/2026`) aux L4/L361 et le libellé de `D3-L7-05` dans `.tools/build_todolist.py`, puis régénérer `docs/06`. |
| `docs/19-audit-cahier-des-charges-todolist.md` | Résumé et tableau détaillé à jour (**82 tâches**, `0 [!]`), mais L56 garde un intertitre `Addendum — contrôles postérieurs du 15/11/2026` sans intertitre symétrique pour les contrôles du `16–17/11/2026` (qui figurent uniquement en tête L7). | Ajouter un sous-titre explicite du `17/11/2026` au §2 et nettoyer les formulations du bilan final qui mélangent le `15/11` et le `17/11`. |
| `docs/21-synthese-retours-audit.md` | La ligne `D3-L4-06` est à jour au `17/11/2026`, mais les lignes 14–15 du tableau récapitulatif répètent encore « aucun rejeu PDO/MySQL » et « run local du 15/11 non-SQL ». | Mettre à jour les lignes 14–15 du tableau pour refléter la campagne VM `17/11/2026` (`f9b614f` / `cb0f814`). |
| `docs/09-audit-ecarts.md` et `docs/17-audit-global-projet.md` | Addendums finaux du `17/11/2026` présents en tête, mais les sections historiques du `15/11/2026` (`84` tâches, `355 h`, `--sans-sql`) restent immédiatement dessous sans mention « historique archivé ». | Préfixer les sections `15 novembre 2026` d’une étiquette claire *(photographie historique antérieure à l’addendum du 17/11/2026)* pour éviter toute confusion de lecture. |
| `docs/pv-recette.md` | Document à jour pour la campagne `f9b614f` (`60/60`), mais **non exporté** dans `livrables/{html,pdf,docx}/` et non référencé dans `README.md` ni `livrables/README.md`. | Étendre `scripts/build-docs.sh` (ou exporter explicitement `docs/pv-recette.md`) et ajouter son lien dans `README.md`. |
| `README.md` et `ARCHITECTURE.md` | Techniquement exacts sur l’installation (`./run.sh --db`) et l’architecture, mais ne mentionnent ni `docs/pv-recette.md`, ni `scripts/run_perf_vm.sh`, ni `tests/perf/dernieres_mesures.txt`. | Ajouter ces 3 entrées dans les tableaux de documentation et tests de `README.md` (sans modifier `**Cadre**` requis par les tests E2E). |
| `scripts/build-docs.sh` | Commentaire/bilan daté (ligne 9 évoque `20 sources` / `58 rendus` dans `docs/10`), et ignore `docs/pv-recette.md` (car ne filtre que `docs/[0-9]*.md` et `docs/DA.md`). | Ajouter `docs/pv-recette.md` aux sources gérées par `scripts/build-docs.sh` et réexécuter le build complet. |

---

## 5. Priorité 3 — Archives figées, documents juridiques et limites volontaires

Ces fichiers contiennent des dates ou des états antérieurs par construction : ils **ne doivent pas être réécrits comme des journaux du 17/11**, mais rester clairement identifiés comme historiques ou hors périmètre :

- `docs/07-note-cybersecurite.md` : analyse de sécurité figée au `28/09/2026` / coupure `23/10/2026` (contient d’anciennes références aux outils `public/gen_volumes.php` et `mesurer.php`, retirés de `public/` depuis). À annoter éventuellement d’un encart renvoyant aux `11/11` contrôles de `tests/security/controles.sh` et à l’addendum `17/11`.
- `docs/10-todo-corrections.md` : archive historique datée du `28/10/2026` (conserve `8` tables / `17` procédures / `SQL absent` / `PHPUnit absent` dans son corps d’origine), déjà complétée en queue pour `D5-L14-06`.
- `docs/12-information-juridique.md` : liste de réserves légales (`[!]`) volontairement maintenues pour toute mise en production commerciale (hors maquette scolaire).
- `docs/13-fiches-recette-uc.md` et `docs/13-suivi-recette-uc.csv` : **164 scénarios + 36 couples = 200 lignes**, volontairement maintenues à **`NON JOUÉ`** tant qu’une campagne manuelle navigateur n’est pas exécutée par des testeurs (`verifier_fiches_uc.py` **46/46 OK**).
- `docs/14-guide-lineserv.md` et `docs/15-email-recette-lineserv.md` : procédures de recette manuelle LineServ (non exécutée, hors périmètre automatique).
- `docs/16-audit-code-mort.md` : **`0` alerte active** et **`0` fichier de zéro octet**, mais les colonnes de taille en octets (§2) conservent la photographie du commit `870dda5`.
- `docs/18-politique-conservation.md`, `docs/20-charte-gitlab-revues-croisees.md`, `docs/DA.md`, `docs/annexes/annexe-cas-usage.md`, `docs/annexes/annexe-gitlab.md`, `docs/annexes/annexe-sql.md` : documents de cadrage/annexes cohérents dans leur rôle (avec mention explicite de photographie antérieure dans `annexe-sql.md` et `annexe-gitlab.md`).
- `docs/sql-a-ecrire/requetes-sql-a-ecrire.md` (L832, L836, L897) et `uploads/01_minishop_schema.txt` (L15) : mentionnent encore `public/install.php` (remplacé par `scripts/db_init.php` / `./run.sh --db`).
- `tests/sql/rapport_tests_sql.md` : rapport daté du `27/10/2026` antérieur aux campagnes VM des `16–17/11/2026` (`docs/pv-recette.md`).

---

## 6. Périmètres vérifiés à jour

Les sous-ensembles suivants sont synchronisés et ne présentent aucune dérive bloquante :
- **Code applicatif et base de données (`app/`, `public/`, `sql/`, `migrations/`)** : schéma (`9` tables, `3` vues, `1` fonction `fn_param`, `18` procédures, `16` déclencheurs), pilote SQL/PDO par défaut, zéro secret littéral, `11/11` contrôles de sécurité conformes.
- **Chaîne de génération WBS / todolist / doc04 (`.tools/`)** : paramètres de totaux synchronisés (`82` tâches, `348 h`, `A: 115 / B: 117 / C: 116`, `34 OK`, `48 OK*`, `0 PARTIEL`, `0 [!]`).
- **Livrables générés déjà réactualisés au `690da2c`** : `livrables/{html,pdf,docx}/{01,04,06,09,17,19,21}.*` et `artefacts/{04,05,06}*.html`.
- **Preuves VM du `17/11/2026` et inventaire** : `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt`, `docs/17-inventaire-projet.csv`, `.github/workflows/ci.yml` et l’ensemble des suites `tests/`.

---

## 7. Plan de remise à jour recommandé (ordre d’exécution)

1. **Mettre à jour les sources de Priorité 1 :**
   - `docs/01-cahier-des-charges-MiniShop.md` (§6, §7.2, §8.1, §12 intro, §12.5, §14.1) ;
   - `.tools/build_doc04.py` (nettoyage de la mention `15/11` « aucun rejeu SQL ») ;
   - `scripts/rejeu_gitlab.py` et `docs/08-fiche-suivi-commits.md` (`82 tâches / 348 h`) ;
   - `docs/11-strategie-tests.md` (addendum `17/11/2026`) ;
   - `docs/12-diaporama-soutenance.md` (diapos 5, 6, 8, 11, 12) ;
   - `docs/annexes/annexe-enf.md` (`ENF-01`, `04`, `10`, `11`, `13`, `14`, `16`) et `docs/annexes/annexe-php-patterns.md` ;
   - `docs/sql-a-ecrire/requetes-sql-a-ecrire.md` (`install.php` → `scripts/db_init.php`).
2. **Harmoniser les fichiers de Priorité 2 :**
   - `docs/02-document-tests-validation.md` (chapô et tableau §8), `docs/03-support-soutenance.md`, `.tools/build_wbs.py`, `.tools/build_todolist.py`, `docs/19`, `docs/21`, `docs/09` / `docs/17` (étiquettes historiques), `README.md` et `scripts/build-docs.sh` (inclure `docs/pv-recette.md`).
3. **Tout régénérer en une passe :**
   - exécuter `.tools/build_wbs.py`, `.tools/build_todolist.py`, `.tools/build_doc04.py`, `.tools/build_doc04_html.py` (et régénérer `artefacts/01-*.html` et `artefacts/02-*.html`) ;
   - exécuter `bash scripts/build-docs.sh` pour régénérer **tous** les fichiers `livrables/html/`, `livrables/pdf/`, `livrables/docx/`, `livrables/minishop-soutenance.pptx` et `livrables/README.md`.
4. **Valider avant commit :**
   - `python3 -m unittest discover -s tests/python -v` (en particulier `test_inventaire_global_a_jour` et `test_archives_bureautiques_integrite`), `python3 scripts/verifier_fiches_uc.py`, `bash tests/security/controles.sh` et `git diff --check`.
