# MiniShop — soutenance SAÉ 302
## Application de vente en ligne · 12 diapositives · cible 15 min + questions
- Une application PHP sans framework ; stockage MySQL/PDO (SQL) par défaut, JSON comme option explicite pour les tests portables et démonstrations isolées
- Un **cahier des charges** de 16 sections, des règles de gestion numérotées RB-01 → RB-20
- **Une base de démonstration** livrée : 36 produits (3 pages de 12), 3 clients, un back-office
> Le support résume la conception et les preuves disponibles ; il ne constitue pas un procès-verbal de démonstration ni de validation.

---

# Le besoin
## Un mini-shop crédible, pas une maquette
- Vendre du matériel informatique : catalogue, panier, commande suivie, back-office
- **14 cas d'utilisation** (UC-01 → UC-14), 3 acteurs : visiteur/client, gestionnaire, super-administrateur
- Contraintes imposées : PHP procédural objet sans framework, MySQL/PDO, Git, pas de CMS
> Objectif : un logiciel défendable en soutenance, pas une démonstration jetable.

---

# Périmètre livré
## Ce que fait réellement l'application
- **Vitrine** : accueil, catalogue filtrable, fiche produit, recherche, pages légales
- **Client** : compte, panier (sans rechargement), commande, suivi, annulation, changement de mot de passe
- **Back-office** : produits, stocks, commandes, clients, équipe, paramètres (frais de port, TVA)
- **Transverse** : journal d'audit, erreurs 404/403/500 habillées, accessibilité et responsive
> 32 écrans capturables, 1 seul point d'entrée : `public/index.php`.

---

# Architecture
## MVC strict, front controller, zéro dépendance
- `public/` = seul dossier exposé (front controller, CSS, JS, images)
- `app/Controller` → `app/Repository` → magasin de données ; `app/View` sans logique
- Routeur à **liste blanche** : aucune route administrative par convention implicite
- `StoreInterface` : deux pilotes ; **PDO/MySQL** est le défaut, `JsonStore` reste une option explicite pour les tests portables et démos sans serveur SQL
![Architecture MVC](docs/diagrams/architecture_mvc.png)
> Le choix du magasin se fait par configuration, sans toucher aux contrôleurs.

---

# Modèle de données
## 8 entités métier, 9 tables SQL, 3 vues, 18 procédures, 16 déclencheurs
- 8 entités métier : client, administrateur, catégorie, produit, commande, ligne_commande, historique_statut, paramètre ; `stock_movement_history` est une table technique d’audit supplémentaire
- Contraintes portées par la base : `CHECK`, clés étrangères, unicité, prix ≥ 0, stock ≥ 0
- Déclencheurs : décrémentation de stock, journal de statut, interdiction de suppression d'un produit commandé
- Procédures : création de commande transactionnelle, annulation, alertes de stock, purge
> Les repositories appellent les procédures SQL sur le chemin par défaut ; la chaîne complète PDO/MariaDB (`29/29` SQL et `105/105` HTTP SQL) a été validée sur VM Debian 13 le 17/11/2026 (`f9b614f`, `docs/pv-recette.md`).

---

# Règles de gestion
## 20 règles décrites, scénarios validés sur VM
- `RB-01…RB-11` sont classées par convention du CDC comme règles du sujet ; `RB-12…RB-20` sont neuf compléments (PDF source non suivi dans le dépôt).
- `T-01…T-29` ont été validés en SQL réel (`29/29` sous MariaDB 11.8.6) et sur le pilote JSON (`29/29`) lors de la campagne du 17/11/2026 (`docs/pv-recette.md`).
- Cinq séquences sont présentes : quatre séquences prévues comme imposées et une bonus catalogue ; aucune sixième n’est annoncée.
> Les 200 entrées manuelles de `docs/13-suivi-recette-uc.csv` restent distinctement `NON JOUÉ` (la validation automatique sur base isolée ne remplace pas une recette manuelle navigateur).

---

# Sécurité
## Onze contrôles statiques définis, portée limitée
- Mots de passe **bcrypt**, sessions `HttpOnly` + `SameSite=Lax`, identifiant régénéré à la connexion
- **CSRF** sur tous les formulaires et sur l'API (comparaison `hash_equals`, jeton CSPRNG)
- SQL **préparé** partout (aucune concaténation), sorties échappées par `e()`, CSP stricte
- Contrôle d'accès : route `admin`/`super`, vérification d'appartenance des commandes (IDOR)
> `tests/security/controles.sh` contient 11 contrôles statiques, dont `S-11`/`SEC-14`. Le rejeu local du 23/10/2026 a réussi 11/11 ; ce résultat ne remplace pas les 10 scénarios offensifs `S-01…S-10` ni une recette LineServ.

---

# Tests
## Campagne automatisée complète sur VM (17/11/2026)
- `php scripts/generer_pv.php` (`f9b614f`, VM Debian 13, PHP 8.4.27 / MariaDB 11.8.6) : **60/60 étapes vertes**, **0 warning PHP** (`docs/pv-recette.md`).
- Intégration SQL (`29/29`), parcours HTTP SQL de bout en bout (**105/105**), concurrence HTTP 20 acteurs / 12 unités (**8/8**), E2E JSON isolé (**338/338**), PHPUnit (**57/57**), linter strict (**171** fichiers), sécurité statique (**11/11**).
- Performances `ENF-01` (`cb0f814`, `minishop_perf` : 236 produits, 1 004 commandes) : SQL p95 ≤ **20,6 ms**, HTTP p95 global = **6 ms**, **0 erreur** (`tests/perf/dernieres_mesures.txt`).
- Le registre manuel contient **200 entrées `NON JOUÉ`**. Aucun test manuel LineServ, déploiement ou restauration de base n’est revendiqué.
> PV décidé et visé nominativement par Johan Polsinelli et Chahd Gouiaa dans `docs/pv-recette.md`.

---

# Design
## Un système, pas des feuilles de style empilées
- Étude de 5 concurrents (Amazon, Cdiscount, Fnac, Boulanger, Back Market) → `docs/DA.md`
- Jetons : palette contrastée AA/AAA, échelle typographique en `rem`, grille 8 px
- Responsive vérifié de **320 px à 1536 px**, paliers en `em`, cibles tactiles ≥ 44 px
- Accessibilité : lien d'évitement, focus visible, `aria-live`, contrastes mesurés
![États de commande](docs/diagrams/etats_commande.png)
> Aucun débordement horizontal : l'audit WCAG 1.4.10 est rejouable par script.

---

# Démonstration
## Trois minutes, trois parcours
- **Client** : recherche → fiche produit → ajout au panier (sans rechargement) → commande → suivi
- **Gestionnaire** : expédition d'une commande → journal de statut mis à jour automatiquement
- **Super-admin** : création d'un produit → contrôle du stock et de l'alerte de rupture
> Les identifiants de démonstration ne sont pas reproduits dans ce support. Pour une recette, utiliser uniquement des comptes de test autorisés et transmettre tout accès séparément par canal privé.

---

# Difficultés et arbitrages
## Ce que nous avons appris
- Deux magasins (JSON et PDO) derrière une même interface ; SQL est le pilote par défaut et l’intégration PDO/MariaDB (`29/29` SQL, `105/105` HTTP SQL, `8/8` concurrence) est validée sur VM isolée
- Résolution de bugs réels en campagne VM : collation `utf8mb4_unicode_ci`, fuseau UTC, requêtes non bufferisées `SqlLoader`, garde `--load` sur base perf
- Accessibilité et tableaux de données : le défilement d'un tableau est admis par WCAG 1.4.10, à condition de le documenter
- Arbitrages assumés : pas de paiement réel, images produits générées, installation CLI sécurisée (`scripts/db_init.php` / `./run.sh --db`)
> Un arbitrage écrit et justifié vaut mieux qu'une fonctionnalité à moitié faite.

---

# Bilan et suites
## Où en est le projet
- Application PHP/PDO + MariaDB validée automatiquement sur VM Debian 13 (**60/60** étapes, **p95 HTTP = 6 ms**, `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt`).
- Pilotage WBS / todolist à jour : **82 tâches / 348 h** (**34 `OK`**, **48 `OK*`**, **0 `PARTIEL`**, **0 `[!]`**), relectures croisées continues attestées (`docs/21`).
- Les **200 scénarios manuels** (`docs/13-suivi-recette-uc.csv`) restent `NON JOUÉ` ; aucune validation pédagogique n’est revendiquée.
- Aucune recette LineServ, aucun déploiement et aucune restauration de base ne sont attestés.
> Questions ?
