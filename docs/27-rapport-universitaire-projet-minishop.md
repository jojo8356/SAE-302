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
    - 4.1.3 Lecture architecturale : briques, interfaces, état, plan de contrôle et SPOF
    - 4.1.4 État et *stateless* : localisation exacte des 6 formes d’état et externalisation
    - 4.1.5 Synchrone, asynchrone, cohérence forte/éventuelle, *backpressure* et idempotence
    - 4.1.6 Dépendances, domaines de panne, rayon d’impact et frontières de confiance
    - 4.1.7 Point d’entrée : serveur web, *reverse proxy* et *forward proxy* selon le code
  - 4.2 Étude complète de design, direction artistique et accessibilité (`docs/DA.md`)
    - 4.2.1 Benchmark des 5 acteurs français du e-commerce et positionnement
    - 4.2.2 Palette WCAG 2.1 AA, typographie sourcée (`Lato` / `SIL OFL 1.1`) et grille
    - 4.2.3 Iconographie (`Lucide`), photos, 12 composants, UTC et i18n
    - 4.2.4 Parcours UX anti-désorientation (« ne jamais être perdu ») et lois européennes
  - 4.3 Stratégie de tests complète en pyramide (`docs/11`) et exigences `ENF-01` à `ENF-22`
    - 4.3.1 La pyramide des tests à 9 étages et sa justification méthodologique
    - 4.3.2 Protocoles SQL, contrats statiques, couverture honnête et garde de verdict
  - 4.4 Campagne VM Debian 13, mesures de performances et limites physiques mono-serveur
    - 4.4.1 Bilan des tests et mesures SQL (`EXPLAIN`) et HTTP (`p95 = 6 ms`)
    - 4.4.2 Mise en perspective des mesures VM face aux piles concurrentes
    - 4.4.3 Évaluation de la charge, répartition L4/L7, passerelle d’API et *Ingress*
    - 4.4.4 Disponibilité, durabilité, RPO/RTO, latence/débit et montée en charge
  - 4.5 Analyse critique des biais, limites du prototype et risques résiduels
- **Chapitre 5 — Exploitation (Build → Run), conformité légale, RGPD, gouvernance GitLab et barème**
  - 5.1 Prérequis d’hébergement, déploiement, MCO, moindre privilège et conteneurisation
  - 5.2 Conformité juridique e-commerce, audit des traceurs et politique de conservation RGPD (`docs/12` & `docs/18`)
    - 5.2.1 Cadre légal e-commerce français et traduction dans les pages réglementaires
    - 5.2.2 Audit technique des cookies, du stockage local et minimisation des journaux
    - 5.2.3 Politique de conservation RGPD, maintenance CLI (`db_init.php --nettoyage`) et archivage libsodium
    - 5.2.4 Les 7 points bloquants avant ouverture commerciale (dont rétractation 19 juin 2026)
  - 5.3 Analyse des écarts vis-à-vis du cahier des charges initial et bilan réflexif
  - 5.4 Gouvernance GitLab, `README.md`, organisation de soutenance et grille de conformité au barème
- **Chapitre 6 — Feuille de route d’industrialisation : vers une marketplace professionnelle, intelligente et automatisée (modèle « Amazon »)**
  - 6.1 Industrialisation : PSP, cache distribué, recherche et observabilité
  - 6.2 Évolution du modèle 3FN vers une *Marketplace* multi-vendeurs (*Seller Central* et *Buy Box*)
  - 6.3 Fournisseurs, fiches fournisseurs et approvisionnements back-office
  - 6.4 Moteur de recommandation hybride (SQL, collaboratif et sémantique)
  - 6.5 Automatisation back-office vendeur, réassort prédictif et logistique
  - 6.6 Trajectoire de migration incrémentale en 4 paliers (*Strangler Fig Pattern*)
- **Conclusion générale et perspectives**
- **Bibliographie et webographie**
- **Annexes** — A : 14 `UC` · B : 15 contrôleurs PHP · C : 9 tables, 18 proc., 16 triggers · D : 39 tests · E : Preuves SQL

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
- **Tableau 19** — Taxonomie, interfaces (protocole, port, API) et plan de données vs contrôle
- **Tableau 20** — Grille d’état (*stateful*/*stateless*), dépendances implicites et SPOF de MiniShop
- **Tableau 21** — Cartographie des 6 formes d’état dans le code réel de MiniShop
- **Tableau 22** — Compromis d’externalisation de l’état (mono-serveur vs multi-copies)
- **Tableau 23** — Flux synchrones V1 vs échanges asynchrones V2 et protection anti-cascade
- **Tableau 24** — Cohérence forte vs cohérence éventuelle, *backpressure* et idempotence
- **Tableau 25** — Graphe des dépendances (directes, transitives, implicites) et *blast radius*
- **Tableau 26** — Catalogue complet des 9 domaines de panne et solutions professionnelles

\newpage

- **Tableau 27** — Domaines de panne, coût d’évitement SPOF et frontières réseau vs confiance
- **Tableau 28** — Serveur web, *reverse proxy* et *forward proxy* selon le sens du trafic
- **Tableau 29** — État, sauvegarde, signaux `502`/`504` et compromis du point d’entrée MiniShop
- **Tableau 30** — Benchmark ergonomique des 5 concurrents et positionnement (`docs/DA.md`)
- **Tableau 31** — Palette chromotypographique et ratios de contraste WCAG 2.1 AA
- **Tableau 32** — Les 12 composants UI, règles WCAG 2.1 AA et microcopy éditoriale
- **Tableau 33** — Parcours UX anti-désorientation et conformité aux directives européennes
- **Tableau 34** — Synthèse des exigences non fonctionnelles (`ENF-01` à `ENF-22`)
- **Tableau 35** — Pyramide de validation complète à 9 étages (`docs/11-strategie-tests.md`)
- **Tableau 36** — Bilan chiffré des campagnes de tests automatisés et VM Debian 13
- **Tableau 37** — Mesures SQL détaillées (`minishop_perf`) et analyse des plans `EXPLAIN`
- **Tableau 38** — Mesures HTTP détaillées par route (`N = 200` requêtes/URL, `ENF-01`)
- **Tableau 39** — Mise en perspective des mesures VM face aux piles alternatives
- **Tableau 40** — Capacité physique mono-serveur et leviers d’optimisation/scaling
- **Tableau 41** — Répartition L4/L7, passerelle d’API et *Ingress* appliqués aux routes MiniShop
- **Tableau 42** — État, sauvegarde, signaux et compromis des briques de répartition
- **Tableau 43** — Disponibilité en série/parallèle, durabilité et objectifs RPO/RTO de MiniShop
- **Tableau 44** — Latence en percentiles, débit, montée verticale/horizontale et coût d’un neuf
- **Tableau 45** — Règle du moindre privilège : permissions par utilisateur et conteneurisation
- **Tableau 46** — Cadre légal e-commerce français (LCEN, Consommation, CNIL)
- **Tableau 47** — Audit technique des cookies, du `localStorage` et des journaux
- **Tableau 48** — Politique de conservation RGPD et limites assumées (`docs/18`)
- **Tableau 49** — Les 7 points bloquants avant toute mise en vente commerciale réelle (`docs/12`)
- **Tableau 50** — Contrôle de conformité face aux livrables et pénalités du barème
- **Tableau 51** — Briques d’industrialisation pour une plateforme de production
- **Tableau 52** — Extension du modèle 3FN vers une *Marketplace* multi-vendeurs
- **Tableau 53** — Gestion back-office des fiches fournisseurs et commandes d’approvisionnement
- **Tableau 54** — Architecture du moteur de recommandation hybride (V1 à V3)
- **Tableau 55** — Automatisations back-office vendeur, logistique et anti-fraude
- **Tableau 56** — Plan de migration incrémental en 4 paliers (MiniShop → *Marketplace*)

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

#### 4.1.3 Lecture architecturale de MiniShop : briques, interfaces, état, plan de contrôle et SPOF

Au-delà du découpage en couches logiques, une architecture Web se lit en distinguant pour chaque composant sa **famille** (application, *middleware*, service d’infrastructure), son **interface en trois couches** (protocole, port, API), son **mode d’échange** (synchrone ou asynchrone), son appartenance au **plan de données** ou au **plan de contrôle**, ainsi que son **état** (*stateful* ou *stateless*) et ses **points uniques de défaillance (SPOF)**.

1. **Taxonomie des briques, interfaces (Protocole / Port / API) et séparation des plans dans le code réel :**
   Dans une requête type (`Navigateur → Apache/PHP → MariaDB`), un composant joue plusieurs rôles à la fois : l’application PHP (`public/index.php`) agit comme **serveur** face au navigateur et comme **client** face à MariaDB (`DbConnexion::ouvrir()`). Le Tableau 19 cartographie chaque brique réelle du dépôt :

| Brique réelle du dépôt | Famille & Rôle | Interface : Protocole · Port · API | Plan (Données vs Contrôle) |
|---|---|---|---|
| **Code métier PHP** (`public/index.php`, `Router.php`, `Controller/`, `Repository/`, `PanierSession`) | **Application** (serveur HTTP / client SQL) | `HTTP/1.1` (`HTTPS` TLS en prod) · `80`/`443` (`8000` dev) · routes à liste blanche (`GET`/`POST` HTML + `/api/panier/*` JSON) | **Données** : catalogue, panier, commandes. **Contrôle** : gardes RBAC (`Router`), `ParametreRepository`. |
| **Apache 2.4 + PHP-FPM 8.2+** (ou serveur dev `run.sh`) | **Middleware** (point d’entrée HTTP & moteur *shared-nothing*) | `HTTP/HTTPS` (`443`) + `FastCGI` (`9000` ou socket Unix) · `public/.htaccess` (`mod_rewrite`) | **Données** : exécution PHP et fichiers `public/img/`. **Contrôle** : règles `.htaccess`, `php.ini`. |
| **Stockage de sessions** (`Auth::demarrer()`, `MINISHOPSESS`) | **Middleware** (état session & panier `PanierSession`) | Fichiers locaux `session.save_path` (ou `/tmp/minishop_sess/*.json` en WASM) ; cible prod **Redis** `RESP` · `6379` | **Données** : identifiant `client_id` / `admin_id`, panier, CSRF. **Contrôle** : `use_strict_mode=1`, `HttpOnly`. |
| **MariaDB 10.6+ / MySQL 8.0** (`SqlStore`, `DbConnexion`) | **Middleware** (SGBD relationnel transactionnel) | Protocole binaire MySQL/PDO · TCP `127.0.0.1:3306` (repli socket `/run/mysqld/mysqld.sock` sur erreur `1698`/`1045`) · SQL préparé, `3` vues, `18` `sp_*` | **Données** : `client`, `produit`, `commande`, historiques. **Contrôle** : table `parametre`, `administrateur`, privilèges `GRANT`. |
| **Repli `JsonStore`** (`data/minishop/*.json`) | **Middleware** local (mode dev/tests sans SGBD) | Système de fichiers POSIX + `flock()` sur `.minishop.lock` (aucun port réseau) · `StoreInterface` | **Données & Contrôle** colocalisés dans les fichiers JSON (mono-hôte uniquement). |
| **Infrastructure implicite** (`DNS`, `NTP/UTC`, `TLS`, `.env`) | **Services d’infrastructure** (non dessinés sur les schémas) | `DNS` (`53`), `NTP` (`123` → horloge système `UTC` lue par `App\Tools\Heure`), `TLS` (`443`), variables `App\Config\Env` | **Contrôle** : résolution de nom, validité des certificats (`cookie secure`), cohérence chronologique `UTC`. |

*Tableau 19 — Lecture architecturale des briques réelles de MiniShop : famille, interface en 3 couches et séparation des plans.*

2. **Modèle d’échange synchrone et propagation des pannes :**
   Dans la version V1 livrée, **100 % des flux sont synchrones** — y compris les requêtes `fetch()` vers `ApiController` (`/api/panier/etat`, `ajouter`, `retirer`) qui bloquent jusqu’au calcul serveur de `PanierSession` et à la lecture de stock dans MariaDB. En conséquence, toute indisponibilité ou contention sur MariaDB (`SELECT ... FOR UPDATE`) se propage immédiatement au worker PHP puis au navigateur. Le plan de données (navigation et commandes clients) reste toutefois **indépendant du plan de contrôle administratif** : une panne d’un script CLI (`db_init.php`) ou le blocage de l’espace `/admin/*` n’interrompt pas la consultation ni l’achat en front-office, mais gèle toute mise à jour de stock ou de tarif.

3. **État (*stateful* / *stateless*), dépendances critiques, SPOF et piège réplication/sauvegarde :**
   Appliquer la grille de criticité directement à notre implémentation met en évidence la concentration de l’état et les **points uniques de défaillance (SPOF)** actuels sur un déploiement mono-serveur, ainsi que la façon dont les évolutions des chapitres 4 à 6 y répondent :

| Brique MiniShop | Qui l’appelle ? | État (*Stateful* / *Stateless*) | Que sauvegarder ? | Impact si elle tombe (SPOF ?) & Remédiation |
|---|---|---|---|---|
| **Navigateur client** | Utilisateur final | Aucun état serveur (reçoit cookie `MINISHOPSESS`) | Rien | Seul cet utilisateur est impacté ; le repli sans JS (`ENF-07`) préserve toutes les fonctions. |
| **Frontal Apache + PHP** (`public/index.php`, `Router`) | Navigateur | **Stateless** (modèle *shared-nothing* PHP) | `.env` et code source Git (reconstructible) | **SPOF** sur mono-serveur ; levé par réplication horizontale derrière NGINX/HAProxy (§ `4.4.3`). |
| **Sessions disque** (`files` / `/tmp/minishop_sess`) | PHP (`Auth`, `PanierSession`) | **Stateful** sur disque local | Tolérable (perte = déconnexion + panier perdu) | **SPOF** + verrou `flock()` ; externalisation vers **Redis** pour rendre le frontal *stateless* (§ `4.4.3`). |
| **Base MariaDB** (`SqlStore`, `sql/01..03`) | Application PHP + scripts CLI | **Stateful** critique (données métier et financières) | **Priorité 1** : `mysqldump --single-transaction --routines --triggers` + binlogs | **SPOF critique** : toute la boutique s’arrête. **Attention** : une réplique en lecture (§ `4.4.3`) améliore la disponibilité et le débit de lecture, mais **ne remplace jamais une sauvegarde** car elle réplique aussi un `DELETE`/`DROP` accidentel ; d’où les dumps (§ `5.1.1`) et archives `libsodium` (§ `5.2.3`). |
| **Médias produits** (`public/img/produits/`) | Navigateur / Apache | **Stateful** sur disque | Volume médias (sauvegarde objet) | Images cassées en cas de perte disque ou de multi-frontaux sans stockage partagé (S3/CDN). |
| **Dépendances implicites** (`DNS`, `NTP/UTC`, `TLS`) | Navigateur & PHP (`Heure`, `Auth`) | Infrastructure externe | Configuration DNS, clés TLS | Souvent oubliées sur les schémas : une dérive `NTP` fausse `Heure::maintenantUtc()` (filtres `du`/`au`, purge RGPD `db_init.php --nettoyage`) ; un certificat expiré ou une panne DNS rend le site inaccessible. |

*Tableau 20 — Grille d’analyse d’état, de dépendance, de sauvegarde et de SPOF appliquée à MiniShop.*

#### 4.1.4 État et *stateless* : localisation exacte des 6 formes d’état dans MiniShop et externalisation

Une brique est **avec état (*stateful*)** lorsque deux requêtes identiques peuvent recevoir des réponses différentes selon ce qui a été mémorisé auparavant, et **sans état (*stateless*)** si l’on peut la détruire et la recréer à neuf à partir de son code et de sa configuration sans rien perdre (**test de destruction**). Une architecture Web robuste concentre tout l’état dans quelques briques spécialisées et conserve les instances applicatives jetables et multipliables.

1. **Inventaire forensique des six formes d’état dans le code de MiniShop :**
   L’audit direct du code source (`app/`, `public/`, `scripts/`) montre que l’état ne se limite pas à MariaDB ; il se répartit sous six formes précises :

| Forme d’état | Localisation exacte dans le code V1 | Propriétaire cible en production | Impact en cas de perte & règle d’exploitation |
|---|---|---|---|
| **1. Données métier durables** | MariaDB (`SqlStore` : `9` tables) ou repli `JsonTable` (`data/minishop/*.json` + `.minishop.lock`) | **SGBD MariaDB** (Primary + Replicas + dumps) | **Perte critique** (commandes, stocks, comptes). Sauvegarde prioritaire (§ `5.1.1`) ; `JsonStore` interdit en multi-hôtes. |
| **2. Fichiers & Archives** | Visuels statiques `public/img/` et `public/img/produits/` (`0` appel `move_uploaded_file` en V1) ; archives RGPD `libsodium` et journaux `NettoyageJournaux` | **Stockage objet (S3/MinIO)** pour médias/exports ; volume dédié `cli-maintenance` pour archives | Visuels manquants ou perte de preuves RGPD/comptables ; tout futur téléversement doit écrire hors disque local Web. |
| **3. Messages en transit** | **Aucun** en V1 (`100 %` synchrone) ; cible Palier 2 (§ `6.5`) | **Broker / File de messages** (`Redis Streams` / `RabbitMQ`) | Vérifier la durabilité configurée (disque *fsync* vs mémoire volatile) : une panne sans persistance perd les tâches (Factur-X, e-mails). |
| **4. Sessions & État client** | Serveur : `Auth::demarrer()`, `PanierSession` (`$_SESSION['panier']`), `Csrf::jeton()`, `flash_*` sur `files` (ou `/tmp/minishop_sess/*.json` en WASM). Navigateur : cookie `MINISHOPSESS` + `localStorage` (`public/js/preferences.js` : langue/fuseau uniquement) | **Cache partagé Redis** (`session.save_handler = redis`) ; `localStorage` reste côté navigateur | **État local caché n° 1** en V1 : perdre la brique déconnecte les clients et vide les paniers. En V2, Redis ne doit pas être traité comme un simple cache jetable s’il porte sessions et verrous. |
| **5. Données dérivées** | Vues `v_catalogue`, `v_commandes_client`, `v_etat_stock` recalculées à la volée (zéro cache en V1) | **Cache Redis** (catalogue) + **Meilisearch** + table `recommandation_produit` | Reconstructibles depuis MariaDB : leur perte coûte du temps de calcul (latence), jamais d’information métier. |
| **6. Configuration & Secrets** | `.env` (`App\Config\Env`), `app/Config/env.php`, variables système (`DB_*`, `MINISHOP_ENV_FILE`), clé `libsodium` CLI, certificats TLS | **Coffre de secrets / variables injectées** au démarrage (hors image Docker, droits `0400`) | Souvent oubliés dans l’inventaire : sans eux, aucun redémarrage ni chiffrement/TLS n’est possible. |

*Tableau 21 — Cartographie complète des six formes d’état dans le code réel de MiniShop et destinations d’externalisation.*

2. **Repérer l’état local caché et rejeter le piège des *sticky sessions* :**
   Les quatre questions de diagnostic appliquées au code livré révèlent où l’état se dissimule derrière l’apparence *stateless* du modèle *shared-nothing* PHP :
   - *Écriture disque hors journaux ?* **Oui** : fichiers de session PHP (`session.save_path` ou `/tmp/minishop_sess/*.json`), fichiers `data/minishop/*.json` sous `JsonStore`, et exports chiffrés `libsodium` générés par `scripts/db_init.php`.
   - *Tâches planifiées (`cron`) ?* **Oui** en exploitation : la purge RGPD (`--nettoyage`) et le calcul nocturne de co-achats (§ `6.4`) doivent tourner sur un **unique** conteneur `cli-maintenance` protégé par verrou (`flock`), jamais dupliqués sur chaque nœud Web.
   - *Piège des sticky sessions (affinité de session) :* lors de l’ajout d’un 2e frontal derrière NGINX/HAProxy, renvoyer un client toujours vers le même serveur PHP donne l’illusion du *stateless* tout en gardant `$_SESSION` sur le disque local. À la moindre panne ou mise à jour d’une copie, tous ses clients perdent leur panier `PanierSession`, et la charge se déséquilibre.
   - *Test de coupure de 50 % des copies en plein trafic :* en V1 (`files`), la moitié des sessions actives est perdue ; après externalisation vers **Redis**, **S3** et **MariaDB** (modèle cible `v2`), l’application PHP devient intégralement jetable et redéployable copie par copie sans coupure.

3. **Arbitrage d’architecture : ce que l’on gagne et ce que l’on paie en externalisant l’état :**

| Ce que l’on gagne en externalisant l’état (V2) | Ce que l’on paie en complexité d’exploitation |
|---|---|
| Ajout ou retrait instantané de frontaux Apache/PHP sans perte de panier ni de session | **Trois briques d’état** spécialisées à superviser et sécuriser (MariaDB, Redis, stockage objet S3/MinIO) au lieu d’une |
| Déploiements *rolling update* copie par copie sans déconnecter les utilisateurs | **Latence réseau supplémentaire** (`0,3`–`1 ms` en LAN) sur chaque lecture de session ou média externalisé |
| Perte d’un nœud applicatif absorbée automatiquement par les copies restantes | **Nouveaux modes de panne** : indisponibilité Redis (coupure sessions) ou S3 (images inaccessibles) |
| Sauvegarde ciblée et cohérente par famille de données (SQL, objets, secrets) | **Attention aux fausses sécurités** : un conteneur Docker avec volume monté reste *stateful*, et une réplique ne remplace jamais une sauvegarde |

*Tableau 22 — Arbitrage architectural : gains et coûts de l’externalisation de l’état pour MiniShop.*

#### 4.1.5 Synchrone, asynchrone et cohérence : lecture des échanges dans MiniShop

Le choix entre **appel synchrone** et **échange asynchrone** détermine comment les ralentissements se propagent en cascade, à quel moment une donnée devient visible partout (**cohérence forte** ou **éventuelle**) et quelles garanties doivent porter les consommateurs de messages (**idempotence** et ***backpressure***).

1. **État des lieux du code V1 : une chaîne 100 % synchrone et ses risques de cascade :**
   Dans l’implémentation actuelle de MiniShop, **tous les flux applicatifs sont synchrones** (`Navigateur → Apache/PHP → MariaDB/JsonStore → PHP → Navigateur`). Même les appels JavaScript `fetch()` de `public/js/panier.js` (vers `ApiController::ajouter()` / `retirer()`) et de `public/js/preferences.js` (vers `/admin`) sont asynchrones uniquement pour l’interface navigateur sans rechargement, mais **synchrones côté serveur** : le worker PHP bloque jusqu’à la réponse complète de `PanierSession` et MariaDB avant de retourner JSON (`200` ou `409`).
   L’audit de `app/Config/DbConnexion.php` et `app/Model/Data/JsonStore.php` montre l’absence de garde-fous avancés contre la **panne en cascade** en V1 :
   - **Délais d’expiration (*timeouts*) :** `DbConnexion::options()` ne fixe pas de `PDO::ATTR_TIMEOUT` explicite et `panier.js` n’utilise pas d’`AbortController` ; un blocage SQL ou un verrou coopératif `flock($verrou, LOCK_EX)` dans `JsonStore` / `NettoyageJournaux` retient le worker PHP jusqu’au `max_execution_time`.
   - **Nouvelles tentatives (*retries*) & disjoncteur (*circuit breaker*) :** un seul basculement maîtrisé existe dans `DbConnexion::ouvrir()` (repli TCP `127.0.0.1:3306` → socket Unix sur erreur `1698`/`1045`). Il n’y a ni boucle de réessai aveugle (qui amplifierait une surcharge) ni disjoncteur : si MariaDB ralentit (`SELECT ... FOR UPDATE` sur un *hot SKU*, § `4.4.3`), les `40`–`60` workers PHP-FPM saturent en cascade jusqu’au `502`/`504` du serveur HTTP.

| Flux / Traitement MiniShop | Mode V1 actuel | Mode cible V2 (architecture avec *broker*) | Protection contre les pannes en cascade |
|---|---|---|---|
| **Navigation catalogue, fiche produit, panier (`ApiController`)** | **Synchrone** (HTML / JSON immédiat) | **Synchrone** (lecture cache Redis + MariaDB) | Timeouts courts (`PDO::ATTR_TIMEOUT = 3 s`, `AbortController`), limitation de débit (`429`) en amont |
| **Validation commande & décrément stock (`sp_create_order_from_basket`)** | **Synchrone** (verrou `FOR UPDATE`) | **Synchrone** (l’utilisateur attend la confirmation immédiate) | Timeout SQL borné, file d’attente HTTP bornée (`503 Retry-After`) |
| **Paiement PSP, e-mails SMTP, PDF Factur-X, indexation recherche, réassort** | Hors périmètre / manuel | **Asynchrone** (*Broker* `Redis Streams` ou `RabbitMQ` + *workers* PHP CLI) | Isolation temporelle totale : une panne SMTP ou PDF ne fait jamais échouer la commande |

*Tableau 23 — Flux synchrones dans le code V1 vs découplage asynchrone cible et garde-fous anti-cascade.*

2. **Cohérence forte vs cohérence éventuelle dans les données MiniShop :**
   Toute donnée du système ne tolère pas la même fraîcheur. La matrice ci-dessous distingue ce qui exige une **cohérence forte** (lecture immédiate de la dernière écriture sur le nœud *Primary*) de ce qui accepte une **cohérence éventuelle** (*eventual consistency*, avec retard transitoire de quelques millisecondes à quelques minutes) :

| Donnée ou vue MiniShop | Cohérence requise | Justification technique & piège à éviter |
|---|---|---|
| **Stock (`produit.stock`), panier, création/annulation commande (`sp_create_order_from_basket`, `sp_cancel_order`)** | **Forte** (`ACID`, *Primary* MariaDB + `FOR UPDATE`) | Une lecture périmée sur réplique provoquerait une **survente** (`RB-18`) ou une double restitution de stock. |
| **Affichage immédiat après achat (`CommandeController::valider` → `/mes-commandes/{id}`)** | **Forte** (*Read-Your-Own-Writes* sur *Primary*) | Lire sur une réplique asynchrone juste après `INSERT` ferait croire au client que sa commande a disparu (`404`). |
| **Catalogue public en cache Redis, recherche Meilisearch, `v_etat_stock` tableau de bord** | **Éventuelle** (TTL court ou événement de mise à jour) | Un décalage de quelques secondes est acceptable ; la vérification finale du stock au *checkout* reste toujours **forte**. |
| **Statistiques CA, co-achats (`recommandation_produit`), factures PDF, notifications** | **Éventuelle** (réplique en lecture ou traitement différé) | Décharge intégralement le nœud transactionnel principal sans risque métier. |

*Tableau 24 — Arbitrage entre cohérence forte et cohérence éventuelle selon la criticité des données MiniShop.*

3. **Backpressure, *backlog* et idempotence obligatoire des consommateurs :**
   Introduire un *broker* au Palier 2 (§ `6.5`) ne supprime pas les pannes : il les **déplace** vers la file si la consommation ralentit. Pour rendre ce modèle sûr en exploitation, trois règles complètent le socle V1 :
   - ***Backpressure* et borne de *backlog* :** borner la capacité des files (`MAXLEN` Redis Streams ou `x-max-length` RabbitMQ + *Dead Letter Queue* pour les messages en échec après `N` essais), limiter le `prefetch_count` des *workers* PHP CLI pour ne pas saturer MariaDB, et retourner `429 Too Many Requests` / `503 Service Unavailable` côté frontal lorsque la capacité est atteinte.
   - **Livraison « au moins une fois » et idempotence :** un *worker* qui génère une facture ou confirme un webhook PSP peut planter **après** exécution mais **avant** l’accusé de réception (`ACK`), provoquant un re-traitement. MiniShop applique déjà cette logique côté SGBD via `commande.numero` unique et la garde `statut <> 'ANNULEE'` dans `sp_cancel_order` (`test_annulation_concurrente.py`) : tout futur consommateur asynchrone devra porter une **clé d’idempotence unique** (`event_id` en contrainte `UNIQUE`) et privilégier des transitions d’état idempotentes (*« passer de `EN_ATTENTE` à `PAYEE` »* plutôt que *« créditer à nouveau »*).
   - **Ne jamais rendre asynchrone ce que l’utilisateur attend :** la validation de disponibilité stock et l’enregistrement de la commande doivent rester synchrones ; seules les tâches post-validation (e-mail, PDF Factur-X, étiquette, indexation) quittent le chemin critique HTTP.

#### 4.1.6 Dépendances, domaines de panne, rayon d’impact et frontières de confiance dans MiniShop

Lire une architecture en production consiste à ne plus regarder chaque brique isolément, mais à tracer son **graphe complet de dépendances** (directes, transitives et implicites), à repérer dans quels **domaines de panne** se trouvent les copies, à borner le **rayon d’impact (*blast radius*)** et à distinguer **frontière réseau** et **frontière de confiance**.

1. **Dépendances directes, transitives, implicites et rayon d’impact sur le code réel :**
   La panne d’une brique remonte toujours en sens inverse des flèches d’appel :

| Type de dépendance | Chaîne exacte observée dans MiniShop | Rayon d’impact (*blast radius*) & Mécanisme de dégradation |
|---|---|---|
| **Directe** | `public/index.php` / `Router` → `Database::store()` (`SqlStore`) ; `PanierSession` → `ProduitRepository` & `ParametreRepository` | Toute indisponibilité de MariaDB remonte en `PDOException` capturée par le front-controller (`HTTP 500` générique sans détail interne). |
| **Transitive** | `Navigateur → Apache/PHP → ApiController → PanierSession → MariaDB` | Si JavaScript ou `/api/panier/*` échoue, `ENF-07` garantit la **dégradation gracieuse** vers `POST /panier/ajouter` HTML ; si MariaDB tombe, l’application ne bascule **jamais** automatiquement sur `JsonStore` (`STORAGE_DRIVER` fixe dans `.env`) afin d’éviter toute désynchronisation de stock. |
| **Implicite (hors schéma MVC)** | **DNS** (résolution d’hôte), **TLS** (drapeau `secure` de `MINISHOPSESS` dans `Auth::demarrer()`), **NTP/UTC** (`DbConnexion::imposerFuseauUtc()` + `Heure::maintenantUtc()`), extensions PHP + collation `utf8mb4_unicode_ci` | Souvent oubliées : une dérive `NTP` fausse les bornes `du`/`au` et la purge RGPD (`db_init.php --nettoyage`) ; un certificat TLS expiré bloque l’accès HTTPS. **Atout `ENF-20` :** zéro dépendance CDN externe (polices `minishop-euro.woff2` et SVG Lucide embarqués en local), réduisant d’autant le *blast radius* externe. |

*Tableau 25 — Graphe des dépendances (directes, transitives, implicites) et rayon d’impact dans le code de MiniShop.*

   En complément, le cloisonnement entre espace public et `/admin/*` (`RB-13`, puis séparation en 4 conteneurs au Tableau 43) et le découplage asynchrone Palier 2 (§ `6.5`) appliquent les trois leviers classiques de réduction du *blast radius* : **cloisonner** (une panne `cli-maintenance` ou `admin-web` ne touche pas `front-web`), **dégrader plutôt que tomber** (sans cache Redis en V2, les requêtes retombent sur MariaDB ; sans JS, le parcours HTML reste opérationnel), et **découpler** (la panne du worker SMTP retarde un courriel sans jamais bloquer une commande).

2. **Domaines de panne (*failure domains*) complets, solutions d’ingénierie et frontières réseau vs confiance :**
   Un **domaine de panne** regroupe tout ensemble de composants pouvant tomber simultanément parce qu’ils partagent une même dépendance physique, logicielle, réseau, géographique ou logique. Sur un mono-serveur *LineServ* ou un VPS unique (`ENF-03`), toutes les briques de MiniShop partagent le même domaine de panne. Pour concevoir une architecture de niveau professionnel, l’analyse doit couvrir **les neuf domaines de panne emboîtés ou transverses possibles**, du processus jusqu’au risque humain et logique, avec des remédiations concrètes appliquées à notre pile (`Tableau 26`) :

   - **Domaines matériels & locaux (processus, disque, hôte, baie) :** limiter la consommation CPU/RAM par conteneur (`cgroups`) et borner les durées d’exécution (`PDO::ATTR_TIMEOUT`, `request_terminate_timeout`, `AbortController`) ; sortir tout état du disque local Web (`sessions` → Redis Sentinel, médias → stockage objet S3/MinIO multi-AZ, journaux → collecte centralisée hors nœud avec rotation et alertes disque/inodes à `80 %`) ; imposer une **anti-affinité stricte** entre répliques pour que deux frontaux PHP ou deux nœuds MariaDB ne résident jamais sur le même hyperviseur ni dans la même baie électrique (`Switch TOR` / `PDU`).
   - **Domaines de zone, de région et anti-*split-brain* SGBD :** répartir les frontaux PHP *stateless* et le répartiteur de charge (paire VRRP `Keepalived` ou *Load Balancer* managé multi-AZ) sur au moins deux **zones de disponibilité** (`Zone A` / `Zone B`). Pour MariaDB, la promotion automatique en cas de perte de `Zone A` exige un mécanisme à **quorum strict** (orchestrateur éprouvé type **MariaDB MaxScale** / **Orchestrator** ou grappe **Galera à 3 nœuds** sur 3 zones ou 2 zones + arbitre `garbd`, couplé à du **fencing / STONITH**) afin d’empêcher tout **double primaire (*split-brain*)** acceptant des commandes contradictoires lors d’une partition réseau. Une grappe multi-régions (réplique asynchrone distante) reste réservée au Plan de Reprise d’Activité (`PRA`) sur sinistre majeur pour ne pas pénaliser la latence `FOR UPDATE`.
   - **Domaines transverses (fournisseur, DNS/TLS/NTP, dépendances tierces et erreurs logiques) :** supprimer les pannes d’infrastructure implicite via un **DNS redondé** chez deux opérateurs (*multi-DNS* à TTL `60`–`300 s`), le renouvellement TLS automatisé et supervisé (`certbot` + alerte `J-21`), des serveurs **NTP** redondés (`chrony`, critique pour `App\Tools\Heure::maintenantUtc()`) et un registre d’images miroir local (*pull-through cache*). Côté applicatif V2, adopter le pattern ***Outbox* transactionnelle** (écrire l’événement métier dans une table `outbox_event` au sein de la même transaction ACID que `sp_create_order_from_basket`, puis le relayer vers `RabbitMQ`/`Redis Streams`), un **disjoncteur (*circuit breaker*)** avec dégradation gracieuse sur les services externes (PSP, SMTP, Meilisearch), et des **sauvegardes immuables (*WORM / Object Lock*)** isolées dans un compte d’administration séparé, validées par des exercices de panne planifiés (*GameDays* : arrêt de 50 % des frontaux, bascule de zone, restauration complète sur base vierge).

| Niveau de domaine de panne | Exemple concret sur MiniShop (V1 → V2) | Impact brut sans protection | Solution d’architecture professionnelle |
|---|---|---|---|
| **1. Processus / Worker** | Fuite mémoire PHP-FPM, blocage `flock()` (`JsonStore` / `NettoyageJournaux`), requête SQL lente | Saturation des `40`–`60` workers et `502`/`504` HTTP | Timeouts courts (`PDO::ATTR_TIMEOUT = 3 s`, `AbortController`), `request_terminate_timeout`, isolation en 4 conteneurs (`cgroups`) |
| **2. Disque / Volume local** | Panne SSD/NVMe ou disque plein (`session.save_path`, `public/img/`, `/var/lib/mysql`) | Perte des sessions/paniers, images cassées ou arrêt brutal SGBD | Sessions sur **Redis Sentinel**, médias sur **S3/MinIO** multi-AZ, logs streamés hors hôte + monitoring `80 %` |
| **3. Machine physique / Hyperviseur** | Panne carte mère/noyau du VPS ou nœud *LineServ* (`ENF-03`) | Arrêt total simultané de PHP, Apache et MariaDB | Séparation des tiers + ≥ `2` frontaux PHP avec règle d’anti-affinité sur hyperviseurs distincts |
| **4. Baie / Commutateur (`Rack`)** | Coupure `PDU` électrique ou *switch TOR* de la baie | Perte simultanée de toutes les VM hébergées dans ce rack | Placement des copies sur baies et alimentations indépendantes (*rack anti-affinity*) |
| **5. Zone de disponibilité (`AZ`)** | Incendie, coupure réseau ou climatisation d’un centre de données | Perte complète de la `Zone A` (frontaux + Primary MariaDB) | Répartiteur HA multi-AZ (`Keepalived`/LB), frontaux PHP en `Zone A+B`, MariaDB Primary/Standby avec quorum + *fencing* anti-*split-brain* |
| **6. Région géographique** | Sinistre majeur ou panne globale du plan de contrôle d’une région cloud | Indisponibilité totale si tout est dans une seule région | Réplique asynchrone + sauvegardes chiffrées répliquées dans une 2e région (bascule PRA documentée) |
| **7. Fournisseur & Services implicites** | Panne registrar `DNS`, certificat `TLS` expiré (`cookie secure`), dérive `NTP/UTC` (`Heure`), registre Docker | Site injoignable, redémarrages bloqués, filtres `du`/`au` et purges RGPD faussés | Multi-DNS, renouvellement TLS supervisé (`J-21`), `chrony` NTP redondé, registre miroir local, zéro CDN (`ENF-20`) |
| **8. Dépendances tierces externes** | Panne ou latence du PSP paiement, SMTP, Meilisearch ou transporteur (V2) | Échec ou blocage synchrone du parcours d’achat | **Outbox transactionnelle** + file asynchrone (`Redis Streams`/`RabbitMQ`), disjoncteur, mode dégradé (catalogue sans cache/reco) |
| **9. Erreur logique / Humaine / Sécurité** | `DROP`/`DELETE` accidentel, bug applicatif, fuite `.env`, compromission `SUPER` | **Répliqué instantanément** sur toutes les zones (*blast radius* maximal) | Sauvegardes **hors ligne immuables (*WORM*)** dans compte isolé + RBAC/MFA/moindre privilège (§ `5.1.2`) + *GameDays* mensuels |

*Tableau 26 — Catalogue exhaustif des neuf domaines de panne possibles et solutions professionnelles appliquées à MiniShop.*

   - **Frontière réseau ≠ Frontière de confiance dans notre code (`Tableau 27`) :** supprimer un SPOF ou répartir sur plusieurs zones ne dispense jamais du contrôle applicatif et SQL.

| Axe d’analyse | Frontière physique / Réseau | Frontière de confiance réelle dans le code MiniShop (Zéro confiance implicite) |
|---|---|---|
| **Domaine de panne & Coût anti-SPOF** | V1 : `1` domaine unique (`ENF-03`). V2 : `>= 2` zones (double instance, réplication, arbitre anti-*split-brain*) | Une redondance n’est réelle qu’entre domaines matériels/réseaux distincts et avec sauvegarde immuable séparée. |
| **Internet → Apache (`public/.htaccess`)** | Pare-feu + `DocumentRoot public/` ; `.htaccess` bloque `.env`, `.sql`, `.log`, `.md` | **Zéro confiance navigateur :** routeur à liste blanche (`Router.php`), `Csrf::verifier()`, `e()`, `Auth::exige*()`, anti-IDOR `404` (`CommandeController`) et recalcul serveur des prix/stocks dans `PanierSession` / `ApiController`. |
| **PHP → MariaDB (`127.0.0.1:3306` / socket)** | Réseau privé local (port `3306` fermé vers l’extérieur en prod, § `5.1.2`) | **Un réseau local n’authentifie personne :** compte SQL dédié (`DbProvision`) ou socket Unix (`1698`/`1045`), liste blanche des tables/colonnes dans `SqlStore`, et re-vérification `id_client` / `RB-18` dans les procédures SQL (`sp_confirm_order`, `sp_cancel_order`, `ENF-19`). |

*Tableau 27 — Domaines de panne, coût de suppression des SPOF et frontières réseau vs confiance dans MiniShop.*

#### 4.1.7 Point d’entrée de MiniShop : serveur web, *reverse proxy* et *forward proxy* selon le code

Dans l’architecture Web de MiniShop, trois rôles HTTP coexistent ou apparaissent lors du passage en production — **serveur web**, ***reverse proxy*** et ***forward proxy*** — qu’il faut distinguer uniquement par le **sens du trafic** et **qui les appelle**, et non par le nom du logiciel (un même produit comme NGINX pouvant assumer les trois rôles).

1. **Rôles réels dans le code actuel (`V1`) vs architecture de production (`V2`) :**
   - **Serveur web (V1 dev et Apache) :** en développement (`run.sh`), le serveur intégré PHP (`php -S 127.0.0.1:8000 -t public scripts/dev-router.php`) sert directement les ressources statiques existantes (`return false`) et délègue le routage à `public/index.php` tout en répliquant les blocages de fichiers cachés (`.env`, `.sql`, `.log`). En hébergement cible V1 (`STACK.md`, `public/.htaccess`), **Apache 2.4 (`80`/`443`) + `mod_rewrite`** tient ce rôle : il sert lui-même `public/css/`, `public/img/`, `public/fonts/minishop-euro.woff2`, injecte les en-têtes défensifs (`nosniff`, `DENY`, `Referrer-Policy`) et exécute l’application PHP via `mod_proxy_fcgi` (`PHP-FPM`) ou module Apache.
   - ***Reverse proxy* (bordure TLS, *LineServ* ou NGINX/HAProxy en V2) :** placé entre Internet et les frontaux PHP-FPM/Apache, il reçoit les requêtes **entrantes** des navigateurs, assure la **terminaison TLS** (`443`), le routage par chemin (`/` → pool `front-web`, `/admin/*` → `admin-web` restreint IP/VPN), la répartition de charge (`§ 4.4.3`), la limitation de débit (`429`) et le masquage des serveurs internes.
   - **Piège critique vérifié dans notre code (`app/Security/Auth.php:58` & `docs/14-guide-lineserv.md:119`) :** aujourd’hui, `Auth::demarrer()` règle le drapeau `secure` du cookie `MINISHOPSESS` uniquement d’après `$_SERVER['HTTPS']`. Si un *reverse proxy* frontal (NGINX, HAProxy ou frontal *LineServ*) termine le chiffrement TLS et relaie en HTTP clair vers Apache/PHP, `$_SERVER['HTTPS']` reste vide et le cookie perd `Secure` tant que l’on n’adapte pas le proxy et Apache (`X-Forwarded-Proto: https` vérifié exclusivement depuis l’IP du proxy de confiance via `mod_remoteip`, ou variable FastCGI `HTTPS=on`).
   - ***Forward proxy* (trafic sortant en V2+) :** en V1 (`ENF-20`), MiniShop n’effectue **aucun appel HTTP sortant** à l’exécution (zéro CDN, zéro appel API externe, paiement simulé). Dès l’intégration d’un PSP (Stripe/Paybox), d’un relais SMTP ou d’API transporteurs au Palier 1–2 (`§ 6.1`), placer un **forward proxy** (type Squid / proxy d’egress) devant les conteneurs `front-web` et `cli-maintenance` permet de filtrer les sorties **par nom de domaine autorisé** (`api.stripe.com`, etc.), de journaliser les flux sortants et d’empêcher un conteneur compromis d’exfiltrer des données ou d’effectuer des appels SSRF arbitraires.

| Critère d’analyse | Serveur web (`Apache` / `dev-router.php`) | *Reverse proxy* (`NGINX` / `HAProxy` / frontal TLS) | *Forward proxy* (`Squid` / egress V2+) |
|---|---|---|---|
| **Qui l’appelle ?** | Navigateurs / clients HTTP (`80`/`443`, `8000` dev) | Clients externes Internet (`443` HTTPS) | Serveurs internes (`front-web`, `cli-maintenance`) ou postes admin |
| **Vers où envoie-t-il ?** | Répond lui-même (statiques `public/` + exécution PHP `public/index.php`) | Vers le pool interne `front-web` / `admin-web` | Vers Internet (API PSP Stripe/Paybox, dépôts OS/paquets) |
| **Ce qu’il protège / cache** | Rien (expose les fichiers autorisés de `public/`) | Cache la topologie et les IP internes des serveurs PHP | Cache l’IP des clients/serveurs internes et bloque les sorties non autorisées |
| **Fonction dans MiniShop** | `.htaccess`, MIME, static assets, exécution PHP | Terminaison TLS, routage L7 `/admin`, répartition de charge, `429` | Liste blanche de domaines sortants (anti-exfiltration / anti-SSRF) |

*Tableau 28 — Distinction serveur web, reverse proxy et forward proxy selon le sens du trafic dans MiniShop.*

2. **État porté, sauvegarde, pannes (`502`/`504`) et compromis du point d’entrée :**
   Contrairement à une idée reçue, un *reverse proxy* unique n’est pas « totalement sans état » au sens d’exploitation : il porte des **certificats TLS et clés privées**, ainsi que sa **configuration de routage et de filtrage** (à versionner dans Git et savoir réémettre automatiquement via Let’s Encrypt / ACME ou PKI interne). S’il tombe sans redondance (paire active/passive avec **IP flottante VRRP / Keepalived**), **toute la boutique devient inaccessible** même si PHP et MariaDB sont sains (`SPOF` d’entrée). En exploitation, les codes **`502 Bad Gateway`** et **`504 Gateway Timeout`** émis par NGINX/HAProxy signalent précisément un *reverse proxy* vivant devant un worker `PHP-FPM`/Apache arrêté ou saturé — évitant de chercher la panne côté certificat ou DNS.

| Question de la grille | Serveur web (`Apache` / `PHP-FPM`) | *Reverse proxy* (`NGINX` / `HAProxy`) | *Forward proxy* (`Squid` V2+) |
|---|---|---|---|
| **État porté & Sauvegarde** | Code déployé + `.htaccess` (Git) + certificats si sans proxy | **Configuration de routage** (Git) + **certificats/clés TLS** (ACME/coffre) | **Règles ACL de domaines autorisés** (Git) + cache de paquets éventuel |
| **Impact s’il tombe** | Indisponibilité du nœud (absorbée si pool ≥ `2` frontaux) | **SPOF critique** : toute la boutique est coupée si non doublé par VIP VRRP | Plus d’appels PSP/API externes ni de mises à jour sortantes |
| **Signaux à surveiller** | Erreurs `5xx`, saturation `pm.max_children`, latence `p95` | Codes **`502`** (backend mort) et **`504`** (timeout PHP/SQL), expiration TLS | Refus de domaine (`403`), volume sortant anormal, latence API externe |
| **Gain vs Coût d’entrée** | Simple en mono-serveur (`ENF-03`), mais expose directement Apache | Centralise TLS, routage, WAF/rate-limit et autorise ≥ `2` copies PHP | Une brique réseau en plus (saut + latence, risque de coupure si mal configuré) |

*Tableau 29 — État, sauvegarde, lecture des codes 502/504 et compromis d’architecture du point d’entrée.*

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

*Tableau 30 — Benchmark ergonomique des 5 concurrents français et positionnement de MiniShop (`docs/DA.md`).*

#### 4.2.2 Palette WCAG 2.1 AA, typographie sourcée (`Lato` / `SIL OFL 1.1`) et grille

| Jeton CSS (`app.css`) | Valeur hexadécimale | Rôle & Règle d’usage | Ratio WCAG 2.1 calculé |
|---|---|---|---|
| `--papier` / `--surface` | `#FBFAF7` / `#FFFFFF` | Fond papier chaud anti-fatigue / cartes et tableaux | — |
| `--encre` / `--encre-douce` | `#0F172A` / `#475569` | Textes principaux et titres / légendes et mentions secondaires | **17,1:1** / **7,3:1** sur papier |
| `--primaire` / `--primaire-fonce` | `#1D4ED8` / `#1E3A8A` | Liens, éléments actifs, contour focus 3 px / en-têtes sombres | **6,7:1** / **10,4:1** |
| `--accent` / `--accent-fonce` | `#C2410C` / `#9A3412` | **Uniquement l’action d’achat engagée** (1 seul CTA accent par écran) | **5,2:1** / **7,0:1** sur blanc |
| `--succes` / `--alerte` / `--danger` / `--info` | `#166534` / `#9A3412` / `#991B1B` / `#1E3A8A` | Badges d’état couplés à un fond pastel et **libellé texte obligatoire** (daltonisme) | **6,5:1** / **6,4:1** / **6,8:1** / **8,5:1** |

*Tableau 31 — Palette chromotypographique complète et ratios de contraste WCAG 2.1 AA.*

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

*Tableau 32 — Synthèse des 12 composants UI, règles WCAG 2.1 AA et discipline éditoriale.*

En complément, la traduction intégrale **FR / EN** (`public/js/i18n-en.js`, vérifiée sur `48` vues et `45` messages métier) et le choix du fuseau horaire (stockage serveur strictement **UTC** via `app/Tools/Heure.php`, projection navigateur selon `localStorage` et `public/js/tz-lookup.js` sans envoi GPS) assurent l’accessibilité linguistique et temporelle (`ENF-07`).

#### 4.2.4 Parcours UX anti-désorientation (« ne jamais être perdu ») et conformité aux lois européennes

L’ergonomie d’une boutique en ligne ne vise pas uniquement l’esthétique : elle doit garantir que l’utilisateur **sache à tout moment où il se trouve, ce qu’il peut faire ensuite et combien il paiera réellement**, tout en respectant le droit européen de la consommation et du numérique :

1. **Architecture anti-désorientation (« ne jamais être perdu ») :**
   - *Repères permanents :* l’en-tête collant (`64 px`) affiche en continu le logo d’accueil, les rubriques principales, la barre de recherche et le badge panier mis à jour sans rechargement (`aria-live="polite"`), tandis que le back-office utilise une barre latérale dédiée clairement distincte de la boutique.
   - *Zéro cul-de-sac (états vides guidés) :* aucun écran ne renvoie un tableau vide ou un message sec ; chaque état vide (panier vide, recherche infructueuse, historique sans commande) affiche une phrase explicative et un lien d’action direct vers le catalogue ou la réinitialisation des filtres.
   - *Mémoire d’état et gestion douce des erreurs :* les filtres de recherche et de tri sont encodés dans l’URL (`ENF-05`), les pastilles de filtres actifs sont retirables en un clic, les saisies de formulaire sont conservées après rejet serveur (`ENF-06`) et `ui.js` place automatiquement le focus sur le champ en erreur.
   - *Fluidité et réversibilité :* tout le tunnel fonctionne sans JavaScript (`ENF-07`), le paiement et l’annulation exigent une confirmation explicite en 2 étapes, et l’utilisateur bascule à tout instant en anglais ou dans son fuseau local (`/preferences`).

2. **Conformité UX aux directives et règlements européens :**

| Texte européen de référence | Exigence UX imposée | Traduction concrète dans MiniShop |
|---|---|---|
| **Directive 2011/83/UE (Droits des consommateurs, art. 5 §1 e & art. 8 §2)** | Clarté totale du prix TTC, de la TVA et des frais supplémentaires *avant* engagement ; bouton explicite d’obligation de paiement | Affichage permanent du TTC + HT + TVA (`RB-16`), calcul préalable des frais de port (`4,90 €` / gratuit `>= 80 €`, `ENF-14`), et récapitulatif complet avant confirmation (`sp_compute_shipping`). |
| **Règlement (UE) 2022/2065 (DSA, art. 25 — *Dark patterns*) & RGPD art. 7 / 12** | Interdiction des interfaces trompeuses (faux comptes à rebours, urgence factice, cases pré-cochées, consentement asymétrique) | Refus explicite des faux compteurs (`docs/DA.md`), stock réel affiché sans pression artificielle, couleur rouge réservée aux erreurs/ruptures, et absence de bandeau cookie trompeur. |
| **Directive (UE) 2023/2673 (Rétractation en ligne — 19 juin 2026)** | Bouton/fonctionnalité directe, visible et gratuite pour exercer la rétractation à distance avec confirmation et accusé durable | Page informative + modèle `R. 221-1` présents (`/retractation`) ; l’implémentation du bouton en ligne « confirmer la rétractation » est inscrite en **point bloquant n° 3** avant ouverture commerciale (Tableau 49). |
| **Directive (UE) 2019/882 (*European Accessibility Act*) & Norme EN 301 549** | Accessibilité numérique des services e-commerce (perceptible, utilisable, compréhensible, robuste — WCAG 2.1 AA) | Contrastes calculés `>= 5,2:1`, navigation intégrale au clavier + lien d’évitement, cibles tactiles `>= 44×44 px`, zoom `200 %` (`rem`), annonces `aria-live`, et `alt=""` sur vignettes redondantes. |

*Tableau 33 — Synthèse des mécanismes UX anti-désorientation et de leur conformité aux directives européennes.*

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

*Tableau 34 — Synthèse des exigences non fonctionnelles clés (`docs/annexes/annexe-enf.md`).*

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

*Tableau 35 — Pyramide de validation complète à 9 étages (`docs/11-strategie-tests.md`).*

#### 4.3.2 Protocoles d’isolation SQL, contrats statiques, couverture honnête et garde de verdict

- **Isolation de `sql_integration.php` :** conçu comme une enveloppe mince autour du harnais SQL de référence `tests/sql/run_tests.sh` (évitant la divergence entre deux jeux de tests), ce script crée une base jetable `minishop_test`, rejoue `fixture.sql` avant chaque scénario, **refuse par défaut toute base déjà existante** (`--reinitialiser-base-existante` requis) et **interdit formellement de cibler `DB_NAME` applicatif**.
- **Tests de contrat statiques (`contract_http`, `css_contract`, `responsive_contract`) :** attrapent les ruptures de cohérence entre fichiers écrits séparément (route renommée dans un `<a href>`, couleur hors palette, `style=` inline bloqué par la CSP, grille `min-width` provoquant un défilement mobile).
- **Couverture honnête sans Xdebug (`scripts/coverage.php`) :** conformément à `ENF-20` (zéro dépendance PCOV/Xdebug), mesure quels fichiers de `app/` sont chargés par les suites PHP afin de détecter les fichiers jamais exécutés (en complément de `scripts/code_mort.php` via tokens PHP et de `scripts/verifier_deprecations.py` sur le PHP CLI cible).
- **Fiabilité du verdict (`scripts/wasm_verdict.mjs`) :** testé sur **9/9 cas** pour garantir qu’une erreur fatale (`255`), un échec `❌` (`1`) ou un `NON JOUÉ` (`2`) ne puisse jamais être classé comme succès (`0`).

\newpage

### 4.4 Campagne VM Debian 13, mesures de performances et limites physiques mono-serveur

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

*Tableau 36 — Synthèse chiffrée des campagnes de validation automatisées.*

La campagne de performance dédiée (`bash scripts/run_perf_vm.sh`, commit `cb0f814`, `tests/perf/dernieres_mesures.txt`) a été conduite sur la base isolée `minishop_perf` peuplée à **`236` produits**, **`1 004` commandes**, **`1 916` lignes de commande** et **`1 998` traces de statut** (au-delà de la volumétrie cible `ENF-02` de `200` produits / `1 000` commandes). Les Tableaux 32 et 33 en restituent l’intégralité :

| Requête SGBD évaluée (`100` itérations) | Moyenne (ms) | p95 (ms) | Plan `EXPLAIN` complet & Verdict |
|---|---:|---:|---|
| **Catalogue (`v_catalogue`, 12 lignes)** | `19,8` | `20,6` | `c`: `index` (`uk_categorie_nom`, `rows=4`), `p`: `range` (`idx_produit_visible`, `rows=236`, BNL join) — **OK (`< 500 ms`, pas de balayage complet)** |
| **Recherche plein texte `LIKE`** | `19,7` | `20,6` | `produit`: `range` (`idx_produit_visible`, `rows=236`, `Using index condition; Using filesort`) — **OK (`< 500 ms`)** |
| **10 dernières commandes client** | `19,3` | `20,2` | `commande`: `ref` (`idx_commande_client`, `rows=79`, `Using where; Using filesort`) — **OK (`< 500 ms`)** |
| **Indicateurs du jour (`GROUP BY` statut)** | `19,7` | `20,5` | `commande`: **`type=ALL`** (`aucun index`, `rows=1004`, `Using temporary; Using filesort`) — **OK (`< 500 ms`), à indexer au-delà de `50 000` lignes** |
| **Vue `v_etat_stock` (1 page)** | `19,8` | `20,6` | **OK (`< 500 ms`)** |
| **Écriture transactionnelle (`sp_create_order` + `sp_add_order_line` + `sp_confirm_order`)** | **`44,0`** | — | Verrou `SELECT ... FOR UPDATE` + triggers snapshot/stock/audit + calcul port (`CMD2026-001005 · 215,88 EUR · port 0,00`, nettoyée après mesure) |

*Tableau 37 — Mesures SQL détaillées (`minishop_perf`, `100` itérations) et plans `EXPLAIN` (`tests/perf/dernieres_mesures.txt`).*

| Route HTTP évaluée (`N = 200` requêtes/URL) | Moyenne (ms) | Médiane (ms) | p95 (ms) | Code HTTP |
|---|---:|---:|---:|---:|
| `/` (accueil) | `3,4` | `3,4` | `3,7` | `200` |
| `/catalogue` | `6,1` | `6,0` | `6,5` | `200` |
| `/catalogue?cat=1&prix_max=500&stock=1&tri=prix_asc` | `2,9` | `2,7` | `3,6` | `200` |
| `/recherche?q=clavier` | `5,8` | `5,7` | `6,2` | `200` |
| `/produit/casque-sans-fil-aura` | `1,2` | `1,2` | `1,4` | `200` |
| `/panier` | `1,3` | `1,2` | `1,5` | `200` |
| **Verdict global `ENF-01`** | — | — | **`6,0 ms` global** | **0 réponse inattendue — CONFORME (`< 500 ms`)** |

*Tableau 38 — Mesures HTTP détaillées par route sur `http://127.0.0.1:18080` (`N = 200` req./URL).*

#### 4.4.2 Mise en perspective des mesures VM face aux piles concurrentes

À la lumière des mesures observées sur notre VM Debian 13 (PHP `8.4.27` + MariaDB `11.8.6`), il convient de situer précisément le comportement de MiniShop par rapport aux autres architectures évoquées au Chapitre 1 (`Tableaux 6 à 8`) :

| Scénario de charge évalué | **Mesure réelle MiniShop (PHP 8.4 + MariaDB 11.8.6)** | **Comportement attendu d’un framework + ORM lourd** | **Comportement attendu avec SQLite ou NoSQL** |
|---|---|---|---|
| **Navigation catalogue & fiche produit (`200` req./URL)** | **p95 = 6,0 ms** (`1,2` à `6,1 ms` en moyenne) grâce à l’absence d’ORM, au routeur léger et aux vues SQL pré-jointes | Surcoût d’hydratation d’entités et d’injection de dépendances (généralement `+15` à `+40 ms` à froid/moyenne selon le cache) | SQLite rapide en lecture pure, mais dégradation sévère dès qu’apparaissent des écritures concurrentes |
| **Transaction complète de commande (`sp_create_order` + lignes + `sp_confirm_order`)** | **44,0 ms** en moyenne incluant verrou `FOR UPDATE`, snapshot tarifaire, calcul de port et insertion d’audit | Plusieurs allers-retours applicatifs + requêtes ORM supplémentaires si l’on ne déporte pas la logique en procédure stockée | Sur SQLite : contention globale de fichier ; sur NoSQL : nécessité de transactions distribuées ou risque de désynchronisation du stock |
| **Contention critique (`20` acheteurs simultanés pour `12` unités)** | **8/8 scénarios réussis**, **`0` survente** : le verrou InnoDB ligne par ligne sérialise exactement les `12` premiers achats | Risque classique de *race condition* si l’ORM lit puis met à jour le stock en PHP sans verrou `FOR UPDATE` explicite | Échecs `SQLITE_BUSY` fréquents ou risque de stock négatif sans opérateur atomique strict |

*Tableau 39 — Mise en perspective des résultats expérimentaux de la VM Debian 13 face aux piles alternatives.*

#### 4.4.3 Évaluation de la charge, limite mono-serveur, répartition L4/L7, passerelle d’API et *Ingress*

Conformément à `ENF-03`, MiniShop est aujourd’hui dimensionné comme une application **mono-serveur** (un hôte exécutant conjointement Apache/PHP et MariaDB, typiquement un VPS `2`–`4` vCPU / `2`–`4` Go RAM ou un hébergement mutualisé type *LineServ*). Il est donc essentiel de quantifier la **limite physique immédiate** d’utilisateurs concurrents avant saturation, puis d’identifier les leviers d’optimisation et de *scaling* validés par la documentation officielle des composants :

1. **Modélisation de la limite physique immédiate en mono-serveur :**
   - *Plafond de workers PHP (`pm.max_children` / `MaxRequestWorkers`) :* chaque processus PHP 8.2+ sans framework consomme typiquement **`25` à `40 Mo`** de RAM résidente (selon la documentation PHP-FPM, `pm.max_children = RAM_allouée_PHP / mémoire_moyenne_processus`). Sur un mono-serveur de `4 Go` dont `1,5 Go` reste dédié à PHP (le reste étant partagé entre l’OS, Apache et `innodb_buffer_pool_size` MariaDB), le plafond physique se situe autour de **`40` à `60` requêtes PHP simultanées actives** (et plus restrictif encore sur mutualisé académique).
   - *Débit maximal vs utilisateurs actifs (loi de Little : `L = λ × W`) :* avec nos latences mesurées (`6 ms` p95 sur lecture catalogue, `44 ms` sur commande transactionnelle), `50` workers PHP saturés représentent théoriquement plusieurs milliers de requêtes/s en lecture pure — **mais trois goulots d’étranglement physiques réduisent fortement cette capacité réelle sous charge mixte** :
     1. **Verrouillage exclusif des sessions fichiers PHP (`session.save_handler = files`) :** selon le manuel officiel PHP (*Session Handling*), `session_start()` pose un verrou exclusif (`flock`) sur le fichier de session jusqu’à la fin du script, **sérialisant** toutes les requêtes AJAX ou onglets parallèles d’un même utilisateur.
     2. **Connexions et contention InnoDB (`max_connections = 151` par défaut) :** sur MariaDB, les connexions PDO non persistées restent limitées par `max_connections` (et `innodb_buffer_pool_size` limité sur petit serveur) ; surtout, lors d’une vente flash sur un même article populaire, le verrou ligne `SELECT ... FOR UPDATE` sur `produit(id_produit)` **sérialise les transactions concurrentes d’achat sur ce SKU** (`1 / 0,044 s ≈ 22` validations/s sur ce seul article), ce qui garantit `0` survente (`8/8`) mais crée une file d’attente au-delà de quelques dizaines d’acheteurs simultanés sur la même référence.
     3. **Absence de cache HTTP/applicatif et balayage `commande(statut)` :** le `type=ALL` relevé par `EXPLAIN` (Tableau 37) commence à peser sur le CPU SQL au-delà de `50 000` commandes (`ENF-02`).

   En ordre de grandeur réaliste pour un mono-serveur modeste non caché (`2`–`4` vCPU, `4` Go RAM), la plateforme absorbe confortablement la cible `ENF-02` (`10` à `50` utilisateurs actifs simultanés, soit **~`40`–`60` requêtes HTTP dynamiques strictement parallèles** et **`~20`–`25` validations/s** sur un même produit chaud) avant d’observer une montée en file d’attente des workers PHP ou des verrous MariaDB.

2. **Stratégie complète d’optimisation et de passage à l’échelle (*Scaling* vertical puis horizontal) :**

| Niveau d’optimisation | Mécanisme technique & Paramétrage | Source technique officielle | Effet mesurable sur la capacité |
|---|---|---|---|
| **1. Runtime Web & PHP** | Passer d’Apache `mpm_prefork` à **`mpm_event` + `PHP-FPM`** ; activer **`OPcache`** + **`opcache.preload`** (`app/bootstrap.php`, dépôts, routeur) | *Apache HTTP Server MPM Event Docs* ; *PHP Manual — OPcache & PHP-FPM* | Supprime la copie de l’interpréteur PHP sur les fichiers statiques (`CSS`/`JS`/`JPG`), réduit la RAM/connexion et élimine la recompilation par requête. |
| **2. Sessions & Cache applicatif** | Remplacer `files` par **Redis (`session.save_handler = redis`)** + cache des pages catalogue et vues `v_catalogue` / `v_etat_stock` (TTL court + invalidation sur écriture admin) | *Redis Official Docs — Session Store & Caching* ; *phpredis* | Supprime le verrouillage disque `flock`, autorise plusieurs frontaux PHP sans état (*stateless*) et absorbe `> 90 %` du trafic de lecture hors MariaDB. |
| **3. SGBD MariaDB & Index** | Allouer **`innodb_buffer_pool_size`** (`50–70 %` RAM sur nœud SQL dédié) ; ajouter `INDEX idx_commande_statut (statut)` ; déployer **MariaDB Primary + Read Replicas** | *MariaDB Knowledge Base — InnoDB Buffer Pool & Replication* | Garde les index et tables chaudes en RAM, élimine le `type=ALL` du back-office et déporte les lectures (`sp_search_products`, `sp_revenue_report`) sur les réplicas. |
| **4. Répartition de charge & CDN** | Placer **NGINX / HAProxy** (*load balancer* L7) devant un pool horizontal de nœuds PHP-FPM + **CDN / cache périphérique** pour `/img/`, `/css/`, `/fonts/` | *NGINX HTTP Load Balancing Docs* ; *Cloudflare / MDN HTTP Caching* | Découpe la bande passante statique du serveur applicatif et permet d’ajouter des frontaux PHP à la demande derrière une VIP. |
| **5. Découplage asynchrone** | Déporter factures PDF, e-mails, notifications et réassort vers des **files de messages** (Redis Streams / RabbitMQ + workers CLI) | *RabbitMQ / Redis Streams Architecture Docs* | Réduit le temps d’exécution des requêtes PHP critiques (`sp_confirm_order`), libérant immédiatement les workers FPM. |

*Tableau 40 — Limites physiques mono-serveur (`ENF-03`) et plan d’optimisation/scaling appuyé sur les documentations officielles.*

3. **Distinguer répartiteur L4/L7, passerelle d’API et *Ingress* lors du passage à l’échelle de MiniShop :**
   Lorsque la boutique dépasse le plafond mono-serveur (`40`–`60` requêtes PHP parallèles) et ajoute une seconde copie `front-web` (avec sessions Redis, § `4.1.4`), il faut choisir et distinguer trois briques souvent confondues car placées au même endroit sur le schéma d’entrée :
   - **Répartiteur de charge (`Load Balancer`) L4 vs L7 :**
     - Le **répartiteur L4** (couche transport TCP/UDP) lit uniquement adresse IP et port, sans déchiffrer TLS ni inspecter l’URL, avec un coût CPU minimal par paquet. Il convient pour répartir des connexions TCP vers des **réplicas de lecture MariaDB (`3306`)** ou pour encaisser de très forts débits bruts, mais ne peut ni router `/admin/*` séparément de `/`, ni inspecter les en-têtes HTTP.
     - Le **répartiteur L7** (NGINX, HAProxy, ALB) termine TLS, lit la requête HTTP complète et applique l’algorithme (*round-robin*, *least connections*, ou empreinte de session avant externalisation Redis) pour distribuer entre copies identiques de `front-web`, tout en dirigeant `/admin/*` vers `admin-web` et en injectant `X-Forwarded-Proto: https` (indispensable pour que `Auth::demarrer()` dans `app/Security/Auth.php:58` maintienne le drapeau `Secure` de `MINISHOPSESS`).
   - **Contrôles de santé (*health checks*) et piège détecté dans `public/index.php` :** un répartiteur L7 est aveugle sans sonde fiable. Un simple test TCP (`port 80/443`) continue d’envoyer des requêtes vers un `PHP-FPM` bloqué sur `flock()`, tandis qu’une sonde sur `/` (`HomeController::index()`) interroge MariaDB : **si la base ralentit, toutes les copies PHP saines échouent ensemble et sont retirées simultanément** (coupure totale). La remédiation d’ingénierie consiste à ajouter à `public/index.php` une route dédiée **`GET /healthz`** (*liveness* léger répondant `200 OK` sans accès SGBD) distincte de `/readyz`, et à calibrer le **délai d’éviction** (`intervalle × échecs`, ex. `2 s × 2 = 4 s` max) afin de borner le nombre de requêtes perdues lors d’une panne d’un nœud.
   - **Passerelle d’API (*API Gateway*) vs notre `ApiController` actuel :** dans `public/index.php` et `app/Controller/ApiController.php`, les routes `/api/panier`, `/api/panier/ajouter`, `/api/panier/quantite` et `/api/panier/retirer` forment une **API interne de session** protégée par cookie `MINISHOPSESS` + jeton `Csrf::verifier()` (`ENF-07`) destinée au seul navigateur, et non à des programmes tiers. En V1, **aucune passerelle d’API (Kong, Tyk, APISIX) n’est nécessaire** — elle ajouterait sans bénéfice un état critique à sauvegarder (consommateurs, clés, quotas) et un rayon d’impact maximal en cas d’erreur de règle. Elle devient pertinente uniquement en **V2/V3** lors de l’ouverture d’API partenaires/mobiles `/v1/orders`, `/v1/catalog` ou vendeurs *Marketplace* (§ `6.2`) pour centraliser clés API/OAuth 2.0, quotas par client, versionnement `/v1`→`/v2`, transformation et facturation d’usage.
   - **Kubernetes *Ingress* vs Contrôleur d’Ingress (et *Gateway API*) :** si les `4` conteneurs du § `5.1.2` (`front-web`, `admin-web`, `mariadb`, `cli-maintenance`) migrent sur Kubernetes, l’objet **`Ingress`** (ou `HTTPRoute` via *Gateway API*) n’est qu’un **manifeste déclaratif YAML** (`minishop.fr/` → Service `front-web`, `/admin` → Service `admin-web`). Sans **contrôleur d’Ingress** déployé en `>= 2` réplicas dans le cluster (NGINX, Traefik, HAProxy, Envoy) et exposé lui-même par un répartiteur L4/L7 cloud, l’objet `Ingress` reste **lettre morte**.

| Brique / Niveau | Ce qu’elle décide sur MiniShop | Rôle précis sur nos routes / flux réels | Besoin en V1 (`ENF-03`) vs V2/K8s |
|---|---|---|---|
| **Répartiteur L4** (TCP/UDP) | IP + port uniquement (`443` ou `3306`) | Équilibrage TCP vers réplicas MariaDB `3306` ou façade L4 devant contrôleurs L7 | Inutile en V1 ; utile en V2 devant réplicas SQL ou cluster K8s |
| **Répartiteur L7** (HTTP/HTTPS) | Chemin, domaine, en-tête, état `/healthz` | Distribue `/` et `/api/panier*` sur `front-web`, isole `/admin/*` sur `admin-web`, pose `X-Forwarded-Proto` | **Indispensable dès ≥ `2` frontaux PHP-FPM** (en paire VRRP/Keepalived anti-SPOF) |
| **Passerelle d’API** (Kong / APISIX) | Identité client (`OAuth2`/clé), quotas, `/v1` | Non adaptée aux appels navigateur CSRF de `ApiController` ; destinée aux API M2M V2 (`/v1/*`) | **Évitée en V1** ; requise uniquement pour API publiques/partenaires V2–V3 |
| ***Ingress* + Contrôleur** (K8s) | Règles déclaratives YAML exécutées par le contrôleur L7 | Traduit les manifestes `Ingress`/`HTTPRoute` vers les Services `front-web` et `admin-web` | Hors périmètre V1 (`compose`) ; requis si déploiement Kubernetes |

*Tableau 41 — Distinction répartiteur L4/L7, passerelle d’API et Ingress Kubernetes appliqués aux routes réelles de MiniShop.*

4. **Grille d’état, sauvegarde, signaux d’alerte et compromis de ces briques :**
   Sur le chemin critique de chaque requête, ces trois familles rendent possible la mise à jour sans coupure (*rolling update*) des frontaux PHP-FPM, mais restent inutiles face à un **SPOF de base MariaDB unique** et deviennent elles-mêmes le **SPOF d’entrée** si elles ne sont pas redondées :

| Question de la grille | Répartiteur de charge (`L4` / `L7`) | Passerelle d’API (V2 `/v1/*`) | *Ingress* & Contrôleur (K8s) |
|---|---|---|---|
| **Qui l’appelle & État porté** | Clients HTTP / services ; porte **configuration + certificats TLS** | Programmes consommateurs ; porte **base consommateurs, clés API, quotas** | Clients HTTP hors cluster ; porte **objets `Ingress`/`Gateway` + secrets TLS** |
| **À sauvegarder** | Configuration HAProxy/NGINX dans Git + certificats/ACME | **Base de configuration & clés consommateurs** + manifestes Git | **Manifestes YAML** dans Git + certificats (`cert-manager`) |
| **Impact s’il tombe** | Boutique injoignable (si non doublé par VIP/LB managé) | Toutes les API tierces coupées (blast radius maximal) | Plus aucun trafic HTTP n’entre dans le cluster |
| **Signaux à observer** | Nœuds retirés de la rotation, délai d’éviction, `502`/`504` | Pics `401`/`403`, saturation de quotas (`429`), latence gateway | Erreurs `5xx`, certificats proches d’expiration, état du contrôleur |
| **Compromis clé** | Supprime le SPOF PHP, mais ne réplique pas MariaDB | Centralise auth & quotas en 1 point, mais devient goulot commun | Déclaratif et portable K8s, mais piège fréquent si contrôleur absent |

*Tableau 42 — Grille état, sauvegarde, impact de panne, signaux et compromis des briques de répartition et passerelle.*

\newpage

#### 4.4.4 Disponibilité, durabilité, RPO/RTO, latence/débit et montée en charge de MiniShop

Les mesures précédentes (`p95 = 6,0 ms`, écriture `44 ms`, capacité `40`–`60` workers PHP) permettent d’évaluer rigoureusement la **disponibilité**, la **durabilité** et les objectifs de reprise (**RPO / RTO**) de l’architecture MiniShop en V1 et dans sa cible de production :

1. **Calcul de disponibilité en série (V1) vs en parallèle (V2) et domaine de panne :**
   - **En série (`V1` mono-serveur, `ENF-03`) :** la requête HTTP traverse successivement **Apache/PHP**, le **stockage local de sessions/médias** et **MariaDB**. Avec une disponibilité unitaire hypothétique de `99,9 %` par maillon (`8 h 46` d’arrêt/an chacun), les disponibilités en série se **multiplient** : `0,999 × 0,999 × 0,999 ≈ 99,70 %` (soit `~26 heures` d’arrêt potentiel par an). De plus, ces trois briques partagent **le même domaine de panne** (un unique VPS ou hébergement *LineServ*) : toute panne matérielle, noyau ou disque arrête l’ensemble.
   - **En parallèle (`V2` multi-frontaux, § `4.4.3`) :** deux frontaux PHP-FPM *stateless* indépendants à `99 %` chacun ne tombent simultanément que `0,01 × 0,01 = 0,0001` du temps, soit `1 − 0,0001 = 99,99 %` (`~53 min/an`). Toutefois, (i) ils doivent être répartis dans **deux zones/hôtes distincts** (domaines de panne indépendants) et (ii) le répartiteur **NGINX/HAProxy** placé en série devant eux plafonne la disponibilité globale (d’où une paire redondée VRRP/Keepalived pour tenir les quatre neuf, seuil où `53 min/an` interdit toute reprise exclusivement manuelle et impose une bascule automatique).

2. **Disponibilité ≠ Durabilité et objectifs RPO / RTO appliqués au dépôt :**
   - **Disponibilité vs Durabilité sur notre code :** lorsque MariaDB est arrêtée pour maintenance, sa disponibilité tombe à `0 %` mais la **durabilité** InnoDB est intacte. À l’inverse, sous le pilote secondaire `JsonStore` (`data/minishop/*.json`) ou sur un disque unique non sauvegardé, le service peut répondre à `100 %` jusqu’à la panne disque où des écritures multi-fichiers sont perdues (§ `4.1.2`). Enfin, même un stockage objet annoncé à onze neuf (`99,999999999 %`) réplique avec la même fidélité un `DELETE` ou `DROP` accidentel : seule une **sauvegarde hors ligne** protège contre l’erreur logique ou humaine.
   - **RPO et RTO réels vs cibles :** en V1 (`ENF-15`), `README.md` et le § `5.1.1` documentent un `mysqldump --single-transaction --routines --triggers` visant un retour arrière `<= 15 min`, mais **aucune restauration automatique ni test de reprise *LineServ* n’a encore été exécuté** (*un RTO jamais restauré sur base vierge reste un RTO non prouvé*).

| Indicateur / Scénario MiniShop | État V1 actuel (académique, `ENF-03`/`ENF-15`) | Architecture cible Production (`99,9 %` à `99,99 %`) |
|---|---|---|
| **Chaîne de disponibilité** | **En série** (`Apache/PHP × Disque × MariaDB`) dans **1 seul domaine de panne** (`~99,7 %` théorique) | Frontaux PHP **en parallèle** sur ≥ `2` domaines de panne + répartiteur HA + MariaDB Primary/Standby (`99,9 %` = `8 h 46/an` ; `99,99 %` = `53 min/an`, bascule auto obligatoire) |
| **Durabilité des données** | InnoDB ACID sur disque unique (`SqlStore`) ; faible sur `JsonStore` multi-fichiers (`§ 4.1.2`) | Binlogs archivés + stockage objet S3 pour médias/backups + archives RGPD chiffrées `libsodium` (§ `5.2.3`) |
| **RPO (perte max tolérée)** | **Non borné / Manuel** (aucun cron de dump actif dans le dépôt) | **`<= 1 h` à `<= 5 min`** (dump quotidien + archivage continu des journaux binaires MariaDB) |
| **RTO (délai de reprise)** | **`~15 min` théorique** (`mysqldump`), **non prouvé** sur *LineServ* (`ENF-15`) | **`<= 15 min`** (restauration automatisée testée mensuellement) ; **`< 60 s`** avec bascule automatique *Standby* |

*Tableau 43 — Disponibilité en série/parallèle, durabilité et objectifs RPO/RTO mesurés et cibles de MiniShop.*

3. **Latence en percentiles vs débit, montée verticale/horizontale et coût d’un neuf supplémentaire :**
   - **Pourquoi nous mesurons le `p95` (`ENF-01`) plutôt que la moyenne seule :** sur `minishop_perf`, nos routes HTTP affichent un `p95` global de **`6,0 ms`** (`1,4` à `6,5 ms` par route, Tableau 38) et nos requêtes SQL `20,2` à `20,6 ms` en lecture contre **`44,0 ms`** pour une commande transactionnelle complète (`Tableau 37`). En chaîne synchrone en série, ces latences s’additionnent à chaque appel ; mesurer `p95`/`p99` révèle immédiatement les files d’attente (ex. sérialisation `FOR UPDATE` sur un article chaud à `~22,7` validations/s) qu’une moyenne masquerait.
   - **Montée verticale vs horizontale et coût du passage `99,9 % → 99,99 %` :**

| Dimension architecturale | Application PHP (`public/index.php`, `Router`) | Base MariaDB (`SqlStore`, `sql/01..03`) | Coût d’un neuf supplémentaire (`99,9 %` → `99,99 %`) |
|---|---|---|---|
| **Stratégie de montée en charge** | **Horizontale** dès que `MINISHOPSESS` passe sur **Redis** (§ `4.1.4`) : ajout de nœuds *stateless* derrière NGINX | **Verticale en priorité** (CPU, NVMe, `innodb_buffer_pool_size`, index `idx_commande_statut`), puis réplicas en lecture | Passer de `8 h 46` à `53 min` d’arrêt/an exige ≥ `2` zones de panne, une bascule automatique testée et une astreinte + observabilité temps réel |
| **Plafond & SPOF levé** | Supprime le SPOF applicatif ; limité uniquement par le répartiteur et l’état partagé | Conserve une cohérence forte (`ACID`) sur le nœud *Primary* sans schisme de stock | Réduit les pertes de ventes en pic, au prix d’une complexité accrue (nouveaux modes de panne réseau/bascule) |

*Tableau 44 — Synthèse latence/débit, montée en charge verticale/horizontale et compromis d’un « neuf » de disponibilité supplémentaire.*

### 4.5 Analyse critique des biais, limites du prototype et risques résiduels

Dans une démarche scientifique rigoureuse, nous consignons explicitement les limites des résultats obtenus :
1. **Recette manuelle navigateur en attente :** les `200` scénarios humains consignés dans `docs/13-suivi-recette-uc.csv` (`164` scénarios UC + `36` transitions `RB-11`) demeurent au statut `NON JOUÉ`, et aucun test sur l’hébergement mutualisé *LineServ* n’est revendiqué sans campagne dédiée.
2. **Biais de mesure locale :** les benchmarks `ENF-01` (`6,0 ms p95`) sont mesurés sur boucle locale (`127.0.0.1`) d’une VM dédiée, excluant la latence réseau WAN et la négociation TLS ; de même, `P-03` HTTP n’a pas fait l’objet d’une boucle séparée.
3. **Limites de couverture et contrats statiques (`docs/11` § 8) :** `scripts/coverage.php` trace uniquement le chargement de fichiers (sans Xdebug/PCOV) et `css_contract.php`/`responsive_contract.php` restent statiques hors lecteur d’écran physique.
4. **Écarts assumés en maquette (`docs/01` §8.10) :** paiement et expédition simulés, formulaire de contact journalisé localement sans SMTP, absence d’authentification multifacteur TOTP sur `SUPER` (recommandée par OWASP ASVS V4.3.1), absence de *rate limiting* applicatif sur `/connexion`, et indexation secondaire à prévoir sur `commande(statut)` au-delà de `50 000` commandes (`EXPLAIN type=ALL`).

\newpage

## Chapitre 5 — Exploitation (*Build → Run*), conformité légale, RGPD, gouvernance GitLab et barème

### 5.1 Prérequis d’hébergement, déploiement, MCO, moindre privilège et conteneurisation

#### 5.1.1 Prérequis d’hébergement, déploiement initial, retour arrière et MCO

L’exploitation cible (`STACK.md`, `docs/26-transition-exploitation-maintenance-build-to-run.md`) requiert PHP **8.2+** (`json`, `mbstring`, `pdo_mysql`), MySQL **8.0.16+** ou MariaDB **10.6+**, et Apache 2.4 (`DocumentRoot public/`, `mod_rewrite` activé ; le serveur intégré PHP de `run.sh` étant réservé au développement). Le passage en production impose :
1. La création d’un fichier `.env` hors racine Web (`APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql`) avec un utilisateur SQL applicatif aux droits restreints (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `EXECUTE`).
2. La suppression physique des scripts utilitaires `public/gen_volumes.php` et `public/mesurer.php` du répertoire public déployé.
3. Le remplacement des comptes de démonstration du seed par un compte `SUPER` privé fort.
4. La réalisation préalable d’une sauvegarde logique complète (`mysqldump --single-transaction --routines --triggers`) pour garantir un retour arrière (*rollback*) en moins de `15` minutes incluant procédures et déclencheurs.

#### 5.1.2 Règle du moindre privilège : permissions par utilisateur, cloisonnement des données et conteneurisation

Pour limiter au maximum la surface d’exposition et l’impact d’une éventuelle compromission (fuite de données personnelles ou élévation de privilèges), MiniShop applique la **règle du moindre privilège** dès la version académique et identifie les durcissements requis pour une exploitation en production :

- **Ce qui est déjà en place dans MiniShop :**
  - **Routage par liste blanche et gardes déclaratives (`app/Router.php`, `public/index.php`) :** aucune route dynamique ; chaque route impose explicitement sa méthode HTTP et son niveau d’accès (`public`, `client`, `admin`, `super`).
  - **Étanchéité Client / Administrateur (`RB-13`, `app/Security/Auth.php`) :** deux clés de session distinctes (`$_SESSION['client_id']` et `$_SESSION['admin_id']`), cookie `MINISHOPSESS` en `HttpOnly` + `SameSite=Lax` + `use_strict_mode=1`, régénération d’identifiant à toute connexion/déconnexion. Seul le rôle `SUPER` accède à `/admin/equipe` (`EquipeAdminController`), un `GESTIONNAIRE` recevant `403`.
  - **Protection anti-IDOR (`SEC-08`, `CommandeController`) :** chaque lecture, confirmation ou annulation vérifie côté serveur et SGBD l’appartenance à `id_client` authentifié et retourne une `404` générique en cas d’accès à une commande tierce.
  - **Cloisonnement Web et archivage CLI :** `DocumentRoot` limité à `public/` avec blocage `.htaccess` sur `.env`, `.sql`, `.log`, `.md` ; compte SQL dédié (`DbProvision`) distinct de `root` et restreint à la base du projet ; exports d’archivage RGPD isolés en CLI sous droits POSIX `0700`/`0600` et chiffrés via `libsodium`.

- **Axes d’amélioration des permissions applicatives et SQL :**
  1. **Séparer le compte SQL de migration du compte SQL applicatif (`app/Tools/DbProvision.php`) :** aujourd’hui, le script de développement accorde `ALL PRIVILEGES ON minishop.*`. En production, le compte utilisé par PHP-FPM doit être restreint à `SELECT, INSERT, UPDATE, EXECUTE` (et `DELETE` ciblé), sans aucun privilège `DDL` (`ALTER`, `DROP`, `TRIGGER`) ni `FILE`/`SUPER`, un second compte privilégié étant réservé aux scripts de migration.
  2. **Projection stricte des colonnes en mémoire PHP (DTO / vues SQL) :** les appels génériques `$store->find()` / `$store->select()` dans `ClientRepository::find()` ou `EquipeAdminController` chargent encore `mot_de_passe_hash` en mémoire PHP. Exclure ce champ de toutes les requêtes hors authentification supprime tout risque de fuite accidentelle dans une vue, un log ou un futur endpoint JSON.
  3. **RBAC back-office granulaire par métier :** scinder le rôle unique `GESTIONNAIRE` en profils spécialisés (`PREPARATEUR` limité aux bons de préparation sans vue sur les paramètres boutique ni le CA ; `CATALOGUE` limité aux produits/stocks sans accès aux données personnelles `email` / `adresse_livraison` des clients ; `SUPER` protégé par MFA TOTP et journalisation des consultations de fiches clients).
  4. **Révocation instantanée de session (`token_version`) et séparation de domaine :** ajouter un compteur `token_version` en base incrémenté lors d’un changement de mot de passe, de rôle ou d’un blocage de compte, limiter l’inactivité admin (`15`–`30 min`) et servir le back-office sur un sous-domaine isolé (`admin.domaine.fr` avec préfixe de cookie `__Host-`).

- **Faut-il conteneuriser le code et isoler les informations par personne ?**
  Il ne faut **pas** créer un conteneur par utilisateur (ce qui est ingérable et inutile), mais combiner une **isolation d’exécution en 4 conteneurs** avec un **cloisonnement logique des données** :

| Axe d’isolation | Périmètre isolé | Mécanisme concret (moindre privilège) | Fuite de données évitée |
|---|---|---|---|
| **Conteneur `front-web`** | PHP-FPM + serveur HTTP front-office (`public/`) | Routes `/admin/*` absentes ou désactivées ; compte SQL sans droit sur `administrateur` ni `parametre` | Une faille sur la boutique publique ne donne accès ni au code back-office ni aux tables d’administration |
| **Conteneur `admin-web`** | Back-office (`/admin/*`) sur sous-domaine dédié | Accessible uniquement derrière VPN / IP autorisée / SSO + MFA TOTP ; timeout session court | Exposition Internet nulle de l’interface d’administration |
| **Conteneur `db-mariadb`** | SGBD MariaDB | Réseau Docker interne privé (port `3306` fermé vers l’extérieur), volume chiffré au repos | Aucun accès direct au moteur SQL depuis Internet ou des services tiers |
| **Conteneur `cli-maintenance`** | Tâches planifiées, archivage RGPD (`libsodium`), PDF | **Seul** conteneur détenant les clés de chiffrement d’archive et l’accès au stockage `backup` (absents des conteneurs Web) | Une compromission HTTP ne peut ni lire les sauvegardes ni déchiffrer les archives clients |
| **Cloisonnement logique par personne** | Données personnelles (PII) et multi-vendeurs (Chap. 6) | Secrets injectés en mémoire (`0400`, hors image Docker) ; colonnes PII isolées par rôle ; filtrage strict `id_vendeur` / *Row-Level Security* | Un préparateur, un catalogueur ou un vendeur A ne peut jamais lire les clients ou marges d’un tiers |

*Tableau 45 — Règle du moindre privilège : cloisonnement par conteneur d’exécution et isolation logique des données par profil.*

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

*Tableau 46 — Cadre légal e-commerce français et traduction dans les pages réglementaires de MiniShop (`docs/12-information-juridique.md`).*

#### 5.2.2 Audit technique des cookies, du stockage local et minimisation des journaux

L’inspection statique des scripts PHP/JavaScript et des vues (`docs/12` § 2) établit un inventaire exhaustif des traceurs et stockages locaux actifs dans l’application :

| Mécanisme / Fichier observé | Données persistées & Durée | Attributs de sécurité & Statut CNIL | Action requise avant mise en production |
|---|---|---|---|
| **Cookie `MINISHOPSESS`** (`app/Security/Auth.php`) | Identifiant de session PHP (panier, auth client/admin, jeton CSRF) | `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` si HTTPS détecté. **Susceptible d’exemption** CNIL (strictement nécessaire au service demandé). | Vérifier `Secure=true` derrière un proxy TLS de confiance (`X-Forwarded-Proto`). |
| **Cookie `MINISHOPSESSID`** (`public/mesurer.php`) | Session de l’outil de benchmark interne | `HttpOnly`, `SameSite=Lax`, **`Secure=false`**. Outil de développement uniquement. | **Supprimer impérativement** `mesurer.php` et `gen_volumes.php` avant déploiement public. |
| **`localStorage` (`minishop.preferences.v1`)** (`public/js/preferences.js`) | Langue (`fr`/`en`) et fuseau horaire choisis par l’utilisateur | Stockage local sur demande explicite ; `tz-lookup.js` déduit le fuseau localement après clic sans transmettre ni sauvegarder les coordonnées GPS. Aucun pixel tiers ni SDK analytics détecté. | Refaire un inventaire réseau complet sur l’instance hébergée réelle ; ajouter un bandeau conforme si un traceur non exempté est intégré. |
| **Journaux JSON & Contact** (`var/journal/db.jsonl`, `var/log/contact.jsonl`) | Audit JSON et messages `/contact` (`<= 2 000` car.) | `NettoyageJournaux::ajouter` écrit sous verrou avec `chmod 0600` ; les nouvelles entrées `client`/`administrateur` dans `db.jsonl` **omettent nom, e-mail et `mot_de_passe_hash`** (minimisation). | Traiter les anciennes entrées historiques déjà écrites ; mettre en place un serveur SMTP réel pour `/contact` (aujourd’hui journalisé localement sans envoi d’e-mail). |

*Tableau 47 — Audit technique des traceurs, cookies et minimisation des données (`docs/12-information-juridique.md`).*

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

*Tableau 48 — Politique de conservation RGPD, maintenance CLI et archivage chiffré libsodium (`docs/18-politique-conservation.md`).*

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

*Tableau 49 — Synthèse des 7 points bloquants à lever avant toute exploitation commerciale réelle.*

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

*Tableau 50 — Contrôle complet de conformité au barème officiel et d’absence de pénalité.*

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

*Tableau 51 — Briques d’industrialisation requises pour transformer MiniShop en système de production.*

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

Le Tableau 52 synthétise l’impact architectural de cette extension sur nos règles existantes :

| Composant MiniShop | Évolution vers le modèle *Marketplace* multi-vendeurs | Préservation des garanties existantes |
|---|---|---|
| **Catalogue & *Buy Box*** | Un algorithme pondéré (`prix_ttc`, `delai_exp_heures`, taux de retour, note vendeur) élit l’offre par défaut (`est_buy_box = 1`) sur la fiche produit, tout en listant les autres vendeurs. | La table `produit` reste la référence canonique unique du catalogue. |
| **Panier & Ventilation (`Split Order`)** | Si un client achète simultanément deux articles de deux vendeurs distincts, la commande globale (`COMMANDE`) est ventilée en **sous-commandes** (`SOUS_COMMANDE_VENDEUR`), chacune ayant son propre suivi logistique. | La contrainte d’atomicité (`sp_create_order_from_basket`) verrouille par `FOR UPDATE` chaque `offre_vendeur` impliquée ; aucun passage sous zéro n’est possible. |
| **Flux financier & Séquestre** | Intégration de paiements scindés (*Stripe Connect* / *Adyen for Platforms*) : encaissement sur compte de cantonnement, prélèvement automatique de la commission (`taux_commission`) et virement au vendeur à confirmation de livraison (`LIVREE`). | Aucune détention illicite de fonds pour compte de tiers (conformité DSP2). |
| **Cloisonnement RBAC & Anti-IDOR** | Ajout d’un troisième espace dédié `/vendeur/*` protégé par `Auth::exigeVendeur()` et filtrage systématique `WHERE id_vendeur = ?` sur produits, stocks et sous-commandes. | Généralisation directe de la garde IDOR actuelle (`SEC-08`, `ENF-12`). |

*Tableau 52 — Extension du modèle relationnel 3FN et de la sécurité vers une marketplace multi-vendeurs.*

### 6.3 Gestion des fournisseurs, fiches fournisseurs et commandes d’approvisionnement back-office

Dans la version académique actuelle, les entrées de stock proviennent exclusivement d’ajustements manuels saisis dans `/admin/stocks` (`sp_adjust_stock` avec motif `REASSORT`), sans référentiel fournisseur ni suivi des achats amont. Pour industrialiser l’approvisionnement en back-office à l’échelle d’une centrale d’achat ou d’un distributeur, il est indispensable de modéliser proprement les **fournisseurs**, leurs **catalogues multi-sources** et les **commandes fournisseurs (Bons de Commande Fournisseur — BCF)** en respectant strictement notre normalisation 3FN et notre piste d’audit `stock_movement_history`.

#### 6.3.1 Extension du modèle 3FN aux fournisseurs et réceptions

```sql
-- Extension 3FN de la base MiniShop pour l'approvisionnement amont :
CREATE TABLE fournisseur (
  id_fournisseur        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code_fournisseur      VARCHAR(30)  NOT NULL UNIQUE,
  raison_sociale        VARCHAR(150) NOT NULL,
  siret                 CHAR(14)     NULL,
  email_commandes       VARCHAR(180) NOT NULL,
  delai_moyen_jours     SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  conditions_paiement   VARCHAR(80)  NOT NULL DEFAULT 'NET_30',
  actif                 TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE catalogue_fournisseur (
  id_fournisseur        INT UNSIGNED NOT NULL,
  id_produit            INT UNSIGNED NOT NULL,
  sku_fournisseur       VARCHAR(60)  NOT NULL,
  prix_achat_ht         DECIMAL(10,2) NOT NULL CHECK (prix_achat_ht > 0),
  moq_quantite_min      INT UNSIGNED NOT NULL DEFAULT 1,
  priorite_rang         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_fournisseur, id_produit),
  FOREIGN KEY (id_fournisseur) REFERENCES fournisseur(id_fournisseur) ON DELETE RESTRICT,
  FOREIGN KEY (id_produit)     REFERENCES produit(id_produit)         ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE bon_commande_fournisseur (
  id_bcf                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  numero_bcf            VARCHAR(25)  NOT NULL UNIQUE,   -- ex. BCF2026-000142
  id_fournisseur        INT UNSIGNED NOT NULL,
  statut                ENUM('BROUILLON','ENVOYEE','CONFIRMEE','RECEPTIONNEE_PARTIELLE',
                             'RECEPTIONNEE_TOTALE','CLOTUREE','ANNULEE') NOT NULL DEFAULT 'BROUILLON',
  date_livraison_prevue DATE NULL,
  montant_total_ht      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cree_par              VARCHAR(120) NOT NULL,
  FOREIGN KEY (id_fournisseur) REFERENCES fournisseur(id_fournisseur) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE ligne_bcf (
  id_ligne_bcf          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_bcf                INT UNSIGNED NOT NULL,
  id_produit            INT UNSIGNED NOT NULL,
  quantite_commandee    INT UNSIGNED NOT NULL CHECK (quantite_commandee > 0),
  quantite_recue        INT UNSIGNED NOT NULL DEFAULT 0,
  prix_achat_unitaire   DECIMAL(10,2) NOT NULL CHECK (prix_achat_unitaire > 0), -- snapshot achat
  FOREIGN KEY (id_bcf)     REFERENCES bon_commande_fournisseur(id_bcf) ON DELETE CASCADE,
  FOREIGN KEY (id_produit) REFERENCES produit(id_produit)              ON DELETE RESTRICT
) ENGINE=InnoDB;
```

#### 6.3.2 Pilotage des fiches fournisseurs et cycle des commandes en back-office (`/admin/*`)

| Module back-office à ajouter | Fonctionnalités opérationnelles pour le gestionnaire | Intégration transactionnelle avec le cœur MiniShop |
|---|---|---|
| **Fiches fournisseurs (`/admin/fournisseurs`)** | Coordonnées commerciales/comptables, conditions de règlement (`NET_30`, `COMPTANT`), catalogue des produits rattachés avec `sku_fournisseur`, coût d’achat HT, **MOQ** (quantité minimale de commande) et rang de préférence (`priorite_rang = 1` principal, `2` secours). | Le calcul de marge réelle (`produit.prix_ht - catalogue_fournisseur.prix_achat_ht`) alimente directement le garde-fou anti-vente à perte et les tableaux de bord CA. |
| **Évaluation & Fiabilité (Scorecard)** | Suivi automatique du délai réel de réception par rapport à `date_livraison_prevue` (taux **OTIF** — *On-Time In-Full*), taux de colis non conformes et alertes en cas de dépendance à un fournisseur unique. | Alimente l’algorithme du point de commande dynamique `S_cmd` (Tableau 55) avec le délai réel `L_fournisseur` plutôt qu’une estimation fixe. |
| **Génération des commandes fournisseur (`/admin/approvisionnements`)** | Création manuelle ou **pré-remplissage automatique** des brouillons `BROUILLON` dès qu’un produit passe en `TRES_BAS` ou `RUPTURE` dans `v_etat_stock`, respectant automatiquement le MOQ et le fournisseur de rang 1 ; validation CSRF par `GESTIONNAIRE`/`SUPER`, génération PDF et envoi e-mail/EDI (`ENVOYEE`). | `ligne_bcf.prix_achat_unitaire` fige par *snapshot* le prix d’achat négocié au moment de l’émission du BCF (exactement comme `RB-06` côté vente client). |
| **Réception physique & Mise en stock atomique** | Interface de réception partielle ou totale (saisie ou scan code-barres de `quantite_recue`, gestion des écarts/avaries) faisant évoluer le BCF vers `RECEPTIONNEE_PARTIELLE` puis `RECEPTIONNEE_TOTALE` et `CLOTUREE`. | Une procédure `sp_receive_supplier_order` verrouille `ligne_bcf` (`FOR UPDATE`), contrôle `quantite_recue <= quantite_commandee`, puis appelle **`sp_adjust_stock(..., 'DELTA', +qte_recue, 'REASSORT_FOURNISSEUR', ...)`** : le stock est incrémenté atomiquement et **chaque réception est tracée dans `stock_movement_history`**. |

*Tableau 53 — Gestion back-office des fiches fournisseurs et du cycle complet des commandes d’approvisionnement.*

\newpage

### 6.4 Moteur de recommandation hybride (analyse du panier, filtrage collaboratif et sémantique)

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

*Tableau 54 — Architecture incrémentale du moteur de recommandation hybride.*

### 6.5 Automatisation du back-office vendeur, réapprovisionnement prédictif et logistique

Dans un back-office artisanal (et encore en partie dans MiniShop V1), l’administrateur doit surveiller manuellement `v_etat_stock`, saisir les ajustements `sp_adjust_stock` et faire évoluer manuellement les statuts `EN_PREPARATION -> EXPEDIEE -> LIVREE`. Pour passer à une exploitation professionnelle fluide pour des dizaines ou centaines de vendeurs, les opérations répétitives doivent être automatisées autour d’une **file de tâches asynchrones** (*Message Queue* : Redis Streams ou RabbitMQ + *workers* PHP CLI) :

| Processus back-office | Fonctionnement manuel actuel (V1) | Automatisation professionnelle cible | Impact opérationnel |
|---|---|---|---|
| **Réapprovisionnement prédictif** | Seuil statique `seuil_alerte = 3` par produit ; réassort saisi à la main (`DELTA`) | Calcul automatique du point de commande dynamique (`S_cmd = D_moy_30j × L_fournisseur + Z × σ_D × √L`) ; génération automatique de bons de commande fournisseur en brouillon | Réduit simultanément les ruptures de stock (`stock = 0`) et le sur-stockage immobilisant la trésorerie |
| **Import catalogue & Média** | Création/édition unitaire via formulaire `/admin/produits` | Import asynchrone massif (**CSV / XML / API REST vendeur**) avec validation atomique des contraintes `RB-02`/`RB-07`, redimensionnement automatique d’images (`WebP/AVIF`) et détection de doublons | Permet d’intégrer plusieurs milliers de références sans bloquer le serveur Web |
| **Facturation & Liasses PDF** | Récapitulatif HTML consultable en ligne | Génération asynchrone à la validation (`PAYEE`) d’une facture PDF horodatée et scellée au format **Factur-X** (hybride PDF/A-3 + XML structuré) archivée en stockage objet S3 | Conformité comptable et fiscale automatique dès la commande validée |
| **Expédition & Suivi transporteur** | Transition manuelle `EN_PREPARATION -> EXPEDIEE -> LIVREE` par l’admin | Appel API transporteur (Colissimo, Chronopost, Mondial Relay) générant l’étiquette PDF et le n° de suivi ; **webhooks transporteur** déclenchant automatiquement `sp_update_order_status` | Zéro ressaisie manuelle ; traçabilité client temps réel conservée dans `order_status_history` |
| **Tarification encadrée (*Repricing*)** | Modification manuelle du `prix_ht` | Règles de réajustement automatique face aux offres concurrentes de la *Buy Box*, strictement bornées par un plancher de rentabilité (`prix_ht >= cout_achat * (1 + marge_min)`) | Compétitivité continue sans jamais provoquer de vente à perte (`RB-02` étendue) |
| **Modération & Anti-fraude** | Contrôle humain a posteriori | Règles automatisées bloquant : hausse brutale de prix suspecte, pic d’annulations vendeur (`> 5 %`), commande atypique (score de risque avant expédition) et alertes RGPD automatisées | Protection des acheteurs et préservation de la réputation de la plateforme |

*Tableau 55 — Catalogue des automatisations du back-office vendeur et de la chaîne logistique.*

\newpage

### 6.6 Trajectoire de migration incrémentale en 4 paliers (*Strangler Fig Pattern*)

L’erreur classique d’une montée en charge consiste à vouloir tout réécrire d’un bloc (*Big Bang*). Grâce au contrat `StoreInterface`, à la centralisation des règles métier en dépôts PHP et à la séparation nette des couches MVC, l’évolution vers une plateforme de type Amazon doit suivre une **trajectoire incrémentale en 4 paliers** où chaque phase reste pleinement déployable et testable :

| Palier | Horizon / Objectif | Chantiers techniques prioritaires | Prérequis validé avant passage au palier suivant |
|---|---|---|---|
| **Palier 1 — Socle Production Mono-Boutique** | Court terme (mise en ligne commerciale réelle) | PSP (Stripe/Paybox) + webhooks idempotents ; SMTP transactionnel ; MFA `SUPER` ; Redis sessions + *rate limiting* ; index `commande(statut)` ; campagne Playwright + *LineServ* | 0 anomalie critique sur recette humaine (`200` scénarios) et test de charge ciblé |
| **Palier 2 — Automatisation Back-Office, Fournisseurs & Recommandation V1** | Moyen terme (productivité opérationnelle) | File asynchrone (*workers* CLI) pour factures Factur-X, étiquettes transporteur, webhooks de suivi ; tables `fournisseur` / `bon_commande_fournisseur` + réapprovisionnement prédictif (Tableau 53) ; recommandation SQL co-achats (Tableau 54) | Mesure de réduction du temps de traitement commande/réassort et hausse du panier moyen par A/B test |
| **Palier 3 — Ouverture *Marketplace* Multi-Vendeurs** | Croissance catalogue (*Seller Central*) | Tables `vendeur`, `offre_vendeur`, `sous_commande_vendeur` (Tableau 52) ; portail `/vendeur/*` isolé ; flux séquestre *Split Payment* ; élection de *Buy Box* et import massif | Audits IDOR multi-tenant et réconciliation comptable automatisée à 100 % |
| **Palier 4 — Recherche Sémantique, IA & Haute Disponibilité** | Grande échelle | Meilisearch/OpenSearch ; recommandation hybride (ALS + *embeddings*) ; *Repricing* borné ; réplicas MariaDB en lecture ; architecture multi-nœuds avec traces OpenTelemetry | SLA `>= 99,9 %`, latence `p95 < 100 ms` sous charge `100x` supérieure à `ENF-02` |

*Tableau 56 — Plan de migration incrémental en 4 paliers vers une plateforme e-commerce professionnelle.*

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

### Documentation technique des technologies employées et sources de scaling
- **[7] The PHP Group.** *PHP Manual — PDO, Password Hashing (`password_hash`), Session Security (`session.save_handler`), PHP-FPM (`pm.max_children`) & OPcache Preloading*. Disponible sur : `https://www.php.net/manual/fr/`.
- **[8] MariaDB Foundation & Oracle Corp.** *MariaDB 10.6+ / MySQL 8.0 Reference Manual — InnoDB Buffer Pool, `max_connections`, Locking Reads (`SELECT ... FOR UPDATE`), Replication, Stored Routines and Triggers*. Disponible sur : `https://mariadb.com/kb/` et `https://dev.mysql.com/doc/`.
- **[9] Apache Software Foundation, NGINX & Redis Ltd.** *Apache HTTP Server `mpm_event` Documentation* (`https://httpd.apache.org/docs/`), *NGINX HTTP Load Balancing* (`https://nginx.org/en/docs/`) et *Redis Official Documentation — Session Store & Caching* (`https://redis.io/docs/`).
- **[10] Union européenne, Mozilla Foundation (MDN) & W3C.** *Directives 2011/83/UE (consommateurs), (UE) 2023/2673 (rétractation en ligne), (UE) 2019/882 (European Accessibility Act)*, *Règlement (UE) 2022/2065 (DSA)*, *Norme EN 301 549* et *WCAG 2.1 AA* (`https://eur-lex.europa.eu/` et `https://www.w3.org/TR/WCAG21/`).

### Ressources graphiques, licences et corpus documentaire interne
- **[11]** Lucide Contributors & Cole Bemis (Feather). *Lucide Icons* (licence **ISC**, SVG inline dans `app/Security/xss.php`) ; Google Fonts / Łukasz Dziedzic, *Lato Regular* (licence **SIL OFL 1.1**, glyphe `U+20AC` `public/fonts/minishop-euro.woff2`, `public/fonts/OFL.txt`).
- **[12]** Polsinelli J., Gouiaa C., *Corpus documentaire interne MiniShop* : *Architecture logicielle* (`ARCHITECTURE.md`), *Cahier des charges* (`docs/01`), *Document de tests et validation* (`docs/02`), *Support de soutenance* (`docs/03`), *Conception BD et SQL* (`docs/04`), *Note cybersécurité* (`docs/07`), *Stratégie de tests* (`docs/11`), *Information juridique e-commerce* (`docs/12`), *Politique de conservation* (`docs/18`), *Audit UML* (`docs/23`), *Direction artistique* (`docs/DA.md`), *Procès-verbal de recette* (`docs/pv-recette.md`) et *Mesures de performance* (`tests/perf/dernieres_mesures.txt`), Université Côte d’Azur, 2026.

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
