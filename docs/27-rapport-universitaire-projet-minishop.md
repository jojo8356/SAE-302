# Rapport de projet universitaire — Conception, sécurisation et validation d’une architecture e-commerce transactionnelle sans framework (MiniShop)

> **Mémoire complet de projet académique répondant à l’ensemble des questions, exigences et critères du cahier des charges officiel SAÉ 302** — Université Côte d’Azur · IUT de Nice · Département Informatique · BUT2.

---

## Fiche d’identification académique

| Rubrique | Information |
|---|---|
| **Établissement** | Université Côte d’Azur — Institut Universitaire de Technologie (IUT) de Nice Côte d’Azur |
| **Formation & Unité** | Bachelor Universitaire de Technologie (BUT) Informatique — Situation d’Apprentissage et d’Évaluation **SAÉ 302** |
| **Intitulé du projet** | **MiniShop** — Site e-commerce transactionnel et back-office d’administration |
| **Auteurs (binôme)** | **Johan Polsinelli** & **Chahd Gouiaa** |
| **Encadrant pédagogique** | **Mr Thanh-Phuong Nguyen** (`thanh-phuong.nguyen@univ-cotedazur.fr`, accès `Reporter` sur GitLab) |
| **Année universitaire** | 2025 – 2026 (campagnes de validation VM consignées au 17 novembre 2026) |
| **Références** | PHP 8.2+ / PDO / MySQL 8.0+ / MariaDB 10.6+ / JavaScript natif |

---

## Remerciements

Nous adressons nos sincères remerciements à **Mr Thanh-Phuong Nguyen**, notre enseignant référent, pour la précision du cahier des charges de cette SAÉ 302, ses conseils sur la modélisation relationnelle et l’architecture transactionnelle, ainsi que son exigence constante en matière de sécurité applicative et de rigueur documentaire.

Nous remercions également l’ensemble de l’équipe pédagogique du département Informatique de l’IUT de Nice Côte d’Azur pour la qualité des enseignements dispensés en conception de bases de données, SQL avancé, programmation orientée objet PHP, développement Web côté client, cybersécurité et méthodologie de tests, ainsi que pour la mise à disposition des environnements d’hébergement pédagogique.

Enfin, nous exprimons notre gratitude aux membres du jury qui consacrent leur temps à l’évaluation de ce mémoire et de la soutenance associée.

---

## Résumé

Ce rapport présente l’étude complète, la conception, la sécurisation, l’implémentation et la validation expérimentale de **MiniShop**, une plateforme Web de commerce électronique dotée d’un back-office d’administration, réalisée dans le cadre de la SAÉ 302 du BUT Informatique. Répondant intégralement aux spécifications du sujet officiel, le projet couvre les onze compétences fondamentales attendues : modélisation Merise et UML 2.5, SQL avancé sous MySQL/MariaDB, développement PHP 8.2+ orienté objet sans framework, accès aux données par PDO, interactions progressives en JavaScript, gestion des sessions, sécurité applicative, procédures stockées, déclencheurs et validation expérimentale.

Notre problématique d’ingénierie consiste à garantir l’intégrité stricte des stocks, l’immutabilité tarifaire des commandes et la résistance aux vulnérabilités Web courantes sans recourir à un framework industriel tiers, tout en offrant une portabilité vers un moteur de persistance secondaire JSON (`StoreInterface`). La base relationnelle implémente **9 tables en 3FN** (avec deux dénormalisations comptables verrouillées par déclencheurs), **3 vues**, **1 fonction** (`fn_param`), **18 procédures stockées** (minimum imposé : `5`) et **16 déclencheurs** (minimum imposé : `5`).

Les campagnes automatisées exécutées sur machine virtuelle Debian 13 (PHP `8.4.27`, MariaDB `11.8.6`) valident **60/60 étapes** de recette, **29/29 scénarios d’intégration SQL**, **105/105 vérifications HTTP SQL E2E**, **8/8 scénarios de concurrence** sans survente (`20` acheteurs simultanés pour `12` unités), **11/11 contrôles statiques de sécurité** et une latence HTTP au 95e centile de **6,0 ms** (seuil cible `< 500 ms`). L’analyse critique précise enfin les limites du périmètre maquette (paiement simulé, contact journalisé localement sans SMTP, `200` scénarios manuels restant `NON JOUÉ`).

**Mots-clés :** génie logiciel, architecture MVC sans framework, base de données relationnelle, normalisation 3FN, procédures stockées, triggers SQL, sécurité OWASP, validation expérimentale.

---

## Sommaire

- **Introduction générale**
  - Contexte pédagogique et genèse du besoin
  - Problématique scientifique et technique
  - Objectifs et structuration du rapport
- **Chapitre 1 — Spécification du système : contexte, acteurs, fonctionnalités et état de l’art**
  - 1.1 Présentation du projet, objectifs et les 11 compétences mobilisées
  - 1.2 Acteurs et fonctionnalités demandées (Visiteur, Client, Administrateur)
  - 1.3 Règles métier officielles (`RB-01` à `RB-11`) et extensions (`RB-12` à `RB-20`)
  - 1.4 Contraintes techniques (`CT-01` à `CT-12`) et justification des choix technologiques
- **Chapitre 2 — Modélisation UML 2.5 et spécifications de sécurité**
  - 2.1 Diagramme de cas d’utilisation et les 14 cas d’utilisation minimum
  - 2.2 Descriptions textuelles détaillées de 3 cas d’utilisation (hors « Passer une commande »)
  - 2.3 Diagramme de classes et justification des choix de conception objet
  - 2.4 Les 4 diagrammes de séquences (authentification, recherche, panier, commande)
  - 2.5 Réponses aux 5 spécifications de sécurité du cahier des charges
  - 2.6 Architecture 3 couches MVC adaptée au système
- **Chapitre 3 — Analyse et conception de la base de données (Merise, Normalisation et SQL)**
  - 3.1 Modèle Conceptuel des Données (MCD) : entités, associations et cardinalités justifiées
  - 3.2 Modèle Logique des Données (MLD) et vues relationnelles
  - 3.3 Normalisation complète : dépendances fonctionnelles, 1FN/2FN/3FN, PK/FK et redondances
  - 3.4 Script SQL d’installation et jeu de données (`sql/01` à `sql/04`)
  - 3.5 Procédures stockées (18 livrées) et réponse à la question pédagogique du sujet
  - 3.6 Déclencheurs (16 livrés) : couverture de « Trigger 1 — Stock » et « Trigger 2 — Historique »
- **Chapitre 4 — Application Web, protocole de validation expérimentale et performances**
  - 4.1 Réalisation PHP/PDO/JS, persistance duale `StoreInterface` et ergonomie
  - 4.2 Stratégie et document de tests : synthèses chiffrées sur VM Debian 13
  - 4.3 Évaluation quantitative des performances (`ENF-01`) et plans d’exécution `EXPLAIN`
  - 4.4 Analyse critique des limites, biais et écarts par rapport au CDC initial
- **Chapitre 5 — Livraison, exploitation (*Build → Run*), gouvernance GitLab et conformité au barème**
  - 5.1 Prérequis d’hébergement, procédure de déploiement, retour arrière et MCO
  - 5.2 Gouvernance GitLab, `README.md` et organisation de la soutenance (15 minutes)
  - 5.3 Grille complète de vérification face aux pénalités du sujet officiel SAÉ 302
- **Conclusion générale et perspectives**
- **Bibliographie et webographie**
- **Annexes**
  - Annexe A — Cartographie des 15 contrôleurs PHP et politiques d’accès
  - Annexe B — Répertoire complet des 3 vues, 1 fonction, 18 procédures et 16 triggers SQL
  - Annexe C — Requêtes SQL de preuve de non-contradiction des redondances

---

## Table des figures

- **Figure 1** — Diagramme de cas d’utilisation du processus de commande (`docs/diagrams/cas_utilisation_commandes.png`)
- **Figure 2** — Diagramme de classes de l’application PHP (`docs/diagrams/diagramme_classes.png`)
- **Figure 3** — Diagramme de séquence : Authentification (`docs/diagrams/seq_authentification.png`)
- **Figure 4** — Diagramme de séquence : Rechercher un produit (`docs/diagrams/seq_recherche_produit.png`)
- **Figure 5** — Diagramme de séquence : Ajout au panier (`docs/diagrams/seq_ajout_panier.png`)
- **Figure 6** — Diagramme de séquence : Passer une commande (`docs/diagrams/seq_passer_commande.png`)
- **Figure 7** — Architecture 3 couches MVC et double persistance (`docs/diagrams/architecture_mvc.png`)
- **Figure 8** — Modèle Conceptuel des Données à 9 entités (`docs/diagrams/mcd_minishop.png`)
- **Figure 9** — Modèle Logique des Données normalisé (`docs/diagrams/mld_minishop.png`)
- **Figure 10** — Automate à 6 états du cycle de vie d’une commande (`docs/diagrams/etats_commande.png`)

## Table des tableaux

- **Tableau 1** — Matrice des 11 compétences pédagogiques du sujet dans MiniShop
- **Tableau 2** — Synthèse des fonctionnalités demandées et livrées par acteur
- **Tableau 3** — Les 11 règles métier officielles du cahier des charges et leurs mécanismes d’application
- **Tableau 4** — Contraintes techniques (`CT-01` à `CT-12`) et état de l’art comparatif
- **Tableau 5** — Correspondance des 14 cas d’utilisation minimum du sujet
- **Tableau 6** — Réponses techniques aux 5 spécifications de sécurité du sujet
- **Tableau 7** — Justification détaillée des cardinalités du MCD
- **Tableau 8** — Dépendances fonctionnelles (DF) identifiées table par table
- **Tableau 9** — Vérification formelle 1FN, 2FN et 3FN par relation
- **Tableau 10** — Justification des clés primaires (PK), uniques (UK) et étrangères (FK)
- **Tableau 11** — Inventaire des redondances maîtrisées et mécanismes de contrôle
- **Tableau 12** — Réponse détaillée aux 5 axes de la question pédagogique sur les procédures stockées
- **Tableau 13** — Correspondance exacte entre les exemples du sujet et les objets SQL livrés
- **Tableau 14** — Bilan chiffré des campagnes de tests automatisés et VM Debian 13
- **Tableau 15** — Mesures expérimentales de performance SQL et HTTP (`ENF-01`)
- **Tableau 16** — Contrôle de conformité face aux livrables et pénalités du barème officiel

---

## Glossaire des sigles et acronymes

| Sigle | Définition exacte |
|---|---|
| **3FN** | Troisième Forme Normale (dépendances fonctionnelles directes de la clé primaire) |
| **ACID** | Atomicité, Cohérence (*Consistency*), Isolation, Durabilité |
| **ANSSI / OWASP** | Agence Nationale de la Sécurité des Systèmes d’Information / *Open Worldwide Application Security Project* |
| **CSP / CSRF** | *Content Security Policy* / *Cross-Site Request Forgery* |
| **DF** | Dépendance Fonctionnelle (`X -> Y`) |
| **ENF / CT** | Exigence Non Fonctionnelle / Contrainte Technique |
| **IDOR** | *Insecure Direct Object Reference* (accès direct non autorisé à une ressource par identifiant) |
| **MCD / MLD** | Modèle Conceptuel des Données / Modèle Logique des Données (Merise) |
| **MVC** | *Model–View–Controller* (architecture applicative en 3 couches logiques) |
| **PDO** | *PHP Data Objects* (couche d’abstraction et de requêtes préparées en PHP) |
| **PK / FK / UK** | *Primary Key* (clé primaire) / *Foreign Key* (clé étrangère) / *Unique Key* (clé unique) |
| **RB / SEC / UC** | Règle Métier / Exigence de Sécurité / Cas d’Utilisation (*Use Case*) |
| **RBAC** | *Role-Based Access Control* (contrôle d’accès basé sur les rôles) |
| **SGBD / SQL** | Système de Gestion de Base de Données / *Structured Query Language* |
| **XSS** | *Cross-Site Scripting* (injection de code côté client) |

---

## Introduction générale

### Contexte pédagogique et genèse du besoin

Dans le cadre du **Bachelor Universitaire de Technologie (BUT) Informatique** à l’IUT de Nice Côte d’Azur, la **Situation d’Apprentissage et d’Évaluation SAÉ 302** confronte les étudiants à un projet complet d’ingénierie logicielle : concevoir, modéliser, implémenter, sécuriser et valider une application de commerce électronique nommée **MiniShop**. L’entreprise commanditaire souhaite passer d’un suivi artisanal de ses ventes — reposant sur des tableurs dépourvus d’atomicité et d’historique — à une plateforme Web structurée comportant un front-office public/client et un back-office d’administration.

Au-delà de la livraison d’un site fonctionnel, le cahier des charges officiel poursuit un objectif pédagogique explicite : démontrer la maîtrise simultanée des couches conceptuelles (Merise, UML 2.5), des mécanismes internes d’un SGBD relationnel (contraintes, procédures stockées, déclencheurs), du développement **PHP 8.2+ orienté objet sans framework** via PDO, du JavaScript natif côté client, et de la cybersécurité applicative selon les standards actuels.

### Problématique scientifique et technique

Notre travail répond à la problématique centrale suivante :

> **Comment concevoir, sécuriser et valider une application Web e-commerce en PHP orienté objet sans framework tiers qui garantisse formellement, au niveau du SGBD comme de l’application, l’intégrité des stocks sous accès concurrents, l’immutabilité financière des commandes et l’immunité contre les vulnérabilités Web classiques, tout en conservant une portabilité vers un pilote de persistance secondaire ?**

### Objectifs et structuration du rapport

Afin de répondre de manière exhaustive et vérifiable à **toutes les questions et exigences du cahier des charges officiel**, ce rapport suit pas à pas le plan académique attendu par le jury :
- Le **Chapitre 1** pose la spécification générale (présentation, objectifs, acteurs, fonctionnalités, règles métier, contraintes techniques et état de l’art justificatif).
- Le **Chapitre 2** traite les diagrammes UML 2.5 exigés (cas d’utilisation dont **3 descriptions textuelles hors « Passer une commande »**, diagramme de classes justifié, **4 diagrammes de séquences**), les **5 réponses de sécurité** et l’architecture 3 couches MVC.
- Le **Chapitre 3** développe intégralement l’analyse et la conception BD/SQL (MCD, MLD, **les 5 étapes de normalisation**, scripts SQL avec jeu de données, **18 procédures stockées + réponse complète aux 5 volets de la question pédagogique**, et **16 déclencheurs** incluant *Stock* et *Historique*).
- Le **Chapitre 4** expose la réalisation applicative, les campagnes de tests et validation sur VM Debian 13, les performances mesurées (`ENF-01`) et l’analyse critique des limites.
- Le **Chapitre 5** synthétise l’exploitation (*Build → Run*), la gouvernance GitLab, la soutenance et la conformité au barème officiel.

---

## Chapitre 1 — Spécification du système : contexte, acteurs, fonctionnalités et état de l’art

### 1.1 Présentation du projet, objectifs et les 11 compétences mobilisées

**MiniShop** est une boutique e-commerce spécialisée en matériel informatique, audio et objets connectés, articulée autour d’un parcours d’achat complet et d’un back-office de gestion. Sur le plan fonctionnel, le système vise trois objectifs mesurables : (1) offrir aux visiteurs et clients une navigation fluide (`< 500 ms` au 95e centile), (2) garantir l’absence de survente et la fiabilité comptable des commandes, et (3) outiller les gestionnaires pour piloter catalogue, catégories, stocks et expéditions.

| Compétence exigée par le sujet officiel | Traduction concrète dans MiniShop | Preuve dans le dépôt |
|---|---|---|
| **1. Conception & modélisation de BD** | MCD à 9 entités, MLD 3FN, 2 dénormalisations verrouillées | `docs/04-conception-bd-et-sql.md`, `mcd_minishop.png` |
| **2. SQL et MySQL / MariaDB** | DDL InnoDB `utf8mb4`, `CHECK`, `FULLTEXT`, `3` vues, jeu de démo par `CALL` | `sql/01_minishop_schema.sql`, `sql/04_minishop_demo.sql` |
| **3. Développement PHP** | PHP `8.2+` en typage strict (`declare(strict_types=1)`), zéro framework | `app/`, `public/index.php` |
| **4. Programmation orientée objet** | Espaces de noms `App\`, contrat `StoreInterface`, hiérarchie `Controller` | `app/Controller/`, `app/Repository/`, `app/Model/` |
| **5. Accès aux BD avec PDO** | `SqlStore` et `DbConnexion`, `ATTR_EMULATE_PREPARES => false` | `app/Config/DbConnexion.php`, `SqlStore.php` |
| **6. Programmation côté client JS** | Amélioration progressive du panier, i18n FR/EN, fuseau horaire local | `public/js/panier.js`, `ui.js`, `i18n-en.js` |
| **7. Authentification & sessions** | Sessions strictes, séparation `client_id` / `admin_id`, régénération ID | `app/Security/Auth.php`, `AuthController.php` |
| **8. Sécurité des applications Web** | PDO préparé, `e()` + CSP, `Csrf::verifier()` (`hash_equals`), bcrypt, IDOR | `app/Security/`, `tests/security/controles.sh` (`11/11`) |
| **9. Procédures stockées** | **18 procédures** + **1 fonction** (`fn_param`), verrous `FOR UPDATE` | `sql/02_minishop_procedures.sql` |
| **10. Déclencheurs (triggers)** | **16 triggers** (`stock`, `prix_ttc`, `snapshot`, `ORDER_STATUS_HISTORY`) | `sql/03_minishop_triggers.sql` |
| **11. Tests et validation Web** | PHPUnit (`57/57`), SQL (`29/29`), E2E (`105/105`), concurrence (`8/8`) | `tests/`, `docs/02`, `docs/pv-recette.md` |

*Tableau 1 — Couverture des 11 compétences pédagogiques imposées par le cahier des charges.*

### 1.2 Acteurs et fonctionnalités demandées (Visiteur, Client, Administrateur)

Le cahier des charges définit **trois acteurs principaux** (`Visiteur`, `Client`, `Administrateur`), que notre conception complète en dissociant l’acteur `Administrateur` en deux niveaux de privilèges (`GESTIONNAIRE` et `SUPER`) conformément au principe de moindre privilège.

| Acteur | Fonctionnalités exigées par le sujet officiel | Extensions livrées dans MiniShop |
|---|---|---|
| **Visiteur** | (1) Consulter les catégories ; (2) Rechercher un produit ; (3) Filtrer les produits ; (4) Consulter la fiche détaillée d’un produit ; (5) Créer un compte. | Tri multicritères, pagination plafonnée, panier visiteur en session conservé jusqu’à connexion, préférences langue/UTC, formulaire contact. |
| **Client** | (1) Se connecter / se déconnecter ; (2) Modifier ses informations ; (3) Ajouter des produits au panier ; (4) Modifier les quantités ; (5) Supprimer des produits du panier ; (6) Valider une commande ; (7) Consulter son historique de commandes. | Changement sécurisé de mot de passe, adresse de livraison structurée pré-remplie, annulation client en 2 étapes avant expédition (`UC-07`). |
| **Administrateur** (`GESTIONNAIRE` & `SUPER`) | (1) Ajouter / modifier / supprimer une catégorie ; (2) Ajouter / modifier / supprimer un produit ; (3) Modifier le stock ; (4) Consulter les commandes ; (5) Modifier le statut d’une commande. | Tableau de bord CA/alertes, ajustement de stock `DELTA` avec motif obligatoire tracé (`stock_movement_history`), annulation admin 2 étapes, et gestion équipe + paramètres réservée à `SUPER`. |

*Tableau 2 — Correspondance exhaustive entre les fonctionnalités demandées et les fonctionnalités livrées.*

**Périmètre hors maquette assumé :** comme indiqué dans `docs/01` et `ARCHITECTURE.md`, les paiements bancaires et la livraison sont simulés, et les messages de contact sont journalisés localement (`var/log/contact.jsonl`) sans serveur SMTP.

### 1.3 Règles métier officielles (`RB-01` à `RB-11`) et extensions (`RB-12` à `RB-20`)

Les **11 contraintes spécifiques du métier** inscrites au sujet officiel (p. 2) sont toutes formalisées, implémentées côté serveur/SGBD et associées à des scénarios de test dédiés :

| Code | Énoncé officiel du sujet / règle étendue | Application en base (`sql/`) et en PHP | Scénario associé |
|---|---|---|---|
| **RB-01** | L’adresse e-mail d’un client doit être unique. | Contrainte `UNIQUE KEY uk_client_email` + vérification `sp_create_account` / `sp_update_client` | `T-04`, `F-07` |
| **RB-02** | Le prix d’un produit doit être strictement positif. | `CHECK ck_produit_prix (prix_ht > 0)` | `T-01` |
| **RB-03** | Le stock ne peut jamais être négatif. | `CHECK ck_produit_stock (stock >= 0)` + `trg_produit_regles` + `sp_adjust_stock` | `T-02`, `T-03`, `T-19` |
| **RB-04** | Une commande validée doit contenir au moins une ligne. | Vérification dans `sp_confirm_order` + `trg_commande_delete` | `T-16`, `T-24` |
| **RB-05** | La quantité commandée doit être strictement supérieure à zéro. | `CHECK ck_ligne_quantite (quantite > 0)` + `sp_add_order_line` | `T-06`, `T-07` |
| **RB-06** | Le prix enregistré dans `LIGNE_COMMANDE` correspond au prix au moment de l’achat. | `trg_ligne_prix_snapshot` (`BEFORE INSERT`) + `trg_ligne_immutable` (`BEFORE UPDATE`) | `T-11`, `T-23`, `F-17` |
| **RB-07** | Un produit appartient à une seule catégorie. | `produit.id_categorie NOT NULL` + FK `ON DELETE RESTRICT` | `T-14` |
| **RB-08** | Une catégorie peut contenir zéro ou plusieurs produits. | Cardinalité `(0,N)` côté `CATEGORIE` (catégorie vide autorisée) | `F-01` |
| **RB-09** | Un client peut passer plusieurs commandes (historique préservé). | `commande.id_client` FK `RESTRICT`, suppression produit déjà vendu bloquée (`visible = 0`) | `T-15`, `T-20` |
| **RB-10** | Une commande appartient à un seul client. | `commande.id_client NOT NULL` + contrôle d’appartenance `sp_confirm_order` / `CommandeController` | `T-17`, `T-18` |
| **RB-11** | Une commande suit une machine à états contrainte : `BROUILLON`, `EN_PREPARATION`, `EXPEDIEE`, `LIVREE`, `ANNULEE`, `ANNULEE_APRES_EXPEDITION`. | Matrice de transitions dans `trg_commande_transition_statut` + journal `order_status_history` | `T-12`, `T-13`, `T-22` |
| **RB-12…20** | *Extensions :* hachage bcrypt 8 car. min. (`RB-12`), séparation `SUPER`/`GESTIONNAIRE` (`RB-13`), immutabilité `montant_total` (`RB-15`), calcul auto `prix_ttc` (`RB-16`), refus produits masqués (`RB-17`/`RB-19`), anti-survente (`RB-18`), frais de port `4,90 EUR` / gratuit `>= 80 EUR` (`RB-20`). | Triggers blocs B/D, `sp_compute_shipping`, `fn_param`, `PanierSession` | `T-05`, `T-08`, `T-10`, `T-28`, `T-29` |

*Tableau 3 — Réponse détaillée aux 11 contraintes métier officielles et aux règles complémentaires.*

### 1.4 Contraintes techniques (`CT-01` à `CT-12`) et justification des choix technologiques

Le sujet impose de détailler les contraintes techniques et l’architecture du système. Le Tableau 4 récapitule nos choix techniques face aux alternatives de l’état de l’art :

| Dimension | Contrainte / Choix retenu (`CT-01`…`CT-12`) | Justification académique face aux alternatives |
|---|---|---|
| **Langage serveur** | **PHP 8.2+** en typage strict (`declare(strict_types=1)`), POO sans framework tiers | Évite la « boîte noire » d’un framework lourd (Symfony/Laravel) : chaque ligne de routage, session, CSRF et PDO est auditable, tout en gardant `0` dépendance externe en production (`p95 = 6 ms`). |
| **SGBD & Pilote** | **MySQL 8.0.16+ / MariaDB 10.6+** (InnoDB, `utf8mb4_unicode_ci`) via **PDO** (`STORAGE_DRIVER=sql` par défaut) | Garantit les propriétés ACID, les verrous de ligne `SELECT ... FOR UPDATE` et l’exécution native des 18 procédures et 16 triggers. |
| **Portabilité secours** | Contrat `StoreInterface` + pilote secondaire **`JsonStore`** (`STORAGE_DRIVER=json`) | Permet la démonstration et le test hors ligne sans SGBD, contrairement à un couplage ORM strict, tout en bornant ses limites de concurrence multi-fichiers. |
| **Front-end & Temps** | HTML5 sémantique, CSS3 adaptatif (`320`–`1440 px`), JS natif progressif, stockage **UTC** | Amélioration progressive : **100 % des parcours restent utilisables sans JavaScript** (`ENF-07`). |
| **Hébergement cible** | Apache 2.4 (`mod_rewrite`, `DocumentRoot public/`) + routeur CLI `scripts/dev-router.php` | Isole `app/`, `sql/`, `var/` et `.env` hors de la racine Web publique. |

*Tableau 4 — Contraintes techniques et arbitrage technologique.*

---

## Chapitre 2 — Modélisation UML 2.5 et spécifications de sécurité

### 2.1 Diagramme de cas d’utilisation et les 14 cas d’utilisation minimum

Conformément à la liste minimale des 14 cas d’utilisation exigés en page 3 du sujet, la couverture fonctionnelle de MiniShop est présentée dans le Tableau 5 et illustrée sur le périmètre transactionnel par la Figure 1.

| N° sujet | Cas d’utilisation minimum exigé | ID interne | Acteur principal | Point d’entrée / Preuve |
|---:|---|---|---|---|
| 1 | Consulter le catalogue | `UC-01` | Visiteur | `CatalogueController::index` (`/catalogue`, `/categories`) |
| 2 | Rechercher un produit | `UC-02` | Visiteur | `CatalogueController::recherche` (`/recherche`, `sp_search_products`) |
| 3 | Consulter un produit | `UC-02` | Visiteur | `CatalogueController::detail` (`/produit/{slug}`) |
| 4 | Créer un compte | `UC-04` | Visiteur | `AuthController::inscription` (`/inscription`, `sp_create_account`) |
| 5 | Se connecter | `UC-04` | Client / Admin | `AuthController::connexion` & `DashboardController::connexion` |
| 6 | Gérer le panier | `UC-03` | Visiteur / Client | `PanierController` + `ApiController` (`/panier`, `/api/panier/*`) |
| 7 | Passer une commande | `UC-06` | Client | `CommandeController::valider` (`sp_create_order_from_basket`) |
| 8 | Consulter l’historique des commandes | `UC-05` | Client | `CommandeController::historique` (`/mes-commandes`) |
| 9 | Annuler une commande | `UC-07` | Client / Admin | `CommandeController::annuler` (`sp_cancel_order`) & `sp_cancel_order_admin` |
| 10 | Gérer les produits | `UC-09` | Administrateur | `ProduitAdminController` (`/admin/produits/*`, `sp_save_product`) |
| 11 | Gérer les catégories | `UC-10` | Administrateur | `CategorieAdminController` (`/admin/categories/*`, `sp_save_category`) |
| 12 | Gérer les stocks | `UC-11` | Administrateur | `StockAdminController` (`/admin/stocks/*`, `sp_adjust_stock`) |
| 13 | Gérer les commandes | `UC-12` | Administrateur | `CommandeAdminController` (`/admin/commandes/*`) |
| 14 | Modifier le statut d’une commande | `UC-12` | Administrateur | `CommandeAdminController::statut` (`sp_update_order_status`) |

*Tableau 5 — Couverture des 14 cas d’utilisation minimum imposés par le sujet (complétés par `UC-08` Tableau de bord, `UC-13` Équipe `SUPER` et `UC-14` Paramètres).*

![Diagramme de cas d’utilisation du processus de commande](docs/diagrams/cas_utilisation_commandes.png)

*Figure 1 — Diagramme de cas d’utilisation ciblé sur le cycle de vie des commandes et les acteurs.*

### 2.2 Descriptions textuelles détaillées de 3 cas d’utilisation (hors « Passer une commande »)

Le sujet officiel (p. 3) demande explicitement : *« Choisir 3 cas d’utilisation (non pour cas d’utilisation ci-dessous [Passer une commande]) et détailler par description textuelle »*. Nous détaillons ci-dessous trois cas d’utilisation majeurs couvrant respectivement le front-office, le client authentifié et le back-office :

#### Cas d’utilisation détaillé n°1 — Rechercher et filtrer des produits (`UC-01` / `UC-02`)
- **Acteur principal :** Visiteur (ou Client).
- **Préconditions :** le catalogue contient des catégories et des produits ; seuls les articles marqués `visible = 1` doivent être exposés (`RB-19`).
- **Scénario nominal :**
  1. Le visiteur accède à `/catalogue` ou soumet le champ de recherche (`/recherche?q=...`).
  2. Le visiteur sélectionne éventuellement une catégorie (`cat`), un prix TTC maximal (`prix_max`), le filtre disponibilité en stock (`stock=1`) et un critère de tri (`tri`).
  3. Le contrôleur `CatalogueController` assainit les paramètres, borne la pagination côté serveur (maximum `60` articles par page) et sollicite `ProduitRepository` (`sp_search_products` en mode SQL).
  4. Le SGBD retourne les produits visibles correspondants (via l’index `idx_produit_visible` et `v_catalogue`) ainsi que le nombre total de résultats.
  5. Le système affiche la grille de produits échappée (`e()`), avec prix TTC, état du stock et liens de pagination conservant l’état des filtres dans l’URL.
- **Scénarios alternatifs :**
  - *A1 (aucun résultat) :* la requête renvoie un ensemble vide ; la vue affiche un message neutre et un lien de réinitialisation des filtres sans erreur SQL.
  - *A2 (page hors bornes, ex. `page=999`) :* le serveur borne la page ou affiche un état vide propre permettant le retour à la page 1.
  - *A3 (tentative d’injection SQL dans `q` ou `tri`) :* le critère de tri passe par une liste blanche stricte et le mot-clé est transmis en paramètre lié PDO, neutralisant toute injection (`SEC-01`).

#### Cas d’utilisation détaillé n°2 — Annuler une commande côté client (`UC-07`)
- **Acteur principal :** Client authentifié.
- **Préconditions :** le client est connecté (`$_SESSION['client_id']` valide) ; la commande ciblée existe, lui appartient (`RB-10`), et se trouve à un statut annulable avant expédition (`EN_PREPARATION` ou `BROUILLON`).
- **Scénario nominal :**
  1. Depuis `/mes-commandes/{id}`, le client clique sur « Annuler la commande ».
  2. Le système affiche une page dédiée de confirmation en deux étapes (fonctionnant même sans JavaScript, `ENF-07`) rappelant le numéro de commande et incluant un jeton CSRF.
  3. Le client confirme l’annulation (`POST /mes-commandes/{id}/annuler`).
  4. `CommandeController` vérifie le jeton CSRF (`hash_equals`) puis appelle `CommandeRepository::cancelOrder()` → `sp_cancel_order(id_commande, id_client)`.
  5. La procédure contrôle en transaction l’appartenance client et le statut, passe la commande à `ANNULEE`, remet `frais_port = 0` et `montant_total = 0` (tout en conservant l’historique des lignes dans `ligne_commande`), et restitue le stock des produits via la transaction/déclencheurs.
  6. `trg_history_statut` enregistre automatiquement la transition `EN_PREPARATION -> ANNULEE` dans `order_status_history`, puis le client est redirigé avec un message de confirmation humain.
- **Scénarios alternatifs :**
  - *A1 (IDOR — commande appartenant à un autre client) :* `CommandeController` et `sp_cancel_order` rejettent l’accès et retournent une page `404` neutre sans révéler l’existence de la commande (`SEC-08`).
  - *A2 (commande déjà `EXPEDIEE`, `LIVREE` ou `ANNULEE`) :* la matrice de transition `RB-11` (`trg_commande_transition_statut`) refuse la mutation et retourne un message métier clair sans restituer de stock.
  - *A3 (double soumission concurrente) :* le verrouillage transactionnel empêche une double restitution de stock.

#### Cas d’utilisation détaillé n°3 — Ajuster manuellement le stock d’un produit (`UC-11`)
- **Acteur principal :** Administrateur (`GESTIONNAIRE` ou `SUPER`).
- **Préconditions :** l’administrateur est authentifié (`Auth::exigeAdmin()`) ; le produit cible existe en base.
- **Scénario nominal :**
  1. L’administrateur ouvre `/admin/stocks` (alimenté par la vue `v_etat_stock` classant les articles en `RUPTURE`, `TRES_BAS` ou `DISPONIBLE`).
  2. Il sélectionne un produit, choisit le mode d’ajustement par défaut non destructif **`DELTA`** (« Ajouter / retirer ») ou `SET` (fixation directe), saisit la quantité et sélectionne/renseigne un **motif obligatoire** (ex. `REASSORT`, `INVENTAIRE`, `CASSE`).
  3. Il soumet le formulaire avec jeton CSRF valide.
  4. `StockAdminController` appelle `sp_adjust_stock` qui verrouille la ligne produit (`FOR UPDATE`), calcule le nouveau stock, vérifie `stock_apres >= 0` et enregistre dans la même transaction une trace inaltérable dans `stock_movement_history` (auteur, rôle, stocks avant/après, motif, horodatage UTC).
  5. Le tableau des alertes est actualisé et un message flash confirme le nouveau niveau de stock.
- **Scénarios alternatifs :**
  - *A1 (passage en stock négatif, ex. retrait de `10` unités sur un stock de `3`) :* `sp_adjust_stock` et `trg_produit_regles` refusent l’opération (`RB-03`) avec un message utilisateur lisible ; aucun mouvement n’est journalisé.
  - *A2 (motif absent ou accès par un non-administrateur) :* le routeur bloque l’accès (`403`) pour un non-admin ; le contrôleur rejette tout ajustement sans motif.

*(Note : le cas d’utilisation `Passer une commande` est quant à lui modélisé dynamiquement par le diagramme de séquence Figure 6 et l’automate Figure 10.)*

### 2.3 Diagramme de classes et justification des choix de conception objet

Le sujet demande de définir les classes, attributs, méthodes principales, associations, cardinalités et de **justifier les choix de conception**.

![Diagramme de classes de l’application PHP MiniShop](docs/diagrams/diagramme_classes.png)

*Figure 2 — Diagramme de classes simplifié : contrôleurs, dépôts métier, sécurité et abstraction de persistance.*

**Justification des choix de conception orientée objet :**
1. **Séparation `Controller` / `Repository` / `StoreInterface` :** les contrôleurs (`15` classes sous `App\Controller`) gèrent exclusivement HTTP, CSRF et sélection de vue ; les dépôts (`ProduitRepository`, `CommandeRepository`, `ClientRepository`, `CategorieRepository`, `AdminRepository`, `ParametreRepository`) portent les règles métier ; `StoreInterface` isole les requêtes SQL (`SqlStore`) du moteur `JsonStore`.
2. **Absence d’ORM lourd au profit de tableaux/DTOs typés et de `BusinessError` :** permet d’exploiter directement les résultats des procédures stockées (`CALL sp_*`) sans conflit de cache d’entités ni hydratation coûteuse, tout en traduisant les codes d’erreur SGBD en exceptions métier `BusinessError` affichables proprement à l’utilisateur.
3. **Abstraction de session panier (`PanierSession`) :** ne stocke en session PHP que les couples `{id_produit -> quantite}` ; les prix, stocks disponibles, TVA et frais de port sont systématiquement relus côté serveur à chaque affichage ou validation.

### 2.4 Les 4 diagrammes de séquences (authentification, recherche, panier, commande)

Le cahier des charges (p. 4) exige au moins **4 diagrammes de séquences** intégrant procédure stockée et déclencheur lorsque pertinent :

![Diagramme de séquence — Authentification](docs/diagrams/seq_authentification.png)

*Figure 3 — Séquence d’authentification : récupération du hash via `sp_get_credentials`, vérification `password_verify()` en PHP et régénération de session.*

![Diagramme de séquence — Rechercher un produit](docs/diagrams/seq_recherche_produit.png)

*Figure 4 — Séquence de recherche et filtrage multicritères via `sp_search_products` et échappement `e()`.*

![Diagramme de séquence — Ajout au panier](docs/diagrams/seq_ajout_panier.png)

*Figure 5 — Séquence d’ajout au panier avec vérification serveur de la visibilité (`RB-19`) et plafonnement au stock réel (`RB-18`).*

![Diagramme de séquence — Passer une commande](docs/diagrams/seq_passer_commande.png)

*Figure 6 — Séquence transactionnelle de validation de commande : `sp_create_order_from_basket`, `SELECT ... FOR UPDATE`, calcul du port (`sp_compute_shipping`) et triggers de stock/historique.*

### 2.5 Réponses aux 5 spécifications de sécurité du cahier des charges

Le cahier des charges officiel (p. 4–5) identifie cinq points critiques à démontrer explicitement :

| Exigence du sujet (p. 4–5) | Règle imposée par le professeur | Implémentation exacte dans MiniShop & preuve |
|---|---|---|
| **1. SQL Injection** | Utiliser `PDO::prepare()` et `PDO::execute()`, interdire toute concaténation de paramètres. | `SqlStore` prépare 100 % des requêtes et appels `CALL sp_*` avec `PDO::ATTR_EMULATE_PREPARES => false` ; les noms de colonnes de tri passent par liste blanche. Vérifié par `controles.sh [01/11]`. |
| **2. Mots de passe** | Interdire les mots de passe en clair (type `password = "123456"`), exiger `password_hash()` et `password_verify()`. | Création et changement via `password_hash(..., PASSWORD_BCRYPT)` (≥ 8 car.) ; vérification temps constant côté PHP via `password_verify()` sur le hash retourné par `sp_get_credentials`. Vérifié par `[04/11]`. |
| **3. XSS** | Échapper toutes les données affichées avec `htmlspecialchars()`. | Fonction globale `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) appliquée sur 100 % des variables affichées dans `app/View/`, doublée d’une `Content-Security-Policy` stricte. Vérifié par `[02/11]`. |
| **4. Sessions** | Les pages privées doivent vérifier la session utilisateur (`$_SESSION['user_id']`). | `Auth::exigeClient()` contrôle `$_SESSION['client_id']` et `Auth::exigeAdmin()` contrôle `$_SESSION['admin_id']` (deux espaces cloisonnés) ; cookies `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1` et `session_regenerate_id(true)`. Vérifié par `[06/11]`. |
| **5. Autorisation (IDOR)** | Un client ne doit pas accéder à une commande tierce en modifiant l’ID dans l’URL (`commande.php?id=25`). | `CommandeController` exige `id_client = Auth::clientId()` pour afficher `/mes-commandes/{id}` ; `sp_confirm_order` et `sp_cancel_order` re-vérifient `id_client` en SQL. Toute tentative tierce renvoie une `404` neutre. Vérifié par `[05/11]` & `T-18`. |
| **Complément CSRF & Back-office** | Protection des mutations et accès admin. | Jeton CSPRNG (`random_bytes(32)`) vérifié par `hash_equals()` sur les `12` contrôleurs POST mutatifs (`[07/11]`) ; zéro inscription admin publique (`[08/11]`) ; `X-Frame-Options: DENY`. |

*Tableau 6 — Réponses vérifiées aux 5 spécifications de sécurité du sujet officiel.*

### 2.6 Architecture 3 couches MVC adaptée au système

MiniShop met en œuvre une architecture **3 couches logiques MVC** avec front controller unique (`public/index.php`) et routeur à liste blanche (`app/Router.php`) appliquant les gardes `public`, `client`, `admin` et `super` avant toute exécution de contrôleur :

![Architecture 3 couches MVC et double persistance de MiniShop](docs/diagrams/architecture_mvc.png)

*Figure 7 — Architecture MVC pragmatique : présentation (`public/`, `app/View/`), logique applicative (`Controller`, `Repository`, `Security`) et persistance duale (`SqlStore` / `JsonStore`).*

---

## Chapitre 3 — Analyse et conception de la base de données (Merise, Normalisation et SQL)

### 3.1 Modèle Conceptuel des Données (MCD) : entités, associations et cardinalités justifiées

Le MCD comporte **9 entités** (`CLIENT`, `ADMINISTRATEUR`, `CATEGORIE`, `PRODUIT`, `COMMANDE`, `LIGNE_COMMANDE`, `ORDER_STATUS_HISTORY`, `PARAMETRE`, `STOCK_MOVEMENT_HISTORY`) reliées par 6 associations métier et d’audit.

![Modèle Conceptuel des Données MiniShop à 9 entités](docs/diagrams/mcd_minishop.png)

*Figure 8 — Modèle Conceptuel des Données (MCD) complet à 9 entités.*

| Association | Entités & Cardinalités | Justification métier et structurelle | Traduction DDL |
|---|---|---|---|
| `PASSER` | `CLIENT` `(0,N)` — `COMMANDE` `(1,1)` | Un client inscrit peut ne pas avoir encore commandé ou passer N commandes ; toute commande appartient à un unique client (`RB-09`, `RB-10`). | `id_client NOT NULL`, `FK ON DELETE RESTRICT` |
| `COMPORTER` | `COMMANDE` `(1,N)` — `LIGNE_COMMANDE` `(1,1)` | Une ligne n’existe que dans une commande ; une commande validée contient au moins une ligne (`RB-04`). | `id_commande NOT NULL`, `FK ON DELETE CASCADE` (brouillons) |
| `ETRE VENDU DANS` | `PRODUIT` `(0,N)` — `LIGNE_COMMANDE` `(1,1)` | Un nouveau produit peut n’avoir aucune vente ; une ligne référence obligatoirement un unique produit. | `id_produit NOT NULL`, `FK ON DELETE RESTRICT` |
| `CLASSER` | `CATEGORIE` `(0,N)` — `PRODUIT` `(1,1)` | Une catégorie peut être créée vide (`RB-08`) ; chaque produit appartient à une seule catégorie (`RB-07`). | `id_categorie NOT NULL`, `FK ON DELETE RESTRICT` |
| `ETRE TRACÉE DANS` | `COMMANDE` `(0,N)` — `ORDER_STATUS_HISTORY` `(1,1)` | Chaque création ou transition de statut génère une trace horodatée rattachée à sa commande. | `order_id NOT NULL`, `FK ON DELETE CASCADE` |

*Tableau 7 — Justification complète des cardinalités du MCD.*

**Justification de deux choix structurants du MCD :**
1. **Dissociation `CLIENT` / `ADMINISTRATEUR` :** leurs attributs (adresse postale vs rôle `SUPER`/`GESTIONNAIRE`) et cycles de vie étant disjoints, deux tables séparées empêchent structurellement toute escalade de privilège depuis l’inscription publique (`SEC-10`).
2. **Table `STOCK_MOVEMENT_HISTORY` sans clé étrangère bloquante :** conserve par *snapshot* (`id_produit`, `reference_produit`, `nom_produit`) l’audit des mouvements manuels (`SET`/`DELTA`, stocks avant/après, motif, auteur) même si un article non vendu est ultérieurement supprimé.

### 3.2 Modèle Logique des Données (MLD) et vues relationnelles

![Modèle Logique des Données MiniShop](docs/diagrams/mld_minishop.png)

*Figure 9 — Modèle Logique des Données (MLD) issu de la dérivation du MCD.*

Trois **vues SQL** complètent le MLD pour centraliser les calculs récurrents de lecture : `v_etat_stock` (classification unifiée `RUPTURE` / `TRES_BAS` / `DISPONIBLE`), `v_catalogue` (produits visibles `visible = 1` enrichis de leur catégorie et état de stock) et `v_commandes_client` (synthèse commandes par client).

### 3.3 Normalisation complète : dépendances fonctionnelles, 1FN/2FN/3FN, PK/FK et redondances

En réponse directe aux **cinq points imposés en partie A.iii du sujet (p. 6)**, nous démontrons successivement les dépendances fonctionnelles, les trois premières formes normales, le choix des clés et le traitement des redondances.

#### 1. Dépendances fonctionnelles identifiées (DF)

| Relation | Dépendances fonctionnelles non triviales (`X -> Y`) | Observations |
|---|---|---|
| `CLIENT` | `id_client -> nom, prenom, email, mot_de_passe_hash, telephone, adresse_livraison, code_postal, ville, actif, date_creation, derniere_connexion` ; `email -> id_client` | `email` est clé candidate (`RB-01`). |
| `ADMINISTRATEUR` | `id_admin -> nom, prenom, email, mot_de_passe_hash, role, actif, date_creation, derniere_connexion` ; `email -> id_admin` | `email` est clé candidate. |
| `CATEGORIE` | `id_categorie -> nom, slug, description` ; `nom -> id_categorie` ; `slug -> id_categorie` | `nom` et `slug` sont clés candidates. |
| `PRODUIT` | `id_produit -> reference, nom, slug, description, prix_ht, tva, stock, stock_initial, seuil_alerte, visible, image_url, id_categorie, ...` ; `reference -> id_produit` ; `slug -> id_produit` ; `(prix_ht, tva) -> prix_ttc` (dérivée) | `(prix_ht, tva) -> prix_ttc` est maintenue par triggers `BEFORE INSERT/UPDATE`. |
| `COMMANDE` | `id_commande -> numero, id_client, adresse_livraison, statut, frais_port, montant_total, montant_avant_annulation, commentaire, date_commande, date_modification` ; `numero -> id_commande` | `adresse_livraison` et `frais_port` dépendent de `id_commande` (figées à l’instant de commande), non de `id_client`. |
| `LIGNE_COMMANDE` | `id_ligne -> id_commande, id_produit, quantite, prix_unitaire, total_ligne` | `prix_unitaire` dépend de `id_ligne` (snapshot figé à l’achat `RB-06`), et non plus de `id_produit`. |
| `ORDER_STATUS_HISTORY` | `id -> order_id, old_status, new_status, changed_by, changed_by_role, commentaire, changed_at` | Clé technique `id` car deux événements tests peuvent survenir dans la même seconde. |
| `PARAMETRE` | `cle -> valeur, unite, comment, date_modification` | `cle` est la clé naturelle directe. |
| `STOCK_MOVEMENT_HISTORY` | `id_mouvement -> id_produit, reference_produit, nom_produit, mode_mouvement, quantite, stock_avant, stock_apres, motif, changed_by, changed_by_role, changed_at` | Journal d’audit immuable en écriture seule. |

*Tableau 8 — Dépendances fonctionnelles identifiées sur l’ensemble des 9 tables.*

#### 2. Vérification formelle 1FN, 2FN et 3FN

| Table | 1FN | 2FN | 3FN | Démonstration |
|---|---|---|---|---|
| `CLIENT` / `ADMINISTRATEUR` | **OUI** | **OUI** | **OUI** | Attributs atomiques (adresse décomposée en `adresse_livraison`, `code_postal`, `ville`) ; clé primaire simple ; aucune DF `ville -> code_postal` valide en France (plusieurs communes partagent un CP). |
| `CATEGORIE` / `PARAMETRE` / `HISTORIES` | **OUI** | **OUI** | **OUI** | Attributs atomiques, clé primaire mono-attribut, aucune dépendance transitive. |
| `PRODUIT` | **OUI** | **OUI** | **3FN (avec dénormalisation verrouillée)** | `prix_ttc` dépend transitivement de `prix_ht` et `tva`. Conservé pour permettre le filtrage indexé (`ENF-01`), mais **neutralisé par `trg_produit_ttc` et `trg_produit_ttc_update`** (équivalent à une colonne générée), rendant toute anomalie de mise à jour impossible. |
| `COMMANDE` | **OUI** | **OUI** | **3FN (avec dénormalisation verrouillée)** | `montant_total` est un agrégat des lignes + `frais_port`. Conservé comme montant contractuel facturé et **vérifié par `trg_commande_transition_statut`** (`RB-15`), empêchant toute désynchronisation. |
| `LIGNE_COMMANDE` | **OUI** | **OUI** | **OUI** | Une clé composée `(id_commande, id_produit)` aurait violé la 2FN (dépendance du prix à `id_produit` seul) et empêché deux lignes distinctes d’un même article. La clé de substitution `id_ligne` + le snapshot `prix_unitaire` assurent strictement la 3FN. |

*Tableau 9 — Démonstration de normalisation 1FN / 2FN / 3FN.*

#### 3. & 4. Justification des clés primaires (PK) et clés étrangères (FK)

| Clé | Choix retenu | Justification | Risque évité |
|---|---|---|---|
| **PK** `id_client`, `id_admin`, `id_categorie`, `id_produit`, `id_commande`, `id_ligne` | Entiers auto-incrémentés (clés de substitution) + `UK` sur `email`, `reference`, `slug`, `numero` | Des clés naturelles (`email`, `slug`) peuvent être corrigées ; des entiers courts optimisent jointures et index InnoDB tout en préservant l’unicité métier via `UNIQUE KEY`. | Évite de propager une modification d’e-mail sur toutes les FK de `COMMANDE`. |
| **FK** `produit.id_categorie` | `NOT NULL`, `ON DELETE RESTRICT`, `ON UPDATE CASCADE` | Tout produit appartient à une catégorie existante (`RB-07`) et une catégorie occupée ne peut être supprimée (`RB-14`). | Empêche les produits orphelins ou l’effacement accidentel d’un rayon entier. |
| **FK** `commande.id_client` | `NOT NULL`, `ON DELETE RESTRICT` | Toute commande est rattachée à un client (`RB-10`) et son historique comptable doit survivre (`RB-09`). | Empêche la perte d’historique lors de la suppression d’un compte. |
| **FK** `ligne_commande.id_commande` | `NOT NULL`, `ON DELETE CASCADE` | Identification faible : la ligne suit la vie d’un brouillon purgé (`T-24`), tandis que `trg_commande_delete` interdit la suppression d’une commande validée. | Permet la purge propre des brouillons vides sans orphelins. |
| **FK** `ligne_commande.id_produit` | `NOT NULL`, `ON DELETE RESTRICT` | Empêche la suppression physique d’un article déjà commandé (`RB-14`, `T-15`) ; `sp_delete_product` bascule alors `visible = 0`. | Préserve l’intégrité des factures historiques. |
| **FK** `order_status_history.order_id` | `NOT NULL`, `ON DELETE CASCADE` | La trace d’audit accompagne sa commande (non supprimable après validation). | Cohérence stricte de la piste d’audit. |

*Tableau 10 — Justification des clés primaires, uniques et étrangères.*

#### 5. Redondances identifiées et contrôles de non-contradiction

| Donnée redondante / dérivée | Nature | Pourquoi elle est conservée | Mécanisme empêchant toute incohérence |
|---|---|---|---|
| `PRODUIT.prix_ttc` | Dérivée (`prix_ht`, `tva`) | Filtrage catalogue et tri par prix TTC indexés (`ENF-01`) | Triggers `trg_produit_ttc` et `trg_produit_ttc_update` |
| `LIGNE_COMMANDE.prix_unitaire` | Copie historique (`RB-06`) | Immutabilité du prix facturé si le catalogue évolue | `trg_ligne_prix_snapshot` + `trg_ligne_immutable` |
| `LIGNE_COMMANDE.total_ligne` | Dérivée (`quantite * prix_unitaire`) | Lecture rapide des factures | `trg_ligne_prix_snapshot` + `trg_ligne_immutable` |
| `COMMANDE.montant_total` | Agrégat (`SUM(total_ligne) + frais_port`) | Valeur contractuelle figée (`RB-15`) | `trg_commande_transition_statut` + `sp_confirm_order` |
| `COMMANDE.adresse_livraison` | Copie historique (`CLIENT`) | Conserver l’adresse de livraison réelle même si le client déménage | Figée à la création de la commande |

*Tableau 11 — Traitement et verrouillage des redondances.*

### 3.4 Script SQL d’installation et jeu de données (`sql/01` à `sql/04`)

La base est construite de manière reproductible par quatre scripts ordonnés :
- `sql/01_minishop_schema.sql` : création de la base (`utf8mb4_unicode_ci`), des **9 tables**, des index (`FULLTEXT(nom, description)`, `idx_produit_visible`, `idx_commande_client`, `idx_commande_statut`), des **3 vues**, et insertion du jeu de référence : **4 catégories**, **36 produits** (dont `POWER-010`, `POWER-030`, `MONTRE-032` en rupture `stock = 0`, `MICRO-007` au seuil d’alerte et `BRAC-012` masqué `visible = 0`), **3 clients**, **1 administrateur `SUPER`** (mots de passe hachés par `password_hash()`) et **4 paramètres** (`frais_port = 4.90`, `franchise_port = 80.00`, `tva_defaut = 20.00`, `conservation_compte = 24`).
- `sql/02_minishop_procedures.sql` : **1 fonction** (`fn_param`) et **18 procédures stockées**.
- `sql/03_minishop_triggers.sql` : **16 déclencheurs**.
- `sql/04_minishop_demo.sql` : création de **4 commandes de démonstration** par appels réels `CALL sp_*` (deux `EN_PREPARATION` dont une bénéficiant de la franchise de port et une facturant `4,90 EUR`, un brouillon vide, et une `EXPEDIEE`), prouvant à chaud le fonctionnement des déclencheurs dès l’installation.

### 3.5 Procédures stockées (18 livrées) et réponse à la question pédagogique du sujet

Le sujet impose au minimum `5` procédures stockées ; **18 procédures** et **1 fonction** (`fn_param`) sont livrées et appelées par `app/Repository/` (inventaire détaillé en Annexe B).

#### Réponse complète à la question pédagogique du sujet (p. 7)
> *« Pourquoi utiliser une procédure stockée dans ce cas plutôt que réaliser toutes les opérations directement en PHP ? »*

| Aspect imposé par le sujet | Apport décisif de la procédure stockée (côté MySQL/MariaDB) | Conséquence si tout était codé uniquement en PHP |
|---|---|---|
| **1. Centralisation de la logique** | `sp_create_order_from_basket`, `sp_confirm_order` et `sp_adjust_stock` portent une source unique de vérité (verrou -> contrôle stock -> calcul port -> écriture -> historique) partagée par le front-office, le back-office et tout futur import CLI/CSV. | Duplication des règles dans plusieurs contrôleurs/scripts, avec risque majeur qu’un nouveau parcours oublie le snapshot de prix (`RB-06`) ou le calcul du port (`RB-20`). |
| **2. Sécurité** | Défense en profondeur : en production, le compte SQL applicatif peut ne disposer que du privilège `EXECUTE` sur les procédures et `SELECT` sur les vues, sans droit `UPDATE/DELETE` direct sur `produit` ou `commande`. | Une faille applicative exposerait directement les tables critiques à des altérations arbitraires de stock ou de montants. |
| **3. Transactions** | Les instructions `START TRANSACTION` / `COMMIT` / `ROLLBACK` et les verrous `SELECT ... FOR UPDATE` s’exécutent de façon atomique au plus près des données sur le serveur SGBD, insensibles aux déconnexions ou timeouts réseau intermédiaires du script PHP. | Un crash PHP entre l’insertion de la commande et la décrémentation du stock (ou un `beginTransaction()` omis) provoquerait une corruption silencieuse des stocks. |
| **4. Réutilisabilité** | `sp_search_products` sert indifféremment le visiteur (`p_admin = 0`) et l’administrateur (`p_admin = 1`) ; `sp_compute_shipping` est invoquée par le panier comme par la confirmation finale sans réécriture. | Divergence progressive des formules d’alerte ou de port entre les différents modules PHP. |
| **5. Contrôle des accès** | Les procédures d’écriture critique vérifient leurs propres préconditions (`sp_confirm_order` contrôle l’appartenance `id_client`, `sp_delete_category` refuse toute catégorie occupée), formant un second rempart indépendant du code PHP. | La sécurité reposerait exclusivement sur la rigueur de chaque action HTTP appelante. |

*Tableau 12 — Réponse aux 5 critères de la question pédagogique sur les procédures stockées.*

**Nuance critique d’ingénierie (limites assumées) :** nous réservons les procédures stockées aux **opérations d’écriture multi-tables et décisions critiques**, tout en conservant les lectures simples en requêtes préparées PDO. Nous assumons également le coût de cette approche : couplage plus fort au dialecte MySQL/MariaDB, débogage moins outillé qu’en PHP et nécessité de maintenir la parité avec le pilote secondaire `JsonStore`.

| Exemple du sujet officiel | Objet livré dans MiniShop | Explication de correspondance |
|---|---|---|
| `sp_create_order` | `sp_create_order` | Nom et rôle exacts (+ génération numéro `CMDAAAA-NNNNNN`). |
| `sp_add_order_item` | `sp_add_order_line` (+ `sp_create_order_from_basket`) | Adapté en `_line` par cohérence avec la table française `ligne_commande`. |
| `sp_update_order_status` | `sp_update_order_status` | Nom et rôle exacts (+ matrice `RB-11` et auteur tracé). |
| « Trigger 1 — Stock » | `trg_ligne_decrement_stock` + `trg_ligne_controle_insert` + `trg_ligne_restore_stock` | Décrémente à l’insertion, bloque avant négatif et restitue à l’annulation. |
| « Trigger 2 — Historique » (`ORDER_STATUS_HISTORY`) | `trg_history_statut` (`AFTER UPDATE`) + `trg_history_creation` (`AFTER INSERT`) | Table `order_status_history(id, order_id, old_status, new_status, changed_at, ...)` au format exact du sujet. |

*Tableau 13 — Correspondance nominative entre les exemples du sujet et les objets SQL livrés.*

### 3.6 Déclencheurs (16 livrés) : couverture de « Trigger 1 — Stock » et « Trigger 2 — Historique »

Le sujet impose au minimum `5` déclencheurs ; **16 triggers** (Annexe B) protègent l’intégrité des données même lors d’écritures SQL directes :
- **Couverture « Trigger 1 — Stock » :** `trg_ligne_controle_insert` (`BEFORE INSERT`) vérifie la disponibilité et lève `STOCK_INSUFFISANT` si besoin ; `trg_ligne_decrement_stock` (`AFTER INSERT`) soustrait `quantite` du stock produit ; `trg_ligne_restore_stock` (`AFTER DELETE`) restitue le stock lors de l’annulation avant expédition ou purge de brouillon.
- **Couverture « Trigger 2 — Historique » :** sur la table `order_status_history`, `trg_history_creation` enregistre la trace initiale `NULL -> BROUILLON` et `trg_history_statut` trace automatiquement tout changement de statut avec l’auteur et son rôle, en conformité avec l’automate de la Figure 10.

![Automate des états et transitions d’une commande](docs/diagrams/etats_commande.png)

*Figure 10 — Automate à 6 états du cycle de vie d’une commande contrôlé par `trg_commande_transition_statut`.*

---

## Chapitre 4 — Application Web, protocole de validation expérimentale et performances

### 4.1 Réalisation PHP/PDO/JS, persistance duale `StoreInterface` et ergonomie

L’application expose **15 contrôleurs PHP** (Annexe A) s’appuyant sur des dépôts métier et le contrat `StoreInterface` (`SqlStore` par défaut via PDO ; `JsonStore` sur activation explicite `STORAGE_DRIVER=json`). À la suite de l’analyse architecturale (`ARCHITECTURE.md`), soulignons avec rigueur que si `JsonStore` assure une écriture atomique par fichier sous verrou coopératif `.minishop.lock`, seul `SqlStore` garantit la durabilité ACID multi-tables et l’isolation sous forte concurrence.

Côté interface (`docs/DA.md`), l’application fonctionne en **amélioration progressive** (panier AJAX via `ApiController` + repli POST complet sans JavaScript), s’adapte de `320` à `1440 px`, propose une traduction FR/EN (`public/js/i18n-en.js`) et stocke tous les horodatages en **UTC** en les projetant selon le fuseau horaire choisi localement par l’utilisateur (`public/js/tz-lookup.js`, sans conservation de coordonnées GPS).

### 4.2 Stratégie et document de tests : synthèses chiffrées sur VM Debian 13

Le document de tests et validation (`docs/02-document-tests-validation.md`) et les fiches par cas d’utilisation (`docs/13-fiches-recette-uc.md`) couvrent les tests unitaires, d’intégration SQL, E2E HTTP, de concurrence, de sécurité statique et d’audit structurel. La campagne de recette automatisée sur **VM Debian 13** (PHP `8.4.27`, MariaDB `11.8.6`, commit `f9b614f`, `docs/pv-recette.md`) complétée des contrôles statiques sur l’état courant donne les résultats suivants :

| Suite / Outil exécuté | Cible vérifiée | Résultat mesuré | Statut |
|---|---|---|---|
| **Campagne VM complète (`generer_pv.php`)** | Enchaînement des suites sur Debian 13 + MariaDB 11.8.6 | **60 / 60 étapes** (`0` anomalie) | **VALIDÉ** |
| **Intégration SQL (`tests/php/sql_integration.php`)** | Règles `T-01` à `T-29`, triggers, `CHECK`, rollbacks | **29 / 29 scénarios** | **VALIDÉ** |
| **E2E HTTP SQL (`tests/php/http_sql_e2e.php`)** | Parcours complets HTTP + PDO client & back-office | **105 / 105 assertions** | **VALIDÉ** |
| **Concurrence HTTP (`tests/php/concurrence_http.php`)** | 20 acheteurs simultanés pour 12 unités disponibles | **8 / 8 scénarios** (`0` survente) | **VALIDÉ** |
| **Tests unitaires PHPUnit (`tests/phpunit/`)** | Règles métier, panier, sécurité, rétention CLI | **57 / 57 tests** | **VALIDÉ** |
| **E2E PHP sur JSON (`tests/php/run_tests.php`)** | Parité fonctionnelle sur pilote secondaire `JsonStore` | **338 / 338 assertions** | **VALIDÉ** |
| **Sécurité statique (`tests/security/controles.sh`)** | 11 points (SQLi, XSS, secrets, bcrypt, IDOR, session, CSRF, RBAC, `.env`, PCI) | **11 / 11 conformes** | **CONFORME** |
| **Cohérence UC & audit (`verifier_fiches_uc.py`, `unittest`)** | 14 UC, 164 scénarios, 36 couples `RB-11`, inventaire Git | **46 / 46** UC + **22 / 22** audits Python | **CONFORME** |

*Tableau 14 — Synthèse des preuves de validation automatisées.*

### 4.3 Évaluation quantitative des performances (`ENF-01`) et plans d’exécution `EXPLAIN`

L’exigence `ENF-01` fixe un temps de réponse `< 500 ms` au 95e centile (`p95`). Sur la base de volumétrie cible `minishop_perf` (**236 produits**, **1 004 commandes**, **1 916 lignes**, **1 998 traces** — commit `cb0f814`, `tests/perf/dernieres_mesures.txt`), les mesures obtenues sont très largement conformes :

| Type de mesure | Requête / URL évaluée | Moyenne (ms) | p95 (ms) | Plan `EXPLAIN` / Observation |
|---|---|---:|---:|---|
| **SQL (100 itérations)** | Catalogue (`v_catalogue`, 12 lignes) | 19,8 | 20,6 | `range` sur `idx_produit_visible` (`< 500 ms`) |
| **SQL (100 itérations)** | Recherche `LIKE` | 19,7 | 20,6 | `range` sur `idx_produit_visible` (`< 500 ms`) |
| **SQL (100 itérations)** | 10 dernières commandes client | 19,3 | 20,2 | `ref` sur `idx_commande_client` (`< 500 ms`) |
| **SQL (100 itérations)** | Indicateurs du jour (`GROUP BY`) | 19,7 | 20,5 | `ALL` sur `commande` (1 004 lignes, `< 500 ms`) |
| **SQL écriture métier** | `sp_create_order` + `sp_add_order_line` + `sp_confirm_order` | 44,0 | — | Verrou `FOR UPDATE` + triggers + calcul port |
| **HTTP (200 req./URL)** | `/`, `/catalogue`, `/catalogue?...`, `/recherche`, `/produit/...`, `/panier` | 1,2 – 6,1 | **6,0 global** | **0 réponse inattendue — CONFORME ENF-01** |

*Tableau 15 — Résultats expérimentaux de performance SGBD et HTTP.*

### 4.4 Analyse critique des biais, limites du prototype et risques résiduels

Par honnêteté scientifique envers le jury, nous consignons explicitement les limites suivantes :
1. **Recette manuelle navigateur en attente :** les `200` scénarios humains consignés dans `docs/13-suivi-recette-uc.csv` (`164` UC + `36` transitions `RB-11`) restent au statut `NON JOUÉ`, et aucun test sur l’hébergement mutualisé *LineServ* n’est revendiqué sans campagne dédiée.
2. **Conditions de mesure locale :** les benchmarks `ENF-01` (`6 ms p95`) sont mesurés sur boucle locale d’une VM dédiée (sans latence réseau WAN ni terminaison TLS).
3. **Écarts assumés en maquette (`docs/01` §8.10) :** simulation du paiement et du contact, absence d’authentification multifacteur (MFA TOTP) sur `SUPER` (recommandée par OWASP ASVS V4.3.1), absence de *rate limiting* applicatif sur `/connexion`, et indexation secondaire à prévoir sur `commande(statut)` au-delà de 50 000 commandes (`EXPLAIN type=ALL`).

---

## Chapitre 5 — Livraison, exploitation (*Build → Run*), gouvernance GitLab et conformité au barème

### 5.1 Prérequis d’hébergement, procédure de déploiement, retour arrière et MCO

L’exploitation cible (`STACK.md`, `docs/26-transition-exploitation-maintenance-build-to-run.md`) requiert PHP **8.2+** (`json`, `mbstring`, `pdo_mysql`), MySQL **8.0.16+** ou MariaDB **10.6+**, et Apache 2.4 (`DocumentRoot public/`, `mod_rewrite`). Le passage en production impose de configurer `.env` hors racine Web (`APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql`), de supprimer les scripts utilitaires `public/gen_volumes.php` et `public/mesurer.php`, de remplacer les comptes de démonstration du seed, de réaliser un export `mysqldump --single-transaction --routines --triggers` pré-bascule pour garantir un retour arrière (*rollback*), et d’encadrer la rétention RGPD (`docs/18`) via `scripts/nettoyage_donnees.php`, `purger_comptes.php` et `archive_restreinte.php`.

### 5.2 Gouvernance GitLab, `README.md` et organisation de la soutenance (15 minutes)

Conformément aux consignes du sujet (p. 8–9), le dépôt comporte un `README.md` complet détaillant présentation, architecture, installation (`./run.sh --db`, `./run.sh`), configuration `.env`, identifiants locaux et répartition du travail entre **Johan Polsinelli** et **Chahd Gouiaa** (sous la supervision de **Mr Thanh-Phuong Nguyen** en rôle `Reporter`). Le support de soutenance de 15 minutes est structuré dans `docs/03-support-soutenance.md`, `docs/12-diaporama-soutenance.md` (`livrables/minishop-soutenance.pptx`, 12 diapositives) et `docs/24-preparation-soutenance-complete.md` (avec script de démonstration en direct sur front-office, back-office et terminal SQL).

### 5.3 Grille complète de vérification face aux pénalités du sujet officiel SAÉ 302

| Bloc du barème officiel (p. 8–9) | Points | Pénalités listées par le sujet | Vérification de conformité MiniShop |
|---|---:|---|---|
| **i. Document spécification du système** | **20** | Diagramme UML manquant (`-1`), sécurité manquante (`-2`), règle métier manquante (`-2`), contrainte technique manquante (`-2`), architecture manquante (`-2`) | **Conforme :** `14` diagrammes UML (`docs/diagrams/`), `5` réponses sécurité + `SEC-01..14`, `RB-01..20`, `CT-01..12`, architecture MVC 3 couches (`docs/01` & Chap. 1–2). |
| **ii. Document d’analyse et conception de BD** | **20** | Procédure stockée manquante (`-1`, max `-5`), déclencheur manquant (`-1`, max `-5`), script SQL manquant (`-3`), MCD : entité manquante (`-2`), mauvaise cardinalité (`-1`) | **Conforme :** `9` entités et cardinalités justifiées, MLD 3FN, `sql/01..04` avec données, **18 procédures + 1 fonction** (`>= 5`), **16 triggers** (`>= 5`) + réponse pédagogique complète (`docs/04` & Chap. 3). |
| **iii. Application Web + 2 documents** | **30** | Non MVC (`-5`), mauvaise requête SQLi (`-2`), procédure manquante (`-1`), trigger manquant (`-1`), fonctionnalité manquante (`-3`), rôle manquant (`-5`), doc tests manquant (`-3`), auth/session manquante (`-3`) | **Conforme :** MVC strict (`15` contrôleurs), PDO préparé (`11/11` sécurité), toutes fonctionnalités Visiteur/Client/Admin, auth + sessions strictes, `docs/02` + `pv-recette.md` (`60/60`, `29/29`, `105/105`, `8/8`). |
| **iv. Soutenance + questions (15 min)** | **10** | Trame, timing et réponses aux questions du jury | **Prêt :** diaporama `12` slides, démo chronométrée 4 min, terminal SQL préparé, 20 Q/R (`docs/03` & `docs/24`). |
| **v. Contribution régulière GitLab & README** | **10** | Historique commits binôme, `README.md`, accès `Reporter` enseignant | **Prévu :** `README.md` complet, historique Git tracé, charte `docs/20` (vérification finale sur le dépôt GitLab IUT avant soutenance). |

*Tableau 16 — Contrôle complet de conformité au barème officiel et d’absence de pénalité.*

---

## Conclusion générale et perspectives

Le projet **MiniShop** répond intégralement aux exigences fonctionnelles, architecturales, relationnelles, sécuritaires et méthodologiques du cahier des charges officiel de la **SAÉ 302**. En choisissant une approche sans framework en **PHP 8.2+ natif** adossée à une modélisation Merise en **3FN** enrichie de **18 procédures stockées** et **16 déclencheurs**, nous avons démontré qu’il est possible de garantir au plus près des données l’atomicité des commandes, la protection contre la survente (`0` survente sous concurrence) et l’immutabilité comptable des factures, tout en obtenant une latence HTTP de **6,0 ms** au 95e centile.

Les perspectives d’évolution vers une plateforme commerciale réelle s’articulent autour de quatre axes prioritaires : (1) le raccordement à un prestataire de paiement en mode *sandbox* avec journal de transactions et webhooks signés ; (2) l’ajout d’une authentification multifacteur TOTP sur le rôle `SUPER`, d’un *rate limiting* sur la connexion et d’un envoi SMTP réel ; (3) l’exécution complète des `200` scénarios de recette manuelle navigateur (`docs/13-suivi-recette-uc.csv`) et l’ajout d’une indexation sur `commande(statut)` ; et (4) la mise en place d’une suite Playwright continue couvrant l’ensemble des parcours utilisateurs.

---

## Bibliographie et webographie

- **[1] NGUYEN T.-P.** *Sujet officiel SAÉ 302 — MiniShop : Cahier des charges, Spécifications, Conception BD/SQL, Application PHP/PDO et Barème*, IUT de Nice Côte d’Azur, Université Côte d’Azur, 2026.
- **[2] OWASP Foundation.** *OWASP Top 10:2021* & *Application Security Verification Standard (ASVS) v4.0.3*. Disponible sur : `https://owasp.org/`.
- **[3] ANSSI.** *Recommandations pour la sécurisation des sites Web* & *Guide d’hygiène informatique*. Paris : SGDSN. Disponible sur : `https://cyber.gouv.fr/`.
- **[4] Object Management Group (OMG).** *OMG Unified Modeling Language (UML), Version 2.5.1*. 2017. Disponible sur : `https://www.omg.org/spec/UML/`.
- **[5] The PHP Group.** *PHP Manual — PDO, Password Hashing, Session Security*. Disponible sur : `https://www.php.net/manual/fr/`.
- **[6] MariaDB Foundation & Oracle Corp.** *MariaDB 10.6+ / MySQL 8.0 Reference Manual — InnoDB Locking Reads, Stored Routines and Triggers*. Disponible sur : `https://mariadb.com/kb/`.
- **[7] AFNOR.** *Normes NF Z 41-006 (Présentation des thèses et documents assimilés) et NF Z 44-005 (Références bibliographiques)*.
- **[8] CNIL.** *Référentiel gestion des activités commerciales* & *Guide de la sécurité des données personnelles*, 2024. Disponible sur : `https://www.cnil.fr/`.
- **[9] Polsinelli J., Gouiaa C.** Corpus documentaire MiniShop : `README.md`, `ARCHITECTURE.md`, `STACK.md`, `docs/01` à `docs/26`, `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt`, 2026.

---

## Annexes

### Annexe A — Cartographie des 15 contrôleurs PHP et politiques d’accès

| Espace | Contrôleur (`app/Controller/`) | Responsabilité principale | Garde routeur |
|---|---|---|---|
| **Socle** | `Controller.php` | Rendu `layout.php`, redirections, messages flash, `verifierCsrf()`, accesseurs typés | — |
| **Front-office** | `HomeController.php` | Accueil, pages légales, préférences UTC/langue (`/preferences`), formulaire de contact | `public` |
| **Front-office** | `CatalogueController.php` | Liste `/catalogue`, filtres/tri/pagination, `/categories`, `/recherche`, fiche `/produit/{slug}` | `public` |
| **Front-office** | `PanierController.php` | Affichage panier, ajout/modification/retrait en session (`POST` sans JS requis) | `public` |
| **Front-office** | `ApiController.php` | API JSON interne d’amélioration progressive du panier (avec vérification CSRF) | `public` |
| **Front-office** | `AuthController.php` | Inscription (`/inscription`), connexion (`/connexion`), déconnexion | `public` / `client` |
| **Espace Client** | `CompteController.php` | Profil (`/compte`), édition adresse structurée, changement mot de passe | `client` |
| **Espace Client** | `CommandeController.php` | Validation panier (`/commander`), historique `/mes-commandes`, détail et annulation 2 étapes | `client` |
| **Back-office** | `Admin/DashboardController.php` | Connexion admin (`/admin/connexion`), tableau de bord CA et alertes de stock | `public` / `admin` |
| **Back-office** | `Admin/ProduitAdminController.php` | CRUD catalogue produits, calcul TVA/TTC, masquage/suppression logique | `admin` |
| **Back-office** | `Admin/CategorieAdminController.php` | CRUD catégories, contrôle d’unicité et refus si catégorie non vide | `admin` |
| **Back-office** | `Admin/StockAdminController.php` | Ajustement manuel `DELTA` / `SET` via `sp_adjust_stock` avec motif obligatoire | `admin` |
| **Back-office** | `Admin/CommandeAdminController.php` | Suivi commandes, transitions de statut `RB-11`, annulation admin 2 étapes | `admin` |
| **Back-office** | `Admin/EquipeAdminController.php` | Création, activation/blocage et gestion des comptes administrateurs | **`super`** |
| **Back-office** | `Admin/ParametreAdminController.php` | Paramètres globaux (`tva_defaut`, `frais_port`, `franchise_port`, `conservation_compte`) | **`super`** |

### Annexe B — Répertoire complet des 3 vues, 1 fonction, 18 procédures et 16 triggers SQL

- **Tables (9) :** `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre`.
- **Vues (3) :** `v_catalogue`, `v_etat_stock`, `v_commandes_client`.
- **Fonction (1) :** `fn_param`.
- **Procédures stockées (18) :** `sp_compute_shipping`, `sp_create_account`, `sp_get_credentials`, `sp_update_client`, `sp_search_products`, `sp_save_product`, `sp_delete_product`, `sp_adjust_stock`, `sp_save_category`, `sp_delete_category`, `sp_create_order`, `sp_add_order_line`, `sp_create_order_from_basket`, `sp_confirm_order`, `sp_update_order_status`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_revenue_report`.
- **Déclencheurs (16) :**
  - *Lignes de commande :* `trg_ligne_controle_insert`, `trg_ligne_decrement_stock`, `trg_ligne_restore_stock`, `trg_ligne_immutable`, `trg_ligne_prix_snapshot`.
  - *Produits & catégories :* `trg_produit_ttc`, `trg_produit_ttc_update`, `trg_produit_regles`, `trg_produit_delete`, `trg_categorie_slug`, `trg_categorie_delete`.
  - *Commandes & audit :* `trg_commande_transition_statut`, `trg_commande_controle_insert`, `trg_commande_delete`, `trg_history_statut`, `trg_history_creation`.

### Annexe C — Requêtes SQL de preuve de non-contradiction des redondances

```sql
-- Ces 4 requêtes de cohérence doivent impérativement retourner 0 ligne :
SELECT id_produit FROM produit WHERE prix_ttc <> ROUND(prix_ht * (1 + tva / 100), 2);
SELECT c.id_commande FROM commande c
  JOIN (SELECT id_commande, SUM(total_ligne) AS s FROM ligne_commande GROUP BY id_commande) t
    ON t.id_commande = c.id_commande
 WHERE c.statut <> 'BROUILLON' AND c.montant_total <> ROUND(t.s + c.frais_port, 2);
SELECT c.id_commande FROM commande c
  JOIN (SELECT id_commande, SUM(quantite) AS q FROM ligne_commande GROUP BY id_commande) t
    ON t.id_commande = c.id_commande
 WHERE t.q <= 0;
SELECT id_produit FROM produit WHERE stock < 0;
```
