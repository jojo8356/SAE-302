# Rapport de projet universitaire — Conception, sécurisation et validation d’une architecture e-commerce transactionnelle sans framework (MiniShop)

> **Document académique de synthèse destiné aux enseignants et aux membres du jury** — Université Côte d’Azur · IUT de Nice · Département Informatique · BUT2 (SAÉ 302).

---

## Fiche d’identification académique

| Rubrique | Information |
|---|---|
| **Établissement** | Université Côte d’Azur — Institut Universitaire de Technologie (IUT) de Nice Côte d’Azur |
| **Formation & Unité** | Bachelor Universitaire de Technologie (BUT) Informatique — Situation d’Apprentissage et d’Évaluation **SAÉ 302** |
| **Intitulé du projet** | **MiniShop** — Plateforme e-commerce transactionnelle et back-office d’administration |
| **Auteurs (binôme)** | **Johan Polsinelli** & **Chahd Gouiaa** |
| **Encadrante pédagogique** | **Mme Thanh-Phuong Nguyen**, enseignante référente (`thanh-phuong.nguyen@univ-cotedazur.fr`) |
| **Année universitaire** | 2025 – 2026 (campagnes de validation VM consignées au 17 novembre 2026) |
| **Dépôt et référence** | Branche `arena/51cf6db6-sae-302` · Environnement PHP 8.2+ / MySQL 8.0+ / MariaDB 10.6+ |

---

## Remerciements

Nous adressons nos sincères remerciements à **Mme Thanh-Phuong Nguyen**, notre enseignante référente, pour la précision du cahier des charges, l’exigence méthodologique constante tout au long de cette SAÉ 302 et ses recommandations sur la modélisation relationnelle, l’intégrité transactionnelle et la sécurité applicative.

Nous remercions également l’ensemble de l’équipe pédagogique du département Informatique de l’IUT de Nice Côte d’Azur pour la mise à disposition des environnements d’hébergement pédagogique et des enseignements fondamentaux en génie logiciel, bases de données avancées et programmation Web qui ont rendu possible la réalisation de ce travail.

Enfin, nous exprimons notre gratitude aux membres du jury qui consacrent leur temps et leur expertise à l’évaluation critique de ce rapport et de la soutenance associée.

---

## Résumé

Ce mémoire présente la conception, la réalisation, la sécurisation et la validation expérimentale de **MiniShop**, une application Web transactionnelle de commerce électronique dotée d’un back-office d’administration, développée dans le cadre de la SAÉ 302 du BUT Informatique. Face au constat initial d’une gestion commerciale manuelle reposant sur des tableurs non atomiques, notre problématique consiste à concevoir un système fiable, auditable et portable en **PHP 8.2+ natif sans framework tiers**, garantissant l’intégrité stricte des données et des stocks sous accès concurrents.

L’architecture retenue associe une modélisation Merise normalisée en troisième forme normale (**9 tables**, **3 vues**, **18 procédures stockées**, **1 fonction** et **16 déclencheurs** incluant deux dénormalisations comptables verrouillées) à une architecture logicielle **MVC pragmatique** découplée de la persistance via le contrat `StoreInterface` (pilote principal **PDO/MySQL-MariaDB** avec verrouillage `SELECT ... FOR UPDATE`, complété par un pilote de secours **JSON**).

Les campagnes automatisées conduites sur machine virtuelle Debian 13 (MariaDB 11.8.6, PHP 8.4.27) attestent la conformité de **60/60 étapes** de recette, **29/29 scénarios d’intégration SQL**, **105/105 requêtes E2E HTTP SQL**, **8/8 scénarios de concurrence** sans survente (`20` acteurs pour `12` unités), **11/11 contrôles statiques de sécurité** et une latence HTTP au 95e centile de **6,0 ms** (seuil cible `< 500 ms`). L’analyse critique met néanmoins en évidence les limites inhérentes au périmètre maquette (paiement simulé, absence de serveur SMTP, `200` scénarios manuels restant à exécuter en navigateur).

**Mots-clés :** génie logiciel, architecture MVC sans framework, base de données relationnelle, intégrité transactionnelle, déclencheurs SQL, sécurité OWASP, validation expérimentale.

---

## Abstract

This academic report details the design, implementation, security hardening, and experimental validation of **MiniShop**, a transactional e-commerce web platform with an administrative back-office developed for the SAÉ 302 engineering project in Computer Science. Addressing the shortcomings of non-atomic spreadsheet workflows, our research and engineering question focuses on building a reliable, auditable, and portable system in **pure PHP 8.2+ without third-party frameworks**, enforcing strict data and inventory integrity under concurrent requests.

Our methodology combines a third-normal-form relational database design (**9 tables**, **3 views**, **18 stored procedures**, **1 function**, and **16 triggers**, with two trigger-locked accounting denormalizations) with a decoupled **pragmatic MVC architecture** abstracted behind a dual persistence interface (`StoreInterface`: primary **PDO/MySQL-MariaDB** driver using `SELECT ... FOR UPDATE` row locking, paired with an isolated **JSON** fallback driver).

Automated test campaigns executed on a Debian 13 virtual machine (MariaDB 11.8.6, PHP 8.4.27) validate **60/60 verification steps**, **29/29 SQL integrity scenarios**, **105/105 HTTP SQL end-to-end checks**, **8/8 concurrency stress tests** with zero overselling (`20` concurrent buyers for `12` units), **11/11 static security checks**, and a 95th-percentile HTTP response time of **6.0 ms** (well below the `500 ms` requirement). A critical evaluation honestly delineates the remaining academic boundaries, including simulated payment processing, local contact logging without SMTP delivery, and `200` manual browser scenarios pending human execution.

**Keywords:** software engineering, framework-less MVC architecture, relational databases, ACID transactions, SQL triggers, OWASP security, experimental verification.

---

## Sommaire

- **Introduction générale**
  - Contexte et genèse du besoin
  - Problématique scientifique et technique
  - Objectifs du projet et structuration du mémoire
- **Chapitre 1 — Cadre du projet, méthodologie d’ingénierie et état de l’art critique**
  - 1.1 Parties prenantes et délimitation stricte du périmètre
  - 1.2 Organisation du binôme, découpage WBS et gouvernance GitLab
  - 1.3 État de l’art comparatif et justification des choix technologiques
- **Chapitre 2 — Analyse des besoins et conception des données (Merise & UML 2.5)**
  - 2.1 Analyse fonctionnelle : cas d’utilisation et règles métier
  - 2.2 Modèle Conceptuel des Données (MCD) et justification des cardinalités
  - 2.3 Modèle Logique des Données (MLD), normalisation 3FN et dénormalisations verrouillées
  - 2.4 Modélisation dynamique et implémentation SQL avancée
- **Chapitre 3 — Architecture logicielle, réalisation et sécurité *by design***
  - 3.1 Architecture MVC pragmatique, routage à liste blanche et diagramme de classes
  - 3.2 Abstraction de persistance (`StoreInterface`) : parité et limites `SqlStore` / `JsonStore`
  - 3.3 Modélisation des menaces et ingénierie de la sécurité applicative
  - 3.4 Ergonomie, accessibilité, internationalisation et gestion déterministe du temps
- **Chapitre 4 — Protocole de validation expérimentale, sécurité et performances**
  - 4.1 Stratégie de vérification multi-niveaux
  - 4.2 Synthèse quantitative des campagnes de tests automatisés
  - 4.3 Évaluation expérimentale des performances (`ENF-01`) et analyse `EXPLAIN`
  - 4.4 Analyse critique des biais, limites du prototype et risques résiduels
- **Chapitre 5 — Exploitation (*Build → Run*), analyse des écarts et bilan réflexif**
  - 5.1 Prérequis d’hébergement, déploiement (*Cutover / Rollback*) et MCO
  - 5.2 Analyse critique des écarts vis-à-vis du cahier des charges initial
  - 5.3 Bilan méthodologique, difficultés surmontées et apports pédagogiques
- **Conclusion générale et perspectives**
- **Bibliographie et webographie**
- **Annexes**
  - Annexe A — Cartographie des 15 contrôleurs PHP et politiques de garde
  - Annexe B — Inventaire complet des objets SQL (`sql/01` à `sql/03`)
  - Annexe C — Matrice synthétique de traçabilité Exigences ↔ Réalisation ↔ Preuves

---

## Table des figures

- **Figure 1** — Diagramme de cas d’utilisation du processus de commande (`docs/diagrams/cas_utilisation_commandes.png`)
- **Figure 2** — Modèle Conceptuel des Données à 9 entités (`docs/diagrams/mcd_minishop.png`)
- **Figure 3** — Modèle Logique des Données normalisé (`docs/diagrams/mld_minishop.png`)
- **Figure 4** — Automate à 6 états du cycle de vie d’une commande (`docs/diagrams/etats_commande.png`)
- **Figure 5** — Architecture logicielle MVC et double persistance (`docs/diagrams/architecture_mvc.png`)
- **Figure 6** — Diagramme de classes de l’application PHP (`docs/diagrams/diagramme_classes.png`)
- **Figure 7** — Diagramme de séquence transactionnel de validation de commande (`docs/diagrams/seq_passer_commande.png`)

## Table des tableaux

- **Tableau 1** — Comparatif multicritères du socle applicatif (PHP natif vs frameworks MVC)
- **Tableau 2** — Comparatif des stratégies d’accès aux données (`StoreInterface` vs ORM)
- **Tableau 3** — Justification détaillée des cardinalités du MCD
- **Tableau 4** — Répartition des responsabilités entre couche PHP et couche SGBD
- **Tableau 5** — Matrice des menaces OWASP/ANSSI et contre-mesures implémentées
- **Tableau 6** — Bilan chiffré des campagnes de validation automatisée
- **Tableau 7** — Mesures de performance SQL et HTTP sous volumétrie cible (`minishop_perf`)
- **Tableau 8** — Matrice de traçabilité synthétique des exigences du sujet SAÉ 302

---

## Glossaire des sigles et acronymes

| Sigle | Définition exacte |
|---|---|
| **3FN** | Troisième Forme Normale (théorie de la normalisation relationnelle de Codd) |
| **ACID** | Atomicité, Cohérence (*Consistency*), Isolation, Durabilité |
| **ANSSI** | Agence Nationale de la Sécurité des Systèmes d’Information |
| **ASVS** | *Application Security Verification Standard* (référentiel OWASP) |
| **CSP** | *Content Security Policy* (en-tête HTTP de restriction des ressources) |
| **CSRF** | *Cross-Site Request Forgery* (falsification de requête inter-sites) |
| **ENF** | Exigence Non Fonctionnelle (`ENF-01` à `ENF-22`) |
| **HTTP / E2E** | *HyperText Transfer Protocol* / *End-to-End* (tests de bout en bout) |
| **IDOR** | *Insecure Direct Object Reference* (accès direct non autorisé à une ressource) |
| **MCD / MLD** | Modèle Conceptuel des Données / Modèle Logique des Données (méthode Merise) |
| **MCO** | Maintien en Condition Opérationnelle |
| **MVC** | *Model-View-Controller* (patron architectural Modèle-Vue-Contrôleur) |
| **OWASP** | *Open Worldwide Application Security Project* |
| **PDO** | *PHP Data Objects* (couche d’abstraction d’accès aux bases de données en PHP) |
| **RB / SEC** | Règle Métier (`RB-01`…`RB-20`) / Exigence de Sécurité (`SEC-01`…`SEC-14`) |
| **RBAC** | *Role-Based Access Control* (contrôle d’accès basé sur les rôles) |
| **RGPD** | Règlement Général sur la Protection des Données |
| **SAÉ** | Situation d’Apprentissage et d’Évaluation |
| **SGBD** | Système de Gestion de Base de Données |
| **UML** | *Unified Modeling Language* (version 2.5 de l’OMG) |
| **XSS** | *Cross-Site Scripting* (injection de script côté client) |

---

## Introduction générale

### Contexte et genèse du besoin

La numérisation des activités commerciales repose sur une exigence fondamentale de cohérence entre l’état physique des stocks, les engagements tarifaires pris envers les clients et la traçabilité des opérations comptables. Dans la situation initiale décrite par le cahier des charges de la **SAÉ 302**, la boutique artisanale suivait son catalogue, ses niveaux de stock et ses commandes au moyen de tableurs partagés et d’échanges informels. Ce mode de fonctionnement manuel engendre trois défaillances structurelles bien identifiées en ingénierie des systèmes d’information : l’absence de contrôle transactionnel (conduisant à des ventes à stock négatif ou *survente*), la mutabilité rétroactive des prix historiques et l’absence de piste d’audit fiable sur les changements d’état.

Le projet **MiniShop** vise à remplacer ce processus artisanal par une application Web e-commerce complète, structurée autour d’un front-office destiné aux visiteurs et aux clients et d’un back-office réservé aux administrateurs habilités. Réalisé en binôme dans un cadre universitaire strict, le projet impose un niveau d’exigence double : livrer une application robuste et sécurisée tout en démontrant la maîtrise fondamentale des couches basses (SQL procédural, protocole HTTP, gestion manuelle de sessions, défense en profondeur) sans recourir à un framework industriel masquant ces mécanismes.

### Problématique scientifique et technique

Au-delà de la simple écriture de pages Web dynamiques, notre travail s’articule autour de la problématique d’ingénierie suivante :

> **Comment concevoir, sécuriser et valider expérimentalement une application Web transactionnelle en PHP natif sans framework, garantissant l’intégrité stricte des stocks et l’immutabilité financière des commandes sous accès concurrents, tout en assurant la portabilité vers un moteur de persistance secondaire et une maintenabilité mesurable ?**

Cette problématique se décline en quatre verrous techniques majeurs :
1. **Verrou d’intégrité et de concurrence :** empêcher mathématiquement toute survente lorsque plusieurs acheteurs valident simultanément un stock limité, et empêcher qu’une variation de prix du catalogue n’altère les commandes passées.
2. **Verrou architectural :** maintenir un découplage propre entre logique HTTP, règles métier et stockage (`SQL` par défaut, `JSON` en mode de secours portable) sans l’assistance d’un ORM ou d’un conteneur d’injection de dépendances tiers.
3. **Verrou sécuritaire :** éliminer dès la conception les vulnérabilités classiques des applications Web (injections SQL, XSS, CSRF, élévation de privilèges, IDOR, détournement de session) conformément aux référentiels OWASP et ANSSI.
4. **Verrou de validation :** prouver ces propriétés par des expériences reproductibles (tests d’intégration SQL, tests de charge HTTP, attaques de concurrence) tout en distinguant rigoureusement les preuves exécutées des scénarios restant à jouer manuellement.

### Objectifs du projet et structuration du mémoire

Pour répondre à cette problématique, nous avons structuré ce rapport selon la progression canonique de l’ingénierie logicielle universitaire. Le **Chapitre 1** pose le cadre organisationnel, la démarche de conduite de projet et un état de l’art critique justifiant nos choix technologiques. Le **Chapitre 2** détaille l’analyse des besoins et la conception de la base de données (Merise, UML 2.5, 3FN, procédures et déclencheurs). Le **Chapitre 3** expose l’architecture logicielle MVC, le contrat de persistance duale et les mécanismes de sécurité *by design*. Le **Chapitre 4** présente le protocole expérimental, les résultats de validation, les performances mesurées et une analyse critique de leurs limites. Enfin, le **Chapitre 5** traite du passage en exploitation (*Build → Run*), de l’analyse des écarts vis-à-vis du cahier des charges et du bilan réflexif de ce projet.

---

## Chapitre 1 — Cadre du projet, méthodologie d’ingénierie et état de l’art critique

### 1.1 Parties prenantes et délimitation stricte du périmètre

La maîtrise de la dérive fonctionnelle (*scope creep*) constitue un facteur déterminant de réussite dans un projet académique contraint en temps. Le cahier des charges (`docs/01-cahier-des-charges-MiniShop.md`) distingue quatre profils d’interaction strictement cloisonnés :
- **Le Visiteur** : parcourt librement l’accueil, les catégories, la recherche multicritères, les fiches produits, les pages d’information légale et le formulaire de contact, et constitue un panier temporaire en session.
- **Le Client authentifié** : gère son profil et son mot de passe, consulte son historique de commandes, valide un panier en commande et peut annuler une commande avant son expédition.
- **L’Administrateur `GESTIONNAIRE`** : pilote au quotidien le catalogue (produits, catégories), les mouvements de stock justifiés par un motif obligatoire et le cycle de traitement des commandes.
- **L’Administrateur `SUPER`** : dispose en plus du privilège exclusif de gérer les comptes de l’équipe d’administration et les paramètres globaux de la boutique (TVA par défaut, frais de port, seuil de gratuité, durée de conservation).

Afin de concentrer l’effort d’ingénierie sur la fiabilité transactionnelle et la sécurité, **le périmètre exclut explicitement** l’intégration d’un prestataire de paiement bancaire réel (le règlement est simulé à la confirmation), la connexion à un serveur SMTP externe (les messages de contact sont journalisés localement dans `var/log/contact.jsonl`) et l’interfaçage avec un transporteur logistique. Ce bornage assumé est documenté dans l’application elle-même afin d’éviter toute ambiguïté auprès de l’utilisateur ou du jury.

### 1.2 Organisation du binôme, découpage WBS et gouvernance GitLab

La conduite du projet s’appuie sur un découpage structuré des travaux (*Work Breakdown Structure*, consigné dans `docs/05-wbs-projet.md` et `docs/06-todolist.md`) articulé en cinq lots cohérents : (1) cadrage et modélisation des données, (2) socle SQL et triggers, (3) cœur applicatif MVC et persistance duale, (4) interfaces front/back-office et accessibilité, (5) campagne de tests, audits et livrables.

Sur le plan collaboratif, le projet adopte une gouvernance de gestion de configuration inspirée des standards industriels (`docs/20-charte-gitlab-revues-croisees.md`) :
- séparation en branches de travail dédiées et historique atomique tracé ;
- revues croisées documentaires et techniques entre les deux membres du binôme ;
- automatisation d’une chaîne d’intégration continue (`.github/workflows/ci.yml`) contrôlant à chaque poussée la syntaxe PHP (`8.2` et `8.5`), la cohérence des tests SQL sous MySQL 8 et MariaDB 11.8, les tests unitaires, l’audit documentaire (`verifier_fiches_uc.py`) et les contrôles statiques de sécurité (`tests/security/controles.sh`).

### 1.3 État de l’art comparatif et justification des choix technologiques

Un jury d’école d’ingénieurs ou d’université attend non seulement la description des outils employés, mais la **démonstration rationnelle** de leur pertinence face aux alternatives de l’état de l’art.

#### Choix du socle applicatif : PHP 8.2+ natif vs frameworks MVC complets

| Critère d’évaluation | PHP 8.2+ natif (`declare(strict_types=1)`) — *Choix retenu* | Framework lourd (ex. Symfony 7 / Laravel 11) | Micro-framework (ex. Slim) |
|---|---|---|---|
| **Conformité pédagogique SAÉ** | Maximale : zéro dépendance magique, contrôle total des en-têtes, sessions et PDO | Moyenne : masque les mécanismes HTTP/CSRF/SQL sous-jacents | Bonne, mais introduit Composer et des middlewares tiers |
| **Surface d’attaque & dépendances** | Minimale : 0 bibliothèque tierce en production (`vendor/` absent en prod) | Élevée : dizaines de paquets Composer à maintenir | Modérée |
| **Performance à froid (latence)** | Excellente (`p95 = 6,0 ms` mesuré sans cache OPcache complexe) | Plus lourde sans optimisation avancée (boot container) | Bonne |
| **Coût de développement** | Plus élevé : écriture manuelle du routeur, du moteur de vues et des gardes | Faible grâce aux générateurs et composants standards | Intermédiaire |

*Tableau 1 — Comparatif multicritères du socle applicatif (PHP natif vs frameworks MVC).*

**Arbitrage retenu :** dans le cadre d’une SAÉ visant la maîtrise des fondamentaux Web et de la cybersécurité, le choix de **PHP 8.2+ en typage strict sans framework** permet de prouver ligne par ligne la chaîne de sécurité (routeur à liste blanche, échappement XSS, jetons CSRF, PDO préparé) tout en obtenant une empreinte d’exécution très faible.

#### Choix de la couche d’accès aux données : abstraction `StoreInterface` duale vs ORM classique

| Critère d’évaluation | Contrat `StoreInterface` (`SqlStore` PDO + `JsonStore`) — *Choix retenu* | ORM complet (Doctrine / Eloquent) | SQL procédural pur couplé aux vues |
|---|---|---|---|
| **Exploitation des triggers et procédures SGBD** | Directe via `SqlStore` et détection de capacités SQL | Complexe : l’ORM entre souvent en conflit avec l’état modifié par les triggers | Directe mais couple fortement l’HTML au SQL |
| **Portabilité et résilience de démonstration** | Totale : bascule transparente vers `JsonStore` si SGBD indisponible (`STORAGE_DRIVER=json`) | Dépendance stricte à un SGBD relationnel | Nulle sans SGBD |
| **Maintenabilité / Coût d’abstraction** | Modéré : maintien de la parité comportementale entre `SqlStore` et `Triggers.php` | Élevée pour le CRUD, faible pour l’optimisation SQL fine | Faible (dette technique rapide) |

*Tableau 2 — Comparatif des stratégies d’accès aux données (`StoreInterface` vs ORM).*

**Arbitrage retenu :** l’interface `StoreInterface` constitue un **compromis architectural original**. Elle isole les contrôleurs et dépôts métier des détails techniques de stockage tout en laissant le pilote `SqlStore` exploiter pleinement les procédures stockées et les verrous `SELECT ... FOR UPDATE` de MySQL/MariaDB.

#### Choix méthodologiques de modélisation et de sécurité

Pour la conception, nous couplons la méthode **Merise** (indispensable en ingénierie relationnelle francophone pour formaliser les dépendances fonctionnelles et la 3FN via le MCD et le MLD) avec **UML 2.5** (adapté aux comportements dynamiques : cas d’utilisation, séquences, diagramme de classes et automate d’états). En matière de sécurité, nos exigences (`SEC-01` à `SEC-14`) s’alignent explicitement sur l’**OWASP Top 10**, le standard de vérification **OWASP ASVS** (niveaux de contrôle d’accès V4) et les recommandations du guide **ANSSI** sur la sécurisation des sites Web et le moindre privilège.

---

## Chapitre 2 — Analyse des besoins et conception des données (Merise & UML 2.5)

### 2.1 Analyse fonctionnelle : cas d’utilisation et règles métier

Le système couvre **14 cas d’utilisation principaux** (`UC-01` à `UC-14`, détaillés dans `docs/annexes/annexe-cas-usage.md` et vérifiés à travers 164 scénarios dans `docs/13-fiches-recette-uc.md`) répartis entre le front-office (`UC-01` consultation catalogue, `UC-02` fiche produit, `UC-03` panier, `UC-04` inscription/connexion, `UC-05` profil/commandes client, `UC-06` passage de commande, `UC-07` annulation client) et le back-office (`UC-08` tableau de bord, `UC-09` produits, `UC-10` catégories, `UC-11` ajustement de stock, `UC-12` traitement des commandes, `UC-13` gestion équipe `SUPER`, `UC-14` paramètres boutique).

![Diagramme de cas d’utilisation du processus de commande](docs/diagrams/cas_utilisation_commandes.png)

*Figure 1 — Diagramme de cas d’utilisation ciblé sur le cycle de vie des commandes et les acteurs habilités.*

L’analyse fonctionnelle est gouvernée par un noyau strict de règles métier (`RB-01` à `RB-20`) dont les plus critiques pour la cohérence comptable sont :
- **Unicité et intégrité référentielle (`RB-01`, `RB-07`, `RB-14`) :** unicité des courriels clients/admins, des slugs et références produits ; rattachement obligatoire d’un produit à une catégorie et interdiction de supprimer physiquement un produit ayant figuré dans une commande ou une catégorie non vide.
- **Protection des stocks (`RB-03`, `RB-18`) :** interdiction absolue d’un stock négatif et contrôle transactionnel au moment exact de la confirmation de commande.
- **Immutabilité financière (`RB-06`, `RB-09`, `RB-15`, `RB-16`, `RB-20`) :** calcul serveur du prix TTC depuis le prix HT et la TVA, capture définitive du `prix_unitaire` sur la ligne de commande, calcul serveur des frais de port selon le seuil de franchise et verrouillage du montant total.

### 2.2 Modèle Conceptuel des Données (MCD) et justification des cardinalités

Le Modèle Conceptuel des Données comprend **9 entités** : `CLIENT`, `ADMINISTRATEUR`, `CATEGORIE`, `PRODUIT`, `COMMANDE`, `LIGNE_COMMANDE`, `ORDER_STATUS_HISTORY`, `PARAMETRE` et `STOCK_MOVEMENT_HISTORY`.

![Modèle Conceptuel des Données MiniShop à 9 entités](docs/diagrams/mcd_minishop.png)

*Figure 2 — Modèle Conceptuel des Données (MCD) complet à 9 entités.*

| Association | Entités & Cardinalités | Justification d’ingénierie | Conséquence en base |
|---|---|---|---|
| `PASSER` | `CLIENT` `(0,N)` — `COMMANDE` `(1,1)` | Un client peut passer 0 à N commandes ; toute commande a un unique client auteur (`RB-10`). | `id_client` `NOT NULL`, clé étrangère `ON DELETE RESTRICT` |
| `COMPORTER` | `COMMANDE` `(1,N)` — `LIGNE_COMMANDE` `(1,1)` | Une ligne n’existe qu’au sein d’une commande ; toute commande validée porte au moins une ligne (`RB-04`). | Clé étrangère `ON DELETE CASCADE` (limité aux brouillons) |
| `ETRE VENDU DANS` | `PRODUIT` `(0,N)` — `LIGNE_COMMANDE` `(1,1)` | Un article peut ne jamais avoir été vendu ou figurer dans N lignes ; chaque ligne pointe un produit unique. | Clé étrangère `ON DELETE RESTRICT` + trigger de bascule `visible = 0` |
| `CLASSER` | `CATEGORIE` `(0,N)` — `PRODUIT` `(1,1)` | Une catégorie peut initialement être vide (`RB-08`) ; chaque produit appartient à une unique catégorie (`RB-07`). | `id_categorie` `NOT NULL`, `ON DELETE RESTRICT` |
| `ETRE TRACÉE DANS` | `COMMANDE` `(0,N)` — `ORDER_STATUS_HISTORY` `(1,1)` | Chaque transition d’état crée une entrée d’audit horodatée rattachée à la commande. | `order_id` `NOT NULL`, `ON DELETE CASCADE` |

*Tableau 3 — Justification détaillée des cardinalités du MCD.*

Deux choix architecturaux majeurs méritent d’être soulignés devant le jury :
1. **Séparation physique `CLIENT` vs `ADMINISTRATEUR` :** plutôt qu’une table unique `UTILISATEUR` discriminée par une colonne `role`, nous séparons deux tables aux attributs et cycles de vie disjoints (adresse postale pour le client, rôle `SUPER`/`GESTIONNAIRE` pour l’administrateur). Cela rend structurellement impossible toute création de compte administrateur depuis le formulaire d’inscription public (`SEC-10`).
2. **Autonomie de `STOCK_MOVEMENT_HISTORY` :** la table d’audit des ajustements manuels de stock conserve par copie (*snapshot*) l’identifiant, la référence et le nom du produit sans contrainte de clé étrangère bloquante, permettant de préserver l’historique de stock même après suppression logique ou archivage.

### 2.3 Modèle Logique des Données (MLD), normalisation 3FN et dénormalisations verrouillées

La dérivation du MCD produit le Modèle Logique des Données illustré ci-dessous, conforme à la **Troisième Forme Normale (3FN)** : tous les attributs sont atomiques (1FN), dépendent pleinement de l’intégralité de la clé primaire (2FN, par utilisation d’identifiants techniques simples `id_*`) et ne présentent aucune dépendance transitive vers d’autres attributs non-clés (3FN).

![Modèle Logique des Données MiniShop](docs/diagrams/mld_minishop.png)

*Figure 3 — Modèle Logique des Données (MLD) et contraintes d’intégrité référentielle.*

Toutefois, deux **dénormalisations contrôlées** ont été introduites volontairement pour répondre aux impératifs comptables d’un site marchand :
- **Conservation du prix TTC sur `PRODUIT` (`prix_ttc`) :** calculable théoriquement par `prix_ht * (1 + tva / 100)`, cet attribut matérialisé évite de répéter le calcul sur chaque tri ou filtrage catalogue et est **automatiquement recalculé par les déclencheurs `trg_produit_ttc` et `trg_produit_ttc_update`** à chaque insertion ou mise à jour.
- **Gel tarifaire sur `LIGNE_COMMANDE` (`prix_unitaire`, `total_ligne`) et `COMMANDE` (`frais_port`, `montant_total`) :** dans un système e-commerce, le prix payé à l’instant *t* ne doit jamais varier si le catalogue est modifié à *t + 1*. Les triggers `trg_ligne_prix_snapshot` et `trg_ligne_immutable` capturent et figent irrévocablement ces montants côté SGBD.

### 2.4 Modélisation dynamique et implémentation SQL avancée

Le cycle de vie d’une commande obéit à un **automate fini déterministe à 6 états** (`BROUILLON`, `EN_PREPARATION`, `EXPEDIEE`, `LIVREE`, `ANNULEE`, `ANNULEE_APRES_EXPEDITION`), représenté sur la Figure 4.

![Automate des états et transitions d’une commande](docs/diagrams/etats_commande.png)

*Figure 4 — Diagramme d’états-transitions du cycle de vie d’une commande (`RB-11`).*

Afin de satisfaire les exigences de centralisation, d’atomicité et d’auditabilité, la base SQL (`sql/01_minishop_schema.sql` à `sql/04_minishop_demo.sql`) implémente largement au-delà des minimums académiques demandés :
- **9 tables relationnelles** avec contraintes `CHECK` (`ck_produit_prix`, `ck_produit_stock`, `ck_ligne_quantite`), index de recherche (`idx_produit_visible`, `idx_commande_client`) et clés uniques ;
- **3 vues SQL** (`v_catalogue`, `v_etat_stock`, `v_commandes_client`) unifiant le calcul des alertes (`RUPTURE`, `TRES_BAS`, `OK`) ;
- **1 fonction SQL (`fn_param`)** et **18 procédures stockées** encapsulant les transactions critiques (`sp_create_order_from_basket`, `sp_confirm_order`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_adjust_stock`, `sp_revenue_report`, etc.) ;
- **16 déclencheurs (triggers)** assurant la cohérence des write-paths même en cas de requête SQL directe hors application.

| Mécanisme critique | Rôle de la couche PHP (`App\Repository`) | Rôle de la couche SGBD (`sql/`) |
|---|---|---|
| **Anti-survente (`RB-18`)** | Validation des quantités saisies, appel transactionnel | Verrouillage pessimiste `SELECT ... FOR UPDATE` + `trg_ligne_controle_insert` + `trg_ligne_decrement_stock` |
| **Transitions & audit (`RB-11`)** | Vérification CSRF, contrôle d’appartenance et d’autorisation | Matrice `trg_commande_transition_statut` + journalisation `trg_history_statut` |
| **Politique RGPD (`docs/18`)** | Outils CLI simulés par défaut (`purger_comptes.php`, `nettoyage_donnees.php`) | Paramètre `conservation_compte` (`24 mois` par défaut) et intégrité `ON DELETE RESTRICT` sur commandes existantes |

*Tableau 4 — Répartition stricte des responsabilités d’intégrité entre PHP et le SGBD.*

---

## Chapitre 3 — Architecture logicielle, réalisation et sécurité *by design*

### 3.1 Architecture MVC pragmatique, routage à liste blanche et diagramme de classes

MiniShop suit un patron **Modèle–Vue–Contrôleur (MVC) pragmatique** dont l’unique point d’entrée HTTP est `public/index.php`. Le serveur Web (`Apache 2.4` via `public/.htaccess`) interdit tout accès direct aux répertoires internes (`app/`, `sql/`, `var/`, `docs/`, `tests/`).

![Architecture MVC et double persistance de MiniShop](docs/diagrams/architecture_mvc.png)

*Figure 5 — Architecture en couches MVC et découplage de la persistance (`StoreInterface`).*

Le flux de traitement d’une requête s’exécute selon un pipeline déterministe :
1. **Amorçage (`app/bootstrap.php`) :** enregistrement d’un autoloader PSR-4 minimal (`App\` → `app/`), chargement des helpers d’échappement (`e()`) et fixation du fuseau interne à **UTC**.
2. **En-têtes de sécurité et session (`Auth`) :** injection systématique des en-têtes HTTP défensifs avant toute sortie, puis ouverture de la session en mode strict.
3. **Routage à liste blanche (`app/Router.php`) :** aucune résolution dynamique de classe à partir de l’URL n’est tolérée. Chaque route déclare explicitement son verbe HTTP, son motif paramétré (`{id}`, `{slug}`), son contrôleur (`15` fichiers répartis entre `app/Controller/` et `app/Controller/Admin/`) et sa garde d’accès (`public`, `client`, `admin` ou `super`). Une route absente retourne `404` et un verbe interdit retourne `405`.
4. **Orchestration métier (`Repository`) et rendu (`app/View/`) :** les contrôleurs vérifient le jeton CSRF sur toute mutation, délèguent aux *repositories*, puis rendent soit un template PHP intégré au gabarit commun `layout.php`, soit une réponse JSON contrôlée (`ApiController` pour l’amélioration progressive du panier).

![Diagramme de classes de l’application MiniShop](docs/diagrams/diagramme_classes.png)

*Figure 6 — Diagramme de classes simplifié des contrôleurs, dépôts, modèles et pilotes de persistance.*

### 3.2 Abstraction de persistance (`StoreInterface`) : parité et limites `SqlStore` / `JsonStore`

L’inversion de dépendance opérée par `App\Model\Data\StoreInterface` découple entièrement la logique applicative du moteur de stockage :
- **`SqlStore` (pilote nominal, `STORAGE_DRIVER=sql`) :** valide les identifiants SQL (noms de tables et colonnes) contre les métadonnées de `Schema`, prépare toutes les requêtes via PDO natif (`ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`), et exploite les interfaces complémentaires SQL (`sp_*` et verrous `SELECT ... FOR UPDATE`).
- **`JsonStore` (pilote secondaire explicite, `STORAGE_DRIVER=json`) :** stocke chaque table dans un fichier JSON dédié sous `data/minishop/`, en répliquant les contraintes d’unicité, clés étrangères et déclencheurs dans `Schema.php` et `Triggers.php`. Les écritures emploient un verrou coopératif (`.minishop.lock`) et un renommage atomique fichier par fichier.

**Analyse honnête des limites du pilote JSON :** comme documenté dans `ARCHITECTURE.md`, si l’écriture d’un fichier individuel est atomique au sens POSIX (`rename`), une transaction touchant simultanément plusieurs tables JSON n’offre pas la durabilité ACID *crash-safe* d’un journal de transactions SGBD (*WAL / InnoDB redo log*). Le pilote JSON répond parfaitement aux besoins de démonstration hors ligne et de tests rapides isolés, mais seul `SqlStore` est légitime pour un environnement de production concurrentiel.

![Diagramme de séquence du passage de commande](docs/diagrams/seq_passer_commande.png)

*Figure 7 — Séquence transactionnelle de validation du panier, calcul du port et décrémentation sécurisée du stock.*

### 3.3 Modélisation des menaces et ingénierie de la sécurité applicative

La sécurité applicative ne repose pas sur des correctifs tardifs mais sur une défense en profondeur vérifiable statiquement et dynamiquement :

| Catégorie de menace (OWASP) | Vecteur d’attaque modélisé | Contre-mesure implémentée dans MiniShop | Preuve / Fichier |
|---|---|---|---|
| **A03 — Injection SQL (`SEC-01`)** | Altération de paramètres GET/POST (`?q=' OR 1=1--`) | 100 % requêtes préparées PDO (`ATTR_EMULATE_PREPARES => false`) et appels `CALL sp_*` ; validation blanche des colonnes de tri. | `app/Model/Data/SqlStore.php`, contrôle `[01/11]` |
| **A03 — XSS (`SEC-02`)** | Injection de balises `<script>` dans nom produit, adresse ou message | Échappement systématique via `e()` (`htmlspecialchars` UTF-8) dans `app/View/` + en-tête `Content-Security-Policy` restrictif (sans CDN externe). | `app/Security/xss.php`, `public/index.php`, contrôle `[02/11]` |
| **A01 — Contrôle d’accès & IDOR (`SEC-07`, `SEC-08`, `SEC-10`)** | Lecture/annulation d’une commande tierce par modification de l’ID URL ; accès admin par un client ou `GESTIONNAIRE` sur `/admin/equipe` | Gardes routeur (`client`, `admin`, `super`), isolation `$_SESSION['client_id']` / `$_SESSION['admin_id']`, contrôle d’appartenance en base avec réponse `404` neutre sur IDOR, absence de route d’inscription admin. | `app/Security/Auth.php`, `CommandeController.php`, contrôles `[05/11]` & `[08/11]` |
| **A07 — Authentification & Sessions (`SEC-04`, `SEC-05`, `RB-12`)** | Force brute, fixation de session, vol de cookie, énumération d’e-mails | Hachage `password_hash(..., PASSWORD_BCRYPT)` (longueur ≥ 8 car.), `password_verify()`, messages d’erreur indifférenciés à la connexion, `session_regenerate_id(true)`, cookies `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` sous HTTPS. | `AuthController.php`, `Auth.php`, contrôles `[04/11]` & `[06/11]` |
| **CSRF (`SEC-06`)** | Requête POST forgée depuis un site tiers à l’insu de l’utilisateur connecté | Jeton CSPRNG (`bin2hex(random_bytes(32))`) dans tout formulaire POST et appel API JSON, comparé par `hash_equals()` dans `Csrf::verifier()`. | `app/Security/Csrf.php`, contrôle `[07/11]` |
| **Clickjacking & Hygiène (`SEC-11`, `SEC-12`, `SEC-14`)** | Intégration en `<iframe>`, fuite de `.env` ou de données bancaires | En-têtes `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` dans `index.php` et `public/.htaccess` ; `.env` ignoré par Git ; zéro donnée de carte stockée. | `public/.htaccess`, `.gitignore`, contrôles `[10/11]` & `[11/11]` |

*Tableau 5 — Matrice synthétique des menaces et défenses vérifiées.*

### 3.4 Ergonomie, accessibilité, internationalisation et gestion déterministe du temps

L’interface suit une charte graphique sobre et lisible (`docs/DA.md`), dépourvue de dépendances CDN externes. Trois choix d’ingénierie renforcent la qualité d’usage :
1. **Amélioration progressive sans dépendance JavaScript (`ENF-07`) :** l’ensemble des parcours (catalogue, panier, commande, annulation en deux étapes avec page de confirmation dédiée) demeure intégralement fonctionnel lorsque JavaScript est désactivé dans le navigateur ; `public/js/panier.js` apporte uniquement un confort asynchrone.
2. **Design adaptatif (*responsive*) et bilingue :** les feuilles de style (`app.css`, `print.css`) s’adaptent de `320 px` à `1440 px` (repli des tableaux back-office en cartes étiquetées via `data-label`), tandis que `public/js/i18n-en.js` assure une traduction FR/EN complète mémorisée côté navigateur (`localStorage`) sans cookie traceur.
3. **Gestion rigoureuse du temps en UTC :** toutes les dates sont enregistrées en **UTC** en base (`app/Tools/Heure.php`). L’affichage et le filtrage des bornes journalières dans le navigateur s’ajustent au fuseau choisi par l’utilisateur sur `/preferences` (ou détecté localement via `public/js/tz-lookup.js` sans transmission ni conservation de coordonnées géographiques côté serveur).

---

## Chapitre 4 — Protocole de validation expérimentale, sécurité et performances

### 4.1 Stratégie de vérification multi-niveaux

Conformément aux pratiques rigoureuses du génie logiciel (`docs/11-strategie-tests.md`), notre démarche de validation repose sur une pyramide de tests complémentaires :
1. **Contrôles statiques et structurels :** analyse de sécurité automatisée du code source (`tests/security/controles.sh`), vérification de syntaxe PHP/JS/Shell/JSON/XML, et intégrité documentaire (`verifier_fiches_uc.py`, `test_audit_structurel.py`).
2. **Tests unitaires et fonctionnels isolés :** assertions PHPUnit sur les modèles, validations, panier et politiques de rétention.
3. **Tests d’intégration SGBD (`tests/php/sql_integration.php`) :** exécution de `29` scénarios SQL (`T-01` à `T-29`) sur base temporaire isolée vérifiant directement les contraintes `CHECK`, les exceptions `SIGNAL SQLSTATE`, les rollbacks et les effets de bord des 16 triggers.
4. **Tests HTTP de bout en bout et de concurrence :** exécution sur serveur réel (`php -S`) adossé à MariaDB (`http_sql_e2e.php`, `concurrence_http.php`) afin de mesurer le comportement réel sous requêtes parallèles.
5. **Campagne de charge (`ENF-01`) :** mesure de latence SQL (`EXPLAIN` + 100 itérations) et HTTP (`200` requêtes par URL) sur une base peuplée à la volumétrie cible (`minishop_perf`).

### 4.2 Synthèse quantitative des campagnes de tests automatisés

Les résultats ci-dessous proviennent de la campagne complète exécutée sur machine virtuelle Debian 13 (PHP `8.4.27`, MariaDB `11.8.6`, commit `f9b614f` consigné dans `docs/pv-recette.md`), complétés par les contrôles statiques exécutés sur l’état courant du dépôt :

| Suite / Outil | Périmètre vérifié | Résultat mesuré | Statut |
|---|---|---|---|
| **Campagne globale VM (`scripts/generer_pv.php`)** | Orchestration complète des suites automatisées (`docs/pv-recette.md`) | **60 / 60 étapes** (code sortie `0`, `0` anomalie) | **VALIDÉ** |
| **Intégration SQL (`tests/php/sql_integration.php`)** | Contraintes, 18 procédures, 16 triggers (`T-01` à `T-29`) | **29 / 29 scénarios** | **VALIDÉ** |
| **E2E HTTP sur SQL (`tests/php/http_sql_e2e.php`)** | Parcours complets Visiteur, Client, Panier, Admin via HTTP + PDO | **105 / 105 assertions** | **VALIDÉ** |
| **Concurrence HTTP (`tests/php/concurrence_http.php`)** | 20 acheteurs concurrents pour 12 unités disponibles en stock | **8 / 8 scénarios** (`0` survente / `0` stock négatif) | **VALIDÉ** |
| **Tests unitaires PHPUnit (`tests/phpunit/`)** | Règles métier, panier, sécurité, helpers, conservation | **57 / 57 tests** | **VALIDÉ** |
| **E2E PHP sur JSON (`tests/php/run_tests.php`)** | Parité fonctionnelle sur pilote secondaire `JsonStore` | **338 / 338 assertions** | **VALIDÉ** |
| **Sécurité statique (`tests/security/controles.sh`)** | SQLi, XSS, secrets, bcrypt, IDOR, session, CSRF, RBAC, `.env`, PCI | **11 / 11 contrôles** (rejoué sur dépôt courant) | **CONFORME** |
| **Audit structurel & fiches UC (`tests/python/`, `verifier_fiches_uc.py`)** | Inventaire `git ls-files`, syntaxe, cohérence des 14 UC | **22 / 22** tests Python actifs + **46 / 46** contrôles UC | **CONFORME** |

*Tableau 6 — Bilan quantitatif des validations automatisées.*

### 4.3 Évaluation expérimentale des performances (`ENF-01`) et analyse `EXPLAIN`

L’exigence non fonctionnelle `ENF-01` fixe un temps de réponse perçu inférieur à `500 ms` au 95e centile (`p95`). La campagne du 17/11/2026 (`cb0f814`, `tests/perf/dernieres_mesures.txt`) sur une base `minishop_perf` dimensionnée au volume cible annuel (**236 produits**, **1 004 commandes**, **1 916 lignes de commande**, **1 998 traces de statut**) produit les résultats suivants :

| Niveau mesuré | Opération / Route évaluée | Moyenne (ms) | p95 (ms) | Plan `EXPLAIN` / Verdict |
|---|---|---:|---:|---|
| **SGBD (100 itérations)** | Catalogue (`v_catalogue`, 12 lignes) | 19,8 | 20,6 | `range` sur `idx_produit_visible` (`< 500 ms`) |
| **SGBD (100 itérations)** | Recherche plein texte `LIKE` | 19,7 | 20,6 | `range` sur `idx_produit_visible` (`< 500 ms`) |
| **SGBD (100 itérations)** | Historique client (10 dernières commandes) | 19,3 | 20,2 | `ref` sur `idx_commande_client` (`< 500 ms`) |
| **SGBD (100 itérations)** | Indicateurs du jour (`GROUP BY` par statut) | 19,7 | 20,5 | `ALL` sur `commande` (1 004 lignes, `< 500 ms`) |
| **SGBD (écriture atomique)** | `sp_create_order` + `sp_add_order_line` + `sp_confirm_order` | 44,0 | — | Verrou `FOR UPDATE` + triggers + port (`< 500 ms`) |
| **HTTP réel (200 req./URL)** | `/`, `/catalogue` (filtré/non filtré), `/recherche`, `/produit/...`, `/panier` | 1,2 – 6,1 | **6,0 global** | **0 réponse inattendue — CONFORME ENF-01** |

*Tableau 7 — Résultats expérimentaux de performance SGBD et HTTP (`tests/perf/dernieres_mesures.txt`).*

**Lecture critique de l’`EXPLAIN` :** l’analyse des plans d’exécution confirme l’efficacité des index sur le catalogue et l’historique client. En revanche, la requête d’agrégation des commandes par statut révèle un balayage complet (`type=ALL`) sur les `1 004` lignes de la table `commande`. À la volumétrie cible du projet (`20,5 ms` au `p95`), cet accès est négligeable en mémoire ; au-delà de quelques dizaines de milliers de commandes, l’ajout d’un index secondaire `INDEX idx_commande_statut (statut)` s’imposerait naturellement.

### 4.4 Analyse critique des biais, limites du prototype et risques résiduels

Dans une démarche scientifique rigoureuse, il est impératif d’expliciter ce que les mesures **ne prouvent pas** :
1. **Recette manuelle navigateur en attente :** si les `60` étapes automatisées sont vertes, les `200` lignes de recette manuelle consignées dans `docs/13-suivi-recette-uc.csv` (`164` scénarios UC + `36` transitions `RB-11`) demeurent au statut `NON JOUÉ`, et aucun test d’intégration sur l’hébergement cible partagé *LineServ* n’est revendiqué sans exécution dédiée.
2. **Biais de mesure locale des performances :** les benchmarks SQL et HTTP (`6 ms p95`) ont été conduits sur boucle locale (`127.0.0.1:18080`) d’une VM dédiée, excluant la latence réseau WAN, le chiffrement TLS et la contention d’un hébergement mutualisé.
3. **Écarts de sécurité assumés en maquette :** comme documenté dans `docs/01` §8.10, l’application n’intègre ni authentification multifacteur (MFA TOTP sur le rôle `SUPER`, recommandée par OWASP ASVS V4.3.1), ni limiteur de débit applicatif (*rate limiting*) sur les points de connexion ; de plus, les comptes de démonstration du seed doivent impérativement être remplacés avant toute mise en ligne publique.

---

## Chapitre 5 — Exploitation (*Build → Run*), analyse des écarts et bilan réflexif

### 5.1 Prérequis d’hébergement, déploiement (*Cutover / Rollback*) et MCO

La mise en service d’une version exploitable (`STACK.md`, `docs/26-transition-exploitation-maintenance-build-to-run.md`) impose un socle PHP **8.2+** (`json`, `mbstring`, `pdo_mysql`), MySQL **8.0.16+** ou MariaDB **10.6+**, et Apache 2.4 pointant son `DocumentRoot` exclusivement sur `public/` avec `mod_rewrite` activé (le serveur intégré PHP de `run.sh` étant strictement réservé au développement).

Pour une mise en production propre, cinq gestes d’exploitation sont requis :
1. Créer un fichier `.env` hors racine Web avec `APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql` et un compte SQL applicatif aux privilèges restreints (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `EXECUTE`).
2. Supprimer physiquement les utilitaires de benchmark/peuplement (`public/gen_volumes.php`, `public/mesurer.php`) du répertoire déployé par défense en profondeur.
3. Initialiser le schéma via une version préparée sans instruction destructive (`scripts/preparer_sql_lineserv.py`) sur hébergement mutualisé, ou via `sql/01`…`03` sur base dédiée vierge, puis remplacer le compte `SUPER` par défaut.
4. Prévoir une sauvegarde logique complète (`mysqldump --single-transaction --routines --triggers`) avant toute bascule afin de garantir un retour arrière (*rollback*) cohérent incluant déclencheurs et procédures.
5. Activer de manière contrôlée la maintenance de rétention (`scripts/nettoyage_donnees.php` et `purger_comptes.php`, qui exigent la double confirmation `--executer --validation-absence-gel`) et les copies chiffrées libsodium (`scripts/archive_restreinte.php`).

### 5.2 Analyse critique des écarts vis-à-vis du cahier des charges initial

Un projet logiciel mature ajuste sa conception initiale lorsque l’analyse de sécurité ou l’implémentation révèle une contradiction. Quatre évolutions notables ont été apportées et documentées par rapport à la rédaction initiale :
- **En-tête `X-Frame-Options: DENY` vs `SAMEORIGIN` :** plutôt que d’autoriser l’encapsulation en `<iframe>` même sur le même domaine, `public/index.php` et `public/.htaccess` imposent `DENY` (et une CSP `frame-ancestors 'none'` équivalente en défense), offrant une protection maximale contre le *clickjacking*.
- **Subdivision du rôle administrateur (`SUPER` / `GESTIONNAIRE`) :** au-delà du rôle unique suggéré par l’énoncé, l’application du principe de moindre privilège (OWASP ASVS V4.1.3) isole la gestion de l’équipe et des paramètres système.
- **Ajout de la 9e entité `STOCK_MOVEMENT_HISTORY` :** nécessaire pour garantir l’auditabilité et la justification obligatoire (`DELTA` / `SET` + motif) de chaque ajustement manuel de stock via `sp_adjust_stock`.
- **Déploiement à la racine `/` :** l’architecture cible un `DocumentRoot` dédié sur `public/` (`RewriteBase /`) afin d’éviter la fragilité des chemins relatifs sous sous-répertoire.

### 5.3 Bilan méthodologique, difficultés surmontées et apports pédagogiques

Plusieurs difficultés techniques structurantes ont jalonné la réalisation et constitué des vecteurs d’apprentissage majeurs :
- **Ordonnancement des déclencheurs SQL et capture tarifaire (`RB-06`, `T-11`) :** concilier l’insertion initiale d’une ligne de commande, le calcul automatique du total de ligne, le verrouillage d’immutabilité ultérieur et la restitution de stock lors d’une annulation sans créer de boucle de mutation SQL a nécessité une conception très fine des triggers `BEFORE`/`AFTER` et de leurs gardes.
- **Parité comportementale entre `SqlStore` et `JsonStore` :** maintenir les mêmes codes d’erreur métier (`BusinessError`), validations et transitions `RB-11` sur deux moteurs hétérogènes a démontré concrètement la valeur — et le coût de maintenance — d’une abstraction de persistance stricte.
- **Fiabilité documentaire et reproductibilité :** l’harmonisation des 14 diagrammes UML 2.5 avec le code réel (`docs/23-audit-uml-diagrammes.md`) et la mise en place de scripts de vérification automatique (`verifier_fiches_uc.py`, `test_audit_structurel.py`) ont permis d’éliminer les dérives fréquentes entre spécification et implémentation.

---

## Conclusion générale et perspectives

À l’issue de cette SAÉ 302, le projet **MiniShop** apporte une réponse concrète, mesurée et documentée à la problématique initiale : il est possible de bâtir en **PHP 8.2+ natif sans framework tiers** une application e-commerce modulaire, rapide (`6,0 ms p95` en HTTP) et résiliente face aux accès concurrents (`0` survente sous `20` requêtes parallèles). Le couplage entre une couche applicative MVC respectant le contrat `StoreInterface` et une base relationnelle enrichie de **18 procédures stockées** et **16 déclencheurs** garantit que les invariants métier essentiels (intégrité des stocks, traçabilité des statuts, immutabilité des factures) demeurent protégés même indépendamment des contrôleurs PHP.

Le bilan demeure lucide quant au statut du livrable : **MiniShop est validé en tant que maquette universitaire démonstrative et socle technique éprouvé automatiquement**, mais requiert avant toute exploitation commerciale réelle les compléments suivants, qui constituent nos perspectives d’évolution prioritaires :
1. **Intégration d’une passerelle de paiement en mode *sandbox*** (Stripe / Paybox) avec webhooks signés, journal de transactions bancaires et idempotence de confirmation.
2. **Durcissement d’authentification et anti-abus :** ajout d’un second facteur TOTP sur les comptes `SUPER`, limitation de débit (*rate limiting*) sur `/connexion` et `/admin/connexion`, et envoi asynchrone d’e-mails transactionnels via SMTP authentifié.
3. **Finalisation de la recette humaine et automatisation E2E :** exécution et consignation des `200` scénarios manuels navigateur (`docs/13-suivi-recette-uc.csv`), intégration de tests Playwright systématiques sur les parcours complets et ajout d’un index sur `commande(statut)` pour soutenir la montée en charge au-delà de 50 000 commandes.

---

## Bibliographie et webographie

### Normes, référentiels de sécurité et ingénierie logicielle
- **[1] OWASP Foundation.** *OWASP Top 10:2021 — The Ten Most Critical Web Application Security Risks* & *Application Security Verification Standard (ASVS) v4.0.3*. Disponible sur : `https://owasp.org/` (consulté en novembre 2026).
- **[2] ANSSI (Agence Nationale de la Sécurité des Systèmes d’Information).** *Recommandations pour la sécurisation des sites Web* et *Guide d’hygiène informatique*. Paris : SGDSN. Disponible sur : `https://cyber.gouv.fr/`.
- **[3] Object Management Group (OMG).** *OMG Unified Modeling Language (OMG UML), Version 2.5.1*. 2017. Disponible sur : `https://www.omg.org/spec/UML/`.
- **[4] AFNOR.** *Normes NF Z 41-006 (Présentation des thèses et documents assimilés) et NF Z 44-005 (Références bibliographiques)*. Paris : Association Française de Normalisation.

### Documentation technique des technologies employées
- **[5] The PHP Group.** *PHP Manual — PDO, Password Hashing (`password_hash`), Session Security*. Disponible sur : `https://www.php.net/manual/fr/`.
- **[6] MariaDB Foundation & Oracle Corp.** *MariaDB 10.6+ / MySQL 8.0 Reference Manual — InnoDB Locking Reads (`SELECT ... FOR UPDATE`), Stored Routines and Triggers*. Disponible sur : `https://mariadb.com/kb/` et `https://dev.mysql.com/doc/`.
- **[7] CNIL.** *Référentiel relatif aux traitements de données à caractère personnel mis en œuvre aux fins de gestion des activités commerciales* & *Guide de la sécurité des données personnelles*. 2024. Disponible sur : `https://www.cnil.fr/`.
- **[8] Mozilla Foundation (MDN Web Docs).** *Content Security Policy (CSP), X-Frame-Options & SameSite Cookies*. Disponible sur : `https://developer.mozilla.org/`.

### Corpus documentaire interne du projet MiniShop (SAÉ 302)
- **[9]** Polsinelli J., Gouiaa C., *Cahier des charges MiniShop (`docs/01-cahier-des-charges-MiniShop.md`)*, *Document de tests et de validation (`docs/02`)*, *Conception de la base de données et SQL (`docs/04`)*, *Note cybersécurité (`docs/07`)*, *Politique de conservation (`docs/18`)*, *Audit UML (`docs/23`)*, *Procès-verbal de recette (`docs/pv-recette.md`)* et *Mesures de performance (`tests/perf/dernieres_mesures.txt`)*, Université Côte d’Azur, 2026.

---

## Annexes

### Annexe A — Cartographie des 15 contrôleurs PHP et politiques de garde

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

### Annexe B — Inventaire complet des objets SQL (`sql/01` à `sql/03`)

- **Tables (9) :** `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre`.
- **Vues (3) :** `v_catalogue`, `v_etat_stock`, `v_commandes_client`.
- **Fonction (1) :** `fn_param`.
- **Procédures stockées (18) :** `sp_compute_shipping`, `sp_create_account`, `sp_get_credentials`, `sp_update_client`, `sp_search_products`, `sp_save_product`, `sp_delete_product`, `sp_adjust_stock`, `sp_save_category`, `sp_delete_category`, `sp_create_order`, `sp_add_order_line`, `sp_create_order_from_basket`, `sp_confirm_order`, `sp_update_order_status`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_revenue_report`.
- **Déclencheurs (16) :**
  - *Lignes de commande :* `trg_ligne_controle_insert`, `trg_ligne_decrement_stock`, `trg_ligne_restore_stock`, `trg_ligne_immutable`, `trg_ligne_prix_snapshot`.
  - *Produits & catégories :* `trg_produit_ttc`, `trg_produit_ttc_update`, `trg_produit_regles`, `trg_produit_delete`, `trg_categorie_slug`, `trg_categorie_delete`.
  - *Commandes & audit :* `trg_commande_transition_statut`, `trg_commande_controle_insert`, `trg_commande_delete`, `trg_history_statut`, `trg_history_creation`.

### Annexe C — Matrice synthétique de traçabilité Exigences ↔ Réalisation ↔ Preuves

| Bloc d’évaluation SAÉ 302 | Exigence clé | Réalisation dans le dépôt | Preuve de vérification |
|---|---|---|---|
| **i. Cahier des charges & UML** | ≥ 14 UC, règles métier, sécurité, diagrammes UML | `docs/01`, `docs/diagrams/*.puml` (`14` diagrammes alignés) | `docs/23-audit-uml-diagrammes.md`, `verifier_fiches_uc.py` (**46/46**) |
| **ii. Modélisation BD & SQL** | MCD/MLD 3FN, ≥ 5 procédures, ≥ 5 triggers | `docs/04`, `sql/01`…`04` (**18** proc., **16** triggers, **9** tables) | `sql_integration.php` (**29/29** sur MariaDB 11.8.6) |
| **iii. Application Web & sécurité** | MVC PHP natif, PDO préparé, CSRF, bcrypt, IDOR, RBAC | `app/`, `public/` (**15** contrôleurs, `StoreInterface`) | `tests/security/controles.sh` (**11/11**), `http_sql_e2e.php` (**105/105**) |
| **iv. Tests, perf & soutenance** | Stratégie de tests, concurrence, `ENF-01`, GitLab | `tests/`, `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt` | `60/60` VM, `8/8` concurrence, `HTTP p95 = 6,0 ms` |

*Tableau 8 — Synthèse de traçabilité destinée au jury de soutenance.*
