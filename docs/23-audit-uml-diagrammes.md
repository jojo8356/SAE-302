# Audit complet de fidélité des schémas UML (`docs/diagrams/`) face au code

**Date de l’audit :** 2026-11-23  
**Révision contrôlée :** `3d89cfba` (`main`)  
**Périmètre :** les **14 sources PlantUML** (`docs/diagrams/src/*.puml`), les **14 PNG générés réellement** depuis ces sources, et les **14 PNG actuellement versionnés** (`docs/diagrams/*.png`), comparés au code complet (`app/`, `public/`, `sql/`).

---

## 1. Verdict global

**Non : ni les PNG générés depuis `docs/diagrams/src/*.puml`, ni les PNG actuellement versionnés dans `docs/diagrams/*.png` ne sont tous fidèles au code complet.**

La génération effective des 14 sources `.puml` et l’inspection visuelle des **28 images** (14 générées + 14 versionnées) mettent en évidence **trois constats majeurs** :

1. **Désynchronisation totale `.puml` ↔ `.png` versionnés (14 / 14) :**  
   Aucun des 14 PNG actuellement commités dans `docs/diagrams/` ne provient des fichiers `docs/diagrams/src/*.puml` actuels. Les PNG versionnés viennent d’une génération antérieure (avant l’introduction de `StoreInterface` / `JsonStore` / `SqlStore`, des 7 contrôleurs `Admin\*`, des 9 tables et des 18 procédures). Le job CI `docs (plantuml)` régénère les PNG dans un artefact jetable sans mettre à jour le dépôt ; exécuter `bash docs/diagrams/render.sh` aujourd’hui écraserait donc les 14 images versionnées — avec une **régression grave sur `etats_commande.png`** et un **bug visuel bloquant sur `mld_minishop.png`**.
2. **Défauts de rendu et d’ergonomie observés après génération de `src/*.puml` :**  
   - **`mld_minishop` :** syntaxe `class` avec parenthèses SQL (`VARCHAR(100)`, `DECIMAL(10,2)`, `ENUM(...)`) interprétées comme des **méthodes** par PlantUML (chaque table est coupée en deux compartiments attributs/méthodes), et anomalie de rendu faisant apparaître `id_client : <<FK>> client` jusque dans la table `client`.
   - **`diagramme_classes` :** `PanierSession` flotte hors de tout package ; `Parametre`, `CategorieRepository`, `ClientRepository` et `ParametreRepository` flottent isolés sans aucune flèche.
   - **`cas_utilisation_detail` :** `Retirer du panier` flotte sans aucun lien d’acteur ni relation ; la flèche `Valider la commande ..> Se connecter` croise le trait `Ajouter au panier --> Modifier une quantité`.
   - **`cas_utilisation_global` :** les flèches `Visiteur --> UC-05` et `Visiteur --> UC-06` bouclent par le haut en traversant tout le diagramme.
   - **`seq_passer_commande` :** le libellé `[STOCK_INSUFFISANT ou panier vide]` du bloc `alt` chevauche la ligne de séparation et la flèche `302 /mes-commandes/{id}`.
3. **Écarts sémantiques avec le code complet dans les deux jeux :**  
   Aucun des deux jeux ne respecte intégralement les classes, méthodes, colonnes SQL, index, règles de gestion (`RB-*`) et transactions réelles du dépôt.

### Méthode et environnement de génération utilisés

Pour cet audit, les 14 fichiers `docs/diagrams/src/*.puml` ont été compilés réellement hors dépôt (dans `/tmp/rendered_all/`, sans modifier `docs/diagrams/*.png` pour préserver le dépôt propre pendant l’audit) via :
- **Java :** OpenJDK `17.0.9+9` (`jdk4py 17.0.9.2`)
- **PlantUML :** `plantuml.jar` (`1.2019.06` via `node-plantuml`, et syntaxe également validée par le job CI `docs (plantuml)` sous PlantUML `1.2024.0` sur la PR précédente)
- **Graphviz :** `dot` `16.1.0` (`@hpcc-js/wasm-graphviz`)

Les **14 PNG générés** et les **14 PNG versionnés** ont tous été inspectés visuellement.

---

## 2. Tableau synthétique des 14 diagrammes

| # | Diagramme | `.puml` après génération (rendu réel + code) | `.png` actuel (versionné) | Gravité |
|---|---|---|---|---|
| 1 | `architecture_mvc` | **Partiellement fidèle** (rendu propre, mais omet `Config/`, `Service/`, `Tools/`, `Controller --> Views`) | **Obsolète / faux** (`7 tables`, `16 procédures`, `AdminController`, `Hasher`, `app/Model` entités) | Élevée |
| 2 | `diagramme_classes` | **Faux + rendu imparfait** (`AdminController`, 6 entités PHP inexistantes, `JsonStore --> Views`, `PanierSession` hors package, 4 classes isolées) | **Obsolète / faux** (autre diagramme à méthodes fictives `-pdo`, `search()`, `get()`, `total()`, `vide()`) | Critique |
| 3 | `mcd_minishop` | **Incomplet** (8 entités au lieu de 9 : manque `stock_movement_history` ; `comment` nommé `description`) | **Cassé visuellement + faux** (boîtes vides `CMD`/`PROD`/`LIN`/`HIS` détachées, 7 entités, FK fictive admin) | Critique |
| 4 | `mld_minishop` | **Faux + rendu cassé** (`RETOURNEE` inexistant, 6 tables/9, mauvais index/types, colonnes classées en méthodes, `<<FK>> client` dupliquée dans `client`) | **Incomplet / faux** (7 tables/9, relation FK fictive `ADMINISTRATEUR`–`COMMANDE`) | Critique |
| 5 | `etats_commande` | **Faux (régression !)** (`PAYEE --> EN_PREPARATION` inversé, `RETOURNEE` inexistant, manque `EXPEDIEE --> ANNULEE` et `BROUILLON --> EN_PREPARATION`) | **Fidèle** (6 statuts et transitions exactes — seul PNG versionné meilleur que son `.puml`) | Critique sur `.puml` |
| 6 | `cas_utilisation_global` | **Partiellement fidèle + rendu chargé** (droits `UC05`/`UC15` imprécis, manque `/admin/parametres`, boucles d’arc en haut) | **Obsolète / confus** (`14 cas`, `Administrateur --|> Visiteur`, flèches pleines entre UC) | Élevée |
| 7 | `cas_utilisation_detail` | **Partiellement fidèle + rendu défectueux** (`Retirer du panier` sans acteur, flèches croisées, manque `/contact` et `/preferences`) | **Hors sujet total** (dessine la gestion admin du catalogue au lieu du parcours visiteur/client) | Critique |
| 8 | `cas_utilisation_commandes` | **Partiellement fidèle** (nom de fichier trompeur, restreint `/admin/parametres` à `SUPER` à tort) | **Incohérent** (flèches `<<include>>`/`<<extend>>` inversées) | Moyenne |
| 9 | `activite_passer_commande` | **Partiellement fidèle** (panier affiché HT/TVA faux, omet `EN_PREPARATION` et ordre de vidage) | **Partiellement fidèle** (double `UPDATE` statut, `SIGNAL` vs `p_code_retour`, omet `+ frais_port`) | Moyenne |
| 10 | `seq_authentification` | **Faux sur un acteur + noms inexacts** (`Auth -> Store` faux, noms `rechercherParEmail`/`connecter`/`date_derniere_connexion` inexacts, omet `COMPTE_BLOQUE` et `render` 200) | **Obsolète / faux** (`login.php`, `AuthMiddleware`, anti-brute-force 5 échecs inexistant, `user_id`) | Élevée |
| 11 | `seq_consulter_catalogue` | **Globalement fidèle** (rendu propre ; omet `CategorieRepository` et `sp_search_products`) | **Hors sujet total** (dessine `UC-03` fiche produit avec `ProduitController::parSlug` inexistant) | Critique sur `.png` |
| 12 | `seq_recherche_produit` | **Partiellement fidèle** (`total` vs `total_trouves`, omet `sp_search_products` et vue partagée) | **Obsolète / faux** (`SearchRequest`, `SearchQuery`, `par_page <= 60` alors que `12`) | Élevée |
| 13 | `seq_ajout_panier` | **Faux sur le contrat réel** (JSON vs form-urlencoded, `disponible()`, `409 {reste}`, `200` incomplet) | **Obsolète / faux** (`panier/ajouter.php`, `PanierController`, JSON `{lignes, total, stockRestant}`) | Élevée |
| 14 | `seq_passer_commande` | **Faux sur la transaction SQL + chevauchement visuel** (`BEGIN`/`COMMIT` PHP interdit avec `sp_create_order_from_basket`, noms faux, texte `alt` superposé) | **Faux** (`$pdo->beginTransaction()`, `409`/`200` au lieu de `302`, omet `+ frais_port` et `p_payee`) | Critique |

---

## 3. Audit détaillé diagramme par diagramme

### 3.1 `architecture_mvc` (`architecture_mvc.puml` vs `architecture_mvc.png`)

- **Réalité du code :**
  - `public/index.php` démarre `Auth::demarrer()` et enregistre les routes sur `App\Router`.
  - `app/Controller/` contient `Controller` (abstrait), `HomeController`, `CatalogueController`, `AuthController`, `CompteController`, `PanierController`, `CommandeController`, `ApiController`, plus **7 contrôleurs d’administration** dans `app/Controller/Admin/` (`DashboardController`, `ProduitAdminController`, `CategorieAdminController`, `StockAdminController`, `CommandeAdminController`, `ParametreAdminController`, `EquipeAdminController`).
  - `app/Config/` (`Database`, `DbConnexion`, `Env`), `app/Service/` (`RetentionComptes`, `NettoyageJournaux`, `NettoyageSauvegardes`), `app/Tools/` (`Heure`, `SqlLoader`, `DbInstaller`, `DbProvision`, `EnvFile`, `Revision`, `VolumetryGenerator`), `app/View/Image/*` et `app/Security/xss.php` complètent l’architecture.
  - Les contrôleurs (`CatalogueController`, `StockAdminController`, `CommandeAdminController`, `HomeController`) importent directement `App\Config\Database` et `App\Model\Data\Views`.
  - La base MySQL/MariaDB (`sql/01`–`03`) compte **9 tables**, **3 vues**, **1 fonction** (`fn_param`), **18 procédures stockées** et **16 triggers**.
- **Constat après génération (`.puml`) :**
  - Le rendu généré est lisible et la hiérarchie en 3 couches (`Présentation` / `Métier` / `Données`) autour de `StoreInterface` (`JsonStore` / `SqlStore`) est juste, mais omet `app/Config/*` (`Database`), `app/Service/*`, `app/Tools/*`, le lien `Controller --> Views` et les compteurs exacts de la base.
- **Écarts dans le `.png` versionné :**
  - Montre `AdminController` (inexistant), `app/Model/{Client, Categorie, Produit, Commande, LigneCommande}` (classes inexistantes), `app/Security/{Csrf, Hasher, Auth}` (`Hasher` n’existe pas), `Database (singleton PDO, ATTR_EMULATE_PREPARES = false)` sans `StoreInterface`/`JsonStore`/`SqlStore`, et écrit `7 tables` + `16 procédures stockées` au lieu de `9 tables` + `18 procédures` + `1 fonction` + `3 vues`.

---

### 3.2 `diagramme_classes` (`diagramme_classes.puml` vs `diagramme_classes.png`)

- **Réalité du code :**
  - Aucune classe entité (`Produit`, `Categorie`, `Client`, `Commande`, `LigneCommande`, `Parametre`, `Administrateur`, `OrderStatusHistory`) n’existe en PHP : les repositories manipulent des tableaux associatifs (`array<string,mixed>`). Le seul objet de domaine hors couche `Data` est `App\Model\PanierSession`.
  - `AdminController` n’existe pas : il y a 7 classes concrètes sous `App\Controller\Admin\*`, plus `HomeController`, `CatalogueController`, `AuthController`, `CompteController`, `PanierController`, `CommandeController`, `ApiController`, toutes filles de `App\Controller\Controller`.
  - `ClientRepository` délègue les comptes à `ClientAccountGateway` (`ClientAccountGatewayFactory` → `SqlClientAccountGateway` / `StoreClientAccountGateway`).
  - `SqlStore` implémente `StoreInterface`, `StoredProcedureResultExecutor`, `StoredFunctionExecutor`, `RowLockingStore`.
  - `JsonStore` implémente `StoreInterface` et `StockMovementAuditor`, et s’appuie sur `Triggers`, `Schema`, `Journal`, `Filter` — **jamais sur `Views`** (`Views` est appelé par `ProduitRepository`, `CatalogueController`, `StockAdminController` et `CommandeAdminController`).
- **Constat après génération (`.puml`) :**
  - Visuellement : `PanierSession` est placé **en dehors de tout package**, et `Parametre`, `CategorieRepository`, `ClientRepository`, `ParametreRepository` flottent isolés sans aucune relation.
  - Sémantiquement : déclare 7 classes inexistantes (`AdminController`, `Produit`, `Categorie`, `Client`, `Commande`, `LigneCommande`, `Parametre`), omet les 7 `*AdminController`, `HomeController`, `AuthController`, `CompteController`, `ApiController`, `ClientAccountGateway*`, `StoredProcedure*`, `Schema`, `Filter`, `Database`, trace une fausse flèche `JsonStore --> Views`, et impose `Commande "1" *-- "1..*" LigneCommande` alors qu’un `BROUILLON` naît avec `0` ligne avant `addOrderLine()`.
- **Écarts dans le `.png` versionné :**
  - Diagramme différent, avec méthodes et attributs fictifs (`-pdo : PDO`, `ProduitRepository::search()`, `get()`, `save(Produit)`, `hide()`, `ClientRepository::findByEmail()`, `update()`, `CommandeRepository::confirm()`, `changeStatus()`, `cancel()`, `historyOf()`, `PanierSession::total()`, `vide()`).

---

### 3.3 `mcd_minishop` (`mcd_minishop.puml` vs `mcd_minishop.png`)

- **Réalité SQL (`sql/01_minishop_schema.sql` et `app/Model/Data/Schema.php`) :**
  - **9 tables** : `administrateur`, `client`, `categorie`, `produit`, `stock_movement_history`, `parametre`, `commande`, `ligne_commande`, `order_status_history`.
  - Colonnes réelles notables manquantes ou renommées :
    - `administrateur` : manque `prenom`, `date_creation`, `derniere_connexion` ;
    - `client` : manque `telephone`, `date_creation`, `derniere_connexion` ;
    - `produit` : manque `description`, `stock_initial`, `image_url`, `date_creation`, `date_modification` ;
    - `parametre` : la colonne s’appelle **`comment`** (`sql/01_minishop_schema.sql:173`), pas `description`, et il manque `date_modification` ;
    - `commande` : manque `montant_avant_annulation`, `commentaire`, `date_modification` ;
    - `order_status_history` : manque `changed_by_role` (`ENUM('CLIENT','ADMIN','SYSTEME')`) ;
    - `stock_movement_history` : **table absente** (`id_mouvement`, `id_produit`, `reference_produit`, `nom_produit`, `mode_mouvement`, `quantite`, `stock_avant`, `stock_apres`, `motif`, `changed_by`, `changed_by_role`, `changed_at`).
- **Constat après génération (`.puml`) :**
  - Le rendu généré est lisible, mais le titre annonce `8 entités` et omet la 9e table `STOCK_MOVEMENT_HISTORY`, nomme `description` au lieu de `comment` sur `PARAMETRE`, omet les attributs ci-dessus, et réduit `PARAMETRE` à `TVA par défaut` (il gère aussi `frais_port`, `franchise_port` et `conservation_compte`).
- **Écarts dans le `.png` versionné :**
  - **Visuellement cassé** : 4 boîtes vides (`CMD`, `PROD`, `LIN`, `HIS`) détachées de `COMMANDE`, `PRODUIT`, `LIGNE_COMMANDE`, `ORDER_STATUS_HISTORY` ; n’a que 7 entités ; trace des associations fictives `ADMINISTRATEUR — COMMANDE` et `ADMINISTRATEUR — PRODUIT`.

---

### 3.4 `mld_minishop` (`mld_minishop.puml` vs `mld_minishop.png`)

- **Réalité SQL (`sql/01_minishop_schema.sql`) :**
  - 9 tables ; `commande.statut` = `ENUM('BROUILLON','EN_PREPARATION','PAYEE','EXPEDIEE','LIVREE','ANNULEE')` (6 valeurs).
  - `produit.reference` = `VARCHAR(30)`, `produit.slug` = `VARCHAR(180)`, `order_status_history.id` = `BIGINT UNSIGNED`.
  - Index réels sur `produit` : `ft_produit_recherche (nom, description)` (`FULLTEXT`), `idx_produit_categorie (id_categorie)`, `idx_produit_visible (visible, stock)`.
  - Triggers prix TTC : `trg_produit_ttc` (`BEFORE INSERT`) et `trg_produit_ttc_update` (`BEFORE UPDATE`).
- **Constat après génération (`.puml`) :**
  - **Défaut de rendu PlantUML observé :** l’usage de `class` avec des types contenant des parenthèses (`VARCHAR(100)`, `DECIMAL(10,2)`, `ENUM(...)`) amène PlantUML à classer ces colonnes dans le **compartiment des méthodes** (sous le trait horizontal), et produit une anomalie sur `client` qui affiche `id_client : <<FK>> client` dans son propre bloc.
  - **Erreurs sémantiques :** invente **`RETOURNEE`** dans `commande.statut` ; **omet 3 tables sur 9** (`administrateur`, `parametre`, `stock_movement_history`) ; tailles/types inexacts (`VARCHAR(40)`/`VARCHAR(160)`/`BIGINT`) ; note citant des index inexistants (`idx_catalogue`, `idx_recherche(nom)`).
- **Écarts dans le `.png` versionné :**
  - Omet `parametre` et `stock_movement_history` (7 tables sur 9) et trace une fausse relation FK `ADMINISTRATEUR 1 -- 0..* COMMANDE (SUIVRE)`.

---

### 3.5 `etats_commande` (`etats_commande.puml` vs `etats_commande.png`)

- **Réalité du code (`app/Model/Data/Triggers.php:55-64` et `sql/03_minishop_triggers.sql:211-218`) :**
  ```text
  BROUILLON      -> EN_PREPARATION | PAYEE | ANNULEE
  EN_PREPARATION -> PAYEE | EXPEDIEE | ANNULEE
  PAYEE          -> EXPEDIEE | ANNULEE
  EXPEDIEE       -> LIVREE | ANNULEE
  LIVREE         -> (terminal)
  ANNULEE        -> (terminal)
  ```
- **Constat après génération (`.puml` — régression critique) :**
  - Inverse une transition : dessine **`PAYEE --> EN_PREPARATION`** alors que seule **`EN_PREPARATION --> PAYEE`** est autorisée (`PAYEE --> EN_PREPARATION` lève `RB11_TRANSITION_STATUT_INTERDITE`).
  - Omet **`BROUILLON --> EN_PREPARATION`** et **`EXPEDIEE --> ANNULEE`**.
  - Invente un 7e état **`RETOURNEE`** (`EXPEDIEE --> RETOURNEE`, `LIVREE --> RETOURNEE`, `RETOURNEE --> [*]`) absent de SQL et de PHP.
- **État du `.png` versionné :**
  - **Fidèle à 100 % à `Triggers::TRANSITIONS` et `trg_commande_transition_statut`** : 6 statuts, toutes les flèches exactes. Ne pas écraser ce PNG avant de corriger `etats_commande.puml`.

---

### 3.6 `cas_utilisation_global` (`cas_utilisation_global.puml` vs `cas_utilisation_global.png`)

- **Constat après génération (`.puml`) :**
  - Visuellement : les deux grandes flèches `Visiteur --> UC-05` et `Visiteur --> UC-06` contournent le cadre par le haut et croisent le reste du schéma.
  - Sémantiquement : `Visiteur` ne peut pas gérer son compte (routes `/compte*` gardées `'client'`) ; `Gérer les comptes d'équipe` (`/admin/equipe`, garde `'super'`) n’est pas un `<<extend>>` de `UC-05` ; la gestion des paramètres (`/admin/parametres`, EF-ADM-11) est absente.
- **Écarts dans le `.png` versionné :**
  - Annonce `14 cas` (omet paramètres et équipe), dessine `Administrateur --|> Visiteur` contraire à `RB-13`, et présente des flèches pleines entre UC (`UC-01 -> UC-06`, etc.).

---

### 3.7 `cas_utilisation_detail` (`cas_utilisation_detail.puml` vs `cas_utilisation_detail.png`)

- **Constat après génération (`.puml`) :**
  - Visuellement : le cas **`Retirer du panier`** flotte dans le cadre sans aucune flèche d’acteur ni relation, et la flèche `Valider la commande ..> Se connecter` croise `Ajouter au panier --> Modifier une quantité`.
  - Sémantiquement : `Modifier une quantité` et `Retirer du panier` sont accessibles aussi au `Visiteur` (routes `/panier/*` et `/api/panier/*` de garde `'public'`) ; `U6 ..> U7` et `U6 --> U7` sont redondants ; manquent `/contact` et `/preferences`.
- **Écarts dans le `.png` versionné :**
  - **Hors sujet total :** dessine la gestion du catalogue back-office par `Administrateur` au lieu du parcours visiteur/client.

---

### 3.8 `cas_utilisation_commandes` (`cas_utilisation_commandes.puml` vs `cas_utilisation_commandes.png`)

- **Constat après génération (`.puml`) :**
  - Rendu lisible, mais le nom du fichier (`cas_utilisation_commandes`) ne correspond plus à son périmètre (tout le back-office) et restreint `Gérer les paramètres` au seul `Super administrateur`, alors que `/admin/parametres` porte la garde `'admin'` (`Auth::exigeAdmin()`, donc accessible aussi aux `GESTIONNAIRE` dans `public/index.php:129-130` et `ParametreAdminController.php:40`).
- **Écarts dans le `.png` versionné :**
  - Couvre uniquement les commandes (admin + client), avec des relations `<<include>>` / `<<extend>>` inversées (`Confirmer la commande ..> Valider le panier : <<include>>`, `Retirer un produit ..> Consulter le panier : <<extend>>`).

---

### 3.9 `activite_passer_commande` (`activite_passer_commande.puml` vs `activite_passer_commande.png`)

- **Constat après génération (`.puml`) :**
  - Rendu clair, mais :
    - `app/View/panier/index.php` affiche `Prix unitaire TTC`, `Sous-total (marchandises TTC)`, `Frais de port`, `Total TTC` (**ni sous-total HT ni TVA détaillés dans le panier**).
    - Omet le statut `EN_PREPARATION` lorsque la case `paiement_simule` est décochée (`app/View/commande/valider.php`).
    - Place `Vider le panier` après l’affichage du récapitulatif, alors que `CommandeController::valider()` appelle `$panier->vider()` avant `302 /mes-commandes/{id}`.
- **Écarts dans le `.png` versionné :**
  - Montre deux `UPDATE` successifs (`EN_PREPARATION` puis `PAYEE`) au lieu de l’unique `UPDATE ... IF(IFNULL(p_payee,0)=1, 'PAYEE', 'EN_PREPARATION')`, omet `+ c.frais_port` dans `montant_total = SUM(total_ligne)`, et confond `SIGNAL` vs capture `v_code = 'STOCK_INSUFFISANT'` + `OUT p_code_retour`.

---

### 3.10 `seq_authentification` (`seq_authentification.puml` vs `seq_authentification.png`)

- **Constat après génération (`.puml`) :**
  - **Flèche fausse visible sur le rendu :** `Auth -> Store : mise à jour date_derniere_connexion` — en réalité `Auth` ne touche jamais à `Store`, c’est **`AuthController`** qui appelle `ClientRepository::noteConnexion()` (et la colonne SQL est `derniere_connexion`).
  - Noms inexacts : `ClientRepository::getCredentials(email)` (noté `rechercherParEmail`), `Auth::connecterClient(id)` (noté `connecter`).
  - Omet la branche `COMPTE_BLOQUE` (message distinct : `Ce compte est bloqué. Contactez le support.`), la redirection vers `$_SESSION['cible_apres_connexion']`, et le fait qu’en échec `AuthController::connexion()` rend `200` la vue `auth/connexion` (sans `302 /connexion`).
- **Écarts dans le `.png` versionné :**
  - Cite `login.php`, `AuthMiddleware`, `findByEmail`, `TokenCSRFInvalide`, `419`/`401`/`403`, un anti-brute-force `5 échecs / 15 min` inexistant, `$_SESSION['user_id']`/`$_SESSION['role']` (réel : `$_SESSION['client_id']`), `vue/login.php`.

---

### 3.11 `seq_consulter_catalogue` (`seq_consulter_catalogue.puml` vs `seq_consulter_catalogue.png`)

- **Constat après génération (`.puml`) :**
  - Rendu propre et flux globalement fidèle à `CatalogueController::catalogue()` → `ProduitRepository::searchProducts($criteres)` (`par_page = 12`) → `200 HTML` ; omet `CategorieRepository::listerAvecCompteur()` et l’appel à `sp_search_products` en mode SQL.
- **Écarts dans le `.png` versionné :**
  - **Hors sujet total :** dessine `Séquence 5 (bonus) — Consulter la fiche produit (UC-03)` avec `ProduitController::parSlug` (inexistant — c’est `CatalogueController::produit()` + `ProduitRepository::findVisibleBySlug()`).

---

### 3.12 `seq_recherche_produit` (`seq_recherche_produit.puml` vs `seq_recherche_produit.png`)

- **Constat après génération (`.puml`) :**
  - La clé retournée par `ProduitRepository::searchProducts()` est `total` (`total_trouves` est la colonne du 2e jeu de résultat de `sp_search_products`), en mode SQL la requête appelle `sp_search_products`, et la vue sans résultat reste `catalogue/index.php`.
- **Écarts dans le `.png` versionné :**
  - Invente `SearchRequest`, `SearchQuery`, `CatalogueController::liste()`, `ProduitRepository::search(query)`, `taille de page bornée à 60` (`par_page = 12`) et `vue/catalogue.php`.

---

### 3.13 `seq_ajout_panier` (`seq_ajout_panier.puml` vs `seq_ajout_panier.png`)

- **Réalité du code (`public/js/panier.js`, `app/Controller/ApiController.php`, `app/Model/PanierSession.php`) :**
  - `panier.js` envoie `POST /api/panier/ajouter` en **`application/x-www-form-urlencoded`** (`URLSearchParams(new FormData(formulaire))`), pas en JSON.
  - `ApiController::ajouter()` appelle `exigerPoste()` (`POST` + `Csrf::estValide()`, sinon `403 {ok:false, code:'CSRF_INVALIDE', message}`) puis appelle directement `PanierSession::ajouter($idProduit, $quantite)` — **sans passer par `ProduitRepository::disponible()`**.
  - `PanierSession::ajouter()` plafonne silencieusement à `(int) $produit['stock']` via `min($demande, $stock)` et ne lève `BusinessError` que pour quantité `<= 0` ou produit introuvable/masqué (`409 {ok:false, code, message, nombre_articles}`).
  - En succès (`200`), le JSON renvoyé est `{ok: true, message, nombre_articles, sous_total, frais_port, total_ttc, lien_panier}`.
  - En erreur (`!ok`), `panier.js` annonce le message dans `#zone-annonces` et désactive le bouton (`disabled = true`, `aria-disabled="true"`).
- **Constat après génération (`.puml`) :** montre un envoi JSON, un appel inexistant à `ProduitRepository::disponible()`, une réponse `409 {code: STOCK_INSUFFISANT, reste: N}` inexistante, et une réponse `200` incomplète.
- **Écarts dans le `.png` versionné :** cible `panier/ajouter.php` + `PanierController::ajouter` sur `POST /panier/ajouter`, invente `404 {"erreur":"PRODUIT_INTROUVABLE"}`, `{"erreur":"STOCK_INSUFFISANT","reste":1}` et `200 {lignes, total, stockRestant}`.

---

### 3.14 `seq_passer_commande` (`seq_passer_commande.puml` vs `seq_passer_commande.png`)

- **Réalité du code (`app/Controller/CommandeController.php`, `app/Repository/CommandeRepository.php`, `sql/02_minishop_procedures.sql`) :**
  - `CommandeController::valider()` appelle `verifierCsrf()`, `Auth::exigeClient()`, `Database::store()->setActor(...)`, `CommandeRepository::createOrderFromBasket($idClient, $adresse, $panier->pourCommande(), $payee)`, puis `$panier->vider()` et redirige en `302 /mes-commandes/{id}` (ou `302 /panier` sur `BusinessError`).
  - En mode SQL (`$this->store instanceof StoredProcedureExecutor`), `CommandeRepository::createOrderFromBasket()` appelle `CALL sp_create_order_from_basket(...)` **sans ouvrir de transaction PDO côté PHP** (`START TRANSACTION` / `COMMIT` / `ROLLBACK` sont gérés exclusivement dans la procédure SQL, comme documenté dans `ARCHITECTURE.md` §3).
- **Constat après génération (`.puml`) :**
  - Visuellement : `[STOCK_INSUFFISANT ou panier vide]` chevauche la séparation du bloc `alt` et la flèche `302 /mes-commandes/{id}`.
  - Sémantiquement : montre `BEGIN` / `COMMIT` / `ROLLBACK` côté PHP autour de `sp_create_order_from_basket` (faux en mode SQL), utilise des noms inexacts (`exigerClient()`, `confirmerDepuisPanier()`), omet `p_payee` et `$panier->vider()`.
- **Écarts dans le `.png` versionné :**
  - Cumule `$pdo->beginTransaction()` + `$pdo->commit()` avec `START TRANSACTION`, `creerDepuisPanier`, codes `409`/`200` au lieu de `302`, `session_unset('panier')`, `erreur 1644` directe et omission de `+ frais_port`.

---

## 4. Plan d’action recommandé avant de régénérer les PNG

1. **Corriger en priorité les 5 diagrammes critiques dans `docs/diagrams/src/` :**
   - `etats_commande.puml` : supprimer `RETOURNEE`, inverser vers `EN_PREPARATION --> PAYEE`, ajouter `BROUILLON --> EN_PREPARATION` et `EXPEDIEE --> ANNULEE`.
   - `mld_minishop.puml` : utiliser `entity` (ou `{field}`) pour éviter le découpage attributs/méthodes et le bug `<<FK>> client`, supprimer `RETOURNEE`, ajouter `administrateur`, `parametre`, `stock_movement_history`, corriger les types (`VARCHAR(30)`, `VARCHAR(180)`, `BIGINT UNSIGNED`) et les vrais noms d’index/triggers.
   - `mcd_minishop.puml` : passer à **9 entités** (ajouter `STOCK_MOVEMENT_HISTORY`), renommer `description` → `comment` sur `PARAMETRE`, compléter les attributs.
   - `diagramme_classes.puml` : remplacer `AdminController` par les 7 contrôleurs `Admin\*`, retirer les 6 entités PHP inexistantes, placer `PanierSession` dans le package `Modèle`, corriger `JsonStore --> Views` et relier les dépôts isolés.
   - `seq_passer_commande.puml` : supprimer `BEGIN`/`COMMIT`/`ROLLBACK` côté PHP en mode SQL (transaction gérée dans `sp_create_order_from_basket`), corriger `Auth::exigeClient()`, `createOrderFromBasket()`, `p_payee`, `$panier->vider()` et aérer le bloc `alt`.
2. **Corriger les 9 autres `.puml`** (`architecture_mvc`, les 3 `cas_utilisation_*`, `activite_passer_commande`, `seq_authentification`, `seq_consulter_catalogue`, `seq_recherche_produit`, `seq_ajout_panier`) selon les écarts relevés au §3 (notamment rattacher `Retirer du panier` à `Visiteur` et `Client`, et déplacer `noteConnexion` vers `AuthController -> ClientRepository -> Store`).
3. **Régénérer les 14 PNG** via `bash docs/diagrams/render.sh`, vérifier visuellement chacun des 14 rendus, puis committer ensemble `docs/diagrams/src/*.puml` et `docs/diagrams/*.png`.

---

## 5. Suivi de résolution (post-audit)

L’ensemble des 14 sources `docs/diagrams/src/*.puml` et des 14 images `docs/diagrams/*.png` a été corrigé et régénéré via `docs/diagrams/render.sh`, puis inspecté visuellement (14/14 conformes au code `app/`, `public/` et `sql/`, sans anomalie de rendu).

