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

> **Objet du document.** Ce mémoire constitue la synthèse technique, scientifique et méthodologique intégrale du projet **MiniShop**. Il rassemble dans un document unique, directement exploitable par le jury et l’enseignant référent : l’analyse complète du cahier des charges officiel, les modélisations UML 2.5 et Merise, la démonstration formelle de normalisation 3FN, l’architecture MVC sans framework et les mécanismes de sécurité vérifiés, les résultats expérimentaux de tests, de concurrence et de performance, ainsi que l’analyse critique des limites et le dossier d’exploitation.

\newpage

## Remerciements

Nous adressons nos sincères remerciements à **Mr Thanh-Phuong Nguyen**, notre enseignant référent, pour la précision du cahier des charges de cette SAÉ 302, ses conseils exigeants sur la modélisation relationnelle et l’architecture transactionnelle, ainsi que son accompagnement constant en matière de cybersécurité applicative et de rigueur documentaire.

Nous remercions également l’ensemble de l’équipe pédagogique du département Informatique de l’IUT de Nice Côte d’Azur pour la qualité des enseignements dispensés en conception de bases de données, SQL avancé, programmation orientée objet PHP, développement Web côté client, cybersécurité et méthodologie de validation logicielle, ainsi que pour la mise à disposition des environnements d’hébergement pédagogique.

Enfin, nous exprimons notre gratitude aux membres du jury qui consacrent leur temps et leur expertise à l’évaluation de ce mémoire et de la soutenance associée.

\newpage

## Résumé

Ce rapport présente l’étude complète, la conception, la sécurisation, l’implémentation et la validation expérimentale de **MiniShop**, une plateforme Web de commerce électronique dotée d’un back-office d’administration, réalisée dans le cadre de la SAÉ 302 du BUT Informatique à l’IUT de Nice Côte d’Azur. Répondant intégralement aux spécifications du cahier des charges officiel, le projet mobilise conjointement les onze compétences fondamentales attendues : modélisation Merise et UML 2.5, SQL avancé sous MySQL/MariaDB, développement PHP 8.2+ orienté objet sans framework, accès aux données par PDO, interactions progressives en JavaScript natif, gestion durcie des sessions, sécurité applicative selon l’OWASP et l’ANSSI, procédures stockées, déclencheurs SQL et validation expérimentale multi-niveaux.

Notre problématique d’ingénierie consiste à garantir l’intégrité stricte des stocks, l’immutabilité tarifaire des commandes passées et la résistance aux vulnérabilités Web courantes sans recourir à un framework industriel tiers, tout en offrant une portabilité vers un moteur de persistance secondaire JSON via le contrat `StoreInterface`. La base relationnelle implémente **9 tables normalisées en 3FN** (incluant deux dénormalisations comptables volontaires et verrouillées par déclencheurs), **3 vues SQL**, **1 fonction** (`fn_param`), **18 procédures stockées** (minimum imposé : `5`) et **16 déclencheurs** (minimum imposé : `5`).

Les campagnes automatisées exécutées sur machine virtuelle Debian 13 (PHP `8.4.27`, MariaDB `11.8.6`) valident **60/60 étapes** de recette, **29/29 scénarios d’intégration SQL**, **105/105 vérifications HTTP SQL de bout en bout**, **8/8 scénarios de concurrence** sans survente (`20` acheteurs simultanés pour `12` unités en stock), **57/57 tests unitaires PHPUnit**, **338/338 assertions E2E JSON**, **11/11 contrôles statiques de sécurité** et une latence HTTP au 95e centile de **6,0 ms** sous volumétrie cible (`236` produits et `1 004` commandes), très en deçà du seuil de `500 ms`. L’analyse critique précise enfin de façon transparente les limites du périmètre maquette : simulation du paiement bancaire, journalisation locale des messages de contact sans serveur SMTP, absence de MFA sur le rôle `SUPER` et `200` scénarios manuels navigateur restant au statut `NON JOUÉ`.

**Mots-clés :** génie logiciel, architecture MVC sans framework, base de données relationnelle, normalisation 3FN, procédures stockées, déclencheurs SQL, sécurité OWASP, validation expérimentale.

\newpage

## Sommaire

- **Introduction générale**
- **Chapitre 1 — Spécification du système : contexte, acteurs, fonctionnalités, règles métier et état de l’art**
  - 1.1 Présentation du projet, objectifs et les 11 compétences mobilisées
  - 1.2 Acteurs et fonctionnalités demandées (Visiteur, Client, Administrateur)
  - 1.3 Règles métier officielles (`RB-01` à `RB-11`) et extensions (`RB-12` à `RB-20`)
  - 1.4 Contraintes techniques (`CT-01` à `CT-12`), état de l’art et benchmarks comparatifs (PHP, SGBD et choix de MariaDB)
    - 1.4.1 Contraintes techniques et comparatif des architectures PHP
    - 1.4.2 Benchmark comparatif : PHP 8.2+ face aux autres langages serveur
    - 1.4.3 Benchmark comparatif des SGBD : MySQL / MariaDB face aux autres moteurs
    - 1.4.4 Pourquoi choisir MariaDB (compatibilité MySQL 8.0) pour MiniShop ?
  - 1.5 Organisation du travail, découpage WBS et gouvernance qualité
- **Chapitre 2 — Modélisation UML 2.5 et spécifications de sécurité**
  - 2.1 Diagramme de cas d’utilisation et les 14 cas d’utilisation minimum
  - 2.2 Descriptions textuelles détaillées de 3 cas d’utilisation (hors « Passer une commande »)
  - 2.3 Diagramme de classes et justification des choix de conception objet
  - 2.4 Les 4 diagrammes de séquences (authentification, recherche, panier, commande)
  - 2.5 Réponses détaillées aux 5 spécifications de sécurité du cahier des charges
  - 2.6 Architecture 3 couches MVC adaptée au système
- **Chapitre 3 — Analyse et conception de la base de données (Merise, Normalisation et SQL)**
  - 3.1 Modèle Conceptuel des Données (MCD) : entités, associations et cardinalités justifiées
  - 3.2 Modèle Logique des Données (MLD) et vues relationnelles
  - 3.3 Normalisation complète : dépendances fonctionnelles, 1FN/2FN/3FN, PK/FK et redondances
  - 3.4 Script SQL d’installation et jeu de données (`sql/01` à `sql/04`)
  - 3.5 Procédures stockées (18 livrées) et réponse complète à la question pédagogique du sujet
  - 3.6 Déclencheurs (16 livrés) : couverture de « Trigger 1 — Stock » et « Trigger 2 — Historique »
- **Chapitre 4 — Réalisation applicative, design system, stratégie de tests et performances**
  - 4.1 Architecture logicielle MVC complète et double persistance (`ARCHITECTURE.md`)
    - 4.1.1 Les 8 couches applicatives et le cycle de vie d’une requête HTTP
    - 4.1.2 Contrat `StoreInterface` : parité et limites entre `SqlStore` et `JsonStore`
  - 4.2 Étude complète de design, direction artistique et accessibilité (`docs/DA.md`)
    - 4.2.1 Benchmark des 5 acteurs français du e-commerce et positionnement
    - 4.2.2 Palette WCAG 2.1 AA, typographie sourcée (`Lato` / `SIL OFL 1.1`) et grille
    - 4.2.3 Iconographie (`Lucide` / `ISC`), photographie à 2 niveaux, 12 composants, UTC et i18n
  - 4.3 Stratégie de tests complète en pyramide (`docs/11`) et exigences `ENF-01` à `ENF-22`
    - 4.3.1 La pyramide des tests à 9 étages et sa justification méthodologique
    - 4.3.2 Protocoles d’isolation SQL, contrats statiques, couverture honnête et garde de verdict
  - 4.4 Campagne VM Debian 13 et mesures complètes de performances (`tests/perf/dernieres_mesures.txt`)
    - 4.4.1 Bilan des suites automatisées et mesures détaillées SQL (`EXPLAIN`) et HTTP (`p95 = 6 ms`)
    - 4.4.2 Mise en perspective des mesures VM face aux piles concurrentes
  - 4.5 Analyse critique des biais, limites du prototype et risques résiduels
- **Chapitre 5 — Exploitation (*Build → Run*), conformité légale, RGPD, gouvernance GitLab et barème**
  - 5.1 Prérequis d’hébergement, procédure de déploiement, retour arrière et MCO
  - 5.2 Conformité juridique e-commerce, audit des traceurs et politique de conservation RGPD (`docs/12` & `docs/18`)
    - 5.2.1 Cadre légal e-commerce français et traduction dans les pages réglementaires
    - 5.2.2 Audit technique des cookies, du stockage local et minimisation des journaux
    - 5.2.3 Politique de conservation RGPD, maintenance CLI (`db_init.php --nettoyage`) et archivage libsodium
    - 5.2.4 Les 7 points bloquants audités avant ouverture commerciale réelle (dont rétractation du 19 juin 2026)
  - 5.3 Analyse des écarts vis-à-vis du cahier des charges initial et bilan réflexif
  - 5.4 Gouvernance GitLab, `README.md`, organisation de soutenance et grille de conformité au barème
- **Chapitre 6 — Feuille de route d’industrialisation : vers une *marketplace* professionnelle, intelligente et automatisée (modèle « Amazon »)**
  - 6.1 Passage à l’échelle industrielle : paiement PSP, cache distribué, recherche et observabilité
  - 6.2 Évolution du modèle 3FN vers une *Marketplace* multi-vendeurs (*Seller Central* et *Buy Box*)
  - 6.3 Moteur de recommandation hybride (analyse du panier, filtrage collaboratif et sémantique)
  - 6.4 Automatisation du back-office vendeur, réapprovisionnement prédictif et logistique
  - 6.5 Trajectoire de migration incrémentale en 4 paliers (*Strangler Fig Pattern*)
- **Conclusion générale et perspectives**
- **Bibliographie et webographie**
- **Annexes**
  - Annexe A — Fiches synthétiques des 14 cas d’utilisation (`UC-01` à `UC-14`)
  - Annexe B — Cartographie des 15 contrôleurs PHP et politiques d’accès
  - Annexe C — Répertoire complet des 9 tables, 3 vues, 1 fonction, 18 procédures et 16 triggers SQL
  - Annexe D — Synthèse des 29 scénarios SQL (`T-01` à `T-29`) et des 10 scénarios offensifs (`S-01` à `S-10`)
  - Annexe E — Requêtes SQL de preuve de non-contradiction des redondances

\newpage

## Table des figures

- **Figure 1** — Diagramme de cas d’utilisation du processus de commande (`docs/diagrams/cas_utilisation_commandes.png`)
- **Figure 2** — Diagramme d’activité du parcours de validation d’une commande (`docs/diagrams/activite_passer_commande.png`)
- **Figure 3** — Diagramme de classes de l’application PHP (`docs/diagrams/diagramme_classes.png`)
- **Figure 4** — Diagramme de séquence : Authentification (`docs/diagrams/seq_authentification.png`)
- **Figure 5** — Diagramme de séquence : Rechercher un produit (`docs/diagrams/seq_recherche_produit.png`)
- **Figure 6** — Diagramme de séquence : Ajout au panier (`docs/diagrams/seq_ajout_panier.png`)
- **Figure 7** — Diagramme de séquence : Passer une commande (`docs/diagrams/seq_passer_commande.png`)
- **Figure 8** — Architecture 3 couches MVC et double persistance (`docs/diagrams/architecture_mvc.png`)
- **Figure 9** — Modèle Conceptuel des Données à 9 entités (`docs/diagrams/mcd_minishop.png`)
- **Figure 10** — Modèle Logique des Données normalisé (`docs/diagrams/mld_minishop.png`)
- **Figure 11** — Automate à 6 états du cycle de vie d’une commande (`docs/diagrams/etats_commande.png`)

\newpage

## Table des tableaux

- **Tableau 1** — Matrice des 11 compétences pédagogiques du sujet dans MiniShop
- **Tableau 2** — Synthèse des fonctionnalités demandées et livrées par acteur
- **Tableau 3** — Règles métier officielles (`RB-01` à `RB-11`) et extensions (`RB-12` à `RB-20`)
- **Tableau 4** — Contraintes techniques (`CT-01` à `CT-12`) et état de l’art PHP
- **Tableau 5** — Comparatif des stratégies d’accès aux données (`StoreInterface` vs ORM)
- **Tableau 6** — Benchmark multicritères : PHP 8.2+ natif face aux autres langages serveur
- **Tableau 7** — Benchmark multicritères : MySQL / MariaDB face aux autres SGBD
- **Tableau 8** — Comparatif technique ciblé MySQL 8.0 vs MariaDB 10.6–11.8
- **Tableau 9** — Correspondance des 14 cas d’utilisation minimum du sujet
- **Tableau 10** — Réponses techniques aux 5 spécifications de sécurité du sujet
- **Tableau 11** — Justification détaillée des cardinalités du MCD
- **Tableau 12** — Dépendances fonctionnelles (DF) identifiées table par table
- **Tableau 13** — Vérification formelle 1FN, 2FN et 3FN par relation
- **Tableau 14** — Justification des clés primaires (PK), uniques (UK) et étrangères (FK)
- **Tableau 15** — Inventaire des redondances maîtrisées et mécanismes de contrôle
- **Tableau 16** — Réponse aux 5 axes pédagogiques sur les procédures stockées
- **Tableau 17** — Correspondance entre les exemples du sujet et les objets SQL livrés
- **Tableau 18** — Cartographie des 8 couches applicatives et outillage (`ARCHITECTURE.md`)
- **Tableau 19** — Benchmark ergonomique des 5 concurrents et positionnement (`docs/DA.md`)
- **Tableau 20** — Palette chromotypographique et ratios de contraste WCAG 2.1 AA
- **Tableau 21** — Les 12 composants UI, règles WCAG 2.1 AA et microcopy éditoriale
- **Tableau 22** — Synthèse des exigences non fonctionnelles (`ENF-01` à `ENF-22`)
- **Tableau 23** — Pyramide de validation complète à 9 étages (`docs/11-strategie-tests.md`)
- **Tableau 24** — Bilan chiffré des campagnes de tests automatisés et VM Debian 13
- **Tableau 25** — Mesures SQL détaillées (`minishop_perf`) et analyse des plans `EXPLAIN`
- **Tableau 26** — Mesures HTTP détaillées par route (`N = 200` requêtes/URL, `ENF-01`)
- **Tableau 27** — Mise en perspective des mesures VM face aux piles alternatives
- **Tableau 28** — Cadre légal e-commerce français (LCEN, Consommation, CNIL)
- **Tableau 29** — Audit technique des cookies, du `localStorage` et des journaux
- **Tableau 30** — Politique de conservation RGPD et limites assumées (`docs/18`)
- **Tableau 31** — Les 7 points bloquants avant toute mise en vente commerciale réelle (`docs/12`)
- **Tableau 32** — Contrôle de conformité face aux livrables et pénalités du barème
- **Tableau 33** — Briques d’industrialisation pour une plateforme de production
- **Tableau 34** — Extension du modèle 3FN vers une *Marketplace* multi-vendeurs
- **Tableau 35** — Architecture du moteur de recommandation hybride (V1 à V3)
- **Tableau 36** — Automatisations back-office vendeur, logistique et anti-fraude
- **Tableau 37** — Plan de migration incrémental en 4 paliers (MiniShop → *Marketplace*)

\newpage

## Glossaire des sigles et acronymes

| Sigle | Définition exacte |
|---|---|
| **3FN** | Troisième Forme Normale (dépendances fonctionnelles directes de la clé primaire) |
| **ACID** | Atomicité, Cohérence (*Consistency*), Isolation, Durabilité |
| **ANSSI / OWASP** | Agence Nationale de la Sécurité des Systèmes d’Information / *Open Worldwide Application Security Project* |
| **ASVS** | *Application Security Verification Standard* (standard de vérification de sécurité OWASP) |
| **CSP / CSRF** | *Content Security Policy* / *Cross-Site Request Forgery* |
| **CT / ENF** | Contrainte Technique / Exigence Non Fonctionnelle |
| **DF** | Dépendance Fonctionnelle (`X -> Y`) |
| **E2E** | *End-to-End* (test de bout en bout simulant un parcours utilisateur complet) |
| **IDOR** | *Insecure Direct Object Reference* (accès direct non autorisé à une ressource par identifiant) |
| **MCD / MLD** | Modèle Conceptuel des Données / Modèle Logique des Données (méthode Merise) |
| **MCO** | Maintien en Condition Opérationnelle |
| **MVC** | *Model–View–Controller* (architecture applicative en 3 couches logiques) |
| **PDO** | *PHP Data Objects* (couche d’abstraction et de requêtes préparées en PHP) |
| **PK / FK / UK** | *Primary Key* (clé primaire) / *Foreign Key* (clé étrangère) / *Unique Key* (clé unique) |
| **RB / SEC / UC** | Règle Métier / Exigence de Sécurité / Cas d’Utilisation (*Use Case*) |
| **RBAC** | *Role-Based Access Control* (contrôle d’accès basé sur les rôles) |
| **RGPD** | Règlement Général sur la Protection des Données |
| **SAÉ** | Situation d’Apprentissage et d’Évaluation (référentiel national BUT) |
| **SGBD / SQL** | Système de Gestion de Base de Données / *Structured Query Language* |
| **UML** | *Unified Modeling Language* (version 2.5 de l’OMG) |
| **UTC** | *Coordinated Universal Time* (temps universel coordonné utilisé comme référence unique en base) |
| **WCAG** | *Web Content Accessibility Guidelines* (référentiel d’accessibilité Web niveau AA) |
| **XSS** | *Cross-Site Scripting* (injection de code côté client) |

\newpage

## Introduction générale

### Contexte pédagogique et genèse du besoin

Dans le cadre du **Bachelor Universitaire de Technologie (BUT) Informatique** à l’IUT de Nice Côte d’Azur, la **Situation d’Apprentissage et d’Évaluation SAÉ 302** confronte les étudiants à un projet complet d’ingénierie des systèmes d’information : concevoir, modéliser, implémenter, sécuriser et valider une application de commerce électronique nommée **MiniShop**. L’entreprise commanditaire souhaite remplacer un suivi artisanal de ses ventes — reposant sur des tableurs partagés dépourvus d’atomicité et d’historique — par une plateforme Web structurée comportant un front-office destiné aux visiteurs et clients, ainsi qu’un back-office réservé aux gestionnaires habilités.

Ce fonctionnement initial sur tableurs engendre trois défaillances critiques caractéristiques des systèmes non transactionnels : (i) l’absence d’exclusion mutuelle sur les quantités disponibles, provoquant des *surventes* lorsque plusieurs clients commandent simultanément les dernières unités ; (ii) l’altération rétroactive des montants historiques lorsque le prix d’un article est modifié au catalogue ; et (iii) l’absence de journal d’audit fiable retraçant les changements de statut d’une commande ou les ajustements de stock.

Au-delà de la livraison d’un site fonctionnel, le cahier des charges officiel de la SAÉ 302 poursuit une ambition pédagogique explicite : démontrer la maîtrise simultanée des couches conceptuelles (Merise, UML 2.5), des mécanismes internes d’un SGBD relationnel (contraintes d’intégrité, vues, procédures stockées, déclencheurs), du développement **PHP 8.2+ orienté objet sans framework** via PDO, du JavaScript natif côté client, et de la cybersécurité applicative selon les standards actuels.

### Problématique scientifique et technique

Notre travail s’articule autour de la problématique d’ingénierie suivante :

> **Comment concevoir, sécuriser et valider expérimentalement une application Web e-commerce en PHP orienté objet sans framework tiers qui garantisse formellement, au niveau du SGBD comme de la couche applicative, l’intégrité stricte des stocks sous accès concurrents, l’immutabilité financière des commandes et l’immunité contre les vulnérabilités Web classiques, tout en conservant une portabilité vers un pilote de persistance secondaire ?**

Cette problématique se décline en quatre verrous scientifiques et techniques :
1. **Verrou d’intégrité transactionnelle et de concurrence :** empêcher mathématiquement tout passage en stock négatif sous requêtes simultanées grâce au verrouillage pessimiste InnoDB (`SELECT ... FOR UPDATE`), tout en figeant contractuellement les prix et frais de port à l’instant de validation.
2. **Verrou architectural :** maintenir un découplage strict entre routage HTTP, règles métier et stockage (`SQL` par défaut, `JSON` en mode secondaire portable) via l’interface `StoreInterface`, sans recourir à un ORM ni à un conteneur de dépendances tiers.
3. **Verrou sécuritaire :** éliminer dès la conception les vulnérabilités majeures recensées par l’OWASP Top 10 (injections SQL, XSS, CSRF, IDOR, fixation de session, élévation de privilèges) et vérifier mécaniquement ces propriétés à chaque évolution.
4. **Verrou de validation :** démontrer ces garanties par des expériences chiffrées et reproductibles (tests d’intégration SQL, E2E HTTP, tests de concurrence, mesure de latence `p95`), tout en distinguant rigoureusement les preuves exécutées des scénarios manuels non encore joués.

### Objectifs du projet et structuration du mémoire

Afin de répondre de manière exhaustive et vérifiable à **toutes les questions, consignes et rubriques du cahier des charges officiel**, ce mémoire est organisé en cinq chapitres complémentaires suivis de cinq annexes techniques :
- Le **Chapitre 1** pose la spécification complète du système : contexte, objectifs, acteurs, fonctionnalités, les 11 règles métier officielles et leurs extensions, les contraintes techniques et un état de l’art comparatif justifiant chaque choix d’architecture.
- Le **Chapitre 2** développe la modélisation UML 2.5 (cas d’utilisation dont **3 descriptions textuelles détaillées hors « Passer une commande »**, diagramme d’activité, diagramme de classes justifié, **les 4 diagrammes de séquences exigés**), les **5 réponses de sécurité** illustrées par des extraits du code source, ainsi que l’architecture 3 couches MVC.
- Le **Chapitre 3** traite intégralement l’analyse et la conception de la base de données : MCD à 9 entités et cardinalités justifiées, MLD et vues, **démonstration complète des 5 points de normalisation (DF, 1FN/2FN/3FN, PK/FK, redondances)**, scripts DDL/données, **18 procédures stockées + réponse détaillée aux 5 axes de la question pédagogique**, et **16 déclencheurs** couvrant *Stock* et *Historique*.
- Le **Chapitre 4** expose la réalisation applicative, l’ergonomie et la direction artistique (`DA.md`), la couverture des exigences non fonctionnelles (`ENF-01` à `ENF-22`), les campagnes expérimentales sur VM Debian 13 (concurrence, SQL, E2E, `ENF-01` à `6,0 ms p95`) et l’analyse critique des limites.
- Le **Chapitre 5** présente l’exploitation (*Build → Run*), la politique de conservation RGPD, l’analyse critique des écarts vis-à-vis du CDC initial, la gouvernance GitLab, la préparation de soutenance et la grille complète de conformité au barème officiel.
- Le **Chapitre 6** projette enfin l’architecture MiniShop vers un système industriel professionnel multi-vendeurs de type *marketplace* (« Amazon »), en détaillant le passage à l’échelle, l’extension 3FN multi-vendeurs, un moteur de recommandation hybride et l’automatisation avancée des tâches vendeurs et logistiques.

\newpage

## Chapitre 1 — Spécification du système : contexte, acteurs, fonctionnalités, règles métier et état de l’art

### 1.1 Présentation du projet, objectifs et les 11 compétences mobilisées

**MiniShop** est une boutique e-commerce spécialisée en matériel informatique, audio, accessoires et objets connectés, articulée autour d’un front-office de vente et d’un back-office d’administration. Sur le plan opérationnel, le système poursuit trois objectifs quantifiables : (1) offrir aux visiteurs et clients une navigation fluide respectant le seuil de latence `ENF-01` (`p95 < 500 ms`), (2) garantir l’absence de survente et la fiabilité comptable des commandes et factures, et (3) doter l’équipe de gestion d’outils traçables pour administrer catalogue, catégories, mouvements de stock et expéditions.

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

Le cahier des charges définit **trois acteurs principaux** (`Visiteur`, `Client`, `Administrateur`), que notre conception raffine en dissociant l’acteur `Administrateur` en deux niveaux de privilèges (`GESTIONNAIRE` et `SUPER`) conformément au principe de moindre privilège (OWASP ASVS V4.1.3) :
- **Le Visiteur** : consulte librement le catalogue, les catégories, la recherche multicritères, les fiches produits, les mentions légales et la page de contact, et constitue un panier temporaire en session.
- **Le Client authentifié** : gère son compte et son mot de passe, consulte son historique de commandes, transforme son panier en commande validée et peut demander l’annulation avant expédition.
- **L’Administrateur `GESTIONNAIRE`** : gère au quotidien les produits, les catégories, les mouvements de stock (avec motif obligatoire) et le cycle d’expédition des commandes.
- **L’Administrateur `SUPER`** : dispose en plus du privilège exclusif d’administrer l’équipe d’administration (`/admin/equipe`) et les paramètres globaux de la boutique (`/admin/parametres` : TVA, frais de port, seuil de gratuité, durée de conservation).

| Acteur | Fonctionnalités exigées par le sujet officiel | Extensions livrées dans MiniShop |
|---|---|---|
| **Visiteur** | (1) Consulter les catégories ; (2) Rechercher un produit ; (3) Filtrer les produits ; (4) Consulter la fiche détaillée d’un produit ; (5) Créer un compte. | Tri multicritères, pagination plafonnée (`12` à `60`), panier visiteur conservé jusqu’à connexion, préférences langue/UTC, formulaire contact. |
| **Client** | (1) Se connecter / se déconnecter ; (2) Modifier ses informations ; (3) Ajouter des produits au panier ; (4) Modifier les quantités ; (5) Supprimer des produits du panier ; (6) Valider une commande ; (7) Consulter son historique de commandes. | Changement sécurisé de mot de passe, adresse de livraison structurée pré-remplie, annulation client en 2 étapes avant expédition (`UC-09`). |
| **Administrateur** (`GESTIONNAIRE` & `SUPER`) | (1) Ajouter / modifier / supprimer une catégorie ; (2) Ajouter / modifier / supprimer un produit ; (3) Modifier le stock ; (4) Consulter les commandes ; (5) Modifier le statut d’une commande. | Tableau de bord CA/alertes, ajustement de stock `DELTA` avec motif tracé (`stock_movement_history`), annulation admin 2 étapes, gestion équipe et paramètres réservée à `SUPER`. |

*Tableau 2 — Correspondance exhaustive entre les fonctionnalités demandées et les fonctionnalités livrées.*

**Périmètre hors maquette assumé :** afin de concentrer l’effort d’ingénierie sur l’architecture transactionnelle et la sécurité, les paiements bancaires et l’expédition logistique sont simulés, et les messages envoyés via `/contact` sont journalisés localement (`var/log/contact.jsonl`) sans serveur SMTP externe.

### 1.3 Règles métier officielles (`RB-01` à `RB-11`) et extensions (`RB-12` à `RB-20`)

Les **11 contraintes spécifiques du métier** inscrites au sujet officiel (p. 2) sont toutes formalisées, appliquées à deux niveaux (PHP + SGBD selon `ENF-19`) et associées à des scénarios de test dédiés :

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

### 1.4 Contraintes techniques (`CT-01` à `CT-12`), état de l’art et benchmarks comparatifs

Un jury universitaire ou d’école d’ingénieurs attend que les choix technologiques ne soient pas subis comme une simple contrainte d’énoncé, mais **justifiés face à l’état de l’art**. Cette section expose d’abord les contraintes du socle PHP, puis confronte PHP 8.2+ aux autres langages serveur, MySQL/MariaDB aux autres SGBD, et explicite enfin les raisons techniques et opérationnelles du recours à **MariaDB** en compatibilité **MySQL 8.0**.

#### 1.4.1 Contraintes techniques et comparatif des architectures PHP

Les douze contraintes techniques du cahier des charges (`CT-01` à `CT-12`) imposent notamment PHP `8.2+` sans framework tiers (`CT-01`), PDO exclusif (`CT-02`), MySQL `8.0.16+` / MariaDB `10.6+` (`CT-03`), HTML5/CSS3/JS natif sans dépendance CDN (`CT-04`), routage propre sous `public/` (`CT-05`), séparation stricte front-office / back-office (`CT-06`) et traçabilité complète GitLab (`CT-07` à `CT-12`). Les Tableaux 4 et 5 synthétisent les arbitrages réalisés au sein même de l’écosystème PHP.

| Critère d’évaluation | PHP 8.2+ natif (`declare(strict_types=1)`) — *Choix retenu* | Framework lourd (Symfony 7 / Laravel 11) | Micro-framework (Slim) |
|---|---|---|---|
| **Conformité pédagogique SAÉ** | Maximale : zéro dépendance opaque, maîtrise complète du routeur, des sessions, du CSRF et de PDO | Moyenne : masque les mécanismes HTTP/CSRF/SQL sous-jacents | Bonne, mais introduit Composer et middlewares tiers |
| **Surface d’attaque & dépendances** | Minimale : `0` bibliothèque tierce en exécution (`vendor/` absent en production, `CT-01`/`ENF-20`) | Élevée : dizaines de paquets externes à auditer | Modérée |
| **Latence à froid** | Excellente (`p95 = 6,0 ms` mesuré sans infrastructure complexe) | Plus lourde (initialisation conteneur DI) | Bonne |
| **Effort d’implémentation** | Plus élevé : écriture manuelle du routeur blanc, du moteur de vues et des gardes | Faible grâce aux composants standards | Intermédiaire |

*Tableau 4 — Contraintes techniques (`CT-01`–`CT-12`) et comparatif multicritères du socle PHP.*

| Critère d’évaluation | Contrat `StoreInterface` (`SqlStore` PDO + `JsonStore`) — *Choix retenu* | ORM complet (Doctrine / Eloquent) | SQL procédural mélangé aux vues |
|---|---|---|---|
| **Exploitation des triggers & procédures** | Directe via `SqlStore` avec `ATTR_EMULATE_PREPARES = false` et détection de capacités | Complexe : l’ORM maintient un cache d’entités désynchronisé par les triggers | Directe mais couple fortement l’HTML au SQL |
| **Portabilité et résilience de démo** | Totale : bascule `STORAGE_DRIVER=json` hors ligne sans SGBD | Dépendance stricte au SGBD | Nulle sans SGBD |
| **Coût de maintenance** | Modéré : maintien de la parité entre `SqlStore` et `Triggers.php` | Faible sur du CRUD simple | Faible maintenabilité (dette technique) |

*Tableau 5 — Comparatif des stratégies d’accès aux données (`StoreInterface` vs ORM).*

\newpage

#### 1.4.2 Benchmark comparatif : PHP 8.2+ natif face aux autres langages serveur

Le choix d’un langage côté serveur pour un site e-commerce transactionnel conditionne à la fois la latence par requête, l’isolation mémoire entre clients, la simplicité d’exploitation sur hébergement mutualisé et la facilité de raccordement à un SGBD relationnel. Le Tableau 6 compare **PHP 8.2/8.4** (avec OPcache et typage strict) à cinq écosystèmes concurrents majeurs du développement Web moderne.

| Critère d’ingénierie | **PHP 8.2 / 8.4 (OPcache, sans framework)** — *Retenu* | **Python 3.12 (Django / FastAPI)** | **Node.js 22 / TypeScript (Express / Nest)** | **Java 21 (Spring Boot)** | **Go 1.23 (`net/http`)** |
|---|---|---|---|---|---|
| **Modèle d’exécution** | *Shared-nothing* par requête (PHP-FPM + bytecode en mémoire partagée OPcache) | Processus WSGI/ASGI persistants (souvent limités par le GIL sur CPU pur hors *async*) | Boucle d’événements mono-thread (*event loop*) asynchrone | JVM persistante multi-threads avec compilateur JIT C2 | Binaire natif compilé avec goroutines légères |
| **Empreinte mémoire au repos** | Très faible (~`20`–`40 Mo` par worker FPM, quasi nulle hors pic sous Apache) | Modérée (`60`–`150 Mo` par worker Gunicorn/Uvicorn) | Modérée (`50`–`120 Mo` par instance Node) | Élevée (`250`–`600 Mo` pour une JVM Spring Boot) | Très faible (`15`–`30 Mo` pour tout le serveur) |
| **Temps de démarrage à froid** | Quasi instantané (ms, OPcache préchargé) | Rapide (quelques centaines de ms) | Rapide (< 500 ms) | Lent (`2` à `8 s` de démarrage contexte IoC) | Quasi instantané (< 50 ms) |
| **Isolation des fautes & sessions** | Natif : une fuite ou exception fatale dans une requête n’affecte jamais les autres requêtes | Processus partagé : une fuite mémoire ou un état global mal nettoyé pollue le worker | Critique : une exception synchrone non interceptée fait tomber tout le processus Node | Bonne (isolation par thread), mais pile complexe | Bonne, mais gestion manuelle de l’état |
| **Accès SGBD relationnel** | Natif et standardisé via **PDO** (`ATTR_EMULATE_PREPARES => false`, zéro ORM) | DB-API 2.0 / SQLAlchemy / ORM Django | Pilotes `mysql2` / Prisma / Drizzle (asynchrones) | JDBC + JPA/Hibernate (lourd avec triggers) | `database/sql` natif |
| **Déploiement hébergement académique (LineServ / Apache)** | **Direct (`100 %` compatible)** : simple dépôt FTP/Git derrière `mod_rewrite` et `.htaccess` | Nécessite un serveur ASGI/WSGI + reverse proxy dédié | Nécessite un daemon Node managé (`pm2` / systemd) + proxy | Nécessite un serveur d’applications ou conteneur dédié | Nécessite l’exécution d’un binaire système et reverse proxy |
| **Débit / Latence pour CRUD e-commerce I/O-bound** | Très élevé : sur notre VM Debian 13, **p95 = 6,0 ms** en HTTP sans framework | Bon sur FastAPI async, plus lent sur Django ORM lourd | Très bon sur I/O non bloquant, sensible aux calculs CPU | Excellent en régime établi (*warm JVM*) | Excellent (référence sur très haute concurrence) |

*Tableau 6 — Benchmark multicritères comparant PHP 8.2+ sans framework aux principaux langages backend.*

**Analyse critique du comparatif PHP :**
- **Où PHP 8.2+ sans framework excelle :** sur une architecture orientée requêtes Web transactionnelles classiques (*request-response* HTML + API JSON), le modèle *shared-nothing* garantit une **isolation parfaite entre requêtes** (aucune fuite d’état client entre deux sessions concurrentes) et supprime tout processus daemon applicatif sur l’hébergement cible (*LineServ*). Couplé à l’absence totale de framework tiers, l’application démarre en quelques millisecondes et offre une latence mesurée à **`6,0 ms` au 95e centile** sur VM Debian 13, soit plus de `80` fois sous le seuil `ENF-01` (`500 ms`).
- **Où d’autres langages surpasseraient PHP :** pour des services temps réel à connexions WebSockets persistantes massives (chat, streaming) ou du calcul intensif / *machine learning*, **Go** ou **Node.js** (pour l’asynchronisme natif) et **Python** (pour l’écosystème scientifique) sont plus adaptés. Pour un e-commerce MVC classique adossé à MariaDB, PHP 8.2+ offre toutefois le meilleur compromis sobriété / performance / déployabilité.

\newpage

#### 1.4.3 Benchmark comparatif des SGBD : MySQL / MariaDB face aux autres moteurs

Le choix du moteur de base de données constitue le cœur de la fiabilité d’un site marchand, car c’est lui qui arbitre la concurrence sur les stocks (`RB-03`, `RB-18`) et la pérennité comptable (`RB-06`, `RB-15`). Le Tableau 7 met en regard **MySQL / MariaDB (InnoDB)** face aux quatre alternatives majeures du marché.

| Critère d’ingénierie | **MySQL 8.0 / MariaDB 10.6–11.8 (InnoDB)** — *Retenu* | **PostgreSQL 16** | **SQLite 3** | **MongoDB 7 (NoSQL Document)** | **Oracle / SQL Server (propriétaires)** |
|---|---|---|---|---|---|
| **Conformité ACID & niveau de verrouillage** | **ACID complet** via InnoDB ; verrous fins au **niveau ligne** (`SELECT ... FOR UPDATE`) | **ACID complet** (MVCC strict) ; verrous ligne et contraintes `EXCLUDE` | ACID complet, mais **verrou global sur tout le fichier** lors d’une écriture | Transactions multi-documents plus lourdes et plus lentes en écriture | ACID complet très avancé |
| **Comportement sous concurrence d’achat (`20` acheteurs / `12` unités)** | Sérialisation fluide ligne par ligne sur `produit(id_produit)` : **`0` survente** mesurée | Excellent (MVCC très robuste sans *gap-lock* parfois piégeux) | Risque élevé d’erreur `SQLITE_BUSY` sous écritures simultanées | Nécessite des transactions répliquées ou `$inc` conditionnels sans FK natives | Excellent |
| **Intégrité référentielle & normalisation 3FN** | Native (`FOREIGN KEY RESTRICT/CASCADE`, `UNIQUE`, `CHECK` effectifs) | La plus stricte et complète du monde open source (DDL transactionnel inclus) | `PRAGMA foreign_keys = ON` désactivé par défaut si non rappelé | **Aucune clé étrangère** ni jointure relationnelle normalisée native | Complète |
| **Procédures stockées & triggers** | Standards SQL/PSM (`BEGIN ... END`), matures pour `18` procédures + `16` triggers | PL/pgSQL très puissant (et `CREATE FUNCTION` + `TRIGGER` séparés) | Pas de procédures stockées (triggers limités) | Agrégation / JS côté serveur sans équivalent déclencheur SQL standard | PL/SQL / T-SQL très riches |
| **Coût de licence & disponibilité universitaire / mutualisée** | **Gratuit, open source**, préinstallé sur `99 %` des hébergements LAMP et VMs IUT | Gratuit, open source (moins courant sur mutualisés PHP basiques) | Embarqué (fichier local uniquement) | Licence SSPL restrictive sur hébergements gérés | Coûts de licence élevés, hors périmètre LAMP pédagogique |

*Tableau 7 — Benchmark multicritères des moteurs de bases de données pour un projet e-commerce.*

**Enseignements clés de ce benchmark SGBD :**
1. **Rejet du NoSQL (MongoDB) :** dans un contexte e-commerce à forte intégrité comptable (`RB-01` à `RB-20`), l’absence de clés étrangères natives et de normalisation 3FN oblige à réimplémenter manuellement côté PHP les contrôles de cohérence, exactement à l’opposé de la défense en profondeur exigée.
2. **Rejet de SQLite en production :** bien que rapide en lecture mono-utilisateur, son verrouillage au niveau du fichier entier provoquerait des conflits d’écriture lors de commandes concurrentes, et l’absence de procédures stockées rend impossible le respect de la compétence 9 du sujet.
3. **Arbitrage MySQL / MariaDB vs PostgreSQL :** PostgreSQL constitue techniquement une alternative remarquable (notamment pour ses DDL transactionnels et ses index partiels). Toutefois, le couple **MySQL 8.0 / MariaDB** s’impose ici en raison de la contrainte explicite `CT-03` du sujet, de son intégration immédiate dans les piles LAMP académiques (*LineServ*) et de son support natif des procédures stockées SQL/PSM appelées via `pdo_mysql`.

#### 1.4.4 Pourquoi choisir MariaDB (compatibilité MySQL 8.0) pour MiniShop ?

Bien que le cahier des charges mentionne historiquement « MySQL », l’équipe a conçu et validé MiniShop pour fonctionner de manière identique sur **MySQL 8.0.16+** et **MariaDB 10.6+** (et déployé concrètement sur **MariaDB 11.8.6** dans notre VM de recette Debian 13, tout en conservant **MySQL 8.0** dans la chaîne d’intégration continue GitHub Actions). Quatre arguments décisifs expliquent l’utilisation de **MariaDB** :

| Axe d’analyse | **MariaDB (`10.6+` / `11.8.6` en VM Debian 13)** | **MySQL (`8.0.16+` en CI GitHub Actions)** | Impact concret pour MiniShop |
|---|---|---|---|
| **1. Distribution Linux & hébergement** | SGBD relationnel par défaut dans les dépôts officiels **Debian 12/13**, Ubuntu et sur les hébergements pédagogiques LAMP (`apt install mariadb-server`). | Nécessite souvent l’ajout des dépôts tiers Oracle APT sur Debian récente. | Déploiement reproductible en une commande sur les VM de l’IUT et serveurs académiques. |
| **2. Gouvernance Open Source** | Licence **GPLv2** intégralement communautaire (MariaDB Foundation) sans fonctionnalités critiques réservées à une édition payante. | Édité par Oracle ; certaines fonctions avancées (Audit, Thread Pool natif) sont réservées à l’édition *Enterprise* payante. | Pérennité académique et industrielle sans verrouillage éditeur (*vendor lock-in*). |
| **3. Contraintes `CHECK` & moteur InnoDB** | `CHECK` appliqués nativement dès la version **10.2.1** ; moteur Aria/InnoDB optimisé pour les verrous de ligne `FOR UPDATE`. | `CHECK` ignorés avant 8.0.15, appliqués à partir de **8.0.16**. | Nos règles `ck_produit_prix`, `ck_produit_stock` et `ck_ligne_quantite` sont réellement bloquantes sur les deux moteurs. |
| **4. Vigilances résolues par MiniShop** | Mémorisation stricte de la collation de connexion dans les routines ; syntaxe `SIGNAL SQLSTATE '45000'` identique. | Même comportement sur la collation par défaut (`utf8mb4_0900_ai_ci` vs `utf8mb4_unicode_ci`). | MiniShop force explicitement `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` en tête de `sql/01..04` pour éliminer l’erreur `1267 Illegal mix of collations`. |

*Tableau 8 — Comparatif technique ciblé expliquant le choix de MariaDB et sa double compatibilité MySQL 8.0.*

En résumé, **MariaDB** nous permet de bénéficier à la fois de la **compatibilité protocolaire et SQL totale avec MySQL** (même pilote `pdo_mysql`, même syntaxe de procédures et de triggers, validation continue sur les deux moteurs) et d’une **intégration native, libre et performante sous Debian 13**, confirmée par nos `60/60` étapes de recette, `29/29` scénarios SQL et `8/8` tests de concurrence sans survente.

\newpage

### 1.5 Organisation du travail, découpage WBS et gouvernance qualité

La conduite du projet repose sur un découpage structuré (*Work Breakdown Structure*, `docs/05-wbs-projet.md` et `docs/06-todolist.md`) en cinq lots complémentaires exécutés par **Johan Polsinelli** et **Chahd Gouiaa**, sous revue croisée continue (`docs/20-charte-gitlab-revues-croisees.md`) et contrôle automatisé à chaque poussée (syntaxe PHP `8.2` et `8.5`, exécution SQL sur MySQL 8 / MariaDB 11.8, contrôles de sécurité `11/11` et audit documentaire `46/46`) :

| Lot WBS | Périmètre technique principal | Livrables de sortie vérifiés |
|---|---|---|
| **Lot 1 — Cadrage & Modélisation** | Analyse CDC, règles `RB-01..20`, `CT-01..12`, UML 2.5, Merise | `docs/01`, `14` diagrammes `docs/diagrams/*.png` |
| **Lot 2 — Base de données SQL** | DDL 3FN, `18` procédures, `1` fonction, `16` triggers, jeu de démo | `sql/01..04`, `docs/04`, `29/29` tests SQL |
| **Lot 3 — Cœur MVC & Double persistance** | Routeur blanc, `15` contrôleurs, `StoreInterface`, sécurité OWASP | `app/`, `public/`, `11/11` `controles.sh` |
| **Lot 4 — Front/Back-office & Ergonomie** | Direction artistique `DA.md`, WCAG 2.1 AA, sans JS (`ENF-07`), i18n/UTC | `app/View/`, `public/css/`, `public/js/` |
| **Lot 5 — Validation & Exploitation** | Campagne VM Debian 13, concurrence, `ENF-01`, RGPD, soutenance | `docs/pv-recette.md`, `docs/18`, `docs/26`, `docs/27` |

\newpage

## Chapitre 2 — Modélisation UML 2.5 et spécifications de sécurité

### 2.1 Diagramme de cas d’utilisation et les 14 cas d’utilisation minimum

Conformément à la liste minimale des **14 cas d’utilisation** exigés en page 3 du sujet officiel, la couverture fonctionnelle de MiniShop est répertoriée dans le Tableau 9, complétée par les diagrammes UML Figure 1 et Figure 2.

| N° sujet | Cas d’utilisation minimum exigé | ID interne | Acteur principal | Point d’entrée / Preuve |
|---:|---|---|---|---|
| 1 | Consulter le catalogue | `UC-01` | Visiteur | `CatalogueController::catalogue` (`/catalogue`, `/categories`) |
| 2 | Rechercher un produit | `UC-02` | Visiteur | `CatalogueController::recherche` (`/recherche`, `sp_search_products`) |
| 3 | Consulter un produit | `UC-03` | Visiteur | `CatalogueController::produit` (`/produit/{slug}`) |
| 4 | Créer un compte | `UC-04` | Visiteur | `AuthController::inscription` (`/inscription`, `sp_create_account`) |
| 5 | Se connecter | `UC-05` | Client / Admin | `AuthController::connexion` & `DashboardController::connexion` |
| 6 | Gérer le panier | `UC-06` | Visiteur / Client | `PanierController` + `ApiController` (`/panier`, `/api/panier/*`) |
| 7 | Passer une commande | `UC-07` | Client | `CommandeController::valider` (`sp_create_order_from_basket`) |
| 8 | Consulter l’historique des commandes | `UC-08` | Client | `CommandeController::historique` (`/mes-commandes`) |
| 9 | Annuler une commande | `UC-09` | Client / Admin | `CommandeController::annuler` (`sp_cancel_order`) & `sp_cancel_order_admin` |
| 10 | Gérer les produits | `UC-10` | Administrateur | `ProduitAdminController` (`/admin/produits/*`, `sp_save_product`) |
| 11 | Gérer les catégories | `UC-11` | Administrateur | `CategorieAdminController` (`/admin/categories/*`, `sp_save_category`) |
| 12 | Gérer les stocks | `UC-12` | Administrateur | `StockAdminController` (`/admin/stocks/*`, `sp_adjust_stock`) |
| 13 | Gérer les commandes | `UC-13` | Administrateur | `CommandeAdminController` (`/admin/commandes/*`) |
| 14 | Modifier le statut d’une commande | `UC-14` | Administrateur | `CommandeAdminController::statut` (`sp_update_order_status`) |

*Tableau 9 — Couverture exhaustive des 14 cas d’utilisation minimum imposés par le sujet (complétés par le tableau de bord, la gestion d’équipe `SUPER` et les paramètres).*

![Diagramme de cas d’utilisation du processus de commande](docs/diagrams/cas_utilisation_commandes.png)

*Figure 1 — Diagramme de cas d’utilisation ciblé sur le cycle de vie des commandes et les acteurs habilités.*

\newpage

![Diagramme d’activité du parcours de validation de commande](docs/diagrams/activite_passer_commande.png)

*Figure 2 — Diagramme d’activité : validation du panier, contrôle des stocks et gestion des échecs.*

\newpage

### 2.2 Descriptions textuelles détaillées de 3 cas d’utilisation (hors « Passer une commande »)

Le cahier des charges officiel (p. 3) demande explicitement : *« Choisir 3 cas d’utilisation (non pour cas d’utilisation ci-dessous [Passer une commande]) et détailler par description textuelle »* en présentant l’acteur principal, les préconditions, le scénario nominal et les scénarios alternatifs. Les 14 fiches synthétiques complètes figurent en outre en **Annexe A**.

#### Cas d’utilisation détaillé n°1 — Rechercher et filtrer un produit (`UC-02`)
- **Acteur principal :** Visiteur (ou Client authentifié).
- **Acteur secondaire :** SGBD MySQL / MariaDB (`sp_search_products`).
- **Préconditions :** le catalogue contient des produits et des catégories ; seuls les articles visibles (`visible = 1`) doivent apparaître dans les résultats publics (`RB-19`).
- **Scénario nominal :**
  1. Le visiteur saisit un mot-clé dans la barre de recherche (`/recherche?q=...`) et/ou sélectionne des filtres cumulables sur `/catalogue` (catégorie `cat`, prix TTC maximal `prix_max`, disponibilité en stock `stock=1`, critère de tri `tri`).
  2. Le contrôleur `CatalogueController` valide et assainit les paramètres reçus (transtypage entier/flottant, troncature à 120 caractères, vérification de `tri` sur une liste blanche stricte, plafond de pagination serveur entre `1` et `60`).
  3. Le dépôt `ProduitRepository` exécute `CALL sp_search_products(mot_cle, id_categorie, prix_min, prix_max, en_stock, tri, page, par_page, p_admin=0)` au moyen de paramètres liés PDO.
  4. Le SGBD retourne deux jeux de résultats : la page de produits correspondante (exploitant l’index `idx_produit_visible` et l’index `FULLTEXT`) et le compteur total permettant de calculer la pagination.
  5. La vue affiche la grille de produits échappée via `e()`, avec prix TTC, état du stock (`DISPONIBLE` / `TRES_BAS` / `RUPTURE`) et des liens de pagination conservant tous les filtres dans l’URL (`ENF-05`).
- **Scénarios alternatifs :**
  - *A1 (aucun résultat correspondant) :* le système affiche un message neutre et propose des liens vers les catégories actives sans jamais lever d’erreur technique.
  - *A2 (critère de tri inconnu ou altéré, ex. `?tri=prix;DROP TABLE`) :* le serveur rejette la valeur hors liste blanche, bascule sur le tri par défaut `nom` et enregistre l’événement sans interrompre la navigation (`S-01`).
  - *A3 (page hors bornes, ex. `page=999`) :* la page retournée est vide et propose un lien direct de retour à la page 1 (`F-04`).
  - *A4 (fourchette de prix incohérente `prix_min > prix_max`) :* le système avertit l’utilisateur et réinitialise ou ajuste les bornes sans erreur SQL.

#### Cas d’utilisation détaillé n°2 — Annuler une commande côté client (`UC-09`)
- **Acteur principal :** Client authentifié.
- **Acteur secondaire :** SGBD MySQL / MariaDB (`sp_cancel_order`, `trg_ligne_restore_stock`, `trg_history_statut`).
- **Préconditions :** le client dispose d’une session valide (`$_SESSION['client_id']`) ; la commande ciblée lui appartient (`RB-10`) et son statut est annulable par le client (`BROUILLON` ou `EN_PREPARATION`, avant toute expédition).
- **Scénario nominal :**
  1. Depuis la page `/mes-commandes/{id}`, le client active le bouton « Annuler cette commande ».
  2. Le système affiche une page dédiée de confirmation en deux étapes (fonctionnant **même sans JavaScript**, conformément à `ENF-07`), récapitulant le numéro de commande (`CMDAAAA-NNNNNN`) et embarquant un jeton CSRF unique (`Csrf::champ()`).
  3. Le client soumet le formulaire de confirmation (`POST /mes-commandes/{id}/annuler`).
  4. `CommandeController::annuler` vérifie le jeton CSRF en temps constant (`hash_equals`) et délègue à `CommandeRepository::cancelOrder()`.
  5. La procédure stockée `sp_cancel_order(id_commande, id_client)` ouvre une transaction, verrouille la commande, vérifie l’appartenance client et le statut, passe le statut à `ANNULEE`, remet `frais_port = 0.00` et `montant_total = 0.00` tout en conservant les lignes dans `ligne_commande` pour l’historique, et restitue les quantités au stock produit.
  6. Le déclencheur `trg_history_statut` écrit automatiquement la transition `EN_PREPARATION -> ANNULEE` dans `order_status_history`. Le client est redirigé vers son historique avec un message humain de confirmation.
- **Scénarios alternatifs :**
  - *A1 (IDOR — identifiant d’une commande appartenant à un autre client) :* le contrôleur et `sp_cancel_order` rejettent la requête et retournent une page `404` neutre sans confirmer l’existence de la commande (`SEC-08`, `ENF-12`).
  - *A2 (commande déjà `EXPEDIEE` ou `LIVREE`) :* la matrice de transition `RB-11` (`trg_commande_transition_statut`) refuse l’annulation et affiche un message métier explicite sans altérer le stock.
  - *A3 (annulation concurrente simultanée client + administrateur) :* le verrouillage transactionnel sérialise les deux requêtes ; la seconde constate le statut déjà `ANNULEE` (`STATUT_DEJA_A_JOUR`) et **ne restitue pas le stock une seconde fois**.

#### Cas d’utilisation détaillé n°3 — Ajuster manuellement le stock d’un produit (`UC-12`)
- **Acteur principal :** Administrateur (`GESTIONNAIRE` ou `SUPER`).
- **Acteur secondaire :** SGBD MySQL / MariaDB (`sp_adjust_stock`, `trg_produit_regles`, `v_etat_stock`).
- **Préconditions :** session administrateur active (`Auth::exigeAdmin()`) ; le produit cible existe en base.
- **Scénario nominal :**
  1. L’administrateur accède à `/admin/stocks`, alimenté par la vue `v_etat_stock` qui calcule automatiquement les alertes (`RUPTURE`, `TRES_BAS`, `DISPONIBLE`).
  2. Il choisit un article et sélectionne le mode d’ajustement par défaut non destructif **`DELTA`** (« Ajouter / retirer ») — ou `SET` (fixation d’inventaire) —, saisit la quantité entière et sélectionne un **motif obligatoire** (`REASSORT`, `INVENTAIRE`, `CASSE`, `RETOUR`, etc.).
  3. Il valide le formulaire muni du jeton CSRF.
  4. `StockAdminController` vérifie le CSRF et appelle `sp_adjust_stock(id_produit, mode, quantite, motif, auteur, role)`.
  5. La procédure pose un verrou `SELECT stock INTO v_avant FROM produit WHERE id_produit = ? FOR UPDATE`, calcule `v_apres`, refuse tout passage sous `0` (`RB-03`), met à jour `produit.stock` et insère dans la même transaction une ligne d’audit inaltérable dans `stock_movement_history` (référence, nom, `stock_avant`, `stock_apres`, motif, auteur, rôle, horodatage UTC).
  6. La page `/admin/stocks` se rafraîchit avec le nouveau stock et un message flash de confirmation.
- **Scénarios alternatifs :**
  - *A1 (mouvement menant à un stock négatif, ex. retrait de `10` unités sur un stock de `3`) :* `sp_adjust_stock` renvoie le code métier `RB03_STOCK_NEUTRE_NEGATIF` ; le stock reste inchangé (`3`) et aucun mouvement erroné n’est enregistré (`T-19`).
  - *A2 (motif vide ou tentative d’accès par un client standard) :* le routeur bloque tout non-administrateur (`403`) ; le contrôleur rejette toute requête sans motif valide.

*(Note : le cas d’utilisation « Passer une commande » `UC-07` respecte intégralement les 11 étapes nominales du cahier des charges, illustrées par la Figure 2 et la Figure 7.)*

\newpage

### 2.3 Diagramme de classes et justification des choix de conception objet

Le cahier des charges (p. 4) demande de présenter les classes, attributs, méthodes, associations, cardinalités et de **justifier les choix de conception**.

![Diagramme de classes de l’application PHP MiniShop](docs/diagrams/diagramme_classes.png)

*Figure 3 — Diagramme de classes simplifié : contrôleurs, dépôts métier, sécurité et abstraction de persistance.*

**Justification approfondie des choix de conception orientée objet :**
1. **Architecture en couches strictes (`Controller` → `Repository` → `StoreInterface`) :** les contrôleurs (`15` classes) sont volontairement légers : ils ne contiennent aucune requête SQL et délèguent toute règle métier aux dépôts (`ProduitRepository`, `CommandeRepository`, `ClientRepository`, `CategorieRepository`, `AdminRepository`, `ParametreRepository`).
2. **Dépôts métier et `BusinessError` plutôt qu’un ORM générique :** un ORM classique (Active Record / Data Mapper) maintient un graphe d’objets en mémoire qui entre fréquemment en conflit avec les colonnes recalculées côté SGBD par des triggers (`prix_ttc`, `total_ligne`, `montant_total`, `stock`). En travaillant par tableaux associatifs typés et exceptions métier `BusinessError` traduites en messages humains, l’application reste en parfaite synchronie avec l’état transactionnel de MariaDB.
3. **Isolation du panier en session (`PanierSession`) :** ne persiste côté session que le couple `{id_produit -> quantite}`. Lors de chaque affichage ou validation, le serveur relit en base la visibilité (`RB-19`), le prix TTC et le stock disponible : le navigateur n’est jamais source de vérité tarifaire.

\newpage

### 2.4 Les 4 diagrammes de séquences (authentification, recherche, panier, commande)

Le cahier des charges (p. 4) exige au moins **4 diagrammes de séquences** intégrant procédure stockée et déclencheur lorsque pertinent :

![Diagramme de séquence — Authentification](docs/diagrams/seq_authentification.png)

*Figure 4 — Séquence 1 (Authentification) : appel à `sp_get_credentials`, vérification bcrypt temps constant `password_verify()` en PHP et régénération d’identifiant de session `session_regenerate_id(true)`.*

\newpage

![Diagramme de séquence — Rechercher un produit](docs/diagrams/seq_recherche_produit.png)

*Figure 5 — Séquence 2 (Rechercher un produit) : validation de la liste blanche de tri, appel paramétré `sp_search_products` et rendu HTML échappé par `e()`.*

![Diagramme de séquence — Ajout au panier](docs/diagrams/seq_ajout_panier.png)

*Figure 6 — Séquence 3 (Ajout au panier) : vérification CSRF, contrôle serveur de visibilité (`RB-19`) et plafonnement au stock disponible (`RB-18`).*

\newpage

![Diagramme de séquence — Passer une commande](docs/diagrams/seq_passer_commande.png)

*Figure 7 — Séquence 4 (Passer une commande) : appel transactionnel `sp_create_order_from_basket`, verrouillage `SELECT ... FOR UPDATE`, calcul des frais de port (`sp_compute_shipping`) et exécution en cascade des triggers de snapshot (`trg_ligne_prix_snapshot`), décrémentation (`trg_ligne_decrement_stock`) et audit (`trg_history_statut`).*

\newpage

### 2.5 Réponses détaillées aux 5 spécifications de sécurité du cahier des charges

Le sujet officiel (p. 4–5) insiste sur cinq vulnérabilités critiques à maîtriser avec PHP/PDO/MySQL. Le Tableau 10 et les extraits de code vérifiés ci-dessous apportent la preuve technique pour chacune d’elles :

| Exigence du sujet (p. 4–5) | Règle imposée par le professeur | Implémentation exacte dans MiniShop & preuve |
|---|---|---|
| **1. SQL Injection** | Utiliser `PDO::prepare()` et `PDO::execute()`, interdire toute concaténation de paramètres. | `SqlStore` prépare 100 % des requêtes et appels `CALL sp_*` avec `PDO::ATTR_EMULATE_PREPARES => false` ; les noms de tables/colonnes sont validés par liste blanche sur `Schema`. Vérifié par `controles.sh [01/11]`. |
| **2. Mots de passe** | Interdire les mots de passe en clair (type `password = "123456"`), exiger `password_hash()` et `password_verify()`. | Création et modification via `password_hash(..., PASSWORD_BCRYPT)` (longueur minimale `8` caractères) ; comparaison en temps constant via `password_verify()` sur le hash lu par `sp_get_credentials`. Vérifié par `[04/11]`. |
| **3. XSS** | Échapper toutes les données affichées avec `htmlspecialchars()`. | Helper global `e()` (`htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')`) appliqué sur toutes les sorties de `app/View/`, doublé d’une `Content-Security-Policy` stricte sans script externe. Vérifié par `[02/11]`. |
| **4. Sessions** | Les pages privées doivent vérifier la session utilisateur (`$_SESSION['user_id']`). | `Auth::exigeClient()` vérifie `$_SESSION['client_id']` et `Auth::exigeAdmin()` vérifie `$_SESSION['admin_id']` (deux espaces cloisonnés) ; cookie `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `session_regenerate_id(true)`. Vérifié par `[06/11]`. |
| **5. Autorisation (IDOR)** | Un client ne doit pas accéder à une commande tierce en modifiant l’ID dans l’URL (`commande.php?id=25`). | `CommandeController` exige `id_client = Auth::clientId()` pour `/mes-commandes/{id}` ; `sp_confirm_order` et `sp_cancel_order` re-vérifient `id_client` côté SQL. Toute tentative tierce renvoie une `404` neutre (`ENF-12`). Vérifié par `[05/11]` & `T-18`. |
| **Complément CSRF & En-têtes** | Protection des écritures POST et anti-clickjacking. | Jeton CSPRNG (`random_bytes(32)`) vérifié par `hash_equals()` dans `Csrf::verifier()` sur les `12` contrôleurs POST mutatifs (`[07/11]`) ; `X-Frame-Options: DENY` ; zéro inscription admin publique (`[08/11]`). |

*Tableau 10 — Réponses vérifiées aux 5 spécifications de sécurité du sujet officiel.*

Extrait vérifié du durcissement HTTP et des jetons CSRF dans `public/index.php` et `app/Security/Csrf.php` :

```php
// public/index.php — en-têtes défensifs injectés avant tout routage
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; font-src 'self'; "
     . "style-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; "
     . "base-uri 'none'; frame-ancestors 'none'");

// app/Security/Csrf.php — comparaison en temps constant (anti-timing attack)
public static function verifier(): void
{
    Auth::demarrer();
    $recu = (string) ($_POST['_csrf'] ?? '');
    if ($recu === '' || !hash_equals(self::jeton(), $recu)) {
        http_response_code(403);
        $_SESSION['flash_erreur'] = 'Jeton de sécurité invalide ou expiré (CSRF).';
    }
}
```

### 2.6 Architecture 3 couches MVC adaptée au système

MiniShop implémente une architecture **3 couches logiques MVC** articulée autour d’un point d’entrée unique (`public/index.php`) et d’un routeur à liste blanche (`app/Router.php`). Aucune inclusion dynamique de fichier n’est pilotée par l’URL (immunité LFI/RFI), et chaque route déclare explicitement son contrôleur, sa méthode et sa garde (`public`, `client`, `admin` ou `super`).

![Architecture 3 couches MVC et double persistance de MiniShop](docs/diagrams/architecture_mvc.png)

*Figure 8 — Architecture 3 couches MVC : présentation (`public/`, `app/View/`), contrôle et logique métier (`Controller`, `Repository`, `Security`) et persistance duale (`SqlStore` / `JsonStore`).*

\newpage

## Chapitre 3 — Analyse et conception de la base de données (Merise, Normalisation et SQL)

### 3.1 Modèle Conceptuel des Données (MCD) : entités, associations et cardinalités justifiées

Conformément à la partie 3.A.i du sujet (p. 6), le Modèle Conceptuel des Données modélise **9 entités** (`CLIENT`, `ADMINISTRATEUR`, `CATEGORIE`, `PRODUIT`, `COMMANDE`, `LIGNE_COMMANDE`, `ORDER_STATUS_HISTORY`, `PARAMETRE` et `STOCK_MOVEMENT_HISTORY`) et justifie chaque cardinalité ligne à ligne.

![Modèle Conceptuel des Données MiniShop à 9 entités](docs/diagrams/mcd_minishop.png)

*Figure 9 — Modèle Conceptuel des Données (MCD) complet à 9 entités.*

| Association | Entités & Cardinalités | Justification métier et structurelle | Traduction DDL |
|---|---|---|---|
| `PASSER` | `CLIENT` `(0,N)` — `COMMANDE` `(1,1)` | Un client inscrit peut n’avoir encore passé aucune commande ou en passer plusieurs ; toute commande appartient à un unique client (`RB-09`, `RB-10`). | `id_client NOT NULL`, `FK ON DELETE RESTRICT` |
| `COMPORTER` | `COMMANDE` `(1,N)` — `LIGNE_COMMANDE` `(1,1)` | Une ligne n’a de sens qu’au sein d’une commande ; toute commande validée porte au moins une ligne (`RB-04`). | `id_commande NOT NULL`, `FK ON DELETE CASCADE` (brouillons) |
| `ETRE VENDU DANS` | `PRODUIT` `(0,N)` — `LIGNE_COMMANDE` `(1,1)` | Un article neuf peut ne pas encore être vendu ; chaque ligne de commande référence obligatoirement un unique produit. | `id_produit NOT NULL`, `FK ON DELETE RESTRICT` |
| `CLASSER` | `CATEGORIE` `(0,N)` — `PRODUIT` `(1,1)` | Une catégorie peut initialement être vide (`RB-08`) ; chaque produit est classé dans une seule catégorie (`RB-07`). | `id_categorie NOT NULL`, `FK ON DELETE RESTRICT` |
| `ETRE TRACÉE DANS` | `COMMANDE` `(0,N)` — `ORDER_STATUS_HISTORY` `(1,1)` | Chaque création ou changement d’état de commande génère une entrée d’audit rattachée à cette commande. | `order_id NOT NULL`, `FK ON DELETE CASCADE` |

*Tableau 11 — Justification détaillée des cardinalités du MCD.*

**Refus explicites de conception à défendre devant le jury :**
- **Séparation physique `CLIENT` vs `ADMINISTRATEUR` :** plutôt qu’une table `UTILISATEUR(role)`, deux tables aux attributs disjoints rendent structurellement impossible la création d’un compte administrateur depuis le formulaire d’inscription public (`SEC-10`).
- **Pas de colonne `PRODUIT.id_commande` ni `COMMANDE.produits_csv` :** la première empêcherait un produit d’être acheté plusieurs fois, la seconde violerait la 1FN.
- **Autonomie de `STOCK_MOVEMENT_HISTORY` :** conserve par *snapshot* l’identifiant, la référence et le nom du produit sans FK bloquante, afin de préserver l’historique d’audit même si un produit non vendu est supprimé.

### 3.2 Modèle Logique des Données (MLD) et vues relationnelles

![Modèle Logique des Données MiniShop](docs/diagrams/mld_minishop.png)

*Figure 10 — Modèle Logique des Données (MLD) issu de la dérivation du MCD.*

Trois **vues SQL** complètent le MLD pour factoriser les règles de lecture récurrentes sans duplication en PHP : `v_etat_stock` (classification unifiée `RUPTURE` / `TRES_BAS` / `DISPONIBLE`), `v_catalogue` (produits visibles `visible = 1` avec catégorie et stock) et `v_commandes_client` (synthèse des commandes par client).

\newpage

### 3.3 Normalisation complète : dépendances fonctionnelles, 1FN/2FN/3FN, PK/FK et redondances

Le sujet officiel (p. 6, section 3.A.iii) exige explicitement de traiter cinq points précis. Ils sont démontrés rigoureusement ci-dessous.

#### 1. Dépendances fonctionnelles identifiées (DF)

| Relation | Dépendances fonctionnelles non triviales (`X -> Y`) | Observations |
|---|---|---|
| `CLIENT` | `id_client -> nom, prenom, email, mot_de_passe_hash, telephone, adresse_livraison, code_postal, ville, actif, date_creation, derniere_connexion` ; `email -> id_client` | `email` est clé candidate (`RB-01`). |
| `ADMINISTRATEUR` | `id_admin -> nom, prenom, email, mot_de_passe_hash, role, actif, date_creation, derniere_connexion` ; `email -> id_admin` | `email` est clé candidate. |
| `CATEGORIE` | `id_categorie -> nom, slug, description` ; `nom -> id_categorie` ; `slug -> id_categorie` | `nom` et `slug` sont clés candidates. |
| `PRODUIT` | `id_produit -> reference, nom, slug, description, prix_ht, tva, stock, stock_initial, seuil_alerte, visible, image_url, id_categorie, ...` ; `reference -> id_produit` ; `slug -> id_produit` ; `(prix_ht, tva) -> prix_ttc` (dérivée) | La DF dérivée `(prix_ht, tva) -> prix_ttc` est maintenue par triggers `BEFORE INSERT/UPDATE`. |
| `COMMANDE` | `id_commande -> numero, id_client, adresse_livraison, statut, frais_port, montant_total, montant_avant_annulation, commentaire, date_commande, date_modification` ; `numero -> id_commande` | `adresse_livraison` et `frais_port` dépendent de `id_commande` (figées à l’instant de commande), non de `id_client`. |
| `LIGNE_COMMANDE` | `id_ligne -> id_commande, id_produit, quantite, prix_unitaire, total_ligne` | `prix_unitaire` dépend de `id_ligne` (snapshot `RB-06`), non du prix courant de `id_produit`. |
| `ORDER_STATUS_HISTORY` | `id -> order_id, old_status, new_status, changed_by, changed_by_role, commentaire, changed_at` | Clé technique `id` car deux transitions de test peuvent partager la même seconde. |
| `PARAMETRE` | `cle -> valeur, unite, comment, date_modification` | `cle` est la clé primaire naturelle. |
| `STOCK_MOVEMENT_HISTORY` | `id_mouvement -> id_produit, reference_produit, nom_produit, mode_mouvement, quantite, stock_avant, stock_apres, motif, changed_by, changed_by_role, changed_at` | Table d’audit en écriture seule. |

*Tableau 12 — Dépendances fonctionnelles identifiées sur les 9 tables.*

#### 2. Vérification formelle 1FN, 2FN et 3FN

| Table | 1FN | 2FN | 3FN | Démonstration |
|---|---|---|---|---|
| `CLIENT` / `ADMINISTRATEUR` | **OUI** | **OUI** | **OUI** | Attributs atomiques (adresse décomposée en `adresse_livraison`, `code_postal`, `ville`) ; clé primaire mono-attribut ; pas de DF `ville -> code_postal` valide en France (plusieurs communes partagent un même code postal). |
| `CATEGORIE` / `PARAMETRE` / `HISTORIES` | **OUI** | **OUI** | **OUI** | Attributs atomiques, clé primaire simple, aucune dépendance transitive. |
| `PRODUIT` | **OUI** | **OUI** | **3FN (dénormalisation verrouillée)** | `prix_ttc` dépend transitivement de `prix_ht` et `tva`. Conservé pour permettre le filtrage indexé (`prix_ttc BETWEEN` dans `sp_search_products`, `ENF-01`), mais **neutralisé par `trg_produit_ttc` et `trg_produit_ttc_update`**, empêchant toute anomalie de mise à jour. |
| `COMMANDE` | **OUI** | **OUI** | **3FN (dénormalisation verrouillée)** | `montant_total` est un agrégat des lignes + `frais_port`. Conservé comme montant contractuel facturé et **vérifié par `trg_commande_transition_statut`** (`RB-15`), rendant toute désynchronisation impossible. |
| `LIGNE_COMMANDE` | **OUI** | **OUI** | **OUI** | Une clé composée `(id_commande, id_produit)` aurait violé la 2FN (dépendance du prix à `id_produit` seul) et interdit deux lignes distinctes du même produit. L’identifiant de substitution `id_ligne` + le snapshot `prix_unitaire` garantissent strictement la 3FN. |

*Tableau 13 — Démonstration de normalisation 1FN / 2FN / 3FN.*

#### 3. & 4. Justification des clés primaires (PK) et clés étrangères (FK)

| Clé | Choix retenu | Justification | Anomalie évitée |
|---|---|---|---|
| **PK** `id_client`, `id_admin`, `id_categorie`, `id_produit`, `id_commande`, `id_ligne` | Entiers auto-incrémentés (clés de substitution) + `UK` sur `email`, `reference`, `slug`, `numero` | Des candidats naturels (`email`, `slug`) peuvent être corrigés ; des entiers courts optimisent jointures et index InnoDB tout en garantissant l’unicité métier via `UNIQUE KEY`. | Évite de devoir propager un changement d’e-mail sur toutes les clés étrangères de `COMMANDE`. |
| **FK** `produit.id_categorie` | `NOT NULL`, `ON DELETE RESTRICT`, `ON UPDATE CASCADE` | Tout produit appartient à une catégorie existante (`RB-07`) et une catégorie occupée ne peut être supprimée (`RB-14`). | Empêche les produits orphelins ou l’effacement d’un rayon non vide. |
| **FK** `commande.id_client` | `NOT NULL`, `ON DELETE RESTRICT` | Toute commande est rattachée à un client (`RB-10`) et son historique doit survivre (`RB-09`). | Empêche la perte d’historique lors de la suppression d’un compte. |
| **FK** `ligne_commande.id_commande` | `NOT NULL`, `ON DELETE CASCADE` | Identification faible : la ligne disparaît si un brouillon vide est purgé (`T-24`), tandis que `trg_commande_delete` interdit la suppression d’une commande validée. | Permet la purge propre des brouillons sans orphelins. |
| **FK** `ligne_commande.id_produit` | `NOT NULL`, `ON DELETE RESTRICT` | Empêche la suppression physique d’un produit déjà commandé (`RB-14`, `T-15`) ; `sp_delete_product` bascule alors `visible = 0`. | Préserve la cohérence des factures historiques. |
| **FK** `order_status_history.order_id` | `NOT NULL`, `ON DELETE CASCADE` | L’audit suit la commande (jamais supprimable après validation). | Cohérence de la piste d’audit. |

*Tableau 14 — Justification des clés primaires, uniques et étrangères.*

#### 5. Redondances identifiées et contrôles de non-contradiction

| Donnée redondante / dérivée | Nature | Pourquoi elle est conservée | Mécanisme de non-contradiction |
|---|---|---|---|
| `PRODUIT.prix_ttc` | Dérivée (`prix_ht`, `tva`) | Filtrage et tri par prix TTC indexés (`ENF-01`) | Triggers `trg_produit_ttc` et `trg_produit_ttc_update` |
| `LIGNE_COMMANDE.prix_unitaire` | Copie historique (`RB-06`) | Immutabilité du prix facturé si le tarif catalogue évolue | `trg_ligne_prix_snapshot` + `trg_ligne_immutable` |
| `LIGNE_COMMANDE.total_ligne` | Dérivée (`quantite * prix_unitaire`) | Lecture rapide des factures | `trg_ligne_prix_snapshot` + `trg_ligne_immutable` |
| `COMMANDE.montant_total` | Agrégat (`SUM(total_ligne) + frais_port`) | Valeur contractuelle figée (`RB-15`) | `trg_commande_transition_statut` + `sp_confirm_order` |
| `COMMANDE.adresse_livraison` | Copie historique (`CLIENT`) | Conserver l’adresse réelle de livraison même si le client déménage | Figée à la création de la commande |

*Tableau 15 — Inventaire des redondances maîtrisées (vérifiées par les 4 requêtes de l’Annexe E).*

### 3.4 Script SQL d’installation et jeu de données (`sql/01` à `sql/04`)

La base est construite de manière reproductible par quatre scripts ordonnés :
- `sql/01_minishop_schema.sql` : création de la base (`utf8mb4_unicode_ci`), des **9 tables**, des index (`FULLTEXT(nom, description)`, `idx_produit_visible`, `idx_commande_client`, `idx_commande_statut`), des **3 vues**, et insertion du jeu de données de référence : **4 catégories**, **36 produits** (dont `POWER-010`, `POWER-030`, `MONTRE-032` en rupture `stock = 0`, `MICRO-007` au seuil d’alerte et `BRAC-012` masqué `visible = 0`), **3 clients**, **1 administrateur `SUPER`** (mots de passe hachés par `password_hash()`) et **4 paramètres** (`frais_port = 4.90`, `franchise_port = 80.00`, `tva_defaut = 20.00`, `conservation_compte = 24`).
- `sql/02_minishop_procedures.sql` : **1 fonction** (`fn_param`) et **18 procédures stockées**.
- `sql/03_minishop_triggers.sql` : **16 déclencheurs**.
- `sql/04_minishop_demo.sql` : création de **4 commandes de démonstration** par appels réels `CALL sp_*` (deux `EN_PREPARATION` dont une bénéficiant de la franchise de port et une facturant `4,90 EUR`, un brouillon vide, et une `EXPEDIEE`), démontrant l’exécution effective des triggers dès le chargement.

Extrait représentatif du schéma DDL (`sql/01_minishop_schema.sql`) :

```sql
CREATE TABLE produit (
  id_produit   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference    VARCHAR(30)  NOT NULL,
  nom          VARCHAR(150) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  prix_ht      DECIMAL(10,2) NOT NULL,
  tva          DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
  prix_ttc     DECIMAL(10,2) NOT NULL,        -- dérivé, verrouillé par trigger (RB-16)
  stock        INT          NOT NULL DEFAULT 0,
  seuil_alerte INT          NOT NULL DEFAULT 3,
  id_categorie INT UNSIGNED NOT NULL,
  visible      TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_produit           PRIMARY KEY (id_produit),
  CONSTRAINT fk_produit_categorie FOREIGN KEY (id_categorie)
        REFERENCES categorie (id_categorie) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uk_produit_reference UNIQUE KEY (reference),
  CONSTRAINT uk_produit_slug      UNIQUE KEY (slug),
  CONSTRAINT ck_produit_prix      CHECK (prix_ht > 0),   -- RB-02
  CONSTRAINT ck_produit_stock     CHECK (stock >= 0)     -- RB-03
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

\newpage

### 3.5 Procédures stockées (18 livrées) et réponse complète à la question pédagogique du sujet

Le sujet impose au minimum `5` procédures stockées ; **18 procédures** et **1 fonction** (`fn_param`) sont livrées et appelées par `app/Repository/` (répertoire complet en **Annexe C**).

#### Réponse complète à la question pédagogique du sujet (p. 7)
> *« Pourquoi utiliser une procédure stockée dans ce cas plutôt que réaliser toutes les opérations directement en PHP ? »*

| Aspect imposé par le sujet | Apport décisif de la procédure stockée (côté MySQL/MariaDB) | Conséquence si tout était codé uniquement en PHP |
|---|---|---|
| **1. Centralisation de la logique** | `sp_create_order_from_basket`, `sp_confirm_order` et `sp_adjust_stock` portent une source unique de vérité (verrou -> contrôle stock -> calcul port -> écriture -> historique) partagée par le front-office, le back-office et tout futur import CLI/CSV. | Duplication des règles dans plusieurs contrôleurs/scripts, avec risque élevé qu’un nouveau parcours oublie le snapshot de prix (`RB-06`) ou le calcul du port (`RB-20`). |
| **2. Sécurité** | Défense en profondeur : en production, le compte SQL applicatif peut ne disposer que du privilège `EXECUTE` sur les procédures et `SELECT` sur les vues, sans droit `UPDATE/DELETE` direct sur `produit` ou `commande`. | Une faille applicative exposerait directement les tables critiques à des altérations arbitraires de stock ou de montants. |
| **3. Transactions** | Les instructions `START TRANSACTION` / `COMMIT` / `ROLLBACK` et les verrous `SELECT ... FOR UPDATE` s’exécutent de façon atomique au plus près des données sur le serveur SGBD, insensibles aux déconnexions ou timeouts réseau intermédiaires du script PHP. | Un crash PHP entre l’insertion de la commande et la décrémentation du stock (ou un `beginTransaction()` omis) provoquerait une corruption silencieuse des stocks. |
| **4. Réutilisabilité** | `sp_search_products` sert indifféremment le visiteur (`p_admin = 0`) et l’administrateur (`p_admin = 1`) ; `sp_compute_shipping` est invoquée par le panier comme par la confirmation finale sans réécriture. | Divergence progressive des formules d’alerte ou de port entre les différents modules PHP. |
| **5. Contrôle des accès** | Les procédures d’écriture critique vérifient leurs propres préconditions (`sp_confirm_order` contrôle l’appartenance `id_client`, `sp_delete_category` refuse toute catégorie occupée), formant un second rempart indépendant du code PHP. | La sécurité reposerait exclusivement sur la rigueur de chaque action HTTP appelante. |

*Tableau 16 — Réponse complète aux 5 volets de la question pédagogique sur les procédures stockées.*

**Nuance critique d’ingénierie (frontière et coûts assumés) :** nous réservons les procédures stockées aux **opérations d’écriture multi-tables et décisions critiques**, tout en conservant les lectures simples en requêtes préparées PDO. Nous assumons également le coût de cette approche : couplage plus fort au dialecte MySQL/MariaDB, débogage moins outillé qu’en PHP et nécessité de maintenir la parité avec le pilote secondaire `JsonStore`.

| Exemple du sujet officiel | Objet livré dans MiniShop | Explication de correspondance |
|---|---|---|
| `sp_create_order` | `sp_create_order` | Nom et rôle exacts (+ génération numéro `CMDAAAA-NNNNNN`). |
| `sp_add_order_item` | `sp_add_order_line` (+ `sp_create_order_from_basket`) | Adapté en `_line` par cohérence avec la table française `ligne_commande`. |
| `sp_update_order_status` | `sp_update_order_status` | Nom et rôle exacts (+ matrice `RB-11` et auteur tracé). |
| « Trigger 1 — Stock » | `trg_ligne_decrement_stock` + `trg_ligne_controle_insert` + `trg_ligne_restore_stock` | Décrémente à l’insertion, bloque avant négatif et restitue à l’annulation. |
| « Trigger 2 — Historique » (`ORDER_STATUS_HISTORY`) | `trg_history_statut` (`AFTER UPDATE`) + `trg_history_creation` (`AFTER INSERT`) | Table `order_status_history(id, order_id, old_status, new_status, changed_at, ...)` au format exact du sujet. |

*Tableau 17 — Correspondance nominative entre les exemples du sujet et les objets SQL livrés.*

### 3.6 Déclencheurs (16 livrés) : couverture de « Trigger 1 — Stock » et « Trigger 2 — Historique »

Le sujet impose au minimum `5` déclencheurs ; **16 triggers** (Annexe C) protègent l’intégrité des données même lors d’écritures SQL directes :
- **Couverture « Trigger 1 — Stock » :** `trg_ligne_controle_insert` (`BEFORE INSERT`) vérifie la disponibilité et lève `STOCK_INSUFFISANT` si besoin ; `trg_ligne_decrement_stock` (`AFTER INSERT`) soustrait `quantite` du stock produit ; `trg_ligne_restore_stock` (`AFTER DELETE`) restitue le stock lors de l’annulation avant expédition ou purge de brouillon.
- **Couverture « Trigger 2 — Historique » :** sur la table `order_status_history`, `trg_history_creation` enregistre la trace initiale `NULL -> BROUILLON` et `trg_history_statut` trace automatiquement tout changement de statut avec l’auteur et son rôle, conformément à l’automate de la Figure 11.

```sql
-- Extrait de sql/03_minishop_triggers.sql : Trigger 1 (contrôle + décrément)
CREATE TRIGGER trg_ligne_controle_insert
BEFORE INSERT ON ligne_commande
FOR EACH ROW
BEGIN
  DECLARE v_prix DECIMAL(10,2);
  DECLARE v_stock INT;
  SELECT prix_ttc, stock INTO v_prix, v_stock
    FROM produit WHERE id_produit = NEW.id_produit;
  IF v_prix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_INTROUVABLE';
  END IF;
  IF v_stock < NEW.quantite THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STOCK_INSUFFISANT';
  END IF;
END;

CREATE TRIGGER trg_ligne_decrement_stock
AFTER INSERT ON ligne_commande
FOR EACH ROW
BEGIN
  UPDATE produit
     SET stock = GREATEST(stock - NEW.quantite, 0)
   WHERE id_produit = NEW.id_produit;
END;
```

![Automate des états et transitions d’une commande](docs/diagrams/etats_commande.png)

*Figure 11 — Automate à 6 états du cycle de vie d’une commande contrôlé par `trg_commande_transition_statut`.*

\newpage

## Chapitre 4 — Réalisation applicative, design system, stratégie de tests et performances

### 4.1 Architecture logicielle MVC complète et double persistance (`ARCHITECTURE.md`)

MiniShop est une application PHP rendue côté serveur (*Server-Side Rendering*) sans framework MVC tiers ni ORM, enrichie par du CSS et du JavaScript natifs. Afin de compléter le diagramme d’architecture (Figure 8) présenté au Chapitre 2, cette section détaille l’organisation interne réelle décrite dans `ARCHITECTURE.md`.

#### 4.1.1 Les 8 couches applicatives et le cycle de vie d’une requête HTTP

| Couche | Emplacement dans le dépôt | Responsabilité exacte |
|---|---|---|
| **1. Présentation Web** | `public/` (`index.php`, `css/`, `js/`, `img/`, `fonts/`, `.htaccess`) | Point d’entrée HTTP unique, ressources statiques locales, réécriture Apache (`DocumentRoot public/`). |
| **2. Routage & Gardes** | `app/Router.php` | Liste blanche explicite des routes (`public`, `client`, `admin`, `super`) et motifs `{id}` / `{slug}` ; `404` sur toute route non déclarée. |
| **3. Contrôle / Orchestration** | `app/Controller/` (`15` contrôleurs) | Validation des paramètres HTTP, vérification explicite CSRF (`verifierCsrf()`), délégation aux dépôts, rendu HTML ou réponse JSON (`ApiController`). |
| **4. Vues PHP** | `app/View/` + `layout.php` | Gabarit HTML commun front/back-office ; échappement systématique de toutes les sorties par le helper `e()`. |
| **5. Dépôts métier** | `app/Repository/` | Encapsulation des opérations catalogue, catégories, comptes, commandes, statistiques et paramètres ; traduction des erreurs en `BusinessError`. |
| **6. Modèle & Règles partagées** | `app/Model/`, `app/Model/Data/`, `app/Service/`, `app/Tools/` | `PanierSession` (IDs + quantités uniquement), `Schema`, `Triggers`, `Views`, journalisation, temps UTC (`Heure`), rétention. |
| **7. Configuration & Persistance** | `app/Config/`, `app/Model/Data/` (`StoreInterface`, `SqlStore`, `JsonStore`) | Résolution de configuration (`.env` surchargé par l’environnement processus) et sélection du pilote (`sql` par défaut, `json` secondaire). |
| **8. Outillage hors Web** | `scripts/`, `tests/`, `sql/`, `docs/` | Initialisation, vérifications statiques, campagnes de tests, schéma SQL et documentation (jamais exposés comme routes HTTP). |

*Tableau 18 — Cartographie complète des 8 couches applicatives et de l’outillage (`ARCHITECTURE.md`).*

**Cycle de vie exact d’une requête HTTP en 8 étapes :**
1. **Réception :** Apache (`.htaccess`) ou `scripts/dev-router.php` sert directement les fichiers statiques existants et transmet les autres URL à `public/index.php`.
2. **Amorçage (`app/bootstrap.php`) :** enregistre un autoloader minimal `App\` → `app/` (sans Composer requis), charge les helpers et fixe le fuseau PHP à `UTC`.
3. **Sécurité & Session :** `public/index.php` envoie les en-têtes HTTP défensifs (`CSP`, `X-Frame-Options: DENY`, `nosniff`), puis `Auth` démarre la session (`MINISHOPSESS`).
4. **Résolution & Garde (`app/Router.php`) :** résout l’action exacte et exécute la garde (`public`, `client`, `admin`, `super`) avant le contrôleur.
5. **Traitement HTTP :** le contrôleur valide les champs, vérifie explicitement le jeton CSRF sur les routes mutatives ; un refus d’accès rend `403`, une violation métier affiche un message humain, une exception technique est journalisée (`500` générique).
6. **Règles & Persistance :** le dépôt obtient le magasin via `Database::store()` et groupe les écritures composées dans une transaction.
7. **Réponse :** rendu d’une vue dans `layout.php`, réponse JSON (`ApiController`), ou redirection post-POST avec message flash.
8. **Rendu navigateur :** les vues échappent les chaînes par `e()` ; le JavaScript natif améliore l’ergonomie sans jamais faire autorité sur les prix, stocks ou règles métier.

#### 4.1.2 Contrat `StoreInterface` : parité et limites entre `SqlStore` et `JsonStore`

`app/Model/Data/StoreInterface.php` isole les dépôts métier du moteur de stockage :
- **`SqlStore` (pilote principal par défaut) :** implémente le contrat sur MySQL/MariaDB via PDO avec validation des identifiants sur `Schema`, requêtes préparées natives (`ATTR_EMULATE_PREPARES => false`) et verrous `SELECT ... FOR UPDATE`. Les dépôts détectent les capacités SQL et appellent les procédures transactionnelles autonomes (`sp_create_order_from_basket`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_adjust_stock`) **sans ouvrir de transaction PDO englobante**.
- **`JsonStore` (pilote secondaire explicite `STORAGE_DRIVER=json`) :** stocke chaque table dans `data/minishop/*.json` sous verrou coopératif local `.minishop.lock` et réapplique `Schema`, `Triggers` et `Views` en PHP. Chaque fichier est écrit par fichier temporaire + renommage atomique, mais **un commit multi-fichiers JSON n’est pas résistant à une panne en cours d’écriture** : il convient aux démonstrations hors ligne et tests isolés, jamais à une production multi-hôtes ou concurrente.

\newpage

### 4.2 Étude complète de design, direction artistique et accessibilité (`docs/DA.md`)

Conçue avant l’écriture de `public/css/app.css` et vérifiée automatiquement par `tests/php/css_contract.php` (`69` assertions), la direction artistique (`docs/DA.md`) repose sur le positionnement *« La précision technique, sans la froideur »* et sur l’absence totale de dépendance CDN externe (`ENF-20`).

#### 4.2.1 Benchmark des 5 acteurs français du e-commerce et positionnement

| Acteur analysé | Ce que l’on reprend dans MiniShop | Ce que l’on refuse explicitement |
|---|---|---|
| **Amazon.fr** | La *buy box* (prix + dispo + quantité + CTA groupés), la réassurance et le délai estimé | La densité publicitaire (8–12 blocs sans scroller), les carrousels empilés et les faux compteurs d’urgence |
| **Cdiscount.com** | La lisibilité nette du prix TTC, du HT et du taux de TVA | L’usage du **rouge** pour afficher un prix (dans MiniShop, le rouge est réservé aux erreurs et ruptures) |
| **Fnac.com** | Le rythme éditorial alternant bandeaux pleine largeur et grilles produits aérées | L’importation de deux familles de polices Web externes (incompatible avec `ENF-20`) |
| **Boulanger.com** | Les filtres à facettes conservés dans l’URL (`?cat=...&prix_max=...&tri=...`), pastilles actives retirables, cartes homogènes | La comparaison multi-produits et le sélecteur de magasin physique (hors périmètre CDC) |
| **Back Market** | La sobriété chromatique, le contraste élevé, la respiration visuelle et les cibles tactiles `>= 44 px` | — (*modèle de clarté retenu*, complété par un back-office aussi soigné que la boutique) |

*Tableau 19 — Benchmark ergonomique des 5 concurrents français et positionnement de MiniShop (`docs/DA.md`).*

#### 4.2.2 Palette WCAG 2.1 AA, typographie sourcée (`Lato` / `SIL OFL 1.1`) et grille

| Jeton CSS (`app.css`) | Valeur hexadécimale | Rôle & Règle d’usage | Ratio WCAG 2.1 calculé |
|---|---|---|---|
| `--papier` / `--surface` | `#FBFAF7` / `#FFFFFF` | Fond papier chaud anti-fatigue / cartes et tableaux | — |
| `--encre` / `--encre-douce` | `#0F172A` / `#475569` | Textes principaux et titres / légendes et mentions secondaires | **17,1:1** / **7,3:1** sur papier |
| `--primaire` / `--primaire-fonce` | `#1D4ED8` / `#1E3A8A` | Liens, éléments actifs, contour focus 3 px / en-têtes sombres | **6,7:1** / **10,4:1** |
| `--accent` / `--accent-fonce` | `#C2410C` / `#9A3412` | **Uniquement l’action d’achat engagée** (1 seul CTA accent par écran) | **5,2:1** / **7,0:1** sur blanc |
| `--succes` / `--alerte` / `--danger` / `--info` | `#166534` / `#9A3412` / `#991B1B` / `#1E3A8A` | Badges d’état couplés à un fond pastel et **libellé texte obligatoire** (daltonisme) | **6,5:1** / **6,4:1** / **6,8:1** / **8,5:1** |

*Tableau 20 — Palette chromotypographique complète et ratios de contraste WCAG 2.1 AA.*

- **Typographie et source exacte :** pile système native (`system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`) complétée pour le seul glyphe euro (`unicode-range: U+20AC`) par `public/fonts/minishop-euro.woff2` (`MiniShopEuro`), extrait de **Lato Regular** (`google/fonts`, `ofl/lato/Lato-Regular.ttf`, blob `0f3d0f837d24834b9b5b0a6b735459c56f5e75c3`) sous licence **SIL OFL 1.1** (`public/fonts/OFL.txt`, CSP `font-src 'self'`). Les prix utilisent `font-variant-numeric: tabular-nums` (alignement vertical) et l’échelle modulaire `1,25` (`12 / 14 / 16 / 20 / 25 / 31 / 39 px`) avec `max-width: 68ch` sur les textes.
- **Grille et responsive :** unité de base `8 px`, conteneur `max-width: 1180px`, grille catalogue adaptative `1 / 2 / 3` colonnes (`360 / 640 / 1024 px`), tableaux back-office repliés avec attributs `data-label` sur mobile et feuille dédiée `public/css/print.css` pour les commandes et CGV.

#### 4.2.3 Iconographie (`Lucide` / `ISC`), photographie à 2 niveaux, 12 composants, UTC et i18n

- **Iconographie sourcée sans CDN :** tracés SVG **Lucide** (licence **ISC**, *Cole Bemis Feather MIT 2013–2022 & Lucide Contributors 2022*) inlinés côté serveur dans `app/Security/xss.php` (`icone()`, `icone_categorie()` : `laptop`, `headphones`, `shopping-bag`, `watch`, repli `tag`), compatibles avec `img-src 'self' data:` et vérifiés sans URL externe par `css_contract.php`.
- **Photographie à deux niveaux :** une image d’ambiance `16/9` dans le hero (`public/img/illustration-achat.jpg`, `960×524`, `63 Ko`, `loading="eager"`, `fetchpriority="high"`) harmonisée avec `--primaire` et `--accent`, et **12 packshots produits homogènes** `4/3` (`public/img/produits/*.jpg`, `800×600`, `15–49 Ko`, `loading="lazy"`, `alt=""` car le titre de carte porte déjà le nom pour les lecteurs d’écran) répartis de façon cohérente sur les 36 articles, avec repli SVG si un visuel manque. Aucune banque d’images tierce ni droit externe.

| Axe design & accessibilité | Règles mises en œuvre (`docs/DA.md`) |
|---|---|
| **12 composants UI** | En-tête collant (`64 px`), recherche, carte produit `4:3`, *buy box*, filtres URL, pagination `aria-current="page"`, tableau back-office, formulaire (`575 px` max centré pour contact/auth), boutons avec état `disabled`, badges d’état, flash `role="status"`/`alert"`, état vide guidé. |
| **10 exigences WCAG 2.1 AA** | Contrastes `>= 5,2:1` et bordures `>= 3:1`, navigation 100 % clavier, lien d’évitement « Aller au contenu », `:focus-visible` `3 px`, `aria-live="polite"` panier, `prefers-reduced-motion` (`0,01 ms`), zoom `200 %` (`rem`), cibles tactiles `>= 44×44 px`. |
| **Microcopy & Bornes assumées** | Vouvoyement factuel sans fausse urgence (`129,90 € TTC`, `En stock (12)`, jamais `Erreur 1062` ni `Plus que 2 !!!`) ; `~700` lignes de CSS écrites à la main sans Tailwind/Bootstrap ; pas de mode sombre en v1 (tokens documentés). |

*Tableau 21 — Synthèse des 12 composants UI, règles WCAG 2.1 AA et discipline éditoriale.*

En complément, la traduction intégrale **FR / EN** (`public/js/i18n-en.js`, vérifiée sur `48` vues et `45` messages métier) et le choix du fuseau horaire (stockage serveur strictement **UTC** via `app/Tools/Heure.php`, projection navigateur selon `localStorage` et `public/js/tz-lookup.js` sans envoi GPS) assurent l’accessibilité linguistique et temporelle (`ENF-07`).

\newpage

### 4.3 Stratégie de tests complète en pyramide (`docs/11`) et exigences `ENF-01` à `ENF-22`

| ID | Exigence non fonctionnelle | Critère / Seuil cible | Vérification & Statut consigné |
|---|---|---|---|
| `ENF-01` | Performance HTTP sous volumétrie cible | `p95 < 500 ms`, moyenne `< 300 ms` | **Validée VM (`cb0f814`) :** HTTP **p95 = 6,0 ms**, SQL p95 `<= 20,6 ms` |
| `ENF-02` | Volumétrie cible 2026 | `200` produits / `1 000` commandes/an | Mesurée sur `minishop_perf` (`236` produits / `1 004` commandes) |
| `ENF-04/05/06` | Pagination bornée, filtres URL, conservation saisies | `1 <= par_page <= 60`, URL partageable | Validés via `T-25` et `http_sql_e2e.php` |
| `ENF-07/08` | Parcours complet sans JS & WCAG 2.1 AA | `0` blocage sans JS, contrastes AA | Validé en E2E sans JS (`F-13`) et tests contrat CSS (`css_contract.php`) |
| `ENF-10` | Portabilité MySQL 8 / MariaDB 10.6+ / Apache | Scripts reproductibles | MariaDB 11.8.6 validée sur VM (`60/60`) ; MySQL 8 en CI GitHub Actions |
| `ENF-11/12` | Sécurité hashes & réponse IDOR `404` neutre | `0` clair, `404` sur ID étranger | `11/11` `controles.sh` + `105/105` HTTP SQL |
| `ENF-14/16` | Affichage TTC + port avant validation & atomicité | Transactions `ROLLBACK` intégral | Validés (`T-28`, `T-29`, `8/8` concurrence HTTP) |

*Tableau 22 — Synthèse des exigences non fonctionnelles clés (`docs/annexes/annexe-enf.md`).*

#### 4.3.1 La pyramide des tests à 9 étages et sa justification méthodologique

Comme l’explique `docs/11-strategie-tests.md`, nous avons volontairement écarté une stratégie reposant sur 400 tests unitaires à `PDO` simulé (qui prouveraient uniquement que PHP passe une chaîne SQL à un bouchon). L’essentiel de la logique critique résidant dans le SGBD (`9` tables, `18` procédures, `16` triggers), la validation est structurée en **pyramide à 9 étages** testant chaque règle là où elle s’exécute réellement :

| Étage | Nature de la vérification | Suites & Outils créés | Volume vérifié (VM `17/11/2026`) | Limite explicitement assumée |
|---:|---|---|---|---|
| **0** | Analyse statique, sécurité & audits | `strict_types_lint.php`, `controles.sh`, `verifier_fiches_uc.py`, `tests/python/`, `wasm_verdict.mjs` | **171** fichiers (`0` violation), sécu **11/11**, UC **46/46**, verdict **9/9**, Python **42** | Ne remplace pas une recette humaine |
| **1** | Unitaires purs | `tests/php/unit_tests.php` + `tests/phpunit/**` | **297** vérifications + **57/57** PHPUnit | Fixtures mémoire isolées |
| **2** | Contrats statiques inter-artefacts | `contract_http.php`, `css_contract.php`, `responsive_contract.php` + `*_test.php` | **27** HTTP + **69** CSS + **50** responsive | Analyse statique hors navigateur réel |
| **3** | Règles métier sur le moteur | `tests/php/run_tests.php` (`T-01..T-29`), `engine_smoke.php`, `json_concurrency_stress.php` | **29/29** sur `JsonStore` + stress concurrence | Pilote JSON ; l’équivalent SQL est à l’étage 5 |
| **4** | Fonctionnalités par dépôts | `features_test.php` (`UC-01..14`), `catalogue_stock_test.php`, `nouveautes_test.php` | **2 550** scénarios catalogue verts | Magasins temporaires isolés |
| **5** | Intégration SQL réelle & migrations | `tests/php/sql_integration.php` (`tests/sql/run_tests.sh`) + `migration_mariadb_test.php` | **29/29** sous **MariaDB 11.8.6** + migrations vertes | Base temporaire isolée (`minishop_test`) |
| **6** | Parcours HTTP E2E, concurrence & DOM | `http_sql_e2e.php`, `concurrence_http.php`, `wasm-e2e.mjs`, `preferences_test.mjs` | **105/105** HTTP SQL, **8/8** concurrence, **338/338** JSON, **48** vues FR/EN | Automatisé ; navigateur humain non joué |
| **6 bis** | Navigateur headless (CI) | `tests/node/responsive_navigateur.mjs` (Playwright Chromium `320/375/1440 px`) | Protocole prêt (exige binaire Chromium) | Contrat CSS/HTML vérifié ; Chromium en CI |
| **7** | Performance & volumétrie | `scripts/run_perf_vm.sh` → `gen_volumes_cli.php` + `mesurer_sql.sh` + `mesurer.sh` | SQL **p95 <= 20,6 ms**, HTTP **p95 = 6 ms**, `0` erreur | VM locale (`cb0f814`) |
| **8** | Recette manuelle humaine | `docs/13-fiches-recette-uc.md`, `docs/13-suivi-recette-uc.csv` | **164** scénarios + **36** couples = **200 lignes `NON JOUÉ`** | Campagne humaine restante |

*Tableau 23 — Pyramide de validation complète à 9 étages (`docs/11-strategie-tests.md`).*

#### 4.3.2 Protocoles d’isolation SQL, contrats statiques, couverture honnête et garde de verdict

- **Isolation de `sql_integration.php` :** conçu comme une enveloppe mince autour du harnais SQL de référence `tests/sql/run_tests.sh` (évitant la divergence entre deux jeux de tests), ce script crée une base jetable `minishop_test`, rejoue `fixture.sql` avant chaque scénario, **refuse par défaut toute base déjà existante** (`--reinitialiser-base-existante` requis) et **interdit formellement de cibler `DB_NAME` applicatif**.
- **Tests de contrat statiques (`contract_http`, `css_contract`, `responsive_contract`) :** attrapent les ruptures de cohérence entre fichiers écrits séparément (route renommée dans un `<a href>`, couleur hors palette, `style=` inline bloqué par la CSP, grille `min-width` provoquant un défilement mobile).
- **Couverture honnête sans Xdebug (`scripts/coverage.php`) :** conformément à `ENF-20` (zéro dépendance PCOV/Xdebug), mesure quels fichiers de `app/` sont chargés par les suites PHP afin de détecter les fichiers jamais exécutés (en complément de `scripts/code_mort.php` via tokens PHP et de `scripts/verifier_deprecations.py` sur le PHP CLI cible).
- **Fiabilité du verdict (`scripts/wasm_verdict.mjs`) :** testé sur **9/9 cas** pour garantir qu’une erreur fatale (`255`), un échec `❌` (`1`) ou un `NON JOUÉ` (`2`) ne puisse jamais être classé comme succès (`0`).

\newpage

### 4.4 Campagne VM Debian 13 et mesures complètes de performances (`tests/perf/dernieres_mesures.txt`)

#### 4.4.1 Bilan des suites automatisées et mesures détaillées SQL (`EXPLAIN`) et HTTP (`p95 = 6 ms`)

La campagne exécutée sur **VM Debian 13** (PHP `8.4.27`, MariaDB `11.8.6`, commit `f9b614f`, `docs/pv-recette.md`), complétée par les audits du dépôt, valide l’intégralité des suites automatisées :

| Suite / Outil exécuté | Cible vérifiée | Résultat mesuré | Statut |
|---|---|---|---|
| **Campagne VM complète (`generer_pv.php`)** | Orchestration complète sur Debian 13 + MariaDB 11.8.6 | **60 / 60 étapes** (`0` warning PHP) | **VALIDÉ** |
| **Intégration SQL (`tests/php/sql_integration.php`)** | Règles `T-01` à `T-29`, triggers, `CHECK`, rollbacks | **29 / 29 scénarios** | **VALIDÉ** |
| **E2E HTTP SQL (`tests/php/http_sql_e2e.php`)** | Parcours complets HTTP + PDO client & back-office | **105 / 105 assertions** | **VALIDÉ** |
| **Concurrence HTTP (`tests/php/concurrence_http.php`)** | `20` acheteurs simultanés pour `12` unités disponibles | **8 / 8 scénarios** (`0` survente) | **VALIDÉ** |
| **Tests unitaires & Contrats (`unit_tests`, `phpunit`, `*_contract`)** | `297` unitaires + `57/57` PHPUnit + `27`/`69`/`50` contrats + `171` linter | **100 % verts** (`0` violation) | **VALIDÉ** |
| **E2E JSON & i18n (`run_tests.php`, `preferences_test.mjs`)** | Parité `JsonStore` + `48` vues FR/EN & `45` messages métier | **338 / 338 assertions** + **48 vues** | **VALIDÉ** |
| **Sécurité statique & Audits (`controles.sh`, `verifier_fiches_uc.py`)** | 11 points OWASP/ANSSI + 14 UC / 164 scénarios / 36 couples | **11 / 11** + **46 / 46** + **22 / 22** Python | **CONFORME** |

*Tableau 24 — Synthèse chiffrée des campagnes de validation automatisées.*

La campagne de performance dédiée (`bash scripts/run_perf_vm.sh`, commit `cb0f814`, `tests/perf/dernieres_mesures.txt`) a été conduite sur la base isolée `minishop_perf` peuplée à **`236` produits**, **`1 004` commandes**, **`1 916` lignes de commande** et **`1 998` traces de statut** (au-delà de la volumétrie cible `ENF-02` de `200` produits / `1 000` commandes). Les Tableaux 25 et 26 en restituent l’intégralité :

| Requête SGBD évaluée (`100` itérations) | Moyenne (ms) | p95 (ms) | Plan `EXPLAIN` complet & Verdict |
|---|---:|---:|---|
| **Catalogue (`v_catalogue`, 12 lignes)** | `19,8` | `20,6` | `c`: `index` (`uk_categorie_nom`, `rows=4`), `p`: `range` (`idx_produit_visible`, `rows=236`, BNL join) — **OK (`< 500 ms`, pas de balayage complet)** |
| **Recherche plein texte `LIKE`** | `19,7` | `20,6` | `produit`: `range` (`idx_produit_visible`, `rows=236`, `Using index condition; Using filesort`) — **OK (`< 500 ms`)** |
| **10 dernières commandes client** | `19,3` | `20,2` | `commande`: `ref` (`idx_commande_client`, `rows=79`, `Using where; Using filesort`) — **OK (`< 500 ms`)** |
| **Indicateurs du jour (`GROUP BY` statut)** | `19,7` | `20,5` | `commande`: **`type=ALL`** (`aucun index`, `rows=1004`, `Using temporary; Using filesort`) — **OK (`< 500 ms`), à indexer au-delà de `50 000` lignes** |
| **Vue `v_etat_stock` (1 page)** | `19,8` | `20,6` | **OK (`< 500 ms`)** |
| **Écriture transactionnelle (`sp_create_order` + `sp_add_order_line` + `sp_confirm_order`)** | **`44,0`** | — | Verrou `SELECT ... FOR UPDATE` + triggers snapshot/stock/audit + calcul port (`CMD2026-001005 · 215,88 EUR · port 0,00`, nettoyée après mesure) |

*Tableau 25 — Mesures SQL détaillées (`minishop_perf`, `100` itérations) et plans `EXPLAIN` (`tests/perf/dernieres_mesures.txt`).*

| Route HTTP évaluée (`N = 200` requêtes/URL) | Moyenne (ms) | Médiane (ms) | p95 (ms) | Code HTTP |
|---|---:|---:|---:|---:|
| `/` (accueil) | `3,4` | `3,4` | `3,7` | `200` |
| `/catalogue` | `6,1` | `6,0` | `6,5` | `200` |
| `/catalogue?cat=1&prix_max=500&stock=1&tri=prix_asc` | `2,9` | `2,7` | `3,6` | `200` |
| `/recherche?q=clavier` | `5,8` | `5,7` | `6,2` | `200` |
| `/produit/casque-sans-fil-aura` | `1,2` | `1,2` | `1,4` | `200` |
| `/panier` | `1,3` | `1,2` | `1,5` | `200` |
| **Verdict global `ENF-01`** | — | — | **`6,0 ms` global** | **0 réponse inattendue — CONFORME (`< 500 ms`)** |

*Tableau 26 — Mesures HTTP détaillées par route sur `http://127.0.0.1:18080` (`N = 200` req./URL).*

#### 4.4.2 Mise en perspective des mesures VM face aux piles concurrentes

À la lumière des mesures observées sur notre VM Debian 13 (PHP `8.4.27` + MariaDB `11.8.6`), il convient de situer précisément le comportement de MiniShop par rapport aux autres architectures évoquées au Chapitre 1 (`Tableaux 6 à 8`) :

| Scénario de charge évalué | **Mesure réelle MiniShop (PHP 8.4 + MariaDB 11.8.6)** | **Comportement attendu d’un framework + ORM lourd** | **Comportement attendu avec SQLite ou NoSQL** |
|---|---|---|---|
| **Navigation catalogue & fiche produit (`200` req./URL)** | **p95 = 6,0 ms** (`1,2` à `6,1 ms` en moyenne) grâce à l’absence d’ORM, au routeur léger et aux vues SQL pré-jointes | Surcoût d’hydratation d’entités et d’injection de dépendances (généralement `+15` à `+40 ms` à froid/moyenne selon le cache) | SQLite rapide en lecture pure, mais dégradation sévère dès qu’apparaissent des écritures concurrentes |
| **Transaction complète de commande (`sp_create_order` + lignes + `sp_confirm_order`)** | **44,0 ms** en moyenne incluant verrou `FOR UPDATE`, snapshot tarifaire, calcul de port et insertion d’audit | Plusieurs allers-retours applicatifs + requêtes ORM supplémentaires si l’on ne déporte pas la logique en procédure stockée | Sur SQLite : contention globale de fichier ; sur NoSQL : nécessité de transactions distribuées ou risque de désynchronisation du stock |
| **Contention critique (`20` acheteurs simultanés pour `12` unités)** | **8/8 scénarios réussis**, **`0` survente** : le verrou InnoDB ligne par ligne sérialise exactement les `12` premiers achats | Risque classique de *race condition* si l’ORM lit puis met à jour le stock en PHP sans verrou `FOR UPDATE` explicite | Échecs `SQLITE_BUSY` fréquents ou risque de stock négatif sans opérateur atomique strict |

*Tableau 27 — Mise en perspective des résultats expérimentaux de la VM Debian 13 face aux piles alternatives.*

### 4.5 Analyse critique des biais, limites du prototype et risques résiduels

Dans une démarche scientifique rigoureuse, nous consignons explicitement les limites des résultats obtenus :
1. **Recette manuelle navigateur en attente :** les `200` scénarios humains consignés dans `docs/13-suivi-recette-uc.csv` (`164` scénarios UC + `36` transitions `RB-11`) demeurent au statut `NON JOUÉ`, et aucun test sur l’hébergement mutualisé *LineServ* n’est revendiqué sans campagne dédiée.
2. **Biais de mesure locale :** les benchmarks `ENF-01` (`6,0 ms p95`) sont mesurés sur boucle locale (`127.0.0.1`) d’une VM dédiée, excluant la latence réseau WAN et la négociation TLS ; de même, `P-03` HTTP n’a pas fait l’objet d’une boucle séparée.
3. **Limites de couverture et contrats statiques (`docs/11` § 8) :** `scripts/coverage.php` trace uniquement le chargement de fichiers (sans Xdebug/PCOV) et `css_contract.php`/`responsive_contract.php` restent statiques hors lecteur d’écran physique.
4. **Écarts assumés en maquette (`docs/01` §8.10) :** paiement et expédition simulés, formulaire de contact journalisé localement sans SMTP, absence d’authentification multifacteur TOTP sur `SUPER` (recommandée par OWASP ASVS V4.3.1), absence de *rate limiting* applicatif sur `/connexion`, et indexation secondaire à prévoir sur `commande(statut)` au-delà de `50 000` commandes (`EXPLAIN type=ALL`).

\newpage

## Chapitre 5 — Exploitation (*Build → Run*), conformité légale, RGPD, gouvernance GitLab et barème

### 5.1 Prérequis d’hébergement, procédure de déploiement, retour arrière et MCO

L’exploitation cible (`STACK.md`, `docs/26-transition-exploitation-maintenance-build-to-run.md`) requiert PHP **8.2+** (`json`, `mbstring`, `pdo_mysql`), MySQL **8.0.16+** ou MariaDB **10.6+**, et Apache 2.4 (`DocumentRoot public/`, `mod_rewrite` activé ; le serveur intégré PHP de `run.sh` étant réservé au développement). Le passage en production impose :
1. La création d’un fichier `.env` hors racine Web (`APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql`) avec un utilisateur SQL applicatif aux droits restreints (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `EXECUTE`).
2. La suppression physique des scripts utilitaires `public/gen_volumes.php` et `public/mesurer.php` du répertoire public déployé.
3. Le remplacement des comptes de démonstration du seed par un compte `SUPER` privé fort.
4. La réalisation préalable d’une sauvegarde logique complète (`mysqldump --single-transaction --routines --triggers`) pour garantir un retour arrière (*rollback*) en moins de `15` minutes incluant procédures et déclencheurs.

\newpage

### 5.2 Conformité juridique e-commerce, audit des traceurs et politique de conservation RGPD (`docs/12` & `docs/18`)

Au-delà de la technique pure, un site de vente aux consommateurs en France est soumis à un socle juridique strict (LCEN, Code de la consommation, Code de commerce, RGPD et lignes directrices de la CNIL). L’audit juridique et documentaire consigné dans `docs/12-information-juridique.md` (état au 23 octobre 2026) et `docs/18-politique-conservation.md` constitue un **document de travail pédagogique** — et non un avis juridique ni une certification de conformité — permettant d’exposer avec transparence devant le jury ce qui est déjà implémenté dans MiniShop et les prérequis bloquants avant toute ouverture commerciale réelle.

#### 5.2.1 Cadre légal e-commerce français et traduction dans les pages réglementaires

Huit pages dédiées (`app/View/home/`) traduisent les obligations précontractuelles et d’information observées dans les textes officiels français (`tests/php/information_juridique_test.php` vérifie leur accessibilité et leurs contrats de contenu) :

| Obligation légale (France) | Références juridiques | Traduction exacte dans MiniShop (`app/View/home/`) |
|---|---|---|
| **Identification & Informations précontractuelles** | LCEN art. 6-III-2, DGCCRF e-commerce | `/mentions-legales` (`mentions_legales.php`) : édition étudiante non professionnelle (Johan Polsinelli & Chahd Gouiaa, co-direction de publication, e-mails universitaires, hébergement visé IUT Nice Côte d’Azur) ; `/cgv` (`cgv.php`) et `/cgu` (`cgu.php`) ; total TTC + frais simulés avant confirmation. |
| **Livraison et retours** | Code de la consommation, fiches DGCCRF | `/livraison` (`livraison.php`) : modalités générales de la maquette sans inventer ni transporteur ni délai contractuel fictif. |
| **Droit de rétractation (14 jours)** | Art. `L. 221-21`, `D. 221-5`, modèle `R. 221-1`, avis type `R. 221-3` | `/retractation` (`retractation.php`) : rappel du délai légal de 14 jours à compter de la réception, exceptions légales et formulaire type `R. 221-1` distinct de l’annulation avant expédition. **Bouton en ligne absent** (voir § 5.2.4). |
| **Garanties légales** | Art. `D. 211-2` et annexe partie A (conformité 2 ans & vices cachés) | `/garanties` (`garanties.php`) et fragment réutilisable `garanties_encadre.php` reproductant l’encadré réglementaire officiel dans `/cgv` (coordonnées du professionnel vendeur à compléter en production). |
| **Médiation de la consommation** | Fiche DGCCRF médiation | Mention explicite dans `/cgv` de la réclamation préalable écrite et de l’obligation de désigner un médiateur agréé avec adresse et site Web — **aucun médiateur fictif inventé** dans la maquette. |
| **Données personnelles (RGPD)** | RGPD chap. III (art. 12–23), CNIL transparence et durées | `/confidentialite` (`confidentialite.php`) : décrit factuellement les traitements observés dans le code, les durées proposées et les éléments restant à fixer par un futur responsable de traitement. |
| **Cookies & autres traceurs** | Lignes directrices CNIL & FAQ cookies | `/cookies` (`cookies.php`) : détaille le cookie de session strictement nécessaire et le `localStorage` de préférences ; **aucun bandeau de consentement factice** tant qu’aucun traceur marketing/analytics n’est présent. |

*Tableau 28 — Cadre légal e-commerce français et traduction dans les pages réglementaires de MiniShop (`docs/12-information-juridique.md`).*

#### 5.2.2 Audit technique des cookies, du stockage local et minimisation des journaux

L’inspection statique des scripts PHP/JavaScript et des vues (`docs/12` § 2) établit un inventaire exhaustif des traceurs et stockages locaux actifs dans l’application :

| Mécanisme / Fichier observé | Données persistées & Durée | Attributs de sécurité & Statut CNIL | Action requise avant mise en production |
|---|---|---|---|
| **Cookie `MINISHOPSESS`** (`app/Security/Auth.php`) | Identifiant de session PHP (panier, auth client/admin, jeton CSRF) | `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` si HTTPS détecté. **Susceptible d’exemption** CNIL (strictement nécessaire au service demandé). | Vérifier `Secure=true` derrière un proxy TLS de confiance (`X-Forwarded-Proto`). |
| **Cookie `MINISHOPSESSID`** (`public/mesurer.php`) | Session de l’outil de benchmark interne | `HttpOnly`, `SameSite=Lax`, **`Secure=false`**. Outil de développement uniquement. | **Supprimer impérativement** `mesurer.php` et `gen_volumes.php` avant déploiement public. |
| **`localStorage` (`minishop.preferences.v1`)** (`public/js/preferences.js`) | Langue (`fr`/`en`) et fuseau horaire choisis par l’utilisateur | Stockage local sur demande explicite ; `tz-lookup.js` déduit le fuseau localement après clic sans transmettre ni sauvegarder les coordonnées GPS. Aucun pixel tiers ni SDK analytics détecté. | Refaire un inventaire réseau complet sur l’instance hébergée réelle ; ajouter un bandeau conforme si un traceur non exempté est intégré. |
| **Journaux JSON & Contact** (`var/journal/db.jsonl`, `var/log/contact.jsonl`) | Audit JSON et messages `/contact` (`<= 2 000` car.) | `NettoyageJournaux::ajouter` écrit sous verrou avec `chmod 0600` ; les nouvelles entrées `client`/`administrateur` dans `db.jsonl` **omettent nom, e-mail et `mot_de_passe_hash`** (minimisation). | Traiter les anciennes entrées historiques déjà écrites ; mettre en place un serveur SMTP réel pour `/contact` (aujourd’hui journalisé localement sans envoi d’e-mail). |

*Tableau 29 — Audit technique des traceurs, cookies et minimisation des données (`docs/12-information-juridique.md`).*

\newpage

#### 5.2.3 Politique de conservation RGPD, maintenance CLI (`db_init.php --nettoyage`) et archivage libsodium

Conformément à l’article 5 du RGPD (limitation de conservation), à l’article 17 (droit à l’effacement) et à l’article `L. 123-22` du Code de commerce (10 ans pour les seules pièces justificatives comptables concernées — et **non** pour l’intégralité des comptes ou brouillons), `docs/18-politique-conservation.md` propose une grille de rétention paramétrable (`parametre.conservation_compte = 24`) assortie d’outils CLI **simulés par défaut** :

| Catégorie de données | Durée proposée | Outil CLI dédié (`scripts/`) | Règles de sûreté, verrous et limites connues |
|---|---|---|---|
| **Comptes clients inactifs sans commande** | **24 mois** après dernière action | `purger_comptes.php` / `db_init.php --nettoyage comptes` | Exclut automatiquement tout compte lié à une commande (`RB-09`) ; applique une marge de sûreté de 2 jours ; informer la personne avant suppression selon procédure validée. |
| **Commandes & Comptes avec commandes** | Base active durant relation puis **archive intermédiaire** (10 ans max si pièce comptable `L. 123-22`) | *Non automatisé dans la maquette* | **Volontairement bloqué :** le trigger `trg_commande_delete` interdit de supprimer une commande validée et `trg_ligne_restore_stock` restituerait le stock. Nécessite une future procédure d’anonymisation transactionnelle dédiée respectant les gels contentieux. |
| **Messages Contact (`var/log/contact.jsonl`)** | **12 mois** après `date` | `db_init.php --nettoyage contact` | Rotation atomique sous `flock` + `chmod 0600` (extension POSIX requise) ; les dates invalides ou futures sont conservées ; arrêt sûr si ligne `> 1 Mio`. |
| **Journaux JSON (`var/journal/db.jsonl`)** | **6 mois** glissants après `at` | `db_init.php --nettoyage journaux` | Ne couvre pas les journaux serveur Apache/OS ni les copies hébergeur ; nécessite arrêt préalable des anciens travailleurs sans verrou. |
| **Sauvegardes locales dédiées** | **30 jours** minimum (2 plus récentes conservées) | `db_init.php --nettoyage sauvegardes` | Exige `NETTOYAGE_SAUVEGARDES` (`0700` hors webroot), fichier marqueur `.minishop-sauvegardes` contenant `MINISHOP_BACKUPS_V1`, format strict `minishop-AAAAMMJJThhmmssZ-<32hex>.backup`. |
| **Extractions chiffrées ponctuelles** | Sur durée justifiée + **12 mois** pour `acces.jsonl` | `scripts/archive_restreinte.php` | Boîtes scellées **libsodium** (clé publique sur serveur, clé privée **hors serveur** sur machine de confiance), plafond `8 Mio`, journal local `acces.jsonl` (UID + SHA-256). *Limite :* chiffre et détecte la corruption mais n’authentifie pas l’auteur et ne purge pas automatiquement à échéance. |

*Tableau 30 — Politique de conservation RGPD, maintenance CLI et archivage chiffré libsodium (`docs/18-politique-conservation.md`).*

**Verrou opérationnel strict contre la suppression accidentelle :** toute exécution réelle via `php scripts/db_init.php --nettoyage <catégorie>` exige **simultanément** les deux options `--executer --validation-absence-gel`, attestant que le responsable a vérifié l’absence de demande de droits, d’obligation légale ou de gel contentieux sur les éléments visés (et ne recrée jamais la base, contrairement à `--yes`).

#### 5.2.4 Les 7 points bloquants audités avant ouverture commerciale réelle (`docs/12` § 3)

Dans une démarche d’ingénierie responsable, l’audit juridique établit explicitement les **sept prérequis bloquants** qui interdisent de mettre la maquette telle quelle en production commerciale :

| N° | Point bloquant audité (`docs/12`) | État constaté dans la maquette MiniShop | Travaux obligatoires avant mise en vente réelle |
|---:|---|---|---|
| **1** | **Identification du vendeur commercial** | Mentions légales étudiantes non professionnelles | Publier raison sociale, forme juridique, capital, adresse, téléphone, RCS/RNE, TVA intracommunautaire et hébergeur de production effectif. |
| **2** | **Finalisation des CGV & Médiation** | Clauses génériques et aucun médiateur fictif | Compléter moyens de paiement, délais réels, modalités de retour, coordonnées du garant et médiateur de la consommation désigné. |
| **3** | **Bouton de rétractation en ligne (19 juin 2026)** | **Fonction absente** (`L. 221-21` & `D. 221-5` : formulaire gratuit direct « confirmer la rétractation » + accusé sur support durable) | Concevoir et tester le formulaire accessible sans connexion bloquante, avec preuve d’horodatage durable — une page statique ou l’annulation avant expédition ne suffit pas. |
| **4** | **Paiement réel & Preuve contractuelle** | Bouton et statut `PAYEE` simulés | Intégrer PSP conforme, mention explicite « commande avec obligation de paiement », correction avant validation et confirmation durable par e-mail. |
| **5** | **Gouvernance RGPD complète** | Conservation proposée non automatisée (`cron` absent) | Désigner responsable de traitement/DPO, traiter les demandes d’accès/effacement (y compris anonymisation des commandes terminées), et vérifier les sous-traitants. |
| **6** | **Sécurité d’hébergement réel** | Non testé derrière proxy mutualisé *LineServ* | Imposer TLS + `Secure`, supprimer `mesurer.php` / `gen_volumes.php`, vérifier permissions fichiers hors `public/`, sauvegardes et supervision. |
| **7** | **Audit d’accessibilité réel** | Tests CSS automatisés uniquement | Réaliser un audit manuel complet RGAA / WCAG 2.1 AA avec lecteurs d’écran et navigateurs multiples. |

*Tableau 31 — Synthèse des 7 points bloquants à lever avant toute exploitation commerciale réelle.*

### 5.3 Analyse des écarts vis-à-vis du cahier des charges initial et bilan réflexif

Quatre évolutions architecturales ou de sécurité ont été apportées et tracées par rapport à la rédaction initiale du CDC :
- **En-tête `X-Frame-Options: DENY` vs `SAMEORIGIN` :** `public/index.php` et `public/.htaccess` imposent `DENY` (et CSP `frame-ancestors 'none'`) pour une protection maximale contre le *clickjacking*.
- **Subdivision du rôle administrateur (`SUPER` / `GESTIONNAIRE`) :** application stricte du moindre privilège (OWASP ASVS V4.1.3) isolant la gestion de l’équipe et des paramètres.
- **Ajout de la 9e entité `STOCK_MOVEMENT_HISTORY` :** nécessaire pour tracer de façon inaltérable l’auteur, l’écart et le motif obligatoire de chaque mouvement manuel `sp_adjust_stock`.
- **Déploiement à la racine `/` (`DocumentRoot public/`) :** évite les ambiguïtés de chemins relatifs en sous-répertoire.

Sur le plan réflexif, les difficultés les plus formatrices ont porté sur l’ordonnancement des déclencheurs SQL (`trg_ligne_immutable` devant précéder `trg_ligne_prix_snapshot` pour rejeter toute modification au lieu de la corriger silencieusement, `T-11`), la parité d’erreurs métier entre `SqlStore` et `JsonStore`, et l’harmonisation des 14 schémas UML 2.5 avec l’implémentation finale (`docs/23-audit-uml-diagrammes.md`).

\newpage

### 5.4 Gouvernance GitLab, `README.md`, soutenance (15 min) et grille de conformité au barème

Le dépôt comporte un `README.md` complet (présentation, architecture, installation `./run.sh --db` et `./run.sh`, répartition entre **Johan Polsinelli** et **Chahd Gouiaa**, rôle `Reporter` pour **Mr Thanh-Phuong Nguyen**). Le support de soutenance de `15` minutes est préparé (`docs/03`, `docs/12`, `livrables/minishop-soutenance.pptx`, `docs/24-preparation-soutenance-complete.md`).

| Bloc du barème officiel (p. 8–9) | Points | Pénalités listées par le sujet | Vérification de conformité MiniShop |
|---|---:|---|---|
| **i. Document spécification du système** | **20** | Diagramme UML manquant (`-1`), sécurité manquante (`-2`), règle métier manquante (`-2`), contrainte technique manquante (`-2`), architecture manquante (`-2`) | **Conforme :** `14` diagrammes UML (`docs/diagrams/`), `5` réponses sécurité + `SEC-01..14`, `RB-01..20`, `CT-01..12`, architecture MVC 3 couches (`docs/01` & Chap. 1–2). |
| **ii. Document d’analyse et conception de BD** | **20** | Procédure stockée manquante (`-1`, max `-5`), déclencheur manquant (`-1`, max `-5`), script SQL manquant (`-3`), MCD : entité manquante (`-2`), mauvaise cardinalité (`-1`) | **Conforme :** `9` entités et cardinalités justifiées, MLD 3FN, `sql/01..04` avec données, **18 procédures + 1 fonction** (`>= 5`), **16 triggers** (`>= 5`) + réponse pédagogique complète (`docs/04` & Chap. 3). |
| **iii. Application Web + 2 documents** | **30** | Non MVC (`-5`), mauvaise requête SQLi (`-2`), procédure manquante (`-1`), trigger manquant (`-1`), fonctionnalité manquante (`-3`), rôle manquant (`-5`), doc tests manquant (`-3`), auth/session manquante (`-3`) | **Conforme :** MVC strict (`15` contrôleurs), PDO préparé (`11/11` sécurité), toutes fonctionnalités Visiteur/Client/Admin, auth + sessions strictes, `docs/02` + `pv-recette.md` (`60/60`, `29/29`, `105/105`, `8/8`). |
| **iv. Soutenance + questions (15 min)** | **10** | Trame, timing et réponses aux questions du jury | **Prêt :** diaporama `12` slides, démo chronométrée 4 min, terminal SQL préparé, 20 Q/R (`docs/03` & `docs/24`). |
| **v. Contribution régulière GitLab & README** | **10** | Historique commits binôme, `README.md`, accès `Reporter` enseignant | **Prévu :** `README.md` complet, historique Git tracé, charte `docs/20` (vérification finale sur le dépôt GitLab IUT avant soutenance). |

*Tableau 32 — Contrôle complet de conformité au barème officiel et d’absence de pénalité.*

\newpage

## Chapitre 6 — Feuille de route d’industrialisation : vers une *marketplace* professionnelle, intelligente et automatisée (modèle « Amazon »)

Si MiniShop répond pleinement aux exigences académiques et transactionnelles d’une boutique mono-enseigne (`200` produits, `1 000` commandes/an selon `ENF-02`), l’ambition d’un système e-commerce professionnel à grande échelle — inspiré des standards industriels d’une *marketplace* moderne de type **Amazon** — requiert des évolutions structurelles maîtrisées. Plutôt que de rejeter l’architecture existante, ce chapitre démontre comment le socle conçu (`StoreInterface`, normalisation 3FN, verrous InnoDB, séparation MVC et audit immuable) constitue une base saine sur laquelle greffer quatre transformations majeures : l’industrialisation de l’infrastructure, l’ouverture multi-vendeurs, la recommandation personnalisée et l’automatisation complète du back-office vendeur.

### 6.1 Passage à l’échelle industrielle : paiement PSP, cache distribué, recherche et observabilité

La première étape consiste à transformer le prototype académique mono-serveur en une plateforme de production hautement disponible, conforme aux standards bancaires et capable d’absorber des pics de trafic (soldes, *Black Friday*) sans compromettre la cohérence des stocks :

| Domaine critique | État actuel dans MiniShop (V1) | Architecture cible de production professionnelle | Bénéfice mesurable & sécurité |
|---|---|---|---|
| **Paiement & Conformité PCI-DSS** | Simulation logique lors de `sp_confirm_order` (aucune donnée CB stockée, `[11/11]`) | Intégration d’un PSP (Stripe / Paybox / Adyen) par redirection ou *Elements* hébergés (SAQ A), clé d’idempotence (`idempotency_key`), webhooks signés HMAC et états `EN_ATTENTE_PAIEMENT -> PAYEE -> REMBOURSEE` | Zéro transit de numéro de carte sur les serveurs applicatifs ; aucune double facturation en cas de réessai réseau |
| **Sessions & Limitation de débit** | Sessions PHP sur disque local ; pas de *rate limiting* sur `/connexion` | Externalisation des sessions et verrous applicatifs dans **Redis** (cluster), limitation de débit (*sliding window* IP + compte) sur `/connexion`, `/inscription`, `/api/*` | Permet la scalabilité horizontale sur plusieurs nœuds PHP-FPM derrière un répartiteur de charge |
| **Moteur de recherche & Cache** | `sp_search_products` (index `FULLTEXT` / `LIKE` MariaDB, `20,6 ms` p95) | Indexation asynchrone dans **Meilisearch** ou **OpenSearch/Elasticsearch** (tolérance aux fautes de frappe, facettes dynamiques, synonymes) + cache Redis des pages catalogue chaudes | Maintien d’une latence `< 30 ms` même au-delà du million de références catalogue |
| **Base de données & Scalabilité** | Instance unique MariaDB `11.8.6` | Topologie **Primary + Read Replicas** (écritures `sp_*` sur le primaire, lectures `v_catalogue` et analytique sur les réplicas), index `commande(statut)`, sauvegardes continues PITR (*Point-In-Time Recovery*) | Séparation du trafic de consultation (90 %+) et du trafic transactionnel critique |
| **Sécurité avancée & Observabilité** | Bcrypt, CSRF, PDO, journaux fichiers simples | MFA TOTP obligatoire pour `SUPER` (OWASP ASVS V4.3.1), secrets en coffre-fort (Vault/KMS), logs structurés JSON avec `X-Request-Id`, traces **OpenTelemetry**, alertes Prometheus/Grafana | Détection proactive des anomalies, audit légal corrélé de bout en bout |

*Tableau 33 — Briques d’industrialisation requises pour transformer MiniShop en système de production.*

### 6.2 Évolution du modèle 3FN vers une *Marketplace* multi-vendeurs (*Seller Central* et *Buy Box*)

Le modèle actuel suppose un vendeur unique (l’administrateur de la boutique). Pour évoluer vers une place de marché de type *Amazon Seller Central*, il convient d’appliquer une distinction fondamentale : **dissocier la fiche produit canonique** (équivalent de l’*ASIN*, identité technique unique du produit évitant les doublons dans le catalogue) **des offres commerciales portées par les vendeurs tiers** (prix, stock, délai d’expédition, état neuf/reconditionné).

```sql
-- Extension incrémentale 3FN du schéma MiniShop vers le multi-vendeurs :
CREATE TABLE vendeur (
  id_vendeur       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  raison_sociale   VARCHAR(150) NOT NULL,
  siret            CHAR(14)     NOT NULL UNIQUE,
  statut_kyb       ENUM('EN_ATTENTE','VERIFIE','SUSPENDU') NOT NULL DEFAULT 'EN_ATTENTE',
  taux_commission  DECIMAL(5,2) NOT NULL DEFAULT 12.00,
  compte_psp_id    VARCHAR(120) NULL
) ENGINE=InnoDB;

CREATE TABLE offre_vendeur (
  id_offre         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_produit       INT UNSIGNED NOT NULL,
  id_vendeur       INT UNSIGNED NOT NULL,
  prix_ht          DECIMAL(10,2) NOT NULL CHECK (prix_ht > 0),
  stock            INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
  delai_exp_heures SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  est_buy_box      TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (id_produit) REFERENCES produit(id_produit) ON DELETE RESTRICT,
  FOREIGN KEY (id_vendeur) REFERENCES vendeur(id_vendeur) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

Le Tableau 34 synthétise l’impact architectural de cette extension sur nos règles existantes :

| Composant MiniShop | Évolution vers le modèle *Marketplace* multi-vendeurs | Préservation des garanties existantes |
|---|---|---|
| **Catalogue & *Buy Box*** | Un algorithme pondéré (`prix_ttc`, `delai_exp_heures`, taux de retour, note vendeur) élit l’offre par défaut (`est_buy_box = 1`) sur la fiche produit, tout en listant les autres vendeurs. | La table `produit` reste la référence canonique unique du catalogue. |
| **Panier & Ventilation (`Split Order`)** | Si un client achète simultanément deux articles de deux vendeurs distincts, la commande globale (`COMMANDE`) est ventilée en **sous-commandes** (`SOUS_COMMANDE_VENDEUR`), chacune ayant son propre suivi logistique. | La contrainte d’atomicité (`sp_create_order_from_basket`) verrouille par `FOR UPDATE` chaque `offre_vendeur` impliquée ; aucun passage sous zéro n’est possible. |
| **Flux financier & Séquestre** | Intégration de paiements scindés (*Stripe Connect* / *Adyen for Platforms*) : encaissement sur compte de cantonnement, prélèvement automatique de la commission (`taux_commission`) et virement au vendeur à confirmation de livraison (`LIVREE`). | Aucune détention illicite de fonds pour compte de tiers (conformité DSP2). |
| **Cloisonnement RBAC & Anti-IDOR** | Ajout d’un troisième espace dédié `/vendeur/*` protégé par `Auth::exigeVendeur()` et filtrage systématique `WHERE id_vendeur = ?` sur produits, stocks et sous-commandes. | Généralisation directe de la garde IDOR actuelle (`SEC-08`, `ENF-12`). |

*Tableau 34 — Extension du modèle relationnel 3FN et de la sécurité vers une marketplace multi-vendeurs.*

\newpage

### 6.3 Moteur de recommandation hybride (analyse du panier, filtrage collaboratif et sémantique)

Un levier décisif de croissance d’Amazon repose sur la personnalisation (« *Les clients ayant acheté cet article ont également acheté…* »). Notre modèle normalisé `ligne_commande` permet déjà de déployer une **V1 immédiatement opérationnelle** sans infrastructure d’apprentissage automatique complexe, puis d’évoluer par paliers vers un moteur hybride complet :

#### Phase 1 — Analyse du panier de la ménagère (*Market Basket Analysis*) directement sur SQL
À partir des commandes validées (`statut IN ('EN_PREPARATION', 'EXPEDIEE', 'LIVREE')`), on calcule périodiquement (tâche cron nocturne) les paires de produits co-achetés en mesurant trois indicateurs statistiques classiques :
- **Support** `P(A ∩ B)` : fréquence conjointe des deux articles dans l’ensemble des commandes ;
- **Confiance** `P(B | A) = P(A ∩ B) / P(A)` : probabilité d’acheter `B` sachant que `A` est dans le panier ;
- **Lift** `P(B | A) / P(B)` : force réelle de l’association (un *Lift* `> 1` élimine les best-sellers évidents achetés indépendamment).

```sql
-- Calcul nocturne matérialisé dans recommandation_produit (zéro coût sur le checkout) :
SELECT l1.id_produit AS produit_source,
       l2.id_produit AS produit_recommande,
       COUNT(DISTINCT l1.id_commande) AS co_achats
  FROM ligne_commande l1
  JOIN ligne_commande l2
    ON l1.id_commande = l2.id_commande
   AND l1.id_produit <> l2.id_produit
  JOIN commande c ON c.id_commande = l1.id_commande
  JOIN produit  p ON p.id_produit  = l2.id_produit
 WHERE c.statut IN ('EN_PREPARATION', 'EXPEDIEE', 'LIVREE')
   AND p.visible = 1 AND p.stock > 0
 GROUP BY l1.id_produit, l2.id_produit
HAVING co_achats >= 3
 ORDER BY l1.id_produit, co_achats DESC;
```

#### Phases 2 & 3 — Filtrage collaboratif, *embeddings* sémantiques et évaluation rigoureuse

| Palier moteur | Algorithmes & Données d’entrée | Problème résolu | Métriques d’évaluation & Garde-fous RGPD |
|---|---|---|---|
| **V1 — Co-occurrence SQL** | Règles d’association (`Support`, `Confiance`, `Lift`) sur `ligne_commande` | Recommandation produit-à-produit (« Produits complémentaires ») servie en `< 2 ms` depuis table matérialisée ou Redis | Couverture catalogue, taux de clic (CTR) ; aucune donnée personnelle requise (statistiques agrégées) |
| **V2 — Filtrage collaboratif & Contenu** | Factorisation matricielle implicite (**ALS / BPR**) sur vues, paniers et achats + **vecteurs sémantiques (*embeddings*)** sur `nom`, `description` et `categorie` (index `pgvector` ou Qdrant) | Résout le **démarrage à froid** (*cold start*) pour un produit neuf jamais commandé ou un nouveau client sans historique | Évaluation hors ligne par validation croisée temporelle (**Precision@10**, **Recall@10**, **NDCG@10**) |
| **V3 — Re-classement temps réel** | Modèle de classement contextuel (*Learning to Rank* léger) combinant affinité, **marge**, **stock disponible (`RB-18`)** et délai vendeur | Évite de recommander un article en rupture ou à faible qualité de service ; maximise la satisfaction et le panier moyen | **A/B testing** contrôlé ; conformité RGPD (consentement préalable, droit d’opposition au profilage art. 21 RGPD) |

*Tableau 35 — Architecture incrémentale du moteur de recommandation hybride.*

### 6.4 Automatisation du back-office vendeur, réapprovisionnement prédictif et logistique

Dans un back-office artisanal (et encore en partie dans MiniShop V1), l’administrateur doit surveiller manuellement `v_etat_stock`, saisir les ajustements `sp_adjust_stock` et faire évoluer manuellement les statuts `EN_PREPARATION -> EXPEDIEE -> LIVREE`. Pour passer à une exploitation professionnelle fluide pour des dizaines ou centaines de vendeurs, les opérations répétitives doivent être automatisées autour d’une **file de tâches asynchrones** (*Message Queue* : Redis Streams ou RabbitMQ + *workers* PHP CLI) :

| Processus back-office | Fonctionnement manuel actuel (V1) | Automatisation professionnelle cible | Impact opérationnel |
|---|---|---|---|
| **Réapprovisionnement prédictif** | Seuil statique `seuil_alerte = 3` par produit ; réassort saisi à la main (`DELTA`) | Calcul automatique du point de commande dynamique (`S_cmd = D_moy_30j × L_fournisseur + Z × σ_D × √L`) ; génération automatique de bons de commande fournisseur en brouillon | Réduit simultanément les ruptures de stock (`stock = 0`) et le sur-stockage immobilisant la trésorerie |
| **Import catalogue & Média** | Création/édition unitaire via formulaire `/admin/produits` | Import asynchrone massif (**CSV / XML / API REST vendeur**) avec validation atomique des contraintes `RB-02`/`RB-07`, redimensionnement automatique d’images (`WebP/AVIF`) et détection de doublons | Permet d’intégrer plusieurs milliers de références sans bloquer le serveur Web |
| **Facturation & Liasses PDF** | Récapitulatif HTML consultable en ligne | Génération asynchrone à la validation (`PAYEE`) d’une facture PDF horodatée et scellée au format **Factur-X** (hybride PDF/A-3 + XML structuré) archivée en stockage objet S3 | Conformité comptable et fiscale automatique dès la commande validée |
| **Expédition & Suivi transporteur** | Transition manuelle `EN_PREPARATION -> EXPEDIEE -> LIVREE` par l’admin | Appel API transporteur (Colissimo, Chronopost, Mondial Relay) générant l’étiquette PDF et le n° de suivi ; **webhooks transporteur** déclenchant automatiquement `sp_update_order_status` | Zéro ressaisie manuelle ; traçabilité client temps réel conservée dans `order_status_history` |
| **Tarification encadrée (*Repricing*)** | Modification manuelle du `prix_ht` | Règles de réajustement automatique face aux offres concurrentes de la *Buy Box*, strictement bornées par un plancher de rentabilité (`prix_ht >= cout_achat * (1 + marge_min)`) | Compétitivité continue sans jamais provoquer de vente à perte (`RB-02` étendue) |
| **Modération & Anti-fraude** | Contrôle humain a posteriori | Règles automatisées bloquant : hausse brutale de prix suspecte, pic d’annulations vendeur (`> 5 %`), commande atypique (score de risque avant expédition) et alertes RGPD automatisées | Protection des acheteurs et préservation de la réputation de la plateforme |

*Tableau 36 — Catalogue des automatisations du back-office vendeur et de la chaîne logistique.*

\newpage

### 6.5 Trajectoire de migration incrémentale en 4 paliers (*Strangler Fig Pattern*)

L’erreur classique d’une montée en charge consiste à vouloir tout réécrire d’un bloc (*Big Bang*). Grâce au contrat `StoreInterface`, à la centralisation des règles métier en dépôts PHP et à la séparation nette des couches MVC, l’évolution vers une plateforme de type Amazon doit suivre une **trajectoire incrémentale en 4 paliers** où chaque phase reste pleinement déployable et testable :

| Palier | Horizon / Objectif | Chantiers techniques prioritaires | Prérequis validé avant passage au palier suivant |
|---|---|---|---|
| **Palier 1 — Socle Production Mono-Boutique** | Court terme (mise en ligne commerciale réelle) | PSP (Stripe/Paybox) + webhooks idempotents ; SMTP transactionnel ; MFA `SUPER` ; Redis sessions + *rate limiting* ; index `commande(statut)` ; campagne Playwright + *LineServ* | 0 anomalie critique sur recette humaine (`200` scénarios) et test de charge ciblé |
| **Palier 2 — Automatisation Back-Office & Recommandation V1** | Moyen terme (productivité opérationnelle) | File asynchrone (*workers* CLI) pour factures Factur-X, étiquettes transporteur, webhooks de suivi et réapprovisionnement prédictif ; recommandation SQL co-achats (Tableau 35) | Mesure de réduction du temps de traitement commande et hausse du panier moyen par A/B test |
| **Palier 3 — Ouverture *Marketplace* Multi-Vendeurs** | Croissance catalogue (*Seller Central*) | Tables `vendeur`, `offre_vendeur`, `sous_commande_vendeur` (Tableau 34) ; portail `/vendeur/*` isolé ; flux séquestre *Split Payment* ; élection de *Buy Box* et import massif | Audits IDOR multi-tenant et réconciliation comptable automatisée à 100 % |
| **Palier 4 — Recherche Sémantique, IA & Haute Disponibilité** | Grande échelle | Meilisearch/OpenSearch ; recommandation hybride (ALS + *embeddings*) ; *Repricing* borné ; réplicas MariaDB en lecture ; architecture multi-nœuds avec traces OpenTelemetry | SLA `>= 99,9 %`, latence `p95 < 100 ms` sous charge `100x` supérieure à `ENF-02` |

*Tableau 37 — Plan de migration incrémental en 4 paliers vers une plateforme e-commerce professionnelle.*

Cette approche progressive préserve intégralement les garanties transactionnelles prouvées dans les chapitres précédents — verrouillage des stocks sans survente, immuabilité financière des commandes et traçabilité d’audit — tout en levant méthodiquement chaque limite du prototype académique.

\newpage

## Conclusion générale et perspectives

À l’issue de cette SAÉ 302, le projet **MiniShop** apporte une réponse complète, mesurée et documentée à la problématique initiale. En concevant une application **PHP 8.2+ sans framework tiers** adossée à une base relationnelle normalisée en **3FN** et protégée par **18 procédures stockées** et **16 déclencheurs**, nous démontrons que la logique transactionnelle critique (prévention de survente sous concurrence, immutabilité des tarifs facturés, traçabilité des statuts et des mouvements de stock) peut être garantie de manière stricte côté SGBD tout en maintenant une architecture MVC lisible, portable (`StoreInterface`) et performante (`6,0 ms` au 95e centile en HTTP).

Conscients de la frontière qui sépare une maquette académique d’une plateforme commerciale en production réelle, nous identifions quatre perspectives d’évolution prioritaires :
1. **Paiement et notifications transactionnelles :** raccordement à un prestataire de paiement en mode *sandbox* (Stripe / Paybox) avec webhooks signés, idempotence de confirmation et envoi d’e-mails via SMTP authentifié.
2. **Durcissement d’authentification et anti-abus :** ajout d’un second facteur TOTP sur le rôle `SUPER` (OWASP ASVS V4.3.1) et mise en place d’un limiteur de débit (*rate limiting*) sur les formulaires d’authentification et de contact.
3. **Finalisation de la recette humaine et indexation :** exécution et consignation des `200` scénarios manuels navigateur (`docs/13-suivi-recette-uc.csv`), campagne dédiée sur l’hébergement *LineServ* et ajout d’un index secondaire sur `commande(statut)` pour soutenir la montée en charge au-delà de `50 000` commandes.
4. **Automatisation navigateur continue :** extension des scénarios E2E Playwright sur l’ensemble des parcours multi-largeurs et accessibilité automatisée (`axe-core`).

\newpage

## Bibliographie et webographie

### Textes de référence académique et cahier des charges
- **[1] NGUYEN T.-P.** *Sujet officiel SAÉ 302 — MiniShop : Cahier des charges, Spécifications du système, Conception BD/SQL, Application PHP/PDO et Barème*, IUT de Nice Côte d’Azur, Université Côte d’Azur, 2026.
- **[2] AFNOR.** *Normes NF Z 41-006 (Présentation des thèses et documents assimilés) et NF Z 44-005 (Références bibliographiques)*, Paris : Association Française de Normalisation.

### Sécurité applicative, modélisation et protection des données
- **[3] OWASP Foundation.** *OWASP Top 10:2021 — The Ten Most Critical Web Application Security Risks* et *Application Security Verification Standard (ASVS) v4.0.3*. Disponible sur : `https://owasp.org/`.
- **[4] ANSSI.** *Recommandations pour la sécurisation des sites Web* et *Guide d’hygiène informatique*. Paris : SGDSN. Disponible sur : `https://cyber.gouv.fr/`.
- **[5] Object Management Group (OMG).** *OMG Unified Modeling Language (UML), Version 2.5.1*. 2017. Disponible sur : `https://www.omg.org/spec/UML/`.
- **[6] CNIL & Légifrance.** *Référentiel activités commerciales*, *Lignes directrices cookies et traceurs*, *Code de la consommation* (`L. 221-21`, `D. 221-5`, `R. 221-1`, `D. 211-2`) et *Code de commerce* (`L. 123-22`), 2024–2026. Disponibles sur : `https://www.cnil.fr/` et `https://www.legifrance.gouv.fr/`.

### Documentation technique des technologies employées
- **[7] The PHP Group.** *PHP Manual — PDO, Password Hashing (`password_hash`), Session Security*. Disponible sur : `https://www.php.net/manual/fr/`.
- **[8] MariaDB Foundation & Oracle Corp.** *MariaDB 10.6+ / MySQL 8.0 Reference Manual — InnoDB Locking Reads (`SELECT ... FOR UPDATE`), Stored Routines and Triggers*. Disponible sur : `https://mariadb.com/kb/` et `https://dev.mysql.com/doc/`.
- **[9] Mozilla Foundation (MDN Web Docs) & W3C.** *Content Security Policy (CSP), X-Frame-Options, SameSite Cookies* et *WCAG 2.1 Level AA*. Disponible sur : `https://developer.mozilla.org/` et `https://www.w3.org/TR/WCAG21/`.

### Ressources graphiques, licences et corpus documentaire interne
- **[10]** Lucide Contributors & Cole Bemis (Feather). *Lucide Icons* (licence **ISC**, SVG inline dans `app/Security/xss.php`) ; Google Fonts / Łukasz Dziedzic, *Lato Regular* (licence **SIL OFL 1.1**, glyphe `U+20AC` `public/fonts/minishop-euro.woff2`, `public/fonts/OFL.txt`).
- **[11]** Polsinelli J., Gouiaa C., *Architecture logicielle (`ARCHITECTURE.md`)*, *Cahier des charges (`docs/01`)*, *Document de tests et validation (`docs/02`)*, *Support de soutenance (`docs/03`)*, *Conception BD et SQL (`docs/04`)*, *Note cybersécurité (`docs/07`)*, *Stratégie de tests (`docs/11`)*, *Information juridique e-commerce (`docs/12`)*, *Politique de conservation (`docs/18`)*, *Audit UML (`docs/23`)*, *Direction artistique (`docs/DA.md`)*, *Procès-verbal de recette (`docs/pv-recette.md`)* et *Mesures de performance (`tests/perf/dernieres_mesures.txt`)*, Université Côte d’Azur, 2026.

\newpage

## Annexes

### Annexe A — Fiches synthétiques des 14 cas d’utilisation (`UC-01` à `UC-14`)

| ID | Cas d’utilisation | Acteur principal | Entrées / Préconditions clés | Procédures / Règles appliquées | Tests |
|---|---|---|---|---|---|
| `UC-01` | Consulter le catalogue | Visiteur | `page`, `cat`, `tri` (navigation publique) | `sp_search_products`, `RB-19`, `EF-VIS-01/03/05` | `F-01`, `F-03`, `F-04` |
| `UC-02` | Rechercher un produit | Visiteur | `q` (1..120 car.) + filtres cumulables | `sp_search_products`, `RB-19`, `SEC-01/02`, `ENF-04/05` | `F-02`, `S-01`, `T-25` |
| `UC-03` | Consulter la fiche produit | Visiteur | `slug` / `id_produit`, `visible = 1` | `v_catalogue`, `RB-02`, `RB-16`, `RB-19` | `F-05` |
| `UC-04` | Créer un compte | Visiteur | nom, prénom, email unique, mdp ≥ 8 car., CGV | `sp_create_account`, `password_hash`, `RB-01`, `RB-12` | `F-06`, `F-07`, `T-04`, `T-05` |
| `UC-05` | Se connecter / Gérer son compte | Visiteur / Client | email + mdp, session active pour profil | `sp_get_credentials`, `sp_update_client`, `SEC-04/05/06` | `F-08`, `F-10`, `F-11`, `S-05/06` |
| `UC-06` | Gérer le panier | Visiteur / Client | `id_produit`, `quantite`, produit visible | `PanierSession`, `RB-05`, `RB-15`, `RB-18`, `ENF-07` | `F-09`, `F-12..F-15` |
| `UC-07` | Passer une commande | Client | authentifié, panier ≥ 1 ligne, stock suffisant, CSRF | `sp_create_order_from_basket`, `sp_compute_shipping`, `RB-04/06/11/18/20` | `F-16..F-18`, `T-28`, `T-29` |
| `UC-08` | Consulter l’historique | Client | authentifié, appartenance `id_client` | `v_commandes_client`, `RB-06`, `RB-10`, `SEC-08` | `F-19`, `F-20`, `S-03` |
| `UC-09` | Annuler une commande (Client) | Client | commande propre, statut avant expédition, confirmation 2 étapes | `sp_cancel_order`, `trg_ligne_restore_stock`, `RB-11`, `RB-18` | `F-21`, `T-21` |
| `UC-10` | Gérer les produits | Administrateur | session admin, catégorie existante, ref & slug uniques | `sp_save_product`, `sp_delete_product`, `RB-02/03/07/09/14/16` | `F-22`, `F-23`, `T-15`, `T-20` |
| `UC-11` | Gérer les catégories | Administrateur | session admin, nom unique, suppression si vide | `sp_save_category`, `sp_delete_category`, `RB-07/08/14` | `F-25`, `T-14` |
| `UC-12` | Gérer les stocks | Administrateur | session admin, mode `DELTA`/`SET`, motif obligatoire | `sp_adjust_stock`, `v_etat_stock`, `trg_produit_regles`, `RB-03` | `F-26`, `F-27`, `T-19`, `T-26` |
| `UC-13` | Gérer les commandes (Admin) | Administrateur | rôle `GESTIONNAIRE` ou `SUPER` | `sp_revenue_report`, `RB-10/11/15` | `F-28`, `F-30`, `F-31` |
| `UC-14` | Modifier statut / Annuler (Admin) | Administrateur | commande non `BROUILLON`, matrice `RB-11`, commentaire | `sp_update_order_status`, `sp_cancel_order_admin`, `trg_history_statut` | `F-29`, `F-48`, `T-10/12/13/22` |

### Annexe B — Cartographie des 15 contrôleurs PHP et politiques d’accès

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

\newpage

### Annexe C — Répertoire complet des 9 tables, 3 vues, 1 fonction, 18 procédures et 16 triggers SQL

#### Tables (9), Vues (3) et Fonction (1)
- **Tables (9) :** `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre`.
- **Vues (3) :** `v_catalogue`, `v_etat_stock`, `v_commandes_client`.
- **Fonction (1) :** `fn_param(cle, type_attendu)`.

#### Catalogue des 18 procédures stockées (`sql/02_minishop_procedures.sql`)

| # | Procédure | Rôle principal | Règles / Tests associés |
|---:|---|---|---|
| 1 | `sp_create_account` | Création de compte client avec unicité e-mail et contrôle hash bcrypt | `RB-01`, `RB-12`, `T-04`, `T-05` |
| 2 | `sp_get_credentials` | Lecture sécurisée du hash et statut actif pour authentification | `SEC-04`, `F-08` |
| 3 | `sp_update_client` | Mise à jour profil client avec re-contrôle unicité e-mail | `RB-01`, `F-10` |
| 4 | `sp_search_products` | Recherche + filtres + tri liste blanche + pagination (2 jeux de résultats) | `RB-19`, `SEC-01`, `T-25` |
| 5 | `sp_save_product` | Création/édition produit avec recalcul TTC | `RB-02/07/14/16`, `F-22` |
| 6 | `sp_delete_product` | Suppression physique si jamais vendu, sinon masquage `visible = 0` | `RB-09/14/17`, `T-15`, `T-20` |
| 7 | `sp_adjust_stock` | Mouvement `SET`/`DELTA` verrouillé (`FOR UPDATE`) + audit `stock_movement_history` | `RB-03`, `T-19` |
| 8 | `sp_save_category` | Création / renommage de catégorie | `RB-08`, `F-25` |
| 9 | `sp_delete_category` | Suppression refusée si catégorie occupée | `RB-14`, `T-14` |
| 10 | `sp_create_order` | Ouverture d’un brouillon et attribution `CMDAAAA-NNNNNN` | `RB-08`, `F-16` |
| 11 | `sp_add_order_line` | Verrou `FOR UPDATE`, contrôle stock, insertion ligne et recalcul port | `RB-05/06/15/18`, `T-06`, `T-09` |
| 12 | `sp_create_order_from_basket` | Transaction panier complète (rollback intégral si un produit manque) | `RB-04/18`, `ENF-16`, `T-29` |
| 13 | `sp_confirm_order` | Contrôle appartenance `id_client`, `RB-04`, fige port et total | `SEC-08`, `RB-20`, `T-16/18/28` |
| 14 | `sp_update_order_status` | Changement de statut selon matrice `RB-11` | `RB-11`, `F-29`, `T-12/13` |
| 15 | `sp_cancel_order` | Annulation client avant expédition + restitution stock + solde montant à 0 | `RB-11/18/20`, `T-21`, `T-28` |
| 16 | `sp_revenue_report` | CA par statut, panier moyen et top 5 produits sur période UTC | `UC-13`, `F-31` |
| 17 | `sp_compute_shipping` | Lecture `parametre` (`fn_param`) : port dû ou franchise | `RB-20`, `T-28` |
| 18 | `sp_cancel_order_admin` | Annulation back-office (restitution avant expédition, pas de retour auto si `EXPEDIEE`) | `EF-ADM-07`, `F-48` |

#### Catalogue des 16 déclencheurs (`sql/03_minishop_triggers.sql`)

| ID | Déclencheur | Évènement | Rôle / Règle garantie | Test |
|---|---|---|---|---|
| `TRG-A1` | `trg_ligne_controle_insert` | `BEFORE INSERT ON ligne_commande` | Vérifie produit existant et `stock >= quantite` (`RB-17`, `RB-18`) | `T-08` |
| `TRG-A2` | `trg_ligne_decrement_stock` | `AFTER INSERT ON ligne_commande` | Décrémente le stock sans passage négatif (`Trigger 1 — Stock`) | `T-21` |
| `TRG-A3` | `trg_ligne_restore_stock` | `AFTER DELETE ON ligne_commande` | Restitue le stock à l’annulation ou purge de brouillon | `T-21`, `T-24` |
| `TRG-B1` | `trg_ligne_immutable` | `BEFORE UPDATE ON ligne_commande` | Interdit toute réécriture de prix/quantité (`RB-06`, `RB-09`) | `T-11` |
| `TRG-B2` | `trg_ligne_prix_snapshot` | `BEFORE INSERT ON ligne_commande` | Force le snapshot `prix_unitaire` et `total_ligne` depuis la base | `T-23` |
| `TRG-B3` | `trg_produit_ttc` | `BEFORE INSERT ON produit` | Calcule `prix_ttc` depuis `prix_ht` et `tva` (`RB-16`) | `Annexe E` |
| `TRG-B4` | `trg_produit_ttc_update` | `BEFORE UPDATE ON produit` | Recalcule `prix_ttc` sans toucher aux anciennes commandes | `T-23` |
| `TRG-B5` | `trg_produit_regles` | `BEFORE UPDATE ON produit` | Refuse `stock < 0` (`RB-03`) | `T-03` |
| `TRG-B6` | `trg_produit_delete` | `BEFORE DELETE ON produit` | Interdit la suppression physique d’un produit vendu (`RB-14`) | `T-15` |
| `TRG-C1` | `trg_categorie_slug` | `BEFORE INSERT ON categorie` | Génère automatiquement le slug si absent | `F-25` |
| `TRG-C2` | `trg_categorie_delete` | `BEFORE DELETE ON categorie` | Interdit la suppression d’une catégorie non vide (`RB-14`) | `T-14` |
| `TRG-D1` | `trg_commande_transition_statut` | `BEFORE UPDATE ON commande` | Applique la matrice `RB-11` et contrôle `montant_total` (`RB-15`) | `T-10`, `T-12`, `T-13` |
| `TRG-D2` | `trg_commande_controle_insert` | `BEFORE INSERT ON commande` | Contrôle client existant (`RB-10`), force montant initial à `0` | `T-17` |
| `TRG-D3` | `trg_commande_delete` | `BEFORE DELETE ON commande` | Interdit la purge après validation ou s’il reste des lignes | `T-24` |
| `TRG-D4` | `trg_history_statut` | `AFTER UPDATE ON commande` | Écrit `ORDER_STATUS_HISTORY` (`Trigger 2 — Historique`) | `T-22` |
| `TRG-D5` | `trg_history_creation` | `AFTER INSERT ON commande` | Enregistre la trace initiale `NULL -> BROUILLON` | `T-22` |

\newpage

### Annexe D — Synthèse des 29 scénarios SQL (`T-01` à `T-29`) et des 10 scénarios offensifs (`S-01` à `S-10`)

#### Couverture des 29 scénarios d’intégration SQL (`tests/php/sql_integration.php` — `29/29` sur VM Debian 13)
- **Intégrité catalogue & comptes (`T-01` à `T-05`, `T-14`, `T-15`, `T-19`, `T-20`) :** rejet prix HT nul, rejet stock négatif direct/trigger/procédure, unicité e-mail, rejet mot de passe non haché bcrypt, protection catégorie non vide et produit vendu (bascule `visible = 0`).
- **Lignes, snapshots & commandes (`T-06` à `T-11`, `T-16` à `T-18`, `T-23`, `T-24`) :** rejet quantité nulle, blocage survente, immutabilité `prix_unitaire` et `montant_total`, rejet commande sans ligne, rejet client inexistant, contrôle IDOR `sp_confirm_order`, persistance du prix facturé après hausse tarifaire, purge autorisée sur brouillon vide.
- **Transitions, vues, port & atomicité (`T-12`, `T-13`, `T-21`, `T-22`, `T-25` à `T-29`) :** rejet transitions interdites (`EN_PREPARATION -> BROUILLON`, `EXPEDIEE -> BROUILLON`), restitution exacte du stock à l’annulation, trace `ORDER_STATUS_HISTORY` sans doublon, filtrage `sp_search_products`, alertes `v_etat_stock`, cohérence des `3` vues, calcul et gel du port (`RB-20`), et rollback intégral sur erreur en milieu de panier.

#### Couverture des 10 scénarios offensifs de cybersécurité (`docs/07-note-cybersecurite.md` + `tests/security/controles.sh`)
- `S-01` Injection SQL (`?q=' OR 1=1 --`, tri altéré) → requêtes préparées PDO + liste blanche de tri.
- `S-02` XSS stocké (`<script>alert(...)`) → échappement `e()` + `Content-Security-Policy` stricte.
- `S-03` IDOR (`/mes-commandes/1..4` d’autrui) → contrôle d’appartenance PHP + SQL et réponse `404` neutre.
- `S-04` Fixation / vol de session → `session_regenerate_id(true)`, `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`.
- `S-05` CSRF sur routes POST et API → jeton CSPRNG vérifié par `hash_equals()`.
- `S-06` Force brute & énumération de comptes → bcrypt + messages d’erreur indifférenciés à la connexion.
- `S-07` Élévation de privilèges (`Client -> Admin` ou `GESTIONNAIRE -> SUPER`) → cloisonnement `client_id`/`admin_id`, gardes routeur.
- `S-08` Fuite d’informations dans les journaux / erreurs 500 → messages génériques hors mode debug, aucun secret dans les logs.
- `S-09` Falsification de prix/port côté client (`prix_unitaire=0.01`) → recalcul exclusif côté serveur/SGBD.
- `S-10` Détournement d’en-têtes / Clickjacking / fuite `.env` → `X-Frame-Options: DENY`, `.gitignore`, aucune donnée de carte (`S-11`).

\newpage

### Annexe E — Requêtes SQL de preuve de non-contradiction des redondances

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
