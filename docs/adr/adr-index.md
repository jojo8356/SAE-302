# Index des ADR — Décisions d’architecture de MiniShop

**Type Diátaxis :** Référence & Explication · **Propriétaire :** Architectes MiniShop (Johan Polsinelli & Chahd Gouiaa)  
**Revue :** À chaque changement d’infrastructure, de sécurité ou de schéma SQL (`docs/20-charte-gitlab-revues-croisees.md`)

---

## Règle de gouvernance

Un **ADR** (*Architecture Decision Record*) explique **pourquoi** un choix technique a été retenu à un instant donné, quelles alternatives ont été écartées et quelles conséquences il impose à l’exploitation. On ne réécrit jamais un ADR passé en silence : si une décision change en V2, on crée un nouvel ADR qui marque le précédent comme *Remplacé par ADR-XXXX*.

| Identifiant | Titre | Statut | Propriétaire | Fichier |
|---|---|---|---|---|
| **ADR-0001** | Double persistance `SqlStore` et `JsonStore` derrière `StoreInterface` | Accepté | Équipe MiniShop | `adr-0001-double-persistance-sqlstore-jsonstore.md` |
| **ADR-0002** | Procédures stockées et déclencheurs dans MariaDB 3FN | Accepté | Équipe MiniShop | `adr-0002-procedures-stockees-triggers-mariadb.md` |
| **ADR-0003** | Sessions strictes, RBAC et zéro dépendance CDN hors ligne | Accepté | Équipe MiniShop | `adr-0003-authentification-session-rbac-hors-ligne.md` |
| **ADR-0004** | Documentation dans Git (*Docs-as-Code*) et génération multi-formats | Accepté | Équipe MiniShop | `adr-0004-documentation-as-code-multi-formats.md` |

*Tableau 1 — Registre des décisions d’architecture (ADR) actives dans MiniShop.*
