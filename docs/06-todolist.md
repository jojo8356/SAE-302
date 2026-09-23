# MiniShop — Todolist projet : tâches dépendantes et tâches asynchrones

**État réel arrêté au 23 septembre 2026** (vérifié dans le dépôt et sur la base MariaDB 11.8.6), croisé avec la WBS
(`docs/05-wbs-projet.md`) : les 86 tâches y sont reprises avec leur statut, puis classées en
**tâches dépendantes** (l'ordre est imposé) et **tâches asynchrones** (aucune attente, démarrage immédiat possible, en parallèle).

| | |
|---|---|
| **Avancement global** | **162 h faites / 360 h (45 %)** — 84 tâches terminées ou soldées, 14 partielles, 36 à faire |
| **Reste à faire** | **198 h** : application Web 146 h (le gros du reste), tests 18 h, pilotage 12 h, soutenance 9 h, BD 9 h, UML 4 h |
| **Prêtes MAINTENANT (asynchrones)** | **~40 h de travail immédiatement disponible**, répartissables en parallèle entre A, B et C sans aucun blocage |
| **Chaîne dépendante** | L8 → L9 → L10 → L11 → L12 → L14 → L15 → L16 (l'application), jalons J3→J6 |
| **Documents liés** | `docs/05-wbs-projet.md` (détail des tâches et charges) · `docs/01` §13.4 (jalons) · `README.md` (état du dépôt) |
| **Version** | 1.0 — 23 septembre 2026 |

---

## 1. État d'avancement (mesuré, pas déclaré)

### 1.1 Par division

| Division | Charge | Fait | Reste | Avancement |
|---|---:|---:|---:|---|
| **D1 — Pilotage, dépôt GitLab & intégration** | 24 h | 12 h | 12 h | ██████████░░░░░░░░░░ 50% |
| **D2 — Document de spécification du système** | 54 h | 50 h | 4 h | ███████████████████░ 93% |
| **D3 — Conception de la base de données & SQL** | 98 h | 89 h | 9 h | ██████████████████░░ 91% |
| **D4 — Application Web PHP / MySQL / PDO** | 146 h | 0 h | 146 h | ░░░░░░░░░░░░░░░░░░░░ 0% |
| **D5 — Tests & validation** | 24 h | 6 h | 18 h | █████░░░░░░░░░░░░░░░ 25% |
| **D6 — Soutenance** | 14 h | 5 h | 9 h | ███████░░░░░░░░░░░░░ 36% |
| **Total** | **360 h** | **162 h** | **198 h** | **45 %** |

### 1.2 Par personne (chargé vs restant)

| Pers. | Charge WBS | Reste | Fait | Remarque |
|---|---:|---:|---:|---|
| **A** | 122 h | 84 h | 38 h | porte la chaîne critique (socle + panier/commande + back-office) ; les pilotes async (GitLab, slides) sont légers, à faire en début de semaine |
| **B** | 117 h | **17 h seulement** | 100 h | la base est **terminée et validée** ; B est la ressource libre de la période → absorbe du C (revues des MR d'A) et les tests |
| **C** | 121 h | 97 h | 24 h | le front-office + sécurité + JS pèsent sur C : **prioriser les MR croisées avec A sur L8** pour démarrer |

> **Lecture clé** : le reste du projet est concentré sur **une seule chaîne** (D4, 146 h). Tout le jeu
> consiste à démarrer L8 **immédiatement** (il n'attend plus rien) pendant que B solde les 17 h qui lui restent
> et que chacun prend sa part du **backlog asynchrone** (§2) — c'est le seul levier pour tenir S6→S12.

**Légende des statuts utilisés partout ci-dessous :** ✅ fait (0 h restante) · 🟡 partiel (reste chiffré) · ⬜ à faire ·
**`ASYNC`** = aucune dépendance non satisfaite → démarrage immédiat possible · **`ASYNC*`** = la *préparation* est
immédiate (écrire le script), l'*exécution utile* attend un lot de la chaîne.

---

## 2. ⚡ TÂCHES NON DÉPENDANTES — le backlog asynchrone (à prendre sans attendre)

Ces tâches **ne dépendent d'aucune tâche restante** : elles peuvent être exécutées **en parallèle**, dans n'importe
quel ordre, par des personnes différentes, pendant que la chaîne de l'application (§3) avance. C'est le travail à
enfiler dès aujourd'hui — idéalement **cette semaine (S6)**, en tête de chaque journée avant les lots de chaîne.

| # | ID | Tâche | Pers. | h | Priorité | Pourquoi c'est prêt maintenant |
|---|---|---|---|---:|---|---|
| 1 | `D4-L8-01` | Front controller + routeur (listes blanches de routes et de tri) | A | 5 | P1 | ses seules dépendances (spec UML, règles, SQL) sont **toutes FAITES** |
| 2 | `D5-L14-06` | Performance p95 < 500 ms : partie base seule MESURABLE MAINTENANT (mesurer_sql.sh), partie HTTP après L9 | A | 5 | P2 | mesurer_sql.sh est livré et la base est chargée → mesure base seule immédiate |
| 3 | `D6-L16-01` | Slides de la soutenance (le déroulé docs/03 existe ; les diapositives restent à produire) | A | 4 | P2 | le déroulé minute par minute (docs/03) est écrit : les slides n'attendent que lui |
| 4 | `D1-L1-01` | Créer le dépôt GitLab privé, inviter l'équipe + enseignante, protéger main, envoyer le courriel « Composition SAE » | A | 2 | P1 | aucun prérequis technique (10 min d'admin GitLab) mais **tout le reste en dépend** → le traiter en premier |
| 5 | `D1-L1-07` | Réunion de cadrage : relecture du sujet ligne à ligne, périmètre, règle de repli groupe de 2 | A | 2 | P3 | pure décision d'équipe, aucun artefact requis |
| 6 | `D3-L6-06` | Écrire les fichiers tNN_*.sql pour T-01…T-12 (le manifeste existe) | A | 2 | P1 | le manifeste (attendu par test) et la fixture sont déjà livrés |
| 7 | `D3-L6-07` | Écrire tests/sql/run_tests.sh + tNN T-25…T-29 (fixture déjà livrée) | A | 2 | P1 | idem + run_tests.sh s'écrit contre le manifeste |
| 8 | `D3-L7-05` | Formaliser en tNN rejouables les tests SQL directs T-02…T-24 (campagne du 23/09 déjà exécutée et rapportée) | A | 2 | P1 | la campagne a déjà été **exécutée pour de vrai** (rapport versionné) — il reste à la rendre rejouable |
| 9 | `D1-L1-02` | Compléter le squelette : arborescence app/ + public/ vides (.gitkeep) — le reste (docs/sql/tests/scripts) est en place | A | 1 | P2 | les dossiers docs/ sql/ tests/ scripts/ existent déjà |
| 10 | `D1-L1-04` | Épingler la grille de conformité du barème en issue GitLab (contenu déjà rédigé : CDC §16) | A | 1 | P2 | la grille est déjà rédigée (CDC §16.2), il ne reste qu'à l'épingler |
| 11 | `D2-L3-06` | Versionner les sources .puml + docs/diagrams/render.sh (le README les annonce : à livrer) | B | 4 | P1 | les 14 PNG existent ; il ne manque que les sources et le script de rendu |
| 12 | `D3-L5-05` | Écrire scripts/load_db.sh (encapsule DELIMITER, chargement 01→04, comptages de contrôle) | B | 3 | P1 | les 4 fichiers sql/ sont **corrigés et validés** (campagne du 23/09) — le script n'est qu'un enrubannage |
| 13 | `D5-L14-04` | Concurrence : écrire reserver.sh (script dès maintenant), exécuter 20/12 après L10 | B | 3 | P1 | le scénario de charge (20/12) est défini : le script s'écrit sans l'app |
| 14 | `D4-L12-05` | Écrire tests/security/controles.sh (10 contrôles) : le script SE PRÉPARE MAINTENANT, son « vert » attend l'application | C | 4 | P1 | les 10 contrôles sont spécifiés (CDC §15.3) : le grep-script s'écrit sans l'app |

**Total backlog asynchrone : ~40 h** (A 36 h — dont le lot L8 de 22 h qui est la *porte d'entrée de la chaîne* —,
B 7 h, C 4 h + les préparations `ASYNC*`).

**Trois façons de croiser les bras entre personnes** (règle des ≥ 5 commits par zone, WBS §10) :

1. **B aide A sur L8** : B connaît `fn_param`, les codes retour (`STOCK_INSUFFISANT`…) et les signatures des
   17 procédures mieux que quiconque → c'est B qui rédige les stubs d'appel du `CommandeRepository`.
2. **C relit chaque MR de L8** (revue croisée obligatoire de toute façon) → C prépare L9/L12 en lisant le socle.
3. **A prend les 4 h de slides** (L16-01) en fin de semaine creuse : tâche isolée, aucun conflit de merge.

---

## 3. ⛓️ TÂCHES DÉPENDANTES — la chaîne de l'application (l'ordre est imposé)

Une tâche de cette section **ne peut pas commencer** avant que ses dépendances soient ✅. La chaîne critique est :

> **L8 (socle) → L9 (front-office) → L10 (panier/commande) → L11 (back-office) → L12 (sécurité) → L14 (tests) → L15 (rendu) → L16 (répétition)**

| Ordre | Lot | h | Ne peut démarrer qu'après | Débloque | Jalon à l'arrivée |
|---|---|---:|---|---|---|
| 1 | **L8** socle applicatif (routeur, PDO, vues, auth/CSRF, CI active) | 22 | — (déps déjà ✅) → ****`ASYNC`** | L9, L10, L11, L12 | page d'accueil servie (S6) |
| 2 | **L9** front-office (catalogue, recherche, fiche, compte, connexion) | 32 | L8 ; 04/05 après L8-04 | L10 (partiellement), L12 | **J3** parcours visiteur (S7) |
| 3 | **L10** panier + commande (session, UC-06/07/08/09) | 28 | L8 ; écrans après L10-02/04 | L13, L14-03/04 | commande de démo dans l'app (S8) |
| 4 | **L11** back-office (produits, catégories, stocks, commandes, statuts) | 30 | L8-04 (auth admin) ; vues après L11-02..04 | L12, L14-03, L15-01 | **J4** parcours admin (S9) |
| 5 | **L12** durcissement sécurité (CSRF, IDOR, échappement, en-têtes) | 20 | L9 + L10 + L11 (à l'exception du script, §2) | L14-03 | S-01…S-08 au vert (S9-S10) |
| 6 | **L14** tests & validation (sauf parties déjà async) | 15 | L12 ; 02 dès que le harnais est prêt | L15 | **J5** recette exécutée (S10) |
| 7 | **L15** mise en ligne de démo + README final + tag | 6 | L11 + L14 | L16-03 | run de bout en bout (S11) |
| 8 | **L16-03** répétitions chronométrées | 5 | L16-01 (slides) + L11 (démo réelle) | — | **J6** soutenance (S12) |

### Sous-dépendances internes à respecter (les pièges classiques)

- **L10 exige L6 + L7 ✅** (procédures + triggers) : `sp_create_order_from_basket` ne doit **jamais** être
  réimplémenté en PHP — l'appel de procédure est la frontière (pénalité MVC/SP).
- **L9-04/05 exigent L8-04** (middleware d'authentification) : ne pas « temporairement » lire `$_SESSION` en direct.
- **L11-01 exige L8-04** : le back-office doit monter sur le **même** middleware, pas un second.
- **L12 s'écrit au fil de l'eau mais se *valide* en bloc** : chaque vue poussée dans L9-L11 doit déjà être
  échappée (D4-L8-03 fournit le helper) sinon L12-03 deviendra une re-chasse aux 30 vues.
- **L14-02 (29/29)** ne peut tourner qu'une fois les `tNN` écrits (§2 : tâches async de B/A) — d'où l'intérêt
  de les solder **avant** la fin de L8 : le harnais sera prêt quand l'app arrivera.

---

## 4. Graphe de dépendances (vue synthétique)

```text
 ASYNC (immédiat, en parallèle)          CHAÎNE DÉPENDANTE (séquentielle)
 ─────────────────────────────          ─────────────────────────────────
 D1-L1-01 GitLab (2h) ──┐               L8 socle (22h, A) ──┬─> L9 front (32h, C) ──┬─> L10 panier/cmd (28h, A+C)
 D1-L1-02/04/07 (5h)    │                                   │                       │        │
 D2-L3-06 puml (4h, B)  ├─ tout cela tourne                │                       │        └─> L13 JS (14h, C)
 D3-L5-05 load_db (3h,B)│   EN PARALLÈLE                   ├─> L11 back (30h, A+C) ┘
 D3-L6-06/07 tNN (4h,A) │   de la chaîne ────────────────> │
 D3-L7-05 tNN (2h, A)   │                                   └─> L12 sécu (20h, C) ──> L14 tests (15h) ──> L15 rendu (6h)
 D6-L16-01 slides (4h,A)                                                                   └─> L16-03 répét (5h)
 D4-L12-05* / L14-04* / L14-06* (préparations)
```

---

## 5. Détail complet — les 86 tâches avec leur statut réel

### D1 — Pilotage, dépôt GitLab & intégration (12/24 h faites, reste 12 h)

#### 🟡 Lot L1 — Pilotage & dépôt (10/16 h · reste 6 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D1-L1-01` | Créer le dépôt GitLab privé, inviter l'équipe + enseignante, protéger main, envoyer le courriel « Composition SAE » | A | 2 | ⬜ `ASYNC` | — | P1 |
| `D1-L1-02` | Compléter le squelette : arborescence app/ + public/ vides (.gitkeep) — le reste (docs/sql/tests/scripts) est en place | A | 1 | 🟡 `ASYNC` | D1-L1-01 | P2 |
| `D1-L1-03` | README v0 (prérequis, installation cible, membres et rôles) | A | 0 | ✅ | — | — |
| `D1-L1-04` | Épingler la grille de conformité du barème en issue GitLab (contenu déjà rédigé : CDC §16) | A | 1 | 🟡 `ASYNC` | D1-L1-01 | P2 |
| `D1-L1-05` | Charte GitLab : branches, MR croisées, commits conventionnels, 1 issue par UC/EF | A | 0 | ✅ | — | — |
| `D1-L1-06` | Chaîne CI (.gitlab-ci.yml, 6 jobs) — fichier livré ; s'exécutera au premier push | A | 0 | ✅ | — | — |
| `D1-L1-07` | Réunion de cadrage : relecture du sujet ligne à ligne, périmètre, règle de repli groupe de 2 | A | 2 | ⬜ `ASYNC` | — | P3 |

#### 🟡 Lot L15 — Démo en ligne & rendu (2/8 h · reste 6 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D1-L15-01` | scripts/deploy.sh : hashes aléatoires, emails de démo régénérés | A | 3 | ⬜ | L11 | P2 |
| `D1-L15-02` | README final : statut réel de l'application, wrapper build-docs.sh (ou pointer .tools), comptes | A | 1 | 🟡 | D1-L15-01 | P2 |
| `D1-L15-03` | Nettoyage de l'arborescence + tag v1.0-rendu | A | 2 | ⬜ | D1-L15-02 | P1 |


### D2 — Document de spécification du système (50/54 h faites, reste 4 h)

#### ✅ Lot L2 — CDC : spécification (26/26 h · reste 0 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D2-L2-01` | Rédiger §1 Présentation + §2 Objectifs | A | 0 | ✅ | — | — |
| `D2-L2-02` | Rédiger §3 Acteurs + RACI | A | 0 | ✅ | — | — |
| `D2-L2-03` | Rédiger §4 Fonctionnalités (table exigence ↔ UC ↔ écran ↔ test) | C | 0 | ✅ | — | — |
| `D2-L2-04` | Rédiger §7 Contraintes techniques | A | 0 | ✅ | — | — |
| `D2-L2-05` | Rédiger §8 Sécurité (SEC-01…14 + matrice menaces) | C | 0 | ✅ | — | — |
| `D2-L2-06` | Relecture croisée §1→§8 + pointage des pénalités | A | 0 | ✅ | — | — |

#### 🟡 Lot L3 — Diagrammes UML & règles (24/28 h · reste 4 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D2-L3-01` | Diagrammes de cas d'utilisation (vue globale + 2 zooms) | B | 0 | ✅ | — | — |
| `D2-L3-02` | Descriptions textuelles des 3 UC imposés + fiches des 11 autres | B | 0 | ✅ | — | — |
| `D2-L3-03` | Diagramme de classes + justifications | B | 0 | ✅ | — | — |
| `D2-L3-04` | Formaliser RB-01…RB-20 | C | 0 | ✅ | — | — |
| `D2-L3-05` | 5 diagrammes de séquence (SP/triggers visibles) | C | 0 | ✅ | — | — |
| `D2-L3-06` | Versionner les sources .puml + docs/diagrams/render.sh (le README les annonce : à livrer) | B | 4 | 🟡 `ASYNC` | — | P1 |


### D3 — Conception de la base de données & SQL (89/98 h faites, reste 9 h)

#### ✅ Lot L4 — MCD/MLD/normalisation (28/28 h · reste 0 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D3-L4-01` | MCD (entités + associations) | B | 0 | ✅ | — | — |
| `D3-L4-02` | Cardinalités justifiées ligne à ligne | B | 0 | ✅ | — | — |
| `D3-L4-03` | MLD : 8 relations + 3 vues | B | 0 | ✅ | — | — |
| `D3-L4-04` | DF + démonstration 1FN/2FN/3FN | B | 0 | ✅ | — | — |
| `D3-L4-05` | Clés justifiées + redondances + requêtes de preuve | B | 0 | ✅ | — | — |
| `D3-L4-06` | Relecture croisée du document de conception (docs/04) | A | 0 | ✅ | — | — |

#### ⬜ Lot L5 — Script SQL & seed (17/20 h · reste 3 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D3-L5-01` | DDL : 8 tables, CHECK, InnoDB utf8mb4 | B | 0 | ✅ | — | — |
| `D3-L5-02` | FK (CASCADE/RESTRICT) + index (FULLTEXT, filtres) | B | 0 | ✅ | — | — |
| `D3-L5-03` | 3 vues (v_etat_stock, v_catalogue, v_commandes_client) | B | 0 | ✅ | — | — |
| `D3-L5-04` | Seed : catégories, 12 produits, 3 clients, 1 admin (hashes vérifiés bcrypt) | B | 0 | ✅ | — | — |
| `D3-L5-05` | Écrire scripts/load_db.sh (encapsule DELIMITER, chargement 01→04, comptages de contrôle) | B | 3 | ⬜ `ASYNC` | — | P1 |

#### 🟡 Lot L6 — 17 procédures + tests (26/30 h · reste 4 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D3-L6-01` | fn_param + comptes (create_account, get_credentials, update_client) | B | 0 | ✅ | — | — |
| `D3-L6-02` | sp_search_products (filtres, tri liste blanche, pagination) | B | 0 | ✅ | — | — |
| `D3-L6-03` | sp_save/delete_product, sp_save/delete_category, sp_adjust_stock | B | 0 | ✅ | — | — |
| `D3-L6-04` | sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order | B | 0 | ✅ | — | — |
| `D3-L6-05` | sp_update_order_status, sp_cancel_order, sp_revenue_report, sp_compute_shipping | B | 0 | ✅ | — | — |
| `D3-L6-06` | Écrire les fichiers tNN_*.sql pour T-01…T-12 (le manifeste existe) | A | 2 | 🟡 `ASYNC` | — | P1 |
| `D3-L6-07` | Écrire tests/sql/run_tests.sh + tNN T-25…T-29 (fixture déjà livrée) | A | 2 | 🟡 `ASYNC` | D3-L6-06 | P1 |

#### 🟡 Lot L7 — 16 déclencheurs + tests (18/20 h · reste 2 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D3-L7-01` | Triggers ligne_commande (TRG-A1/A2/A3, B1/B2) | B | 0 | ✅ | — | — |
| `D3-L7-02` | Triggers produit/catégorie (TRG-B3…B6, C1, C2) | B | 0 | ✅ | — | — |
| `D3-L7-03` | Triggers commande (TRG-D1…D5, historique automatique) | B | 0 | ✅ | — | — |
| `D3-L7-04` | Documentation des 16 déclencheurs (doc 04, partie C) | B | 0 | ✅ | — | — |
| `D3-L7-05` | Formaliser en tNN rejouables les tests SQL directs T-02…T-24 (campagne du 23/09 déjà exécutée et rapportée) | A | 2 | 🟡 `ASYNC` | — | P1 |


### D4 — Application Web PHP / MySQL / PDO (0/146 h faites, reste 146 h)

#### ⬜ Lot L8 — Socle applicatif MVC (0/22 h · reste 22 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L8-01` | Front controller + routeur (listes blanches de routes et de tri) | A | 5 | ⬜ `ASYNC` | — | P1 |
| `D4-L8-02` | app/Config/Database.php : PDO, EXCEPTION, EMULATE_PREPARES = false | A | 3 | ⬜ | D4-L8-01 | P1 |
| `D4-L8-03` | Gabarit de vues + layout + helper d'échappement systématique | A | 4 | ⬜ | D4-L8-01 | P1 |
| `D4-L8-04` | app/Security : AuthMiddleware, sessions durcies, jetons CSRF | A | 6 | ⬜ | D4-L8-02 | P1 |
| `D4-L8-05` | CI complète activée + app/Config/env.example.php + public/install.php (annoncés par sql/01 et le README) | A | 4 | ⬜ | D4-L8-04 | P1 |

#### ⬜ Lot L9 — Front-office (0/32 h · reste 32 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L9-01` | UC-01 Catalogue : v_catalogue + pagination (12 par défaut, borne 60) | C | 6 | ⬜ | L8 | P1 |
| `D4-L9-02` | UC-02 Recherche/filtres : SearchRequest (validation serveur, tri liste blanche) | C | 6 | ⬜ | D4-L9-01 | P1 |
| `D4-L9-03` | UC-03 Fiche produit : état du stock, MAX = stock, 404 si masquée | C | 5 | ⬜ | D4-L9-01 | P1 |
| `D4-L9-04` | UC-04 Création de compte : validation, sp_create_account, hash | C | 6 | ⬜ | D4-L8-04 | P1 |
| `D4-L9-05` | UC-05 Connexion/déconnexion : sp_get_credentials + password_verify + session régénérée | C | 6 | ⬜ | D4-L8-04 | P1 |
| `D4-L9-06` | Parcours visiteur complet démontré (JS désactivé inclus) — jalon J3 | C | 3 | ⬜ | D4-L9-01..05 | P2 |

#### ⬜ Lot L10 — Panier & commande (0/28 h · reste 28 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L10-01` | PanierSession : ajout, quantités, retrait, plafond au stock, total serveur | A | 6 | ⬜ | L8 | P1 |
| `D4-L10-02` | UC-06 PanierController : contrôle serveur disponible() avant tout ajout | A | 5 | ⬜ | D4-L10-01 | P1 |
| `D4-L10-03` | Vues panier + récapitulatif serveur + badge d'articles | C | 4 | ⬜ | D4-L10-02 | P1 |
| `D4-L10-04` | UC-07 CommandeController : adresse, confirmation, sp_create_order_from_basket | A | 3 | ⬜ | D4-L10-02 | P1 |
| `D4-L10-05` | Écrans commande : adresse, numéro CMD2026-0000NN, confirmation | C | 6 | ⬜ | D4-L10-04 | P1 |
| `D4-L10-06` | UC-08 historique + détail + UC-09 annulation (vues client) | C | 4 | ⬜ | D4-L10-05 | P1 |

#### ⬜ Lot L11 — Back-office (0/30 h · reste 30 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L11-01` | AdminController + authentification admin (rôles SUPER/GESTIONNAIRE) | A | 4 | ⬜ | D4-L8-04 | P1 |
| `D4-L11-02` | UC-10 Produits : sp_save_product / sp_delete_product | A | 5 | ⬜ | D4-L11-01 | P1 |
| `D4-L11-03` | UC-11 Catégories + UC-12 Stocks (SET/DELTA, motif) | A | 5 | ⬜ | D4-L11-01 | P1 |
| `D4-L11-04` | UC-13 Commandes + UC-14 statut (matrice RB-11) + indicateurs | A | 4 | ⬜ | D4-L11-01 | P1 |
| `D4-L11-05` | Vues back-office : listes filtrables, formulaires, alertes (v_etat_stock) | C | 8 | ⬜ | D4-L11-02..04 | P1 |
| `D4-L11-06` | Parcours admin complet démontré — jalon J4 | C | 4 | ⬜ | D4-L11-05 | P2 |

#### ⬜ Lot L12 — Durcissement sécurité (0/20 h · reste 20 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L12-01` | CSRF vérifié sur chaque méthode mutative | C | 4 | ⬜ | L9;L10;L11 | P1 |
| `D4-L12-02` | Autorisation : contrôle d'appartenance, 404 sur id étranger (IDOR) | C | 5 | ⬜ | L9;L10 | P1 |
| `D4-L12-03` | Échappement systématique : revue des vues | C | 4 | ⬜ | L9;L10;L11 | P1 |
| `D4-L12-04` | En-têtes durcis, cookie HttpOnly/SameSite, erreurs génériques | C | 3 | ⬜ | L9 | P2 |
| `D4-L12-05` | Écrire tests/security/controles.sh (10 contrôles) : le script SE PRÉPARE MAINTENANT, son « vert » attend l'application | C | 4 | ⬜ `ASYNC` | — | P1 |

#### ⬜ Lot L13 — JavaScript client (0/14 h · reste 14 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D4-L13-01` | JS panier : badge, MAJ AJAX, repli sans JS | C | 5 | ⬜ | D4-L10-03 | P2 |
| `D4-L13-02` | Validation live des formulaires + écoute clavier des quantités | C | 4 | ⬜ | D4-L13-01 | P2 |
| `D4-L13-03` | Accessibilité : labels, focus visible, aria-live | C | 3 | ⬜ | D4-L13-01 | P2 |
| `D4-L13-04` | Check-list « JS = confort seulement » (test F-13) | C | 2 | ⬜ | D4-L13-03 | P3 |


### D5 — Tests & validation (6/24 h faites, reste 18 h)

#### 🟡 Lot L14 — Tests & validation (6/24 h · reste 18 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D5-L14-01` | Doc de tests : dispositif, stratégie, niveaux (doc 02 §1–§2) | B | 0 | ✅ | — | — |
| `D5-L14-02` | Rejouer les 29 tests SQL via le harnais + rapport final | B | 4 | ⬜ | D3-L6-06;D3-L6-07;D3-L7-05 | P1 |
| `D5-L14-03` | Cas fonctionnels F-01…F-31 : exécution + preuves | B | 3 | ⬜ | L9;L10;L11 | P1 |
| `D5-L14-04` | Concurrence : écrire reserver.sh (script dès maintenant), exécuter 20/12 après L10 | B | 3 | ⬜ `ASYNC` | D4-L10-04 | P1 |
| `D5-L14-05` | Matrice de traçabilité exigence → test + PV de recette à jour | A | 3 | 🟡 | D5-L14-02;D5-L14-03 | P1 |
| `D5-L14-06` | Performance p95 < 500 ms : partie base seule MESURABLE MAINTENANT (mesurer_sql.sh), partie HTTP après L9 | A | 5 | ⬜ `ASYNC` | L9;L10;L11 | P2 |


### D6 — Soutenance (5/14 h faites, reste 9 h)

#### 🟡 Lot L16 — Soutenance (5/14 h · reste 9 h)

| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |
|---|---|---|---:|---|---|---|
| `D6-L16-01` | Slides de la soutenance (le déroulé docs/03 existe ; les diapositives restent à produire) | A | 4 | 🟡 `ASYNC` | — | P2 |
| `D6-L16-02` | Questions anticipées + réponses courtes (docs/03 §3) | B | 0 | ✅ | — | — |
| `D6-L16-03` | Deux répétitions chronométrées avec la démo réelle + check-list | C | 5 | ⬜ | D6-L16-01;L11 | P2 |


---

## 6. Déclencheurs : « quand X se termine → Y démarre »

| Événement déclencheur | Tâches débloquées |
|---|---|
| **D1-L1-01** dépôt GitLab créé (aujourd'hui) | tout le workflow MR/issues ; D1-L1-02, D1-L1-04 |
| **L8-01** routeur OK | L8-02/03 (PDO, vues) |
| **L8-04** middleware auth/CSRF OK | L9-04/05 (compte, connexion) · L11-01 (admin) |
| **L8 complet** (jalon S6) | L9-01..03, L10-01/02, L11-02..04 → **la chaîne se met à couler** |
| **L9-01..03** catalogue/fiche OK | L10-01 (le panier référence la fiche) |
| **L10-04** CommandeController OK | L10-05/06 (écrans), L13 (JS panier), L14-04 (exécution reserver.sh) |
| **L11 complet** (J4) | L12 en mode validation, L14-03 (F-tests), L15-01 (deploy.sh) |
| **L12 complet** (S-01…S-08 vert) | L14-03/05 (recette finale), L15 (rendu) |
| **L15-03** tag `v1.0-rendu` | L16-03 (répétitions sur la démo figée) |

---

## 7. Cette semaine (S6) — le plan d'action immédiat

| Jour | A | B | C |
|---|---|---|---|
| **J1** | `D1-L1-01` GitLab + push de ce qui existe (2 h) | `D3-L5-05` load_db.sh (3 h) | revue du doc 04 + prépa L9 (catalogue) |
| **J2** | `D4-L8-01` routeur (5 h) | `D2-L3-06` puml + render.sh (4 h) | revue MR routeur + stubs vues |
| **J3** | `D4-L8-02` Database PDO (3 h) | `D3-L6-06/07` + `D3-L7-05` tNN (6 h) | `D4-L12-05` controles.sh (4 h) |
| **J4** | `D4-L8-03` vues + échappement (4 h) | `D5-L14-06` mesure perf base seule + rapport | revue MR + prépa L9-01 |
| **J5** | `D4-L8-04` auth/CSRF (6 h, déborde S7 si besoin) | aide A : stubs CommandeRepository (B est libre !) | `D1-L1-04` grille issue + `D1-L1-07` cadrage |

**Objectif de fin de semaine : L8 terminé, harnais de tests prêt, dépôt vivant (≥ 1 commit/pers./jour)** —
la chaîne critique peut alors couler sans attendre pendant 6 semaines jusqu'à J6.

---

*Todolist MiniShop v1.0 — statuts mesurés dans le dépôt et sur la base (campagne SQL du 23/09,
rapport `tests/sql/rapport_tests_sql.md`) ; charges issues de la WBS `docs/05` (360 h, répartition A 122 / B 117 / C 121).*