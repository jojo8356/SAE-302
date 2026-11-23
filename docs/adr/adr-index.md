# Index des ADR — Décisions d’architecture de MiniShop

**Type Diátaxis :** Référence & Explication · **Propriétaire :** Architectes MiniShop (Johan Polsinelli & Chahd Gouiaa)  
**Modèle MADR :** [`template.md`](template.md) · **Revue :** À chaque changement d’architecture, de sécurité ou de schéma SQL (`docs/20-charte-gitlab-revues-croisees.md`)

---

## Règle de gouvernance

Un **ADR** (*Architecture Decision Record*) suit le format **MADR** (`NNNN-titre-en-kebab-case.md`) et capture **une seule décision** en 1 à 2 pages : statut, contexte, critères de choix (*Decision Drivers*), options comparées, décision et conséquences (positives, négatives et neutres). Un ADR passé au statut `Accepted` est **immuable** : si une décision évolue en V2, un nouvel ADR est créé et marque le précédent comme `Superseded`.

| Identifiant | Titre | Statut | Date | Fichier |
|---|---|---|---|---|
| **ADR-0000** | Adopter les Architecture Decision Records (MADR) dans Git | `Accepted` | 2026-09-15 | [`0000-utiliser-les-adr.md`](0000-utiliser-les-adr.md) |
| **ADR-0001** | Adopter une double persistance `SqlStore` et `JsonStore` derrière `StoreInterface` | `Accepted` | 2026-09-20 | [`0001-double-persistance-sqlstore-jsonstore.md`](0001-double-persistance-sqlstore-jsonstore.md) |
| **ADR-0002** | Porter les invariants transactionnels et d’audit par procédures stockées et déclencheurs MariaDB | `Accepted` | 2026-09-25 | [`0002-procedures-stockees-triggers-mariadb.md`](0002-procedures-stockees-triggers-mariadb.md) |
| **ADR-0003** | Utiliser des sessions PHP durcies, un routeur RBAC à liste blanche et zéro CDN externe | `Accepted` | 2026-10-02 | [`0003-authentification-session-rbac-hors-ligne.md`](0003-authentification-session-rbac-hors-ligne.md) |
| **ADR-0004** | Adopter une documentation dans Git (*Docs-as-Code*) compilée en multi-formats | `Accepted` | 2026-11-16 | [`0004-documentation-as-code-multi-formats.md`](0004-documentation-as-code-multi-formats.md) |
| **ADR-0005** | Construire une architecture MVC en PHP 8.2+ natif sans framework lourd | `Accepted` | 2026-09-12 | [`0005-architecture-mvc-php-natif-sans-framework.md`](0005-architecture-mvc-php-natif-sans-framework.md) |

*Tableau 1 — Registre des décisions d’architecture (ADR) actives dans MiniShop.*
