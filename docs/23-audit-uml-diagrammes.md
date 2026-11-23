# Audit complet de fidélité des schémas UML (`docs/diagrams/`) face au code

**Date de l’audit :** 2026-11-23  
**Révision contrôlée :** `3d89cfba` (`main`)  
**Périmètre :** les **14 sources PlantUML** (`docs/diagrams/src/*.puml`) et les **14 images PNG versionnées** (`docs/diagrams/*.png`), comparées aux sources réelles (`app/`, `public/`, `sql/`).

---

## 1. Verdict global

**Non : les schémas UML ne sont pas fidèles au code complet — ni dans les PNG actuellement versionnés, ni dans les PNG qui seraient produits après génération depuis `docs/diagrams/src/*.puml`.**

L’audit met en évidence **deux constats structurants** :

1. **Désynchronisation totale `.puml` ↔ `.png` (14/14) :**  
   Aucun des 14 PNG actuellement commités dans `docs/diagrams/` ne correspond au fichier `.puml` homonyme dans `docs/diagrams/src/`. Les 14 PNG proviennent d’une génération antérieure (avant la généralisation `StoreInterface` / `JsonStore` / `SqlStore`, le découpage en 7 contrôleurs back-office, le passage à 9 tables et 18 procédures). Le job CI `docs (plantuml)` exécute `docs/diagrams/render.sh` et dépose le résultat en artefact temporaire sans mettre à jour le dépôt ; lancer `bash docs/diagrams/render.sh` localement remplacerait donc les 14 images actuelles par des rendus très différents — et provoquerait même une **régression grave sur `etats_commande.png`**.
2. **Écarts avec le code réel dans les deux jeux :**  
   - **Dans les `.puml` (donc après génération via `render.sh`) :** `etats_commande.puml` inverse une transition réelle et invente un statut `RETOURNEE` inexistant ; `mld_minishop.puml` invente aussi `RETOURNEE`, omet 3 tables sur 9 et cite des index inexistants ; `mcd_minishop.puml` annonce 8 entités et omet `stock_movement_history` ; `diagramme_classes.puml` cite `AdminController` et 6 entités PHP inexistantes ; `seq_ajout_panier.puml`, `seq_passer_commande.puml`, `seq_authentification.puml` et `cas_utilisation_commandes.puml` comportent des erreurs d’architecture ou de signatures.
   - **Dans les `.png` actuellement versionnés :** `mcd_minishop.png` est visuellement cassé (4 boîtes d’alias vides `CMD`, `PROD`, `LIN`, `HIS` détachées) ; `seq_consulter_catalogue.png` montre la fiche produit (`UC-03`) au lieu du catalogue ; `cas_utilisation_detail.png` montre la gestion du catalogue admin alors que le `.puml` et la légende concernent le visiteur/client ; `architecture_mvc.png` annonce `7 tables` et `16 procédures` au lieu de `9 tables`, `18 procédures`, `1 fonction`, `3 vues`, `16 triggers`.

### Note honnête sur l’exécution de génération locale

Dans l’environnement d’audit utilisé pour rédiger ce rapport, `java`, `plantuml` et `graphviz` ne sont pas installés : `bash docs/diagrams/render.sh` n’y a donc pas été exécuté.  
En revanche :
- les **14 PNG versionnés** ont tous été inspectés visuellement ;
- les **14 fichiers `.puml`** ont tous été lus intégralement et confrontés ligne par ligne aux fichiers `app/`, `public/` et `sql/`.

---

## 2. Tableau synthétique des 14 diagrammes

| # | Diagramme | `.puml` (résultat après génération) | `.png` actuel (versionné) | Gravité des écarts |
|---|---|---|---|---|
| 1 | `architecture_mvc` | **Partiellement fidèle** (incomplet : omet `Config/`, `Service/`, `Tools/`, `Views` depuis contrôleurs) | **Obsolète / faux** (`7 tables`, `16 procédures`, `AdminController`, `Hasher`, `app/Model` entités) | Élevée |
| 2 | `diagramme_classes` | **Faux / obsolète** (`AdminController`, 6 classes entités PHP inexistantes, `JsonStore --> Views`) | **Obsolète / faux** (autre diagramme à méthodes fictives `-pdo`, `search()`, `get()`, `total()`, `vide()`) | Critique |
| 3 | `mcd_minishop` | **Incomplet** (8 entités au lieu de 9 : manque `stock_movement_history` ; `comment` nommé `description`) | **Cassé visuellement + faux** (boîtes vides `CMD`/`PROD`/`LIN`/`HIS`, 7 entités, FK fictive admin) | Critique |
| 4 | `mld_minishop` | **Faux / incomplet** (`RETOURNEE` inexistant, 6 tables sur 9, mauvais index/types) | **Incomplet / faux** (7 tables sur 9, relation FK fictive `ADMINISTRATEUR`–`COMMANDE`) | Critique |
| 5 | `etats_commande` | **Faux (régression !)** (`PAYEE --> EN_PREPARATION` inversé, `RETOURNEE` inexistant, manque `EXPEDIEE --> ANNULEE`) | **Globalement fidèle** (6 statuts et transitions exactes — seul PNG meilleur que son `.puml`) | Critique sur `.puml` |
| 6 | `cas_utilisation_global` | **Partiellement fidèle** (droits `UC05`/`UC15` imprécis, manque `/admin/parametres`) | **Obsolète / confus** (`14 cas`, `Administrateur --|> Visiteur`, flèches pleines entre UC) | Élevée |
| 7 | `cas_utilisation_detail` | **Partiellement fidèle** (droits visiteur `U7`/`U8`, flèches `U6`↔`U7` redondantes, manque `/contact` et `/preferences`) | **Hors sujet total** (dessine la gestion admin du catalogue au lieu du parcours visiteur/client) | Critique |
| 8 | `cas_utilisation_commandes` | **Partiellement fidèle** (nom de fichier trompeur, restreint `/admin/parametres` à `SUPER` à tort) | **Incohérent** (flèches `<<include>>`/`<<extend>>` inversées) | Moyenne |
| 9 | `activite_passer_commande` | **Partiellement fidèle** (panier affiché HT/TVA faux, omet `EN_PREPARATION` et ordre de vidage) | **Partiellement fidèle** (double `UPDATE` statut, `SIGNAL` vs `p_code_retour`, omet `+ frais_port`) | Moyenne |
| 10 | `seq_authentification` | **Partiellement fidèle** (noms de méthodes/colonnes inexacts, omet `COMPTE_BLOQUE` et `render` 200) | **Obsolète / faux** (`login.php`, `AuthMiddleware`, anti-brute-force 5 échecs inexistant, `user_id`) | Élevée |
| 11 | `seq_consulter_catalogue` | **Globalement fidèle** (omet `CategorieRepository` et `sp_search_products`) | **Hors sujet total** (dessine `UC-03` fiche produit avec `ProduitController::parSlug` inexistant) | Critique sur `.png` |
| 12 | `seq_recherche_produit` | **Partiellement fidèle** (`total` vs `total_trouves`, omet `sp_search_products` et vue partagée) | **Obsolète / faux** (`SearchRequest`, `SearchQuery`, `par_page <= 60` alors que `12`) | Élevée |
| 13 | `seq_ajout_panier` | **Faux sur le contrat réel** (JSON vs form-urlencoded, `disponible()`, `409 {reste}`, `200` incomplet) | **Obsolète / faux** (`panier/ajouter.php`, `PanierController`, JSON `{lignes, total, stockRestant}`) | Élevée |
| 14 | `seq_passer_commande` | **Faux sur la transaction SQL** (`BEGIN`/`COMMIT` côté PHP interdit avec `sp_create_order_from_basket`, noms faux) | **Faux** (`$pdo->beginTransaction()`, `409`/`200` au lieu de `302`, omet `+ frais_port` et `p_payee`) | Critique |

---

## 3. Audit détaillé diagramme par diagramme

### 3.1 `architecture_mvc` (`architecture_mvc.puml` vs `architecture_mvc.png`)

- **Réalité du code :**
  - `public/index.php` démarre `Auth::demarrer()` et enregistre les routes sur `App\Router`.
  - `app/Controller/` contient `Controller` (abstrait), `HomeController`, `CatalogueController`, `AuthController`, `CompteController`, `PanierController`, `CommandeController`, `ApiController`, plus **7 contrôleurs d’administration** dans `app/Controller/Admin/` (`DashboardController`, `ProduitAdminController`, `CategorieAdminController`, `StockAdminController`, `CommandeAdminController`, `ParametreAdminController`, `EquipeAdminController`).
  - `app/Config/` (`Database`, `DbConnexion`, `Env`), `app/Service/` (`RetentionComptes`, `NettoyageJournaux`, `NettoyageSauvegardes`), `app/Tools/` (`Heure`, `SqlLoader`, `DbInstaller`, `DbProvision`, `EnvFile`, `Revision`, `VolumetryGenerator`), `app/View/Image/*` et `app/Security/xss.php` font partie intégrante de l’architecture.
  - Les contrôleurs (`CatalogueController`, `StockAdminController`, `CommandeAdminController`, `HomeController`) importent directement `App\Config\Database` et `App\Model\Data\Views`.
  - La base MySQL/MariaDB (`sql/01`–`03`) compte **9 tables**, **3 vues**, **1 fonction** (`fn_param`), **18 procédures stockées** et **16 triggers**.
- **Écarts dans `.puml` (après génération) :**
  - Structure générale en 3 couches (`Présentation` / `Métier` / `Données`) et abstraction `StoreInterface` (`JsonStore` / `SqlStore`) globalement justes, mais omet `app/Config/*` (`Database`), `app/Service/*`, `app/Tools/*`, le lien `Controller --> Views` et les chiffres exacts de la base (`MySQL procédures + triggers`).
- **Écarts dans `.png` actuel :**
  - Diagramme différent : montre `AdminController` (inexistant), `app/Model/{Client, Categorie, Produit, Commande, LigneCommande}` (classes inexistantes), `app/Security/{Csrf, Hasher, Auth}` (`Hasher` n’existe pas), `Database (singleton PDO, ATTR_EMULATE_PREPARES = false)` sans `StoreInterface`/`JsonStore`/`SqlStore`, et écrit `7 tables` + `16 procédures stockées` au lieu de `9 tables` + `18 procédures` + `1 fonction` + `3 vues`.

---

### 3.2 `diagramme_classes` (`diagramme_classes.puml` vs `diagramme_classes.png`)

- **Réalité du code :**
  - Aucune classe entité (`Produit`, `Categorie`, `Client`, `Commande`, `LigneCommande`, `Parametre`, `Administrateur`, `OrderStatusHistory`) n’existe en PHP : les repositories manipulent et retournent des tableaux associatifs (`array<string,mixed>`). Le seul objet de domaine/modèle hors couche `Data` est `App\Model\PanierSession`.
  - `AdminController` n’existe pas : il y a 7 classes concrètes sous `App\Controller\Admin\*`, plus `HomeController`, `CatalogueController`, `AuthController`, `CompteController`, `PanierController`, `CommandeController`, `ApiController`, toutes filles de `App\Controller\Controller`.
  - `ClientRepository` délègue les comptes à `ClientAccountGateway` (`ClientAccountGatewayFactory` → `SqlClientAccountGateway` / `StoreClientAccountGateway`).
  - `SqlStore` implémente `StoreInterface`, `StoredProcedureResultExecutor`, `StoredFunctionExecutor`, `RowLockingStore`.
  - `JsonStore` implémente `StoreInterface` et `StockMovementAuditor`, et s’appuie sur `Triggers`, `Schema`, `Journal`, `Filter` — **jamais sur `Views`**. Ce sont `ProduitRepository`, `CatalogueController`, `StockAdminController` et `CommandeAdminController` qui appellent `Views`.
- **Écarts dans `.puml` (après génération) :**
  - Déclare 7 classes inexistantes (`AdminController`, `Produit`, `Categorie`, `Client`, `Commande`, `LigneCommande`, `Parametre`).
  - Utilise `PanierSession` dans une flèche sans l’avoir déclaré dans aucun package.
  - Omet `HomeController`, `AuthController`, `CompteController`, `ApiController`, les 7 `*AdminController`, `ClientAccountGateway*`, `StoredProcedure*`, `Schema`, `Filter`, `Database`.
  - Flèche fausse `JsonStore --> Views`.
  - Multiplicités `Commande "1" *-- "1..*" LigneCommande` trompeuse lors de la phase `BROUILLON` (une commande créée par `sp_create_order` naît avec 0 ligne puis reçoit ses lignes avant validation `RB-04`).
- **Écarts dans `.png` actuel :**
  - Diagramme complètement différent, avec méthodes et attributs fictifs : `-pdo : PDO` dans chaque repository, `ProduitRepository::search()`, `get()`, `save(Produit)`, `hide(id)` (réels : `searchProducts()`, `find()`, `saveProduct()`, `deleteProduct()`), `ClientRepository::findByEmail()`, `update()` (réels : `getCredentials()`, `emailExiste()`, `updateClient()`), `CommandeRepository::confirm()`, `changeStatus()`, `cancel()`, `historyOf()` (réels : `confirmOrder()`, `updateOrderStatus()`, `cancelOrder()`, `historique()`), `PanierSession::total()`, `vide()` (réels : `recapitulatif()`, `estVide()`).

---

### 3.3 `mcd_minishop` (`mcd_minishop.puml` vs `mcd_minishop.png`)

- **Réalité SQL (`sql/01_minishop_schema.sql` et `app/Model/Data/Schema.php`) :**
  - **9 tables** : `administrateur`, `client`, `categorie`, `produit`, `stock_movement_history`, `parametre`, `commande`, `ligne_commande`, `order_status_history`.
  - Colonnes notables de `sql/01_minishop_schema.sql` :
    - `administrateur` : `id_admin`, `nom`, `prenom`, `email`, `mot_de_passe_hash`, `role`, `actif`, `date_creation`, `derniere_connexion`.
    - `client` : inclut `telephone`, `date_creation`, `derniere_connexion`.
    - `produit` : inclut `description`, `stock_initial`, `image_url`, `date_creation`, `date_modification`.
    - `parametre` : `cle`, `valeur`, `unite`, `comment`, `date_modification`.
    - `commande` : inclut `montant_avant_annulation`, `commentaire`, `date_modification`.
    - `order_status_history` : inclut `changed_by_role` (`ENUM('CLIENT','ADMIN','SYSTEME')`).
    - `stock_movement_history` : `id_mouvement`, `id_produit`, `reference_produit`, `nom_produit`, `mode_mouvement`, `quantite`, `stock_avant`, `stock_apres`, `motif`, `changed_by`, `changed_by_role`, `changed_at` (sans FK vers `produit` pour survivre à la suppression d’un produit).
- **Écarts dans `.puml` (après génération) :**
  - Titre erroné `MCD — MiniShop (8 entités)` : **il manque `STOCK_MOVEMENT_HISTORY`** (9e table).
  - Colonne `description` dans `PARAMETRE` au lieu de **`comment`**.
  - Plusieurs attributs réels manquants sur `PRODUIT`, `CLIENT`, `COMMANDE`, `ADMINISTRATEUR`, `ORDER_STATUS_HISTORY`, `PARAMETRE`.
  - Lien `PAR .. PRO : TVA par défaut` réducteur (`PARAMETRE` pilote aussi `frais_port`, `franchise_port` et `conservation_compte`).
- **Écarts dans `.png` actuel :**
  - **Rendu visuellement cassé :** quatre boîtes vides d’alias (`CMD`, `PROD`, `LIN`, `HIS`) sont dessinées à part des entités `COMMANDE`, `PRODUIT`, `LIGNE_COMMANDE`, `ORDER_STATUS_HISTORY`.
  - N’affiche que 7 entités (manquent `PARAMETRE` et `STOCK_MOVEMENT_HISTORY`).
  - Dessine des associations `ADMINISTRATEUR 1 --- 0..* COMMANDE (SUIVRE)` et `ADMINISTRATEUR 1 --- 0..* PRODUIT (GERER)` qui ne correspondent à aucune clé étrangère en base.

---

### 3.4 `mld_minishop` (`mld_minishop.puml` vs `mld_minishop.png`)

- **Réalité SQL (`sql/01_minishop_schema.sql`) :**
  - 9 tables, `commande.statut` = `ENUM('BROUILLON','EN_PREPARATION','PAYEE','EXPEDIEE','LIVREE','ANNULEE')` (6 valeurs).
  - `produit.reference` = `VARCHAR(30)`, `produit.slug` = `VARCHAR(180)`, `order_status_history.id` = `BIGINT UNSIGNED`.
  - Index sur `produit` : `ft_produit_recherche (nom, description)` (`FULLTEXT`), `idx_produit_categorie (id_categorie)`, `idx_produit_visible (visible, stock)`.
  - Triggers prix TTC : `trg_produit_ttc` (`BEFORE INSERT`) et `trg_produit_ttc_update` (`BEFORE UPDATE`).
- **Écarts dans `.puml` (après génération) :**
  - **Ajoute `'RETOURNEE'` dans `commande.statut`**, valeur absente de SQL et de PHP.
  - **Omet 3 tables complètes :** `administrateur`, `parametre`, `stock_movement_history`.
  - Tailles/types inexacts (`reference : VARCHAR(40)` au lieu de `30`, `slug : VARCHAR(160)` au lieu de `180`, `order_status_history.id : BIGINT` au lieu de `BIGINT UNSIGNED`) et nombreuses colonnes omises (`client`, `produit`, `commande`, `order_status_history`).
  - Note sur `produit` fausse (`idx_catalogue`, `idx_recherche(nom)` inexistants).
- **Écarts dans `.png` actuel :**
  - Omet `parametre` et `stock_movement_history` (7 tables sur 9).
  - Trace une liaison directe `ADMINISTRATEUR 1 -- 0..* COMMANDE (SUIVRE)` qui laisserait croire à une FK `id_admin` inexistante dans `commande`.

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
- **Écarts dans `.puml` (régression critique si régénéré) :**
  - Inverse le sens réel : **`PAYEE --> EN_PREPARATION`** est écrit dans le `.puml`, alors que cette transition est **interdite** (`EN_PREPARATION --> PAYEE` seule est autorisée).
  - Omet **`BROUILLON --> EN_PREPARATION`** et **`EXPEDIEE --> ANNULEE`**.
  - Invente un 7e état **`RETOURNEE`** (`EXPEDIEE --> RETOURNEE`, `LIVREE --> RETOURNEE`, `RETOURNEE --> [*]`) absent du code et de la base.
- **État du `.png` actuel :**
  - **Fidèle à 100 % à la matrice réelle (`Triggers::TRANSITIONS` et `trg_commande_transition_statut`)** : 6 statuts, toutes les flèches exactes et note conforme. Il ne faut surtout **pas** régénérer ce PNG tant que `etats_commande.puml` n’est pas corrigé.

---

### 3.6 `cas_utilisation_global` (`cas_utilisation_global.puml` vs `cas_utilisation_global.png`)

- **Réalité du code (`public/index.php`) :**
  - Les routes client `/compte`, `/compte/mot-de-passe`, `/commande/valider`, `/mes-commandes*` portent la garde `'client'`.
  - Les routes back-office `/admin`, `/admin/produits*`, `/admin/categories*`, `/admin/stocks`, `/admin/commandes*`, **`/admin/parametres`** portent la garde `'admin'` (`GESTIONNAIRE` et `SUPER`).
  - Seule `/admin/equipe*` porte la garde `'super'`.
  - Les sessions client et admin sont strictement séparées (`RB-13`, `Auth`).
- **Écarts dans `.puml` (après génération) :**
  - Associe `Visiteur` directement à `UC-05 Se connecter / gérer son compte` (un visiteur peut se connecter, mais pas gérer son compte sans être `Client`).
  - Modélise `Gérer les comptes d'équipe` en `<<extend>>` de `UC-05` (faux : relève de l’administration `SUPER`, pas du compte client).
  - Omet la gestion des paramètres commerciaux (`/admin/parametres`, EF-ADM-11).
- **Écarts dans `.png` actuel :**
  - Annonce `14 cas` (omet paramètres et équipe), dessine une généralisation `Administrateur --|> Visiteur` contraire à `RB-13`, et présente des flèches pleines entre cas (`UC-01 -> UC-06`, `UC-02 -> UC-07`, etc.) qui prêtent à confusion.

---

### 3.7 `cas_utilisation_detail` (`cas_utilisation_detail.puml` vs `cas_utilisation_detail.png`)

- **Écarts dans `.puml` (après génération) :**
  - `Modifier une quantité` (`U7`) et `Retirer du panier` (`U8`) sont aussi accessibles au `Visiteur` sans connexion (garde `'public'` dans `public/index.php`), pas seulement via `<<extend>>` ou `Client`.
  - `U6 ..> U7 : <<extend>>` et `U6 --> U7` coexistent de façon redondante.
  - Omet `Contacter la boutique` (`/contact`) et `Préférences langue/fuseau` (`/preferences`).
- **Écarts dans `.png` actuel :**
  - **Complètement hors sujet par rapport au `.puml` et à la légende du CDC §5 :** le `.png` dessine la **gestion du catalogue back-office** par `Administrateur` (avec mention `formulaire JS` pour `RB-02/03/07`), et non les cas détaillés visiteur/client.

---

### 3.8 `cas_utilisation_commandes` (`cas_utilisation_commandes.puml` vs `cas_utilisation_commandes.png`)

- **Écarts dans `.puml` (après génération) :**
  - Le fichier s’appelle `cas_utilisation_commandes.puml` mais décrit tout le back-office (produits, catégories, stocks, commandes, paramètres, équipe).
  - Relie `Gérer les paramètres` (`PA`) uniquement à `Super administrateur`, alors que `/admin/parametres` porte la garde `'admin'` (`Auth::exigeAdmin()`, accessible aussi aux gestionnaires).
- **Écarts dans `.png` actuel :**
  - Ne couvre que les commandes (admin + client), mais avec plusieurs relations `<<include>>` / `<<extend>>` inversées (`Confirmer la commande ..> Valider le panier : <<include>>`, `Retirer un produit ..> Consulter le panier : <<extend>>`).

---

### 3.9 `activite_passer_commande` (`activite_passer_commande.puml` vs `activite_passer_commande.png`)

- **Écarts dans `.puml` (après génération) :**
  - Indique que le panier affiche `sous-total HT, TVA, port, total TTC` : `app/View/panier/index.php` affiche uniquement `Prix unitaire TTC`, `Sous-total (marchandises TTC)`, `Frais de port` et `Total TTC` (pas de détail HT/TVA sur la page panier).
  - Indique uniquement `Statut PAYEE + historique` alors que `EN_PREPARATION` est appliqué si `paiement_simule` est décoché (`app/View/commande/valider.php`).
  - Place `Vider le panier` après `Afficher numéro et récapitulatif`, alors que `CommandeController::valider()` appelle `$panier->vider()` puis redirige (`302 /mes-commandes/{id}`).
- **Écarts dans `.png` actuel :**
  - Montre deux `UPDATE` successifs (`EN_PREPARATION` puis `PAYEE`), alors que `sp_create_order_from_basket` fait un **unique** `UPDATE ... SET statut = IF(IFNULL(p_payee, 0) = 1, 'PAYEE', 'EN_PREPARATION')`.
  - Omet `+ c.frais_port` dans la boîte `montant_total = SUM(total_ligne)`.
  - Décrit `SIGNAL '45000' STOCK_INSUFFISANT` propagé par exception SQL alors que `sp_create_order_from_basket` capture le code `v_code = 'STOCK_INSUFFISANT'` retourné par `sp_add_order_line`, exécute `ROLLBACK` et renvoie `OUT p_code_retour = 'STOCK_INSUFFISANT'`.

---

### 3.10 `seq_authentification` (`seq_authentification.puml` vs `seq_authentification.png`)

- **Écarts dans `.puml` (après génération) :**
  - Noms réels : `ClientRepository::getCredentials($email)` (pas `rechercherParEmail`), `Auth::connecterClient($id)` (pas `connecter`), colonne `derniere_connexion` (pas `date_derniere_connexion`).
  - Omet la branche `COMPTE_BLOQUE` (qui affiche un message distinct : `Ce compte est bloqué. Contactez le support.`) et la redirection vers `$_SESSION['cible_apres_connexion']` lorsqu’elle est définie.
  - En cas d’échec, `AuthController::connexion()` rend `200` la vue `auth/connexion`, et ne renvoie pas `302 /connexion`.
- **Écarts dans `.png` actuel :**
  - Fait référence à des composants et comportements absents du code : `login.php`, `AuthMiddleware`, `findByEmail($email)`, `TokenCSRFInvalide`, codes `419`/`401`/`403`, limitation `5 échecs / 15 min => temporisation` (non implémentée), `$_SESSION['user_id']`/`$_SESSION['role']` (réel : `$_SESSION['client_id']`), `vue/login.php`.

---

### 3.11 `seq_consulter_catalogue` (`seq_consulter_catalogue.puml` vs `seq_consulter_catalogue.png`)

- **Écarts dans `.puml` (après génération) :**
  - Fidèle au flux de `CatalogueController::catalogue()`, mais omet l’appel à `CategorieRepository::listerAvecCompteur()` et l’exécution de `sp_search_products` côté SQL (`StoredProcedureResultExecutor`).
- **Écarts dans `.png` actuel :**
  - **Diagramme totalement différent (`Séquence 5 (bonus) — Consulter la fiche produit (UC-03)`)** : cite `ProduitController` et `parSlug($slug)` qui n’existent pas (`CatalogueController::produit($slug)` appelle `ProduitRepository::findVisibleBySlug($slug)` et `Views::etatStock($produit)`).

---

### 3.12 `seq_recherche_produit` (`seq_recherche_produit.puml` vs `seq_recherche_produit.png`)

- **Écarts dans `.puml` (après génération) :**
  - Le compteur retourné par `ProduitRepository::searchProducts()` s’appelle `total` (pas `total_trouves` — `total_trouves` est la colonne du 2e jeu de résultat SQL de `sp_search_products`).
  - En mode SQL, la recherche passe par `sp_search_products` (avec `FULLTEXT` / `LIKE` / normalisation selon le cas), et la vue sans résultat reste `catalogue/index.php` avec les catégories déjà chargées.
- **Écarts dans `.png` actuel :**
  - Invente `SearchRequest`, `SearchQuery`, `CatalogueController::liste()`, `ProduitRepository::search(query)` (inexistants), une pagination bornée à `60` (`par_page` est fixé à `12` dans `CatalogueController`) et `vue/catalogue.php` (`app/View/catalogue/index.php`).

---

### 3.13 `seq_ajout_panier` (`seq_ajout_panier.puml` vs `seq_ajout_panier.png`)

- **Réalité du code (`public/js/panier.js`, `app/Controller/ApiController.php`, `app/Model/PanierSession.php`) :**
  - `panier.js` intercepte `form[data-panier]` et envoie `POST /api/panier/ajouter` en **`application/x-www-form-urlencoded`** (`URLSearchParams(new FormData(formulaire))`), pas en JSON.
  - `ApiController::ajouter()` appelle `exigerPoste()` (`POST` + `Csrf::estValide()`, sinon `403 {ok:false, code:'CSRF_INVALIDE', message}`) puis appelle directement `PanierSession::ajouter($idProduit, $quantite)` — **sans passer par `ProduitRepository::disponible()`**.
  - `PanierSession::ajouter()` plafonne silencieusement à `(int) $produit['stock']` via `min($demande, $stock)` si le produit est visible, et ne lève `BusinessError` que pour quantité `<= 0` ou produit introuvable/masqué (`409 {ok:false, code, message, nombre_articles}`).
  - En succès (`200`), le JSON renvoyé est `{ok: true, message, nombre_articles, sous_total, frais_port, total_ttc, lien_panier}`.
  - En erreur (`!ok`), `panier.js` annonce le message dans `#zone-annonces` et désactive le bouton (`disabled = true`, `aria-disabled="true"`).
- **Écarts dans `.puml` :** montre un envoi JSON, un appel inexistant à `ProduitRepository::disponible()`, une réponse `409 {code: STOCK_INSUFFISANT, reste: N}` inexistante (`reste` absent, et dépassement plafonné plutôt que `409`), et une réponse `200` incomplète.
- **Écarts dans `.png` actuel :** cible `panier/ajouter.php` + `PanierController::ajouter` sur `POST /panier/ajouter` au lieu d’`ApiController` + `POST /api/panier/ajouter`, invente `404 {"erreur":"PRODUIT_INTROUVABLE"}`, `{"erreur":"STOCK_INSUFFISANT","reste":1}` et `200 {lignes, total, stockRestant}`.

---

### 3.14 `seq_passer_commande` (`seq_passer_commande.puml` vs `seq_passer_commande.png`)

- **Réalité du code (`app/Controller/CommandeController.php`, `app/Repository/CommandeRepository.php`, `sql/02_minishop_procedures.sql`) :**
  - `CommandeController::valider()` appelle `verifierCsrf()`, `Auth::exigeClient()`, `Database::store()->setActor(...)`, `CommandeRepository::createOrderFromBasket($idClient, $adresse, $panier->pourCommande(), $payee)`, puis `$panier->vider()` et redirige en `302 /mes-commandes/{id}` (ou `302 /panier` en cas de `BusinessError`).
  - En mode SQL (`$this->store instanceof StoredProcedureExecutor`), `CommandeRepository::createOrderFromBasket()` appelle `CALL sp_create_order_from_basket(...)` **sans ouvrir de transaction PDO côté PHP** (`BEGIN`/`COMMIT` sont gérés exclusivement à l’intérieur de la procédure stockée SQL, comme documenté dans `ARCHITECTURE.md` §3).
- **Écarts dans `.puml` :**
  - Montre `CR -> DB : BEGIN` / `COMMIT` / `ROLLBACK` pilotés depuis `CommandeRepository` (faux en mode SQL : c’est `sp_create_order_from_basket` qui ouvre et clôt la transaction).
  - Utilise des noms inexacts (`exigerClient()`, `confirmerDepuisPanier()`), omet `p_payee` et `$panier->vider()`.
- **Écarts dans `.png` actuel :**
  - Cumule les mêmes erreurs transactionnelles (`$pdo->beginTransaction()` + `$pdo->commit()` en plus du `START TRANSACTION` SQL), nom inexistant (`creerDepuisPanier`), codes HTTP faux (`409` / `200` au lieu de `302`), `session_unset('panier')`, `erreur 1644` directe et omission de `+ frais_port` dans `montant_total`.

---

## 4. Plan de mise en conformité recommandé

Avant de relancer `bash docs/diagrams/render.sh` (qui réécrira les 14 PNG dans `docs/diagrams/`) :

1. **Corriger en priorité les 5 diagrammes critiques dans `docs/diagrams/src/` :**
   - `etats_commande.puml` : supprimer `RETOURNEE`, corriger `EN_PREPARATION --> PAYEE`, ajouter `BROUILLON --> EN_PREPARATION` et `EXPEDIEE --> ANNULEE` (s’aligner sur `Triggers::TRANSITIONS`).
   - `mld_minishop.puml` : supprimer `RETOURNEE`, ajouter `administrateur`, `parametre`, `stock_movement_history`, corriger `VARCHAR(30)` / `VARCHAR(180)` / `BIGINT UNSIGNED` et les vrais noms d’index/triggers.
   - `mcd_minishop.puml` : passer à **9 entités** (ajouter `STOCK_MOVEMENT_HISTORY`), renommer `description` → `comment` sur `PARAMETRE`, compléter les attributs.
   - `diagramme_classes.puml` : remplacer `AdminController` par les 7 contrôleurs `Admin\*`, retirer les 6 entités PHP inexistantes (ou typer explicitement les tableaux/données), ajouter `PanierSession` dans son package et corriger `JsonStore --> Views`.
   - `seq_passer_commande.puml` : retirer `BEGIN`/`COMMIT`/`ROLLBACK` côté PHP pour le mode SQL (transaction interne à `sp_create_order_from_basket`), corriger les noms (`Auth::exigeClient()`, `createOrderFromBasket()`, `$panier->vider()`).
2. **Aligner les 9 autres `.puml`** (`architecture_mvc`, les 3 `cas_utilisation_*`, `activite_passer_commande`, `seq_authentification`, `seq_consulter_catalogue`, `seq_recherche_produit`, `seq_ajout_panier`) avec les méthodes, gardes et contrats réels relevés au §3.
3. **Régénérer tous les PNG via `bash docs/diagrams/render.sh`** (sur la VM ou un environnement avec Java + PlantUML + Graphviz), vérifier visuellement les 14 PNG produits (y compris `cas_utilisation_detail.png` et `seq_consulter_catalogue.png` aujourd’hui hors sujet) et les committer ensemble avec `docs/diagrams/src/*.puml`.
