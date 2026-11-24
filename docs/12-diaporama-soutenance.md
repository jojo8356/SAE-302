# MiniShop — soutenance SAÉ 302
- **Code source PHP strict** sans dépendance runtime
- **Mode de données :** `SqlStore` par défaut, `JsonStore` en option
- **Jeu de démonstration Complet**
- **Gouvernance documentaire**
<!-- row-images: docs/img/logos/php.png docs/img/logos/database.png public/img/illustration-achat.jpg docs/img/logos/folder.png -->

---

# Le besoin et les acteurs
- Catalogue matériel informatique, panier persistant en session, commande suivie et back-office
- **14 cas d'utilisation**, **3 acteurs**
- Contraintes respectées : **PHP 8.2+ POO**, **MySQL/MariaDB via PDO**
- Traçabilité native
![Cas d'utilisation global](docs/diagrams/cas_utilisation_global.png)
<!-- logos: docs/img/logos/php.png docs/img/logos/mariadb.png docs/img/logos/mysql.png -->
> Objectif : une application maintenable, testable et défendable devant le jury, pas une maquette jetable.

---

# Périmètre livré dans le code
- **Front-office** · accueil, catalogue intelligent, fiche produit, catégories, préférences langue et fuseau, contact et pages légales
- **Espace client** · CRUD utilisateur, panier et API interne, commande, historique et annulation en 2 étapes
- **Back-office** · tableau de bord, gestion produits + administrative, équipe
- **Transverse et CLI** : pages erreurs, journaux locaux, rétention RGPD et rotation des sauvegardes
<!-- layout: grid-2x2 -->
<!-- row-images: docs/img/logos/frontoffice.png docs/img/logos/espace_client.png docs/img/logos/backoffice.png docs/img/logos/cli_ops.png -->

---

# Architecture logicielle
- **`public/` seul dossier exposé** : `index.php`, `.htaccess`, `css/`, `js/`, `img/`, `fonts/`
- **`app/bootstrap.php`** : autoloader PSR-4 maison, fuseau, `0` dépendance Composer en prod
- **`app/Router.php`** : `42` routes GET et `25` routes POST
- **Chaîne MVC** : contrôleurs → classes Repository → `Database::store()` vers `SqlStore` ou `JsonStore`
<!-- layout: tree-arch -->

---

# Modèle de données et SQL
- **Tables 3FN** : `administrateur`, `client`, `categorie`, `produit`, `stock_movement_history`, `parametre`, `commande`, `ligne_commande`, `order_status_history`
- **Vues** `v_etat_stock`, `v_catalogue`, `v_commandes_client` et `1` fonction `fn_param`
- **Procédures** appelées par les repositories : commandes, ajustement de stock, recherche, port et CA
- **Déclencheurs** : prix TTC, snapshot de ligne, stock, matrice de statuts, audit et blocage de suppression
<!-- layout: sql-erd -->

---

# Logique métier et parité SQL / JSON
- **Panier en session** : seuls `id_produit` et `quantite` sont stockés ; prix, TVA, port et stock sont recalculés côté serveur
- **Anti-survente** : verrous `SELECT … FOR UPDATE` dans les procédures de création et d'ajout de ligne
- **Cycle de commande** : `BROUILLON` → `EN_PREPARATION` → `PAYEE` → `EXPEDIEE` ; annulation en 2 étapes avec retour stock avant expédition uniquement
- **Parité JSON** : `Schema`, `Triggers` et `Views` valident `T-01…T-29` à `29/29` en SQL comme en JSON
![États d'une commande](docs/diagrams/etats_commande.png)

---

# Sécurité applicative
- **Sessions et RBAC** : `bcrypt` via `password_hash`, espaces `client_id` / `admin_id` isolés, régénération d'ID et cookie `MINISHOPSESS` durci
- **CSRF** : jeton CSPRNG `random_bytes` vérifié en temps constant via `hash_equals` sur formulaires POST et API panier
- **XSS et en-têtes** : échappement `e()`, CSP stricte sans script ni style inline, `X-Frame-Options: DENY` et `nosniff`
- **SQL et IDOR** : requêtes préparées PDO sans émulation, identifiants filtrés par `Schema` et contrôle d'appartenance `id_client`
- **Audit statique** : `11/11` contrôles statiques dans `tests/security/controles.sh`, rejoués le 17/11/2026
<!-- layout: bento-sec -->

---

# Tests automatisés et performances
- **60/60 étapes vertes** : campagne globale sans warning PHP via `generer_pv.php`, incluant `43` suites PHP, `11` Python et `57/57` PHPUnit
- **29/29 SQL & JSON** : intégration SQL, HTTP SQL E2E `105/105`, concurrence HTTP `20` clients / `12` unités `8/8` et sécurité `11/11`
- **338/338 E2E WASM** : JSON isolé, traduction FR↔EN sur `48` états et responsive Playwright de `320 px` à `1536 px`
- **6 ms HTTP p95** : benchmark ENF-01 sur base `minishop_perf` de `236` produits / `1 004` commandes, SQL p95 ≤ **20,6 ms**, `0` erreur
<!-- layout: kpi-4 -->

---

# Design system et accessibilité
- **Design system** : jetons CSS dans `app.css`, palette contrastée AA/AAA, échelle fluide en `rem`, grille `8 px` et feuille d'impression
- **Accessibilité WCAG 2.1 AA** : lien d'évitement, `aria-current="page"`, `aria-live` sur le panier, alternatives d'image typées et cibles ≥ `44 px`
- **Responsive vérifié** : de **320 px à 1536 px** via contrats CSS, responsive et Playwright, tableaux défilants documentés
- **JS natif optionnel** : panier AJAX, traduction complète FR↔EN, fuseau horaire `localStorage` et géolocalisation sans tiers
<!-- layout: pillars-4 -->

---

# Démonstration en direct
- **Front-office et client** : recherche `/catalogue` → fiche produit → panier AJAX → validation commande → annulation en 2 étapes
- **Back-office gestionnaire** : changement de statut des commandes et ajustement tracé des stocks
- **Back-office super-admin** : création produit, paramètres boutique et gestion de l'équipe
- **Preuve SQL directe** : `v_etat_stock`, `stock_movement_history` et `order_status_history`
<!-- layout: steps -->
![Séquence passer commande](docs/diagrams/seq_passer_commande.png)

---

# Difficultés rencontrées et arbitrages
- **Parité SQL / JSON** : double implémentation unifiée derrière `StoreInterface` et vérifiée à `29/29`
- **Incidents VM résolus dans le code** : collation `utf8mb4_unicode_ci`, stockage **UTC**, requêtes non bufferisées, garde `--load` et pool de sessions WASM
- **Arbitrages assumés** : paiement et expédition simulés, contact journalisé localement sans SMTP et installation CLI dédiée
- **Cible LineServ non testée** : prérequis bloquants documentés pour `RewriteBase /`, séparation hors `www/` et droits SQL/PHP
<!-- layout: compare-2 -->

---

# Bilan, documentation et suites
- **Produit validé en VM** : `60/60` étapes automatisées, HTTP p95 = **6 ms**, pilotage WBS de `82` tâches / `348 h` soldé sans tâche ouverte
- **Documentation vivante** : `113` fichiers PHP et `394` méthodes PHPDoc, `8` modules Python avec doctests, `6` ADR MADR, `4` C4 et fiche service
- **Limites maintenues** : `200` scénarios manuels `NON JOUÉ`, aucune recette ni déploiement LineServ attesté
- **Suites hors périmètre** : adaptateur de préfixe URL pour sous-dossier, paiement signé, envoi SMTP réel et MFA `SUPER`
<!-- layout: pillars-3 -->
