# Rapport complet de mise en production (*Production Readiness Report* & Dossier d’Exploitation) — MiniShop

> **Objet.** Ce rapport rassemble l’ensemble des preuves techniques, architecturales, sécuritaires, de performance et d’exploitation nécessaires pour prononcer la décision **Go / No-Go** de mise en production du projet **MiniShop (SAÉ 302)**. Il croise les standards de *Production Readiness Review* (PRR — SRE / ITIL 4 / OWASP) avec les éléments vérifiés dans le dépôt, les résultats de campagne sur VM Debian 13 et les procédures d’exploitation (*Runbook / DEX*).

---

## 1. Fiche d’identification et verdict synthétique *Go / No-Go*

### 1.1 Métadonnées du système livré

| Champ | Valeur vérifiée |
|---|---|
| **Nom du système** | **MiniShop** — Boutique e-commerce et back-office d’administration |
| **Cadre & commanditaire** | Université Côte d’Azur · IUT de Nice · BUT Informatique (SAÉ 302) |
| **Équipe projet** | **Johan Polsinelli** (`Owner`) & **Chahd Gouiaa** (`Developer`) |
| **Encadrement** | **NGUYEN Thanh-Phuong**, enseignante référente (`Reporter`) |
| **Stack d’exécution** | PHP **8.2+** (sans framework, `declare(strict_types=1)`), PDO natif, **MySQL 8.0.16+ / MariaDB 10.6+**, Apache 2.4 (`DocumentRoot public/`) |
| **Pilote secondaire** | Mode JSON (`STORAGE_DRIVER=json`, `data/minishop/*.json`) pour tests isolés et secours |
| **Références de preuve** | `docs/pv-recette.md` (`f9b614f`, 60/60), `tests/perf/dernieres_mesures.txt` (`cb0f814`, HTTP p95 = 6 ms), `tests/security/controles.sh` (11/11) |
| **Branche de référence** | `arena/da9a06dc-sae-302` |

### 1.2 Décision de mise en production (Verdict)

| Contexte de déploiement visé | Décision | Justification synthétique |
|---|---|---|
| **Mise en production pédagogique / démonstration isolée** | **GO CONDITIONNEL** | Socle technique, SQL, sécurité et performances validés automatiquement (`60/60`, HTTP p95 = `6 ms`, `11/11` sécurité). Sous réserve d’appliquer la checklist *Cutover* (§7) : `.env` production dédié, suppression/blocage de `gen_volumes.php` et `mesurer.php`, renouvellement des comptes de démonstration et sauvegarde initiale. |
| **Mise en production commerciale réelle (Internet / clients réels)** | **NO-GO (hors périmètre)** | Paiement bancaire simulé, formulaire de contact journalisé localement sans envoi d’e-mail (`var/log/contact.jsonl`), absence de MFA sur `SUPER`, absence de limiteur de débit (*rate limiting*) et 200 scénarios de recette manuelle (`docs/13-suivi-recette-uc.csv`) encore marqués `NON JOUÉ`. |

---

## 2. Scorecard *Production Readiness Review* (8 piliers SRE / ITIL)

| Pilier PRR | Statut | Preuves vérifiées dans le dépôt | Écarts / Conditions d’acceptation |
|---|---|---|---|
| **1. Architecture & Périmètre** | **PASS** | `ARCHITECTURE.md`, `STACK.md`, `docs/01`, 14 diagrammes UML (`docs/diagrams/`). Front controller unique `public/index.php`, `StoreInterface` découplé. | Périmètre maquette documenté (paiement et livraison simulés). |
| **2. Données & Base SQL** | **PASS** | `sql/01`…`04` : **9 tables**, **3 vues**, **18 procédures**, **1 fonction**, **16 triggers**. Verrouillage `SELECT ... FOR UPDATE` + `CHECK`. `29/29` tests SQL. | Indexation secondaire de `commande(statut)` à ajouter si forte volumétrie (> 50 000 commandes). |
| **3. Sécurité & Conformité** | **PASS (périmètre)** | `tests/security/controles.sh` (**11/11**), PDO préparé (`EMULATE_PREPARES=false`), bcrypt, CSRF `hash_equals`, IDOR (404), `X-Frame-Options: DENY`, CSP, séparation `client_id`/`admin_id`. | Pas de MFA ni de *rate limiting* applicatif ; comptes du seed à remplacer avant exposition. |
| **4. Tests & Qualité logicielle** | **PASS (auto)** | `60/60` VM (`docs/pv-recette.md`), `105/105` HTTP SQL, `8/8` concurrence, `57/57` PHPUnit, `46/46` fiches UC, `22/22` tests Python, PHP 8.2/8.5 lint. | Les 200 tests manuels navigateur (`docs/13-suivi-recette-uc.csv`) restent `NON JOUÉ`. |
| **5. Performance & Capacité (`ENF-01`)** | **PASS** | `tests/perf/dernieres_mesures.txt` (236 produits, 1 004 commandes) : **HTTP p95 = 6 ms**, **SQL p95 ≤ 20,6 ms**, écriture commande = `44 ms`, 0 erreur. | Mesuré sur VM locale dédiée (`minishop_perf`), seuil cible `< 500 ms` largement respecté. |
| **6. Livraison & Configuration** | **CONDITIONNEL** | `.env.example`, `public/.htaccess`, `scripts/db_init.php`, `scripts/preparer_sql_lineserv.py`, `run.sh`. | Générer `.env` privé côté serveur (`APP_DEBUG=0`, `APP_ENV=prod`) et retirer `gen_volumes.php` / `mesurer.php`. |
| **7. Exploitation, Sauvegardes & MCO** | **CONDITIONNEL** | `docs/18-politique-conservation.md`, `scripts/nettoyage_donnees.php`, `scripts/purger_comptes.php`, `scripts/archive_restreinte.php`, `docs/26`. | Planifier `cron` / tâches d’exploitation et réaliser un test réel de sauvegarde/restauration avant ouverture. |
| **8. Documentation & Passation** | **PASS** | 27 documents structurés (`docs/01`…`27`, annexes, `livrables/{html,pdf,docx}`, `minishop-soutenance.pptx`). | Valider le procès-verbal final de mise en production (§8). |

---

## 3. Architecture technique déployée et périmètre applicatif

MiniShop repose sur une architecture **MVC stricte sans framework** (`app/`) exposant uniquement `public/` comme racine Web (`DocumentRoot`), avec inversion de dépendance sur la couche de persistance (`StoreInterface`) :

![Architecture MVC et persistance duale de MiniShop](docs/diagrams/architecture_mvc.png)

### 3.1 Composants critiques en production

| Couche | Modules clés | Rôle en production |
|---|---|---|
| **Entrée & Routage** | `public/index.php`, `public/.htaccess`, `app/Router.php` | Point d’entrée unique, réécriture Apache vers `index.php`, liste blanche des routes, contrôle de méthode HTTP (`405`) et erreurs neutres (`404`/`403`/`500`). |
| **Contrôleurs Front** | `HomeController`, `CatalogueController`, `PanierController`, `AuthController`, `CompteController`, `CommandeController`, `ApiController` | 7 contrôleurs front-office et API JSON panier ; aucune confiance accordée aux prix/stocks envoyés par le navigateur (`EF-GEN-01`). |
| **Contrôleurs Admin** | `DashboardController`, `ProduitAdminController`, `CategorieAdminController`, `StockAdminController`, `CommandeAdminController`, `EquipeAdminController`, `ParametreAdminController` | 7 contrôleurs protégés par `Auth::exigeAdmin()` ; `/admin/equipe*` et `/admin/parametres*` réservés au rôle `SUPER` (`Auth::exigeSuper()`). |
| **Persistance** | `app/Config/Database.php`, `SqlStore.php`, `JsonStore.php`, `app/Repository/*` | Pilote par défaut `STORAGE_DRIVER=sql` via PDO (`ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`). |

---

## 4. Base de données, intégrité transactionnelle et performance

### 4.1 Inventaire du schéma SQL (`sql/01`…`04`)

| Objet SQL | Nombre | Rôle critique en production |
|---|---:|---|
| **Tables** | **9** | `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre` |
| **Vues** | **3** | `v_etat_stock` (calcul unifié des alertes), `v_catalogue` (produits visibles), `v_commandes_client` |
| **Procédures stockées** | **18** | Création/confirmation/annulation atomique de commande, gestion produits/catégories/stocks, calcul du port, reporting CA (`sp_revenue_report`) |
| **Fonction SQL** | **1** | `fn_param` (lecture centralisée des paramètres de la table `parametre`) |
| **Déclencheurs (Triggers)** | **16** | Décrémentation et restitution de stock, snapshot et immutabilité des prix facturés (`total_ligne`, `montant_total`), piste d’audit automatique, protection contre suppression d’articles commandés |

### 4.2 Synthèse des performances mesurées (`ENF-01`)

Campagne réalisée le **17/11/2026** sur VM Debian 13 (`cb0f814`, base `minishop_perf` contenant **236 produits**, **1 004 commandes**, **1 916 lignes** et **1 998 traces de statut**) :

| Mesure | Résultat mesuré | Seuil cible (`ENF-01`) | Verdict |
|---|---:|---:|---|
| **HTTP global (N = 200 req./URL, 6 routes)** | **p95 = 6,0 ms** (moy. 1,2–6,1 ms) | `< 500 ms` | **CONFORME** (0 réponse inattendue) |
| **Requêtes SQL de lecture (100 itérations)** | **p95 = 20,2 – 20,6 ms** | `< 500 ms` | **CONFORME** |
| **Écriture métier complète** (`sp_create_order` + `sp_add_order_line` + `sp_confirm_order`) | **44,0 ms** | `< 500 ms` | **CONFORME** |
| **Concurrence anti-survente** (`concurrence_http.php`, 20 acteurs / 12 unités) | **8/8** (0 stock négatif) | 0 survente | **CONFORME** |

> **Point d’attention d’exploitation (`EXPLAIN`) :** sur la requête d’analyse « commandes par statut », `EXPLAIN` relève un balayage de table (`type=ALL` sur 1 004 lignes) sans impact à l’échelle cible (20,5 ms p95), à indexer (`INDEX(statut)`) uniquement si le volume dépasse plusieurs dizaines de milliers de commandes.

---

## 5. Sécurité, conformité et analyse des risques résiduels

### 5.1 Mesures vérifiées automatiquement (`tests/security/controles.sh` — 11/11)

| Contrôle | Mécanisme vérifié dans le code | Référence |
|---|---|---|
| **Injection SQL** | 100 % requêtes préparées PDO / appels `CALL sp_*` ; `ATTR_EMULATE_PREPARES = false` | `SEC-01` |
| **XSS & CSP** | Échappement systématique via `e()` (`ENT_QUOTES` + `ENT_SUBSTITUTE`) + en-tête `Content-Security-Policy` | `SEC-02` |
| **Mots de passe** | `password_hash(..., PASSWORD_BCRYPT)` et `password_verify()`, 8 caractères min., messages neutres à la connexion | `SEC-04` / `RB-12` |
| **Sessions** | `session_regenerate_id(true)`, `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, isolation `$_SESSION['client_id']` / `$_SESSION['admin_id']` | `SEC-05` / `RB-13` |
| **CSRF** | Jeton CSPRNG 32 octets vérifié par `hash_equals()` sur l’ensemble des contrôleurs POST mutatifs | `SEC-06` |
| **IDOR** | Contrôle systématique d’appartenance `id_client` en base et retour neutre `404` sur commande tierce | `SEC-07` / `SEC-08` |
| **Durcissement HTTP & RBAC** | `X-Frame-Options: DENY` (`index.php` + `.htaccess`), `nosniff`, `Referrer-Policy`, aucune route d’inscription admin, séparation `SUPER`/`GESTIONNAIRE` | `SEC-10` / `SEC-11` |
| **Hygiène dépôt & PCI** | Aucun secret versionné (`.env` ignoré), aucun `eval()`/`shell_exec()`/`extract($_POST)`, aucune donnée bancaire stockée | `SEC-12` / `SEC-14` |

### 5.2 Registre des risques résiduels avant mise en production

| ID | Risque identifié | Criticité | Mesure de contournement / Prérequis avant ouverture |
|---|---|---|---|
| **R-01** | Comptes de démonstration du seed aux mots de passe publics connus | **Critique** | **Obligatoire :** remplacer ou désactiver les comptes du seed avant exposition et créer des identifiants `SUPER` forts et privés. |
| **R-02** | Scripts de développement présents sous `public/` (`gen_volumes.php`, `mesurer.php`) | **Élevée** | Bloqués en `APP_ENV=prod` (`404`), mais à **supprimer physiquement** du répertoire public déployé par défense en profondeur. |
| **R-03** | Absence de *rate limiting* applicatif sur `/connexion` et `/admin/connexion` | **Moyenne** | Activer une limitation au niveau reverse-proxy / serveur Web (`mod_evasive`, `fail2ban` ou WAF) en production exposée. |
| **R-04** | Absence de MFA sur le compte `SUPER` | **Moyenne** | Restreindre l’accès à `/admin/*` par liste blanche IP / VPN d’administration en attendant l’ajout d’un 2FA TOTP. |
| **R-05** | 200 scénarios de recette manuelle (`docs/13-suivi-recette-uc.csv`) à `NON JOUÉ` | **Moyenne** | Exécuter un parcours manuel complet de recette sur l’environnement cible pré-ouverture et archiver le PV signé. |

---

## 6. Dossier d’Exploitation (DEX — ITIL 4) pour la mise en production

### 6.1 Prérequis d’hébergement et configuration `.env` de production

| Paramètre | Valeur attendue en production | Remarque d’exploitation |
|---|---|---|
| `APP_ENV` | `prod` | Désactive les outils de développement et active `Secure` sur les cookies si HTTPS. |
| `APP_DEBUG` | `0` | Masque toute trace interne ; les erreurs sont journalisées côté serveur. |
| `STORAGE_DRIVER` | `sql` | Pilote de production nominal (`SqlStore` + PDO MySQL/MariaDB). |
| `DB_HOST` / `DB_PORT` | Hôte / port dédié | Ne pas utiliser `root` SQL : créer un utilisateur applicatif dédié avec privilèges limités à la base cible (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `EXECUTE`). |
| `DocumentRoot` | `.../public/` | `app/`, `sql/`, `var/`, `docs/`, `tests/`, `.env` **strictement hors de la racine Web**. |
| Permissions FS | PHP lecture seule sur `app/`, écriture sur `var/` | `chmod 640` / `600` sur `.env` selon l’utilisateur du serveur Web (`www-data`). |

### 6.2 Procédure de déploiement initial (*Cutover*) et retour arrière (*Rollback*)

1. **Sauvegarde préalable (T−30 min) :** réaliser un export complet `mysqldump --routines --triggers --events` de l’état initial ou existant dans un stockage privé hors racine Web.
2. **Déploiement du code :** transférer `app/`, `public/` (sans `gen_volumes.php` ni `mesurer.php`), `sql/`, `scripts/`, `var/` ; configurer `.env` hors web.
3. **Initialisation SQL (base vide uniquement) :** importer `sql/01_minishop_schema.sql` (ou sa version préparée sans `DROP DATABASE`), puis `sql/02_minishop_procedures.sql`, puis `sql/03_minishop_triggers.sql`.
4. **Durcissement des comptes :** créer le compte `SUPER` de production et vérifier la table `parametre` (`tva_defaut`, `frais_port`, `franchise_port`, `conservation_compte`).
5. **Plan de *Rollback* (< 15 min) :** en cas d’anomalie bloquante post-bascule :
   - passer le VirtualHost en page de maintenance (`503`) ;
   - restaurer l’archive code précédente et réimporter le dump SQL pré-bascule (`routines` et `triggers` inclus) ;
   - vérifier les compteurs et journaux avant réouverture.

### 6.3 Tests de fumée (*Smoke Tests*) immédiats post-MEP (`J+0`)

| # | Vérification post-déploiement | Résultat attendu |
|---|---|---|
| 1 | `GET /` et `GET /catalogue` | HTTP `200`, feuilles CSS/JS et images chargées sans erreur console |
| 2 | `GET /.env`, `GET /app/bootstrap.php`, `GET /gen_volumes.php` | HTTP `403` ou `404` (aucune fuite de code ou de configuration) |
| 3 | En-têtes HTTP de réponse (`curl -I`) | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy` présents |
| 4 | Parcours test synthétique (panier → commande → annulation en 2 étapes) | Commande créée, stock décrémenté puis restitué à l’annulation, trace dans `order_status_history` |
| 5 | Connexion `/admin/connexion` + `/admin/stocks` + `/admin/equipe` (`GESTIONNAIRE` vs `SUPER`) | `200` pour `SUPER`, `403` pour `GESTIONNAIRE` sur `/admin/equipe` |

### 6.4 Exploitation récurrente, sauvegardes et maintenance (MCO)

| Activité d’exploitation | Fréquence | Outil / Procédure du dépôt |
|---|---|---|
| **Sauvegarde complète BD** (`tables + routines + triggers`) | Quotidienne (automatisée) | `mysqldump --single-transaction --routines --triggers` + test mensuel de restauration sur base isolée |
| **Rotation et purge des journaux** | Hebdomadaire | `php scripts/nettoyage_donnees.php` (simulation puis `--executer`) — cf. `docs/18-politique-conservation.md` |
| **Rétention / anonymisation des comptes inactifs** | Mensuelle | `php scripts/purger_comptes.php` selon `parametre.conservation_compte` |
| **Contrôle de sécurité & intégrité** | À chaque mise à jour | `bash tests/security/controles.sh` + `php scripts/run-all-tests.php` |
| **Surveillance des erreurs PHP / Apache** | Quotidienne | Logs privés serveur + `var/log/` (jamais exposés en HTTP) |

### 6.5 Matrice d’escalade (N1 / N2 / N3)

| Niveau | Périmètre d’intervention | Responsable | Délai cible de prise en charge |
|---|---|---|---|
| **N1 — Support & Exploitation** | Indisponibilité HTTP, saturation disque/logs, redémarrage service Apache/MariaDB, restauration sauvegarde | Administrateur système / Exploitation IUT | `< 30 min` (heures ouvrées) |
| **N2 — Maintenance applicative** | Anomalie fonctionnelle front/back-office, blocage transition statut commande, ajustement paramètres | Équipe projet MiniShop (Johan Polsinelli / Chahd Gouiaa) | `< 4 h` (incident majeur) / `< 24 h` |
| **N3 — Base de données & Sécurité** | Corruption intégrité, verrou SQL persistant, suspicion faille ou fuite d’accès | Référent BD / Sécurité & Encadrement | Immédiat (`< 15 min` si incident sécurité) |

---

## 7. Checklist finale de décision avant ouverture

- [ ] `.env` créé hors racine Web avec `APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql` et utilisateur SQL restreint.
- [ ] Scripts dev retirés du `DocumentRoot` (`public/gen_volumes.php`, `public/mesurer.php`).
- [ ] Comptes de démonstration du seed désactivés / renouvelés par un compte `SUPER` dédié.
- [ ] Sauvegarde complète (`--routines --triggers`) vérifiée et procédure de *rollback* validée.
- [ ] Campagne *smoke tests* post-MEP (`§6.3`) exécutée avec `0` anomalie bloquante.
- [ ] Recette manuelle complémentaire consignée dans `docs/13-suivi-recette-uc.csv`.

---

## 8. Procès-verbal d’autorisation de mise en production

| Rôle | Nom | Décision (`GO` / `NO-GO`) | Date & Visa |
|---|---|---|---|
| **Responsable Technique / Développement** | Johan Polsinelli | *À renseigner lors de la MEP* | `____ / ____ / 2026` |
| **Responsable Qualité / Développement** | Chahd Gouiaa | *À renseigner lors de la MEP* | `____ / ____ / 2026` |
| **Maître d’ouvrage / Encadrement** | NGUYEN Thanh-Phuong | *À renseigner lors de la MEP* | `____ / ____ / 2026` |

---

## 9. Références méthodologiques et sources

1. **Référentiels internes MiniShop :** `ARCHITECTURE.md`, `STACK.md`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/04-conception-bd-et-sql.md`, `docs/07-note-cybersecurite.md`, `docs/18-politique-conservation.md`, `docs/26-transition-exploitation-maintenance-build-to-run.md`, `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt`.
2. **Production Readiness Review (PRR) & SRE :** [Production Readiness Review & Checklist (8 Operational Pillars)](https://vettom.pages.dev/Monitoring/Observability/production-readiness/) et [Production Readiness Checklist for Software Teams](https://dasha.ai/blog/production-readiness-checklist).
3. **Dossier d’Exploitation (DEX — ITIL 4) :** [Modèle DEX — DataALC (11 rubriques ITIL 4)](https://www.dataalc.com/boite-a-outils/templates/dex/).
4. **Checklist Go-Live & Sécurité applicative :** [Production Readiness Checklist: 123 Items and How to Verify](https://axonbuild.com/blog/production-readiness-checklist) et [Go-Live : préparation, cutover et rollback](https://www.kern-it.be/en/definitions/go-live/).
