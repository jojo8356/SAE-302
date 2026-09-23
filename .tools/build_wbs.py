# -*- coding: utf-8 -*-
"""Génère la WBS du projet MiniShop : docs/05-wbs-projet.md (+ .html autoportant).
Méthode : structure 4 niveaux (projet → divisions → lots → tâches individuelles),
estimation bottom-up, plan de charge, Gantt, chemin critique, coûts, exploitation GitLab."""
import pathlib, re, markdown

DOCS = pathlib.Path("/home/user/docs")
OUT_MD = DOCS / "05-wbs-projet.md"
OUT_HTML = pathlib.Path("/home/user/artefacts/05-wbs-projet.html")  # artefact rendu

# =====================================================================
# DONNÉES — niveaux 3 (lots) et 4 (tâches individuelles)
# tâche = (id, libellé, responsable, charge_h, définition_de_fini, dépendances)
# =====================================================================
LOTS = {
 "L1": dict(nom="Cadrage, relecture du sujet, grille de conformité, squelette du dépôt, README",
            resp="A", semaines=["S1"], tasks=[
   ("D1-L1-01","Ouvrir le dépôt privé GitLab, inviter les 3 développeurs et l'enseignante (Reporter), protéger main, désactiver le squash","A",2,"accès effectifs + courriel « Composition SAE » envoyé","—"),
   ("D1-L1-02","Squelette du dépôt : arborescence app/ public/ sql/ tests/ docs/ scripts/ + .gitignore","A",2,"arborescence conforme au CDC §9.2","01"),
   ("D1-L1-03","README v0 : prérequis, installation cible, membres et rôles (RACI)","A",3,"README visible à la racine, lu par les 3 membres","02"),
   ("D1-L1-04","Grille de conformité au barème (CDC §16) épinglée en issue GitLab","A",3,"issue créée : 5 livrables + pénalités suivis","01"),
   ("D1-L1-05","Charte GitLab : branches, MR croisées, commits conventionnels, 1 issue par UC/EF","A",2,"wiki + modèle de MR (annexe 17.9)","01"),
   ("D1-L1-06","CI v0 : .gitlab-ci.yml avec job php-lint","A",2,"pipeline vert sur un commit vide","02"),
   ("D1-L1-07","Réunion de cadrage : relecture du sujet ligne à ligne, périmètre, règle de repli groupe de 2","A",2,"compte-rendu dans le wiki du dépôt","—"),
 ]),
 "L2": dict(nom="CDC : présentation, objectifs, acteurs, fonctionnalités",
            resp="A + C", semaines=["S1","S2"], tasks=[
   ("D2-L2-01","Rédiger §1 Présentation + §2 Objectifs (objectifs techniques mesurables)","A",4,"sections relues, conformité au sujet pointée","L1"),
   ("D2-L2-02","Rédiger §3 Acteurs (3 primaires + 2 secondaires) et RACI de l'équipe","A",3,"tableau RACI 3 personnes validé","01"),
   ("D2-L2-03","Rédiger §4 Fonctionnalités : table exigence ↔ UC ↔ écran ↔ test (EF-VIS/CLI/ADM)","C",6,"couverture des 14 UC vérifiable ligne à ligne","02"),
   ("D2-L2-04","Rédiger §7 Contraintes techniques (CT-01…CT-09)","A",4,"contraintes imposées vs retenues séparées","01"),
   ("D2-L2-05","Rédiger §8 Sécurité : SEC-01…SEC-14 + matrice menaces → contre-mesures","C",6,"les 5 exigences du sujet (SQLi, mots de passe, XSS, sessions, autorisation) couvertes","01"),
   ("D2-L2-06","Relecture croisée §1→§8 + pointage des pénalités du barème","A",3,"grille §16.2 entièrement au vert","01–05"),
 ]),
 "L3": dict(nom="Diagrammes UML (cas, classes, séquences) + règles métier formalisées",
            resp="B + C", semaines=["S2","S3"], tasks=[
   ("D2-L3-01","Diagrammes de cas d'utilisation : vue globale (3 acteurs, 14 UC) + 2 zooms (commandes, catalogue)","B",6,"PNG + sources .puml versionnés, rendus par render.sh","L2"),
   ("D2-L3-02","Descriptions textuelles des 3 UC imposés (UC-07, UC-02, UC-14) + fiches des 11 autres","B",4,"annexe 17.1 complète (les 14 cas)","01"),
   ("D2-L3-03","Diagramme de classes (domaine, contrôle, repositories) + justification des choix et cardinalités","B",6,"8 classes du domaine + cardinalités argumentées","01"),
   ("D2-L3-04","Formaliser RB-01…RB-20 : énoncé, forme formelle, point d'application, test associé","C",4,"chaque règle a un point de contrôle et un test","L2"),
   ("D2-L3-05","5 diagrammes de séquence (authentification, recherche, catalogue, panier, commande) avec SP/triggers","C",6,"les 4 séquences imposées + 1 bonus, SP et triggers visibles","03–04"),
   ("D2-L3-06","Diagramme d'activité (UC-07) + diagramme d'états de la commande + rendu PNG complet","C",2,"8 diagrammes rendus par docs/diagrams/render.sh","05"),
 ]),
 "L4": dict(nom="MCD → MLD → normalisation, jeu de données",
            resp="B (+ relecture A)", semaines=["S3"], tasks=[
   ("D3-L4-01","Identifier entités et associations ; dessiner le MCD (PlantUML)","B",6,"7 entités + table parametre, associations nommées","L3"),
   ("D3-L4-02","Justifier chaque cardinalité ligne à ligne + consigner les refus de conception","B",5,"tableau cardinalité/justification/test complet","01"),
   ("D3-L4-03","Transformer en MLD : 8 relations (dont parametre) + 3 vues","B",5,"règle de conversion 1NF appliquée et documentée","02"),
   ("D3-L4-04","Recenser les dépendances fonctionnelles et démontrer 1FN/2FN/3FN","B",5,"table DF par table + verdict par forme normale","03"),
   ("D3-L4-05","Justifier clés primaires/étrangères + redondances assumées + 4 requêtes de preuve","B",3,"requêtes de preuve renvoyant 0 ligne","04"),
   ("D3-L4-06","Relecture croisée du document de conception (docs/04) et validation du jargon","A",4,"document autonome conforme au plan du sujet","03"),
 ]),
 "L5": dict(nom="Script SQL complet (DDL, vues, index, seed)",
            resp="B", semaines=["S4"], tasks=[
   ("D3-L5-01","DDL : 8 tables, types, CHECK (RB-02/03/05), InnoDB utf8mb4","B",6,"sql/01 charge sans erreur sur MySQL 8 et MariaDB 11","L4"),
   ("D3-L5-02","Clés étrangères (CASCADE/RESTRICT au bon endroit) + index (FULLTEXT, filtres)","B",4,"plans d'exécution justifiant chaque index","01"),
   ("D3-L5-03","3 vues : v_etat_stock, v_catalogue, v_commandes_client","B",4,"vues créées et comptées par load_db.sh","01"),
   ("D3-L5-04","Seed : 4 catégories, 12 produits (1 rupture, 1 masqué), 3 clients (password_hash), 1 admin","B",3,"jeu de démonstration RB-04/RB-19 jouable","01"),
   ("D3-L5-05","scripts/load_db.sh : chargement 01→04 + comptages de contrôle","B",3,"sortie « base chargée, procédures ≥ 5, déclencheurs ≥ 5 »","02–04"),
 ]),
 "L6": dict(nom="17 procédures stockées + 1 fonction + tests SQL associés",
            resp="B (+ tests A)", semaines=["S5"], tasks=[
   ("D3-L6-01","fn_param + sp_create_account, sp_get_credentials, sp_update_client","B",4,"comptes créés/bloqués selon RB-01/RB-12","L5"),
   ("D3-L6-02","sp_search_products : filtres, tri liste blanche, pagination, 2 jeux de résultats","B",4,"T-25 : page bornée, tri jamais injecté","01"),
   ("D3-L6-03","sp_save_product, sp_delete_product, sp_save_category, sp_delete_category, sp_adjust_stock","B",5,"RB-02/03/07/14 appliqués côté base","01"),
   ("D3-L6-04","sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order","B",6,"transaction complète, ROLLBACK si stock manque","01"),
   ("D3-L6-05","sp_update_order_status, sp_cancel_order, sp_revenue_report, sp_compute_shipping","B",3,"matrice RB-11 + port calculé/franchisé (T-28)","04"),
   ("D3-L6-06","Tests SQL T-01…T-12 (règles compte/produit/ligne) + manifeste du harnais","A",4,"manifeste : attendu ERREUR:<motif> ou OK par test","01–03"),
   ("D3-L6-07","Tests SQL T-25…T-29 (pagination, vues, frais de port) + run_tests.sh","A",4,"harnais rejouable : rapport généré","05"),
 ]),
 "L7": dict(nom="16 déclencheurs + tests SQL associés",
            resp="B (+ tests A)", semaines=["S5","S6"], tasks=[
   ("D3-L7-01","Triggers ligne_commande : TRG-A1/A2/A3 (contrôle, décrément, restitution) + B1/B2 (immuabilité, snapshot)","B",5,"stock jamais négatif, prix figé (RB-03/06/18)","L5"),
   ("D3-L7-02","Triggers produit/catégorie : TRG-B3…B6, C1, C2 (TTC, stock, suppressions protégées)","B",4,"RB-14/16 appliqués, slug généré","01"),
   ("D3-L7-03","Triggers commande : TRG-D1…D5 (matrice RB-11, historique automatique)","B",5,"ORDER_STATUS_HISTORY écrit par trigger seul","01"),
   ("D3-L7-04","Documenter les 16 déclencheurs (tableau de la partie C du document 04)","B",2,"1 ligne = 1 trigger = 1 règle = 1 test","01–03"),
   ("D3-L7-05","Tests SQL directs T-02…T-24 + rapport_tests_sql.md versionné","A",4,"15 tests attaquent la base hors application","01–03"),
 ]),
 "L8": dict(nom="Socle applicatif : front controller, routeur, PDO, Database, vues de base",
            resp="A", semaines=["S6"], tasks=[
   ("D4-L8-01","Front controller + routeur (listes blanches de routes et de tri)","A",5,"une seule porte d'entrée, routes inconnues → 404","L2–L3"),
   ("D4-L8-02","app/Config/Database.php : PDO, ERRMODE_EXCEPTION, EMULATE_PREPARES = false","A",3,"connexion centralisée, aucune requête hors repository","01"),
   ("D4-L8-03","Gabarit de vues + layout + helper d'échappement systématique","A",4,"toute variable passe par htmlspecialchars($v, ENT_QUOTES…)","01"),
   ("D4-L8-04","app/Security : AuthMiddleware, sessions durcies, jetons CSRF","A",6,"pages privées inaccessibles sans session, CSRF vérifié","02"),
   ("D4-L8-05","CI complète (6 jobs) + app/Config/env.example.php (jamais de secret versionné)","A",4,"pipeline vert bloque un merge en échec","01"),
 ]),
 "L9": dict(nom="Front-office : catalogue, recherche, fiche, inscription, connexion",
            resp="C", semaines=["S7"], tasks=[
   ("D4-L9-01","UC-01 Catalogue : v_catalogue + pagination (12 par défaut, borne 60)","C",6,"filtres conservés dans l'URL (ENF-05)","L8 + L5"),
   ("D4-L9-02","UC-02 Recherche/filtres : SearchRequest (validation serveur, tri liste blanche)","C",6,"aucun tri injecté, page bornée (CT/SEC-02)","01"),
   ("D4-L9-03","UC-03 Fiche produit : état du stock, MAX = stock, 404 si masquée","C",5,"RB-19 : jamais de liste des produits retirés","01"),
   ("D4-L9-04","UC-04 Création de compte : validation, sp_create_account, hash","C",6,"doublon email refusé (T-05)","L8"),
   ("D4-L9-05","UC-05 Connexion/déconnexion : sp_get_credentials + password_verify + régénération de session","C",6,"SEC-04/05 : 5 échecs/15 min → temporisation","04"),
   ("D4-L9-06","Parcours visiteur complet démontré (JS désactivé inclus)","C",3,"démo S7 : du catalogue à la connexion sans erreur","01–05"),
 ]),
 "L10": dict(nom="Panier + commande (session, PanierController, CommandeController)",
             resp="A + C", semaines=["S8"], tasks=[
   ("D4-L10-01","PanierSession : ajout, quantités, retrait, plafond au stock, total recalculé côté serveur","A",6,"panier jamais cohérent avec un stock fantôme","L8"),
   ("D4-L10-02","UC-06 PanierController : contrôles serveur (disponible()) avant tout ajout","A",5,"409 STOCK_INSUFFISANT avec reste retourné","01"),
   ("D4-L10-03","Vues panier + récapitulatif serveur + badge d'articles","C",4,"le navigateur ne reçoit AUCUN champ prix éditable","02"),
   ("D4-L10-04","UC-07 CommandeController : adresse, confirmation, sp_create_order_from_basket","A",3,"1 seul aller-retour, rollback intégral si besoin","L6–L7 + 02"),
   ("D4-L10-05","Écrans commande : choix d'adresse, numéro CMD2026-0000NN, confirmation","C",6,"postconditions du CDC (§SEC-06) visibles","04"),
   ("D4-L10-06","UC-08 historique + détail + UC-09 annulation (vues client)","C",4,"annulation impossible après EXPEDIEE (RB-11)","05"),
 ]),
 "L11": dict(nom="Back-office : produits, catégories, stocks, commandes, statuts, indicateurs",
             resp="A + C", semaines=["S9"], tasks=[
   ("D4-L11-01","AdminController + authentification admin (rôles SUPER/GESTIONNAIRE)","A",4,"aucune route d'inscription admin (S-08)","L8"),
   ("D4-L11-02","UC-10 Produits : création/modification/masquage via sp_save_product / sp_delete_product","A",5,"F-22 : formulaire re-rendu sans perte de saisie","01"),
   ("D4-L11-03","UC-11 Catégories + UC-12 Stocks (réajustement SET/DELTA, motif)","A",5,"RB-14 : suppression refusée si catégorie occupée","01"),
   ("D4-L11-04","UC-13 Commandes + UC-14 statut (matrice RB-11) + indicateurs (sp_revenue_report)","A",4,"transition interdite refusée et tracée","01"),
   ("D4-L11-05","Vues back-office : listes filtrables, formulaires, alertes de stock (v_etat_stock)","C",8,"ruptures et seuils visibles dès l'accueil admin","02–04"),
   ("D4-L11-06","Parcours admin complet démontré (produit → stock → commande → statut)","C",4,"démo S9 sans erreur, J4 atteint","05"),
 ]),
 "L12": dict(nom="Durcissement sécurité (CSRF, échappement, autorisation, en-têtes, journaux)",
             resp="C", semaines=["S9","S10"], tasks=[
   ("D4-L12-01","CSRF vérifié sur chaque méthode mutative","C",4,"S-01/S-02 : POST sans jeton → 419","L9–L11"),
   ("D4-L12-02","Autorisation : contrôle d'appartenance, 404 sur id étranger (IDOR)","C",5,"T-18 : commande.php?id=étranger → 404","01"),
   ("D4-L12-03","Échappement systématique : revue des vues + htmlspecialchars partout","C",4,"contrôle statique n°2 vert","L9–L11"),
   ("D4-L12-04","En-têtes durcis, cookie HttpOnly/SameSite, messages d'erreur génériques","C",3,"SEC-10/12 : aucune fuite d'information","02"),
   ("D4-L12-05","tests/security/controles.sh : 10 contrôles statiques au vert en CI","C",4,"aucune concaténation SQL, session durcie…","01–04"),
 ]),
 "L13": dict(nom="JavaScript client (panier, filtres, validation live, accessibilité)",
             resp="C", semaines=["S10"], tasks=[
   ("D4-L13-01","JS panier : badge, MAJ AJAX, repli fonctionnel sans JS","C",5,"F-13 : parcours complet JS désactivé","L10"),
   ("D4-L13-02","Validation live des formulaires + écoute clavier des quantités","C",4,"la borne serveur reste la seule qui compte","01"),
   ("D4-L13-03","Accessibilité : labels, focus visible, aria-live sur le panier","C",3,"audit manuel ENF-08 sans erreur bloquante","01"),
   ("D4-L13-04","Check-list « JS = confort seulement » (test F-13)","C",2,"documentée dans le doc de tests","01–03"),
 ]),
 "L14": dict(nom="Tests & validation : harnais, 29 tests SQL, cas fonctionnels, plan de recette, doc",
             resp="B + A", semaines=["S10","S11"], tasks=[
   ("D5-L14-01","Doc de tests : dispositif, stratégie, niveaux, environnement","B",4,"structure §1–§2 du document 02","L6"),
   ("D5-L14-02","Rejouer les 29 tests SQL + rapport final versionné","B",4,"29/29 conformes, rapport_tests_sql.md à jour","L5–L7"),
   ("D5-L14-03","Cas fonctionnels F-01…F-31 : exécution et preuves (captures)","B",3,"chaque EF-* a au moins un test exécuté","L9–L11"),
   ("D5-L14-04","Concurrence : reserver.sh (20 demandes / 12 exemplaires → 12 OK, 8 refus)","B",3,"pas de survente prouvée","L10"),
   ("D5-L14-05","Matrice de traçabilité exigence → test + PV de recette","A",5,"traçabilité §9 du document 02 complète","02–04"),
   ("D5-L14-06","Performance : mesurer.sh p95 < 500 ms + correctifs","A",5,"ENF-01 mesuré et consigné","L9–L11"),
 ]),
 "L15": dict(nom="Mise en ligne de démo, deploy.sh, README final, arborescence propre",
             resp="A", semaines=["S11"], tasks=[
   ("D1-L15-01","scripts/deploy.sh : hashes aléatoires, emails de démo régénérés","A",3,"démo reinstallable en 1 commande, SEC-10 respecté","L14"),
   ("D1-L15-02","README final : installation 5 commandes, comptes, tests, sauvegarde","A",3,"un tiers installe et lance en ≤ 15 min (ENF-13)","01"),
   ("D1-L15-03","Nettoyage de l'arborescence + tag v1.0-rendu","A",2,"shortlog équilibré, tag posé, MR mergées","02"),
 ]),
 "L16": dict(nom="Soutenance : support, trame, répétitions chronométrées, questions anticipées",
             resp="A + B + C", semaines=["S12"], tasks=[
   ("D6-L16-01","Support de soutenance : déroulé minute par minute, choix des démos","A",4,"docs/03 : 15 min couvrant barème et démos","L15"),
   ("D6-L16-02","Questions anticipées : sécurité, règles métier, choix de conception, réponses courtes","B",5,"§3 du docs/03 connu par cœur","L14"),
   ("D6-L16-03","Deux répétitions chronométrées + check-list de la dernière demi-heure","C",5,"15 min ± 30 s tenues deux fois","01–02"),
 ]),
}

DIVISIONS = [
 ("D1","Pilotage, dépôt GitLab & intégration","barème v — contribution GitLab + README (10 pts, individuel)","A",["L1","L15"]),
 ("D2","Document de spécification du système","barème i — 20 points","A + C",["L2","L3"]),
 ("D3","Conception de la base de données & SQL","barème ii — 20 points","B",["L4","L5","L6","L7"]),
 ("D4","Application Web PHP / MySQL / PDO","barème iii — 30 points (avec D5)","A + C",["L8","L9","L10","L11","L12","L13"]),
 ("D5","Tests & validation (document de tests)","barème iii — −3 pts si le document manque","B + A",["L14"]),
 ("D6","Soutenance (15 min + questions)","barème iv — 10 points","A + B + C",["L16"]),
]

LOT_H = {k: sum(t[3] for t in v["tasks"]) for k, v in LOTS.items()}
COULEURS = {"D1":"#64748b","D2":"#7c2d12","D3":"#b45309","D4":"#15803d","D5":"#b91c1c","D6":"#6d28d9"}

# ---------- assertions de cohérence (bottom-up) ----------
TOTALS = {"L1":16,"L2":26,"L3":28,"L4":28,"L5":20,"L6":30,"L7":20,"L8":22,"L9":32,"L10":28,"L11":30,"L12":20,"L13":14,"L14":24,"L15":8,"L16":14}
for lot, tot in TOTALS.items():
    assert LOT_H[lot] == tot, f"{lot} : {LOT_H[lot]} ≠ {tot}"
GRAND = sum(LOT_H.values()); assert GRAND == 360, GRAND
PERS = {"A":0,"B":0,"C":0}
for lot in LOTS.values():
    for t in lot["tasks"]:
        PERS[t[2]] += t[3]
assert PERS == {"A":122,"B":117,"C":121}, PERS
N_TASKS = sum(len(v["tasks"]) for v in LOTS.values())
DIV_H = {d[0]: sum(LOT_H[l] for l in d[4]) for d in DIVISIONS}
assert sum(DIV_H.values()) == 360

# ---------- plan de charge hebdomadaire (répartition égale sur les semaines du lot) ----------
SEMAINES = [f"S{i}" for i in range(1, 13)]
plan = {s: {"A":0,"B":0,"C":0} for s in SEMAINES}
for lid, lot in LOTS.items():
    n = len(lot["semaines"])
    for t in lot["tasks"]:
        part = t[3] / n
        for s in lot["semaines"]:
            plan[s][t[2]] += part
PLAN_TOT = {s: round(sum(plan[s].values())) for s in SEMAINES}

# =====================================================================
# GÉNÉRATION MARKDOWN
# =====================================================================
md = []
A = md.append

A("# MiniShop — WBS (Work Breakdown Structure) : divisions, lots et tâches individuelles")
A("")
A("**Structure de découpage du projet MiniShop selon la méthode WBS/OTP : 4 niveaux (projet → divisions → lots → tâches),")
A("estimation *bottom-up* de chaque tâche individuelle, plan de charge par personne, Gantt, chemin critique et coûts.**")
A("")
A("| | |")
A("|---|---|")
A("| **Projet** | MiniShop — site e-commerce avec back-office (SAE — BUT Informatique / L3, Université Côte d'Azur) |")
A("| **Équipe** | 3 développeurs : **A** chef de projet & back-office · **B** modèle & base de données · **C** présentation, sécurité & tests |")
A("| **Effort total** | **360 h = 3 × 120 h** (estimation *bottom-up* : la somme des 86 tâches de niveau 4) · calendrier **S1 → S12** |")
A("| **Divisions (niveau 2)** | 6 — alignées une à une sur les 5 lignes du barème + la gestion de projet |")
A("| **Lots (niveau 3)** | 16 (L1→L16, identiques au CDC §13.3, charges réconciliées) |")
A("| **Tâches (niveau 4)** | **86 tâches individuelles** — chacune est assignable, estimable et vérifiable (règle d'arrêt WBS) |")
A("| **Documents liés** | `docs/01-cahier-des-charges-MiniShop.md` (§13.3/§13.4) · `docs/02-document-tests-validation.md` · `docs/03-support-soutenance.md` · `docs/04-conception-bd-et-sql.md` · `README.md` |")
A("| **Version** | 1.0 — 23 septembre 2026 |")
A("")
A("---")
A("")
A("## 1. Objet et méthode")
A("")
A("La **Work Breakdown Structure** (structure de découpage de projet, ou organigramme des tâches) hiérarchise le travail en 4 niveaux :")
A("")
A("| Niveau | Contenu | Ici |")
A("|---|---|---|")
A("| **1** | le projet (produit final) | MiniShop : boutique + back-office + dossier de conception et de validation |")
A("| **2** | les **divisions** = livrables majeurs | 6 divisions D1→D6, une par ligne du barème (+ pilotage) |")
A("| **3** | les **lots** = sous-livrables | 16 lots L1→L16 (reprend le CDC §13.3) |")
A("| **4** | les **tâches individuelles** | **86 tâches**, estimées en heures et assignées à A, B ou C |")
A("")
A("**Règle d'arrêt** (jusqu'où descendre) : on ne descend pas plus bas que le niveau 4, car chaque tâche y satisfait les trois critères de la méthode —")
A("1. **assignable** à une ou plusieurs ressources (colonne *Resp.*),")
A("2. **estimable** en charge (colonne *h*),")
A("3. **vérifiable** (colonne *Définition de fini* — le livrable de la tâche est observable).")
A("")
A("**Estimation *bottom-up*** : les charges sont estimées au niveau 4, puis totalisées lot par lot, division par division, jusqu'au total projet.")
A("C'est l'inverse d'une estimation globale « au doigt mouillé » : chaque heure du total projet remonte d'une tâche nommée.")
A("")
A("**Identification** : `D<n>-L<lot>-<nn>` (ex. `D3-L6-04`). Chaque tâche devient une **issue GitLab** portant ce titre")
A("(cf. §10), ce qui relie WBS, barème et trace de contribution individuelle.")
A("")
A("**Note de version** — les charges par lot du CDC §13.3 ont été **réconciliées** avec cette estimation bottom-up")
A("(la première mouture totalisait 402 h de lots contre 362 h annoncées, et la charge C dépassait le plafond affiché) ;")
A("le CDC corrigé et la WBS affichent désormais le même total : **360 h = 3 × 120 h** (§13.2 du CDC).")
A("")
A("---")
A("")
A("## 2. Niveau 1 — le projet")
A("")
A("**MiniShop** : un site e-commerce (catalogue, panier, commandes) + un back-office (produits, catégories, stocks, commandes)")
A("en **PHP 8 / MySQL (PDO) / MVC 3 couches**, avec procédures stockées et déclencheurs, livré avec son dossier de")
A("spécification, sa base conçue et normalisée (3FN), son application testée et validée, et une soutenance de 15 minutes.")
A("")
A("> Critère de succès global : **zéro pénalité** sur les 5 lignes du barème (i 20 pts · ii 20 pts · iii 30 pts · iv 10 pts · v 10 pts),")
A("> ce qui suppose : 3 types de diagrammes UML présents, sécurité traitée (SQLi, mots de passe, XSS, sessions, autorisation),")
A("> 20 règles métier appliquées **en base**, script SQL rejouable, ≥ 5 procédures, ≥ 5 déclencheurs, MVC réel, README d'installation,")
A("> document de tests et dépôt GitLab avec contribution régulière.")
A("")
A("---")
A("")
A("## 3. Niveau 2 — les 6 divisions du projet")
A("")
A("| Div. | Division (livrable majeur) | Point du barème | Resp. | Lots | Charge |")
A("|---|---|---|---|---|---:|")
for did, nom, bareme, resp, lots in DIVISIONS:
    A(f"| **{did}** | {nom} | {bareme} | {resp} | {' + '.join(lots)} | **{DIV_H[did]} h** |")
A(f"| | **Total projet** | | | 16 lots | **{GRAND} h** |")
A("")
A("### Arborescence WBS (niveaux 1 → 3)")
A("")
A("<!--ARBRE_WBS-->")
A("")
A("```text")
A("MiniShop — 360 h · 3 développeurs · 12 semaines (S1→S12)")
A("│")
for i, (did, nom, bareme, resp, lots) in enumerate(DIVISIONS):
    br = "├──" if i < len(DIVISIONS)-1 else "└──"
    A(f"{br} {did} {nom} — {DIV_H[did]} h · resp. {resp} · [{bareme}]")
    for j, l in enumerate(lots):
        lb = "│   ├──" if i < len(DIVISIONS)-1 else "    ├──"
        last = (j == len(lots)-1)
        if i == len(DIVISIONS)-1: lb = "    └──" if last else "    ├──"
        elif last: lb = "│   └──"
        A(f"{lb} {l} {LOTS[l]['nom']} ({LOT_H[l]} h · {LOTS[l]['resp']} · {'/'.join(LOTS[l]['semaines'])})")
A("```")
A("")
A("*Niveau 1 = le projet ; niveau 2 = les 6 divisions (structure des livrables, « PBS ») ; niveau 3 = les lots ;")
A("le niveau 4 — le travail lui-même — est détaillé section 5.*")
A("")
A("---")
A("")
A("## 4. Niveau 3 — les 16 lots")
A("")
A("| Lot | Contenu | Div. | Resp. | Semaines | Charge |")
A("|---|---|---|---|---|---:|")
for lid, lot in LOTS.items():
    did = next(d[0] for d in DIVISIONS if lid in d[4])
    A(f"| **{lid}** | {lot['nom']} | {did} | {lot['resp']} | {'/'.join(lot['semaines'])} | {LOT_H[lid]} h |")
A(f"| | **Total** | | | S1→S12 | **{GRAND} h** |")
A("")
A("---")
A("")
A("## 5. Niveau 4 — les tâches individuelles, division par division")
A("")
A("*Lecture : chaque table liste les tâches du lot, leur responsable (A/B/C), la charge estimée en heures,")
A("la **définition de fini** (livrable observable) et les dépendances (tâches ou lots préalables).")
A("La ligne de sous-total de chaque lot redonne la charge du lot : la somme **remonte** — rien n'est ajouté au niveau lot qui ne vienne d'une tâche.*")
A("")
for did, nom, bareme, resp, lots in DIVISIONS:
    A(f"### {did} — {nom} ({DIV_H[did]} h · resp. {resp} · {bareme})")
    A("")
    for lid in lots:
        lot = LOTS[lid]
        A(f"#### Lot {lid} — {lot['nom']} ({LOT_H[lid]} h · {lot['resp']} · {'/'.join(lot['semaines'])})")
        A("")
        A("| ID | Tâche individuelle | Resp. | h | Définition de fini | Dépend de |")
        A("|---|---|---|---:|---|---|")
        for tid, lbl, r, h, dod, dep in lot["tasks"]:
            A(f"| `{tid}` | {lbl} | {r} | {h} | {dod} | {dep} |")
        sub = {k: sum(t[3] for t in lot["tasks"] if t[2] == k) for k in "ABC" if any(t[2] == k for t in lot["tasks"])}
        A(f"| | **Sous-total {lid}** | | **{LOT_H[lid]}** | répartition : " + " · ".join(f"{k} {v} h" for k, v in sub.items()) + " | |")
        A("")
    A("")
A("---")
A("")
A("## 6. Estimation bottom-up : synthèse et équilibre des charges")
A("")
A("### 6.1 Remontée des charges")
A("")
A("| Niveau | Détail | Charge |")
A("|---|---|---:|")
A(f"| Tâches (niveau 4) | {N_TASKS} tâches individuelles estimées | **{GRAND} h** |")
A(f"| Lots (niveau 3) | 16 lots (L1→L16), sous-totaux des tâches | {GRAND} h |")
A(f"| Divisions (niveau 2) | 6 divisions : " + " · ".join(f"{d[0]} {DIV_H[d[0]]} h" for d in DIVISIONS) + f" | {GRAND} h |")
A(f"| Projet (niveau 1) | 3 personnes × ~120 h sur 12 semaines | **{GRAND} h** |")
A("")
A("### 6.2 Charge par personne (pas de sur-allocation)")
A("")
A("| Personne | Rôle (RACI CDC §3.3) | Charge | Part | Plafond | Verdict |")
A("|---|---|---:|---:|---:|---|")
A(f"| **A** | chef de projet, back-office, intégration/CI | {PERS['A']} h | {PERS['A']/GRAND:.0%} | 122 h | ✅ au plafond |")
A(f"| **B** | modèle et base de données, tests SQL | {PERS['B']} h | {PERS['B']/GRAND:.0%} | 122 h | ✅ |")
A(f"| **C** | présentation, sécurité, JS, tests | {PERS['C']} h | {PERS['C']/GRAND:.0%} | 122 h | ✅ |")
A(f"| | **Total** | **{GRAND} h** | 100 % | 3 × 122 h | équilibré (écart max 5 h) |")
A("")
A("Chaque personne porte sa division principale **et** intervient dans les deux autres")
A("(ex. : A relit le document de conception D3 et exécute des tests SQL ; B rédige des questions de soutenance ;")
A("C formalise les règles métier et exécute le parcours admin) — condition d'une évaluation individuelle non ambiguë.")
A("")
A("### 6.3 Plan de charge hebdomadaire")
A("")
A("*Les charges des lots pluri-hebdomadaires (L2, L3, L7, L12, L14) sont réparties également sur leurs semaines.")
A("Valeurs arrondies à l'heure.*")
A("")
A("| Semaine | S1 | S2 | S3 | S4 | S5 | S6 | S7 | S8 | S9 | S10 | S11 | S12 | **Total** |")
A("|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|")
cells_a = [f"{plan[s]['A']:.0f}" for s in SEMAINES]; cells_b = [f"{plan[s]['B']:.0f}" for s in SEMAINES]; cells_c = [f"{plan[s]['C']:.0f}" for s in SEMAINES]
A("| **A** | " + " | ".join(cells_a) + f" | **{PERS['A']}** |")
A("| **B** | " + " | ".join(cells_b) + f" | **{PERS['B']}** |")
A("| **C** | " + " | ".join(cells_c) + f" | **{PERS['C']}** |")
A("| **Équipe** | " + " | ".join(f"**{PLAN_TOT[s]}**" for s in SEMAINES) + f" | **{sum(PLAN_TOT.values())}** |")
A("")
A("**Pics assumés et anticipés** : S3 (B ≈ 32 h, lot L4 MCD/MLD), S5 (B ≈ 30 h, procédures), S7 (C = 32 h, front-office), S9 (équipe 40 h, back-office + sécurité).")
A("Trois parades, revues chaque vendredi (CDC §13.5) : (i) les tâches amont des lots critiques démarrent dès la quinzaine précédente,")
A("(ii) L13 et L15 (22 h au total, sans dépendance forte) servent de **variable d'ajustement**,")
A("(iii) au-delà de 15 h/personne/semaine pendant deux semaines consécutives, le chef de projet applique la règle de réduction de périmètre du CDC §13.3 (jamais par suppression d'une pénalité).")
A("")
A("---")
A("")
A("## 7. Gantt (12 semaines)")
A("")
A("<!--GANTT_HTML-->")
A("")
A("```text")
gw = ["Lot", *[s.center(4) for s in SEMAINES]]
A(" | ".join(gw))
A(" | ".join(["----"] + ["----"]*12))
for lid, lot in LOTS.items():
    row = [lid.ljust(4)]
    for s in SEMAINES:
        row.append(" ██ " if s in lot["semaines"] else "  · ")
    A(" | ".join([f"{lid:4}"] + row[1:]) + f"  {LOT_H[lid]:>3} h  {lot['resp']}")
A("```")
A("")
A("Jalons intermédiaires (CDC §13.4) : **J1** fin S2 (CDC validé) · **J2 bis** fin S3 (3 types de diagrammes livrés) ·")
A("**J3** fin S7 (parcours visiteur complet) · **J4** fin S9 (back-office + sécurité) · **J5** fin S10 (recette exécutée) · **J6** S12 (soutenance).")
A("Trois dates sont figées par le calendrier pédagogique : S2, S10, S12.")
A("")
A("---")
A("")
A("## 8. Dépendances et chemin critique")
A("")
A("```text")
A("L1 ── L2 ── L3 ── L4 ── L5 ── L6 ── L7 ── L8 ── L9 ── L10 ── L11 ── L12 ── L14 ── L15 ── L16")
A(" └─────────────── parallèle : D2 (UML) / D3 (BD) dès S2 ───────────────┘        │")
A("                                     L8 (socle) peut démarrer dès que L2-L3 sont posés ←──────┘")
A("                                     L12, L13 (sécurité, JS) courent en parallèle de L14")
A("```")
A("")
A("**Chemin critique** (la chaîne qui fixe la date de fin) :")
A("")
A("> `L1 → L2 → L4 → L5 → L6 → L7 → L8 → L10 → L11 → L14 → L15 → L16`")
A("")
A("- **L4→L7 (la base)** est le segment le plus contraint : toute l'application (L9-L11) appelle les vues,")
A("  les procédures et les triggers ; un retard d'une semaine en S3-S5 décale mécaniquement J3, J4 et J5.")
A("- **L10 (panier/commande)** est la tâche la plus riche en dépendances (session + procédures + triggers + CSRF) :")
A("  c'est le lot à protéger en priorité (d'où sa position S8, après le socle et le front-office).")
A("- **Marge faible** : L3 (diagrammes) peut glisser d'une semaine sans décaler la fin, à condition que L4 démarre")
A("  la semaine suivante ; L12/L13 ont une semaine de marge avant J5.")
A("- **Hors chemin critique** : L13 (JS) peut être réduite (repli : validation live du panier seulement) sans toucher au barème.")
A("")
A("---")
A("")
A("## 9. Coûts (le seul « budget » rare du projet : le temps)")
A("")
A("| Poste | Base | Valeur |")
A("|---|---|---:|")
A(f"| Charge de production | {GRAND} h × 60 €/h (indice pédagogique théorique) | {GRAND*60:,} € théoriques |".replace(",", " "))
A("| Licences, hébergement, données | tout open source / poste personnel | 0 € |")
A("| **Budget externe réel** | | **0 €** |")
A("")
A("Le suivi réel se fait en **heures** : la table §6.3 est pointée chaque vendredi par A dans le wiki GitLab")
A("(charge restante par lot), conformément au CDC §13.5.")
A("")
A("---")
A("")
A("## 10. Exploitation dans GitLab (la WBS devient le tableau du projet)")
A("")
A("1. **Une issue par tâche de niveau 4** : titre `[(D3-L6-04)] sp_create_order, sp_add_order_line…` ;")
A("   description = définition de fini + dépendances ; 86 issues créées dès S1 (bulk import CSV).")
A("2. **Labels** : `D1`…`D6` (couleur de division), `L1`…`L16`, `A`/`B`/`C`, `jalon-J1`…`jalon-J6`.")
A("3. **Board** : colonnes *À faire → En cours → En revue (MR) → Fermé* ; une colonne par jalon en vue *Listes de jalons*.")
A("4. **MR** : une MR par lot minimum, **mergée par un autre développeur que l'auteur** (auto-merge interdit) ;")
A("   la CI doit être verte (6 jobs, dont base-tests = 29/29).")
A("5. **Suivi individuel** (barème v) : `git shortlog -sne main` doit montrer 3 auteurs > 20 commits ;")
A("   la répartition cible par zone (B ≈ 45 % de `sql/`, A ≈ 40 % de `app/Controller/`, C ≈ 45 % de `app/View/` et `public/js/`)")
A("   est celle du CDC §14.3 ; chacun garde ≥ 5 commits dans les trois zones.")
A("")
A("---")
A("")
A("## 11. Gouvernance : définition de « terminé » et règle de repli")
A("")
A("**Une tâche de niveau 4 est fermée seulement si :**")
A("")
A("- sa **définition de fini** est observée (colonne dédiée dans chaque table du §5) ;")
A("- le code ou le document passe la **CI** (php-lint, 29 tests SQL, contrôles de sécurité, rendu des diagrammes) ;")
A("- une **autre personne** a relu la MR (revue croisée systématique) ;")
A("- la **documentation** liée (CDC, doc de conception, doc de tests, README) est mise à jour dans la même MR.")
A("")
A("**Règle de repli si groupe de 2** (CDC §13.3) — la WBS se réduit ainsi, sans supprimer une exigence pénalisée :")
A("")
A("| Retrait | Tâches concernées | Économie |")
A("|---|---|---:|")
A("| L13 réduit à la validation live du panier | `D4-L13-02…04` supprimées | −6 h |")
A("| L8 simplifié (table de routes minimale, pas de routeur maison) | `D4-L8-01` réduite | −3 h |")
A("| `EF-ADM-09` (indicateurs) et `EF-ADM-10` (comptes admin) non livrés | `D4-L11-04` réduite | −2 h |")
A("| Procédures réduites à 8, triggers à 8 (en gardant TRG-A1/A2/A3, B2, B5, D1, D4) | `D3-L6-*` et `D3-L7-*` allégées | −25 h |")
A("| **Total repli** | | **≈ −36 h → 324 h pour 2 personnes (~160 h chacune, périmètre validé en S1)** |")
A("")
A("---")
A("")
A("*WBS MiniShop v1.0 — document généré depuis la même source de données que le CDC §13.3 (charges réconciliées) ;")
A("méthode : structure de découpage en 4 niveaux, estimation bottom-up, plan de charge, Gantt, chemin critique.*")

OUT_MD.write_text("\n".join(md), encoding="utf-8")
print(f"OK MD : {OUT_MD} ({len(md)} lignes, {OUT_MD.stat().st_size/1024:.0f} Ko) — {N_TASKS} tâches, {GRAND} h, {PERS}")

# =====================================================================
# GÉNÉRATION HTML (autoportant : arbre CSS + gantt coloré + styles intégrés)
# =====================================================================
# arbre (ul/li)
tree = ['<div class="wbswrap"><ul class="wbstree">',
        '<li><span class="node n1">MiniShop — 360 h · 3 devs · S1→S12</span><ul>']
for did, nom, bareme, resp, lots in DIVISIONS:
    tree.append(f'<li><span class="node ndiv" style="background:{COULEURS[did]}">{did} · {nom} · {DIV_H[did]} h · {resp}</span><ul>')
    for l in lots:
        tree.append(f'<li><span class="node nlot"><b>{l}</b> {LOTS[l]["nom"]} <i>({LOT_H[l]} h · {LOTS[l]["resp"]} · {"/".join(LOTS[l]["semaines"])})</i></span></li>')
    tree.append('</ul></li>')
tree.append('</ul></li></ul></div>')

# gantt coloré
g = ['<table class="gantt"><tr><th>Lot</th>', *[f"<th>{s}</th>" for s in SEMAINES], "<th>h</th><th>Resp.</th></tr>"]
for lid, lot in LOTS.items():
    did = next(d[0] for d in DIVISIONS if lid in d[4])
    g.append(f'<tr><td class="lot" style="border-left:5px solid {COULEURS[did]}"><b>{lid}</b></td>')
    for s in SEMAINES:
        if s in lot["semaines"]:
            g.append(f'<td class="on" style="background:{COULEURS[did]}"></td>')
        else:
            g.append('<td class="off"></td>')
    g.append(f'<td class="h">{LOT_H[lid]}</td><td>{lot["resp"]}</td></tr>')
g.append(f'<tr class="tot"><td><b>Total</b></td>' + '<td class="off"></td>'*12 + f'<td class="h"><b>{GRAND}</b></td><td>A+B+C</td></tr></table>')

text = OUT_MD.read_text(encoding="utf-8")
text = text.replace("<!--ARBRE_WBS-->", "\n".join(tree)).replace("<!--GANTT_HTML-->", "\n".join(g))

EXTRA_CSS = """
.wbswrap { overflow-x:auto; }
ul.wbstree, ul.wbstree ul { list-style:none; margin:0; padding-left:0; }
ul.wbstree ul { padding-left:26px; }
ul.wbstree li { position:relative; padding:5px 0 5px 16px; }
ul.wbstree li::before { content:""; position:absolute; left:0; top:0; bottom:0; border-left:2px solid #d8c9a6; }
ul.wbstree li::after { content:""; position:absolute; left:0; top:19px; width:12px; border-top:2px solid #d8c9a6; }
ul.wbstree li:last-child::before { height:19px; }
ul.wbstree > li::before { border-left:none; }
.node { display:inline-block; padding:4px 12px; border-radius:5px; font-family:'Segoe UI',system-ui,sans-serif; font-size:.86em; }
.node.n1 { background:#1a2332; color:#fff; font-weight:bold; font-size:.95em; }
.node.ndiv { color:#fff; font-weight:bold; }
.node.nlot { background:#fff; border:1px solid #e2d9c8; }
.node.nlot i { color:#7a6a4f; font-style:normal; }
table.gantt { font-size:.8em; }
table.gantt th { text-align:center; padding:5px 4px; }
table.gantt td { padding:0; height:22px; }
table.gantt td.on { opacity:.85; }
table.gantt td.off { background:#f7f2e8 !important; }
table.gantt td.lot { padding:2px 8px; }
table.gantt td.h { text-align:right; padding:2px 8px; }
table.gantt tr.tot td { background:#f0e6d2 !important; font-weight:bold; }
"""
html = markdown.markdown(text, extensions=["tables", "fenced_code"], output_format="html5")
HTML = pathlib.Path("/home/user/.tools/build_doc04_html.py").read_text(encoding="utf-8")
css = re.search(r'CSS = """(.*?)"""', HTML, re.S).group(1) + EXTRA_CSS
page = f"""<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>MiniShop — WBS : divisions, lots et tâches individuelles</title><style>{css}</style></head>
<body>
{html}
</body></html>"""
OUT_HTML.write_text(page, encoding="utf-8")
print(f"OK HTML : {OUT_HTML} ({OUT_HTML.stat().st_size/1024:.0f} Ko)")
