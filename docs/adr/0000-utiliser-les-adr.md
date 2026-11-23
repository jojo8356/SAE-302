# ADR-0000 : Adopter les Architecture Decision Records (MADR) dans Git

## Statut

Accepted

Date: 2026-09-15
Auteurs: Johan Polsinelli, Chahd Gouiaa
Reviewers: Équipe MiniShop

## Contexte et problème

Le dépôt Git de MiniShop conserve l’historique des commits (*ce qui* a été modifié), mais pas le raisonnement complet derrière les choix d’architecture coûteux à défaire (persistance, SQL, sécurité, outillage). Sans trace dédiée, un repreneur ou un membre du jury ne retrouve ni les critères de décision ni les options écartées.

## Decision Drivers

- Conserver la mémoire des décisions à côté du code qu’elles concernent
- Garder un format court (1 à 2 pages) lisible en moins de cinq minutes
- Imposer la comparaison d’options avant de conclure
- Automatiser la vérification de forme et de numérotation en CI

## Options considérées

### Option 1 : Consigner les choix uniquement dans les messages de commit et le rapport final

**Avantages** :

- Aucun fichier supplémentaire à maintenir

**Inconvénients** :

- Raisonnement dispersé sur des dizaines de commits ou noyé dans un rapport de plus de 100 pages
- Aucune structure homogène pour retrouver les alternatives rejetées

### Option 2 : Wiki externe (GitLab Wiki / Notion)

**Avantages** :

- Édition web rapide hors Git

**Inconvénients** :

- Désynchronisé des pull requests et inaccessible lors d’une exécution hors ligne (`ENF-20`)

### Option 3 : ADR au format MADR dans `docs/adr/NNNN-titre.md`

**Avantages** :

- Une décision par fichier, immuable une fois acceptée (`Accepted`), versionnée dans le même commit que le code
- Structure MADR normalisée (`Statut`, `Contexte`, `Decision Drivers`, `Options considérées`, `Décision`, `Conséquences`, `Liens`)

**Inconvénients** :

- Demande 15 à 30 minutes de rédaction lors des choix structurants
- Exige un contrôle CI pour éviter les oublis de sections ou trous de numérotation

## Décision

**Nous adoptons les Architecture Decision Records au format MADR** dans `docs/adr/NNNN-titre.md`, avec un modèle `docs/adr/template.md` et une numérotation séquentielle commençant à `0000`.

Raisons principales :

1. Chaque décision majeure reste courte, datée et immuable à côté du code source.
2. Une fois accepté, un ADR n’est plus réécrit ; toute remise en cause crée un nouvel ADR qui remplace l’ancien (`Superseded`).

## Conséquences

### Positives

- Traçabilité claire des choix d’architecture pour les développeurs, l’exploitation et la soutenance
- Validation automatisée de la structure et de la numérotation par script et CI

### Négatives

- Toute nouvelle décision structurante doit passer par la création d’un fichier numéroté dans `docs/adr/` et la mise à jour de `docs/17-inventaire-projet.csv`

### Neutres

- Les détails d’implémentation courants restent dans la documentation technique (`ARCHITECTURE.md`, `docs/04-*`, `docs/07-*`) et les docstrings

## Liens

- Modèle MADR : `docs/adr/template.md`
- Gouvernance documentaire : `docs/28-documentation-qui-survit-minishop.md`
