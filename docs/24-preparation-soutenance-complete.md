# Préparation complète de la soutenance — MiniShop (SAÉ 302)

> Document de préparation opérationnelle pour la soutenance de **15 minutes + questions** devant le jury (Université Côte d'Azur — BUT2 Informatique).  
> Ce document complète [`03-support-soutenance.md`](03-support-soutenance.md) et [`12-diaporama-soutenance.md`](12-diaporama-soutenance.md) avec : le barème officiel, le déroulé minuté, le script pas-à-pas de la démo en direct, les chiffres exacts vérifiés dans le dépôt, les justifications des choix face au sujet officiel et 20 questions-réponses du jury.

---

## 1. Format officiel, attentes du jury et points de vigilance

### 1.1 Structure de l’évaluation (sujet officiel SAÉ 302)

| Bloc | Attentes principales du jury | Preuves à montrer |
|---|---|---|
| **i. Cahier des charges & conception** | Problème initial, acteurs (`Visiteur`, `Client`, `Administrateur`), 14 UC minimum, 11 règles métier (`RB-01`…`RB-11` + extensions), sécurité (`SEC-01`…`SEC-14`), architecture MVC | `docs/01-cahier-des-charges-MiniShop.md`, diagrammes UML (`docs/diagrams/`) |
| **ii. Modélisation BD & SQL** | MCD (cardinalités justifiées), MLD en 3FN (dénormalisations contrôlées justifiées), scripts reproductibles, **≥ 5 procédures stockées** et **≥ 5 triggers**, requêtes d’analyse | `docs/04-conception-bd-et-sql.md`, `sql/01`…`04`, terminal `mysql` |
| **iii. Application Web & sécurité** | MVC PHP natif (`declare(strict_types=1)`), PDO préparé (`ATTR_EMULATE_PREPARES => false`), `password_hash`/`password_verify`, protection CSRF, contrôle IDOR, séparation front/back-office, accessibilité/responsive | Code `app/`, démo navigateur en direct, `tests/security/controles.sh` (`11/11`) |
| **iv. Tests, GitLab & soutenance (15 min)** | Stratégie & exécution des tests (unitaires, intégration SQL, E2E, concurrence, perf), dépôt **GitLab IUT** propre (commits des deux membres, README complet, accès `Reporter` de l’enseignant), clarté de la démo et réponses aux questions | GitLab IUT (`git shortlog -sne`), `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt` |

### 1.2 Points de vigilance critiques (pénalités du sujet à éviter absolument)

1. **Dépôt GitLab IUT à jour :** fusionner les dernières branches/corrections sur le dépôt GitLab IUT avant la soutenance, vérifier que `NGUYEN Thanh-Phuong` est membre `Reporter` et que le `README.md` contient présentation, installation, technologies et répartition des tâches (**Johan Polsinelli** / **Chahd Gouiaa**).
2. **Ne jamais réutiliser de mots de passe en clair, secrets ou `.env` dans Git.**
3. **Ne jamais présenter une donnée simulée ou un document non exécuté comme un test réel :** distinguer clairement les tests automatisés exécutés (`11/11` sécurité, `29/29` intégration SQL, `105/105` HTTP SQL E2E, `8/8` concurrence HTTP, `46/46` fiches UC) des campagnes manuelles notées `NON JOUÉ` (`docs/13-suivi-recette-uc.csv`).
4. **Démonstration fluide sans bidouillage en séance :** base chargée proprement à l’avance, deux fenêtres prêtes (front-office + back-office) et terminal SQL déjà ouvert.

---

## 2. Trame chronométrée de la présentation (15 minutes)

> L’équipe comporte deux étudiants (**Johan Polsinelli** et **Chahd Gouiaa**). Les rôles `Binôme 1` / `Binôme 2` ci-dessous répartissent la parole de façon équilibrée autour des **11 diapositives actuelles** (`livrables/minishop-soutenance.pptx`), suivies de la démonstration / présentation du code au moment choisi avec le jury.

| Temps | Intervenant | Thème | Diapositive & support affiché | Messages clés à placer |
|---|---|---|---|---|
| **0:00 – 1:15** | Binôme 1 | Accueil, besoin & acteurs | **Diapos 1–2** (`cas_utilisation_global.png`, logos PHP/MariaDB/MySQL) | Socle PHP 8.2+ sans dépendance runtime, double persistance (`SqlStore` par défaut / `JsonStore` en option), **14 cas d’utilisation**, **3 acteurs**, PDO et traçabilité native. |
| **1:15 – 2:30** | Binôme 1 | Périmètre & architecture réelle | **Diapos 3–4** (grille 2×2 + schéma `tree` & MVC) | 4 blocs livrés (`Front-office`, `Espace client`, `Back-office`, `Transverse et CLI`) ; `public/` seul dossier exposé, `Router.php` (`42` GET / `25` POST), triade `Modèle ⇄ Contrôleur → Vue`, socle `Security`/`Service` et passerelle `Database::store()`. |
| **2:30 – 4:15** | Binôme 2 | Modélisation BD & SQL | **Diapo 5** (schéma relationnel 9 tables + couche SQL) | **9 tables 3FN** (`categorie → produit → ligne_commande ← commande ← client`, + `administrateur`, `stock_movement_history`, `order_status_history`, `parametre`), **3 vues** (`v_etat_stock`, `v_catalogue`, `v_commandes_client`), **1 fonction** `fn_param`, **18 procédures** et **16 triggers**. |
| **4:15 – 5:30** | Binôme 2 | Logique métier & parité SQL / JSON | **Diapo 6** (schéma cycle de vie + 3 piliers) | Cycle `BROUILLON → EN_PREPARATION → PAYEE → EXPEDIEE → LIVREE` + branche `ANNULEE` en 2 étapes avec retour stock avant expédition ; panier recalculé serveur ; verrous `SELECT … FOR UPDATE` ; parité SQL / JSON (`Schema`, `Triggers`, `Views`). |
| **5:30 – 6:45** | Binôme 1 | Sécurité applicative | **Diapo 7** (grille 2×2 + bannière `11/11`) | Données sensibles protégées par hachage `bcrypt` (jamais en clair), sessions client/admin isolées et cookie `MINISHOPSESS` durci, CSRF `hash_equals`, XSS (`e()`, CSP, `X-Frame-Options: DENY`, `nosniff`), PDO sans émulation + `id_client` anti-IDOR ; `11/11` contrôles statiques validés. |
| **6:45 – 8:30** | Binôme 2 | Tests, performances & accessibilité | **Diapos 8–9** (`docs/pv-recette.md`, `dernieres_mesures.txt`) | **60/60** étapes VM vertes (`generer_pv.php`), **29/29** SQL/JSON, **105/105** HTTP SQL E2E, **8/8** concurrence, **338/338** E2E WASM, `48` états FR↔EN, benchmark `minishop_perf` (`236` produits / `1 004` commandes : HTTP p95 = `6 ms`, SQL p95 ≤ `20,6 ms`, `0` erreur) ; design system `app.css`, WCAG 2.1 AA, responsive `320–1536 px`. |
| **8:30 – 11:00** | Binôme 1 | Difficultés, bilan & suites | **Diapos 10–11** + `git shortlog -sne` | Parité SQL/JSON et incidents VM résolus dans le code, arbitrages assumés et cible LineServ non testée ; WBS `82` tâches / `348 h` soldé, documentation vivante (`113` PHP / `394` PHPDoc, `8` Python, `6` ADR, `4` C4, fiche service), limites (`200` manuels `NON JOUÉ`) et suites hors périmètre. |
| **11:00 – 15:00** | Binôme 1 & 2 | **Démonstration & présentation du code (4 min)** | Navigateurs + terminal MySQL + éditeur | Parcours client (`/catalogue` → panier AJAX → commande → annulation 2 étapes), back-office gestionnaire & super-admin, preuve SQL directe (`v_etat_stock`, `stock_movement_history`, `order_status_history`) et présentation ciblée du code. |

---

## 2.1 Script oral diapositive par diapositive (phrases prêtes à dire)

> Script calibré sur les **11 diapositives** actuelles (`livrables/minishop-soutenance.pptx`). La présentation des diapositives tient en **~11 minutes**, ce qui laisse **4 minutes complètes** ensuite (ou au moment demandé par le jury) pour la démonstration en direct et la présentation du code.

### Diapositive 1 — *MiniShop — soutenance SAÉ 302* (0:00 – 0:30 · Binôme 1)
> « Bonjour à toutes et à tous. Nous vous présentons **MiniShop**, notre application e-commerce réalisée dans le cadre de la SAÉ 302.  
> Quatre repères structurent le projet : un **code source PHP strict** sans aucune dépendance runtime en production ; un **double mode de données** avec `SqlStore` par défaut et `JsonStore` en option portable ; un **jeu de démonstration complet** prêt à l’emploi ; et une **gouvernance documentaire** directement vérifiée contre le code. »

### Diapositive 2 — *Le besoin et les acteurs* (0:30 – 1:15 · Binôme 1)
> « Le besoin de départ consiste à remplacer un suivi manuel par une boutique en ligne fiable : catalogue de matériel informatique, panier persistant en session, suivi de commande et back-office.  
> Comme l’illustre le diagramme de cas d’utilisation à droite, le périmètre couvre **14 cas d’utilisation** pour **3 acteurs** : Visiteur, Client et Administrateur.  
> Nous respectons strictement les contraintes imposées — **PHP 8.2+ orienté objet** et **MySQL/MariaDB via PDO** — avec une **traçabilité native** des stocks et des statuts. L’objectif est de livrer une application maintenable, testable et défendable devant le jury, pas une maquette jetable. »

### Diapositive 3 — *Périmètre livré dans le code* (1:15 – 1:50 · Binôme 1)
> « Le périmètre livré se découpe en quatre blocs fonctionnels :
> - Le **Front-office** : accueil, catalogue intelligent filtrable et triable, fiche produit, catégories, préférences de langue et de fuseau horaire, contact et pages légales.
> - L’**Espace client** : CRUD utilisateur, panier HTML et API interne, passage de commande, historique et annulation en deux étapes.
> - Le **Back-office** : tableau de bord, gestion des produits et gestion administrative des commandes/stocks, ainsi que la gestion de l’équipe.
> - Le socle **Transverse et CLI** : pages d’erreurs, journaux locaux, rétention RGPD et rotation des sauvegardes. »

### Diapositive 4 — *Architecture logicielle* (1:50 – 2:30 · Binôme 1)
> « Pour expliquer l’architecture sans texte superflu, cette diapositive met en regard **l’arborescence réelle du dépôt** à gauche et le **flux d’exécution** à droite.  
> Seul le dossier **`public/`** est exposé au Web, avec `index.php` comme point d’entrée unique et les en-têtes de sécurité. Il passe la main à `app/Router.php`, qui gère **42 routes GET et 25 routes POST** avec contrôle d’accès.  
> Au cœur d’`app/`, on retrouve la triade MVC : le **Contrôleur** orchestre la requête entre le **Modèle** (`Repository`, `Model`) et la **Vue** (`app/View/`, gabarits HTML échappés), appuyés par la couche transverse (`Security`, `Service`, autoloader PSR-4 sans dépendance Composer).  
> Enfin, `Database::store()` abstrait l’accès aux données entre **`SqlStore`** sur `sql/` et **`JsonStore`** sur `data/minishop/`. »

### Diapositive 5 — *Modèle de données et SQL* (2:30 – 4:15 · Binôme 2)
> « Côté données, voici notre **schéma relationnel en troisième forme normale** avec les **9 tables** et leurs clés primaires et étrangères.  
> Sur la première ligne, la chaîne cœur e-commerce relie `categorie` à `produit` (`1:N`), puis `produit` et `commande` via `ligne_commande`, et enfin `commande` au `client`.  
> Sur la deuxième ligne figurent les tables d’exploitation et de traçabilité : `administrateur`, `stock_movement_history` qui conserve l’audit des mouvements de stock même si un article évolue, `order_status_history` rattachée aux commandes, et `parametre` qui externalise les règles chiffrées.  
> En bas, la **couche SQL active** regroupe nos **3 vues** (`v_etat_stock`, `v_catalogue`, `v_commandes_client`), la **fonction `fn_param`**, nos **procédures stockées** transactionnelles et nos **déclencheurs** qui figent notamment les prix TTC, les snapshots de ligne et l’historique automatique des statuts. »

### Diapositive 6 — *Logique métier et parité SQL / JSON* (4:15 – 5:30 · Binôme 2)
> « La logique métier repose sur un **cycle de vie de commande contrôlé côté serveur** : `BROUILLON` → `EN_PREPARATION` → `PAYEE` → `EXPEDIEE` → `LIVREE`, avec une branche **`ANNULEE`** en deux étapes qui restitue automatiquement le stock avant expédition et conserve la commande à montant nul pour l’audit.  
> Trois garde-fous complètent ce cycle :
> 1. **Le panier en session** ne conserve que l’identifiant produit et la quantité ; prix, TVA, port et stock sont toujours recalculés côté serveur.
> 2. **L’anti-survente** est garanti en SQL par les verrous transactionnels `SELECT … FOR UPDATE`.
> 3. **La parité SQL / JSON** assure que les mêmes règles et les mêmes tests passent via `Schema`, `Triggers` et `Views` sur les deux moteurs. »

### Diapositive 7 — *Sécurité applicative* (5:30 – 6:45 · Binôme 1)
> « La sécurité s’appuie sur quatre piliers défensifs :
> - **Sessions et RBAC** : les données sensibles sont protégées par hachage **`bcrypt`**, jamais stockées en clair ; les sessions client et administrateur sont isolées et le cookie `MINISHOPSESS` est durci.
> - **CSRF** : chaque action sensible vérifie un jeton en temps constant via `hash_equals`.
> - **XSS et en-têtes** : échappement systématique `e()`, CSP stricte sans script ni style inline, `X-Frame-Options: DENY` et `nosniff`.
> - **SQL et IDOR** : requêtes préparées PDO sans émulation, validation des identifiants par `Schema` et contrôle strict d’appartenance sur `id_client`.  
> L’ensemble est vérifié automatiquement par nos **11 contrôles statiques sur 11** dans `tests/security/controles.sh`, rejoués le 17 novembre 2026. »

### Diapositive 8 — *Tests automatisés et performances* (6:45 – 7:45 · Binôme 2)
> « La fiabilité est démontrée par quatre indicateurs clés mesurés en VM Debian 13 :
> - **60/60 étapes vertes** sans warning PHP via `generer_pv.php`, incluant 43 suites PHP, 11 Python et 57/57 PHPUnit.
> - **29/29 en intégration SQL et parité JSON**, complétés par **105/105 en E2E HTTP SQL**, **8/8 en concurrence** avec 20 clients simultanés sur 12 unités, et **11/11 en sécurité**.
> - **338/338 en E2E WASM** isolé, avec traduction FR↔EN vérifiée sur 48 états et responsive Playwright de 320 à 1536 px.
> - **6 ms de temps de réponse HTTP p95** lors du benchmark sur la base `minishop_perf` de 236 produits et 1 004 commandes, avec un p95 SQL inférieur ou égal à 20,6 ms et zéro erreur. »

### Diapositive 9 — *Design system et accessibilité* (7:45 – 8:30 · Binôme 2)
> « Côté interface, nous n’avons utilisé aucun framework CSS ou JS externe :
> - Un **design system** cohérent défini par jetons dans `app.css`, avec contraste AA/AAA et feuille d’impression dédiée.
> - Une **accessibilité WCAG 2.1 AA** : lien d’évitement, `aria-current="page"`, zones `aria-live` sur le panier et cibles tactiles d’au moins 44 px.
> - Un **responsive vérifié** de 320 px à 1536 px par tests Playwright.
> - Et un **JS natif optionnel** pour le panier AJAX, la traduction FR↔EN et la mémorisation locale du fuseau horaire. »

### Diapositive 10 — *Difficultés rencontrées et arbitrages* (8:30 – 9:45 · Binôme 1)
> « Ce tableau compare d’un côté les problèmes techniques résolus dans le code — la double implémentation SQL/JSON derrière `StoreInterface`, ainsi que les incidents détectés en VM comme la collation `utf8mb4_unicode_ci`, le stockage UTC ou les requêtes non bufferisées — et de l’autre nos arbitrages assumés : paiement et expédition simulés conformément au cahier des charges, contact journalisé localement sans SMTP, et documentation claire des prérequis bloquants de la cible **LineServ**, qui n’a pas été testée directement. »

### Diapositive 11 — *Bilan, documentation et suites* (9:45 – 11:00 · Binôme 1)
> « Pour conclure sur le support, trois acquis majeurs : un **produit validé en VM** à 60/60 étapes avec un pilotage WBS de 82 tâches et 348 heures soldé ; une **documentation vivante et contrôlée** couvrant 113 fichiers PHP, 394 méthodes PHPDoc, 8 modules Python, 6 ADR, 4 niveaux C4 et une fiche de service ; et des **limites assumées avec transparence** — les 200 scénarios manuels restent `NON JOUÉ` et aucune recette LineServ n’est revendiquée.  
> Les suites envisagées hors périmètre concernent l’adaptateur de préfixe d’URL, un paiement signé avec SMTP réel et la double authentification `MFA` sur le rôle `SUPER`. Nous pouvons maintenant passer à la démonstration de l’application et à la présentation du code. »

---

## 3. Script pas-à-pas de la démonstration en direct (4 minutes)

### 3.1 Préparation avant d’entrer en salle

Dans un terminal, depuis la racine du dépôt :

```bash
cp -n .env.example .env
./run.sh --db
./run.sh
```

- Vérifier que l’application répond sur **`http://localhost:8000/`**.
- Ouvrir **deux fenêtres navigateur distinctes** (ou une fenêtre normale + une fenêtre navigation privée) pour éviter tout mélange de cookies :
  - **Fenêtre 1 (Front-office / Client)** : `http://localhost:8000/`
  - **Fenêtre 2 (Back-office / Admin)** : `http://localhost:8000/admin/connexion`
- Ouvrir un **2e onglet de terminal** connecté à MySQL/MariaDB prêt à exécuter les requêtes de preuve :

```bash
mysql -u <utilisateur> -p minishop
```

> **Plan B hors ligne (si le serveur SQL tombe en salle) :** basculer `STORAGE_DRIVER=json` dans `.env` et relancer `./run.sh`. Les parcours front-office, panier, commandes et back-office fonctionnent intégralement à l’identique grâce au contrat commun `StoreInterface` (`SqlStore` / `JsonStore`).

### 3.2 Scénario de démo chronométré (4 minutes)

1. **Catalogue & recherche (`0:45`) — Fenêtre 1 (Visiteur) :**
   - Montrer l’accueil (`/`), les catégories et les nouveautés.
   - Aller sur `/catalogue`, filtrer par catégorie + tri + recherche texte.
   - Ouvrir une fiche produit (`/produit/{id}`) : montrer le prix TTC, la disponibilité serveur (`stock_actuel`) et ajouter une quantité au panier (`POST /panier/ajouter`).
2. **Panier & commande client (`1:15`) — Fenêtre 1 (Client) :**
   - Aller sur `/panier`, modifier une quantité (montrer le plafonnement côté serveur si quantité > stock).
   - Cliquer sur valider la commande (`/connexion` puis `/commander`) avec un compte client du seed (`alice@example.com`, identifiants locaux dans `tests/php/http_sql_e2e.php`).
   - Montrer le calcul serveur des frais de port (`sp_compute_shipping`) et du total TTC avant confirmation, puis valider.
   - Aller sur `/mes-commandes`, ouvrir le détail de la commande créée, puis montrer **l’annulation en 2 étapes** (page de confirmation dédiée + conservation de la ligne en historique, statut `ANNULEE`).
3. **Back-office gestionnaire & super-admin (`1:15`) — Fenêtre 2 (Admin `SUPER`) :**
   - Se connecter sur `/admin/connexion` (`admin@minishop.fr`).
   - Sur `/admin` (tableau de bord) : commenter les indicateurs (`CA total` vs `CA du jour` en fuseau local, alertes de stock bas).
   - **Gestionnaire :** aller sur `/admin/stocks` (mode par défaut **« Ajouter / retirer » (`DELTA`)** avec motif obligatoire) puis sur `/admin/commandes` pour faire évoluer une commande `EN_PREPARATION` → `EXPEDIEE`.
   - **Super-admin :** montrer brièvement `/admin/produits`, `/admin/parametres` (frais de port, franchise, TVA, conservation) et `/admin/equipe` (gestion des rôles `GESTIONNAIRE` / `SUPER`).
4. **Preuve directe en base de données (`0:45`) — Terminal SQL :**

```sql
SHOW PROCEDURE STATUS WHERE Db = DATABASE();
SHOW TRIGGERS;
SELECT * FROM v_etat_stock LIMIT 5;
SELECT id_commande, ancien_statut, nouveau_statut, date_changement
  FROM order_status_history
 ORDER BY id_historique DESC LIMIT 5;
SELECT id_mouvement, id_produit, delta_quantite, motif, date_mouvement
  FROM stock_movement_history
 ORDER BY id_mouvement DESC LIMIT 5;
CALL sp_revenue_report('2026-01-01 00:00:00', '2026-12-31 23:59:59');
```

---

## 4. Fiche mémo technique (chiffres exacts du dépôt)

À garder sous les yeux pendant les questions techniques :

### 4.1 Base de données & SQL (`sql/`)

| Élément | Quantité réelle | Détails précis à citer |
|---|---|---|
| **Tables** | **9** | `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre` |
| **Vues SQL** | **3** | `v_etat_stock`, `v_catalogue`, `v_commandes_client` |
| **Procédures stockées** | **18** | `sp_compute_shipping`, `sp_create_account`, `sp_get_credentials`, `sp_update_client`, `sp_search_products`, `sp_save_product`, `sp_delete_product`, `sp_adjust_stock`, `sp_save_category`, `sp_delete_category`, `sp_create_order`, `sp_add_order_line`, `sp_create_order_from_basket`, `sp_confirm_order`, `sp_update_order_status`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_revenue_report` |
| **Fonction SQL** | **1** | `fn_param` |
| **Déclencheurs (triggers)** | **16** | Contrôle/décrément/restauration/immutabilité/snapshot des lignes (`trg_ligne_*`), calcul TTC & règles produits (`trg_produit_*`), slugs & suppression catégories (`trg_categorie_*`), transitions/historique commandes (`trg_commande_*`, `trg_history_*`) |
| **Données de base** | `sql/01` + `sql/04` | 4 catégories, 36 produits, 3 clients seed, 1 admin `SUPER` seed (+ commandes de démo dans `04`) |

### 4.2 Architecture applicative & sécurité (`app/`, `public/`)

- **Point d’entrée unique :** `public/index.php` (routeur explicite avec contrôle de méthode HTTP → `405` / `404`, gestionnaire d’exceptions centralisé, en-têtes de sécurité).
- **Contrôleurs front-office :** `HomeController`, `CatalogueController`, `PanierController`, `AuthController`, `CompteController`, `CommandeController`.
- **Contrôleurs back-office (`app/Controller/Admin/`) :** `DashboardController`, `ProduitAdminController`, `CategorieAdminController`, `StockAdminController`, `CommandeAdminController`, `EquipeAdminController`, `ParametreAdminController`.
- **Sécurité vérifiée (`tests/security/controles.sh` — 11/11 conformes) :**
  1. **SQLi (`SEC-01`) :** 100 % requêtes préparées PDO (`ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`).
  2. **XSS (`SEC-02`) :** échappement systématique via `e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) + en-tête `Content-Security-Policy`.
  3. **Mots de passe (`SEC-04` / `RB-12`) :** hachage `password_hash(..., PASSWORD_BCRYPT)` et vérification `password_verify()`, 8 caractères minimum côté serveur et HTML, messages identiques en cas d’échec de connexion.
  4. **Sessions (`SEC-05`) :** `session_regenerate_id(true)` à la connexion et déconnexion, cookies `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, séparation `$_SESSION['client_id']` / `$_SESSION['admin_id']`.
  5. **CSRF (`SEC-06`) :** jeton aléatoire 32 octets (`bin2hex(random_bytes(32))`) vérifié en temps constant via `hash_equals()` sur toutes les routes mutatives.
  6. **IDOR (`SEC-07` / `SEC-08`) :** filtrage systématique `WHERE id_commande = ? AND id_client = ?` (ou contrôle dans `sp_confirm_order` / `sp_cancel_order`) → `404` neutre en cas de tentative d’accès croisé.
  7. **RBAC Back-office (`SEC-10` / `RB-13`) :** `Auth::exigeAdmin()` sur toutes les routes `/admin/*` et `Auth::exigeSuper()` sur `/admin/equipe*` et `/admin/parametres*` (`403` pour un `GESTIONNAIRE`).
  8. **En-têtes HTTP (`SEC-11`) :** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `CSP`.

### 4.3 Campagnes de tests automatisés

| Suite | Outil / Fichier | Dernier résultat consigné | Ce qu’elle prouve |
|---|---|---|---|
| **Campagne globale VM** | `php scripts/generer_pv.php` | **60/60** étapes vertes (`docs/pv-recette.md`) | Exécution sans warning PHP (`43` PHP, `11` Python, `57/57` PHPUnit) |
| **Contrôles de sécurité statiques** | `bash tests/security/controles.sh` | **11/11** (rejoués le 17/11/2026) | PDO préparé, `e()`, bcrypt, IDOR, CSRF `hash_equals`, sessions, gardes admin |
| **Cohérence documentation / UC** | `python3 scripts/verifier_fiches_uc.py` | **46/46** | Cohérence des 14 UC, scénarios et tableaux de couverture |
| **Audit structurel & inventaire** | `python3 -m unittest discover -s tests/python` | Complet | Inventaire `docs/17-inventaire-projet.csv`, syntaxe Python/Shell/JSON/XML |
| **Intégration SQL & parité JSON** | `tests/php/sql_integration.php` | **29/29** (`docs/pv-recette.md`) | Contraintes `CHECK`, triggers `T-01…T-29`, procédures, parité `SqlStore` / `JsonStore` |
| **E2E HTTP complet sur SQL** | `tests/php/http_sql_e2e.php` | **105/105** (`docs/pv-recette.md`) | Parcours complets client & admin via HTTP + PDO |
| **Concurrence anti-survente** | `tests/php/concurrence_http.php` | **8/8** (20 acteurs / 12 unités) | Zéro stock négatif sous requêtes concurrentes |
| **WASM / JS / Playwright** | `scripts/wasm-e2e.mjs`, Playwright | **338/338** E2E WASM (`48` états FR↔EN) | Navigation JSON isolée, traduction et responsive `320 px` à `1536 px` |
| **Performances (volumétrie cible)** | `scripts/run_perf_vm.sh` | HTTP p95 = **6 ms**, SQL p95 ≤ **20,6 ms** | Tenue de charge sur 236 produits et 1 004 commandes (`0` erreur) |
| **CI GitHub Actions** | `.github/workflows/ci.yml` | Multi-jobs (PHP 8.2/8.5, MySQL 8, MariaDB 11.8, WASM, Playwright) | Non-régression continue à chaque push |

---

## 5. Justifier proprement les choix et écarts face au sujet officiel

Si le jury compare mot à mot `sujetSAE.pdf` avec le dépôt, voici les **6 points précis** à expliquer calmement et avec assurance :

| Point du sujet officiel | Ce qui a été implémenté | Pourquoi ce choix est meilleur / conforme |
|---|---|---|
| Exemple de session `$_SESSION['user_id']` (p. 5 du sujet) | `$_SESSION['client_id']` et `$_SESSION['admin_id']` dans `app/Security/Auth.php` | **Défense en profondeur (`RB-13`)** : un client connecté ne peut jamais être confondu avec un administrateur, même dans une même session navigateur. |
| Nom indicatif `sp_add_order_item` (p. 7 du sujet) | `sp_add_order_line` + `sp_create_order_from_basket` (`sql/02_minishop_procedures.sql`) | Cohérent avec le nom français de la table `ligne_commande` ; `sp_create_order` et `sp_update_order_status` gardent les noms exacts du sujet. |
| Statut initial de commande | État interne `BROUILLON` puis confirmation → `EN_PREPARATION` | Permet de respecter `RB-04` (aucune commande validée sans ligne) ; un `BROUILLON` est refusé côté admin (`COMMANDE_NON_TRAITABLE`). |
| Rôle administrateur unique dans le sujet | Deux rôles : `SUPER` et `GESTIONNAIRE` | Principe du **moindre privilège** (OWASP ASVS / ANSSI) : seul `SUPER` crée/bloque des admins ou modifie les paramètres globaux. |
| Dénormalisation vs 3FN stricte | `produit.prix_ttc`, `commande.montant_total`, `ligne_commande.total_ligne` | Recommandation métier e-commerce : un changement de prix catalogue ou de TVA ne doit **jamais** modifier rétroactivement une facture passée ; ces colonnes sont verrouillées par triggers. |
| Formulaire de contact & paiement | Stockage local dans `var/log/contact.jsonl`, paiement/livraison simulés | Respect strict du périmètre pédagogique (`sujetSAE.pdf` : « paiement factice, sans vraie transaction bancaire »), documenté dans `README.md` et `/mentions-legales`. |

---

## 6. Top 20 des questions probables du jury (et réponses prêtes en 20 s)

### Base de données & SQL

1. **Pourquoi des procédures stockées plutôt que tout gérer en PHP ?**  
   Elles garantissent l’atomicité transactionnelle (`START TRANSACTION` / `COMMIT` / `ROLLBACK`), posent les verrous `SELECT ... FOR UPDATE` au plus près des données pour empêcher la survente, réduisent les allers-retours réseau et imposent les mêmes invariants même si une autre application appelle la base.
2. **Comment empêchez-vous qu’une commande vende plus de stock que disponible si deux clients cliquent en même temps ?**  
   Dans `sp_add_order_line` / `sp_create_order_from_basket`, la lecture du produit utilise `SELECT ... FOR UPDATE` à l’intérieur d’une transaction, puis le trigger `trg_ligne_controle_insert` et la contrainte `CHECK (stock_actuel >= 0)` valident la cohérence. Validé par `concurrence_http.php` (20 clients concurrents sur 12 unités : 0 survente).
3. **Pourquoi garder `total_ligne` et `montant_total` en base alors qu’on peut les recalculer ?**  
   C’est une dénormalisation volontaire et **verrouillée par trigger** (`trg_ligne_prix_snapshot`, `trg_ligne_immutable`) : si le prix HT ou la TVA du produit change demain, les commandes historiques conservent exactement le montant payé le jour de l’achat.
4. **Pourquoi un trigger pour `order_status_history` plutôt qu’un `INSERT` dans la procédure ?**  
   Pour garantir la traçabilité même lors d’une mise à jour SQL directe (maintenance, reprise). Les triggers `trg_history_creation` et `trg_history_statut` assurent l’unicité et l’ordre chronologique de la piste d’audit.
5. **Que se passe-t-il si on tente un `UPDATE` direct `SET stock_actuel = -5` en SQL ?**  
   Il est bloqué au niveau SGBD par la contrainte `CHECK` et les triggers de contrôle (`trg_produit_regles`), testés dans `tests/php/sql_integration.php` (`T-02`, `T-03`).
6. **Pourquoi vérifiez-vous le mot de passe en PHP (`password_verify`) et pas dans la procédure SQL ?**  
   Le sel et le coût bcrypt sont encapsulés dans le hash généré par PHP ; seule la fonction `password_verify()` compare en temps constant. La procédure `sp_get_credentials` ne renvoie donc que le hash et l’état du compte, et la vérification reste côté PHP.
7. **Que se passe-t-il si on supprime une catégorie qui contient des produits, ou un produit déjà commandé ?**  
   Les clés étrangères (`ON DELETE RESTRICT`) et les triggers `trg_categorie_delete` / `trg_produit_delete` bloquent la suppression pour préserver l’historique comptable. En pratique, l’administrateur masque un produit (`visible = 0`) au lieu de le supprimer.
8. **Pourquoi une double gestion de pilote (`SqlStore` par défaut et `JsonStore` en option) ?**  
   Le pilote SQL/PDO est le moteur principal conforme au sujet ; l’interface commune `StoreInterface` permet d’exécuter le même code applicatif sur un magasin JSON en environnement isolé (tests WASM sans serveur MySQL ou plan B de démonstration).

### Architecture MVC & code PHP

9. **Comment fonctionne votre architecture MVC ? Qui fait quoi ?**  
   `public/index.php` reçoit toutes les requêtes, résout la route, appelle le contrôleur (`app/Controller/`), qui valide les entrées/CSRF/droits, délègue l’accès données aux `Repository` (`app/Repository/`) et passe uniquement les données préparées à la vue (`app/View/`).
10. **Pourquoi `declare(strict_types=1);` dans chaque fichier PHP ?**  
    Pour éviter les conversions silencieuses de types (par exemple entre chaînes issues de `$_POST` et entiers d’identifiants) et détecter les erreurs dès l’exécution ou l’analyse statique.
11. **Où sont gérés les prix et stocks lors de la validation du panier ? Faites-vous confiance au formulaire ?**  
    Jamais. Le navigateur n’envoie que `id_produit` et `quantite`. Tous les prix, taux de TVA, stocks et frais de port sont relus et recalculés côté serveur (`PanierSession`, `CommandeRepository`, procédures SQL).
12. **Pourquoi les administrateurs sont-ils dans une table séparée `administrateur` plutôt que dans `client` ?**  
    Le cycle de vie, les attributs (adresse postale vs rôle `SUPER`/`GESTIONNAIRE`) et la surface d’attaque sont distincts : aucune route d’inscription publique ne peut écrire dans `administrateur` (`SEC-10`).

### Sécurité

13. **Comment prouvez-vous l’absence d’injection SQL ?**  
    Toutes les requêtes passent par `PDO::prepare()` + `execute()` avec `ATTR_EMULATE_PREPARES => false` (vérifié statiquement par `tests/security/controles.sh` et testé dynamiquement en E2E). Aucune concaténation SQL de variable utilisateur n’existe dans `app/`.
14. **Comment évitez-vous qu’un client consulte la commande d’un autre en changeant l’URL (`/commande/25`) ?**  
    `CommandeController` exige d’abord `Auth::exigeClient()`, puis filtre toujours par `id_client = $session['id']` dans la requête / procédure (`sp_confirm_order`, `sp_cancel_order`). Si la commande n’appartient pas au client, le serveur renvoie **404** (et non 403) pour ne pas révéler l’existence de l’ID.
15. **Comment fonctionne votre protection CSRF ?**  
    Chaque session génère un jeton de 32 octets aléatoires (`Csrf::jeton()`). Chaque formulaire `POST` inclut ce jeton, et `Csrf::verifier()` compare avec `hash_equals()` avant toute écriture métier.
16. **Pourquoi vos cookies de session ont-ils `HttpOnly`, `SameSite=Lax` et `Secure` ?**  
    `HttpOnly` empêche le vol du cookie via JavaScript en cas de XSS ; `SameSite=Lax` limite l’envoi du cookie depuis des sites tiers (CSRF) ; `Secure` impose HTTPS dès que le site est servi en TLS.

### Tests, GitLab & démarche projet

17. **Comment avez-vous testé le site sans base MySQL dans certains jobs CI ?**  
    Les jobs SQL tournent réellement sur MySQL 8.0 et MariaDB 11.8 (`29/29` + `105/105`). En complément, les tests isolés (routeur, régressions UI, Playwright, WASM) utilisent le pilote JSON sur des répertoires temporaires jetables.
18. **Pourquoi distingue-t-on le « CA du jour » du « CA total » sur le tableau de bord ?**  
    Pour donner une lecture opérationnelle immédiate à l’administrateur tout en tenant compte du fuseau horaire local (`App\Tools\Heure`), alors que les dates sont stockées de manière uniforme côté serveur.
19. **Quelle est votre politique d’annulation de commande et pourquoi en 2 étapes ?**  
    Tant que la commande est `EN_PREPARATION` (avant expédition), l’annulation restaure automatiquement le stock via le workflow SQL (`sp_cancel_order` / `sp_cancel_order_admin`) tout en conservant la commande et ses lignes pour l’audit. La page intermédiaire de confirmation évite toute annulation accidentelle en un clic.
20. **Quelles seraient les prochaines étapes si le projet passait en production réelle ?**  
    Intégrer un prestataire de paiement (webhook signé + idempotence), l’envoi réel d’e-mails transactionnels (confirmation, contact), la gestion des remises/codes promo historisés et la double authentification (`MFA`) pour le rôle `SUPER`.

---

## 7. Check-list finale (J−1 et H−30 min)

### La veille (J−1)

- [ ] Dernière version fusionnée et poussée sur le **GitLab IUT** (pas uniquement sur GitHub).
- [ ] Vérifier sur GitLab IUT : rôle `Reporter` actif pour l’enseignant référent, `README.md` lisible à la racine, `git shortlog -sne` montrant bien les commits des membres du groupe.
- [ ] Vérifier que les tests rapides passent localement :
  - `bash tests/security/controles.sh` (`11/11`)
  - `python3 scripts/verifier_fiches_uc.py` (`46/46`)
  - `python3 -m unittest discover -s tests/python` (sans erreur)
- [ ] Diaporama exporté (`livrables/minishop-soutenance.pptx` ou PDF généré localement) testable hors connexion.

### 30 minutes avant la soutenance (H−30 min)

- [ ] Base locale réinitialisée proprement (`./run.sh --db`) puis serveur démarré (`./run.sh`).
- [ ] Onglet 1 prêt sur `http://localhost:8000/` (navigation privée / visiteur).
- [ ] Onglet 2 prêt sur `http://localhost:8000/admin/connexion`.
- [ ] Terminal prêt avec les requêtes SQL de preuve (`SHOW PROCEDURE STATUS`, `SHOW TRIGGERS`, `v_etat_stock`, `stock_movement_history`, `order_status_history`).
- [ ] Chrono réglé sur **15 minutes** (alerte silencieuse à 7 min et à 13 min).
