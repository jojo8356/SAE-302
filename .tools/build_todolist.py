# -*- coding: utf-8 -*-
"""Génère la todolist MiniShop (Markdown + HTML dans le dépôt).
Les mesures de septembre restent historiques ; les statuts et preuves datées sont qualifiés dans le document généré."""
import pathlib, re, markdown

ROOT = pathlib.Path(__file__).resolve().parents[1]
DOCS = ROOT / "docs"
OUT_MD = DOCS / "06-todolist.md"
OUT_HTML = ROOT / "artefacts" / "06-todolist.html"
SCOPE_DATE = "23 octobre 2026"

# (id, libellé, rôle de planification, h_rest, statut initial, deps, prio, note_async)
# Les statuts/heures initiaux sont remplacés ci-dessous par la cartographie de docs/19 ; ASYNC décrit le graphe de référence, pas la disponibilité actuelle.
T = {
 "L1": [
  ("D1-L1-01","Partager l’URL et l’accès GitLab requis, communiquer la composition ; visibilité privée et protection de branche non exigées par le sujet","A",0,"F","—","—","ASYNC"),
  ("D1-L1-02","Compléter le squelette : arborescence app/ + public/ vides (.gitkeep) — le reste (docs/sql/tests/scripts) est en place","A",1,"P","D1-L1-01","P2","ASYNC"),
  ("D1-L1-03","README v0 (prérequis, installation cible, membres et rôles)","A",0,"F","—","—",""),
  ("D1-L1-04","Issue GitLab de conformité renseignée : cinq livrables et pénalités du barème (CDC §16)","A",0,"F","D1-L1-01","—",""),
  ("D1-L1-05","Charte GitLab : branches, MR croisées, commits conventionnels, 1 issue par UC/EF (docs/20)","A",0,"F","—","—",""),
  ("D1-L1-07","Synthèse des retours continus sur l’audit (mode asynchrone ; aucune réunion complète revendiquée ; docs/21-synthese-retours-audit.md)","A",0,"F","—","—",""),
 ],
 "L2": [
  ("D2-L2-01","Rédiger §1-§2 avec dates et niveaux de preuve cohérents","A",0,"F","—","—",""),
  ("D2-L2-02","Rédiger §3 et RACI par rôles A/B/C, sans affectation nominative","A",0,"F","—","—",""),
  ("D2-L2-03","Traçabilité EF/EV ↔ UC ↔ écran ↔ test ↔ annexe","C",0,"F","—","—",""),
  ("D2-L2-04","Distinguer l'exigence MySQL/PDO de l'état actuel : SQL par défaut, JSON explicitement optionnel","A",0,"F","—","—",""),
  ("D2-L2-05","SEC-01…SEC-14 ; 10 scénarios offensifs S-01…S-10 ; 11 contrôles statiques dont S-11/SEC-14","C",0,"F","—","—",""),
  ("D2-L2-06","Pointage documentaire fait ; relecture croisée étudiante §1→§8 à effectuer","A",3,"T","—","P1",""),
 ],
 "L3": [
  ("D2-L3-01","Diagrammes de cas d'utilisation (vue globale + 2 zooms)","B",0,"F","—","—",""),
  ("D2-L3-02","Descriptions textuelles des 3 UC imposés + fiches des 11 autres","B",0,"F","—","—",""),
  ("D2-L3-03","Diagramme de classes + justifications","B",0,"F","—","—",""),
  ("D2-L3-04","20 identifiants : 11 classés comme issus du sujet + 9 compléments ; PDF source non versionné","C",0,"F","—","—",""),
  ("D2-L3-05","5 séquences présentes : 4 imposées + 1 bonus ; pas de sixième","C",0,"F","—","—",""),
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
  ("D3-L5-01","DDL : 9 tables SQL (8 entités métier + table technique d’audit du stock), CHECK, InnoDB utf8mb4","B",0,"F","—","—",""),
  ("D3-L5-02","FK (CASCADE/RESTRICT) + index (FULLTEXT, filtres)","B",0,"F","—","—",""),
  ("D3-L5-03","3 vues (v_etat_stock, v_catalogue, v_commandes_client)","B",0,"F","—","—",""),
  ("D3-L5-04","Seed : 4 catégories, 36 produits, 3 clients, 1 admin (hashes vérifiés bcrypt)","B",0,"F","—","—",""),
  ("D3-L5-05","Écrire scripts/load_db.sh (encapsule DELIMITER, chargement 01→04, comptages de contrôle)","B",3,"T","—","P1","ASYNC"),
 ],
 "L6": [
  ("D3-L6-01","fn_param + comptes (create_account, get_credentials, update_client)","B",0,"F","—","—",""),
  ("D3-L6-02","sp_search_products (filtres, tri liste blanche, pagination)","B",0,"F","—","—",""),
  ("D3-L6-03","sp_save/delete_product, sp_save/delete_category, sp_adjust_stock","B",0,"F","—","—",""),
  ("D3-L6-04","sp_create_order, sp_add_order_line, sp_create_order_from_basket, sp_confirm_order","B",0,"F","—","—",""),
  ("D3-L6-05","sp_update_order_status, sp_cancel_order, sp_cancel_order_admin, sp_revenue_report, sp_compute_shipping","B",0,"F","—","—",""),
  ("D3-L6-06","Écrire les fichiers tNN_*.sql pour T-01…T-12 (le manifeste existe)","A",2,"P","—","P1","ASYNC"),
  ("D3-L6-07","Écrire tests/sql/run_tests.sh + tNN T-25…T-29 (fixture déjà livrée)","A",2,"P","D3-L6-06","P1","ASYNC"),
 ],
 "L7": [
  ("D3-L7-01","Triggers ligne_commande (TRG-A1/A2/A3, B1/B2)","B",0,"F","—","—",""),
  ("D3-L7-02","Triggers produit/catégorie (TRG-B3…B6, C1, C2)","B",0,"F","—","—",""),
  ("D3-L7-03","Triggers commande (TRG-D1…D5, historique automatique)","B",0,"F","—","—",""),
  ("D3-L7-04","Documentation des 16 déclencheurs (doc 04, partie C)","B",0,"F","—","—",""),
  ("D3-L7-05","Formaliser en tNN rejouables T-02…T-24 ; validation du 23/09 historique, fichier rapport portant une date future 27/10 non recevable comme preuve à la coupure","A",4,"P","—","P1","ASYNC"),
 ],
 "L8": [
  ("D4-L8-01","Front controller + routeur (listes blanches de routes et de tri)","A",5,"T","—","P1","ASYNC"),
  ("D4-L8-02","app/Config/Database.php : PDO, EXCEPTION, EMULATE_PREPARES = false","A",3,"T","D4-L8-01","P1",""),
  ("D4-L8-03","Gabarit de vues + layout + helper d'échappement systématique","A",4,"T","D4-L8-01","P1",""),
  ("D4-L8-04","app/Security : AuthMiddleware, sessions durcies, jetons CSRF","A",6,"T","D4-L8-02","P1",""),
  ("D4-L8-05","CI GitHub Actions complète + app/Config/env.example.php + public/install.php (annoncés par sql/01 et le README)","A",4,"T","D4-L8-04","P1",""),
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
  ("D4-L12-05","11 contrôles statiques sécurité rejoués localement ; S-11/SEC-14 ciblé, sans portée de recette applicative","C",0,"F","—","—","ASYNC*"),
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
  ("D5-L14-04","Concurrence : préparer reserver.sh ; exécuter 20/12 après L10","B",3,"T","D4-L10-04","P1","ASYNC*"),
  ("D5-L14-05","Matrice de traçabilité exigence → test + PV de recette à jour","A",3,"P","D5-L14-02;D5-L14-03","P1",""),
  ("D5-L14-06","Performance p95 < 500 ms : mesure SQL via mesurer_sql.sh ; mesure HTTP après L9","A",5,"T","L9;L10;L11","P2","ASYNC*"),
 ],
 "L15": [
  ("D1-L15-02","README final : statut réel de l'application, wrapper build-docs.sh (ou pointer .tools), comptes","A",1,"P","D5-L14-05","P2",""),
  ("D1-L15-03","Nettoyage de l'arborescence + tag v1.0-rendu","A",2,"T","D1-L15-02","P1",""),
 ],
 "L16": [
  ("D6-L16-01","Slides de la soutenance (le déroulé docs/03 existe ; les diapositives restent à produire)","A",4,"P","—","P2","ASYNC"),
  ("D6-L16-02","Questions anticipées + réponses courtes (docs/03 §3)","B",0,"F","—","—",""),
  ("D6-L16-03","Deux répétitions chronométrées avec la démo réelle + check-list","C",5,"T","D6-L16-01;L11","P2",""),
 ],
}

# Recalage des statuts sur la revue tâche par tâche de docs/19.
# OK/OK* = critère ou livrable constaté ; PARTIEL = exécution ou preuve restante ; U = [!],
# action humaine/externe/mesure qui n'est pas attestable par le seul dépôt.
AUDIT_OK = {"D1-L1-01", "D1-L1-02", "D1-L1-03", "D1-L1-04", "D1-L1-05", "D1-L1-07", "D1-L15-02", "D4-L13-03", "D4-L13-04", "D4-L8-01", "D4-L8-02", "D4-L8-03", "D4-L8-04", "D4-L9-01", "D4-L9-02", "D4-L9-03", "D4-L9-04", "D4-L9-05", "D4-L10-01", "D4-L10-02", "D4-L10-06", "D4-L11-03", "D4-L11-04", "D4-L12-01", "D4-L12-02", "D4-L12-03", "D4-L12-04", "D5-L14-02", "D5-L14-04"}
AUDIT_PARTIEL = {
    "D2-L2-06", "D4-L8-05",
    "D5-L14-05",
}
AUDIT_NON_VERIFIABLE = {"D1-L15-03", "D3-L4-06", "D5-L14-06", "D6-L16-03"}

# Ces h sont les estimations initiales WBS affectées aux tâches ouvertes, pas un relevé
# de temps passé ni une estimation actualisée du travail restant. Cela vaut aussi pour [!].
AUDIT_H_RESTANT = {
    "D2-L2-06": 3,
    "D4-L8-05": 4,
    "D5-L14-05": 3,
    "D1-L15-03": 2, "D3-L4-06": 4, "D5-L14-06": 5, "D6-L16-03": 5,
}
AUDIT_LIBELLES = {
    "D1-L1-02": "Inventaire Git fourni (542 chemins) identique à l'arbre du commit 82a1279fff756531489b470c7a6d946e3510085f : des chemins sont suivis sous chacun des dossiers app/, public/, sql/, tests/, docs/, scripts/ et le fichier .gitignore ; structure vérifiée, pas le fonctionnement du code",
    "D1-L1-03": "README présent avec composition, permissions GitLab et distinction explicite avec le RACI/A-B-C ; aucune correspondance nominative A/B/C ni lecture effective par les membres n'est revendiquée ; `OK` porte sur le critère documentaire de la WBS",
    "D1-L1-05": "Charte GitLab et modèles de MR présents ; aucun usage réel de MR ou revue croisée étudiante n'est attesté",
    "D1-L15-02": "README, wrapper build-docs et aide comptes relus indépendamment par l’assistant le 23/10/2026 (tiers documentaire) ; aide limitée au seed JSON ; aucune installation chronométrée ni revue humaine n’est revendiquée",
    "D1-L15-03": "Nettoyage et tag v1.0-rendu à vérifier sur le dépôt cible ; aucun tag n'est attesté dans les références examinées",
    "D2-L2-06": "Relecture croisée étudiante §1→§8 : aucune trace datée d'un auteur et d'un relecteur distincts",
    "D2-L3-06": "Sources .puml, docs/diagrams/render.sh et job PlantUML présents",
    "D3-L4-06": "Relecture croisée du document de conception (docs/04) : non démontrable par le dépôt seul",
    "D3-L5-05": "scripts/load_db.sh présent et appelé par le workflow SQL ; le run SQL reste à effectuer séparément",
    "D3-L6-06": "Fichiers SQL T-01…T-12 présents ; l'exécution est suivie séparément par D5-L14-02",
    "D3-L6-07": "Harnais et fichiers SQL T-25…T-29 présents ; l'exécution est suivie séparément par D5-L14-02",
    "D3-L7-05": "Tests directs T-13…T-24 présents ; aucun rejeu SQL du checkout courant n'est prouvé",
    "D4-L8-01": "Front controller unique et routeur à liste blanche présents ; routes inconnues en 404 et tests de routes disponibles",
    "D4-L8-02": "Configuration PDO centralisée (ERRMODE_EXCEPTION, EMULATE_PREPARES=false) ; connexion MySQL réelle à rejouer via les commandes de test fournies",
    "D4-L8-03": "Layout, vues et helper d'échappement systématique présents ; contrôle statique XSS vert",
    "D4-L8-04": "Authentification, sessions durcies et CSRF présents ; contrôles sécurité automatisés verts (projet scolaire, sans certification générale)",
    "D4-L8-05": "Configuration CI et d'environnement présente, mais le critère `public/install.php` manque (installation portée par `scripts/db_init.php`/`run.sh`) ; le run non-SQL local ne valide pas ce critère, PDO/MySQL ni une CI distante",
    "D4-L9-01": "Catalogue SQL via v_catalogue/sp_search_products, pagination 12 par défaut et borne 60 ; tests SQL ajoutés dans sql_integration.php",
    "D4-L9-02": "Recherche/filtres/tri SQL avec validation serveur, liste blanche de tri et page bornée ; tests SQL ajoutés dans sql_integration.php",
    "D4-L9-03": "Fiche produit SQL par slug visible, état de stock et produit masqué/absent en null (404 côté contrôleur) ; tests SQL ajoutés dans sql_integration.php",
    "D4-L9-04": "UC-04 création de compte : doublon e-mail refusé et hash vérifiés en SQL (T-04/T-05) et en HTTP SQL (105/105) sur VM native 17/11/2026 (f9b614f, PHP 8.4, MariaDB)",
    "D4-L9-05": "UC-05 connexion : suite HTTP SQL exécutée sur VM native le 17/11/2026 (f9b614f, http_sql_e2e.php §6, 105/105 : sp_get_credentials via PDO + password_verify, régénération de session, compte bloqué, messages identiques ; docs/pv-recette.md)",
    "D4-L9-06": "Parcours visiteur automatisé vert sur JSON (E2E 338), SQL (29/29) et HTTP SQL (http_sql_e2e.php §4, 105/105) au run VM natif 17/11/2026 (f9b614f) ; démo S7 humaine non attestée",
    "D4-L10-01": "Panier de session sur SqlStore : ajout, quantités, retrait, plafond au stock et total recalculé serveur ; tests SQL ajoutés dans sql_integration.php",
    "D4-L10-02": "Contrôle serveur de disponibilité avant ajout (disponible()) et plafond au stock ; SQL est le pilote par défaut, JSON reste un bonus de test isolé",
    "D4-L10-04": "UC-07 commande : procédure, rollback intégral (T-29) et parcours HTTP SQL (§8, 105/105) verts au run VM natif 17/11/2026 (f9b614f)",
    "D4-L10-05": "Écrans de commande présents ; procédures SQL (T-16/T-18/T-28) et parcours HTTP SQL (§8, 105/105) verts au run VM natif 17/11/2026 (f9b614f)",
    "D4-L10-06": "Annulation impossible après EXPEDIEE vérifiée en SQL (T-12/T-13, étape 6) au run VM natif 16/11/2026 ; recette humaine non revendiquée",
    "D4-L11-02": "UC-10 produits : procédures (T-15/T-20) et parcours HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f)",
    "D4-L11-03": "UC-11/12 catégories et stock : suppression refusée si occupée (T-14), stock (T-19) et HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f)",
    "D4-L11-04": "UC-13/14 commandes et statuts : transition interdite refusée et tracée (T-12/T-13/T-22) et HTTP SQL admin (§9, 105/105) verts au run VM natif 17/11/2026 (f9b614f)",
    "D4-L11-06": "Parcours admin automatisé vert (JSON + SQL + HTTP SQL 105/105, 60 étapes) au run VM natif 17/11/2026 (f9b614f) ; démo S9 humaine non attestée",
    "D4-L12-01": "CSRF vérifié sur chaque méthode mutative (jeton constant, formulaires POST) ; contrôles sécurité et tests HTTP verts, périmètre projet scolaire",
    "D4-L12-02": "Contrôle d'appartenance/IDOR corrigé : commande absente ou d'autrui en 404 générique côté contrôleur ; audit sécurité 11/11 et E2E isolé verts",
    "D4-L12-03": "Échappement systématique des vues contrôlé par le scanner statique et les tests ; contrôle XSS vert",
    "D4-L12-04": "En-têtes durcis, cookie HttpOnly/SameSite et erreurs génériques contrôlés par les tests disponibles",
    "D4-L12-05": "`docs/19` rapporte 11 contrôles statiques rejoués localement le 23/10 ; S-11/SEC-14 ciblé, sans portée de recette applicative ou LineServ",
    "D4-L13-01": "Code JS panier/AJAX présent ; le run non-SQL a passé preferences_test (48 vues FR/EN, 45 messages) et E2E JSON isolé (338 vérifications), sans navigateur réel ni PDO/MySQL",
    "D4-L13-03": "Labels, focus visible et annonces aria-live couverts par les contrats automatisés (css_contract, cartes_accessibilite_test, preferences_test) et la check-list ENF-08 documentée ; aucune validation humaine avec technologie d’assistance n’est attestée",
    "D4-L13-04": "F-13 est décrit dans docs/02 et les moyens automatisés (wasm-e2e, responsive_navigateur) y sont listés ; critère WBS de documentation satisfait, sans revendiquer l'exécution des tests ni une recette manuelle",
    "D5-L14-02": "29 tests SQL rejoués sur base temporaire isolée minishop_test au run VM natif 16/11/2026 (SHA 1a79d16, PHP 8.4, MariaDB, 29/29, rapport versionné) ; distincte de toute cible manuelle",
    "D5-L14-03": "Automatisations exécutées au run VM natif 17/11/2026 (60 étapes, SQL + HTTP SQL 105/105 + Concurrence + E2E joués, 0 warning) ; les 200 entrées de recette manuelle restent NON JOUÉ",
    "D5-L14-04": "Concurrence HTTP 20/12 exécutée sur VM native le 17/11/2026 (f9b614f, concurrence_http.php, 8/8 : 20 acteurs / 12 unités, 12 commandes, 8 refus, stock final 0 ; docs/pv-recette.md)",
    "D5-L14-05": "Matrice présente ; PV de recette généré sur VM réelle (docs/pv-recette.md, 60/60 étapes sur f9b614f, 17/11/2026) ; relecture, décision et signature humaines restant à compléter",
    "D5-L14-06": "Scripts présents ; mesures p95 HTTP/SQL sur machine et volumétrie cibles non attestées",
    "D6-L16-01": "Support de soutenance docs/12 et livrables PPTX/PDF/DOCX présents ; répétition à distinguer",
    "D6-L16-03": "Deux répétitions chronométrées avec la démo réelle : aucune trace attestée",
}
_ALL_IDS = {task[0] for tasks in T.values() for task in tasks}
AUDIT_OK_STAR = _ALL_IDS - AUDIT_OK - AUDIT_PARTIEL - AUDIT_NON_VERIFIABLE
AUDIT_STATUT = ({tid: "F" for tid in AUDIT_OK}
                 | {tid: "F*" for tid in AUDIT_OK_STAR}
                 | {tid: "P" for tid in AUDIT_PARTIEL}
                 | {tid: "U" for tid in AUDIT_NON_VERIFIABLE})
assert len(_ALL_IDS) == 84
assert len(AUDIT_OK) == 29 and len(AUDIT_OK_STAR) == 48
assert len(AUDIT_PARTIEL) == 3 and len(AUDIT_NON_VERIFIABLE) == 4
assert set(AUDIT_H_RESTANT) == AUDIT_PARTIEL | AUDIT_NON_VERIFIABLE
for lot, tasks in T.items():
    T[lot] = [
        (tid, AUDIT_LIBELLES.get(tid, label), role, AUDIT_H_RESTANT.get(tid, 0), AUDIT_STATUT[tid], deps, prio, async_)
        for (tid, label, role, _h_old, _status_old, deps, prio, async_) in tasks
    ]

LOT_NOM = {
 "L1":"Pilotage & dépôt","L2":"CDC : spécification","L3":"Diagrammes UML & règles","L4":"MCD/MLD/normalisation",
 "L5":"Script SQL & seed","L6":"18 procédures + tests","L7":"16 déclencheurs + tests","L8":"Socle applicatif MVC",
 "L9":"Front-office","L10":"Panier & commande","L11":"Back-office","L12":"Durcissement sécurité",
 "L13":"JavaScript client","L14":"Tests & validation","L15":"README final & rendu","L16":"Soutenance"}
DIV_LOTS = {"D1":["L1","L15"],"D2":["L2","L3"],"D3":["L4","L5","L6","L7"],
            "D4":["L8","L9","L10","L11","L12","L13"],"D5":["L14"],"D6":["L16"]}
DIV_NOM = {"D1":"Pilotage, dépôt GitLab & collaboration","D2":"Document de spécification du système",
           "D3":"Conception de la base de données & SQL","D4":"Application Web PHP / MySQL / PDO",
           "D5":"Tests & validation","D6":"Soutenance"}
COULEURS = {"D1":"#64748b","D2":"#7c2d12","D3":"#b45309","D4":"#15803d","D5":"#b91c1c","D6":"#6d28d9"}

LOT_H = {"L1":14,"L2":26,"L3":28,"L4":28,"L5":20,"L6":30,"L7":20,"L8":22,"L9":32,"L10":28,"L11":30,"L12":20,"L13":14,"L14":24,"L15":5,"L16":14}

# ---------- calculs + assertions ----------
REST = 0
PERS_REST = {"A":0,"B":0,"C":0}
DIV_REST = {d:0 for d in DIV_NOM}
ASYNC_TASKS = []
for lot, tasks in T.items():
    for (tid, lbl, role, h, status, dep, prio, async_) in tasks:
        assert status in {"F", "F*", "P", "U", "T"}, (tid, status)
        if status in {"F", "F*"}:
            assert h == 0, f"{tid} : OK/OK* ne porte pas de charge ouverte"
        if status in {"P", "U"}:
            assert h > 0, f"{tid} : tâche ouverte sans estimation à revalider"
        REST += h
        PERS_REST[role] += h
        if async_.startswith("ASYNC"):
            ASYNC_TASKS.append((tid, lbl, role, h, prio, async_))

for lot, tasks in T.items():
    div = next(d for d, lots in DIV_LOTS.items() if lot in lots)
    DIV_REST[div] += sum(task[3] for task in tasks)

# Totaux issus de la WBS de référence : ils ne sont pas des relevés d'heures réelles.
WBS_PERS = {"A":117,"B":117,"C":121}
TOTAL = sum(LOT_H.values())
assert TOTAL == 355, TOTAL
assert sum(WBS_PERS.values()) == TOTAL
assert sum(DIV_REST.values()) == REST == 26
assert PERS_REST == {"A":21,"B":0,"C":5}, PERS_REST

N_TASKS = sum(len(tasks) for tasks in T.values())
STATUTS = ("F", "F*", "P", "U", "T")
COUNTS = {status: sum(1 for tasks in T.values() for task in tasks if task[4] == status) for status in STATUTS}
COUNTS_BY_DIV = {
    div: {status: sum(1 for lot in lots for task in T[lot] if task[4] == status) for status in STATUTS}
    for div, lots in DIV_LOTS.items()
}
ASYNC_BY_PERSON = {person: sum(row[3] for row in ASYNC_TASKS if row[2] == person) for person in "ABC"}
ASYNC_H = sum(ASYNC_BY_PERSON.values())
assert N_TASKS == 84 and sum(COUNTS.values()) == N_TASKS, (N_TASKS, COUNTS)
assert COUNTS == {"F":29,"F*":48,"P":3,"U":4,"T":0}, COUNTS
assert sum(COUNTS_BY_DIV[d][s] for d in DIV_LOTS for s in STATUTS) == N_TASKS
BADGE = {"F":"✅","F*":"✅*","P":"🟡","U":"[!]","T":"⬜"}
STATUT_NOM = {"F":"OK","F*":"OK*","P":"PARTIEL","U":"à vérifier / externe","T":"à faire"}

# =====================================================================
# MARKDOWN
# =====================================================================
md = [] ; A = md.append
A("# MiniShop — Todolist projet : suivi de base et planification historique")
A("")
A(f"**Périmètre :** {N_TASKS} tâches de la WBS, dont la charge totale historique est estimée à {TOTAL} h. La répartition `OK`/`OK*`/`PARTIEL`/`[!]` reprend la revue tâche par tâche de `docs/19` ; elle ne constitue pas un nouvel audit exhaustif du working tree actuel.")
A(f"**Avertissement temporel :** `docs/19` contient un addendum daté du 15/11/2026, postérieur à la date du présent rendu ({SCOPE_DATE}). Les statuts sont repris comme cartographie demandée, mais aucun run ou événement daté après le 23/10 n'est crédité ici comme réalisé. La présence de code ou de harnais n'est pas assimilée à son exécution ; seuls les replays explicitement rapportés sont distingués, sans en déduire une validation humaine.")
A("SQL/MySQL/PDO reste le pilote par défaut ; JSON est une option explicite pour les tests isolés. **D1-L1-01** est `OK` sur attestation utilisateur et capture fournie du courriel ; la capture montre l’objet, le destinataire affiché, la composition et l’URL GitLab, mais ni la date ni le dossier Envoyés. Aucune vérification directe de la boîte ni confirmation de remise n’est revendiquée. **D1-L1-02** est `OK` pour l’arborescence : l’inventaire `arbre-arena.txt` (542 chemins) correspond exactement aux chemins Git du commit `82a1279fff756531489b470c7a6d946e3510085f` ; chacun des six dossiers racine exigés contient des chemins suivis et `.gitignore` est suivi comme fichier. Cela ne valide pas le fonctionnement du code et ne décrit pas le checkout local sur `e0c554f`. **D1-L1-07** est `OK` pour la synthèse asynchrone des retours continus dans `docs/21-synthese-retours-audit.md` ; aucune réunion complète, approbation collective ou validation pédagogique n’est revendiquée.")
A("La mise en ligne de démonstration et le staging ont été retirés du périmètre sans remplacement. Le graphe, les dépendances et le plan S6 restent la photographie du 23/09/2026, pas un ordre de priorité actuel.")
A("**Dernier contrôle non-SQL (23/10/2026, working tree basé sur `5d282f4`) :** `node scripts/run-all-tests.mjs --sans-sql` a terminé avec le code 0 via PHP-WASM 8.2 ; PHPUnit passe (57 tests) et l'E2E isolé passe (338 vérifications). SQL est exclu, PHP CLI natif absent ; le détail des suites et limites est en §8.")
A("")
A("| | |")
A("|---|---|")
A(f"| **Répartition des statuts d'audit** | **{COUNTS['F']} `OK` + {COUNTS['F*']} `OK*`**, {COUNTS['P']} `PARTIEL`, {COUNTS['U']} `[!]` non vérifiables ({N_TASKS} tâches) ; **{COUNTS['P'] + COUNTS['U']} tâches ouvertes ou à confirmer** |")
A(f"| **Estimation WBS historique affectée aux tâches ouvertes** | **{REST} h** (dont {DIV_REST['D4']} h pour D4), calculées à partir du plan de référence ; ce n'est ni une mesure du travail passé ni une prévision réestimée |")
A("| **Temps réellement passé** | Non mesuré ; aucun nombre d'heures accomplies n'est déduit des statuts |")
A(f"| **Backlog asynchrone de la photographie** | **{ASYNC_H} h estimées** dans le graphe du 23/09 ; ni une disponibilité actuelle ni une mesure d'effort |")
A("| **Dernier contrôle non-SQL** | `node scripts/run-all-tests.mjs --sans-sql` : code 0 ; SQL/PDO et PHP CLI natif non joués (détails §8) |")
A("| **Documents liés** | `docs/05-wbs-projet.md` (WBS) · `docs/19-audit-cahier-des-charges-todolist.md` (constats) · `docs/21-synthese-retours-audit.md` |")
A("| **Version** | 1.7 — statuts de docs/19 repris ; exécution non-SQL du 23/10 distinguée des validations SQL et humaines |")
A("")
A("---")
A("")
A("## 1. Statuts de l'audit tâche par tâche et estimations historiques WBS")
A("")
A("### 1.1 Répartition par division")
A("")
A("| Division | Charge WBS initiale (estim.) | `OK` | `OK*` | `PARTIEL` | `[!]` | Estimation initiale associée aux tâches ouvertes |")
A("|---|---:|---:|---:|---:|---:|---:|")
for d in ["D1","D2","D3","D4","D5","D6"]:
    tot = sum(LOT_H[l] for l in DIV_LOTS[d])
    cd = COUNTS_BY_DIV[d]
    A(f"| **{d} — {DIV_NOM[d]}** | {tot} h | {cd['F']} | {cd['F*']} | {cd['P']} | {cd['U']} | {DIV_REST[d]} h |")
A(f"| **Total** | **{TOTAL} h** | **{COUNTS['F']}** | **{COUNTS['F*']}** | **{COUNTS['P']}** | **{COUNTS['U']}** | **{REST} h** |")
A("")
A("### 1.2 Estimations WBS par rôle de planification (A/B/C ; aucune correspondance nominative établie)")
A("")
A("| Rôle de planification | Charge WBS initiale (estim.) | Estimation initiale affectée aux tâches ouvertes | Remarque |")
A("|---|---:|---:|---|")
A(f"| **A** | {WBS_PERS['A']} h | {PERS_REST['A']} h | rôle de planification du socle, panier/commande et back-office ; disponibilité actuelle non vérifiée |")
A(f"| **B** | {WBS_PERS['B']} h | {PERS_REST['B']} h | rôle de planification base de données/tests ; disponibilité actuelle non vérifiée |")
A(f"| **C** | {WBS_PERS['C']} h | {PERS_REST['C']} h | rôle de planification front-office/sécurité/JS ; disponibilité et attribution nominative non établies |")
A("")
A(f"> Les **{REST} h** sont la somme des estimations WBS initiales des {len(AUDIT_PARTIEL)} tâches `PARTIEL` et {len(AUDIT_NON_VERIFIABLE)} tâches `[!]`. Elles sont à revalider ; il ne s'agit ni de temps restant mesuré, ni d'heures passées, ni d'une estimation actualisée du travail à fournir.")
A("> Les nombres de statut ne décrivent que les critères précis de la revue. Un `OK*` ne signifie ni que tout le produit est validé, ni que les tests associés ont été exécutés. Le registre manuel de recette reste `NON JOUÉ` pour ses 200 entrées.")
A("> Le dernier run non-SQL est vert, mais reste une validation de code/tests sur le chemin JSON isolé : il ne prouve ni l'exécution PDO/MySQL, ni les 200 cas manuels, ni une recette humaine ou des mesures sur cible.")
A("")
A("**Légende des statuts d'audit :** ✅ `OK` · ✅* `OK*` (livrable/contrôle ciblé, avec réserves) · 🟡 `PARTIEL` · `[!]` action humaine/externe/mesure non attestable ici · ⬜ à faire.")
A("Les tâches `OK*` ne sont ni une certification générale ni une validation pédagogique. Les 200 lignes du registre manuel restent `NON JOUÉ`.")
A("**`ASYNC`** et **`ASYNC*`** décrivent uniquement les dépendances du graphe de référence du 23/09 ; elles ne prouvent pas une disponibilité actuelle.")
A("**D1-L1-01 (`OK`) :** l’utilisateur atteste l’envoi et a fourni une capture montrant l’objet « Composition SAE », la destinataire affichée, la composition du groupe, l’URL GitLab et le rôle `Reporter`. La date et le dossier Envoyés ne sont pas visibles ; aucune vérification directe de la boîte ni confirmation de remise n’est revendiquée (voir `docs/19`).")
A("**D1-L1-02 (`OK`) :** l’inventaire `arbre-arena.txt` fourni par l’utilisateur (542 chemins) correspond exactement, après tri stable, aux chemins de l’arbre Git du commit `82a1279fff756531489b470c7a6d946e3510085f`. Chacun des six dossiers racine exigés (`app/`, `public/`, `sql/`, `tests/`, `docs/`, `scripts/`) contient des chemins suivis et `.gitignore` est suivi comme fichier ; ce constat porte sur la structure de ce SHA, pas sur le code fonctionnel ni sur le checkout local `e0c554f` (voir `docs/19`).")
A("")
A("---")
A("")
A("## 2. Classification asynchrone du plan de base — photographie du 23/09/2026")
A("")
A("Dans le graphe de dépendances de la photographie du 23/09, ces tâches ne dépendent d'aucune tâche restante : elles pouvaient être préparées en parallèle.")
A("Cette liste ne constitue pas un backlog actuel : plusieurs constats ont pu évoluer. Vérifier chaque tâche et ses prérequis dans le dépôt avant de la reprendre.")
A("")
A("| # | ID | Tâche | Rôle de planification | h estimées WBS initiales | Priorité | Motif de la classification dans la photographie |")
A("|---|---|---|---|---:|---|---|")
async_sorted = sorted(ASYNC_TASKS, key=lambda t: ({"A":0,"B":1,"C":2}[t[2]], -t[3]))
for i, (tid, lbl, r, h, prio, mark) in enumerate(async_sorted, 1):
    pourquoi = {
      "D1-L1-01":"le plan de base l'identifiait comme préalable aux tâches de dépôt ; la preuve utilisateur de l'envoi est distinguée dans l'audit docs/19",
      "D1-L1-02":"les dossiers docs/, sql/, tests/ et scripts/ étaient indiqués comme déjà présents dans la photographie",
      "D1-L1-04":"l'issue atteste le suivi des cinq livrables et pénalités ; l'épinglage n'est pas revendiqué",
"D1-L1-07":"synthèse écrite de retours continus en mode asynchrone ; l’utilisateur atteste les feedbacks, aucune réunion complète n’est revendiquée",
      "D2-L3-06":"constat du 23/09 : les PNG existaient ; les sources et le script de rendu étaient alors signalés manquants (voir les corrections ultérieures de l'audit)",
      "D3-L5-05":"le rapport de la campagne du 23/09 indiquait les quatre fichiers SQL corrigés ; cela ne remplace pas un contrôle actuel",
      "D3-L6-06":"le manifeste et la fixture étaient signalés présents dans la photographie",
      "D3-L6-07":"le manifeste et la fixture étaient signalés présents dans la photographie",
"D3-L7-05":"la validation du 23/09 est historique ; le fichier rapport porte une date future 27/10 et ne prouve pas de rejeu à la coupure 23/10",
      "D4-L8-01":"les dépendances étaient marquées faites dans la photographie ; leur état n'a pas été revérifié dans cette liste",
      "D6-L16-01":"docs/03 était déjà présent dans la photographie ; le statut des diapositives est à vérifier dans la todolist",
      "D4-L12-05":"11 contrôles statiques rejoués localement le 23/10/2026 ; preuve limitée aux sources contrôlées, pas une recette applicative ou LineServ",
      "D5-L14-04":"le scénario de charge était défini dans la planification ; vérifier l'état actuel du harnais",
      "D5-L14-06":"la mesure SQL seule était séparée de la mesure HTTP dans le plan ; ni la base ni les mesures n'ont été rejouées ici",
    }.get(tid, "dépendances déjà satisfaites")
    A(f"| {i} | `{tid}` | {lbl} | {r} | {h} | {prio} | {pourquoi} |")
A("")
A(f"**Estimation WBS initiale associée aux tâches classées `ASYNC`/`ASYNC*` : {ASYNC_H} h** (rôles de planification A {ASYNC_BY_PERSON['A']} h, B {ASYNC_BY_PERSON['B']} h, C {ASYNC_BY_PERSON['C']} h). Ce total n'est ni une mesure du travail à venir ni une disponibilité actuelle.")
A("")
A("**Pistes de parallélisation du plan de référence** (aucune disponibilité actuelle ni contribution effective n'est affirmée) :")
A("")
A("1. **B peut contribuer au socle L8** selon les rôles de planification ; les lettres ne sont pas rattachées à des personnes nommées ici.")
A("2. **Une revue distincte de l'auteur est prévue par la charte** ; cette proposition ne prouve pas qu'une MR ou une revue ait eu lieu.")
A("3. **Le rôle A peut prendre en charge les slides** dans le scénario de base ; à réattribuer après confirmation de l'équipe.")
A("")
A("---")
A("")
A("## 3. Tâches dépendantes — séquence de référence du plan historique (à revalider)")
A("")
A("Dans le graphe de référence, les tâches de cette section attendent leurs dépendances ; la chaîne critique prévue est :")
A("")
A("> **L8 (socle) → L9 (front-office) → L10 (panier/commande) → L11 (back-office) → L12 (sécurité) → L14 (tests) → L15 (rendu) → L16 (répétition)**")
A("")
A("| Ordre du plan | Lot | h WBS initiales | Dépendances prévues | Débloque dans le plan | Jalon historique |")
A("|---|---|---:|---|---|---|")
A("| 1 | **L8** socle applicatif (routeur, PDO, vues, auth/CSRF, CI GitHub Actions) | 22 | — dans le plan de base | L9, L10, L11, L12 | page d'accueil servie (jalon S6 prévu) |")
A("| 2 | **L9** front-office (catalogue, recherche, fiche, compte, connexion) | 32 | L8 ; 04/05 après L8-04 | L10 (partiellement), L12 | **J3** parcours visiteur (S7) |")
A("| 3 | **L10** panier + commande (session, UC-06/07/08/09) | 28 | L8 ; écrans après L10-02/04 | L13, L14-03/04 | commande de démo dans l'app (S8) |")
A("| 4 | **L11** back-office (produits, catégories, stocks, commandes, statuts) | 30 | L8-04 (auth admin) ; vues après L11-02..04 | L12, L14-03 | **J4** parcours admin (S9) |")
A("| 5 | **L12** durcissement sécurité (CSRF, IDOR, échappement, en-têtes) | 20 | L9 + L10 + L11 (à l'exception du script, §2) | L14-03 | S-01…S-08 au vert (S9-S10) |")
A("| 6 | **L14** tests & validation (sauf parties déjà async) | 15 | L12 ; 02 dès que le harnais est prêt | L15 | **J5** recette exécutée (S10) |")
A("| 7 | **L15** README final, arborescence et tag de rendu | 3 | L11 + L14 | L16-03 | documentation et tag final préparés (S11) |")
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
A("- **L14-02 (29 scénarios)** ne peut tourner qu'une fois les `tNN` écrits (§2 : tâches async de B/A) ; consigner un résultat avec run/SHA et date — d'où l'intérêt")
A("  de les solder **avant** la fin de L8 : le harnais sera prêt quand l'app arrivera.")
A("")
A("---")
A("")
A("## 4. Graphe de dépendances (vue synthétique)")
A("")
A("```text")
A(" ASYNC (classification du plan de base)  CHAÎNE DÉPENDANTE (ordre planifié)")
A(" ─────────────────────────────          ─────────────────────────────────")
A(" D1-L1-01 GitLab (2h, photo) ──┐        L8 socle (22h, A) ──┬─> L9 front (32h, C) ──┬─> L10 panier/cmd (28h, A+C)")
A(" D1-L1-02/04 (plan de base) ─┘                            │                       │        │")
A(" D2-L3-06 puml (4h, B)  ├─ tout cela tourne                │                       │        └─> L13 JS (14h, C)")
A(" D3-L5-05 load_db (3h,B)│   EN PARALLÈLE                   ├─> L11 back (30h, A+C) ┘")
A(" D3-L6-06/07 tNN (4h,A) │   de la chaîne ────────────────> │")
A(" D3-L7-05 tNN (4h, A)   │                                   └─> L12 sécu (20h, C) ──> L14 tests (15h) ──> L15 rendu (3h)")
A(" D6-L16-01 slides (4h,A)                                                                   └─> L16-03 répét (5h)")
A(" D4-L12-05* / L14-04* / L14-06* (préparations)")
A("```")
A("")
A("---")
A("")
A("## 5. Détail tâche par tâche — 84 tâches WBS, statuts de l'audit et actions restantes")
A("")
for d in ["D1","D2","D3","D4","D5","D6"]:
    tot = sum(LOT_H[l] for l in DIV_LOTS[d])
    cd = COUNTS_BY_DIV[d]
    A(f"### {d} — {DIV_NOM[d]} ({cd['F']} `OK`, {cd['F*']} `OK*`, {cd['P']} `PARTIEL`, {cd['U']} `[!]` ; {DIV_REST[d]} h d'estimations WBS initiales à revalider sur {tot} h de référence)")
    A("")
    for lot in DIV_LOTS[d]:
        tasks = T[lot]
        reste = sum(t[3] for t in tasks)
        statuses = {t[4] for t in tasks}
        icone = "⚠️" if "U" in statuses else ("🟡" if "P" in statuses else ("✅" if statuses == {"F"} else "✅*"))
        A(f"#### {icone} Lot {lot} — {LOT_NOM[lot]} (charge WBS initiale {LOT_H[lot]} h ; estimation affectée aux tâches ouvertes {reste} h)")
        A("")
        A("| ID | Tâche | Rôle de planification | Estimation WBS initiale affectée (h) | Statut | Dépendances du plan | Prio de référence |")
        A("|---|---|---|---:|---|---|---|")
        for (tid, lbl, r, h, st, dep, prio, mark) in tasks:
            badge = f"{BADGE[st]} `{STATUT_NOM[st]}`" + (f" `{mark}`" if mark.startswith("ASYNC") else "")
            A(f"| `{tid}` | {lbl} | {r} | {h} | {badge} | {dep} | {prio} |")
        A("")
    A("")
A("---")
A("")
A("## 6. Déclencheurs du plan de base (à recalibrer)")
A("")
A("| Événement déclencheur | Tâches débloquées |")
A("|---|---|")
A("| **D1-L1-01** dépôt GitLab créé (précondition du plan de base) | workflow MR/issues ; D1-L1-02, D1-L1-04 |")
A("| **L8-01** routeur OK | L8-02/03 (PDO, vues) |")
A("| **L8-04** middleware auth/CSRF OK | L9-04/05 (compte, connexion) · L11-01 (admin) |")
A("| **L8 complet** (jalon S6 prévu) | L9-01..03, L10-01/02, L11-02..04 |")
A("| **L9-01..03** catalogue/fiche OK | L10-01 (le panier référence la fiche) |")
A("| **L10-04** CommandeController OK | L10-05/06 (écrans), L13 (JS panier), L14-04 (exécution reserver.sh) |")
A("| **L11 complet** (J4 prévu) | L12 en mode validation, L14-03 (F-tests) |")
A("| **L12 complet** (S-01…S-08 vert) | L14-03/05 (recette finale) |")
A("| **L14 complet** (J5 prévu) | D1-L15-02 (README final et statut réel des tests) |")
A("| **L15-03** tag `v1.0-rendu` | L16-03 (répétitions sur la démo figée) |")
A("")
A("---")
A("")
A("## 7. Exemple de plan S6 de la photographie — non exécuté, à replanifier")
A("")
A("| Jour | A | B | C |")
A("|---|---|---|---|")
A("| **J1** | `D1-L1-01` GitLab + push de ce qui existe (2 h) | `D3-L5-05` load_db.sh (3 h) | revue du doc 04 + prépa L9 (catalogue) |")
A("| **J2** | `D4-L8-01` routeur (5 h) | `D2-L3-06` puml + render.sh (4 h) | revue MR routeur + stubs vues |")
A("| **J3** | `D4-L8-02` Database PDO (3 h du plan) | `D3-L6-06/07` + `D3-L7-05` tNN (8 h prévues au plan initial) | `D4-L12-05` controles.sh (4 h du plan) |")
A("| **J4** | `D4-L8-03` vues + échappement (4 h) | `D5-L14-06` mesure perf base seule + rapport | revue MR + prépa L9-01 |")
A("| **J5** | `D4-L8-04` auth/CSRF (6 h, déborde S7 si besoin) | aide sur les stubs CommandeRepository (rôle B du plan) | vérifier les preuves de recette ; synthèse asynchrone D1-L1-07 consignée dans docs/21-synthese-retours-audit.md, sans réunion complète revendiquée |")
A("")
A("**Objectif du plan historique : L8 terminé et harnais de tests prêt.** Ce plan n'atteste ni l'exécution des tâches ni des commits et doit être replanifié avant tout usage.")
A("Dans le scénario historique, L8 devait débloquer la chaîne jusqu'au jalon J6 ; ce calendrier ne doit pas être repris sans replanification.")
A("")
A("---")
A("")
A("## 8. Vérifications exécutées et limites du run non-SQL")
A("")
A("Le 23/10/2026, sur le working tree basé sur `5d282f4` (avec les sorties documentaires de cette révision), `node scripts/run-all-tests.mjs --sans-sql` s'est terminé avec le code 0. L'environnement n'avait pas de PHP CLI natif : les suites PHP ont tourné via PHP-WASM 8.2.")
A("- Suites automatisées activées : toutes vertes ; PHPUnit local **57 tests**.")
A("- `tests/node/preferences_test.mjs` : **48 vues FR/EN** et **45 messages métier** vérifiés.")
A("- `tests/node/e2e_isolation.mjs` : **338 vérifications** E2E HTTP internes réussies sur une copie temporaire ; les fichiers témoins du dépôt hôte sont restés inchangés.")
A("- Le lanceur configure explicitement `STORAGE_DRIVER=json` pour ces suites fonctionnelles isolées. Cela ne change pas le pilote applicatif par défaut (SQL/MySQL/PDO) et ne constitue pas une validation du chemin SQL.")
A("")
A("**Hors portée / non joué :** `--sans-sql` saute `tests/php/sql_integration.php` ; aucun test PDO/MySQL ni création de base de test n'a eu lieu pendant ce run. L'analyse de compilation avec PHP CLI natif est `NON JOUÉE` et les **5 tests de concurrence interprocessus** ont été ignorés faute de PHP natif. L'E2E passe par HTTP interne/WASM, pas par un navigateur réel. Les 200 scénarios de recette manuelle restent `NON JOUÉ` ; aucun test manuel LineServ, déploiement, restauration de base, revue croisée étudiante, approbation pédagogique, répétition de soutenance ou mesure de performance sur cible n'est revendiqué.")
A("Le prochain contrôle SQL doit être rapporté séparément, avec le SHA et l'identifiant du run : sa base temporaire isolée est distincte de la cible manuelle LineServ.")
A("")
A("---")
A("")
A("*Todolist MiniShop v1.7 — mise à jour du 23/10/2026 ; répartition des statuts reprise de la revue tâche par tâche de `docs/19`, sans nouvel audit exhaustif du working tree. `docs/19` contient un addendum daté du 15/11/2026, postérieur à cette date : aucun événement ou résultat portant une date future n'est crédité comme réalisé. Le run non-SQL du 23/10 est vert via WASM/JSON isolé ; SQL/PDO, PHP CLI natif et validations humaines restent hors preuve. WBS : 84 tâches et 355 h estimées historiquement ; les 82 h affectées aux tâches ouvertes sont des estimations initiales à revalider, pas du temps réel ni une prévision actuelle. D1-L1-01 repose sur l'attestation utilisateur et la capture fournie (date/dossier Envoyés non visibles, pas de vérification directe) ; D1-L1-02 repose sur l'inventaire de 542 chemins, identique à l'arbre Git du commit `82a1279fff756531489b470c7a6d946e3510085f`, sans vérification fonctionnelle ni assimilation au checkout local `e0c554f` ; D1-L1-07 sur la synthèse asynchrone des feedbacks, sans réunion complète revendiquée. Le registre de recette reste 200 lignes NON JOUÉ ; aucun déploiement, test manuel LineServ, restauration, revue croisée, approbation pédagogique, mesure sur cible ou répétition de soutenance n'est inféré. Les charges A/B/C sont des estimations non nominatives.*")

OUT_MD.write_text("\n".join(md), encoding="utf-8")
print(f"OK MD : {OUT_MD} ({len(md)} lignes) — {N_TASKS} tâches, {REST} h d'estimations WBS initiales affectées aux tâches ouvertes, async {len(ASYNC_TASKS)} tâches")

# =====================================================================
# HTML
# =====================================================================
text = OUT_MD.read_text(encoding="utf-8")
EXTRA = """
.st-f { color:#15803d; font-weight:bold; } .st-p { color:#b45309; font-weight:bold; } .st-t { color:#64748b; font-weight:bold; }
.bar { font-family:monospace; letter-spacing:0; }
"""
html = markdown.markdown(text, extensions=["tables","fenced_code"], output_format="html5")
HTML_CSS = (ROOT / ".tools" / "build_doc04_html.py").read_text(encoding="utf-8")
css = re.search(r'CSS = """(.*?)"""', HTML_CSS, re.S).group(1) + EXTRA
page = f"""<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MiniShop — Todolist : statuts et estimations WBS</title><style>{css}</style></head>
<body>
{html}
</body></html>"""
OUT_HTML.write_text(page, encoding="utf-8")
print(f"OK HTML : {OUT_HTML} ({OUT_HTML.stat().st_size/1024:.0f} Ko)")
