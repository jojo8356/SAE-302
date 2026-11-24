# MiniShop — soutenance SAÉ 302
## Application e-commerce PHP 8.2+ sans framework · 12 diapositives · cible 15 min + questions
- **Code source PHP 8.2+ strict** (`declare(strict_types=1);`) sans dépendance runtime (`composer.json`) : `112` fichiers `app/` et `3` sous `public/`
- **Double persistance** derrière `StoreInterface` : **`SqlStore` (PDO / MySQL / MariaDB)** par défaut, **`JsonStore`** en option explicite (`STORAGE_DRIVER=json`)
- **Jeu de démonstration** (`sql/01`, `sql/04`, `Seed.php`) : `4` catégories, `36` produits (`35` visibles sur `3` pages), `3` clients, `1` admin `SUPER`, `4` commandes SQL et `4` paramètres (`4,90 €` port, franchise `80 €`)
- **Gouvernance documentaire** : cahier des charges (`16` sections), `6` ADR MADR (`0000`–`0005`), `4` niveaux C4, exploitation et `catalog-info.yaml`
> Ce support reflète le code présent dans le dépôt ; il ne vaut ni procès-verbal de validation pédagogique ni attestation LineServ.

---

# Le besoin et les acteurs
## Remplacer le suivi manuel par une boutique fiable
- Catalogue matériel informatique, panier persistant en session, commande suivie et back-office
- **14 cas d'utilisation**, **3 acteurs**
- Contraintes respectées : **PHP 8.2+ POO**, **MySQL/MariaDB via PDO**
- Traçabilité native
![Cas d'utilisation global](docs/diagrams/cas_utilisation_global.png)
> Objectif : une application maintenable, testable et défendable devant le jury, pas une maquette jetable.

---

# Périmètre livré dans le code
## 67 routes explicites (public/index.php) · 40 gabarits de vue (app/View/)
- **Front-office (`35` routes publiques)** : accueil, catalogue filtrable/trié, fiche produit, recherche, catégories, `/preferences` (FR/EN + fuseau), contact (`var/log/contact.jsonl`) et `7` pages légales
- **Espace client (`9` routes `client`)** : inscription, profil, mot de passe, panier HTML + API interne `/api/panier*`, validation commande, historique et annulation en 2 étapes
- **Back-office (`20` routes `admin` + `3` `super`)** : tableau de bord (CA du jour UTC/fuseau + alertes), produits, catégories, stocks, commandes, paramètres et équipe (`SUPER`)
- **Transverse & CLI (`app/Service/`, `scripts/`)** : pages 403/404/500, journal `var/journal/db.jsonl`, rétention RGPD `24` mois (`RetentionComptes`) et rotation journaux/sauvegardes
> Point d'entrée HTTP unique public/index.php ; aucun écran admin « clients » distinct, gestion d'équipe réservée à SUPER.

---

# Architecture logicielle
## MVC pragmatique, front controller, routeur à liste blanche
- `public/` seul dossier exposé : `index.php`, `.htaccess`, `css/`, `js/`, `img/`, `fonts/`
- `app/bootstrap.php` : autoloader PSR-4 maison (`App\` → `app/`), fuseau **UTC**, zéro dépendance Composer en prod
- `app/Router.php` : `42 GET` + `25 POST` avec gardes (`public`, `client`, `admin`, `super`)
- `15` contrôleurs → `9` classes `Repository` → `Database::store()` (`SqlStore` / `JsonStore`)
![Architecture MVC](docs/diagrams/architecture_mvc.png)
> Les contrôleurs et repositories ne dépendent que de StoreInterface et des capacités SQL dédiées.

---

# Modèle de données et SQL
## 9 tables, 3 vues, 18 procédures stockées, 1 fonction et 16 déclencheurs
- **9 tables 3FN** (`sql/01`) : `administrateur`, `client`, `categorie`, `produit`, `stock_movement_history`, `parametre`, `commande`, `ligne_commande`, `order_status_history`
- **3 vues** (`v_etat_stock`, `v_catalogue`, `v_commandes_client`) + **1 fonction** (`fn_param`)
- **18 procédures** (`sql/02`) appelées par les `Repository` : commande, `sp_adjust_stock`, recherche, port, CA
- **16 déclencheurs** (`sql/03`) : prix TTC, snapshot ligne, stock, matrice statuts, audit, blocage suppression
![Modèle logique de données](docs/diagrams/mld_minishop.png)
> Chaîne PDO/MariaDB 11.8.6 validée sur VM Debian 13 le 17/11/2026 : 29/29 SQL et 105/105 HTTP SQL (docs/pv-recette.md).

---

# Logique métier et parité SQL / JSON
## Règles appliquées côté serveur (PanierSession, Repositories, Schema & Triggers)
- **Panier (`PanierSession`)** : seuls `id_produit` et `quantite` en session ; prix, TVA, port (`4,90 €` / `80 €`) et stock recalculés côté serveur
- **Anti-survente (`SqlStore` / `RowLockingStore`)** : verrous `SELECT … FOR UPDATE` dans `sp_create_order_from_basket` et `sp_add_order_line`
- **Cycle de commande (`RB-11`, `RB-18`)** : `BROUILLON` → `EN_PREPARATION` → `PAYEE` → `EXPEDIEE` ; annulation en 2 étapes, restitution stock avant expédition uniquement
- **Parité JSON (`Schema`, `Triggers`, `Views`)** : `T-01…T-29` validés `29/29` en SQL et en JSON
![États d'une commande](docs/diagrams/etats_commande.png)
> Les 200 scénarios manuels (docs/13-suivi-recette-uc.csv) restent distinctement NON JOUÉ dans le PV du 17/11/2026.

---

# Sécurité applicative
## Défense en profondeur dans app/Security/ et public/index.php
- **Sessions & RBAC (`Auth.php`)** : `bcrypt` (`password_hash`/`verify`), espaces `client_id` / `admin_id` isolés, `session_regenerate_id(true)`, cookie `MINISHOPSESS` (`HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` sous HTTPS)
- **CSRF (`Csrf.php`)** : jeton CSPRNG `random_bytes(32)` vérifié via `hash_equals()` sur les formulaires `POST` et `/api/panier/*`
- **XSS & En-têtes (`xss.php`, `index.php`)** : échappement `e()`, CSP stricte (`default-src 'none'`, `style-src 'self'`, `script-src 'self'`, `frame-ancestors 'none'`), `X-Frame-Options: DENY`, `nosniff`
- **SQL & IDOR (`SqlStore`, `CommandeRepository`)** : requêtes préparées PDO (`ATTR_EMULATE_PREPARES => false`), identifiants filtrés par `Schema`, filtrage `id_client` (`404` sur ressource tierce)
- **Audit statique (`tests/security/controles.sh`)** : `11/11` contrôles statiques (dont `S-11`/`SEC-14`), rejoués le 17/11/2026
> Ces contrôles statiques et unitaires sont distincts des 10 scénarios offensifs manuels S-01…S-10 et de LineServ.

---

# Tests automatisés et performances
## Campagne VM Debian 13 (17/11/2026) · PHP 8.4.27 · MariaDB 11.8.6
- **Campagne globale (`scripts/generer_pv.php`, `f9b614f`)** : **60/60 étapes vertes**, `0` warning PHP (`docs/pv-recette.md`), incluant `43` suites PHP, `11` suites Python, `strict_types_lint.php` et `57/57` PHPUnit
- **SQL & Concurrence** : intégration SQL (`29/29`), HTTP SQL E2E (`105/105`), concurrence HTTP `20` clients / `12` unités sans survente (`8/8`), sécurité statique (`11/11`)
- **JS / Navigateur (`tests/node/`)** : E2E WASM JSON isolé (`338/338`), préférences/traduction FR↔EN (`48` états rendus, `45` erreurs), contrat responsive Playwright Chromium (`320 px → 1536 px`)
- **Performances `ENF-01` (`cb0f814`, `minishop_perf` : 236 produits, 1 004 commandes)** : SQL p95 ≤ **20,6 ms**, écriture `sp_add_order_line` **44 ms**, HTTP p95 = **6 ms**, `0` erreur (`tests/perf/dernieres_mesures.txt`)
> Tests exécutés sur VM locale isolée ; aucune recette manuelle LineServ, déploiement ou restauration réelle n'est attestée.

---

# Design system et accessibilité
## CSS natif sans framework (public/css/app.css, print.css) · JavaScript progressif
- **Design system (`docs/DA.md`)** : jetons CSS, palette contrastée AA/AAA, échelle fluide en `rem`, grille `8 px`, feuille d'impression `print.css`
- **Accessibilité WCAG 2.1 AA (`layout.php`, `app/View/Image/`)** : lien d'évitement, `aria-current="page"`, `aria-live` sur le badge panier, classes `AlternativeImage` (informative vs décorative), cibles tactiles ≥ `44 px`
- **Responsive vérifié (`css_contract.php`, `responsive_contract.php`)** : de **320 px à 1536 px**, tableaux défilants documentés (WCAG 1.4.10)
- **JS natif optionnel (`public/js/`)** : panier AJAX (`panier.js`), traduction complète FR↔EN (`i18n-en.js`), fuseau horaire `localStorage` et géolocalisation sans tiers (`preferences.js`, `tz-lookup.js`)
> ENF-07 respecté : tous les parcours critiques (catalogue, panier, commande, admin) fonctionnent sans JavaScript.

---

# Démonstration en direct
## Environnement local isolé (./run.sh --db puis ./run.sh → http://localhost:8000/)
- **Front-office & Client** : recherche `/catalogue` → fiche `/produit/{slug}` → panier AJAX → `/commande/valider` → annulation en 2 étapes
- **Back-office Gestionnaire** : `/admin/commandes/{id}` (statut) et `/admin/stocks` (ajustement tracé)
- **Back-office Super-admin** : `/admin/produit/nouveau`, `/admin/parametres`, `/admin/equipe`
- **Preuve SQL directe** : `v_etat_stock`, `stock_movement_history` et `order_status_history`
![Séquence passer commande](docs/diagrams/seq_passer_commande.png)
> Aucun mot de passe n'est affiché sur ce support ; les accès de démo locale restent séparés de toute cible LineServ.

---

# Difficultés rencontrées et arbitrages
## Problèmes réels résolus dans le code et choix assumés
- **Parité SQL / JSON** : double implémentation (`SqlStore` + `Triggers.php`/`Views.php`), unifiée par `StoreInterface` et testée à `29/29`
- **Incidents VM résolus dans le code** : collation `utf8mb4_unicode_ci`, stockage **UTC** (`Heure.php`), requêtes non bufferisées `SqlLoader`, garde `--load` sur `minishop_perf`, pool de sessions WASM (`Auth.php`)
- **Arbitrages assumés** : paiement et expédition simulés, contact journalisé localement (`var/log/contact.jsonl` sans SMTP), installation CLI dédiée (`scripts/db_init.php` / `./run.sh --db`)
- **Cible LineServ (`docs/14-guide-lineserv.md`)** : non testée ; prérequis bloquants documentés (`RewriteBase /`, séparation `app/`/`.env` hors `www/`, droits SQL/PHP)
> Chaque limite technique est explicitement tracée dans ARCHITECTURE.md, docs/adr/ et docs/14-guide-lineserv.md.

---

# Bilan, documentation et suites
## État vérifié du dépôt et perspectives d'évolution
- **Produit validé en VM (`docs/pv-recette.md`)** : `60/60` étapes automatisées, HTTP p95 = `6 ms`, pilotage WBS `82` tâches / `348 h` (`34 OK`, `48 OK*`, `0 [!]`)
- **Documentation vivante (`verifier_doc_code.py`, `build-docs.sh`)** : `113` fichiers PHP (`394` méthodes PHPDoc), `8` modules Python avec doctests, `6` ADR MADR, `4` C4, `fiche-service-minishop.md` et `catalog-info.yaml`
- **Limites maintenues** : `200` scénarios manuels `NON JOUÉ` (`docs/13-suivi-recette-uc.csv`), aucune recette ni déploiement LineServ attesté
- **Suites hors périmètre** : adaptateur de préfixe URL pour hébergement sous-dossier, prestataire de paiement signé, envoi SMTP réel et MFA `SUPER`
> Merci pour votre attention — place aux questions et à la démonstration.
