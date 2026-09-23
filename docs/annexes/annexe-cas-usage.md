# Annexe 17.1 — Catalogue détaillé des 14 cas d'utilisation minimum

Complément du §5.1 du cahier des charges. Les trois cas détaillés par **description textuelle**
(exigence du sujet) sont UC-07, UC-02 et UC-14, développés au §5.1.5 du CDC ; les 11 autres sont
formalisés ici en fiche pour que la couverture des 14 cas demandés soit vérifiable ligne à ligne.

## UC-01 — Consulter le catalogue

| Champ | Contenu |
|---|---|
| But | Parcourir les produits du front-office, catégorie par catégorie ou page par page. |
| Acteur principal | Visiteur |
| Acteurs secondaires | MySQL (`sp_search_products`) |
| Données en entrée | `page`, `cat`, `tri` (optionnels) |
| Préconditions | aucune (navigation publique, aucune session requise) |
| Scénario nominal | 1. le visiteur ouvre `/catalogue` ; 2. le contrôleur normalise les filtres ; 3. `CALL sp_search_products(NULL, …, p_admin = 0)` ; 4. la page de 12 produits s'affiche (prix TTC, disponibilité, catégorie) ; 5. le visiteur change de page ou de catégorie. |
| Scénarios alternatifs | A1 pas de page suivante → lien « première page » ; A2 catégorie vide → « Aucun produit dans cette catégorie » (jamais masquée) ; A3 produit masqué → jamais listé (`RB-19`). |
| Scénarios d'exception | E1 base indisponible → page 503 générique, aucune trace technique affichée. |
| Postconditions | consultation possible et reproductible (URL partageable) |
| Effets sur le système | aucune écriture |
| Règles métier appliquées | `RB-19` |
| Exigences couvertes | `EF-VIS-01/03/05` |
| Tests de recette | F-01, F-03, F-04 |

## UC-02 — Rechercher un produit

| Champ | Contenu |
|---|---|
| But | Trouver des produits par mot(s)-clé(s) sur le nom, la description et la référence. |
| Acteur principal | Visiteur |
| Acteurs secondaires | MySQL (`sp_search_products`) |
| Données en entrée | `q` (1 à 120 caractères) + filtres cumulables |
| Préconditions | texte non vide après trim (sinon équivaut à UC-01) |
| Scénario nominal | 1. saisie ; 2. validation serveur du contenu de la requête (longueur, type) ; 3. `CALL sp_search_products(q, …)` en paramètres liés ; 4. liste paginée + `total_trouves` (2ᵉ jeu de résultats du même appel) ; 5. filtres reportés dans l'URL. |
| Scénarios alternatifs | A1 aucun résultat → message neutre + suggestions de catégories ; A2 tri inconnu → repli sur `nom` + journalisation ; A3 filtres incohérents (`prix_min > prix_max`) ; A4 recherche vide → UC-01 ; A5 page hors bornes → page vide + lien utile. |
| Scénarios d'exception | E1 base indisponible → 503 ; E2 sur-charge évitée par la borne de page (60 max). |
| Postconditions | aucun effet de bord (GET idempotent) |
| Effets sur le système | aucune écriture |
| Règles métier appliquées | `RB-19`, `SEC-01`, `SEC-02`, `ENF-04/05` |
| Exigences couvertes | `EF-VIS-02` |
| Tests de recette | F-02, S-01, T-25 |

## UC-03 — Consulter la fiche produit

| Champ | Contenu |
|---|---|
| But | Afficher le détail complet d'un produit : prix TTC, TVA, disponibilité, catégorie, description. |
| Acteur principal | Visiteur |
| Acteurs secondaires | MySQL |
| Données en entrée | `slug` (ou `id_produit`) |
| Préconditions | produit existant et `visible = 1` |
| Scénario nominal | 1. clic sur un produit ; 2. `SELECT` avec `WHERE slug = ? AND visible = 1` ; 3. calcul de l'état de stock ; 4. rendu de la fiche ; 5. sélecteur de quantité borné (`max = stock`). |
| Scénarios alternatifs | A1 produit masqué → 404 générique (pas de liste des produits retirés) ; A2 rupture → bouton désactivé + message ; A3 prix modifié après affichage du catalogue → le prix de la fiche fait foi. |
| Scénarios d'exception | E1 produit supprimé entre-temps → 404 au rechargement. |
| Postconditions | information de prix opposable (`ENF-14`) |
| Effets sur le système | aucune écriture |
| Règles métier appliquées | `RB-02`, `RB-16`, `RB-19` |
| Exigences couvertes | `EF-VIS-04` |
| Tests de recette | F-05 |

## UC-04 — Créer un compte

| Champ | Contenu |
|---|---|
| But | Ouvrir un compte client pour pouvoir commander. |
| Acteur principal | Visiteur |
| Acteurs secondaires | MySQL (`sp_create_account`) |
| Données en entrée | nom, prénom, email, mot de passe × 2, acceptation des CGV |
| Préconditions | email valide et inédit ; mot de passe conforme à la politique ; CGV acceptées |
| Scénario nominal | 1. saisie et validation live (JS) ; 2. revalidation serveur complète ; 3. `password_hash()` (bcrypt cost 12) ; 4. `CALL sp_create_account(…)` ; 5. ouverture de session + retour à la page d'origine ; 6. panier éventuellement créé avant l'inscription conservé. |
| Scénarios alternatifs | A1 `EMAIL_DEJA_UTILISE` → message et champs conservés ; A2 mot de passe trop faible → politique affichée ; A3 CGV non acceptées → refus. |
| Scénarios d'exception | E1 doublon arrivé entre le contrôle et l'insertion → la contrainte `UNIQUE` refuse, message identique, aucune donnée partielle ; E2 rejeu du formulaire → même chemin (idempotence par unicité de l'email). |
| Postconditions | compte créé et connecté |
| Effets sur le système | une ligne dans `CLIENT` (hash ≥ 60 caractères), session ouverte |
| Règles métier appliquées | `RB-01`, `RB-12`, `RB-13` |
| Exigences couvertes | `EF-VIS-06` |
| Tests de recette | F-06, F-07, T-04, T-05 |

## UC-05 — Se connecter / se déconnecter, gérer son compte

| Champ | Contenu |
|---|---|
| But | Ouvrir une session fiable, modifier ses informations et son mot de passe, fermer proprement la session. |
| Acteur principal | Visiteur, Client |
| Acteurs secondaires | MySQL (`sp_get_credentials`, `sp_update_client`) |
| Données en entrée | email + mot de passe ; puis champs du profil |
| Préconditions | compte existant et actif |
| Scénario nominal | 1. `POST /connexion` avec jeton CSRF ; 2. `CALL sp_get_credentials(?)` ; 3. `password_verify()` ; 4. `session_regenerate_id(true)` + `$_SESSION['user_id']` ; 5. redirection vers la page demandée avant la connexion. |
| Scénarios alternatifs | A1 compte bloqué (`COMPTE_BLOQUE`) ; A2 email inconnu **ou** mot de passe erroné → message strictement identique ; A3 changement d'email en conflit → refus ; A4 session expirée → reconnexion puis reprise. |
| Scénarios d'exception | E1 5 échecs en 15 min → temporisation du formulaire ; E2 vol de cookie (IP/agent différents) → revalidation. |
| Postconditions | session authentifiée et durcie |
| Effets sur le système | `derniere_connexion`, champs du profil, destruction de session à la déconnexion |
| Règles métier appliquées | `RB-01`, `RB-12`, `SEC-04/05/06` |
| Exigences couvertes | `EF-VIS-07`, `EF-CLI-01/02` |
| Tests de recette | F-08, F-10, F-11, S-05, S-06 |

## UC-06 — Gérer le panier

| Champ | Contenu |
|---|---|
| But | Ajouter, modifier les quantités, retirer des produits et consulter un récapitulatif calculé par le serveur. |
| Acteur principal | Client (ou Visiteur hors session) |
| Acteurs secondaires | MySQL (`sp_search_products` pour le plafond de disponibilité) |
| Données en entrée | `id_produit`, `quantite` |
| Préconditions | produit visible ; au moins un article pour valider |
| Scénario nominal | 1. action `POST /panier/ajouter`, `POST /panier/quantite`, `POST /panier/retirer` ; 2. contrôle serveur (entier, produit visible, disponibilité) ; 3. écriture dans `PanierSession` ; 4. récapitulatif HT/TVA/TTC rendu par le serveur ; 5. mise à jour du badge en AJAX. |
| Scénarios alternatifs | A1 `STOCK_INSUFFISANT` → message « il reste N exemplaire(s) », bouton grisé ; A2 produit devenu invisible → retrait signalé ; A3 quantité 0 → retrait explicite (jamais implicite). |
| Scénarios d'exception | E1 panier corrompu en session → régénération depuis la base ; E2 désactivation de JavaScript → le formulaire classique fonctionne toujours (`ENF-07`). |
| Postconditions | panier cohérent avec la base |
| Effets sur le système | état de session uniquement (aucune écriture en base dans l'option par défaut) |
| Règles métier appliquées | `RB-05`, `RB-15`, `RB-18`, `RB-19` |
| Exigences couvertes | `EF-CLI-03…06`, `EF-VIS-09` |
| Tests de recette | F-09, F-12, F-13, F-14, F-15 |

## UC-07 — Passer une commande

| Champ | Contenu |
|---|---|
| But | Transformer le panier en commande enregistrée, numérotée, à prix figés, avec réservation du stock. |
| Acteur principal | Client |
| Acteurs secondaires | MySQL (`sp_create_order_from_basket`, triggers de stock, snapshot et historique) |
| Données en entrée | adresse de livraison sélectionnée ou saisie, jeton CSRF |
| Préconditions | authentifié ; panier ≥ 1 article ; stock suffisant ; jeton CSRF valide |
| Scénario nominal | 11 étapes, **détaillées au §5.1.5.1 du CDC** (scénario nominal complet, avec les 11 étapes attendues par le sujet). |
| Scénarios alternatifs | A1 stock insuffisant → rollback intégral, panier conservé ; A2 produit supprimé entre l'ajout au panier et la validation ; A3 panier vide ; A4 client non authentifié ; A5 adresse manquante. |
| Scénarios d'exception | E1 erreur de création → rollback, journal, aucun débit de stock ; E2 double clic → jeton consommé / `COMMANDE_DEJA_VALIDÉE` ; E3 concurrence sur la dernière unité → verrou `FOR UPDATE`. |
| Postconditions | commande enregistrée et consultable par son propriétaire et l'administration |
| Effets sur le système | `COMMANDE` + `LIGNE_COMMANDE`, `PRODUIT.stock` décrémenté, `ORDER_STATUS_HISTORY`, panier vidé |
| Règles métier appliquées | `RB-04`, `RB-05`, `RB-06`, `RB-10`, `RB-11`, `RB-15`, `RB-16`, `RB-18` |
| Exigences couvertes | `EF-CLI-07` |
| Tests de recette | F-16, F-17, F-18, séquence 5.3.4 |

## UC-08 — Consulter l'historique des commandes

| Champ | Contenu |
|---|---|
| But | Relancer une commande, consulter les prix payés et le suivi. |
| Acteur principal | Client |
| Acteurs secondaires | MySQL |
| Données en entrée | aucune (liste) ; `id_commande` (détail) |
| Préconditions | authentifié |
| Scénario nominal | 1. `GET /mes-commandes` ; 2. `SELECT … WHERE id_client = ?` (id de session) ; 3. liste (date, statut, montant, nombre de lignes) ; 4. clic → détail : lignes avec prix figés + chronologie des statuts. |
| Scénarios alternatifs | A1 aucun historique → message + lien catalogue ; A2 identifiant d'un autre client dans l'URL → 404. |
| Scénarios d'exception | E1 tentative d'IDOR → 404 + journal (`SEC-13`) ; aucune donnée renvoyée. |
| Postconditions | visibilité strictement limitée à son périmètre |
| Effets sur le système | aucune écriture |
| Règles métier appliquées | `RB-06`, `RB-08`, `RB-10`, `SEC-08` |
| Exigences couvertes | `EF-CLI-08/09` |
| Tests de recette | F-19, F-20, S-03 |

## UC-09 — Annuler une commande

| Champ | Contenu |
|---|---|
| But | Demander l'annulation d'une commande non expédiée, avec restitution du stock. |
| Acteur principal | Client |
| Acteurs secondaires | MySQL (`sp_cancel_order`, `trg_ligne_restore_stock`) |
| Données en entrée | `id_commande`, motif |
| Préconditions | commande appartenant au client ; statut `BROUILLON`, `EN_PREPARATION` ou `PAYEE` |
| Scénario nominal | 1. bouton « Annuler » depuis le détail ; 2. confirmation + motif ; 3. `CALL sp_cancel_order(id, client)` ; 4. purge des lignes → restitution du stock par trigger ; 5. statut `ANNULEE` + trace d'historique ; 6. message de confirmation. |
| Scénarios alternatifs | A1 commande expédiée/livrée → `COMMANDE_NON_ANNULABLE` ; A2 commande d'un autre client → `ACCES_NON_AUTORISE` ; A3 annulation déjà effectuée → idempotence (`STATUT_DEJA_A_JOUR`). |
| Scénarios d'exception | E1 annulation simultanée côté admin → transaction sérialisée par `FOR UPDATE`, le second appel ne double pas la restitution. |
| Postconditions | commande annulée, stock juste, historique complet |
| Effets sur le système | statut `ANNULEE`, lignes purgées, stock restauré exactement une fois |
| Règles métier appliquées | `RB-09`, `RB-11`, `RB-18` |
| Exigences couvertes | `EF-CLI-10` |
| Tests de recette | F-21, T-21 |

## UC-10 — Gérer les produits

| Champ | Contenu |
|---|---|
| But | Créer, modifier, masquer ou supprimer un produit sans casser le prix payé ni le stock. |
| Acteur principal | Administrateur |
| Acteurs secondaires | MySQL (`sp_save_product`, `sp_delete_product`, triggers) |
| Données en entrée | référence, nom, slug, description, prix HT, TVA, stock initial, seuil, catégorie, visibilité, image |
| Préconditions | session administrateur ; catégorie existante ; référence et slug uniques |
| Scénario nominal | 1. liste paginée avec recherche (`p_admin = 1`) ; 2. formulaire ; 3. validation serveur ; 4. `CALL sp_save_product(…)` ; 5. `prix_ttc` recalculé par trigger ; 6. retour sur la liste. |
| Scénarios alternatifs | A1 `REFERENCE_DEJA_UTILISEE` / `SLUG_DEJA_UTILISE` ; A2 `RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF` ; A3 produit déjà commandé → masquage `visible = 0` au lieu de la suppression. |
| Scénarios d'exception | E1 tentative de `DELETE` direct → `PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER` (trigger) ; E2 catégorie inexistante → refus. |
| Postconditions | catalogue à jour sans perte d'historique |
| Effets sur le système | création ou modification de `PRODUIT`, date de modification, cohérence TTC |
| Règles métier appliquées | `RB-02`, `RB-03`, `RB-07`, `RB-09`, `RB-14`, `RB-16`, `RB-17` |
| Exigences couvertes | `EF-ADM-01/02` |
| Tests de recette | F-22, F-23, T-15, T-20 |

## UC-11 — Gérer les catégories

| Champ | Contenu |
|---|---|
| But | Organiser le catalogue en rayons, sans créer d'orphelins. |
| Acteur principal | Administrateur |
| Acteurs secondaires | MySQL (`sp_save_category`, `sp_delete_category`) |
| Données en entrée | nom, slug, description |
| Préconditions | session administrateur ; nom unique ; suppression seulement si catégorie vide |
| Scénario nominal | 1. liste avec nombre de produits visibles ; 2. création/renommage via `sp_save_category` ; 3. suppression via `sp_delete_category`. |
| Scénarios alternatifs | A1 catégorie non vide → `CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS` + lien vers le filtrage des produits ; A2 nom dupliqué → refus. |
| Scénarios d'exception | E1 suppression en cascade tentée → refus FK `RESTRICT` + trigger. |
| Postconditions | classement cohérent avec `RB-07`/`RB-08` |
| Effets sur le système | ligne `CATEGORIE` |
| Règles métier appliquées | `RB-07`, `RB-08`, `RB-14` |
| Exigences couvertes | `EF-ADM-03` |
| Tests de recette | F-25, T-14 |

## UC-12 — Gérer les stocks

| Champ | Contenu |
|---|---|
| But | Réapprovisionner, corriger un inventaire, sortir du stock, sans jamais passer en négatif. |
| Acteur principal | Administrateur |
| Acteurs secondaires | MySQL (`sp_adjust_stock`, `trg_produit_regles`) |
| Données en entrée | `id_produit`, mode (`SET`/`DELTA`), quantité, motif |
| Préconditions | session administrateur ; produit existant ; mouvement justifié |
| Scénario nominal | 1. liste filtrée (ruptures, seuils) ; 2. saisie du mouvement ; 3. `CALL sp_adjust_stock(…)` avec verrou de ligne ; 4. nouveau stock et message de confirmation. |
| Scénarios alternatifs | A1 mouvement menant à un stock négatif → `RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF`, stock inchangé ; A2 produit inexistant → 404. |
| Scénarios d'exception | E1 commande validée entre-temps qui épuise le stock → verrou puis second contrôle du trigger ; aucune valeur incohérente. |
| Postconditions | stock physiquement juste |
| Effets sur le système | `PRODUIT.stock`, motif tracé dans le commentaire de l'action |
| Règles métier appliquées | `RB-03`, `RB-17`, `RB-18` |
| Exigences couvertes | `EF-ADM-04/05` |
| Tests de recette | F-26, F-27, T-19, T-26, T-27 |

## UC-13 — Gérer les commandes (back-office)

| Champ | Contenu |
|---|---|
| But | Suivre l'activité : lister, filtrer, consulter le détail et la piste d'audit, suivre les indicateurs. |
| Acteur principal | Administrateur |
| Acteurs secondaires | MySQL (`sp_revenue_report`, `sp_update_order_status`) |
| Données en entrée | filtres : statut, client, période, montant |
| Préconditions | rôle `GESTIONNAIRE` ou `SUPER` |
| Scénario nominal | 1. filtres appliqués (requête paramétrée) ; 2. liste paginée + montant cumulé ; 3. détail d'une commande : lignes, prix payés, historique des statuts ; 4. indicateurs via `sp_revenue_report`. |
| Scénarios alternatifs | A1 aucune commande sur la période → message + élargissement proposé ; A2 période incohérente → demande de correction. |
| Scénarios d'exception | E1 consultation d'un identifiant inexistant → liste vide, aucune erreur. |
| Postconditions | visibilité complète et tracée |
| Effets sur le système | journalisation des consultations sensibles (`SEC-13`) |
| Règles métier appliquées | `RB-10`, `RB-11`, `RB-15` |
| Exigences couvertes | `EF-ADM-06/08/09` |
| Tests de recette | F-28, F-30, F-31 |

## UC-14 — Modifier le statut d'une commande

| Champ | Contenu |
|---|---|
| But | Faire avancer une commande dans son cycle de vie selon la matrice `RB-11`, en conservant la trace de chaque changement. |
| Acteur principal | Administrateur |
| Acteurs secondaires | MySQL (`sp_update_order_status`, `trg_history_statut`, `trg_commande_transition_statut`) |
| Données en entrée | `id_commande`, statut cible, commentaire |
| Préconditions | commande existante et non `BROUILLON` ; transition autorisée ; commentaire obligatoire pour une annulation |
| Scénario nominal | 8 étapes, **détaillées au §5.1.5.3 du CDC**. |
| Scénarios alternatifs | A1 `RB11_TRANSITION_STATUT_INTERDITE` (aucune écriture, aucune trace) ; A2 `STATUT_DEJA_A_JOUR` ; A3 commande en `BROUILLON` → `COMMANDE_NON_TRAITABLE` ; A4 annulation → restitution de stock éventuelle. |
| Scénarios d'exception | E1 commande supprimée entre-temps → `COMMANDE_INTROUVABLE` + rafraîchissement ; E2 `UPDATE` manuel en base → refusé ou tracé quand même par les triggers. |
| Postconditions | cycle de vie maîtrisé et auditable |
| Effets sur le système | `statut`, `commentaire`, `date_modification`, une ligne dans `ORDER_STATUS_HISTORY` |
| Règles métier appliquées | `RB-09`, `RB-10`, `RB-11`, `RB-13`, `RB-15` |
| Exigences couvertes | `EF-ADM-07` |
| Tests de recette | F-29, T-10, T-12, T-13, T-22 |

