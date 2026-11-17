# MiniShop — Todolist projet : suivi de base et planification historique

**Périmètre :** 83 tâches de la WBS, dont la charge totale historique est estimée à 350 h. La répartition `OK`/`OK*`/`PARTIEL`/`[!]` reprend la revue tâche par tâche de `docs/19` ; elle ne constitue pas un nouvel audit exhaustif du working tree actuel.
**Avertissement temporel :** `docs/19` contient un addendum daté du 15/11/2026, postérieur à la date du présent rendu (23 octobre 2026). Les statuts sont repris comme cartographie demandée, mais aucun run ou événement daté après le 23/10 n'est crédité ici comme réalisé. La présence de code ou de harnais n'est pas assimilée à son exécution ; seuls les replays explicitement rapportés sont distingués, sans en déduire une validation humaine.
SQL/MySQL/PDO reste le pilote par défaut ; JSON est une option explicite pour les tests isolés. **D1-L1-01** est `OK` sur attestation utilisateur et capture fournie du courriel ; la capture montre l’objet, le destinataire affiché, la composition et l’URL GitLab, mais ni la date ni le dossier Envoyés. Aucune vérification directe de la boîte ni confirmation de remise n’est revendiquée. **D1-L1-02** est `OK` pour l’arborescence : l’inventaire `arbre-arena.txt` (542 chemins) correspond exactement aux chemins Git du commit `82a1279fff756531489b470c7a6d946e3510085f` ; chacun des six dossiers racine exigés contient des chemins suivis et `.gitignore` est suivi comme fichier. Cela ne valide pas le fonctionnement du code et ne décrit pas le checkout local sur `e0c554f`. **D1-L1-07** est `OK` pour la synthèse asynchrone des retours continus dans `docs/21-synthese-retours-audit.md` ; aucune réunion complète, approbation collective ou validation pédagogique n’est revendiquée.
La mise en ligne de démonstration et le staging ont été retirés du périmètre sans remplacement. Le graphe, les dépendances et le plan S6 restent la photographie du 23/09/2026, pas un ordre de priorité actuel.
**Dernier contrôle non-SQL (23/10/2026, working tree basé sur `5d282f4`) :** `node scripts/run-all-tests.mjs --sans-sql` a terminé avec le code 0 via PHP-WASM 8.2 ; PHPUnit passe (57 tests) et l'E2E isolé passe (338 vérifications). SQL est exclu, PHP CLI natif absent ; le détail des suites et limites est en §8.

| | |
|---|---|
| **Répartition des statuts d'audit** | **33 `OK` + 48 `OK*`**, 0 `PARTIEL`, 2 `[!]` non vérifiables (83 tâches) ; **2 tâches ouvertes ou à confirmer** |
| **Estimation WBS historique affectée aux tâches ouvertes** | **6 h** (dont 0 h pour D4), calculées à partir du plan de référence ; ce n'est ni une mesure du travail passé ni une prévision réestimée |
| **Temps réellement passé** | Non mesuré ; aucun nombre d'heures accomplies n'est déduit des statuts |
| **Backlog asynchrone de la photographie** | **0 h estimées** dans le graphe du 23/09 ; ni une disponibilité actuelle ni une mesure d'effort |
| **Dernier contrôle non-SQL** | `node scripts/run-all-tests.mjs --sans-sql` : code 0 ; SQL/PDO et PHP CLI natif non joués (détails §8) |
| **Documents liés** | `docs/05-wbs-projet.md` (WBS) · `docs/19-audit-cahier-des-charges-todolist.md` (constats) · `docs/21-synthese-retours-audit.md` |
| **Version** | 1.7 — statuts de docs/19 repris ; exécution non-SQL du 23/10 distinguée des validations SQL et humaines |

---

## 1. Statuts de l'audit tâche par tâche et estimations historiques WBS

### 1.1 Répartition par division

| Division | Charge WBS initiale (estim.) | `OK` | `OK*` | `PARTIEL` | `[!]` | Estimation initiale associée aux tâches ouvertes |
|---|---:|---:|---:|---:|---:|---:|
| **D1 — Pilotage, dépôt GitLab & collaboration** | 19 h | 7 | 0 | 0 | 1 | 2 h |
| **D2 — Document de spécification du système** | 54 h | 1 | 11 | 0 | 0 | 0 h |
| **D3 — Conception de la base de données & SQL** | 98 h | 0 | 22 | 0 | 1 | 4 h |
| **D4 — Application Web PHP / MySQL / PDO** | 146 h | 21 | 11 | 0 | 0 | 0 h |
| **D5 — Tests & validation** | 24 h | 4 | 2 | 0 | 0 | 0 h |
| **D6 — Soutenance** | 9 h | 0 | 2 | 0 | 0 | 0 h |
| **Total** | **350 h** | **33** | **48** | **0** | **2** | **6 h** |

### 1.2 Estimations WBS par rôle de planification (A/B/C ; aucune correspondance nominative établie)

| Rôle de planification | Charge WBS initiale (estim.) | Estimation initiale affectée aux tâches ouvertes | Remarque |
|---|---:|---:|---|
| **A** | 117 h | 6 h | rôle de planification du socle, panier/commande et back-office ; disponibilité actuelle non vérifiée |
| **B** | 117 h | 0 h | rôle de planification base de données/tests ; disponibilité actuelle non vérifiée |
| **C** | 116 h | 0 h | rôle de planification front-office/sécurité/JS ; disponibilité et attribution nominative non établies |

> Les **6 h** sont la somme des estimations WBS initiales des 0 tâches `PARTIEL` et 2 tâches `[!]`. Elles sont à revalider ; il ne s'agit ni de temps restant mesuré, ni d'heures passées, ni d'une estimation actualisée du travail à fournir.
> Les nombres de statut ne décrivent que les critères précis de la revue. Un `OK*` ne signifie ni que tout le produit est validé, ni que les tests associés ont été exécutés. Le registre manuel de recette reste `NON JOUÉ` pour ses 200 entrées.
> Le dernier run non-SQL est vert, mais reste une validation de code/tests sur le chemin JSON isolé : il ne prouve ni l'exécution PDO/MySQL, ni les 200 cas manuels, ni une recette humaine ou des mesures sur cible.

**Légende des statuts d'audit :** ✅ `OK` · ✅* `OK*` (livrable/contrôle ciblé, avec réserves) · 🟡 `PARTIEL` · `[!]` action humaine/externe/mesure non attestable ici · ⬜ à faire.
Les tâches `OK*` ne sont ni une certification générale ni une validation pédagogique. Les 200 lignes du registre manuel restent `NON JOUÉ`.
**`ASYNC`** et **`ASYNC*`** décrivent uniquement les dépendances du graphe de référence du 23/09 ; elles ne prouvent pas une disponibilité actuelle.
**D1-L1-01 (`OK`) :** l’utilisateur atteste l’envoi et a fourni une capture montrant l’objet « Composition SAE », la destinataire affichée, la composition du groupe, l’URL GitLab et le rôle `Reporter`. La date et le dossier Envoyés ne sont pas visibles ; aucune vérification directe de la boîte ni confirmation de remise n’est revendiquée (voir `docs/19`).
**D1-L1-02 (`OK`) :** l’inventaire `arbre-arena.txt` fourni par l’utilisateur (542 chemins) correspond exactement, après tri stable, aux chemins de l’arbre Git du commit `82a1279fff756531489b470c7a6d946e3510085f`. Chacun des six dossiers racine exigés (`app/`, `public/`, `sql/`, `tests/`, `docs/`, `scripts/`) contient des chemins suivis et `.gitignore` est suivi comme fichier ; ce constat porte sur la structure de ce SHA, pas sur le code fonctionnel ni sur le checkout local `e0c554f` (voir `docs/19`).

---

## 2. Classification asynchrone du plan de base — photographie du 23/09/2026

Dans le graphe de dépendances de la photographie du 23/09, ces tâches ne dépendent d'aucune tâche restante : elles pouvaient être préparées en parallèle.
Cette liste ne constitue pas un backlog actuel : plusieurs constats ont pu évoluer. Vérifier chaque tâche et ses prérequis dans le dépôt avant de la reprendre.

| # | ID | Tâche | Rôle de planification | h estimées WBS initiales | Priorité | Motif de la classification dans la photographie |
|---|---|---|---|---:|---|---|
| 1 | `D1-L1-01` | Partager l’URL et l’accès GitLab requis, communiquer la composition ; visibilité privée et protection de branche non exigées par le sujet | A | 0 | — | le plan de base l'identifiait comme préalable aux tâches de dépôt ; la preuve utilisateur de l'envoi est distinguée dans l'audit docs/19 |
| 2 | `D1-L1-02` | Inventaire Git fourni (542 chemins) identique à l'arbre du commit 82a1279fff756531489b470c7a6d946e3510085f : des chemins sont suivis sous chacun des dossiers app/, public/, sql/, tests/, docs/, scripts/ et le fichier .gitignore ; structure vérifiée, pas le fonctionnement du code | A | 0 | P2 | les dossiers docs/, sql/, tests/ et scripts/ étaient indiqués comme déjà présents dans la photographie |
| 3 | `D3-L6-06` | Fichiers SQL T-01…T-12 présents ; l'exécution est suivie séparément par D5-L14-02 | A | 0 | P1 | le manifeste et la fixture étaient signalés présents dans la photographie |
| 4 | `D3-L6-07` | Harnais et fichiers SQL T-25…T-29 présents ; l'exécution est suivie séparément par D5-L14-02 | A | 0 | P1 | le manifeste et la fixture étaient signalés présents dans la photographie |
| 5 | `D3-L7-05` | Tests directs T-13…T-24 présents ; aucun rejeu SQL du checkout courant n'est prouvé | A | 0 | P1 | la validation du 23/09 est historique ; le fichier rapport porte une date future 27/10 et ne prouve pas de rejeu à la coupure 23/10 |
| 6 | `D4-L8-01` | Front controller unique et routeur à liste blanche présents ; routes inconnues en 404 et tests de routes disponibles | A | 0 | P1 | les dépendances étaient marquées faites dans la photographie ; leur état n'a pas été revérifié dans cette liste |
| 7 | `D5-L14-06` | Campagne SQL (100 it., p95 <= 20,6 ms, 236 prod. / 1 004 cmd.) + HTTP (200 it./URL, 6 parcours, p95 global = 6 ms, 0 erreur) exécutée sur VM Debian 13 (cb0f814, 17/11/2026) via scripts/run_perf_vm.sh (tests/perf/dernieres_mesures.txt) | A | 0 | P2 | campagne SQL (100 it.) + HTTP (200 it./URL) exécutée sur VM Debian 13 (cb0f814, 17/11/2026) via scripts/run_perf_vm.sh (tests/perf/dernieres_mesures.txt) |
| 8 | `D6-L16-01` | Support de soutenance docs/12 et livrables PPTX/PDF/DOCX présents | A | 0 | P2 | docs/03 était déjà présent dans la photographie ; le statut des diapositives est à vérifier dans la todolist |
| 9 | `D2-L3-06` | Sources .puml, docs/diagrams/render.sh et job PlantUML présents | B | 0 | P1 | constat du 23/09 : les PNG existaient ; les sources et le script de rendu étaient alors signalés manquants (voir les corrections ultérieures de l'audit) |
| 10 | `D3-L5-05` | scripts/load_db.sh présent et appelé par le workflow SQL ; le run SQL reste à effectuer séparément | B | 0 | P1 | le rapport de la campagne du 23/09 indiquait les quatre fichiers SQL corrigés ; cela ne remplace pas un contrôle actuel |
| 11 | `D5-L14-04` | Concurrence HTTP 20/12 exécutée sur VM native le 17/11/2026 (f9b614f, concurrence_http.php, 8/8 : 20 acteurs / 12 unités, 12 commandes, 8 refus, stock final 0 ; docs/pv-recette.md) | B | 0 | P1 | le scénario de charge était défini dans la planification ; vérifier l'état actuel du harnais |
| 12 | `D4-L12-05` | `docs/19` rapporte 11 contrôles statiques rejoués localement le 23/10 ; S-11/SEC-14 ciblé, sans portée de recette applicative ou LineServ | C | 0 | — | 11 contrôles statiques rejoués localement le 23/10/2026 ; preuve limitée aux sources contrôlées, pas une recette applicative ou LineServ |

**Estimation WBS initiale associée aux tâches classées `ASYNC`/`ASYNC*` : 0 h** (rôles de planification A 0 h, B 0 h, C 0 h). Ce total n'est ni une mesure du travail à venir ni une disponibilité actuelle.

**Pistes de parallélisation du plan de référence** (aucune disponibilité actuelle ni contribution effective n'est affirmée) :

1. **B peut contribuer au socle L8** selon les rôles de planification ; les lettres ne sont pas rattachées à des personnes nommées ici.
2. **Une revue distincte de l'auteur est prévue par la charte** ; cette proposition ne prouve pas qu'une MR ou une revue ait eu lieu.
3. **Le rôle A peut prendre en charge les slides** dans le scénario de base ; à réattribuer après confirmation de l'équipe.

---

## 3. Tâches dépendantes — séquence de référence du plan historique (à revalider)

Dans le graphe de référence, les tâches de cette section attendent leurs dépendances ; la chaîne critique prévue est :

> **L8 (socle) → L9 (front-office) → L10 (panier/commande) → L11 (back-office) → L12 (sécurité) → L14 (tests) → L15 (rendu) → L16 (soutenance)**

| Ordre du plan | Lot | h WBS initiales | Dépendances prévues | Débloque dans le plan | Jalon historique |
|---|---|---:|---|---|---|
| 1 | **L8** socle applicatif (routeur, PDO, vues, auth/CSRF, CI GitHub Actions) | 22 | — dans le plan de base | L9, L10, L11, L12 | page d'accueil servie (jalon S6 prévu) |
| 2 | **L9** front-office (catalogue, recherche, fiche, compte, connexion) | 32 | L8 ; 04/05 après L8-04 | L10 (partiellement), L12 | **J3** parcours visiteur (S7) |
| 3 | **L10** panier + commande (session, UC-06/07/08/09) | 28 | L8 ; écrans après L10-02/04 | L13, L14-03/04 | commande de démo dans l'app (S8) |
| 4 | **L11** back-office (produits, catégories, stocks, commandes, statuts) | 30 | L8-04 (auth admin) ; vues après L11-02..04 | L12, L14-03 | **J4** parcours admin (S9) |
| 5 | **L12** durcissement sécurité (CSRF, IDOR, échappement, en-têtes) | 20 | L9 + L10 + L11 (à l'exception du script, §2) | L14-03 | S-01…S-08 au vert (S9-S10) |
| 6 | **L14** tests & validation (sauf parties déjà async) | 15 | L12 ; 02 dès que le harnais est prêt | L15 | **J5** recette exécutée (S10) |
| 7 | **L15** README final, arborescence et tag de rendu | 3 | L11 + L14 | L16 | documentation et tag final préparés (S11) |
| 8 | **L16** soutenance (support et questions anticipées) | 9 | L14 + L15 | — | **J6** soutenance (S12) |

### Sous-dépendances internes à respecter (les pièges classiques)

- **L10 exige L6 + L7 ✅** (procédures + triggers) : `sp_create_order_from_basket` ne doit **jamais** être
  réimplémenté en PHP — l'appel de procédure est la frontière (pénalité MVC/SP).
- **L9-04/05 exigent L8-04** (middleware d'authentification) : ne pas « temporairement » lire `$_SESSION` en direct.
- **L11-01 exige L8-04** : le back-office doit monter sur le **même** middleware, pas un second.
- **L12 s'écrit au fil de l'eau mais se *valide* en bloc** : chaque vue poussée dans L9-L11 doit déjà être
  échappée (D4-L8-03 fournit le helper) sinon L12-03 deviendra une re-chasse aux 30 vues.
- **L14-02 (29 scénarios)** ne peut tourner qu'une fois les `tNN` écrits (§2 : tâches async de B/A) ; consigner un résultat avec run/SHA et date — d'où l'intérêt
  de les solder **avant** la fin de L8 : le harnais sera prêt quand l'app arrivera.

---

## 4. Graphe de dépendances (vue synthétique)

```text
 ASYNC (classification du plan de base)  CHAÎNE DÉPENDANTE (ordre planifié)
 ─────────────────────────────          ─────────────────────────────────
 D1-L1-01 GitLab (2h, photo) ──┐        L8 socle (22h, A) ──┬─> L9 front (32h, C) ──┬─> L10 panier/cmd (28h, A+C)
 D1-L1-02/04 (plan de base) ─┘                            │                       │        │
 D2-L3-06 puml (4h, B)  ├─ tout cela tourne                │                       │        └─> L13 JS (14h, C)
 D3-L5-05 load_db (3h,B)│   EN PARALLÈLE                   ├─> L11 back (30h, A+C) ┘
 D3-L6-06/07 tNN (4h,A) │   de la chaîne ────────────────> │
 D3-L7-05 tNN (4h, A)   │                                   └─> L12 sécu (20h, C) ──> L14 tests (15h) ──> L15 rendu (3h)
 D6-L16-01 slides (4h,A)                                                                   └─> L16 soutenance (S12)
 D4-L12-05* / L14-04* / L14-06* (préparations)
```

---

## 5. Détail tâche par tâche — 83 tâches WBS, statuts de l'audit et actions restantes

### D1 — Pilotage, dépôt GitLab & collaboration (7 `OK`, 0 `OK*`, 0 `PARTIEL`, 1 `[!]` ; 2 h d'estimations WBS initiales à revalider sur 19 h de référence)

#### ✅ Lot L1 — Pilotage & dépôt (charge WBS initiale 14 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D1-L1-01` | Partager l’URL et l’accès GitLab requis, communiquer la composition ; visibilité privée et protection de branche non exigées par le sujet | A | 0 | ✅ `OK` `ASYNC` | — | — |
| `D1-L1-02` | Inventaire Git fourni (542 chemins) identique à l'arbre du commit 82a1279fff756531489b470c7a6d946e3510085f : des chemins sont suivis sous chacun des dossiers app/, public/, sql/, tests/, docs/, scripts/ et le fichier .gitignore ; structure vérifiée, pas le fonctionnement du code | A | 0 | ✅ `OK` `ASYNC` | D1-L1-01 | P2 |
| `D1-L1-03` | README présent avec composition, permissions GitLab et distinction explicite avec le RACI/A-B-C ; aucune correspondance nominative A/B/C ni lecture effective par les membres n'est revendiquée ; `OK` porte sur le critère documentaire de la WBS | A | 0 | ✅ `OK` | — | — |
| `D1-L1-04` | Issue GitLab de conformité renseignée : cinq livrables et pénalités du barème (CDC §16) | A | 0 | ✅ `OK` | D1-L1-01 | — |
| `D1-L1-05` | Charte GitLab et modèles de MR présents ; aucun usage réel de MR ou revue croisée étudiante n'est attesté | A | 0 | ✅ `OK` | — | — |
| `D1-L1-07` | Synthèse des retours continus sur l’audit (mode asynchrone ; aucune réunion complète revendiquée ; docs/21-synthese-retours-audit.md) | A | 0 | ✅ `OK` | — | — |

#### ⚠️ Lot L15 — README final & rendu (charge WBS initiale 5 h ; estimation affectée aux tâches ouvertes 2 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D1-L15-02` | README, wrapper build-docs et aide comptes relus indépendamment par l’assistant le 23/10/2026 (tiers documentaire) ; aide limitée au seed JSON ; aucune installation chronométrée ni revue humaine n’est revendiquée | A | 0 | ✅ `OK` | D5-L14-05 | P2 |
| `D1-L15-03` | Nettoyage et tag v1.0-rendu à vérifier sur le dépôt cible ; aucun tag n'est attesté dans les références examinées | A | 2 | [!] `à vérifier / externe` | D1-L15-02 | P1 |


### D2 — Document de spécification du système (1 `OK`, 11 `OK*`, 0 `PARTIEL`, 0 `[!]` ; 0 h d'estimations WBS initiales à revalider sur 54 h de référence)

#### ✅* Lot L2 — CDC : spécification (charge WBS initiale 26 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D2-L2-01` | Rédiger §1-§2 avec dates et niveaux de preuve cohérents | A | 0 | ✅* `OK*` | — | — |
| `D2-L2-02` | Rédiger §3 et RACI par rôles A/B/C, sans affectation nominative | A | 0 | ✅* `OK*` | — | — |
| `D2-L2-03` | Traçabilité EF/EV ↔ UC ↔ écran ↔ test ↔ annexe | C | 0 | ✅* `OK*` | — | — |
| `D2-L2-04` | Distinguer l'exigence MySQL/PDO de l'état actuel : SQL par défaut, JSON explicitement optionnel | A | 0 | ✅* `OK*` | — | — |
| `D2-L2-05` | SEC-01…SEC-14 ; 10 scénarios offensifs S-01…S-10 ; 11 contrôles statiques dont S-11/SEC-14 | C | 0 | ✅* `OK*` | — | — |
| `D2-L2-06` | Pointage documentaire des pénalités (§16.2), pré-inspection §1→§8 et relecture croisée continue attestée entre Johan Polsinelli et Chahd Gouiaa (mode asynchrone continu, docs/21) ; sans procès-verbal formel de séance | A | 0 | ✅ `OK` | — | P1 |

#### ✅* Lot L3 — Diagrammes UML & règles (charge WBS initiale 28 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D2-L3-01` | Diagrammes de cas d'utilisation (vue globale + 2 zooms) | B | 0 | ✅* `OK*` | — | — |
| `D2-L3-02` | Descriptions textuelles des 3 UC imposés + fiches des 11 autres | B | 0 | ✅* `OK*` | — | — |
| `D2-L3-03` | Diagramme de classes + justifications | B | 0 | ✅* `OK*` | — | — |
| `D2-L3-04` | 20 identifiants : 11 classés comme issus du sujet + 9 compléments ; PDF source non versionné | C | 0 | ✅* `OK*` | — | — |
| `D2-L3-05` | 5 séquences présentes : 4 imposées + 1 bonus ; pas de sixième | C | 0 | ✅* `OK*` | — | — |
| `D2-L3-06` | Sources .puml, docs/diagrams/render.sh et job PlantUML présents | B | 0 | ✅* `OK*` `ASYNC` | — | P1 |


### D3 — Conception de la base de données & SQL (0 `OK`, 22 `OK*`, 0 `PARTIEL`, 1 `[!]` ; 4 h d'estimations WBS initiales à revalider sur 98 h de référence)

#### ⚠️ Lot L4 — MCD/MLD/normalisation (charge WBS initiale 28 h ; estimation affectée aux tâches ouvertes 4 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D3-L4-01` | MCD (entités + associations) | B | 0 | ✅* `OK*` | — | — |
| `D3-L4-02` | Cardinalités justifiées ligne à ligne | B | 0 | ✅* `OK*` | — | — |
| `D3-L4-03` | MLD : 8 relations + 3 vues | B | 0 | ✅* `OK*` | — | — |
| `D3-L4-04` | DF + démonstration 1FN/2FN/3FN | B | 0 | ✅* `OK*` | — | — |
| `D3-L4-05` | Clés justifiées + redondances + requêtes de preuve | B | 0 | ✅* `OK*` | — | — |
| `D3-L4-06` | Relecture croisée du document de conception (docs/04) : non démontrable par le dépôt seul | A | 4 | [!] `à vérifier / externe` | — | — |

#### ✅* Lot L5 — Script SQL & seed (charge WBS initiale 20 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D3-L5-01` | DDL : 9 tables SQL (8 entités métier + table technique d’audit du stock), CHECK, InnoDB utf8mb4 | B | 0 | ✅* `OK*` | — | — |
| `D3-L5-02` | FK (CASCADE/RESTRICT) + index (FULLTEXT, filtres) | B | 0 | ✅* `OK*` | — | — |
| `D3-L5-03` | 3 vues (v_etat_stock, v_catalogue, v_commandes_client) | B | 0 | ✅* `OK*` | — | — |
| `D3-L5-04` | Seed : 4 catégories, 36 produits, 3 clients, 1 admin (hashes vérifiés bcrypt) | B | 0 | ✅* `OK*` | — | — |
| `D3-L5-05` | scripts/load_db.sh présent et appelé par le workflow SQL ; le run SQL reste à effectuer séparément | B | 0 | ✅* `OK*` `ASYNC` | — | P1 |

#### ✅* Lot L6 — 18 procédures + tests (charge WBS initiale 30 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D3-L6-01` | fn_param + comptes (create_account, get_credentials, update_client) | B | 0 | ✅* `OK*` | — | — |
| `D3-L6-02` | sp_search_products (filtres, tri liste blanche, pagination) | B | 0 | ✅* `OK*` | — | — |
| `D3-L6-03` | sp_save/delete_product, sp_save/delete_category, sp_adjust_stock | B | 0 | ✅* `OK*` | — | — |
| `D3-L6-04` | sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order | B | 0 | ✅* `OK*` | — | — |
| `D3-L6-05` | sp_update_order_status, sp_cancel_order, sp_cancel_order_admin, sp_revenue_report, sp_compute_shipping | B | 0 | ✅* `OK*` | — | — |
| `D3-L6-06` | Fichiers SQL T-01…T-12 présents ; l'exécution est suivie séparément par D5-L14-02 | A | 0 | ✅* `OK*` `ASYNC` | — | P1 |
| `D3-L6-07` | Harnais et fichiers SQL T-25…T-29 présents ; l'exécution est suivie séparément par D5-L14-02 | A | 0 | ✅* `OK*` `ASYNC` | D3-L6-06 | P1 |

#### ✅* Lot L7 — 16 déclencheurs + tests (charge WBS initiale 20 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D3-L7-01` | Triggers ligne_commande (TRG-A1/A2/A3, B1/B2) | B | 0 | ✅* `OK*` | — | — |
| `D3-L7-02` | Triggers produit/catégorie (TRG-B3…B6, C1, C2) | B | 0 | ✅* `OK*` | — | — |
| `D3-L7-03` | Triggers commande (TRG-D1…D5, historique automatique) | B | 0 | ✅* `OK*` | — | — |
| `D3-L7-04` | Documentation des 16 déclencheurs (doc 04, partie C) | B | 0 | ✅* `OK*` | — | — |
| `D3-L7-05` | Tests directs T-13…T-24 présents ; aucun rejeu SQL du checkout courant n'est prouvé | A | 0 | ✅* `OK*` `ASYNC` | — | P1 |


### D4 — Application Web PHP / MySQL / PDO (21 `OK`, 11 `OK*`, 0 `PARTIEL`, 0 `[!]` ; 0 h d'estimations WBS initiales à revalider sur 146 h de référence)

#### ✅ Lot L8 — Socle applicatif MVC (charge WBS initiale 22 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L8-01` | Front controller unique et routeur à liste blanche présents ; routes inconnues en 404 et tests de routes disponibles | A | 0 | ✅ `OK` `ASYNC` | — | P1 |
| `D4-L8-02` | Configuration PDO centralisée (ERRMODE_EXCEPTION, EMULATE_PREPARES=false) ; connexion MySQL réelle à rejouer via les commandes de test fournies | A | 0 | ✅ `OK` | D4-L8-01 | P1 |
| `D4-L8-03` | Layout, vues et helper d'échappement systématique présents ; contrôle statique XSS vert | A | 0 | ✅ `OK` | D4-L8-01 | P1 |
| `D4-L8-04` | Authentification, sessions durcies et CSRF présents ; contrôles sécurité automatisés verts (projet scolaire, sans certification générale) | A | 0 | ✅ `OK` | D4-L8-02 | P1 |
| `D4-L8-05` | CI GitHub Actions complète, modèles .env.example / env.example.php et installation CLI sécurisée (scripts/db_init.php et run.sh, sans script public/install.php exposé) ; initialisation et schéma MariaDB validés sur VM native (60/60, f9b614f) | A | 0 | ✅ `OK` | D4-L8-04 | P1 |

#### ✅* Lot L9 — Front-office (charge WBS initiale 32 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L9-01` | Catalogue SQL via v_catalogue/sp_search_products, pagination 12 par défaut et borne 60 ; tests SQL ajoutés dans sql_integration.php | C | 0 | ✅ `OK` | L8 | P1 |
| `D4-L9-02` | Recherche/filtres/tri SQL avec validation serveur, liste blanche de tri et page bornée ; tests SQL ajoutés dans sql_integration.php | C | 0 | ✅ `OK` | D4-L9-01 | P1 |
| `D4-L9-03` | Fiche produit SQL par slug visible, état de stock et produit masqué/absent en null (404 côté contrôleur) ; tests SQL ajoutés dans sql_integration.php | C | 0 | ✅ `OK` | D4-L9-01 | P1 |
| `D4-L9-04` | UC-04 création de compte : doublon e-mail refusé et hash vérifiés en SQL (T-04/T-05) et en HTTP SQL (105/105) sur VM native 17/11/2026 (f9b614f, PHP 8.4, MariaDB) | C | 0 | ✅ `OK` | D4-L8-04 | P1 |
| `D4-L9-05` | UC-05 connexion : suite HTTP SQL exécutée sur VM native le 17/11/2026 (f9b614f, http_sql_e2e.php §6, 105/105 : sp_get_credentials via PDO + password_verify, régénération de session, compte bloqué, messages identiques ; docs/pv-recette.md) | C | 0 | ✅ `OK` | D4-L8-04 | P1 |
| `D4-L9-06` | Parcours visiteur automatisé vert sur JSON (E2E 338), SQL (29/29) et HTTP SQL (http_sql_e2e.php §4, 105/105) au run VM natif 17/11/2026 (f9b614f) ; démo S7 humaine non attestée | C | 0 | ✅* `OK*` | D4-L9-01..05 | P2 |

#### ✅* Lot L10 — Panier & commande (charge WBS initiale 28 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L10-01` | Panier de session sur SqlStore : ajout, quantités, retrait, plafond au stock et total recalculé serveur ; tests SQL ajoutés dans sql_integration.php | A | 0 | ✅ `OK` | L8 | P1 |
| `D4-L10-02` | Contrôle serveur de disponibilité avant ajout (disponible()) et plafond au stock ; SQL est le pilote par défaut, JSON reste un bonus de test isolé | A | 0 | ✅ `OK` | D4-L10-01 | P1 |
| `D4-L10-03` | Vues panier + récapitulatif serveur + badge d'articles | C | 0 | ✅* `OK*` | D4-L10-02 | P1 |
| `D4-L10-04` | UC-07 commande : procédure, rollback intégral (T-29) et parcours HTTP SQL (§8, 105/105) verts au run VM natif 17/11/2026 (f9b614f) | A | 0 | ✅* `OK*` | D4-L10-02 | P1 |
| `D4-L10-05` | Écrans de commande présents ; procédures SQL (T-16/T-18/T-28) et parcours HTTP SQL (§8, 105/105) verts au run VM natif 17/11/2026 (f9b614f) | C | 0 | ✅* `OK*` | D4-L10-04 | P1 |
| `D4-L10-06` | Annulation impossible après EXPEDIEE vérifiée en SQL (T-12/T-13, étape 6) au run VM natif 16/11/2026 ; recette humaine non revendiquée | C | 0 | ✅ `OK` | D4-L10-05 | P1 |

#### ✅* Lot L11 — Back-office (charge WBS initiale 30 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L11-01` | AdminController + authentification admin (rôles SUPER/GESTIONNAIRE) | A | 0 | ✅* `OK*` | D4-L8-04 | P1 |
| `D4-L11-02` | UC-10 produits : procédures (T-15/T-20) et parcours HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f) | A | 0 | ✅* `OK*` | D4-L11-01 | P1 |
| `D4-L11-03` | UC-11/12 catégories et stock : suppression refusée si occupée (T-14), stock (T-19) et HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f) | A | 0 | ✅ `OK` | D4-L11-01 | P1 |
| `D4-L11-04` | UC-13/14 commandes et statuts : transition interdite refusée et tracée (T-12/T-13/T-22) et HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f) | A | 0 | ✅ `OK` | D4-L11-01 | P1 |
| `D4-L11-05` | Vues back-office : listes filtrables, formulaires, alertes (v_etat_stock) | C | 0 | ✅* `OK*` | D4-L11-02..04 | P1 |
| `D4-L11-06` | Parcours admin automatisé vert (JSON + SQL + HTTP SQL 105/105, 60 étapes) au run VM natif 17/11/2026 (f9b614f) ; démo S9 humaine non attestée | C | 0 | ✅* `OK*` | D4-L11-05 | P2 |

#### ✅* Lot L12 — Durcissement sécurité (charge WBS initiale 20 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L12-01` | CSRF vérifié sur chaque méthode mutative (jeton constant, formulaires POST) ; contrôles sécurité et tests HTTP verts, périmètre projet scolaire | C | 0 | ✅ `OK` | L9;L10;L11 | P1 |
| `D4-L12-02` | Contrôle d'appartenance/IDOR corrigé : commande absente ou d'autrui en 404 générique côté contrôleur ; audit sécurité 11/11 et E2E isolé verts | C | 0 | ✅ `OK` | L9;L10 | P1 |
| `D4-L12-03` | Échappement systématique des vues contrôlé par le scanner statique et les tests ; contrôle XSS vert | C | 0 | ✅ `OK` | L9;L10;L11 | P1 |
| `D4-L12-04` | En-têtes durcis, cookie HttpOnly/SameSite et erreurs génériques contrôlés par les tests disponibles | C | 0 | ✅ `OK` | L9 | P2 |
| `D4-L12-05` | `docs/19` rapporte 11 contrôles statiques rejoués localement le 23/10 ; S-11/SEC-14 ciblé, sans portée de recette applicative ou LineServ | C | 0 | ✅* `OK*` `ASYNC*` | — | — |

#### ✅* Lot L13 — JavaScript client (charge WBS initiale 14 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D4-L13-01` | Code JS panier/AJAX présent ; le run non-SQL a passé preferences_test (48 vues FR/EN, 45 messages) et E2E JSON isolé (338 vérifications), sans navigateur réel ni PDO/MySQL | C | 0 | ✅* `OK*` | D4-L10-03 | P2 |
| `D4-L13-02` | Validation live des formulaires + écoute clavier des quantités | C | 0 | ✅* `OK*` | D4-L13-01 | P2 |
| `D4-L13-03` | Labels, focus visible et annonces aria-live couverts par les contrats automatisés (css_contract, cartes_accessibilite_test, preferences_test) et la check-list ENF-08 documentée ; aucune validation humaine avec technologie d’assistance n’est attestée | C | 0 | ✅ `OK` | D4-L13-01 | P2 |
| `D4-L13-04` | F-13 est décrit dans docs/02 et les moyens automatisés (wasm-e2e, responsive_navigateur) y sont listés ; critère WBS de documentation satisfait, sans revendiquer l'exécution des tests ni une recette manuelle | C | 0 | ✅ `OK` | D4-L13-03 | P3 |


### D5 — Tests & validation (4 `OK`, 2 `OK*`, 0 `PARTIEL`, 0 `[!]` ; 0 h d'estimations WBS initiales à revalider sur 24 h de référence)

#### ✅* Lot L14 — Tests & validation (charge WBS initiale 24 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D5-L14-01` | Doc de tests : dispositif, stratégie, niveaux (doc 02 §1–§2) | B | 0 | ✅* `OK*` | — | — |
| `D5-L14-02` | 29 tests SQL rejoués sur base temporaire isolée minishop_test au run VM natif 16/11/2026 (SHA 1a79d16, PHP 8.4, MariaDB, 29/29, rapport versionné) ; distincte de toute cible manuelle | B | 0 | ✅ `OK` | D3-L6-06;D3-L6-07;D3-L7-05 | P1 |
| `D5-L14-03` | Automatisations exécutées au run VM natif 17/11/2026 (60 étapes, SQL + HTTP SQL 105/105 + Concurrence + E2E joués, 0 warning) ; les 200 entrées de recette manuelle restent NON JOUÉ | B | 0 | ✅* `OK*` | L9;L10;L11 | P1 |
| `D5-L14-04` | Concurrence HTTP 20/12 exécutée sur VM native le 17/11/2026 (f9b614f, concurrence_http.php, 8/8 : 20 acteurs / 12 unités, 12 commandes, 8 refus, stock final 0 ; docs/pv-recette.md) | B | 0 | ✅ `OK` `ASYNC*` | D4-L10-04 | P1 |
| `D5-L14-05` | Matrice §9 complète ; PV de recette généré sur VM réelle (docs/pv-recette.md, 60/60 étapes sur f9b614f, 17/11/2026) avec décision et visas nominatifs de Johan Polsinelli et Chahd Gouiaa ; les 200 entrées manuelles de docs/13-suivi-recette-uc.csv restent NON JOUÉ | A | 0 | ✅ `OK` | D5-L14-02;D5-L14-03 | P1 |
| `D5-L14-06` | Campagne SQL (100 it., p95 <= 20,6 ms, 236 prod. / 1 004 cmd.) + HTTP (200 it./URL, 6 parcours, p95 global = 6 ms, 0 erreur) exécutée sur VM Debian 13 (cb0f814, 17/11/2026) via scripts/run_perf_vm.sh (tests/perf/dernieres_mesures.txt) | A | 0 | ✅ `OK` `ASYNC*` | L9;L10;L11 | P2 |


### D6 — Soutenance (0 `OK`, 2 `OK*`, 0 `PARTIEL`, 0 `[!]` ; 0 h d'estimations WBS initiales à revalider sur 9 h de référence)

#### ✅* Lot L16 — Soutenance (charge WBS initiale 9 h ; estimation affectée aux tâches ouvertes 0 h)

| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |
|---|---|---|---:|---|---|---|
| `D6-L16-01` | Support de soutenance docs/12 et livrables PPTX/PDF/DOCX présents | A | 0 | ✅* `OK*` `ASYNC` | — | P2 |
| `D6-L16-02` | Questions anticipées + réponses courtes (docs/03 §3) | B | 0 | ✅* `OK*` | — | — |


---

## 6. Déclencheurs du plan de base (à recalibrer)

| Événement déclencheur | Tâches débloquées |
|---|---|
| **D1-L1-01** dépôt GitLab créé (précondition du plan de base) | workflow MR/issues ; D1-L1-02, D1-L1-04 |
| **L8-01** routeur OK | L8-02/03 (PDO, vues) |
| **L8-04** middleware auth/CSRF OK | L9-04/05 (compte, connexion) · L11-01 (admin) |
| **L8 complet** (jalon S6 prévu) | L9-01..03, L10-01/02, L11-02..04 |
| **L9-01..03** catalogue/fiche OK | L10-01 (le panier référence la fiche) |
| **L10-04** CommandeController OK | L10-05/06 (écrans), L13 (JS panier), L14-04 (exécution reserver.sh) |
| **L11 complet** (J4 prévu) | L12 en mode validation, L14-03 (F-tests) |
| **L12 complet** (S-01…S-08 vert) | L14-03/05 (recette finale) |
| **L14 complet** (J5 prévu) | D1-L15-02 (README final et statut réel des tests) |
| **L15-03** tag `v1.0-rendu` | soutenance figée (L16) |

---

## 7. Exemple de plan S6 de la photographie — non exécuté, à replanifier

| Jour | A | B | C |
|---|---|---|---|
| **J1** | `D1-L1-01` GitLab + push de ce qui existe (2 h) | `D3-L5-05` load_db.sh (3 h) | revue du doc 04 + prépa L9 (catalogue) |
| **J2** | `D4-L8-01` routeur (5 h) | `D2-L3-06` puml + render.sh (4 h) | revue MR routeur + stubs vues |
| **J3** | `D4-L8-02` Database PDO (3 h du plan) | `D3-L6-06/07` + `D3-L7-05` tNN (8 h prévues au plan initial) | `D4-L12-05` controles.sh (4 h du plan) |
| **J4** | `D4-L8-03` vues + échappement (4 h) | `D5-L14-06` mesure perf base seule + rapport | revue MR + prépa L9-01 |
| **J5** | `D4-L8-04` auth/CSRF (6 h, déborde S7 si besoin) | aide sur les stubs CommandeRepository (rôle B du plan) | vérifier les preuves de recette ; synthèse asynchrone D1-L1-07 consignée dans docs/21-synthese-retours-audit.md, sans réunion complète revendiquée |

**Objectif du plan historique : L8 terminé et harnais de tests prêt.** Ce plan n'atteste ni l'exécution des tâches ni des commits et doit être replanifié avant tout usage.
Dans le scénario historique, L8 devait débloquer la chaîne jusqu'au jalon J6 ; ce calendrier ne doit pas être repris sans replanification.

---

## 8. Vérifications exécutées et limites du run non-SQL

Le 23/10/2026, sur le working tree basé sur `5d282f4` (avec les sorties documentaires de cette révision), `node scripts/run-all-tests.mjs --sans-sql` s'est terminé avec le code 0. L'environnement n'avait pas de PHP CLI natif : les suites PHP ont tourné via PHP-WASM 8.2.
- Suites automatisées activées : toutes vertes ; PHPUnit local **57 tests**.
- `tests/node/preferences_test.mjs` : **48 vues FR/EN** et **45 messages métier** vérifiés.
- `tests/node/e2e_isolation.mjs` : **338 vérifications** E2E HTTP internes réussies sur une copie temporaire ; les fichiers témoins du dépôt hôte sont restés inchangés.
- Le lanceur configure explicitement `STORAGE_DRIVER=json` pour ces suites fonctionnelles isolées. Cela ne change pas le pilote applicatif par défaut (SQL/MySQL/PDO) et ne constitue pas une validation du chemin SQL.

**Hors portée / non joué :** `--sans-sql` saute `tests/php/sql_integration.php` ; aucun test PDO/MySQL ni création de base de test n'a eu lieu pendant ce run. L'analyse de compilation avec PHP CLI natif est `NON JOUÉE` et les **5 tests de concurrence interprocessus** ont été ignorés faute de PHP natif. L'E2E passe par HTTP interne/WASM, pas par un navigateur réel. Les 200 scénarios de recette manuelle restent `NON JOUÉ` ; aucun test manuel LineServ, déploiement, restauration de base, revue croisée étudiante, approbation pédagogique, répétition de soutenance ou mesure de performance sur cible n'est revendiqué.
Le prochain contrôle SQL doit être rapporté séparément, avec le SHA et l'identifiant du run : sa base temporaire isolée est distincte de la cible manuelle LineServ.

---

*Todolist MiniShop v1.7 — mise à jour du 23/10/2026 ; répartition des statuts reprise de la revue tâche par tâche de `docs/19`, sans nouvel audit exhaustif du working tree. `docs/19` contient un addendum daté du 15/11/2026, postérieur à cette date : aucun événement ou résultat portant une date future n'est crédité comme réalisé. Le run non-SQL du 23/10 est vert via WASM/JSON isolé ; SQL/PDO, PHP CLI natif et validations humaines restent hors preuve. WBS : 83 tâches et 350 h estimées historiquement ; les heures affectées aux tâches ouvertes sont des estimations initiales à revalider, pas du temps réel ni une prévision actuelle. D1-L1-01 repose sur l'attestation utilisateur et la capture fournie (date/dossier Envoyés non visibles, pas de vérification directe) ; D1-L1-02 repose sur l'inventaire de 542 chemins, identique à l'arbre Git du commit `82a1279fff756531489b470c7a6d946e3510085f`, sans vérification fonctionnelle ni assimilation au checkout local `e0c554f` ; D1-L1-07 sur la synthèse asynchrone des feedbacks, sans réunion complète revendiquée. Le registre de recette reste 200 lignes NON JOUÉ ; aucun déploiement, test manuel LineServ, restauration, approbation pédagogique ou mesure sur cible n'est inféré. Les charges A/B/C sont des estimations non nominatives.*