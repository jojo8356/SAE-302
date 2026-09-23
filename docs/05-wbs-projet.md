# MiniShop — WBS (Work Breakdown Structure) : divisions, lots et tâches individuelles

**Structure de découpage du projet MiniShop selon la méthode WBS/OTP : 4 niveaux (projet → divisions → lots → tâches),
estimation *bottom-up* de chaque tâche individuelle, plan de charge par personne, Gantt, chemin critique et coûts.**

| | |
|---|---|
| **Projet** | MiniShop — site e-commerce avec back-office (SAE — BUT Informatique / L3, Université Côte d'Azur) |
| **Équipe** | 3 développeurs : **A** chef de projet & back-office · **B** modèle & base de données · **C** présentation, sécurité & tests |
| **Effort total** | **360 h = 3 × 120 h** (estimation *bottom-up* : la somme des 86 tâches de niveau 4) · calendrier **S1 → S12** |
| **Divisions (niveau 2)** | 6 — alignées une à une sur les 5 lignes du barème + la gestion de projet |
| **Lots (niveau 3)** | 16 (L1→L16, identiques au CDC §13.3, charges réconciliées) |
| **Tâches (niveau 4)** | **86 tâches individuelles** — chacune est assignable, estimable et vérifiable (règle d'arrêt WBS) |
| **Documents liés** | `docs/01-cahier-des-charges-MiniShop.md` (§13.3/§13.4) · `docs/02-document-tests-validation.md` · `docs/03-support-soutenance.md` · `docs/04-conception-bd-et-sql.md` · `README.md` |
| **Version** | 1.0 — 23 septembre 2026 |

---

## 1. Objet et méthode

La **Work Breakdown Structure** (structure de découpage de projet, ou organigramme des tâches) hiérarchise le travail en 4 niveaux :

| Niveau | Contenu | Ici |
|---|---|---|
| **1** | le projet (produit final) | MiniShop : boutique + back-office + dossier de conception et de validation |
| **2** | les **divisions** = livrables majeurs | 6 divisions D1→D6, une par ligne du barème (+ pilotage) |
| **3** | les **lots** = sous-livrables | 16 lots L1→L16 (reprend le CDC §13.3) |
| **4** | les **tâches individuelles** | **86 tâches**, estimées en heures et assignées à A, B ou C |

**Règle d'arrêt** (jusqu'où descendre) : on ne descend pas plus bas que le niveau 4, car chaque tâche y satisfait les trois critères de la méthode —
1. **assignable** à une ou plusieurs ressources (colonne *Resp.*),
2. **estimable** en charge (colonne *h*),
3. **vérifiable** (colonne *Définition de fini* — le livrable de la tâche est observable).

**Estimation *bottom-up*** : les charges sont estimées au niveau 4, puis totalisées lot par lot, division par division, jusqu'au total projet.
C'est l'inverse d'une estimation globale « au doigt mouillé » : chaque heure du total projet remonte d'une tâche nommée.

**Identification** : `D<n>-L<lot>-<nn>` (ex. `D3-L6-04`). Chaque tâche devient une **issue GitLab** portant ce titre
(cf. §10), ce qui relie WBS, barème et trace de contribution individuelle.

**Note de version** — les charges par lot du CDC §13.3 ont été **réconciliées** avec cette estimation bottom-up
(la première mouture totalisait 402 h de lots contre 362 h annoncées, et la charge C dépassait le plafond affiché) ;
le CDC corrigé et la WBS affichent désormais le même total : **360 h = 3 × 120 h** (§13.2 du CDC).

---

## 2. Niveau 1 — le projet

**MiniShop** : un site e-commerce (catalogue, panier, commandes) + un back-office (produits, catégories, stocks, commandes)
en **PHP 8 / MySQL (PDO) / MVC 3 couches**, avec procédures stockées et déclencheurs, livré avec son dossier de
spécification, sa base conçue et normalisée (3FN), son application testée et validée, et une soutenance de 15 minutes.

> Critère de succès global : **zéro pénalité** sur les 5 lignes du barème (i 20 pts · ii 20 pts · iii 30 pts · iv 10 pts · v 10 pts),
> ce qui suppose : 3 types de diagrammes UML présents, sécurité traitée (SQLi, mots de passe, XSS, sessions, autorisation),
> 20 règles métier appliquées **en base**, script SQL rejouable, ≥ 5 procédures, ≥ 5 déclencheurs, MVC réel, README d'installation,
> document de tests et dépôt GitLab avec contribution régulière.

---

## 3. Niveau 2 — les 6 divisions du projet

| Div. | Division (livrable majeur) | Point du barème | Resp. | Lots | Charge |
|---|---|---|---|---|---:|
| **D1** | Pilotage, dépôt GitLab & intégration | barème v — contribution GitLab + README (10 pts, individuel) | A | L1 + L15 | **24 h** |
| **D2** | Document de spécification du système | barème i — 20 points | A + C | L2 + L3 | **54 h** |
| **D3** | Conception de la base de données & SQL | barème ii — 20 points | B | L4 + L5 + L6 + L7 | **98 h** |
| **D4** | Application Web PHP / MySQL / PDO | barème iii — 30 points (avec D5) | A + C | L8 + L9 + L10 + L11 + L12 + L13 | **146 h** |
| **D5** | Tests & validation (document de tests) | barème iii — −3 pts si le document manque | B + A | L14 | **24 h** |
| **D6** | Soutenance (15 min + questions) | barème iv — 10 points | A + B + C | L16 | **14 h** |
| | **Total projet** | | | 16 lots | **360 h** |

### Arborescence WBS (niveaux 1 → 3)

<!--ARBRE_WBS-->

```text
MiniShop — 360 h · 3 développeurs · 12 semaines (S1→S12)
│
├── D1 Pilotage, dépôt GitLab & intégration — 24 h · resp. A · [barème v — contribution GitLab + README (10 pts, individuel)]
│   ├── L1 Cadrage, relecture du sujet, grille de conformité, squelette du dépôt, README (16 h · A · S1)
│   └── L15 Mise en ligne de démo, deploy.sh, README final, arborescence propre (8 h · A · S11)
├── D2 Document de spécification du système — 54 h · resp. A + C · [barème i — 20 points]
│   ├── L2 CDC : présentation, objectifs, acteurs, fonctionnalités (26 h · A + C · S1/S2)
│   └── L3 Diagrammes UML (cas, classes, séquences) + règles métier formalisées (28 h · B + C · S2/S3)
├── D3 Conception de la base de données & SQL — 98 h · resp. B · [barème ii — 20 points]
│   ├── L4 MCD → MLD → normalisation, jeu de données (28 h · B (+ relecture A) · S3)
│   ├── L5 Script SQL complet (DDL, vues, index, seed) (20 h · B · S4)
│   ├── L6 17 procédures stockées + 1 fonction + tests SQL associés (30 h · B (+ tests A) · S5)
│   └── L7 16 déclencheurs + tests SQL associés (20 h · B (+ tests A) · S5/S6)
├── D4 Application Web PHP / MySQL / PDO — 146 h · resp. A + C · [barème iii — 30 points (avec D5)]
│   ├── L8 Socle applicatif : front controller, routeur, PDO, Database, vues de base (22 h · A · S6)
│   ├── L9 Front-office : catalogue, recherche, fiche, inscription, connexion (32 h · C · S7)
│   ├── L10 Panier + commande (session, PanierController, CommandeController) (28 h · A + C · S8)
│   ├── L11 Back-office : produits, catégories, stocks, commandes, statuts, indicateurs (30 h · A + C · S9)
│   ├── L12 Durcissement sécurité (CSRF, échappement, autorisation, en-têtes, journaux) (20 h · C · S9/S10)
│   └── L13 JavaScript client (panier, filtres, validation live, accessibilité) (14 h · C · S10)
├── D5 Tests & validation (document de tests) — 24 h · resp. B + A · [barème iii — −3 pts si le document manque]
│   └── L14 Tests & validation : harnais, 29 tests SQL, cas fonctionnels, plan de recette, doc (24 h · B + A · S10/S11)
└── D6 Soutenance (15 min + questions) — 14 h · resp. A + B + C · [barème iv — 10 points]
    └── L16 Soutenance : support, trame, répétitions chronométrées, questions anticipées (14 h · A + B + C · S12)
```

*Niveau 1 = le projet ; niveau 2 = les 6 divisions (structure des livrables, « PBS ») ; niveau 3 = les lots ;
le niveau 4 — le travail lui-même — est détaillé section 5.*

---

## 4. Niveau 3 — les 16 lots

| Lot | Contenu | Div. | Resp. | Semaines | Charge |
|---|---|---|---|---|---:|
| **L1** | Cadrage, relecture du sujet, grille de conformité, squelette du dépôt, README | D1 | A | S1 | 16 h |
| **L2** | CDC : présentation, objectifs, acteurs, fonctionnalités | D2 | A + C | S1/S2 | 26 h |
| **L3** | Diagrammes UML (cas, classes, séquences) + règles métier formalisées | D2 | B + C | S2/S3 | 28 h |
| **L4** | MCD → MLD → normalisation, jeu de données | D3 | B (+ relecture A) | S3 | 28 h |
| **L5** | Script SQL complet (DDL, vues, index, seed) | D3 | B | S4 | 20 h |
| **L6** | 17 procédures stockées + 1 fonction + tests SQL associés | D3 | B (+ tests A) | S5 | 30 h |
| **L7** | 16 déclencheurs + tests SQL associés | D3 | B (+ tests A) | S5/S6 | 20 h |
| **L8** | Socle applicatif : front controller, routeur, PDO, Database, vues de base | D4 | A | S6 | 22 h |
| **L9** | Front-office : catalogue, recherche, fiche, inscription, connexion | D4 | C | S7 | 32 h |
| **L10** | Panier + commande (session, PanierController, CommandeController) | D4 | A + C | S8 | 28 h |
| **L11** | Back-office : produits, catégories, stocks, commandes, statuts, indicateurs | D4 | A + C | S9 | 30 h |
| **L12** | Durcissement sécurité (CSRF, échappement, autorisation, en-têtes, journaux) | D4 | C | S9/S10 | 20 h |
| **L13** | JavaScript client (panier, filtres, validation live, accessibilité) | D4 | C | S10 | 14 h |
| **L14** | Tests & validation : harnais, 29 tests SQL, cas fonctionnels, plan de recette, doc | D5 | B + A | S10/S11 | 24 h |
| **L15** | Mise en ligne de démo, deploy.sh, README final, arborescence propre | D1 | A | S11 | 8 h |
| **L16** | Soutenance : support, trame, répétitions chronométrées, questions anticipées | D6 | A + B + C | S12 | 14 h |
| | **Total** | | | S1→S12 | **360 h** |

---

## 5. Niveau 4 — les tâches individuelles, division par division

*Lecture : chaque table liste les tâches du lot, leur responsable (A/B/C), la charge estimée en heures,
la **définition de fini** (livrable observable) et les dépendances (tâches ou lots préalables).
La ligne de sous-total de chaque lot redonne la charge du lot : la somme **remonte** — rien n'est ajouté au niveau lot qui ne vienne d'une tâche.*

### D1 — Pilotage, dépôt GitLab & intégration (24 h · resp. A · barème v — contribution GitLab + README (10 pts, individuel))

#### Lot L1 — Cadrage, relecture du sujet, grille de conformité, squelette du dépôt, README (16 h · A · S1)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D1-L1-01` | Ouvrir le dépôt privé GitLab, inviter les 3 développeurs et l'enseignante (Reporter), protéger main, désactiver le squash | A | 2 | accès effectifs + courriel « Composition SAE » envoyé | — |
| `D1-L1-02` | Squelette du dépôt : arborescence app/ public/ sql/ tests/ docs/ scripts/ + .gitignore | A | 2 | arborescence conforme au CDC §9.2 | 01 |
| `D1-L1-03` | README v0 : prérequis, installation cible, membres et rôles (RACI) | A | 3 | README visible à la racine, lu par les 3 membres | 02 |
| `D1-L1-04` | Grille de conformité au barème (CDC §16) épinglée en issue GitLab | A | 3 | issue créée : 5 livrables + pénalités suivis | 01 |
| `D1-L1-05` | Charte GitLab : branches, MR croisées, commits conventionnels, 1 issue par UC/EF | A | 2 | wiki + modèle de MR (annexe 17.9) | 01 |
| `D1-L1-06` | CI v0 : .gitlab-ci.yml avec job php-lint | A | 2 | pipeline vert sur un commit vide | 02 |
| `D1-L1-07` | Réunion de cadrage : relecture du sujet ligne à ligne, périmètre, règle de repli groupe de 2 | A | 2 | compte-rendu dans le wiki du dépôt | — |
| | **Sous-total L1** | | **16** | répartition : A 16 h | |

#### Lot L15 — Mise en ligne de démo, deploy.sh, README final, arborescence propre (8 h · A · S11)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D1-L15-01` | scripts/deploy.sh : hashes aléatoires, emails de démo régénérés | A | 3 | démo reinstallable en 1 commande, SEC-10 respecté | L14 |
| `D1-L15-02` | README final : installation 5 commandes, comptes, tests, sauvegarde | A | 3 | un tiers installe et lance en ≤ 15 min (ENF-13) | 01 |
| `D1-L15-03` | Nettoyage de l'arborescence + tag v1.0-rendu | A | 2 | shortlog équilibré, tag posé, MR mergées | 02 |
| | **Sous-total L15** | | **8** | répartition : A 8 h | |


### D2 — Document de spécification du système (54 h · resp. A + C · barème i — 20 points)

#### Lot L2 — CDC : présentation, objectifs, acteurs, fonctionnalités (26 h · A + C · S1/S2)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D2-L2-01` | Rédiger §1 Présentation + §2 Objectifs (objectifs techniques mesurables) | A | 4 | sections relues, conformité au sujet pointée | L1 |
| `D2-L2-02` | Rédiger §3 Acteurs (3 primaires + 2 secondaires) et RACI de l'équipe | A | 3 | tableau RACI 3 personnes validé | 01 |
| `D2-L2-03` | Rédiger §4 Fonctionnalités : table exigence ↔ UC ↔ écran ↔ test (EF-VIS/CLI/ADM) | C | 6 | couverture des 14 UC vérifiable ligne à ligne | 02 |
| `D2-L2-04` | Rédiger §7 Contraintes techniques (CT-01…CT-09) | A | 4 | contraintes imposées vs retenues séparées | 01 |
| `D2-L2-05` | Rédiger §8 Sécurité : SEC-01…SEC-14 + matrice menaces → contre-mesures | C | 6 | les 5 exigences du sujet (SQLi, mots de passe, XSS, sessions, autorisation) couvertes | 01 |
| `D2-L2-06` | Relecture croisée §1→§8 + pointage des pénalités du barème | A | 3 | grille §16.2 entièrement au vert | 01–05 |
| | **Sous-total L2** | | **26** | répartition : A 14 h · C 12 h | |

#### Lot L3 — Diagrammes UML (cas, classes, séquences) + règles métier formalisées (28 h · B + C · S2/S3)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D2-L3-01` | Diagrammes de cas d'utilisation : vue globale (3 acteurs, 14 UC) + 2 zooms (commandes, catalogue) | B | 6 | PNG + sources .puml versionnés, rendus par render.sh | L2 |
| `D2-L3-02` | Descriptions textuelles des 3 UC imposés (UC-07, UC-02, UC-14) + fiches des 11 autres | B | 4 | annexe 17.1 complète (les 14 cas) | 01 |
| `D2-L3-03` | Diagramme de classes (domaine, contrôle, repositories) + justification des choix et cardinalités | B | 6 | 8 classes du domaine + cardinalités argumentées | 01 |
| `D2-L3-04` | Formaliser RB-01…RB-20 : énoncé, forme formelle, point d'application, test associé | C | 4 | chaque règle a un point de contrôle et un test | L2 |
| `D2-L3-05` | 5 diagrammes de séquence (authentification, recherche, catalogue, panier, commande) avec SP/triggers | C | 6 | les 4 séquences imposées + 1 bonus, SP et triggers visibles | 03–04 |
| `D2-L3-06` | Diagramme d'activité (UC-07) + diagramme d'états de la commande + rendu PNG complet | C | 2 | 8 diagrammes rendus par docs/diagrams/render.sh | 05 |
| | **Sous-total L3** | | **28** | répartition : B 16 h · C 12 h | |


### D3 — Conception de la base de données & SQL (98 h · resp. B · barème ii — 20 points)

#### Lot L4 — MCD → MLD → normalisation, jeu de données (28 h · B (+ relecture A) · S3)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D3-L4-01` | Identifier entités et associations ; dessiner le MCD (PlantUML) | B | 6 | 7 entités + table parametre, associations nommées | L3 |
| `D3-L4-02` | Justifier chaque cardinalité ligne à ligne + consigner les refus de conception | B | 5 | tableau cardinalité/justification/test complet | 01 |
| `D3-L4-03` | Transformer en MLD : 8 relations (dont parametre) + 3 vues | B | 5 | règle de conversion 1NF appliquée et documentée | 02 |
| `D3-L4-04` | Recenser les dépendances fonctionnelles et démontrer 1FN/2FN/3FN | B | 5 | table DF par table + verdict par forme normale | 03 |
| `D3-L4-05` | Justifier clés primaires/étrangères + redondances assumées + 4 requêtes de preuve | B | 3 | requêtes de preuve renvoyant 0 ligne | 04 |
| `D3-L4-06` | Relecture croisée du document de conception (docs/04) et validation du jargon | A | 4 | document autonome conforme au plan du sujet | 03 |
| | **Sous-total L4** | | **28** | répartition : A 4 h · B 24 h | |

#### Lot L5 — Script SQL complet (DDL, vues, index, seed) (20 h · B · S4)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D3-L5-01` | DDL : 8 tables, types, CHECK (RB-02/03/05), InnoDB utf8mb4 | B | 6 | sql/01 charge sans erreur sur MySQL 8 et MariaDB 11 | L4 |
| `D3-L5-02` | Clés étrangères (CASCADE/RESTRICT au bon endroit) + index (FULLTEXT, filtres) | B | 4 | plans d'exécution justifiant chaque index | 01 |
| `D3-L5-03` | 3 vues : v_etat_stock, v_catalogue, v_commandes_client | B | 4 | vues créées et comptées par load_db.sh | 01 |
| `D3-L5-04` | Seed : 4 catégories, 12 produits (1 rupture, 1 masqué), 3 clients (password_hash), 1 admin | B | 3 | jeu de démonstration RB-04/RB-19 jouable | 01 |
| `D3-L5-05` | scripts/load_db.sh : chargement 01→04 + comptages de contrôle | B | 3 | sortie « base chargée, procédures ≥ 5, déclencheurs ≥ 5 » | 02–04 |
| | **Sous-total L5** | | **20** | répartition : B 20 h | |

#### Lot L6 — 17 procédures stockées + 1 fonction + tests SQL associés (30 h · B (+ tests A) · S5)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D3-L6-01` | fn_param + sp_create_account, sp_get_credentials, sp_update_client | B | 4 | comptes créés/bloqués selon RB-01/RB-12 | L5 |
| `D3-L6-02` | sp_search_products : filtres, tri liste blanche, pagination, 2 jeux de résultats | B | 4 | T-25 : page bornée, tri jamais injecté | 01 |
| `D3-L6-03` | sp_save_product, sp_delete_product, sp_save_category, sp_delete_category, sp_adjust_stock | B | 5 | RB-02/03/07/14 appliqués côté base | 01 |
| `D3-L6-04` | sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order | B | 6 | transaction complète, ROLLBACK si stock manque | 01 |
| `D3-L6-05` | sp_update_order_status, sp_cancel_order, sp_revenue_report, sp_compute_shipping | B | 3 | matrice RB-11 + port calculé/franchisé (T-28) | 04 |
| `D3-L6-06` | Tests SQL T-01…T-12 (règles compte/produit/ligne) + manifeste du harnais | A | 4 | manifeste : attendu ERREUR:<motif> ou OK par test | 01–03 |
| `D3-L6-07` | Tests SQL T-25…T-29 (pagination, vues, frais de port) + run_tests.sh | A | 4 | harnais rejouable : rapport généré | 05 |
| | **Sous-total L6** | | **30** | répartition : A 8 h · B 22 h | |

#### Lot L7 — 16 déclencheurs + tests SQL associés (20 h · B (+ tests A) · S5/S6)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D3-L7-01` | Triggers ligne_commande : TRG-A1/A2/A3 (contrôle, décrément, restitution) + B1/B2 (immuabilité, snapshot) | B | 5 | stock jamais négatif, prix figé (RB-03/06/18) | L5 |
| `D3-L7-02` | Triggers produit/catégorie : TRG-B3…B6, C1, C2 (TTC, stock, suppressions protégées) | B | 4 | RB-14/16 appliqués, slug généré | 01 |
| `D3-L7-03` | Triggers commande : TRG-D1…D5 (matrice RB-11, historique automatique) | B | 5 | ORDER_STATUS_HISTORY écrit par trigger seul | 01 |
| `D3-L7-04` | Documenter les 16 déclencheurs (tableau de la partie C du document 04) | B | 2 | 1 ligne = 1 trigger = 1 règle = 1 test | 01–03 |
| `D3-L7-05` | Tests SQL directs T-02…T-24 + rapport_tests_sql.md versionné | A | 4 | 15 tests attaquent la base hors application | 01–03 |
| | **Sous-total L7** | | **20** | répartition : A 4 h · B 16 h | |


### D4 — Application Web PHP / MySQL / PDO (146 h · resp. A + C · barème iii — 30 points (avec D5))

#### Lot L8 — Socle applicatif : front controller, routeur, PDO, Database, vues de base (22 h · A · S6)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L8-01` | Front controller + routeur (listes blanches de routes et de tri) | A | 5 | une seule porte d'entrée, routes inconnues → 404 | L2–L3 |
| `D4-L8-02` | app/Config/Database.php : PDO, ERRMODE_EXCEPTION, EMULATE_PREPARES = false | A | 3 | connexion centralisée, aucune requête hors repository | 01 |
| `D4-L8-03` | Gabarit de vues + layout + helper d'échappement systématique | A | 4 | toute variable passe par htmlspecialchars($v, ENT_QUOTES…) | 01 |
| `D4-L8-04` | app/Security : AuthMiddleware, sessions durcies, jetons CSRF | A | 6 | pages privées inaccessibles sans session, CSRF vérifié | 02 |
| `D4-L8-05` | CI complète (6 jobs) + app/Config/env.example.php (jamais de secret versionné) | A | 4 | pipeline vert bloque un merge en échec | 01 |
| | **Sous-total L8** | | **22** | répartition : A 22 h | |

#### Lot L9 — Front-office : catalogue, recherche, fiche, inscription, connexion (32 h · C · S7)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L9-01` | UC-01 Catalogue : v_catalogue + pagination (12 par défaut, borne 60) | C | 6 | filtres conservés dans l'URL (ENF-05) | L8 + L5 |
| `D4-L9-02` | UC-02 Recherche/filtres : SearchRequest (validation serveur, tri liste blanche) | C | 6 | aucun tri injecté, page bornée (CT/SEC-02) | 01 |
| `D4-L9-03` | UC-03 Fiche produit : état du stock, MAX = stock, 404 si masquée | C | 5 | RB-19 : jamais de liste des produits retirés | 01 |
| `D4-L9-04` | UC-04 Création de compte : validation, sp_create_account, hash | C | 6 | doublon email refusé (T-05) | L8 |
| `D4-L9-05` | UC-05 Connexion/déconnexion : sp_get_credentials + password_verify + régénération de session | C | 6 | SEC-04/05 : 5 échecs/15 min → temporisation | 04 |
| `D4-L9-06` | Parcours visiteur complet démontré (JS désactivé inclus) | C | 3 | démo S7 : du catalogue à la connexion sans erreur | 01–05 |
| | **Sous-total L9** | | **32** | répartition : C 32 h | |

#### Lot L10 — Panier + commande (session, PanierController, CommandeController) (28 h · A + C · S8)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L10-01` | PanierSession : ajout, quantités, retrait, plafond au stock, total recalculé côté serveur | A | 6 | panier jamais cohérent avec un stock fantôme | L8 |
| `D4-L10-02` | UC-06 PanierController : contrôles serveur (disponible()) avant tout ajout | A | 5 | 409 STOCK_INSUFFISANT avec reste retourné | 01 |
| `D4-L10-03` | Vues panier + récapitulatif serveur + badge d'articles | C | 4 | le navigateur ne reçoit AUCUN champ prix éditable | 02 |
| `D4-L10-04` | UC-07 CommandeController : adresse, confirmation, sp_create_order_from_basket | A | 3 | 1 seul aller-retour, rollback intégral si besoin | L6–L7 + 02 |
| `D4-L10-05` | Écrans commande : choix d'adresse, numéro CMD2026-0000NN, confirmation | C | 6 | postconditions du CDC (§SEC-06) visibles | 04 |
| `D4-L10-06` | UC-08 historique + détail + UC-09 annulation (vues client) | C | 4 | annulation impossible après EXPEDIEE (RB-11) | 05 |
| | **Sous-total L10** | | **28** | répartition : A 14 h · C 14 h | |

#### Lot L11 — Back-office : produits, catégories, stocks, commandes, statuts, indicateurs (30 h · A + C · S9)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L11-01` | AdminController + authentification admin (rôles SUPER/GESTIONNAIRE) | A | 4 | aucune route d'inscription admin (S-08) | L8 |
| `D4-L11-02` | UC-10 Produits : création/modification/masquage via sp_save_product / sp_delete_product | A | 5 | F-22 : formulaire re-rendu sans perte de saisie | 01 |
| `D4-L11-03` | UC-11 Catégories + UC-12 Stocks (réajustement SET/DELTA, motif) | A | 5 | RB-14 : suppression refusée si catégorie occupée | 01 |
| `D4-L11-04` | UC-13 Commandes + UC-14 statut (matrice RB-11) + indicateurs (sp_revenue_report) | A | 4 | transition interdite refusée et tracée | 01 |
| `D4-L11-05` | Vues back-office : listes filtrables, formulaires, alertes de stock (v_etat_stock) | C | 8 | ruptures et seuils visibles dès l'accueil admin | 02–04 |
| `D4-L11-06` | Parcours admin complet démontré (produit → stock → commande → statut) | C | 4 | démo S9 sans erreur, J4 atteint | 05 |
| | **Sous-total L11** | | **30** | répartition : A 18 h · C 12 h | |

#### Lot L12 — Durcissement sécurité (CSRF, échappement, autorisation, en-têtes, journaux) (20 h · C · S9/S10)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L12-01` | CSRF vérifié sur chaque méthode mutative | C | 4 | S-01/S-02 : POST sans jeton → 419 | L9–L11 |
| `D4-L12-02` | Autorisation : contrôle d'appartenance, 404 sur id étranger (IDOR) | C | 5 | T-18 : commande.php?id=étranger → 404 | 01 |
| `D4-L12-03` | Échappement systématique : revue des vues + htmlspecialchars partout | C | 4 | contrôle statique n°2 vert | L9–L11 |
| `D4-L12-04` | En-têtes durcis, cookie HttpOnly/SameSite, messages d'erreur génériques | C | 3 | SEC-10/12 : aucune fuite d'information | 02 |
| `D4-L12-05` | tests/security/controles.sh : 10 contrôles statiques au vert en CI | C | 4 | aucune concaténation SQL, session durcie… | 01–04 |
| | **Sous-total L12** | | **20** | répartition : C 20 h | |

#### Lot L13 — JavaScript client (panier, filtres, validation live, accessibilité) (14 h · C · S10)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D4-L13-01` | JS panier : badge, MAJ AJAX, repli fonctionnel sans JS | C | 5 | F-13 : parcours complet JS désactivé | L10 |
| `D4-L13-02` | Validation live des formulaires + écoute clavier des quantités | C | 4 | la borne serveur reste la seule qui compte | 01 |
| `D4-L13-03` | Accessibilité : labels, focus visible, aria-live sur le panier | C | 3 | audit manuel ENF-08 sans erreur bloquante | 01 |
| `D4-L13-04` | Check-list « JS = confort seulement » (test F-13) | C | 2 | documentée dans le doc de tests | 01–03 |
| | **Sous-total L13** | | **14** | répartition : C 14 h | |


### D5 — Tests & validation (document de tests) (24 h · resp. B + A · barème iii — −3 pts si le document manque)

#### Lot L14 — Tests & validation : harnais, 29 tests SQL, cas fonctionnels, plan de recette, doc (24 h · B + A · S10/S11)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D5-L14-01` | Doc de tests : dispositif, stratégie, niveaux, environnement | B | 4 | structure §1–§2 du document 02 | L6 |
| `D5-L14-02` | Rejouer les 29 tests SQL + rapport final versionné | B | 4 | 29/29 conformes, rapport_tests_sql.md à jour | L5–L7 |
| `D5-L14-03` | Cas fonctionnels F-01…F-31 : exécution et preuves (captures) | B | 3 | chaque EF-* a au moins un test exécuté | L9–L11 |
| `D5-L14-04` | Concurrence : reserver.sh (20 demandes / 12 exemplaires → 12 OK, 8 refus) | B | 3 | pas de survente prouvée | L10 |
| `D5-L14-05` | Matrice de traçabilité exigence → test + PV de recette | A | 5 | traçabilité §9 du document 02 complète | 02–04 |
| `D5-L14-06` | Performance : mesurer.sh p95 < 500 ms + correctifs | A | 5 | ENF-01 mesuré et consigné | L9–L11 |
| | **Sous-total L14** | | **24** | répartition : A 10 h · B 14 h | |


### D6 — Soutenance (15 min + questions) (14 h · resp. A + B + C · barème iv — 10 points)

#### Lot L16 — Soutenance : support, trame, répétitions chronométrées, questions anticipées (14 h · A + B + C · S12)

| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |
|---|---|---|---:|---|---|
| `D6-L16-01` | Support de soutenance : déroulé minute par minute, choix des démos | A | 4 | docs/03 : 15 min couvrant barème et démos | L15 |
| `D6-L16-02` | Questions anticipées : sécurité, règles métier, choix de conception, réponses courtes | B | 5 | §3 du docs/03 connu par cœur | L14 |
| `D6-L16-03` | Deux répétitions chronométrées + check-list de la dernière demi-heure | C | 5 | 15 min ± 30 s tenues deux fois | 01–02 |
| | **Sous-total L16** | | **14** | répartition : A 4 h · B 5 h · C 5 h | |


---

## 6. Estimation bottom-up : synthèse et équilibre des charges

### 6.1 Remontée des charges

| Niveau | Détail | Charge |
|---|---|---:|
| Tâches (niveau 4) | 86 tâches individuelles estimées | **360 h** |
| Lots (niveau 3) | 16 lots (L1→L16), sous-totaux des tâches | 360 h |
| Divisions (niveau 2) | 6 divisions : D1 24 h · D2 54 h · D3 98 h · D4 146 h · D5 24 h · D6 14 h | 360 h |
| Projet (niveau 1) | 3 personnes × ~120 h sur 12 semaines | **360 h** |

### 6.2 Charge par personne (pas de sur-allocation)

| Personne | Rôle (RACI CDC §3.3) | Charge | Part | Plafond | Verdict |
|---|---|---:|---:|---:|---|
| **A** | chef de projet, back-office, intégration/CI | 122 h | 34% | 122 h | ✅ au plafond |
| **B** | modèle et base de données, tests SQL | 117 h | 32% | 122 h | ✅ |
| **C** | présentation, sécurité, JS, tests | 121 h | 34% | 122 h | ✅ |
| | **Total** | **360 h** | 100 % | 3 × 122 h | équilibré (écart max 5 h) |

Chaque personne porte sa division principale **et** intervient dans les deux autres
(ex. : A relit le document de conception D3 et exécute des tests SQL ; B rédige des questions de soutenance ;
C formalise les règles métier et exécute le parcours admin) — condition d'une évaluation individuelle non ambiguë.

### 6.3 Plan de charge hebdomadaire

*Les charges des lots pluri-hebdomadaires (L2, L3, L7, L12, L14) sont réparties également sur leurs semaines.
Valeurs arrondies à l'heure.*

| Semaine | S1 | S2 | S3 | S4 | S5 | S6 | S7 | S8 | S9 | S10 | S11 | S12 | **Total** |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| **A** | 23 | 7 | 4 | 0 | 10 | 24 | 0 | 14 | 18 | 5 | 13 | 4 | **122** |
| **B** | 0 | 8 | 32 | 20 | 30 | 8 | 0 | 0 | 0 | 7 | 7 | 5 | **117** |
| **C** | 6 | 12 | 6 | 0 | 0 | 0 | 32 | 14 | 22 | 24 | 0 | 5 | **121** |
| **Équipe** | **29** | **27** | **42** | **20** | **40** | **32** | **32** | **28** | **40** | **36** | **20** | **14** | **360** |

**Pics assumés et anticipés** : S3 (B ≈ 32 h, lot L4 MCD/MLD), S5 (B ≈ 30 h, procédures), S7 (C = 32 h, front-office), S9 (équipe 40 h, back-office + sécurité).
Trois parades, revues chaque vendredi (CDC §13.5) : (i) les tâches amont des lots critiques démarrent dès la quinzaine précédente,
(ii) L13 et L15 (22 h au total, sans dépendance forte) servent de **variable d'ajustement**,
(iii) au-delà de 15 h/personne/semaine pendant deux semaines consécutives, le chef de projet applique la règle de réduction de périmètre du CDC §13.3 (jamais par suppression d'une pénalité).

---

## 7. Gantt (12 semaines)

<!--GANTT_HTML-->

```text
Lot |  S1  |  S2  |  S3  |  S4  |  S5  |  S6  |  S7  |  S8  |  S9  | S10  | S11  | S12 
---- | ---- | ---- | ---- | ---- | ---- | ---- | ---- | ---- | ---- | ---- | ---- | ----
L1   |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    16 h  A
L2   |  ██  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    26 h  A + C
L3   |   ·  |  ██  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    28 h  B + C
L4   |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    28 h  B (+ relecture A)
L5   |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    20 h  B
L6   |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    30 h  B (+ tests A)
L7   |   ·  |   ·  |   ·  |   ·  |  ██  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    20 h  B (+ tests A)
L8   |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·    22 h  A
L9   |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·  |   ·    32 h  C
L10  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·  |   ·    28 h  A + C
L11  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·  |   ·    30 h  A + C
L12  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |  ██  |   ·  |   ·    20 h  C
L13  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·  |   ·    14 h  C
L14  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |  ██  |   ·    24 h  B + A
L15  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██  |   ·     8 h  A
L16  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |   ·  |  ██    14 h  A + B + C
```

Jalons intermédiaires (CDC §13.4) : **J1** fin S2 (CDC validé) · **J2 bis** fin S3 (3 types de diagrammes livrés) ·
**J3** fin S7 (parcours visiteur complet) · **J4** fin S9 (back-office + sécurité) · **J5** fin S10 (recette exécutée) · **J6** S12 (soutenance).
Trois dates sont figées par le calendrier pédagogique : S2, S10, S12.

---

## 8. Dépendances et chemin critique

```text
L1 ── L2 ── L3 ── L4 ── L5 ── L6 ── L7 ── L8 ── L9 ── L10 ── L11 ── L12 ── L14 ── L15 ── L16
 └─────────────── parallèle : D2 (UML) / D3 (BD) dès S2 ───────────────┘        │
                                     L8 (socle) peut démarrer dès que L2-L3 sont posés ←──────┘
                                     L12, L13 (sécurité, JS) courent en parallèle de L14
```

**Chemin critique** (la chaîne qui fixe la date de fin) :

> `L1 → L2 → L4 → L5 → L6 → L7 → L8 → L10 → L11 → L14 → L15 → L16`

- **L4→L7 (la base)** est le segment le plus contraint : toute l'application (L9-L11) appelle les vues,
  les procédures et les triggers ; un retard d'une semaine en S3-S5 décale mécaniquement J3, J4 et J5.
- **L10 (panier/commande)** est la tâche la plus riche en dépendances (session + procédures + triggers + CSRF) :
  c'est le lot à protéger en priorité (d'où sa position S8, après le socle et le front-office).
- **Marge faible** : L3 (diagrammes) peut glisser d'une semaine sans décaler la fin, à condition que L4 démarre
  la semaine suivante ; L12/L13 ont une semaine de marge avant J5.
- **Hors chemin critique** : L13 (JS) peut être réduite (repli : validation live du panier seulement) sans toucher au barème.

---

## 9. Coûts (le seul « budget » rare du projet : le temps)

| Poste | Base | Valeur |
|---|---|---:|
| Charge de production | 360 h × 60 €/h (indice pédagogique théorique) | 21 600 € théoriques |
| Licences, hébergement, données | tout open source / poste personnel | 0 € |
| **Budget externe réel** | | **0 €** |

Le suivi réel se fait en **heures** : la table §6.3 est pointée chaque vendredi par A dans le wiki GitLab
(charge restante par lot), conformément au CDC §13.5.

---

## 10. Exploitation dans GitLab (la WBS devient le tableau du projet)

1. **Une issue par tâche de niveau 4** : titre `[(D3-L6-04)] sp_create_order, sp_add_order_line…` ;
   description = définition de fini + dépendances ; 86 issues créées dès S1 (bulk import CSV).
2. **Labels** : `D1`…`D6` (couleur de division), `L1`…`L16`, `A`/`B`/`C`, `jalon-J1`…`jalon-J6`.
3. **Board** : colonnes *À faire → En cours → En revue (MR) → Fermé* ; une colonne par jalon en vue *Listes de jalons*.
4. **MR** : une MR par lot minimum, **mergée par un autre développeur que l'auteur** (auto-merge interdit) ;
   la CI doit être verte (6 jobs, dont base-tests = 29/29).
5. **Suivi individuel** (barème v) : `git shortlog -sne main` doit montrer 3 auteurs > 20 commits ;
   la répartition cible par zone (B ≈ 45 % de `sql/`, A ≈ 40 % de `app/Controller/`, C ≈ 45 % de `app/View/` et `public/js/`)
   est celle du CDC §14.3 ; chacun garde ≥ 5 commits dans les trois zones.

---

## 11. Gouvernance : définition de « terminé » et règle de repli

**Une tâche de niveau 4 est fermée seulement si :**

- sa **définition de fini** est observée (colonne dédiée dans chaque table du §5) ;
- le code ou le document passe la **CI** (php-lint, 29 tests SQL, contrôles de sécurité, rendu des diagrammes) ;
- une **autre personne** a relu la MR (revue croisée systématique) ;
- la **documentation** liée (CDC, doc de conception, doc de tests, README) est mise à jour dans la même MR.

**Règle de repli si groupe de 2** (CDC §13.3) — la WBS se réduit ainsi, sans supprimer une exigence pénalisée :

| Retrait | Tâches concernées | Économie |
|---|---|---:|
| L13 réduit à la validation live du panier | `D4-L13-02…04` supprimées | −6 h |
| L8 simplifié (table de routes minimale, pas de routeur maison) | `D4-L8-01` réduite | −3 h |
| `EF-ADM-09` (indicateurs) et `EF-ADM-10` (comptes admin) non livrés | `D4-L11-04` réduite | −2 h |
| Procédures réduites à 8, triggers à 8 (en gardant TRG-A1/A2/A3, B2, B5, D1, D4) | `D3-L6-*` et `D3-L7-*` allégées | −25 h |
| **Total repli** | | **≈ −36 h → 324 h pour 2 personnes (~160 h chacune, périmètre validé en S1)** |

---

*WBS MiniShop v1.0 — document généré depuis la même source de données que le CDC §13.3 (charges réconciliées) ;
méthode : structure de découpage en 4 niveaux, estimation bottom-up, plan de charge, Gantt, chemin critique.*