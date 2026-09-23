-- =====================================================================
-- MiniShop — 02 : procédures stockées (17 procédures + 1 fonction, minimum demandé : 5)
-- ---------------------------------------------------------------------
-- Conventions :
--   * Toute erreur métier est remontée par  SIGNAL SQLSTATE '45000'
--     avec un MESSAGE_TEXT court, stable, préfixé par une clé (CODE_RAISON)
--     que l'application traduit en message utilisateur.
--   * Les procédures qui écrivent plusieurs lignes sont transactionnelles
--     (START TRANSACTION / COMMIT / ROLLBACK) : c'est l'un des arguments
--     pédagogiques majeurs du choix « procédure stockée plutôt que PHP ».
--   * Les procédures ne renvoient JAMAIS le hash de mot de passe à l'IHM :
--     la couche modèle le compare avec password_verify() (RB-12).
--
-- Chargement :  mysql -u root -p minishop < sql/02_minishop_procedures.sql
-- =====================================================================

-- Nom de la base : passe au client (`mariadb minishop < fichier`) par
-- scripts/load_db.sh ; en mode interactif, taper `USE minishop;` d'abord.

-- ---------------------------------------------------------------------
-- 1. sp_create_account — création d'un compte client (RB-01 : email unique)
--    Retourne id_client ; p_code_retour = 'EMAIL_DEJA_UTILISE' si doublon.
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- 0. Lecteur unique des parametres metier (RB-15, ENF-14) : une fonction,
--    pas une constante recopiee dans chaque procedural et dans le PHP.
--    CREATE FUNCTION exige SUPER sur certains SGBD : si la fonction ne peut
--    pas etre creee, sp_compute_shipping utilise la valeur par defaut documentee.
-- ---------------------------------------------------------------------
DELIMITER $$

DROP FUNCTION IF EXISTS fn_param $$
CREATE FUNCTION fn_param (p_cle VARCHAR(50), p_defaut VARCHAR(100))
RETURNS VARCHAR(100)
DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE v VARCHAR(100);
  SELECT valeur INTO v FROM parametre WHERE cle = p_cle LIMIT 1;
  RETURN IFNULL(v, p_defaut);
END $$

-- ---------------------------------------------------------------------
-- 0 bis. sp_compute_shipping — regle de frais de port (ENF-14, art. L221-5
--        du code de la consommation et art. 5(1)(e) de la directive 2011/83/UE :
--        le port doit etre affiche avant la validation, donc calcule AVANT).
--        Regle : 4,90 EUR TTC en standard, offert a partir de 80,00 EUR.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_compute_shipping $$
CREATE PROCEDURE sp_compute_shipping (
  IN  p_marchandises DECIMAL(10,2),
  OUT p_frais_port   DECIMAL(10,2),
  OUT p_motif        VARCHAR(60)
)
BEGIN
  DECLARE v_port    DECIMAL(10,2);
  DECLARE v_franchise DECIMAL(10,2);
  SET v_port     = CAST(fn_param('frais_port', '4.90') AS DECIMAL(10,2));
  SET v_franchise = CAST(fn_param('franchise_port', '80.00') AS DECIMAL(10,2));
  IF p_marchandises >= v_franchise THEN
    SET p_frais_port = 0.00;
    SET p_motif = CONCAT('PORT_OFFERT_A_PARTIR_DE_', v_franchise);
  ELSE
    SET p_frais_port = v_port;
    SET p_motif = 'LIVRAISON_STANDARD';
  END IF;
END $$

DROP PROCEDURE IF EXISTS sp_create_account $$
CREATE PROCEDURE sp_create_account (
  IN  p_nom         VARCHAR(100),
  IN  p_prenom      VARCHAR(100),
  IN  p_email       VARCHAR(190),
  IN  p_hash        CHAR(60),
  OUT p_id_client   INT UNSIGNED,
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  IF p_nom IS NULL OR p_prenom IS NULL OR TRIM(p_nom) = '' OR TRIM(p_prenom) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CHAMPS_OBLIGATOIRES';
  END IF;
  IF p_hash IS NULL OR CHAR_LENGTH(p_hash) < 60 THEN
    -- garde-fou : on refuse une chaîne qui ne ressemble pas à une sortie de password_hash()
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HASH_MOT_DE_PASSE_INVALIDE';
  END IF;

  SET p_id_client = NULL;
  IF EXISTS (SELECT 1 FROM client WHERE email = p_email) THEN
    SET p_code_retour = 'EMAIL_DEJA_UTILISE';
  ELSE
    INSERT INTO client (nom, prenom, email, mot_de_passe_hash)
      VALUES (p_nom, p_prenom, p_email, p_hash);
    SET p_id_client  = LAST_INSERT_ID();
    SET p_code_retour = 'OK';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 2. sp_get_credentials — récupération de l'identifiant + hash pour la
--    connexion. Le compte inactif est refusé en base, pas seulement en PHP.
--    LA COMPARAISON DU MOT DE PASSE RESTE EN PHP : seul password_verify()
--    sait comparer un bcrypt en temps constant (RB-12, SEC-04).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_get_credentials $$
CREATE PROCEDURE sp_get_credentials (
  IN  p_email     VARCHAR(190),
  OUT p_id_client INT UNSIGNED,
  OUT p_hash      CHAR(60),
  OUT p_statut    VARCHAR(20)
)
BEGIN
  SELECT id_client, mot_de_passe_hash, IF(actif = 1, 'OK', 'COMPTE_BLOQUE')
    INTO p_id_client, p_hash, p_statut
    FROM client
   WHERE email = p_email;

  IF p_id_client IS NULL THEN
    -- message volontairement identique à « mot de passe erroné » côté IHM (SEC-05)
    SET p_hash   = NULL;
    SET p_statut = 'INCONNU';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 3. sp_update_client — modification de ses propres informations
--    (EF-CLI-02). Le changement d'email ré-active la règle RB-01.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_client $$
CREATE PROCEDURE sp_update_client (
  IN  p_id_client   INT UNSIGNED,
  IN  p_nom         VARCHAR(100),
  IN  p_prenom      VARCHAR(100),
  IN  p_email       VARCHAR(190),
  IN  p_telephone   VARCHAR(20),
  IN  p_adresse     VARCHAR(255),
  IN  p_code_postal VARCHAR(10),
  IN  p_ville       VARCHAR(100),
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM client WHERE id_client = p_id_client) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CLIENT_INTROUVABLE';
  END IF;

  IF EXISTS (SELECT 1 FROM client WHERE email = p_email AND id_client <> p_id_client) THEN
    SET p_code_retour = 'EMAIL_DEJA_UTILISE';
  ELSE
    UPDATE client
       SET nom = p_nom, prenom = p_prenom, email = p_email,
           telephone = p_telephone, adresse_livraison = p_adresse,
           code_postal = p_code_postal, ville = p_ville
     WHERE id_client = p_id_client;
    SET p_code_retour = 'OK';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 4. sp_search_products — recherche + filtres + tri + pagination (EF-VIS-02/03)
--    Toutes les valeurs utilisateur sont injectées dans la requête préparée
--    via des paramètres marqués ; seul le tri provient d'une liste blanche.
--    Le LIKE est construit en SQL (CONCAT), jamais en PHP (SEC-01).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_search_products $$
CREATE PROCEDURE sp_search_products (
  IN p_mot_cle      VARCHAR(120),
  IN p_id_categorie INT UNSIGNED,
  IN p_prix_min     DECIMAL(10,2),
  IN p_prix_max     DECIMAL(10,2),
  IN p_en_stock     TINYINT,
  IN p_trie_par     VARCHAR(20),
  IN p_page         INT,
  IN p_par_page     INT,
  IN p_admin        TINYINT
)
BEGIN
  -- Technique « tous les filtres sont toujours lies » : le texte SQL est
  -- dynamique (seule la partie necessaire est construite) mais le NOMBRE de
  -- marqueurs positionnels (?) reste constant, ce qui rend EXECUTE ... USING
  -- portable (MySQL comme MariaDB n'acceptent que des ? en requete preparee).
  -- Les valeurs n'entrent JAMAIS dans le texte de la requete : elles passent
  -- par des marqueurs positionnels (?) prepares (SEC-01).
  DECLARE v_kw   VARCHAR(120);
  DECLARE v_cat  INT UNSIGNED;
  DECLARE v_min  DECIMAL(10,2);
  DECLARE v_max  DECIMAL(12,2);
  DECLARE v_ok   TINYINT;
  DECLARE v_off  INT;
  DECLARE v_lim  INT;

  SET v_kw  = IFNULL(NULLIF(TRIM(p_mot_cle), ''), '');
  SET v_cat = IFNULL(p_id_categorie, 0);
  SET v_min = IFNULL(p_prix_min, 0);
  SET v_max = IFNULL(p_prix_max, 99999999.99);  -- DECIMAL(12,2) : borne haute « tout accepté »
  SET v_ok  = IFNULL(p_en_stock, 0);
  SET v_lim = LEAST(GREATEST(IFNULL(NULLIF(p_par_page, 0), 12), 1), 60);
  SET v_off = (GREATEST(IFNULL(NULLIF(p_page, 0), 1), 1) - 1) * v_lim;

  SET @sql = CONCAT(
    'SELECT p.id_produit, p.reference, p.slug, p.nom, p.prix_ttc, p.stock, p.visible,
            c.nom AS categorie,
            CASE WHEN p.stock = 0 THEN "RUPTURE"
                 WHEN p.stock <= p.seuil_alerte THEN "TRES_BAS"
                 ELSE "DISPONIBLE" END AS etat_stock
       FROM produit p
       JOIN categorie c ON c.id_categorie = p.id_categorie
      WHERE (? = 0 OR p.visible = 1)
        AND (? = "" OR p.nom LIKE CONCAT("%", ?, "%")
             OR p.description LIKE CONCAT("%", ?, "%")
             OR p.reference LIKE CONCAT("%", ?, "%"))
        AND (? = 0 OR p.id_categorie = ?)
        AND p.prix_ttc >= ?
        AND p.prix_ttc <= ?
        AND (? = 0 OR p.stock > 0)',
    CASE
      WHEN p_trie_par = 'prix_asc'  THEN ' ORDER BY p.prix_ttc ASC, p.id_produit'
      WHEN p_trie_par = 'prix_desc' THEN ' ORDER BY p.prix_ttc DESC, p.id_produit'
      WHEN p_trie_par = 'nouveaute' THEN ' ORDER BY p.date_creation DESC, p.id_produit'
      WHEN p_trie_par = 'stock'     THEN ' ORDER BY p.stock DESC, p.id_produit'
      ELSE ' ORDER BY p.nom ASC'
    END,
    ' LIMIT ? OFFSET ?');

  -- ordre des marqueurs = ordre des ? dans le texte de la requete
  SET @f_visible = IF(IFNULL(p_admin, 0) = 1, 0, 1);
  SET @f_kw  = v_kw, @f_cat = v_cat, @f_min = v_min, @f_max = v_max,
      @f_ok  = v_ok, @f_lim = v_lim, @f_off = v_off;

  SET @s = @sql;
  PREPARE stmt FROM @s;
  EXECUTE stmt USING @f_visible, @f_kw, @f_kw, @f_kw, @f_kw, @f_cat, @f_cat, @f_min, @f_max, @f_ok, @f_lim, @f_off;
  DEALLOCATE PREPARE stmt;

  -- second jeu de resultats : le nombre total de produits trouves (pagination)
  SET @s = 'SELECT COUNT(*) AS total_trouves
               FROM produit p
              WHERE (? = 0 OR p.visible = 1)
                AND (? = "" OR p.nom LIKE CONCAT("%", ?, "%")
                     OR p.description LIKE CONCAT("%", ?, "%")
                     OR p.reference LIKE CONCAT("%", ?, "%"))
                AND (? = 0 OR p.id_categorie = ?)
                AND p.prix_ttc >= ?
                AND p.prix_ttc <= ?
                AND (? = 0 OR p.stock > 0)';
  PREPARE stmt FROM @s;
  EXECUTE stmt USING @f_visible, @f_kw, @f_kw, @f_kw, @f_kw, @f_cat, @f_cat, @f_min, @f_max, @f_ok;
  DEALLOCATE PREPARE stmt;
END $$

-- ---------------------------------------------------------------------
-- 5. sp_save_product — création / modification côté Back-office
--    (EF-ADM-01, EF-ADM-02). p_id_produit NULL => création.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_save_product $$
CREATE PROCEDURE sp_save_product (
  IN  p_id_produit   INT UNSIGNED,
  IN  p_reference    VARCHAR(30),
  IN  p_nom          VARCHAR(150),
  IN  p_slug         VARCHAR(180),
  IN  p_description  TEXT,
  IN  p_prix_ht      DECIMAL(10,2),
  IN  p_tva          DECIMAL(5,2),
  IN  p_stock        INT,
  IN  p_seuil_alerte INT,
  IN  p_id_categorie INT UNSIGNED,
  IN  p_visible      TINYINT,
  IN  p_image_url    VARCHAR(255),
  OUT p_id_resultat  INT UNSIGNED,
  OUT p_code_retour  VARCHAR(40)
)
BEGIN
  DECLARE v_dup INT DEFAULT 0;

  IF p_prix_ht IS NULL OR p_prix_ht <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF';
  END IF;
  IF p_stock IS NULL OR p_stock < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF';
  END IF;
  IF p_nom IS NULL OR TRIM(p_nom) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NOM_REQUIS';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM categorie WHERE id_categorie = p_id_categorie) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CATEGORIE_INTROUVABLE';
  END IF;
  IF p_slug IS NULL OR TRIM(p_slug) = '' THEN
    SET p_slug = CONCAT('produit-', IFNULL(p_id_produit, 0));
  END IF;

  SELECT COUNT(*) INTO v_dup FROM produit
   WHERE reference = p_reference AND (id_produit <> IFNULL(p_id_produit, 0));
  IF v_dup > 0 THEN
    SET p_id_resultat = NULL; SET p_code_retour = 'REFERENCE_DEJA_UTILISEE';
  ELSE
    SELECT COUNT(*) INTO v_dup FROM produit
     WHERE slug = p_slug AND (id_produit <> IFNULL(p_id_produit, 0));
    IF v_dup > 0 THEN
      SET p_id_resultat = NULL; SET p_code_retour = 'SLUG_DEJA_UTILISE';
    ELSEIF p_id_produit IS NULL THEN
      INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, prix_ttc,
                           stock, seuil_alerte, id_categorie, visible, image_url)
        VALUES (p_reference, p_nom, p_slug, p_description, p_prix_ht,
                IFNULL(p_tva, 20.00), ROUND(p_prix_ht * (1 + IFNULL(p_tva, 20.00) / 100), 2),
                p_stock, IFNULL(p_seuil_alerte, 3), p_id_categorie,
                IFNULL(p_visible, 1), p_image_url);
      SET p_id_resultat  = LAST_INSERT_ID();
      SET p_code_retour  = 'OK';
    ELSE
      UPDATE produit
         SET reference = p_reference, nom = p_nom, slug = p_slug,
             description = p_description, prix_ht = p_prix_ht,
             tva = IFNULL(p_tva, tva), stock = p_stock,
             seuil_alerte = IFNULL(p_seuil_alerte, seuil_alerte),
             id_categorie = p_id_categorie, visible = IFNULL(p_visible, visible),
             image_url = p_image_url
       WHERE id_produit = p_id_produit;
      SET p_id_resultat = p_id_produit;
      SET p_code_retour = IF(ROW_COUNT() = 0, 'AUCUNE_MODIFICATION', 'OK');
    END IF;
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 6. sp_delete_product — suppression protégée (EF-ADM-02 + RB-16)
--    Un produit déjà commandé reste au catalogue (visible = 0) afin de
--    conserver l'historique des lignes de commande.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_delete_product $$
CREATE PROCEDURE sp_delete_product (IN p_id_produit INT UNSIGNED)
BEGIN
  DECLARE v_lignes INT DEFAULT 0;

  SELECT COUNT(*) INTO v_lignes FROM ligne_commande WHERE id_produit = p_id_produit;
  IF v_lignes > 0 THEN
    UPDATE produit SET visible = 0 WHERE id_produit = p_id_produit;   -- sinon : erreur du trigger
  ELSEIF EXISTS (SELECT 1 FROM produit WHERE id_produit = p_id_produit) THEN
    DELETE FROM produit WHERE id_produit = p_id_produit;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_INTROUVABLE';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 7. sp_adjust_stock — réappro / inventaire (EF-ADM-04, RB-03)
--    p_mode : 'SET' (valeur absolue) ou 'DELTA' (mouvement +/-)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_adjust_stock $$
CREATE PROCEDURE sp_adjust_stock (
  IN  p_id_produit  INT UNSIGNED,
  IN  p_mode        VARCHAR(5),
  IN  p_quantite    INT,
  IN  p_motif       VARCHAR(200),
  OUT p_stock       INT,
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  DECLARE v_new INT DEFAULT 0;

  IF p_quantite IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'QUANTITE_REQUISE';
  END IF;

  SELECT stock INTO v_new FROM produit WHERE id_produit = p_id_produit FOR UPDATE;
  IF v_new IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_INTROUVABLE';
  END IF;

  IF p_mode = 'DELTA' THEN
    SET v_new = v_new + p_quantite;
  ELSE
    SET v_new = p_quantite;
  END IF;

  IF v_new < 0 THEN
    -- la règle RB-03 est aussi garantie par trg_produit_stock_non_negatif :
    -- cette vérification ne fait qu'offrir un message explicite à l'IHM
    SET p_stock = NULL; SET p_code_retour = 'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF';
  ELSE
    UPDATE produit SET stock = v_new WHERE id_produit = p_id_produit;
    SET p_stock = v_new;
    SET p_code_retour = 'OK';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 8. sp_save_category — création / renommage (EF-ADM-03)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_save_category $$
CREATE PROCEDURE sp_save_category (
  IN  p_id_categorie INT UNSIGNED,
  IN  p_nom          VARCHAR(100),
  IN  p_slug         VARCHAR(120),
  IN  p_description  VARCHAR(500),
  OUT p_id_resultat  INT UNSIGNED,
  OUT p_code_retour  VARCHAR(40)
)
BEGIN
  IF p_nom IS NULL OR TRIM(p_nom) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NOM_REQUIS';
  END IF;
  IF p_slug IS NULL OR TRIM(p_slug) = '' THEN
    SET p_slug = LOWER(REPLACE(TRIM(p_nom), ' ', '-'));
  END IF;

  IF p_id_categorie IS NULL THEN
    INSERT INTO categorie (nom, slug, description) VALUES (p_nom, p_slug, p_description)
      ON DUPLICATE KEY UPDATE slug = VALUES(slug), description = VALUES(description);
    IF EXISTS (SELECT 1 FROM categorie WHERE nom = p_nom AND slug <> p_slug) THEN
      SET p_code_retour = 'NOM_DEJA_UTILISE';
      SET p_id_resultat = NULL;
    ELSE
      SET p_id_resultat = LAST_INSERT_ID();
      SET p_code_retour = 'OK';
    END IF;
  ELSE
    UPDATE categorie SET nom = p_nom, slug = p_slug, description = p_description
     WHERE id_categorie = p_id_categorie;
    SET p_id_resultat = p_id_categorie;
    SET p_code_retour = IF(ROW_COUNT() = 0, 'CATEGORIE_INTROUVABLE', 'OK');
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 9. sp_delete_category — RB-07 : on ne supprime pas une catégorie occupée
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_delete_category $$
CREATE PROCEDURE sp_delete_category (IN p_id_categorie INT UNSIGNED)
BEGIN
  DECLARE v_nb INT DEFAULT 0;

  SELECT COUNT(*) INTO v_nb FROM produit WHERE id_categorie = p_id_categorie;
  IF v_nb > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM categorie WHERE id_categorie = p_id_categorie) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CATEGORIE_INTROUVABLE';
  END IF;
  DELETE FROM categorie WHERE id_categorie = p_id_categorie;
END $$

-- ---------------------------------------------------------------------
-- 10. sp_create_order — ouverture d'une commande au statut BROUILLON
--     (première étape du cas d'utilisation « Passer une commande »).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_create_order $$
CREATE PROCEDURE sp_create_order (
  IN  p_id_client  INT UNSIGNED,
  IN  p_adresse    VARCHAR(255),
  OUT p_id_commande INT UNSIGNED,
  OUT p_numero      VARCHAR(20)
)
BEGIN
  DECLARE v_seq INT DEFAULT 0;

  IF NOT EXISTS (SELECT 1 FROM client WHERE id_client = p_id_client AND actif = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CLIENT_INTROUVABLE_OU_BLOQUE';
  END IF;
  IF p_adresse IS NULL OR TRIM(p_adresse) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ADRESSE_LIVRAISON_REQUISE';
  END IF;

  INSERT INTO commande (numero, id_client, adresse_livraison, statut)
    VALUES ('TMP', p_id_client, TRIM(p_adresse), 'BROUILLON');
  SET v_seq = LAST_INSERT_ID();
  SET p_id_commande = v_seq;
  SET p_numero = CONCAT('CMD', DATE_FORMAT(NOW(), '%Y'), '-', LPAD(v_seq, 6, '0'));
  UPDATE commande SET numero = p_numero WHERE id_commande = v_seq;
END $$

-- ---------------------------------------------------------------------
-- 11. sp_add_order_line — ajout d'une ligne dans une commande (ou d'un
--     panier persistant) : vérifie RB-05, RB-06, RB-18 ET décrémente le
--     stock. L'ordre des opérations est critique :
--        1) verrou ligne de stock (SELECT ... FOR UPDATE)
--        2) contrôle disponibilité
--        3) INSERT (le trigger trg_ligne_prix_snapshot fige le prix)
--        4) décrément (le trigger trg_ligne_decrement_stock refuse le négatif)
--        5) rafraîchissement du montant_total (RB-15)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_order_line $$
CREATE PROCEDURE sp_add_order_line (
  IN  p_id_commande INT UNSIGNED,
  IN  p_id_produit  INT UNSIGNED,
  IN  p_quantite    INT,
  OUT p_id_ligne    BIGINT UNSIGNED,
  OUT p_total       DECIMAL(10,2),
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  DECLARE v_statut_cmd VARCHAR(20);
  DECLARE v_port_cmd     DECIMAL(10,2);
  DECLARE v_statut        VARCHAR(20);
  DECLARE v_prix          DECIMAL(10,2);
  DECLARE v_stock         INT;
  DECLARE v_deja          INT DEFAULT 0;
  DECLARE v_dispo         INT;

  SET p_id_ligne  = NULL;
  SET p_total     = NULL;
  SET p_code_retour = 'OK';

  -- (1) RB-05 : quantite strictement positive, controlee AVANT toute ecriture
  IF p_quantite IS NULL OR p_quantite <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO';
  END IF;

  -- (2) verrou sur l'en-tete de commande : une commande ne se modifie plus
  --     une fois Expediee/Livree/Annulee
  SELECT statut INTO v_statut FROM commande WHERE id_commande = p_id_commande FOR UPDATE;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_INTROUVABLE';
  END IF;
  IF v_statut NOT IN ('BROUILLON','EN_PREPARATION') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_NON_MODIFIABLE';
  END IF;

  -- (3) RB-17 : le produit a pu disparaitre du catalogue entre l'ajout au
  --     panier et la validation -> verrou du stock + lecture du prix courant
  SELECT prix_ttc, stock INTO v_prix, v_stock
    FROM produit WHERE id_produit = p_id_produit FOR UPDATE;
  IF v_prix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_SUPPRIME_DU_CATALOGUE';
  END IF;

  -- (4) RB-18 : disponibilite = stock physique - ce qui est deja reserve sur
  --     la commande (plusieurs lignes pour un meme produit sont agregees)
  SELECT IFNULL(SUM(quantite), 0) INTO v_deja
    FROM ligne_commande
   WHERE id_commande = p_id_commande AND id_produit = p_id_produit;
  SET v_dispo = v_stock - v_deja;

  IF v_dispo < p_quantite THEN
    SET p_code_retour = 'STOCK_INSUFFISANT';
  ELSE
    -- (5) une seule ecriture : trg_ligne_prix_snapshot fige le prix (RB-06),
    --     trg_ligne_decrement_stock retire les unites (RB-18)
    INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire, total_ligne)
      VALUES (p_id_commande, p_id_produit, p_quantite, v_prix, ROUND(p_quantite * v_prix, 2));
    SET p_id_ligne = LAST_INSERT_ID();

    -- (6) RB-15 : le montant de l'en-tete est une somme, jamais une saisie
    -- (6 bis) ENF-14 : tant que la commande n'est pas validee (BROUILLON), le port est
    -- recalcule a chaque ligne, sinon la regle de franchise dependrait de l'ordre des
    -- lignes. Apres validation, le port est fige (snapshot) comme le prix (RB-06).
    SELECT c.statut, c.frais_port INTO v_statut_cmd, v_port_cmd
      FROM commande c WHERE c.id_commande = p_id_commande;
    IF v_statut_cmd = 'BROUILLON' THEN
      CALL sp_compute_shipping((SELECT IFNULL(SUM(l.total_ligne), 0)
                                  FROM ligne_commande l WHERE l.id_commande = p_id_commande),
                               @v_port_new, @v_motif);
      SET v_port_cmd = IFNULL(@v_port_new, v_port_cmd);
    END IF;

    UPDATE commande c
       SET c.frais_port = v_port_cmd,
           c.montant_total = (SELECT IFNULL(SUM(l.total_ligne), 0)
                                FROM ligne_commande l WHERE l.id_commande = c.id_commande)
                           + v_port_cmd          -- RB-15 : marchandises + port
     WHERE c.id_commande = p_id_commande;
  END IF;

  -- p_total sert aussi au cas STOCK_INSUFFISANT : l'IHM affiche
  -- « X deja reserves, il en reste N » a partir de ce montant et du stock rendu.
  SELECT IFNULL(SUM(total_ligne), 0) INTO p_total
    FROM ligne_commande WHERE id_commande = p_id_commande;

  -- Note gestion des erreurs : si un trigger refuse quand meme l'ecriture
  -- (stock passe negatif a cause d'une ecriture concurrente), le SIGNAL
  -- '45000' remonte a l'appelant : le modele PHP annule la transaction
  -- (rollback). La base garde donc le dernier mot meme si le code applicatif
  -- est faux — c'est l'interet de la defense en profondeur (SEC-11).
END $$

-- ---------------------------------------------------------------------
-- 12. sp_create_order_from_basket — commande à partir d'un tableau JSON
--     [{"id_produit":1,"quantite":2}, ...] : une seule ligne d'appel depuis
--     PHP, une seule transaction, un verrou de stock par produit.
--     Démonstration du scénario nominal du cas d'utilisation UC-07.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_create_order_from_basket $$
CREATE PROCEDURE sp_create_order_from_basket (
  IN  p_id_client   INT UNSIGNED,
  IN  p_adresse     VARCHAR(255),
  IN  p_panier_json JSON,
  IN  p_payee       TINYINT,
  OUT p_id_commande INT UNSIGNED,
  OUT p_numero      VARCHAR(20),
  OUT p_montant     DECIMAL(10,2),
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  DECLARE v_id_produit INT UNSIGNED;
  DECLARE v_quantite   INT;
  DECLARE v_done       TINYINT DEFAULT 0;
  DECLARE v_lignes     INT DEFAULT 0;
  DECLARE v_code       VARCHAR(40);
  DECLARE cur CURSOR FOR
      SELECT j.id_produit, j.quantite FROM JSON_TABLE(p_panier_json, '$[*]'
        COLUMNS (id_produit INT UNSIGNED PATH '$.id_produit',
                 quantite   INT         PATH '$.quantite')) AS j;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  SET p_code_retour = 'OK';

  IF p_panier_json IS NULL OR JSON_LENGTH(p_panier_json) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB19_PANIER_VIDE';
  END IF;

  START TRANSACTION;
    CALL sp_create_order(p_id_client, p_adresse, p_id_commande, p_numero);

    OPEN cur;
    la: LOOP
      FETCH cur INTO v_id_produit, v_quantite;
      IF v_done = 1 THEN LEAVE la; END IF;
      CALL sp_add_order_line(p_id_commande, v_id_produit, v_quantite, @id_ligne, @tot, v_code);
      IF v_code = 'STOCK_INSUFFISANT' THEN
        SET p_code_retour = 'STOCK_INSUFFISANT';
      END IF;
      SET v_lignes = v_lignes + 1;
    END LOOP;
    CLOSE cur;

    IF p_code_retour <> 'OK' THEN
      ROLLBACK;
      SET p_id_commande = NULL; SET p_numero = NULL; SET p_montant = NULL;
    ELSE
      UPDATE commande c
         SET c.montant_total = (SELECT IFNULL(SUM(l.total_ligne), 0)
                                  FROM ligne_commande l WHERE l.id_commande = c.id_commande)
                              + c.frais_port          -- RB-15 : marchandises + port fige
       WHERE c.id_commande = p_id_commande;
      SET v_code = NULL;
      UPDATE commande
         SET statut = IF(IFNULL(p_payee, 0) = 1, 'PAYEE', 'EN_PREPARATION')
       WHERE id_commande = p_id_commande;
      SELECT montant_total INTO p_montant FROM commande WHERE id_commande = p_id_commande;
      COMMIT;
    END IF;
END $$

-- ---------------------------------------------------------------------
-- 13. sp_confirm_order — validation du panier (RB-04 : au moins une ligne)
--     Passe BROUILLON -> EN_PREPARATION (ou PAYEE si paiement simulé).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_confirm_order $$
CREATE PROCEDURE sp_confirm_order (
  IN  p_id_commande INT UNSIGNED,
  IN  p_id_client   INT UNSIGNED,
  IN  p_payee       TINYINT,
  OUT p_montant     DECIMAL(10,2),
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  DECLARE v_id_client  INT UNSIGNED;
  DECLARE v_statut     VARCHAR(20);
  DECLARE v_nb         INT DEFAULT 0;
  DECLARE v_montant    DECIMAL(10,2);
  DECLARE v_manquant   INT DEFAULT 0;

  SELECT id_client, statut, montant_total INTO v_id_client, v_statut, v_montant
    FROM commande WHERE id_commande = p_id_commande FOR UPDATE;

  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_INTROUVABLE';
  END IF;
  -- RB-15 / SEC-08 : contrôle d'appartenance côté serveur, la requête HTTP
  -- peut très bien porter le numéro d'une commande d'un autre client.
  IF p_id_client IS NOT NULL AND v_id_client <> p_id_client THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ACCES_NON_AUTORISE';
  END IF;
  IF v_statut <> 'BROUILLON' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_DEJA_VALIDEE';
  END IF;

  SELECT COUNT(*) INTO v_nb FROM ligne_commande WHERE id_commande = p_id_commande;
  IF v_nb = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE';
  END IF;

  -- RB-18 : les stocks déjà réservés par les lignes sont suffisants par
  -- construction (le décrément a eu lieu à l'ajout) ; on re-vérifie quand
  -- même qu'aucun produit n'est passé négatif entre-temps.
  SELECT COUNT(*) INTO v_manquant
    FROM ligne_commande l JOIN produit p ON p.id_produit = l.id_produit
   WHERE l.id_commande = p_id_commande AND p.stock < 0;
  IF v_manquant > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STOCK_NEGATIF_DETECTE';
  END IF;

  -- le port est fige a la validation (snapshot, meme logique que RB-06 pour le prix) :
  -- une evolution ulterieure des tariffs de transport ne doit pas retoucher la commande
  CALL sp_compute_shipping((SELECT IFNULL(SUM(l.total_ligne), 0)
                              FROM ligne_commande l WHERE l.id_commande = p_id_commande),
                           @v_port, @v_motif);

  UPDATE commande c
     SET c.frais_port = IFNULL(@v_port, 0),
         c.montant_total = (SELECT IFNULL(SUM(l.total_ligne), 0)
                              FROM ligne_commande l WHERE l.id_commande = c.id_commande)
                          + IFNULL(@v_port, 0),
         c.statut = IF(IFNULL(p_payee, 0) = 1, 'PAYEE', 'EN_PREPARATION')
   WHERE c.id_commande = p_id_commande;

  SELECT montant_total INTO p_montant FROM commande WHERE id_commande = p_id_commande;
  SET p_code_retour = 'OK';
END $$

-- ---------------------------------------------------------------------
-- 14. sp_update_order_status — changement de statut (EF-ADM-06, RB-11)
--       La matrice des transitions est contrôlée ici ET par le trigger
--       trg_commande_transition_statut (défense en profondeur).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_order_status $$
CREATE PROCEDURE sp_update_order_status (
  IN  p_id_commande   INT UNSIGNED,
  IN  p_nouveau_statut VARCHAR(20),
  IN  p_commentaire   VARCHAR(500),
  IN  p_id_auteur     INT UNSIGNED,
  IN  p_role_auteur   VARCHAR(10),
  OUT p_code_retour   VARCHAR(40)
)
BEGIN
  DECLARE v_statut VARCHAR(20);

  IF p_id_commande IS NULL OR p_nouveau_statut IS NULL
     OR p_nouveau_statut NOT IN ('BROUILLON','EN_PREPARATION','PAYEE','EXPEDIEE','LIVREE','ANNULEE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STATUT_NON_AUTORISE';
  END IF;

  SELECT statut INTO v_statut FROM commande WHERE id_commande = p_id_commande FOR UPDATE;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_INTROUVABLE';
  END IF;
  -- règle de métier : le client annule tant que ce n'est pas expédié ;
  -- l'administrateur peut en plus rejeter une commande en préparation.
  IF p_role_auteur = 'CLIENT' AND v_statut NOT IN ('BROUILLON','EN_PREPARATION','PAYEE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ANNULATION_TROP_TARDIVE';
  END IF;
  IF v_statut = p_nouveau_statut THEN
    SET p_code_retour = 'STATUT_DEJA_A_JOUR';
  ELSE
    UPDATE commande
       SET statut = p_nouveau_statut,
           commentaire = IFNULL(p_commentaire, commentaire)
     WHERE id_commande = p_id_commande;
    -- PAS d'INSERT dans order_status_history : trg_history_statut (AFTER UPDATE)
    -- s'en charge. Laisser la procédure écrire aussi produirait un doublon
    -- (et l'application pourrait « oublier » de tracer : le trigger, lui, n'oublie jamais).
    SET p_code_retour = 'OK';
  END IF;
END $$

-- ---------------------------------------------------------------------
-- 15. sp_cancel_order — annulation par le client (EF-CLI-05, RB-18) :
--     restauration ligne à ligne du stock PUIS changement de statut,
--     le tout dans une transaction unique (aucune ligne « orpheline »).
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_cancel_order $$
CREATE PROCEDURE sp_cancel_order (
  IN  p_id_commande INT UNSIGNED,
  IN  p_id_client   INT UNSIGNED,
  OUT p_code_retour VARCHAR(40)
)
BEGIN
  DECLARE v_id_produit INT UNSIGNED;
  DECLARE v_quantite   INT;
  DECLARE v_done       TINYINT DEFAULT 0;
  DECLARE v_statut     VARCHAR(20);
  DECLARE v_id_client  INT UNSIGNED;
  DECLARE cur CURSOR FOR
      SELECT id_produit, quantite FROM ligne_commande WHERE id_commande = p_id_commande;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  SELECT id_client, statut INTO v_id_client, v_statut
    FROM commande WHERE id_commande = p_id_commande FOR UPDATE;
  IF v_statut IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_INTROUVABLE';
  END IF;
  IF p_id_client IS NOT NULL AND v_id_client <> p_id_client THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ACCES_NON_AUTORISE';
  END IF;
  IF v_statut NOT IN ('BROUILLON','EN_PREPARATION','PAYEE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'COMMANDE_NON_ANNULABLE';
  END IF;

  START TRANSACTION;
    -- La restitution du stock n'est PAS faite ici : DELETE sur ligne_commande
    -- declenche trg_ligne_restore_stock, qui rend les unites ligne par ligne.
    -- Le cursor sert uniquement a memoriser les quantites restituees pour le
    -- message renvoye a l'IHM (et a prouver qu'on sait lire OLD/NEW... depuis
    -- une procedure). Le trigger reste la source de verite (RB-18).
    OPEN cur;
    SET @qte_restauree = 0;
    lp: LOOP
      FETCH cur INTO v_id_produit, v_quantite;
      IF v_done = 1 THEN LEAVE lp; END IF;
      SET @qte_restauree = @qte_restauree + v_quantite;
    END LOOP;
    CLOSE cur;
    DELETE FROM ligne_commande WHERE id_commande = p_id_commande;
    -- Le port est annule avec les lignes : une commande annulee n'a plus rien a
    -- facturer, et la formule RB-15 (montant = lignes + port) doit rester vraie a
    -- chaque ecriture, y compris pour ANNULEE (le trigger la verifie sans condition).
    UPDATE commande SET statut = 'ANNULEE',
                        frais_port = 0.00,
                        montant_total = 0.00,
                        commentaire = IFNULL(commentaire, 'Annulation demandée par le client')
     WHERE id_commande = p_id_commande;   -- trg_history_statut trace EN_PREPARATION -> ANNULEE
  COMMIT;
  SET p_code_retour = 'OK';
END $$

-- ---------------------------------------------------------------------
-- 16. sp_revenue_report — tableau de bord Back-office (EF-ADM-07)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_revenue_report $$
CREATE PROCEDURE sp_revenue_report (IN p_deb DATETIME, IN p_fin DATETIME)
BEGIN
  SELECT c.statut,
         COUNT(*)                                    AS nb_commandes,
         IFNULL(SUM(c.montant_total), 0)              AS montant_ht_eq_ttc,
         IFNULL(ROUND(AVG(c.montant_total), 2), 0)    AS montant_moyen
    FROM commande c
   WHERE (p_deb IS NULL OR c.date_commande >= p_deb)
     AND (p_fin IS NULL OR c.date_commande <  p_fin)
   GROUP BY c.statut
   ORDER BY montant_ht_eq_ttc DESC;

  SELECT p.reference, p.nom, SUM(l.quantite) AS unites, SUM(l.total_ligne) AS ca
    FROM ligne_commande l
    JOIN commande c ON c.id_commande = l.id_commande AND c.statut <> 'ANNULEE'
    JOIN produit  p ON p.id_produit  = l.id_produit
   WHERE (p_deb IS NULL OR c.date_commande >= p_deb)
     AND (p_fin IS NULL OR c.date_commande <  p_fin)
   GROUP BY p.reference, p.nom
   ORDER BY ca DESC
   LIMIT 5;
END $$

DELIMITER ;
