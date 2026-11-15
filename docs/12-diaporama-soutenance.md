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
> Les repositories appellent les procédures SQL sur le chemin par défaut ; leur exécution PDO/MySQL n’a pas été rejouée dans cette révision, donc ce raccordement statique n’est pas une preuve de fonctionnement de bout en bout.

---

# Règles de gestion
## 20 règles décrites, scénarios référencés
- `RB-01…RB-11` sont classées par convention du CDC comme règles du sujet ; `RB-12…RB-20` sont neuf compléments (PDF source non suivi dans le dépôt).
- `T-01…T-29` sont des scénarios SQL, pas 29 règles ni un résultat d’exécution courant.
- Cinq séquences sont présentes : quatre séquences prévues comme imposées et une bonus catalogue ; aucune sixième n’est annoncée.
> Le fichier `tests/sql/rapport_tests_sql.md` porte une mention 29/29 datée du 27/10/2026, future à la coupure historique du 23/10 ; elle ne prouve pas SQL sur le checkout vérifié le 15/11. Le run non-SQL du 15/11 a réussi T-01…T-29 sur le moteur JSON (29/29), mais excluait explicitement l’intégration PDO/MySQL.

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
## Une pyramide, pas un slogan
- `T-01…T-29` : scénarios SQL spécifiés ; `docs/04` rapporte une campagne historique du 23/09/2026.
- `tests/sql/rapport_tests_sql.md` contient une revendication de résultat au 27/10/2026, date future à la coupure du 23/10 ; elle n’est pas présentée comme acquise à cette date.
- `F-*` et `S-*` relient les exigences aux scénarios dans `docs/02`; leurs identifiants ne prouvent pas leur exécution.
- Le registre manuel contient 200 entrées `NON JOUÉ`. Aucun test manuel LineServ, déploiement ou restauration de base n’est revendiqué.
> Le run restreint du 23/10 (`--sans-sql --sans-e2e`) et le contrôle statique sécurité 11/11 sont historiques. Un run distinct du 15/11 (`node scripts/run-all-tests.mjs --sans-sql`) a terminé avec le code 0 via PHP-WASM et inclut l’E2E HTTP isolé (338 vérifications) ; SQL/PDO et PHP CLI natif restent non joués, et aucune CI globale ou recette navigateur n’est revendiquée.

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
- Deux magasins (JSON et PDO) derrière une même interface ; SQL est le pilote par défaut et les repositories appellent statiquement les 18 procédures, mais le chemin PDO/MySQL reste à valider par un rejeu isolé
- Le dépôt prévoit des tests PHP natifs et des outils de substitution ; aucun résultat de test PHP ou MySQL n’est déduit de ce support
- Accessibilité et tableaux de données : le défilement d'un tableau est admis par WCAG 1.4.10, à condition de le documenter
- Arbitrages assumés : pas de paiement réel, images produits générées, volumétrie limitée
> Un arbitrage écrit et justifié vaut mieux qu'une fonctionnalité à moitié faite.

---

# Bilan et suites
## Où en est le projet
- Le dépôt contient l’application, les suites automatisées et les documents de conception ; cette présence n’atteste pas une recette intégrale.
- Le code sélectionne SQL par défaut et les repositories ont des points d’appel pour les 18 procédures ; aucun rejeu PDO/MySQL ni parcours HTTP SQL complet n’est validé dans cette révision.
- Les 200 scénarios manuels restent `NON JOUÉ` ; aucune validation étudiante ou pédagogique n’est revendiquée.
- Aucune recette LineServ, aucun déploiement et aucune restauration de base ne sont attestés.
> Questions ?
