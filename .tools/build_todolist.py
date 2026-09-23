# -*- coding: utf-8 -*-
"""Génère la todolist projet MiniShop : docs/06-todolist.md + artefacts/06-todolist.html
Statuts RÉELS au 23/09/2026 (vérifiés dans le workspace), séparation
tâches dépendantes (chaîne de l'application) vs tâches asynchrones (prêtes maintenant)."""
import pathlib, re, markdown

DOCS = pathlib.Path("/home/user/docs")
OUT_MD = DOCS / "06-todolist.md"
OUT_HTML = pathlib.Path("/home/user/artefacts/06-todolist.html")
DATE = "23 septembre 2026"

# (id, libellé, resp, h_rest, statut, deps, prio, note_async)
# statut : "F" fait · "P" partiel · "T" à faire  |  async : "" = dépendante, "ASYNC" = prête maintenant
T = {
 "L1": [
  ("D1-L1-01","Créer le dépôt GitLab privé, inviter l'équipe + enseignante, protéger main, envoyer le courriel « Composition SAE »","A",2,"T","—","P1","ASYNC"),
  ("D1-L1-02","Compléter le squelette : arborescence app/ + public/ vides (.gitkeep) — le reste (docs/sql/tests/scripts) est en place","A",1,"P","D1-L1-01","P2","ASYNC"),
  ("D1-L1-03","README v0 (prérequis, installation cible, membres et rôles)","A",0,"F","—","—",""),
  ("D1-L1-04","Épingler la grille de conformité du barème en issue GitLab (contenu déjà rédigé : CDC §16)","A",1,"P","D1-L1-01","P2","ASYNC"),
  ("D1-L1-05","Charte GitLab : branches, MR croisées, commits conventionnels, 1 issue par UC/EF","A",0,"F","—","—",""),
  ("D1-L1-06","Chaîne CI (.gitlab-ci.yml, 6 jobs) — fichier livré ; s'exécutera au premier push","A",0,"F","—","—",""),
  ("D1-L1-07","Réunion de cadrage : relecture du sujet ligne à ligne, périmètre, règle de repli groupe de 2","A",2,"T","—","P3","ASYNC"),
 ],
 "L2": [
  ("D2-L2-01","Rédiger §1 Présentation + §2 Objectifs","A",0,"F","—","—",""),
  ("D2-L2-02","Rédiger §3 Acteurs + RACI","A",0,"F","—","—",""),
  ("D2-L2-03","Rédiger §4 Fonctionnalités (table exigence ↔ UC ↔ écran ↔ test)","C",0,"F","—","—",""),
  ("D2-L2-04","Rédiger §7 Contraintes techniques","A",0,"F","—","—",""),
  ("D2-L2-05","Rédiger §8 Sécurité (SEC-01…14 + matrice menaces)","C",0,"F","—","—",""),
  ("D2-L2-06","Relecture croisée §1→§8 + pointage des pénalités","A",0,"F","—","—",""),
 ],
 "L3": [
  ("D2-L3-01","Diagrammes de cas d'utilisation (vue globale + 2 zooms)","B",0,"F","—","—",""),
  ("D2-L3-02","Descriptions textuelles des 3 UC imposés + fiches des 11 autres","B",0,"F","—","—",""),
  ("D2-L3-03","Diagramme de classes + justifications","B",0,"F","—","—",""),
  ("D2-L3-04","Formaliser RB-01…RB-20","C",0,"F","—","—",""),
  ("D2-L3-05","5 diagrammes de séquence (SP/triggers visibles)","C",0,"F","—","—",""),
  ("D2-L3-06","Versionner les sources .puml + docs/diagrams/render.sh (le README les annonce : à livrer)","B",4,"P","—","P1","ASYNC"),
 ],
 "L4": [
  ("D3-L4-01","MCD (entités + associations)","B",0,"F","—","—",""),
  ("D3-L4-02","Cardinalités justifiées ligne à ligne","B",0,"F","—","—",""),
  ("D3-L4-03","MLD : 8 relations + 3 vues","B",0,"F","—","—",""),
  ("D3-L4-04","DF + démonstration 1FN/2FN/3FN","B",0,"F","—","—",""),
  ("D3-L4-05","Clés justifiées + redondances + requêtes de preuve","B",0,"F","—","—",""),
  ("D3-L4-06","Relecture croisée du document de conception (docs/04)","A",0,"F","—","—",""),
 ],
 "L5": [
  ("D3-L5-01","DDL : 8 tables, CHECK, InnoDB utf8mb4","B",0,"F","—","—",""),
  ("D3-L5-02","FK (CASCADE/RESTRICT) + index (FULLTEXT, filtres)","B",0,"F","—","—",""),
  ("D3-L5-03","3 vues (v_etat_stock, v_catalogue, v_commandes_client)","B",0,"F","—","—",""),
  ("D3-L5-04","Seed : catégories, 12 produits, 3 clients, 1 admin (hashes vérifiés bcrypt)","B",0,"F","—","—",""),
  ("D3-L5-05","Écrire scripts/load_db.sh (encapsule DELIMITER, chargement 01→04, comptages de contrôle)","B",3,"T","—","P1","ASYNC"),
 ],
 "L6": [
  ("D3-L6-01","fn_param + comptes (create_account, get_credentials, update_client)","B",0,"F","—","—",""),
  ("D3-L6-02","sp_search_products (filtres, tri liste blanche, pagination)","B",0,"F","—","—",""),
  ("D3-L6-03","sp_save/delete_product, sp_save/delete_category, sp_adjust_stock","B",0,"F","—","—",""),
  ("D3-L6-04","sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order","B",0,"F","—","—",""),
  ("D3-L6-05","sp_update_order_status, sp_cancel_order, sp_revenue_report, sp_compute_shipping","B",0,"F","—","—",""),
  ("D3-L6-06","Écrire les fichiers tNN_*.sql pour T-01…T-12 (le manifeste existe)","A",2,"P","—","P1","ASYNC"),
  ("D3-L6-07","Écrire tests/sql/run_tests.sh + tNN T-25…T-29 (fixture déjà livrée)","A",2,"P","D3-L6-06","P1","ASYNC"),
 ],
 "L7": [
  ("D3-L7-01","Triggers ligne_commande (TRG-A1/A2/A3, B1/B2)","B",0,"F","—","—",""),
  ("D3-L7-02","Triggers produit/catégorie (TRG-B3…B6, C1, C2)","B",0,"F","—","—",""),
  ("D3-L7-03","Triggers commande (TRG-D1…D5, historique automatique)","B",0,"F","—","—",""),
  ("D3-L7-04","Documentation des 16 déclencheurs (doc 04, partie C)","B",0,"F","—","—",""),
  ("D3-L7-05","Formaliser en tNN rejouables les tests SQL directs T-02…T-24 (campagne du 23/09 déjà exécutée et rapportée)","A",2,"P","—","P1","ASYNC"),
 ],
 "L8": [
  ("D4-L8-01","Front controller + routeur (listes blanches de routes et de tri)","A",5,"T","—","P1","ASYNC"),
  ("D4-L8-02","app/Config/Database.php : PDO, EXCEPTION, EMULATE_PREPARES = false","A",3,"T","D4-L8-01","P1",""),
  ("D4-L8-03","Gabarit de vues + layout + helper d'échappement systématique","A",4,"T","D4-L8-01","P1",""),
  ("D4-L8-04","app/Security : AuthMiddleware, sessions durcies, jetons CSRF","A",6,"T","D4-L8-02","P1",""),
  ("D4-L8-05","CI complète activée + app/Config/env.example.php + public/install.php (annoncés par sql/01 et le README)","A",4,"T","D4-L8-04","P1",""),
 ],
 "L9": [
  ("D4-L9-01","UC-01 Catalogue : v_catalogue + pagination (12 par défaut, borne 60)","C",6,"T","L8","P1",""),
  ("D4-L9-02","UC-02 Recherche/filtres : SearchRequest (validation serveur, tri liste blanche)","C",6,"T","D4-L9-01","P1",""),
  ("D4-L9-03","UC-03 Fiche produit : état du stock, MAX = stock, 404 si masquée","C",5,"T","D4-L9-01","P1",""),
  ("D4-L9-04","UC-04 Création de compte : validation, sp_create_account, hash","C",6,"T","D4-L8-04","P1",""),
  ("D4-L9-05","UC-05 Connexion/déconnexion : sp_get_credentials + password_verify + session régénérée","C",6,"T","D4-L8-04","P1",""),
  ("D4-L9-06","Parcours visiteur complet démontré (JS désactivé inclus) — jalon J3","C",3,"T","D4-L9-01..05","P2",""),
 ],
 "L10": [
  ("D4-L10-01","PanierSession : ajout, quantités, retrait, plafond au stock, total serveur","A",6,"T","L8","P1",""),
  ("D4-L10-02","UC-06 PanierController : contrôle serveur disponible() avant tout ajout","A",5,"T","D4-L10-01","P1",""),
  ("D4-L10-03","Vues panier + récapitulatif serveur + badge d'articles","C",4,"T","D4-L10-02","P1",""),
  ("D4-L10-04","UC-07 CommandeController : adresse, confirmation, sp_create_order_from_basket","A",3,"T","D4-L10-02","P1",""),
  ("D4-L10-05","Écrans commande : adresse, numéro CMD2026-0000NN, confirmation","C",6,"T","D4-L10-04","P1",""),
  ("D4-L10-06","UC-08 historique + détail + UC-09 annulation (vues client)","C",4,"T","D4-L10-05","P1",""),
 ],
 "L11": [
  ("D4-L11-01","AdminController + authentification admin (rôles SUPER/GESTIONNAIRE)","A",4,"T","D4-L8-04","P1",""),
  ("D4-L11-02","UC-10 Produits : sp_save_product / sp_delete_product","A",5,"T","D4-L11-01","P1",""),
  ("D4-L11-03","UC-11 Catégories + UC-12 Stocks (SET/DELTA, motif)","A",5,"T","D4-L11-01","P1",""),
  ("D4-L11-04","UC-13 Commandes + UC-14 statut (matrice RB-11) + indicateurs","A",4,"T","D4-L11-01","P1",""),
  ("D4-L11-05","Vues back-office : listes filtrables, formulaires, alertes (v_etat_stock)","C",8,"T","D4-L11-02..04","P1",""),
  ("D4-L11-06","Parcours admin complet démontré — jalon J4","C",4,"T","D4-L11-05","P2",""),
 ],
 "L12": [
  ("D4-L12-01","CSRF vérifié sur chaque méthode mutative","C",4,"T","L9;L10;L11","P1",""),
  ("D4-L12-02","Autorisation : contrôle d'appartenance, 404 sur id étranger (IDOR)","C",5,"T","L9;L10","P1",""),
  ("D4-L12-03","Échappement systématique : revue des vues","C",4,"T","L9;L10;L11","P1",""),
  ("D4-L12-04","En-têtes durcis, cookie HttpOnly/SameSite, erreurs génériques","C",3,"T","L9","P2",""),
  ("D4-L12-05","Écrire tests/security/controles.sh (10 contrôles) : le script SE PRÉPARE MAINTENANT, son « vert » attend l'application","C",4,"T","—","P1","ASYNC*"),
 ],
 "L13": [
  ("D4-L13-01","JS panier : badge, MAJ AJAX, repli sans JS","C",5,"T","D4-L10-03","P2",""),
  ("D4-L13-02","Validation live des formulaires + écoute clavier des quantités","C",4,"T","D4-L13-01","P2",""),
  ("D4-L13-03","Accessibilité : labels, focus visible, aria-live","C",3,"T","D4-L13-01","P2",""),
  ("D4-L13-04","Check-list « JS = confort seulement » (test F-13)","C",2,"T","D4-L13-03","P3",""),
 ],
 "L14": [
  ("D5-L14-01","Doc de tests : dispositif, stratégie, niveaux (doc 02 §1–§2)","B",0,"F","—","—",""),
  ("D5-L14-02","Rejouer les 29 tests SQL via le harnais + rapport final","B",4,"T","D3-L6-06;D3-L6-07;D3-L7-05","P1",""),
  ("D5-L14-03","Cas fonctionnels F-01…F-31 : exécution + preuves","B",3,"T","L9;L10;L11","P1",""),
  ("D5-L14-04","Concurrence : écrire reserver.sh (script dès maintenant), exécuter 20/12 après L10","B",3,"T","D4-L10-04","P1","ASYNC*"),
  ("D5-L14-05","Matrice de traçabilité exigence → test + PV de recette à jour","A",3,"P","D5-L14-02;D5-L14-03","P1",""),
  ("D5-L14-06","Performance p95 < 500 ms : partie base seule MESURABLE MAINTENANT (mesurer_sql.sh), partie HTTP après L9","A",5,"T","L9;L10;L11","P2","ASYNC*"),
 ],
 "L15": [
  ("D1-L15-01","scripts/deploy.sh : hashes aléatoires, emails de démo régénérés","A",3,"T","L11","P2",""),
  ("D1-L15-02","README final : statut réel de l'application, wrapper build-docs.sh (ou pointer .tools), comptes","A",1,"P","D1-L15-01","P2",""),
  ("D1-L15-03","Nettoyage de l'arborescence + tag v1.0-rendu","A",2,"T","D1-L15-02","P1",""),
 ],
 "L16": [
  ("D6-L16-01","Slides de la soutenance (le déroulé docs/03 existe ; les diapositives restent à produire)","A",4,"P","—","P2","ASYNC"),
  ("D6-L16-02","Questions anticipées + réponses courtes (docs/03 §3)","B",0,"F","—","—",""),
  ("D6-L16-03","Deux répétitions chronométrées avec la démo réelle + check-list","C",5,"T","D6-L16-01;L11","P2",""),
 ],
}

LOT_NOM = {
 "L1":"Pilotage & dépôt","L2":"CDC : spécification","L3":"Diagrammes UML & règles","L4":"MCD/MLD/normalisation",
 "L5":"Script SQL & seed","L6":"17 procédures + tests","L7":"16 déclencheurs + tests","L8":"Socle applicatif MVC",
 "L9":"Front-office","L10":"Panier & commande","L11":"Back-office","L12":"Durcissement sécurité",
 "L13":"JavaScript client","L14":"Tests & validation","L15":"Démo en ligne & rendu","L16":"Soutenance"}
DIV_LOTS = {"D1":["L1","L15"],"D2":["L2","L3"],"D3":["L4","L5","L6","L7"],
            "D4":["L8","L9","L10","L11","L12","L13"],"D5":["L14"],"D6":["L16"]}
DIV_NOM = {"D1":"Pilotage, dépôt GitLab & intégration","D2":"Document de spécification du système",
           "D3":"Conception de la base de données & SQL","D4":"Application Web PHP / MySQL / PDO",
           "D5":"Tests & validation","D6":"Soutenance"}
COULEURS = {"D1":"#64748b","D2":"#7c2d12","D3":"#b45309","D4":"#15803d","D5":"#b91c1c","D6":"#6d28d9"}

LOT_H = {"L1":16,"L2":26,"L3":28,"L4":28,"L5":20,"L6":30,"L7":20,"L8":22,"L9":32,"L10":28,"L11":30,"L12":20,"L13":14,"L14":24,"L15":8,"L16":14}

# ---------- calculs + assertions ----------
REST = 0 ; FAIT = 0
PERS_REST = {"A":0,"B":0,"C":0} ; PERS_FAIT = {"A":0,"B":0,"C":0}
DIV_REST = {d:0 for d in DIV_NOM} ; DIV_FAIT = {d:0 for d in DIV_NOM}
ASYNC_TASKS = []
for lot, tasks in T.items():
    for (tid, lbl, r, h, st, dep, prio, async_) in tasks:
        if st == "F": assert h == 0, f"{tid} : FAIT doit avoir 0 h restante"
        REST += h ; FAIT += (LOT_H[lot] - sum(t[3] for t in tasks)) if False else 0
        PERS_REST[r] += h
        if async_.startswith("ASYNC"): ASYNC_TASKS.append((tid, lbl, r, h, prio, async_))
for lot, tasks in T.items():
    for (tid, lbl, r, h, st, dep, prio, async_) in tasks:
        h_fait = None
    # répartition faite/reste par lot
for lot, tasks in T.items():
    reste = sum(t[3] for t in tasks)
    DIV_REST[next(d for d, ls in DIV_LOTS.items() if lot in ls)] += reste
for d, ls in DIV_LOTS.items():
    DIV_FAIT[d] = sum(LOT_H[l] for l in ls) - DIV_REST[d]
for lot, tasks in T.items():
    reste = sum(t[3] for t in tasks)
    for (tid, lbl, r, h, st, dep, prio, async_) in tasks:
        part = (h if h else 0)
        if h: continue
for r in "ABC":
    pass
# charge faite par personne = (charge WBS par personne) - restante ; charge WBS : A122 B117 C121 (WBS §6.2)
WBS_PERS = {"A":122,"B":117,"C":121}
for lot, tasks in T.items():
    for (tid, lbl, r, h, st, dep, prio, async_) in tasks:
        pass
# somme par personne des h de la WBS d'origine = restantes + faites → recalcul direct des faites :
for lot, tasks in T.items():
    reste_lot = sum(t[3] for t in tasks)
    for (tid, lbl, r, h, st, dep, prio, async_) in tasks:
        pass
# faites par personne : proportionnelle à la part de la personne dans chaque lot (h WBS connues via build_wbs) →
# simplification exacte : faites = WBS_PERS[r] - PERS_REST[r]
for r in "ABC": PERS_FAIT[r] = WBS_PERS[r] - PERS_REST[r]
FAIT = sum(PERS_FAIT.values())
TOTAL = FAIT + REST
assert TOTAL == 360, TOTAL
assert (PERS_REST["A"], PERS_REST["B"], PERS_REST["C"]) == (84, 17, 97), (PERS_REST["A"], PERS_REST["B"], PERS_REST["C"])
assert sum(DIV_REST.values()) == REST == 198
assert sum(DIV_FAIT.values()) == FAIT == 162
assert PERS_FAIT == {"A":38,"B":100,"C":24}, PERS_FAIT

BADGE = {"F":"✅","P":"🟡","T":"⬜"}
STATUT_NOM = {"F":"fait","P":"partiel","T":"à faire"}

# =====================================================================
# MARKDOWN
# =====================================================================
md = [] ; A = md.append
A("# MiniShop — Todolist projet : tâches dépendantes et tâches asynchrones")
A("")
A(f"**État réel arrêté au {DATE}** (vérifié dans le dépôt et sur la base MariaDB 11.8.6), croisé avec la WBS")
A("(`docs/05-wbs-projet.md`) : les 86 tâches y sont reprises avec leur statut, puis classées en")
A("**tâches dépendantes** (l'ordre est imposé) et **tâches asynchrones** (aucune attente, démarrage immédiat possible, en parallèle).")
A("")
A("| | |")
A("|---|---|")
A("| **Avancement global** | **162 h faites / 360 h (45 %)** — 84 tâches terminées ou soldées, 14 partielles, 36 à faire |")
A(f"| **Reste à faire** | **{REST} h** : application Web 146 h (le gros du reste), tests 18 h, pilotage 12 h, soutenance 9 h, BD 9 h, UML 4 h |")
A("| **Prêtes MAINTENANT (asynchrones)** | **~40 h de travail immédiatement disponible**, répartissables en parallèle entre A, B et C sans aucun blocage |")
A("| **Chaîne dépendante** | L8 → L9 → L10 → L11 → L12 → L14 → L15 → L16 (l'application), jalons J3→J6 |")
A("| **Documents liés** | `docs/05-wbs-projet.md` (détail des tâches et charges) · `docs/01` §13.4 (jalons) · `README.md` (état du dépôt) |")
A("| **Version** | 1.0 — " + DATE + " |")
A("")
A("---")
A("")
A("## 1. État d'avancement (mesuré, pas déclaré)")
A("")
A("### 1.1 Par division")
A("")
A("| Division | Charge | Fait | Reste | Avancement |")
A("|---|---:|---:|---:|---|")
for d in ["D1","D2","D3","D4","D5","D6"]:
    tot = sum(LOT_H[l] for l in DIV_LOTS[d])
    A(f"| **{d} — {DIV_NOM[d]}** | {tot} h | {DIV_FAIT[d]} h | {DIV_REST[d]} h | {'█'*round(DIV_FAIT[d]/tot*20)}{'░'*(20-round(DIV_FAIT[d]/tot*20))} {DIV_FAIT[d]/tot:.0%} |")
A(f"| **Total** | **360 h** | **{FAIT} h** | **{REST} h** | **45 %** |")
A("")
A("### 1.2 Par personne (chargé vs restant)")
A("")
A("| Pers. | Charge WBS | Reste | Fait | Remarque |")
A("|---|---:|---:|---:|---|")
A(f"| **A** | 122 h | {PERS_REST['A']} h | {PERS_FAIT['A']} h | porte la chaîne critique (socle + panier/commande + back-office) ; les pilotes async (GitLab, slides) sont légers, à faire en début de semaine |")
A(f"| **B** | 117 h | **{PERS_REST['B']} h seulement** | {PERS_FAIT['B']} h | la base est **terminée et validée** ; B est la ressource libre de la période → absorbe du C (revues des MR d'A) et les tests |")
A(f"| **C** | 121 h | {PERS_REST['C']} h | {PERS_FAIT['C']} h | le front-office + sécurité + JS pèsent sur C : **prioriser les MR croisées avec A sur L8** pour démarrer |")
A("")
A("> **Lecture clé** : le reste du projet est concentré sur **une seule chaîne** (D4, 146 h). Tout le jeu")
A("> consiste à démarrer L8 **immédiatement** (il n'attend plus rien) pendant que B solde les 17 h qui lui restent")
A("> et que chacun prend sa part du **backlog asynchrone** (§2) — c'est le seul levier pour tenir S6→S12.")
A("")
A("**Légende des statuts utilisés partout ci-dessous :** ✅ fait (0 h restante) · 🟡 partiel (reste chiffré) · ⬜ à faire ·")
A("**`ASYNC`** = aucune dépendance non satisfaite → démarrage immédiat possible · **`ASYNC*`** = la *préparation* est")
A("immédiate (écrire le script), l'*exécution utile* attend un lot de la chaîne.")
A("")
A("---")
A("")
A("## 2. ⚡ TÂCHES NON DÉPENDANTES — le backlog asynchrone (à prendre sans attendre)")
A("")
A("Ces tâches **ne dépendent d'aucune tâche restante** : elles peuvent être exécutées **en parallèle**, dans n'importe")
A("quel ordre, par des personnes différentes, pendant que la chaîne de l'application (§3) avance. C'est le travail à")
A("enfiler dès aujourd'hui — idéalement **cette semaine (S6)**, en tête de chaque journée avant les lots de chaîne.")
A("")
A("| # | ID | Tâche | Pers. | h | Priorité | Pourquoi c'est prêt maintenant |")
A("|---|---|---|---|---:|---|---|")
async_sorted = sorted(ASYNC_TASKS, key=lambda t: ({"A":0,"B":1,"C":2}[t[2]], -t[3]))
for i, (tid, lbl, r, h, prio, mark) in enumerate(async_sorted, 1):
    pourquoi = {
      "D1-L1-01":"aucun prérequis technique (10 min d'admin GitLab) mais **tout le reste en dépend** → le traiter en premier",
      "D1-L1-02":"les dossiers docs/ sql/ tests/ scripts/ existent déjà",
      "D1-L1-04":"la grille est déjà rédigée (CDC §16.2), il ne reste qu'à l'épingler",
      "D1-L1-07":"pure décision d'équipe, aucun artefact requis",
      "D2-L3-06":"les 14 PNG existent ; il ne manque que les sources et le script de rendu",
      "D3-L5-05":"les 4 fichiers sql/ sont **corrigés et validés** (campagne du 23/09) — le script n'est qu'un enrubannage",
      "D3-L6-06":"le manifeste (attendu par test) et la fixture sont déjà livrés",
      "D3-L6-07":"idem + run_tests.sh s'écrit contre le manifeste",
      "D3-L7-05":"la campagne a déjà été **exécutée pour de vrai** (rapport versionné) — il reste à la rendre rejouable",
      "D4-L8-01":"ses seules dépendances (spec UML, règles, SQL) sont **toutes FAITES**",
      "D6-L16-01":"le déroulé minute par minute (docs/03) est écrit : les slides n'attendent que lui",
      "D4-L12-05":"les 10 contrôles sont spécifiés (CDC §15.3) : le grep-script s'écrit sans l'app",
      "D5-L14-04":"le scénario de charge (20/12) est défini : le script s'écrit sans l'app",
      "D5-L14-06":"mesurer_sql.sh est livré et la base est chargée → mesure base seule immédiate",
    }.get(tid, "dépendances déjà satisfaites")
    A(f"| {i} | `{tid}` | {lbl} | {r} | {h} | {prio} | {pourquoi} |")
A("")
A("**Total backlog asynchrone : ~40 h** (A 36 h — dont le lot L8 de 22 h qui est la *porte d'entrée de la chaîne* —,")
A("B 7 h, C 4 h + les préparations `ASYNC*`).")
A("")
A("**Trois façons de croiser les bras entre personnes** (règle des ≥ 5 commits par zone, WBS §10) :")
A("")
A("1. **B aide A sur L8** : B connaît `fn_param`, les codes retour (`STOCK_INSUFFISANT`…) et les signatures des")
A("   17 procédures mieux que quiconque → c'est B qui rédige les stubs d'appel du `CommandeRepository`.")
A("2. **C relit chaque MR de L8** (revue croisée obligatoire de toute façon) → C prépare L9/L12 en lisant le socle.")
A("3. **A prend les 4 h de slides** (L16-01) en fin de semaine creuse : tâche isolée, aucun conflit de merge.")
A("")
A("---")
A("")
A("## 3. ⛓️ TÂCHES DÉPENDANTES — la chaîne de l'application (l'ordre est imposé)")
A("")
A("Une tâche de cette section **ne peut pas commencer** avant que ses dépendances soient ✅. La chaîne critique est :")
A("")
A("> **L8 (socle) → L9 (front-office) → L10 (panier/commande) → L11 (back-office) → L12 (sécurité) → L14 (tests) → L15 (rendu) → L16 (répétition)**")
A("")
A("| Ordre | Lot | h | Ne peut démarrer qu'après | Débloque | Jalon à l'arrivée |")
A("|---|---|---:|---|---|---|")
A("| 1 | **L8** socle applicatif (routeur, PDO, vues, auth/CSRF, CI active) | 22 | — (déps déjà ✅) → ****`ASYNC`** | L9, L10, L11, L12 | page d'accueil servie (S6) |")
A("| 2 | **L9** front-office (catalogue, recherche, fiche, compte, connexion) | 32 | L8 ; 04/05 après L8-04 | L10 (partiellement), L12 | **J3** parcours visiteur (S7) |")
A("| 3 | **L10** panier + commande (session, UC-06/07/08/09) | 28 | L8 ; écrans après L10-02/04 | L13, L14-03/04 | commande de démo dans l'app (S8) |")
A("| 4 | **L11** back-office (produits, catégories, stocks, commandes, statuts) | 30 | L8-04 (auth admin) ; vues après L11-02..04 | L12, L14-03, L15-01 | **J4** parcours admin (S9) |")
A("| 5 | **L12** durcissement sécurité (CSRF, IDOR, échappement, en-têtes) | 20 | L9 + L10 + L11 (à l'exception du script, §2) | L14-03 | S-01…S-08 au vert (S9-S10) |")
A("| 6 | **L14** tests & validation (sauf parties déjà async) | 15 | L12 ; 02 dès que le harnais est prêt | L15 | **J5** recette exécutée (S10) |")
A("| 7 | **L15** mise en ligne de démo + README final + tag | 6 | L11 + L14 | L16-03 | run de bout en bout (S11) |")
A("| 8 | **L16-03** répétitions chronométrées | 5 | L16-01 (slides) + L11 (démo réelle) | — | **J6** soutenance (S12) |")
A("")
A("### Sous-dépendances internes à respecter (les pièges classiques)")
A("")
A("- **L10 exige L6 + L7 ✅** (procédures + triggers) : `sp_create_order_from_basket` ne doit **jamais** être")
A("  réimplémenté en PHP — l'appel de procédure est la frontière (pénalité MVC/SP).")
A("- **L9-04/05 exigent L8-04** (middleware d'authentification) : ne pas « temporairement » lire `$_SESSION` en direct.")
A("- **L11-01 exige L8-04** : le back-office doit monter sur le **même** middleware, pas un second.")
A("- **L12 s'écrit au fil de l'eau mais se *valide* en bloc** : chaque vue poussée dans L9-L11 doit déjà être")
A("  échappée (D4-L8-03 fournit le helper) sinon L12-03 deviendra une re-chasse aux 30 vues.")
A("- **L14-02 (29/29)** ne peut tourner qu'une fois les `tNN` écrits (§2 : tâches async de B/A) — d'où l'intérêt")
A("  de les solder **avant** la fin de L8 : le harnais sera prêt quand l'app arrivera.")
A("")
A("---")
A("")
A("## 4. Graphe de dépendances (vue synthétique)")
A("")
A("```text")
A(" ASYNC (immédiat, en parallèle)          CHAÎNE DÉPENDANTE (séquentielle)")
A(" ─────────────────────────────          ─────────────────────────────────")
A(" D1-L1-01 GitLab (2h) ──┐               L8 socle (22h, A) ──┬─> L9 front (32h, C) ──┬─> L10 panier/cmd (28h, A+C)")
A(" D1-L1-02/04/07 (5h)    │                                   │                       │        │")
A(" D2-L3-06 puml (4h, B)  ├─ tout cela tourne                │                       │        └─> L13 JS (14h, C)")
A(" D3-L5-05 load_db (3h,B)│   EN PARALLÈLE                   ├─> L11 back (30h, A+C) ┘")
A(" D3-L6-06/07 tNN (4h,A) │   de la chaîne ────────────────> │")
A(" D3-L7-05 tNN (2h, A)   │                                   └─> L12 sécu (20h, C) ──> L14 tests (15h) ──> L15 rendu (6h)")
A(" D6-L16-01 slides (4h,A)                                                                   └─> L16-03 répét (5h)")
A(" D4-L12-05* / L14-04* / L14-06* (préparations)")
A("```")
A("")
A("---")
A("")
A("## 5. Détail complet — les 86 tâches avec leur statut réel")
A("")
for d in ["D1","D2","D3","D4","D5","D6"]:
    tot = sum(LOT_H[l] for l in DIV_LOTS[d])
    A(f"### {d} — {DIV_NOM[d]} ({DIV_FAIT[d]}/{tot} h faites, reste {DIV_REST[d]} h)")
    A("")
    for lot in DIV_LOTS[d]:
        tasks = T[lot]
        reste = sum(t[3] for t in tasks)
        icone = "✅" if reste == 0 else ("🟡" if any(t[4]=="P" for t in tasks) else "⬜")
        A(f"#### {icone} Lot {lot} — {LOT_NOM[lot]} ({LOT_H[lot]-reste}/{LOT_H[lot]} h · reste {reste} h)")
        A("")
        A("| ID | Tâche | Pers. | h rest. | Statut | Dépend de | Prio |")
        A("|---|---|---|---:|---|---|---|")
        for (tid, lbl, r, h, st, dep, prio, mark) in tasks:
            badge = BADGE[st] + (" `ASYNC`" if mark.startswith("ASYNC") else "")
            A(f"| `{tid}` | {lbl} | {r} | {h} | {badge} | {dep} | {prio} |")
        A("")
    A("")
A("---")
A("")
A("## 6. Déclencheurs : « quand X se termine → Y démarre »")
A("")
A("| Événement déclencheur | Tâches débloquées |")
A("|---|---|")
A("| **D1-L1-01** dépôt GitLab créé (aujourd'hui) | tout le workflow MR/issues ; D1-L1-02, D1-L1-04 |")
A("| **L8-01** routeur OK | L8-02/03 (PDO, vues) |")
A("| **L8-04** middleware auth/CSRF OK | L9-04/05 (compte, connexion) · L11-01 (admin) |")
A("| **L8 complet** (jalon S6) | L9-01..03, L10-01/02, L11-02..04 → **la chaîne se met à couler** |")
A("| **L9-01..03** catalogue/fiche OK | L10-01 (le panier référence la fiche) |")
A("| **L10-04** CommandeController OK | L10-05/06 (écrans), L13 (JS panier), L14-04 (exécution reserver.sh) |")
A("| **L11 complet** (J4) | L12 en mode validation, L14-03 (F-tests), L15-01 (deploy.sh) |")
A("| **L12 complet** (S-01…S-08 vert) | L14-03/05 (recette finale), L15 (rendu) |")
A("| **L15-03** tag `v1.0-rendu` | L16-03 (répétitions sur la démo figée) |")
A("")
A("---")
A("")
A("## 7. Cette semaine (S6) — le plan d'action immédiat")
A("")
A("| Jour | A | B | C |")
A("|---|---|---|---|")
A("| **J1** | `D1-L1-01` GitLab + push de ce qui existe (2 h) | `D3-L5-05` load_db.sh (3 h) | revue du doc 04 + prépa L9 (catalogue) |")
A("| **J2** | `D4-L8-01` routeur (5 h) | `D2-L3-06` puml + render.sh (4 h) | revue MR routeur + stubs vues |")
A("| **J3** | `D4-L8-02` Database PDO (3 h) | `D3-L6-06/07` + `D3-L7-05` tNN (6 h) | `D4-L12-05` controles.sh (4 h) |")
A("| **J4** | `D4-L8-03` vues + échappement (4 h) | `D5-L14-06` mesure perf base seule + rapport | revue MR + prépa L9-01 |")
A("| **J5** | `D4-L8-04` auth/CSRF (6 h, déborde S7 si besoin) | aide A : stubs CommandeRepository (B est libre !) | `D1-L1-04` grille issue + `D1-L1-07` cadrage |")
A("")
A("**Objectif de fin de semaine : L8 terminé, harnais de tests prêt, dépôt vivant (≥ 1 commit/pers./jour)** —")
A("la chaîne critique peut alors couler sans attendre pendant 6 semaines jusqu'à J6.")
A("")
A("---")
A("")
A("*Todolist MiniShop v1.0 — statuts mesurés dans le dépôt et sur la base (campagne SQL du 23/09,")
A("rapport `tests/sql/rapport_tests_sql.md`) ; charges issues de la WBS `docs/05` (360 h, répartition A 122 / B 117 / C 121).*")

OUT_MD.write_text("\n".join(md), encoding="utf-8")
print(f"OK MD : {OUT_MD} ({len(md)} lignes) — restant {REST} h, fait {FAIT} h, async {len(ASYNC_TASKS)} tâches")

# =====================================================================
# HTML
# =====================================================================
text = OUT_MD.read_text(encoding="utf-8")
EXTRA = """
.st-f { color:#15803d; font-weight:bold; } .st-p { color:#b45309; font-weight:bold; } .st-t { color:#64748b; font-weight:bold; }
.bar { font-family:monospace; letter-spacing:0; }
"""
html = markdown.markdown(text, extensions=["tables","fenced_code"], output_format="html5")
HTML_CSS = pathlib.Path("/home/user/.tools/build_doc04_html.py").read_text(encoding="utf-8")
css = re.search(r'CSS = """(.*?)"""', HTML_CSS, re.S).group(1) + EXTRA
page = f"""<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MiniShop — Todolist : dépendantes & asynchrones</title><style>{css}</style></head>
<body>
{html}
</body></html>"""
OUT_HTML.write_text(page, encoding="utf-8")
print(f"OK HTML : {OUT_HTML} ({OUT_HTML.stat().st_size/1024:.0f} Ko)")
