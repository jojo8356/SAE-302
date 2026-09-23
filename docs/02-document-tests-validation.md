# Document de tests et de validation — MiniShop

Deuxième document du rendu (barème iii : l'application **et** les deux documents ; pénalité « manque le document de test et de validation » : **-3 points**).
Complément du §15 du cahier des charges : dispositif, cas détaillés, exécution, registre d'anomalies, PV de recette et traçabilité.
Les tests de base sont **exécutés et rejouables** ; les tests fonctionnels constituent la recette à dérouler sur l'application (lots L8→L13).

## 1. Dispositif

| Élément | Valeur |
|---|---|
| Environnement de test | Linux, PHP 8.2, MariaDB 11.8.6 (campagne réellement jouée) / MySQL 8.0.36, Apache 2.4, Chromium |
| Jeu de données | seed `sql/01` + commandes de démo `sql/04` : 4 catégories, 12 produits, 3 clients, 1 admin, 3 commandes ; base `minishop_test` recréée à chaque campagne |
| Outillage | `scripts/run_sql_tests.sh` (29 tests), `tests/security/controles.sh` (10 contrôles statiques), `tests/charge/reserver.sh`, PHPUnit (unitaires), `docs/diagrams/render.sh`, `pandoc` |
| Entrée en recette | pré-conditions `PRÉ-1`…`PRÉ-6` du CDC §11.3 |
| Critère de recette | 0 anomalie bloquante (perte de données, stock faux, contournement de sécurité) **et** 0 anomalie majeure (fonctionnalité du sujet indisponible) ; les mineures sont listées et acceptées |
| Rejouabilité | chaque test SQL a son journal dans `tests/sql/out/<ID>.log` ; les tests de sécurité sont rejouables par simple copier-coller des requêtes/URL |

## 2. Tests de règles de gestion (base de données) — **exécutés**

Commande unique : `./scripts/run_sql_tests.sh` (manifeste `tests/sql/manifest.txt`, fixture d'état avant chaque test, journal par test).

**Résultat consigné : 29 tests / 29 conformes.** 15 de ces tests contournent volontairement l'application (`INSERT`/`UPDATE`/`DELETE` écrits à la main) : la conformité prouve que les règles ne dépendent pas du code PHP.

| Test | Règle | Vérification — attendu | Résultat |
|---|---|---|---|
| T-01 | RB-02 | prix HT = 0 refuse par le CHECK ck_produit_prix — refus par la base attendu | **conforme** |
| T-02 | RB-03 | stock negatif refuse (CHECK) en ecriture directe — refus par la base attendu | **conforme** |
| T-03 | RB-03 | stock negatif refuse par trg_produit_regles — refus par la base attendu | **conforme** |
| T-04 | RB-01 | email client duplique (contrainte unique) — refus par la base attendu | **conforme** |
| T-05 | RB-12 | sp_create_account refuse un mot de passe non hashe — refus par la base attendu | **conforme** |
| T-06 | RB-05 | quantite = 0 refusee par sp_add_order_line — refus par la base attendu | **conforme** |
| T-07 | RB-05 | quantite = 0 refusee par le CHECK ck_ligne_quantite — refus par la base attendu | **conforme** |
| T-08 | RB-18 | stock insuffisant : le trigger trg_ligne_controle_insert bloque — refus par la base attendu | **conforme** |
| T-09 | RB-18 | stock insuffisant : code de retour de la procedure — exécution sans erreur attendue | **conforme** |
| T-10 | RB-15 | montant_total non saisissable (trigger) — refus par la base attendu | **conforme** |
| T-11 | RB-06 | prix unitaire non modifiable (immutabilite) — refus par la base attendu | **conforme** |
| T-12 | RB-11 | transition EN_PREPARATION -> BROUILLON interdite — refus par la base attendu | **conforme** |
| T-13 | RB-11 | transition EXPEDIEE -> BROUILLON interdite — refus par la base attendu | **conforme** |
| T-14 | RB-14 | suppression d'une categorie non vide — refus par la base attendu | **conforme** |
| T-15 | RB-14 | suppression d'un produit deja commande — refus par la base attendu | **conforme** |
| T-16 | RB-04 | validation d'une commande sans ligne — refus par la base attendu | **conforme** |
| T-17 | RB-10 | commande rattachee a un client inexistant (trigger + FK) — refus par la base attendu | **conforme** |
| T-18 | SEC-08 | sp_confirm_order refuse l'acces a la commande d'un autre client — refus par la base attendu | **conforme** |
| T-19 | RB-03 | sp_adjust_stock refuse le passage en negatif (OUT) — exécution sans erreur attendue | **conforme** |
| T-20 | RB-09 | produit deja commande : bascule visible=0 (pas de suppression) — exécution sans erreur attendue | **conforme** |
| T-21 | RB-18 | annulation : stock restaure ligne par ligne — exécution sans erreur attendue | **conforme** |
| T-22 | RB-11 | historique des statuts ecrit automatiquement par le trigger — exécution sans erreur attendue | **conforme** |
| T-23 | RB-06 | le prix de la ligne survit a une hausse tarifaire ulterieure — exécution sans erreur attendue | **conforme** |
| T-24 | RB-04 | suppression d'une commande BROUILLON vide autorisee — exécution sans erreur attendue | **conforme** |
| T-25 | EF-VIS-02 | recherche + filtres + produits masques exclus — exécution sans erreur attendue | **conforme** |
| T-26 | EF-ADM-05 | alerte stock (RUPTURE / TRES_BAS) calculee par la procedure — exécution sans erreur attendue | **conforme** |
| T-27 | EF-VIS-01 | les 3 vues (catalogue, état de stock, commandes client) existent et sont cohérentes avec les tables (RB-19, RB-15, ENF-13) — exécution sans erreur attendue | **conforme** |

## 3. Tests fonctionnels (recette de l'application)

| ID | Titre | Préconditions | Étapes | Résultats attendus | Règles / ENF | Statut |
|---|---|---|---|---|---|---|
| F-00 | Installation de bout en bout | base vierge, `git clone`, `env.php` copié | exécuter `scripts/load_db.sh` puis `php -S 127.0.0.1:8000 -t public` | sortie : tables 8, vues 3, fonctions 1, procédures 17, triggers ≥ 5, 12 produits, 3 clients ; page d'accueil en HTTP 200 ; le tout en ≤ 15 min | ENF-13 | prêt à rejouer |
| F-01 | Liste des catégories | seed chargé | GET /categories | 4 catégories avec leur nombre de produits visibles (Objets connectés = 1 visible, Audio = 3) | RB-08, RB-19 | conçu |
| F-02 | Recherche simple | seed chargé | GET /recherche?q=casque | 1 résultat : « Casque sans fil Aura » ; le mot-clé est conservé dans l'URL ; aucune trace de SQL dans la page | RB-19, SEC-01 | conçu |
| F-03 | Filtres cumulés + tri | seed chargé | GET /catalogue?cat=2&prix_max=160&stock=1&tri=prix_asc puis copier l'URL en navigation privée | 3 produits triés 70.80 / 118.80 / 154.80 € ; l'URL collée reproduit exactement la même page | RB-19, ENF-04/05 | conçu |
| F-04 | Pagination et bornes | seed chargé | GET /catalogue?page=2, puis page=999, puis par_page=500 | page 2 vide avec lien « page 1 » ; page 999 sans erreur ; 60 lignes maximum malgré la demande de 500 | ENF-04 | conçu |
| F-05 | Fiche produit avec prix TTC | produit `CASQ-005` (129,00 € HT, TVA 20 %) | GET /produit/casque-sans-fil-aura | 154,80 € TTC avec la mention TVA incluse ; état « Disponible » ; `max=16` sur le sélecteur de quantité | RB-02, RB-16, ENF-14 | conçu |
| F-06 | Inscription valide | navigateur vierge | POST /inscription (données conformes + CGV) | compte créé, session ouverte, retour à la page d'origine ; le hash en base fait 60 caractères | RB-01, RB-12 | conçu |
| F-07 | Inscription refusée (email existant) | `alice@example.com` existe | POST /inscription avec le même email | message « Cet email est déjà utilisé », champs conservés, aucune ligne ajoutée | RB-01 | conçu |
| F-08 | Connexion : messages indifférenciés | — | POST /connexion avec (a) email inconnu (b) email connu + mauvais mot de passe | deux réponses strictement identiques (même texte, même code, durées comparables) ; aucun indice sur l'existence du compte | RB-12, SEC-04 | conçu |
| F-09 | Panier hors session | — | ajouter un produit, fermer l'onglet, rouvrir sans login | le panier survit à la navigation dans la session ; la validation renvoie vers /connexion puis revient sur le panier | ENF-07, SEC-08 | conçu |
| F-10 | Modification du profil | client connecté | POST /compte (nouveau téléphone, email inchangé) | profil à jour ; email inchangé : aucun faux refus d'unicité | RB-01 | conçu |
| F-11 | Changement de mot de passe | client connecté | POST /compte/mot-de-passe (ancien correct, nouveau fort) | nouveau hash enregistré ; l'ancien mot de passe est refusé à la connexion suivante | RB-12, SEC-04 | conçu |
| F-12 | Ajout au panier avec plafond | `MICRO-007` stock 2 | ajouter une quantité de 5 | refus « Il reste 2 exemplaire(s) », panier inchangé, bouton grisé | RB-18 | conçu |
| F-13 | JavaScript désactivé | JS coupé dans les outils du navigateur | ajouter, modifier une quantité, retirer, valider | tout le parcours fonctionne en rechargements complets ; seules les mises à jour instantanées disparaissent | ENF-07 | conçu |
| F-14 | Retrait d'une ligne | panier de 2 articles | retirer le premier, puis retirer deux fois le même | récapitulatif recalculé par le serveur ; second retrait ignoré sans erreur (idempotence) | ENF-16 | conçu |
| F-15 | Total calculé côté serveur | panier = {CASQ-005 × 2, CABLE-009 × 1} | GET /panier, puis POST /panier/quantite avec un champ prix_unitaire = 0.01 | sous-total 267,17 €, TVA 53,43 €, total 320,60 € ; le prix envoyé par le navigateur est ignoré | RB-06, RB-15, RB-16 | conçu |
| F-16 | Validation complète d'une commande | panier = {CASQ-005 × 2} | adresse → récapitulatif → confirmer | commande `CMD2026-NNNNNN`, statut `EN_PREPARATION`, montant 309,60 €, 1 ligne (2 × 154,80 €), stock 16 → 14, traces `NULL → BROUILLON → EN_PREPARATION`, panier vidé | RB-04, RB-06, RB-11, RB-15, RB-18 | conçu |
| F-17 | Prix figé après hausse tarifaire | commande F-16 passée ; l'admin passe `CASQ-005` à 199,00 € HT | rouvrir le détail de la commande côté client | la ligne affiche toujours 154,80 € ; le catalogue affiche 238,80 € ; aucune commande antérieure n'est modifiée | RB-06, RB-16 | conçu |
| F-18 | Stock insuffisant sur la 2ᵉ ligne | `MICRO-007` stock 2 ; panier = {CASQ-005 × 1, MICRO-007 × 3} | valider la commande | HTTP 409, message traduit depuis `STOCK_INSUFFISANT`, aucune commande créée, stock de `CASQ-005` inchangé (rollback intégral), panier conservé | RB-04, RB-18, ENF-16 | conçu |
| F-19 | Historique des commandes | 2 commandes dont 1 annulée | GET /mes-commandes | 2 lignes avec date, statut, montant et nombre de lignes ; la commande annulée reste visible et libellée « Annulée » | RB-10, RB-11 | conçu |
| F-20 | Détail d'une commande d'autrui | client `bruno` (id 2) connecté | GET /mes-commandes/1 (commande d'`alice`) | 404 (jamais 403), aucun montant ni nom partiel, ligne de journal `ACCESS_DENIED` | SEC-08, ENF-12 | conçu |
| F-21 | Annulation et restitution | commande `EN_PREPARATION` contenant `SAC-008` × 2 ; stock `SAC-008` = 20 | annulation avec motif | statut `ANNULEE`, lignes purgées, stock 20 → 22 exactement, trace `EN_PREPARATION → ANNULEE`, plus aucune action possible | RB-11, RB-18, RB-09 | conçu |
| F-22 | Création de produit | session admin | POST /admin/produits (prix HT 10,00 €, TVA 20 %, stock 5, catégorie Audio) | produit créé ; `prix_ttc` = 12,00 € calculé par la base ; visible immédiatement côté client | RB-02, RB-16 | conçu |
| F-23 | Masquage d'un produit commandé | produit commandé 1 fois | POST /admin/produits/{id}/suppression | disparition du catalogue client (`visible = 0`), produit conservé en base, prix payé toujours lisible dans la commande | RB-09, RB-14, RB-17 | conçu |
| F-24 | Pages statiques | — | GET /cgv, /mentions, /aide | 3 pages en 200 sans session, liens présents en pied de page | — | conçu |
| F-25 | Catégorie non vide protégée | `audio` porte 3 produits | supprimer la catégorie, réaffecter les produits, supprimer à nouveau | refus `CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS` avec lien de réaffectation ; suppression réussie une fois vide | RB-14 | conçu |
| F-26 | Réapprovisionnement | `ENCH-006` stock 30 | DELTA +15 (motif « réception fournisseur »), puis SET 0 (motif « inventaire »), puis -99999 | 30 → 45 → 0 ; le dernier mouvement est refusé et le stock reste à 0 avec message `RB03_…` | RB-03, RB-17 | conçu |
| F-27 | Alertes de stock | `POWER-010` stock 0 ; `MICRO-007` stock 2, seuil 1 | GET /admin/stocks?etat=alerte | les deux produits remontent : `RUPTURE` et `TRES_BAS` ; la formule est identique à celle de la procédure (aucune divergence) | ENF-13, RB-03 | conçu |
| F-28 | Recherche de commandes (admin) | 3 commandes de démonstration | GET /admin/commandes?statut=EXPEDIEE&debut=2026-01-01&fin=2026-12-31 | 1 commande + montant cumulé 405,48 € ; `debut > fin` → demande de correction sans erreur serveur | RB-10, RB-15 | conçu |
| F-29 | Transition de statut interdite | commande `EXPEDIEE` | POST /admin/commandes/{id}/statut avec statut=BROUILLON (hors interface) | refus `RB11_TRANSITION_STATUT_INTERDITE`, statut inchangé, aucune ligne d'historique ajoutée ; l'interface ne proposait que `LIVREE` ou `ANNULEE` | RB-11, RB-09 | conçu |
| F-30 | Piste d'audit | commande à 3 changements de statut | GET /admin/commandes/{id} | chronologie `NULL → BROUILLON → EN_PREPARATION → PAYEE` avec auteur, rôle et horodatage | RB-11 | conçu |
| F-31 | Indicateurs du back-office | données de démonstration | GET /admin | CA par statut, panier moyen, top 5 produits (résultats de `sp_revenue_report`) | — | conçu |
| F-33 | Frais de livraison affichés avant validation puis figés (`ENF-14`, `RB-20`) | panier de 1 × écran 27" (58.80 EUR TTC), client connecté | 1) ouvrir le récapitulatif ; 2) valider ; 3) relire `SELECT frais_port, montant_total FROM commande WHERE id_commande = :id` ; 4) repasser par l'UI pour tenter de modifier le port | le récapitulatif affiche **4.90 EUR** de livraison et un total de **63.70 EUR** **avant** le clic ; après validation la base porte exactement ces deux valeurs ; le port ne figure dans **aucun** champ de formulaire ; une commande à 80.00 EUR ou plus affiche « livraison offerte » et un port de 0.00 EUR | `ENF-14`, `RB-15`, `RB-20`, directive 2011/83/UE art. 5 §1 e) | conçu |
| F-32 | Séparation des rôles | `alice` (client) et un admin `GESTIONNAIRE` connectés | GET /admin en tant qu'alice ; POST /admin/equipe en tant que GESTIONNAIRE | alice redirigée sans fuite ; le `GESTIONNAIRE` reçoit 403 sur la gestion d'équipe (réservée à `SUPER`) ; aucune route publique ne crée d'administrateur | RB-13, SEC-10 | conçu |

### 3.1 Trois cas détaillés au format complet

| Champ | F-16 — Validation complète d'une commande | F-18 — Stock insuffisant sur la 2ᵉ ligne | F-21 — Annulation et restitution |
|---|---|---|---|
| Préconditions | seed chargé ; `alice@example.com` connecté ; panier = {`CASQ-005` × 2} | panier = {`CASQ-005` × 1, `MICRO-007` × 3} avec `MICRO-007` en stock 2 | commande `EN_PREPARATION` contenant `SAC-008` × 2 ; stock `SAC-008` = 20 |
| Données en entrée | `id_client` (session), adresse « 12 rue de France, 06000 Nice », jeton CSRF | idem, lignes du panier en session | `id_commande`, motif de la commande annulée |
| Étapes | 1. `/panier` → « Passer la commande » ; 2. sélectionner l'adresse ; 3. confirmer | 1. idem ; 2. confirmer | 1. `/mes-commandes/{id}` ; 2. « Annuler » ; 3. saisir le motif et confirmer |
| Résultats attendus | HTTP 200 ; commande `CMD2026-NNNNNN` en `EN_PREPARATION` ; montant 309,60 € ; 1 ligne 2 × 154,80 € ; stock 16 → 14 ; traces `NULL → BROUILLON → EN_PREPARATION` ; panier vidé | HTTP 409 ; message traduit ; `COUNT(commande)` inchangé ; stock `CASQ-005` inchangé (rollback complet) ; panier conservé pour correction | statut `ANNULEE` ; lignes purgées ; stock 20 → 22 **exactement** (un seul crédit) ; trace `EN_PREPARATION → ANNULEE` ; plus aucune action possible |
| Preuves | captures + les 3 requêtes `SELECT` de contrôle (`commande`, `ligne_commande`, `produit`, `order_status_history`) | requêtes avant/après + ligne de journal | requête de stock + historique |
| Règles vérifiées | RB-04, RB-06, RB-11, RB-15, RB-18 | RB-04, RB-18, ENF-16 | RB-09, RB-11, RB-18 |
| Statut | planifié (S10) | planifié (S10) | planifié (S10) |

## 4. Tests de sécurité (offensifs)

| ID | Cas d'attaque | Mode opératoire | Résultat attendu | Exigence |
|---|---|---|---|---|
| S-01 | Injection SQL dans la recherche | 12 payloads : apostrophe fermante suivie de OR 1=1 -- , UNION SELECT email,mot_de_passe_hash FROM client, point-virgule suivi de DROP TABLE client, guillemet double forçé, etc. | réponses 200 avec 0 résultat ou message de validation ; `SELECT COUNT(*) FROM client` vaut 3 avant et après ; aucune erreur SQL affichée ; ligne de journal `SECURITY_REJECTED` | SEC-01, SEC-02 |
| S-02 | Script stocké puis affiché | créer en admin un produit nommé `<script>alert(document.cookie)</script>`, ouvrir la fiche client puis la liste admin | le texte est affiché littéralement (source : `&lt;script&gt;`) ; aucun cookie lu ; la CSP bloque toute exécution même via un attribut | SEC-03 |
| S-03 | IDOR sur les commandes | itérer `?id=1..20` avec un compte client, puis appeler `sp_confirm_order(id_d_autrui, id_client = 2, 0)` | 404 systématique sur les commandes d'autrui ; la procédure renvoie `ACCES_NON_AUTORISE` (test `T-18`) ; aucune donnée renvoyée | SEC-08 |
| S-04 | Falsification du prix depuis le navigateur | POST /commande/valider avec un champ `prix_unitaire: 0.01` ajouté à la main | la ligne est enregistrée au vrai prix (snapshot) ; tout UPDATE ultérieur du prix d'une ligne est refusé (`RB06_…`) | SEC-10, RB-06 |
| S-05 | Vol / fixation de session | capturer le cookie avant login et le rejouer depuis un autre agent ou une autre IP | session non réutilisable telle quelle (revalidation) ; l'identifiant pré-authentification est invalidé (`session_regenerate_id`) | SEC-05 |
| S-06 | CSRF sur une action mutative | poster le formulaire depuis un autre site, sans jeton, avec `SameSite=Lax` | refus 419, aucune écriture ; panier et commande protégés ; les GET sans effet de bord restent possibles | SEC-06 |
| S-07 | Énumération et exposition | tenter `/.git/config`, `/sql/01_minishop_schema.sql`, `/var/log/application.log`, `/app/Config/env.php` ; vérifier les en-têtes | 403/404 sur tous ces chemins ; `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, CSP présente, aucune signature de serveur détaillée, aucun `phpinfo()` | SEC-11, SEC-12 |
| S-08 | Fuite d'information dans les messages | provoquer une violation de contrainte, une coupure de connexion DB, une exception PHP non gérée | pages d'erreur génériques : aucun SQL, aucun chemin, aucun nom de table ; les détails vont uniquement dans `var/log/` ; le journal ne contient jamais de mot de passe ni de hash | SEC-12, SEC-13 |
| S-09 | Force brute | 20 tentatives de connexion sur `admin@minishop.fr` avec des mots de passe de dictionnaire | temporisation après 5 échecs, message neutre, aucune différence de temps de réponse exploitable entre compte existant et inexistant | SEC-04, SEC-06 |
| S-10 | Élévation de privilège par le formulaire | ajouter la paire `role=admin` dans le POST /inscription | champ ignoré (liste stricte des clés acceptées) ; le compte reste de type client ; la table administrateur n'est jamais touchée | SEC-10, RB-13 |

Les 10 cas sont rejouables à la main (curl + navigateur). Les **10 contrôles statiques** sont bloquants en CI : `bash tests/security/controles.sh` doit afficher « 10 contrôles conformes ».

## 5. Tests de performance, de concurrence, d'installation

| ID | Scénario | Commande | Critère |
|---|---|---|---|
| P-01 | Catalogue filtré, 200 produits (volumétrie client retenue) | `bash tests/perf/mesurer.sh '/catalogue?cat=1&prix_max=500'` | p95 < 500 ms, ≤ 5 requêtes (`ENF-01`) |
| P-02 | Recherche « casque » | `bash tests/perf/mesurer.sh '/recherche?q=casque'` | p95 < 500 ms ; `EXPLAIN` utilisant `idx_produit_visible` ou l'index `FULLTEXT` (`ENF-02`) |
| P-03 | Validation de commande | `bash tests/perf/mesurer.sh '/commande/valider'` (POST) | p95 < 900 ms avec verrous |
| **P-06** | **Performances côté base, sur le jeu de volumétrie** | `sudo bash scripts/capture_perf.sh` (ou `./scripts/gen_volumes.sh` puis `DB=minishop_perf ./tests/perf/mesurer_sql.sh 50`) — **verrouillé à chaque `bash scripts/build-docs.sh`** | **exécuté** (21/09/2026, jeu 212 produits / 992 commandes, **31 ms** commande complète le 21/09 à 12:19) : 15–18 ms de moyenne sur les 5 requêtes types, `EXPLAIN` sans `ALL` sur catalogue, recherche et « mes commandes » ; un `ALL` **documenté** sur l'agrégation par statut (anomalie A-08, non bloquante, §7) ; sortie consignée dans `tests/perf/dernieres_mesures.txt` (à joindre en soutenance) |
| C-01 | Tentative de survente | `bash tests/charge/reserver.sh 20 12` | 12 commandes réussies, 8 refus `STOCK_INSUFFISANT`, stock final 0, aucune valeur négative (`RB-18`) |
| C-02 | Rejeu du POST de commande | rejouer le même `POST /commande/valider` | une seule commande ; second envoi refusé (`SEC-06`, cas E2 d'UC-07) |
| I-01 | Installation | machine propre, suivi du `README.md` seul | ≤ 15 min puis `run_sql_tests.sh` **29/29** (`ENF-13`) |
| B-01 | Sauvegarde / restauration | `mysqldump --single-transaction --routines --triggers minishop > b.sql`, restauration dans une base vide, tests rejoués | **29/29** : prouve que la fonction, les 17 procédures **et** les 16 triggers survivent à la restauration (`ENF-15`) |

## 6. Tests unitaires PHP (lot L8→L12)

| Classe | Cas testés | Attentes |
|---|---|---|
| `Produit::estDisponible()` | quantités 0, 1, stock, stock+1, stock négatif | refus si `qte < 1` ou `qte > stock` |
| `Produit::prixTTCCalcule()` | 129,00 € @ 20 % ; 10,00 € @ 5,5 % | 154,80 € et 10,55 € (arrondi 2 décimales, aucune dérive de virgule flottante) |
| `PanierSession` | ajout, fusion du même produit, retrait, plafond | total recalculé à chaque écriture ; aucun prix mémorisé côté client |
| `Commande::statutSuivantAutorise()` | les 36 couples de statuts | exactement la matrice `RB-11` (9 transitions autorisées, les autres refusées) |
| `Hasher` | hachage, vérification, re-hash | `password_verify()` vrai pour le bon mot de passe, faux sinon ; refus d'un hash de moins de 60 caractères |
| `e()` | `<script>`, apostrophes, guillemets, UTF-8 | sortie `htmlspecialchars` avec `ENT_QUOTES` ; aucun HTML actif |
| `SearchQuery` | tri inconnu, `par_page = 500`, `prix_min > prix_max` | repli sur la liste blanche, borne 60, filtres ignorés sans exception |

## 7. Registre des anomalies (issues réelles rencontrées lors de la conception de la base)

| # | Constat | Gravité | Cause racine | Correction | Verrou de non-régression |
|---|---|---|---|---|---|
| A-01 | un UPDATE du prix d'une ligne était « réparé » au lieu d'être rejeté | majeure | ordre de création des triggers (le snapshot était créé après l'immutabilité) | ordre corrigé dans `sql/03_minishop_triggers.sql` + commentaire explicatif | `T-11` |
| A-02 | doublons dans `ORDER_STATUS_HISTORY` | mineure | écriture en procédure **et** en trigger | écriture laissée au seul trigger | `T-22` (assertion d'absence de doublon) |
| A-03 | stock restitué deux fois après annulation | **bloquante** (données fausses) | `UPDATE produit` dans la procédure + trigger `AFTER DELETE` sur les lignes | restitution déléguée au trigger ; la procédure ne fait plus que purge et statut | `T-21` (stock exact, un seul crédit) |
| A-04 | `SELECT … INTO NEW.prix_unitaire` refusé dans un trigger | bloquante (installation impossible) | syntaxe non permise par MySQL/MariaDB | variable locale puis `SET NEW.prix_unitaire = v` | `scripts/load_db.sh` |
| A-05 | erreur « table déjà utilisée par la requête appelante » lors d'une purge de lignes | bloquante (tests non rejouables) | `DELETE` dont la sous-requête lisait `PRODUIT` alors qu'un trigger écrivant `PRODUIT` se déclenchait | purge via table temporaire dans la fixture de test | harnais + `tests/sql/fixture.sql` |
| A-06 | `LIMIT :lim` dans une requête préparée de procédure : erreur de syntaxe | bloquante | marqueurs nommés non supportés en `PREPARE` | réécriture en marqueurs `?` avec nombre de paramètres constant | `T-25` |
| A-07 | `v_catalogue` renvoyait `ERROR 1356` **dans la base de test seulement** | bloquante (campagne de tests faussée) | le test `T-26` faisait `CREATE OR REPLACE VIEW v_etat_stock` à son profit et cassait la vue livrée dont dépend `v_catalogue` ; de plus `v_etat_stock` n'exposait pas `id_produit`, indispensable à la jointure | un test **ne redéfinit jamais** un objet de production ; `v_etat_stock` expose `id_produit` ; la vue livrée est **testée** telle quelle par `T-27` (existence, cohérence des comptages, formule `montant = lignes + port`) | `T-27` (5 assertions) |
| A-08 | l'agrégation « commandes par statut » de `sp_revenue_report` est en `ALL` (992 lignes) malgré `idx_commande_statut` | mineure (17 ms mesurée, sous le seuil `ENF-01`) | `GROUP BY` + `AVG(montant_total)` : l'index sur `statut` seul oblige à relire la table | **constaté et mesuré, non corrigé** : un index de couverture `(statut, montant_total)` ferait passer le plan en `range … Using index` ; ajourné car la volumétrie retenue (1 000 commandes/an) ne le justifie pas — Evolution E-04 | `tests/perf/mesurer_sql.sh`, P-06 |

## 8. Procès-verbal de recette

| Zone | Vérifications | Conformes | Bloquantes | Majeures | Statut |
|---|---|---|---|---|---|
| Base de données (règles de gestion, vues, frais de port, atomicité) | 29 | **29** | 0 | 0 | **validé** (campagne réelle du 21/09/2026, `tests/sql/rapport_tests_sql.md`) |
| Chargement / installation | 2 (`F-00`, `I-01`) | à exécuter | — | — | planifié |
| Front-office visiteur | 9 (`F-01`…`F-09`) | à exécuter | — | — | planifié |
| Client (compte, panier, commande) | 12 (`F-10`…`F-21`) | à exécuter | — | — | planifié |
| Back-office + conformité | 12 (`F-22`…`F-33`) | à exécuter | — | — | planifié |
| Sécurité | 10 + 10 contrôles | à exécuter | — | — | planifié |
| Performance / concurrence | 6 (`P-01`…`P-03`, `P-06`, `C-01`, `C-02`) | **1 conforme** (`P-06`, mesure base) | 0 | 0 | partiellement exécuté (le HTTP attend l'application) |
| **Total** | **71 vérifications** | | | | |

Signature de l'équipe, date : ………………… Validation du maître d'ouvrage pédagogique (soutenance), date : …………………

## 9. Matrice de traçabilité exigence → modèle → base → application → test

| Exigence | Élément de modèle | Mécanisme de base | Point d'application PHP/IHM | Tests |
|---|---|---|---|---|
| EF-VIS-01/03/05 | `Categorie`, `Produit` | `sp_search_products`, index `idx_produit_visible`, vue `v_catalogue` | CatalogueController + `app/View/catalogue.php` | F-01, F-03, F-04, T-25 |
| EF-VIS-02 | `Produit` | `sp_search_products` (marqueurs `?`) | recherche + liste blanche de tri | F-02, S-01, T-25 |
| EF-VIS-04 | `Produit` | `trg_produit_ttc` | ProduitController + fiche | F-05, séquence §5.3.5 |
| EF-VIS-06/07 | `Client` | `sp_create_account`, `sp_get_credentials` | AuthController, `Auth`, `Hasher` | F-06, F-07, F-08, T-04, T-05 |
| EF-CLI-01/02 | `Client` | `sp_update_client` | CompteController | F-10, F-11 |
| EF-CLI-03…06 | `PanierSession` | `sp_search_products` (plafond de disponibilité) | PanierController + `public/js/panier.js` | F-12, F-13, F-14, F-15 |
| EF-CLI-07 | `Commande`, `LigneCommande` | `sp_create_order_from_basket`, `sp_add_order_line`, triggers A1/A2/B2/D1/D4 | CommandeController + `CommandeRepository` | F-16, F-17, F-18, T-06…T-11, T-16 |
| EF-CLI-08/09 | `Commande` | `trg_history_statut` (détail et audit) | CommandeController (filtre `id_client`) | F-19, F-20, S-03, T-18 |
| EF-CLI-10 | `Commande` | `sp_cancel_order` + `TRG-A3` (restitution) | bouton d'annulation + confirmation | F-21, T-21 |
| EF-ADM-01/02 | `Produit` | `sp_save_product`, `sp_delete_product`, triggers B3–B6 | AdminController (produits) | F-22, F-23, T-15, T-20 |
| EF-ADM-03 | `Categorie` | `sp_save_category`, `sp_delete_category`, triggers C1/C2 | AdminController (catégories) | F-25, T-14 |
| EF-ADM-04/05 | `Produit.stock` | `sp_adjust_stock`, `v_etat_stock` | AdminController (stocks) | F-26, F-27, T-19, T-26, T-27 |
| EF-ADM-06/08/09 | `Commande` | `sp_revenue_report`, vue `v_commandes_client` | AdminController (commandes) | F-28, F-30, F-31 |
| EF-ADM-07 | `Commande.statut` | `sp_update_order_status`, triggers D1/D4 | formulaire de changement de statut | F-29, T-12, T-13 |
| SEC-01…SEC-13 | — | CHECK / FK / triggers / privilèges limités | PDO préparé, `e()`, `Auth`, CSRF, journaux | S-01…S-10 + 10 contrôles CI |
| ENF-01/02 | — | index, `FULLTEXT`, `LIMIT` borné, `scripts/gen_volumes.sh` | — | P-01, P-02, **P-06** (exécuté) + `EXPLAIN` |
| ENF-15 | — | `mysqldump --routines --triggers` | scripts | B-01 |
| ENF-16 | — | transactions dans les procédures | `rollBack()` du repository | F-18, T-09 |

Couverture : **100 %** des exigences du §4 (les 22 libellés du sujet repris à l'identique) disposent d'un élément de modèle, d'un mécanisme côté SGBD, d'un point d'application et d'au moins un test ; aucune règle `RB-*` n'est dépourvue de mécanisme côté base (cf. §6.2 du CDC).

## 10. Conclusion provisoire (à valider en fin de lot L14)

- la partie **base de données** est testée, rejouable et sans anomalie connue : les règles `RB-01` à `RB-18` tiennent même quand l'application est court-circuitée ;
- la partie **application** doit être reçue avec les 34 tests fonctionnels, les 10 tests de sécurité, les 5 tests de performance/concurrence et les 2 tests d'installation ;
- le document est tenu à jour à chaque lot (le PV de recette est la seule partie laissée à compléter, volontairement, tant que les lots L8→L13 ne sont pas exécutés).
