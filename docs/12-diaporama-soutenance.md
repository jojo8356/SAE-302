# MiniShop — soutenance SAÉ 302
## Application e-commerce PHP 8.2+ sans framework · 12 diapositives · cible 15 min + questions
- **Code source PHP 8.2+ strict** sans dépendance runtime : `112` fichiers `app/` et `3` sous `public/`
- **Double persistance** derrière `StoreInterface` : **`SqlStore`** par défaut via PDO / MySQL / MariaDB, **`JsonStore`** en option
- **Jeu de démonstration** : `4` catégories, `36` produits, `3` clients, `1` admin `SUPER`, `4` commandes SQL et `4` paramètres
- **Gouvernance documentaire** : cahier des charges, `6` ADR MADR, `4` niveaux C4, exploitation et `catalog-info.yaml`
> Ce support reflète le code présent dans le dépôt ; il ne vaut ni procès-verbal de validation pédagogique ni attestation LineServ.

---

# Le besoin et les acteurs
## Remplacer le suivi manuel par une boutique fiable
- Catalogue matériel informatique, panier persistant en session, commande suivie et back-office
- **14 cas d'utilisation**, **3 acteurs**
- Contraintes respectées : **PHP 8.2+ POO**, **MySQL/MariaDB via PDO**
- Traçabilité native
![Cas d'utilisation global](docs/diagrams/cas_utilisation_global.png)
<!-- logos: docs/img/logos/php.png docs/img/logos/mariadb.png docs/img/logos/mysql.png -->
> Objectif : une application maintenable, testable et défendable devant le jury, pas une maquette jetable.

---

# Périmètre livré dans le code
## 67 routes explicites · 40 gabarits de vue
- **Front-office · 35 routes publiques** : accueil, catalogue filtrable et trié, fiche produit, recherche, catégories, préférences FR/EN et fuseau, contact et 7 pages légales
- **Espace client · 9 routes** : inscription, profil, mot de passe, panier HTML et API interne, commande, historique et annulation en 2 étapes
- **Back-office · 20 routes admin et 3 super** : tableau de bord, produits, catégories, stocks, commandes, paramètres et équipe
- **Transverse et CLI** : pages 403/404/500, journaux locaux, rétention RGPD 24 mois et rotation des sauvegardes
> Point d'entrée HTTP unique public/index.php ; aucun écran admin « clients » distinct, gestion d'équipe réservée à SUPER.

---

# Architecture logicielle
## MVC pragmatique, front controller, routeur à liste blanche
- **`public/` seul dossier exposé** : `index.php`, `.htaccess`, `css/`, `js/`, `img/`, `fonts/`
- **`app/bootstrap.php`** : autoloader PSR-4 maison, fuseau **UTC**, zéro dépendance Composer en production
- **`app/Router.php`** : `42` routes GET et `25` routes POST avec gardes `public`, `client`, `admin`, `super`
- **Chaîne MVC** : `15` contrôleurs → `9` classes Repository → `Database::store()` vers `SqlStore` ou `JsonStore`
![Architecture MVC](docs/diagrams/architecture_mvc.png)
> Les contrôleurs et repositories ne dépendent que de StoreInterface et des capacités SQL dédiées.

---

# Modèle de données et SQL
## 9 tables, 3 vues, 18 procédures stockées, 1 fonction et 16 déclencheurs
- **9 tables 3FN** : `administrateur`, `client`, `categorie`, `produit`, `stock_movement_history`, `parametre`, `commande`, `ligne_commande`, `order_status_history`
- **3 vues** `v_etat_stock`, `v_catalogue`, `v_commandes_client` et **1 fonction** `fn_param`
- **18 procédures** appelées par les repositories : commandes, ajustement de stock, recherche, port et CA
- **16 déclencheurs** : prix TTC, snapshot de ligne, stock, matrice de statuts, audit et blocage de suppression
![Modèle logique de données](docs/diagrams/mld_minishop.png)
> Chaîne PDO/MariaDB 11.8.6 validée sur VM Debian 13 le 17/11/2026 : 29/29 SQL et 105/105 HTTP SQL.

---

# Logique métier et parité SQL / JSON
## Règles appliquées côté serveur
- **Panier en session** : seuls `id_produit` et `quantite` sont stockés ; prix, TVA, port et stock sont recalculés côté serveur
- **Anti-survente** : verrous `SELECT … FOR UPDATE` dans les procédures de création et d'ajout de ligne
- **Cycle de commande** : `BROUILLON` → `EN_PREPARATION` → `PAYEE` → `EXPEDIEE` ; annulation en 2 étapes avec retour stock avant expédition uniquement
- **Parité JSON** : `Schema`, `Triggers` et `Views` valident `T-01…T-29` à `29/29` en SQL comme en JSON
![États d'une commande](docs/diagrams/etats_commande.png)
> Les 200 scénarios manuels restent distinctement NON JOUÉ dans le PV du 17/11/2026.

---

# Sécurité applicative
## Défense en profondeur dans app/Security/ et public/index.php
- **Sessions et RBAC** : `bcrypt` via `password_hash`, espaces `client_id` / `admin_id` isolés, régénération d'ID et cookie `MINISHOPSESS` durci
- **CSRF** : jeton CSPRNG `random_bytes` vérifié en temps constant via `hash_equals` sur formulaires POST et API panier
- **XSS et en-têtes** : échappement `e()`, CSP stricte sans script ni style inline, `X-Frame-Options: DENY` et `nosniff`
- **SQL et IDOR** : requêtes préparées PDO sans émulation, identifiants filtrés par `Schema` et contrôle d'appartenance `id_client`
- **Audit statique** : `11/11` contrôles statiques dans `tests/security/controles.sh`, rejoués le 17/11/2026
> Ces contrôles statiques et unitaires sont distincts des 10 scénarios offensifs manuels S-01…S-10 et de LineServ.

---

# Tests automatisés et performances
## Campagne VM Debian 13 · 17/11/2026 · PHP 8.4.27 · MariaDB 11.8.6
- **Campagne globale** : **60/60 étapes vertes** sans warning PHP via `generer_pv.php`, incluant `43` suites PHP, `11` Python et `57/57` PHPUnit
- **SQL et concurrence** : intégration SQL **29/29**, HTTP SQL E2E **105/105**, concurrence HTTP `20` clients / `12` unités **8/8**, sécurité **11/11**
- **JS et navigateur** : E2E WASM JSON isolé **338/338**, traduction FR↔EN sur `48` états, responsive Playwright de `320 px` à `1536 px`
- **Performances ENF-01** sur base `minishop_perf` de `236` produits / `1 004` commandes : SQL p95 ≤ **20,6 ms**, HTTP p95 = **6 ms**, `0` erreur
> Tests exécutés sur VM locale isolée ; aucune recette manuelle LineServ, déploiement ou restauration réelle n'est attestée.

---

# Design system et accessibilité
## CSS natif sans framework · JavaScript progressif
- **Design system** : jetons CSS dans `app.css`, palette contrastée AA/AAA, échelle fluide en `rem`, grille `8 px` et feuille d'impression
- **Accessibilité WCAG 2.1 AA** : lien d'évitement, `aria-current="page"`, `aria-live` sur le panier, alternatives d'image typées et cibles ≥ `44 px`
- **Responsive vérifié** : de **320 px à 1536 px** via contrats CSS, responsive et Playwright, tableaux défilants documentés
- **JS natif optionnel** : panier AJAX, traduction complète FR↔EN, fuseau horaire `localStorage` et géolocalisation sans tiers
> ENF-07 respecté : tous les parcours critiques fonctionnent sans JavaScript.

---

# Démonstration en direct
## Environnement local isolé · http://localhost:8000/
- **Front-office et client** : recherche `/catalogue` → fiche produit → panier AJAX → validation commande → annulation en 2 étapes
- **Back-office gestionnaire** : changement de statut des commandes et ajustement tracé des stocks
- **Back-office super-admin** : création produit, paramètres boutique et gestion de l'équipe
- **Preuve SQL directe** : `v_etat_stock`, `stock_movement_history` et `order_status_history`
![Séquence passer commande](docs/diagrams/seq_passer_commande.png)
> Aucun mot de passe n'est affiché sur ce support ; les accès de démo locale restent séparés de toute cible LineServ.

---

# Difficultés rencontrées et arbitrages
## Problèmes réels résolus dans le code et choix assumés
- **Parité SQL / JSON** : double implémentation unifiée derrière `StoreInterface` et vérifiée à `29/29`
- **Incidents VM résolus dans le code** : collation `utf8mb4_unicode_ci`, stockage **UTC**, requêtes non bufferisées, garde `--load` et pool de sessions WASM
- **Arbitrages assumés** : paiement et expédition simulés, contact journalisé localement sans SMTP et installation CLI dédiée
- **Cible LineServ non testée** : prérequis bloquants documentés pour `RewriteBase /`, séparation hors `www/` et droits SQL/PHP
> Chaque limite technique est explicitement tracée dans ARCHITECTURE.md, docs/adr/ et docs/14-guide-lineserv.md.

---

# Bilan, documentation et suites
## État vérifié du dépôt et perspectives d'évolution
- **Produit validé en VM** : `60/60` étapes automatisées, HTTP p95 = **6 ms**, pilotage WBS de `82` tâches / `348 h` soldé sans tâche ouverte
- **Documentation vivante** : `113` fichiers PHP et `394` méthodes PHPDoc, `8` modules Python avec doctests, `6` ADR MADR, `4` C4 et fiche service
- **Limites maintenues** : `200` scénarios manuels `NON JOUÉ`, aucune recette ni déploiement LineServ attesté
- **Suites hors périmètre** : adaptateur de préfixe URL pour sous-dossier, paiement signé, envoi SMTP réel et MFA `SUPER`
> Merci pour votre attention — place aux questions et à la démonstration.
