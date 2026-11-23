# Préparation complète de la soutenance — MiniShop (SAÉ 302)

> Document de préparation opérationnelle pour la soutenance de **15 minutes + questions** devant le jury (Université Côte d'Azur — BUT2 Informatique).  
> Ce document complète [`03-support-soutenance.md`](03-support-soutenance.md) et [`12-diaporama-soutenance.md`](12-diaporama-soutenance.md) avec : le barème officiel, le déroulé minuté, le script pas-à-pas de la démo en direct, les chiffres exacts vérifiés dans le dépôt, les justifications des choix face au sujet officiel et 20 questions-réponses du jury.

---

## 1. Format officiel, attentes du jury et points de vigilance

### 1.1 Structure de l’évaluation (sujet officiel SAÉ 302)

| Bloc | Attentes principales du jury | Preuves à montrer |
|---|---|---|
| **i. Cahier des charges & conception** | Problème initial, acteurs (`Visiteur`, `Client`, `Administrateur`), 14 UC minimum, 11 règles métier (`RB-01`…`RB-11` + extensions), sécurité (`SEC-01`…`SEC-14`), architecture MVC | `docs/01-cahier-des-charges-MiniShop.md`, diagrammes UML (`docs/diagrams/`) |
| **ii. Modélisation BD & SQL** | MCD (cardinalités justifiées), MLD en 3FN (dénormalisations contrôlées justifiées), scripts reproductibles, **≥ 5 procédures stockées** et **≥ 5 triggers**, requêtes d’analyse | `docs/04-conception-bd-et-sql.md`, `sql/01`…`04`, terminal `mysql` |
| **iii. Application Web & sécurité** | MVC PHP natif (`declare(strict_types=1)`), PDO préparé (`ATTR_EMULATE_PREPARES => false`), `password_hash`/`password_verify`, protection CSRF, contrôle IDOR, séparation front/back-office, accessibilité/responsive | Code `app/`, démo navigateur en direct, `tests/security/controles.sh` (`11/11`) |
| **iv. Tests, GitLab & soutenance (15 min)** | Stratégie & exécution des tests (unitaires, intégration SQL, E2E, concurrence, perf), dépôt **GitLab IUT** propre (commits des deux membres, README complet, accès `Reporter` de l’enseignante), clarté de la démo et réponses aux questions | GitLab IUT (`git shortlog -sne`), `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt` |

### 1.2 Points de vigilance critiques (pénalités du sujet à éviter absolument)

1. **Dépôt GitLab IUT à jour :** fusionner les dernières branches/corrections sur le dépôt GitLab IUT avant la soutenance, vérifier que `NGUYEN Thanh-Phuong` est membre `Reporter` et que le `README.md` contient présentation, installation, technologies et répartition des tâches (**Johan Polsinelli** / **Chahd Gouiaa**).
2. **Ne jamais réutiliser de mots de passe en clair, secrets ou `.env` dans Git.**
3. **Ne jamais présenter une donnée simulée ou un document non exécuté comme un test réel :** distinguer clairement les tests automatisés exécutés (`11/11` sécurité, `29/29` intégration SQL, `105/105` HTTP SQL E2E, `8/8` concurrence HTTP, `46/46` fiches UC) des campagnes manuelles notées `NON JOUÉ` (`docs/13-suivi-recette-uc.csv`).
4. **Démonstration fluide sans bidouillage en séance :** base chargée proprement à l’avance, deux fenêtres prêtes (front-office + back-office) et terminal SQL déjà ouvert.

---

## 2. Trame chronométrée de la présentation (15 minutes)

> L’équipe comporte deux étudiants (**Johan Polsinelli** et **Chahd Gouiaa**). Les rôles `Binôme 1` / `Binôme 2` ci-dessous servent de répartition équilibrée (~7 min 30 chacun) et peuvent être inversés selon votre aisance.

| Temps | Intervenant | Thème | Support / Écran affiché | Messages clés à placer |
|---|---|---|---|---|
| **0:00 – 1:30** | Binôme 1 | Contexte, objectifs & périmètre | Diapos 1–2 + `usecase_minishop.png` | Problème initial (tableur partagé sans stock ni historique) → MiniShop (3 acteurs, 14 UC + extensions, périmètre maquette sans paiement réel). |
| **1:30 – 3:30** | Binôme 2 | Modélisation BD (MCD → MLD → 3FN) | Diapo 3 + `mcd_minishop.png`, `mld_minishop.png` | 9 tables en 3FN ; **2 dénormalisations verrouillées** (`produit.prix_ttc`, `commande.montant_total` + `ligne_commande.total_ligne`) pour figer la facturation. |
| **3:30 – 5:30** | Binôme 2 | SQL avancé : procédures & triggers | Diapo 4 + `sql/02_minishop_procedures.sql`, `sql/03_minishop_triggers.sql` | **18 procédures**, **1 fonction**, **16 triggers**, **3 vues**. Atomicité transactionnelle + `SELECT ... FOR UPDATE` contre la survente (`RB-06`, `RB-07`). |
| **5:30 – 7:30** | Binôme 1 | Architecture MVC & sécurité | Diapos 5–6 + `architecture_mvc.png`, `app/Security/Auth.php`, `Csrf.php` | Front controller unique (`public/index.php`), PDO natif (`EMULATE_PREPARES = false`), `password_hash`/`verify`, CSRF `hash_equals`, IDOR (404), séparation `client_id`/`admin_id`. |
| **7:30 – 11:30** | Binôme 1 & 2 | **Démonstration en direct (4 min)** | Navigateurs (Front + Back-office) + Terminal SQL | Parcours complet : catalogue/filtre → panier → commande → annulation client en 2 étapes → back-office (stock delta, changement de statut) → preuve SQL (`stock_movement_history`, `order_status_history`). |
| **11:30 – 13:30** | Binôme 2 | Tests, concurrence & performances | Diapos 7–8 + `docs/pv-recette.md`, `tests/perf/dernieres_mesures.txt` | `11/11` sécurité, `29/29` SQL, `105/105` E2E HTTP SQL, `8/8` concurrence (20 clients / 12 unités sans survente), HTTP p95 = `6 ms` sous 236 produits / 1 004 commandes. |
| **13:30 – 15:00** | Binôme 1 | Organisation GitLab, limites & bilan | Diapos 9–12 + `git shortlog -sne` | Travail en binôme sur GitLab, choix d’architecture (mode SQL défaut + mode JSON portable), évolutions futures (paiement réel, remises, panier persistant). |

---

## 3. Script pas-à-pas de la démonstration en direct (4 minutes)

### 3.1 Préparation avant d’entrer en salle

Dans un terminal, depuis la racine du dépôt :

```bash
cp -n .env.example .env
./run.sh --db
./run.sh
```

- Vérifier que l’application répond sur **`http://localhost:8000/`**.
- Ouvrir **deux fenêtres navigateur distinctes** (ou une fenêtre normale + une fenêtre navigation privée) pour éviter tout mélange de cookies :
  - **Fenêtre 1 (Front-office / Client)** : `http://localhost:8000/`
  - **Fenêtre 2 (Back-office / Admin)** : `http://localhost:8000/admin/connexion`
- Ouvrir un **2e onglet de terminal** connecté à MySQL/MariaDB prêt à exécuter les requêtes de preuve :

```bash
mysql -u <utilisateur> -p minishop
```

> **Plan B hors ligne (si le serveur SQL tombe en salle) :** basculer `STORAGE_DRIVER=json` dans `.env` et relancer `./run.sh`. Les parcours front-office, panier, commandes et back-office fonctionnent intégralement à l’identique grâce au contrat commun `DataStore`.

### 3.2 Scénario de démo chronométré (4 minutes)

1. **Catalogue & recherche (`0:45`) — Fenêtre 1 (Visiteur) :**
   - Montrer l’accueil (`/`), les catégories et les nouveautés.
   - Aller sur `/catalogue`, filtrer par catégorie + tri + recherche texte.
   - Ouvrir une fiche produit (`/produit/{id}`) : montrer le prix TTC, la disponibilité serveur (`stock_actuel`) et ajouter une quantité au panier (`POST /panier/ajouter`).
2. **Panier & commande client (`1:15`) — Fenêtre 1 (Client) :**
   - Aller sur `/panier`, modifier une quantité (montrer le plafonnement côté serveur si quantité > stock).
   - Cliquer sur valider la commande (`/connexion` puis `/commander`) avec un compte client du seed (`alice@example.com`, identifiants locaux dans `tests/php/http_sql_e2e.php`).
   - Montrer le calcul serveur des frais de port (`sp_compute_shipping`) et du total TTC avant confirmation, puis valider.
   - Aller sur `/mes-commandes`, ouvrir le détail de la commande créée, puis montrer **l’annulation en 2 étapes** (page de confirmation dédiée + conservation de la ligne en historique, statut `ANNULEE`).
3. **Back-office (`1:15`) — Fenêtre 2 (Admin `SUPER`) :**
   - Se connecter sur `/admin/connexion` (`admin@minishop.fr`).
   - Sur `/admin` (tableau de bord) : commenter les indicateurs (`CA total` vs `CA du jour` en fuseau local, alertes de stock bas).
   - Aller sur `/admin/stocks` : montrer le mode par défaut **« Ajouter / retirer » (`DELTA`)** sur un produit et justifier le motif obligatoire (`REASSORT`, `CORRECTION`, etc.).
   - Aller sur `/admin/commandes` : filtrer et faire évoluer une commande `EN_PREPARATION` → `EXPEDIEE` (montrer le changement d’état dans la piste d’audit).
4. **Preuve directe en base de données (`0:45`) — Terminal SQL :**

```sql
SHOW PROCEDURE STATUS WHERE Db = DATABASE();
SHOW TRIGGERS;
SELECT * FROM v_etat_stock LIMIT 5;
SELECT id_commande, ancien_statut, nouveau_statut, date_changement
  FROM order_status_history
 ORDER BY id_historique DESC LIMIT 5;
SELECT id_mouvement, id_produit, delta_quantite, motif, date_mouvement
  FROM stock_movement_history
 ORDER BY id_mouvement DESC LIMIT 5;
CALL sp_revenue_report('2026-01-01 00:00:00', '2026-12-31 23:59:59');
```

---

## 4. Fiche mémo technique (chiffres exacts du dépôt)

À garder sous les yeux pendant les questions techniques :

### 4.1 Base de données & SQL (`sql/`)

| Élément | Quantité réelle | Détails précis à citer |
|---|---|---|
| **Tables** | **9** | `administrateur`, `client`, `categorie`, `produit`, `commande`, `ligne_commande`, `order_status_history`, `stock_movement_history`, `parametre` |
| **Vues SQL** | **3** | `v_etat_stock`, `v_catalogue`, `v_commandes_client` |
| **Procédures stockées** | **18** | `sp_compute_shipping`, `sp_create_account`, `sp_get_credentials`, `sp_update_client`, `sp_search_products`, `sp_save_product`, `sp_delete_product`, `sp_adjust_stock`, `sp_save_category`, `sp_delete_category`, `sp_create_order`, `sp_add_order_line`, `sp_create_order_from_basket`, `sp_confirm_order`, `sp_update_order_status`, `sp_cancel_order`, `sp_cancel_order_admin`, `sp_revenue_report` |
| **Fonction SQL** | **1** | `fn_param` |
| **Déclencheurs (triggers)** | **16** | Contrôle/décrément/restauration/immutabilité/snapshot des lignes (`trg_ligne_*`), calcul TTC & règles produits (`trg_produit_*`), slugs & suppression catégories (`trg_categorie_*`), transitions/historique commandes (`trg_commande_*`, `trg_history_*`) |
| **Données de base** | `sql/01` + `sql/04` | 4 catégories, 36 produits, 3 clients seed, 1 admin `SUPER` seed (+ commandes de démo dans `04`) |

### 4.2 Architecture applicative & sécurité (`app/`, `public/`)

- **Point d’entrée unique :** `public/index.php` (routeur explicite avec contrôle de méthode HTTP → `405` / `404`, gestionnaire d’exceptions centralisé, en-têtes de sécurité).
- **Contrôleurs front-office :** `HomeController`, `CatalogueController`, `PanierController`, `AuthController`, `CompteController`, `CommandeController`.
- **Contrôleurs back-office (`app/Controller/Admin/`) :** `DashboardController`, `ProduitAdminController`, `CategorieAdminController`, `StockAdminController`, `CommandeAdminController`, `EquipeAdminController`, `ParametreAdminController`.
- **Sécurité vérifiée (`tests/security/controles.sh` — 11/11 conformes) :**
  1. **SQLi (`SEC-01`) :** 100 % requêtes préparées PDO (`ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`).
  2. **XSS (`SEC-02`) :** échappement systématique via `e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) + en-tête `Content-Security-Policy`.
  3. **Mots de passe (`SEC-04` / `RB-12`) :** hachage `password_hash(..., PASSWORD_BCRYPT)` et vérification `password_verify()`, 8 caractères minimum côté serveur et HTML, messages identiques en cas d’échec de connexion.
  4. **Sessions (`SEC-05`) :** `session_regenerate_id(true)` à la connexion et déconnexion, cookies `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, séparation `$_SESSION['client_id']` / `$_SESSION['admin_id']`.
  5. **CSRF (`SEC-06`) :** jeton aléatoire 32 octets (`bin2hex(random_bytes(32))`) vérifié en temps constant via `hash_equals()` sur toutes les routes mutatives.
  6. **IDOR (`SEC-07` / `SEC-08`) :** filtrage systématique `WHERE id_commande = ? AND id_client = ?` (ou contrôle dans `sp_confirm_order` / `sp_cancel_order`) → `404` neutre en cas de tentative d’accès croisé.
  7. **RBAC Back-office (`SEC-10` / `RB-13`) :** `Auth::exigeAdmin()` sur toutes les routes `/admin/*` et `Auth::exigeSuper()` sur `/admin/equipe*` et `/admin/parametres*` (`403` pour un `GESTIONNAIRE`).
  8. **En-têtes HTTP (`SEC-11`) :** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `CSP`.

### 4.3 Campagnes de tests automatisés

| Suite | Outil / Fichier | Dernier résultat consigné | Ce qu’elle prouve |
|---|---|---|---|
| **Contrôles de sécurité statiques** | `bash tests/security/controles.sh` | **11/11** (exécutable à tout moment) | PDO préparé, `e()`, bcrypt, IDOR, CSRF, sessions, gardes admin, absence de secrets |
| **Cohérence documentation / UC** | `python3 scripts/verifier_fiches_uc.py` | **46/46** | Cohérence des 14 UC, scénarios et tableaux de couverture |
| **Audit structurel & inventaire** | `python3 -m unittest discover -s tests/python` | Complet | Inventaire `docs/17-inventaire-projet.csv`, syntaxe Python/Shell/JSON/XML |
| **Intégration SQL (MySQL 8 & MariaDB 11.8)** | `tests/php/sql_integration.php` | **29/29** (`docs/pv-recette.md`) | Contraintes `CHECK`, triggers `T-01…T-29`, procédures, rollbacks |
| **E2E HTTP complet sur SQL** | `tests/php/http_sql_e2e.php` | **105/105** (`docs/pv-recette.md`) | Parcours complets client & admin via HTTP + PDO |
| **Concurrence anti-survente** | `tests/php/concurrence_http.php` | **8/8** (20 acteurs / 12 unités) | Zéro stock négatif sous requêtes concurrentes |
| **Performances (volumétrie cible)** | `scripts/run_perf_vm.sh` | HTTP p95 = **6 ms**, SQL p95 ≤ **20,6 ms** | Tenue de charge sur 236 produits et 1 004 commandes |
| **CI GitHub Actions** | `.github/workflows/ci.yml` | Multi-jobs (PHP 8.2/8.5, MySQL 8, MariaDB 11.8, WASM, Playwright) | Non-régression continue à chaque push |

---

## 5. Justifier proprement les choix et écarts face au sujet officiel

Si le jury compare mot à mot `sujetSAE.pdf` avec le dépôt, voici les **6 points précis** à expliquer calmement et avec assurance :

| Point du sujet officiel | Ce qui a été implémenté | Pourquoi ce choix est meilleur / conforme |
|---|---|---|
| Exemple de session `$_SESSION['user_id']` (p. 5 du sujet) | `$_SESSION['client_id']` et `$_SESSION['admin_id']` dans `app/Security/Auth.php` | **Défense en profondeur (`RB-13`)** : un client connecté ne peut jamais être confondu avec un administrateur, même dans une même session navigateur. |
| Nom indicatif `sp_add_order_item` (p. 7 du sujet) | `sp_add_order_line` + `sp_create_order_from_basket` (`sql/02_minishop_procedures.sql`) | Cohérent avec le nom français de la table `ligne_commande` ; `sp_create_order` et `sp_update_order_status` gardent les noms exacts du sujet. |
| Statut initial de commande | État interne `BROUILLON` puis confirmation → `EN_PREPARATION` | Permet de respecter `RB-04` (aucune commande validée sans ligne) ; un `BROUILLON` est refusé côté admin (`COMMANDE_NON_TRAITABLE`). |
| Rôle administrateur unique dans le sujet | Deux rôles : `SUPER` et `GESTIONNAIRE` | Principe du **moindre privilège** (OWASP ASVS / ANSSI) : seul `SUPER` crée/bloque des admins ou modifie les paramètres globaux. |
| Dénormalisation vs 3FN stricte | `produit.prix_ttc`, `commande.montant_total`, `ligne_commande.total_ligne` | Recommandation métier e-commerce : un changement de prix catalogue ou de TVA ne doit **jamais** modifier rétroactivement une facture passée ; ces colonnes sont verrouillées par triggers. |
| Formulaire de contact & paiement | Stockage local dans `var/log/contact.jsonl`, paiement/livraison simulés | Respect strict du périmètre pédagogique (`sujetSAE.pdf` : « paiement factice, sans vraie transaction bancaire »), documenté dans `README.md` et `/mentions-legales`. |

---

## 6. Top 20 des questions probables du jury (et réponses prêtes en 20 s)

### Base de données & SQL

1. **Pourquoi des procédures stockées plutôt que tout gérer en PHP ?**  
   Elles garantissent l’atomicité transactionnelle (`START TRANSACTION` / `COMMIT` / `ROLLBACK`), posent les verrous `SELECT ... FOR UPDATE` au plus près des données pour empêcher la survente, réduisent les allers-retours réseau et imposent les mêmes invariants même si une autre application appelle la base.
2. **Comment empêchez-vous qu’une commande vende plus de stock que disponible si deux clients cliquent en même temps ?**  
   Dans `sp_add_order_line` / `sp_create_order_from_basket`, la lecture du produit utilise `SELECT ... FOR UPDATE` à l’intérieur d’une transaction, puis le trigger `trg_ligne_controle_insert` et la contrainte `CHECK (stock_actuel >= 0)` valident la cohérence. Validé par `concurrence_http.php` (20 clients concurrents sur 12 unités : 0 survente).
3. **Pourquoi garder `total_ligne` et `montant_total` en base alors qu’on peut les recalculer ?**  
   C’est une dénormalisation volontaire et **verrouillée par trigger** (`trg_ligne_prix_snapshot`, `trg_ligne_immutable`) : si le prix HT ou la TVA du produit change demain, les commandes historiques conservent exactement le montant payé le jour de l’achat.
4. **Pourquoi un trigger pour `order_status_history` plutôt qu’un `INSERT` dans la procédure ?**  
   Pour garantir la traçabilité même lors d’une mise à jour SQL directe (maintenance, reprise). Les triggers `trg_history_creation` et `trg_history_statut` assurent l’unicité et l’ordre chronologique de la piste d’audit.
5. **Que se passe-t-il si on tente un `UPDATE` direct `SET stock_actuel = -5` en SQL ?**  
   Il est bloqué au niveau SGBD par la contrainte `CHECK` et les triggers de contrôle (`trg_produit_regles`), testés dans `tests/php/sql_integration.php` (`T-02`, `T-03`).
6. **Pourquoi vérifiez-vous le mot de passe en PHP (`password_verify`) et pas dans la procédure SQL ?**  
   Le sel et le coût bcrypt sont encapsulés dans le hash généré par PHP ; seule la fonction `password_verify()` compare en temps constant. La procédure `sp_get_credentials` ne renvoie donc que le hash et l’état du compte, et la vérification reste côté PHP.
7. **Que se passe-t-il si on supprime une catégorie qui contient des produits, ou un produit déjà commandé ?**  
   Les clés étrangères (`ON DELETE RESTRICT`) et les triggers `trg_categorie_delete` / `trg_produit_delete` bloquent la suppression pour préserver l’historique comptable. En pratique, l’administrateur masque un produit (`visible = 0`) au lieu de le supprimer.
8. **Pourquoi une double gestion de pilote (`SQL` par défaut et `JSON` secondaire) ?**  
   Le pilote SQL/PDO est le moteur principal conforme au sujet ; l’interface commune `DataStore` permet d’exécuter le même code applicatif sur un magasin JSON en environnement isolé (tests WASM sans serveur MySQL ou plan B de démonstration).

### Architecture MVC & code PHP

9. **Comment fonctionne votre architecture MVC ? Qui fait quoi ?**  
   `public/index.php` reçoit toutes les requêtes, résout la route, appelle le contrôleur (`app/Controller/`), qui valide les entrées/CSRF/droits, délègue l’accès données aux `Repository` (`app/Repository/`) et passe uniquement les données préparées à la vue (`app/View/`).
10. **Pourquoi `declare(strict_types=1);` dans chaque fichier PHP ?**  
    Pour éviter les conversions silencieuses de types (par exemple entre chaînes issues de `$_POST` et entiers d’identifiants) et détecter les erreurs dès l’exécution ou l’analyse statique.
11. **Où sont gérés les prix et stocks lors de la validation du panier ? Faites-vous confiance au formulaire ?**  
    Jamais. Le navigateur n’envoie que `id_produit` et `quantite`. Tous les prix, taux de TVA, stocks et frais de port sont relus et recalculés côté serveur (`PanierSession`, `CommandeRepository`, procédures SQL).
12. **Pourquoi les administrateurs sont-ils dans une table séparée `administrateur` plutôt que dans `client` ?**  
    Le cycle de vie, les attributs (adresse postale vs rôle `SUPER`/`GESTIONNAIRE`) et la surface d’attaque sont distincts : aucune route d’inscription publique ne peut écrire dans `administrateur` (`SEC-10`).

### Sécurité

13. **Comment prouvez-vous l’absence d’injection SQL ?**  
    Toutes les requêtes passent par `PDO::prepare()` + `execute()` avec `ATTR_EMULATE_PREPARES => false` (vérifié statiquement par `tests/security/controles.sh` et testé dynamiquement en E2E). Aucune concaténation SQL de variable utilisateur n’existe dans `app/`.
14. **Comment évitez-vous qu’un client consulte la commande d’un autre en changeant l’URL (`/commande/25`) ?**  
    `CommandeController` exige d’abord `Auth::exigeClient()`, puis filtre toujours par `id_client = $session['id']` dans la requête / procédure (`sp_confirm_order`, `sp_cancel_order`). Si la commande n’appartient pas au client, le serveur renvoie **404** (et non 403) pour ne pas révéler l’existence de l’ID.
15. **Comment fonctionne votre protection CSRF ?**  
    Chaque session génère un jeton de 32 octets aléatoires (`Csrf::jeton()`). Chaque formulaire `POST` inclut ce jeton, et `Csrf::verifier()` compare avec `hash_equals()` avant toute écriture métier.
16. **Pourquoi vos cookies de session ont-ils `HttpOnly`, `SameSite=Lax` et `Secure` ?**  
    `HttpOnly` empêche le vol du cookie via JavaScript en cas de XSS ; `SameSite=Lax` limite l’envoi du cookie depuis des sites tiers (CSRF) ; `Secure` impose HTTPS dès que le site est servi en TLS.

### Tests, GitLab & démarche projet

17. **Comment avez-vous testé le site sans base MySQL dans certains jobs CI ?**  
    Les jobs SQL tournent réellement sur MySQL 8.0 et MariaDB 11.8 (`29/29` + `105/105`). En complément, les tests isolés (routeur, régressions UI, Playwright, WASM) utilisent le pilote JSON sur des répertoires temporaires jetables.
18. **Pourquoi distingue-t-on le « CA du jour » du « CA total » sur le tableau de bord ?**  
    Pour donner une lecture opérationnelle immédiate à l’administrateur tout en tenant compte du fuseau horaire local (`App\Tools\Heure`), alors que les dates sont stockées de manière uniforme côté serveur.
19. **Quelle est votre politique d’annulation de commande et pourquoi en 2 étapes ?**  
    Tant que la commande est `EN_PREPARATION` (avant expédition), l’annulation restaure automatiquement le stock via le workflow SQL (`sp_cancel_order` / `sp_cancel_order_admin`) tout en conservant la commande et ses lignes pour l’audit. La page intermédiaire de confirmation évite toute annulation accidentelle en un clic.
20. **Quelles seraient les prochaines étapes si le projet passait en production réelle ?**  
    Intégrer un prestataire de paiement (webhook signé + idempotence), l’envoi réel d’e-mails transactionnels (confirmation, contact), la gestion des remises/codes promo historisés et la double authentification (`MFA`) pour le rôle `SUPER`.

---

## 7. Check-list finale (J−1 et H−30 min)

### La veille (J−1)

- [ ] Dernière version fusionnée et poussée sur le **GitLab IUT** (pas uniquement sur GitHub).
- [ ] Vérifier sur GitLab IUT : rôle `Reporter` actif pour l’enseignante référente, `README.md` lisible à la racine, `git shortlog -sne` montrant bien les commits des membres du groupe.
- [ ] Vérifier que les tests rapides passent localement :
  - `bash tests/security/controles.sh` (`11/11`)
  - `python3 scripts/verifier_fiches_uc.py` (`46/46`)
  - `python3 -m unittest discover -s tests/python` (sans erreur)
- [ ] Diaporama exporté (`livrables/minishop-soutenance.pptx` ou PDF généré localement) testable hors connexion.

### 30 minutes avant la soutenance (H−30 min)

- [ ] Base locale réinitialisée proprement (`./run.sh --db`) puis serveur démarré (`./run.sh`).
- [ ] Onglet 1 prêt sur `http://localhost:8000/` (navigation privée / visiteur).
- [ ] Onglet 2 prêt sur `http://localhost:8000/admin/connexion`.
- [ ] Terminal prêt avec les requêtes SQL de preuve (`SHOW PROCEDURE STATUS`, `SHOW TRIGGERS`, `v_etat_stock`, `stock_movement_history`, `order_status_history`).
- [ ] Chrono réglé sur **15 minutes** (alerte silencieuse à 7 min et à 13 min).
