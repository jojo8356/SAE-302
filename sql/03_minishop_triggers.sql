-- =====================================================================
-- MiniShop — 03 : déclencheurs (16 triggers, minimum demandé : 5)
-- ---------------------------------------------------------------------
-- Principes :
--   * Un trigger ne peut pas lire la table qu'il est en train de muter
--     (limitation MySQL/MariaDB) : chaque règle est donc écrite « du bon
--     côté » (déclencheur sur la table fille pour la table parente, et
--     inversement).
--   * Les triggers sont la DERNIÈRE barrière : même un UPDATE écrit à la
--     main depuis mysql, phpMyAdmin ou un contrôleur non MVC ne peut pas
--     violer RB-02 / RB-03 / RB-05 / RB-06 / RB-09 / RB-11 / RB-14.
--   * ORDER_STATUS_HISTORY n'est alimentée QUE par trg_history_statut :
--     l'application ne peut pas « oublier » de tracer (audit fiable).
--   * ORDRE DE CRÉATION : MySQL exécute les triggers d'un même évènement
--     dans l'ordre de leur création. trg_ligne_immutable est donc créé
--     AVANT trg_ligne_prix_snapshot, sans quoi une modification du prix
--     d'une ligne serait silencieusement « réparée » par le snapshot au
--     lieu d'être rejetée (cf. test T-11).
--
-- Chargement :  mysql -u root -p minishop < sql/03_minishop_triggers.sql
--               (ou scripts/load_db.sh qui encapsule le DELIMITER $$)
-- =====================================================================

-- =====================================================================
-- BLOC A — STOCK (RB-03, RB-18) : le stock ne peut jamais devenir négatif
-- =====================================================================

-- TRG-A1 — contrôle de disponibilité avant l'insertion d'une ligne
DELIMITER $$

DROP TRIGGER IF EXISTS trg_ligne_controle_insert $$
CREATE TRIGGER trg_ligne_controle_insert
BEFORE INSERT ON ligne_commande
FOR EACH ROW
BEGIN
  DECLARE v_prix DECIMAL(10,2);
  DECLARE v_stock INT;

  SELECT prix_ttc, stock INTO v_prix, v_stock
    FROM produit WHERE id_produit = NEW.id_produit;

  IF v_prix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_INTROUVABLE';    -- RB-17
  END IF;
  IF v_stock < NEW.quantite THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'STOCK_INSUFFISANT';      -- RB-18
  END IF;
END $$

-- TRG-A2 — décrément du stock physique après insertion de la ligne
DROP TRIGGER IF EXISTS trg_ligne_decrement_stock $$
CREATE TRIGGER trg_ligne_decrement_stock
AFTER INSERT ON ligne_commande
FOR EACH ROW
BEGIN
  UPDATE produit
     SET stock = GREATEST(stock - NEW.quantite, 0)
   WHERE id_produit = NEW.id_produit;
END $$

-- TRG-A3 — restauration du stock quand une ligne disparaît
--          (annulation de commande, retrait du panier, purge d'un brouillon)
DROP TRIGGER IF EXISTS trg_ligne_restore_stock $$
CREATE TRIGGER trg_ligne_restore_stock
AFTER DELETE ON ligne_commande
FOR EACH ROW
BEGIN
  UPDATE produit
     SET stock = stock + OLD.quantite
   WHERE id_produit = OLD.id_produit;
END $$

-- =====================================================================
-- BLOC B — PRIX, IMMUTABILITÉ (RB-02, RB-06, RB-09, RB-14, RB-16)
-- =====================================================================

-- TRG-B1 — une ligne de commande est définitive : produit, quantité et prix
--          ne se réécrivent plus (RB-09 + RB-06).
DROP TRIGGER IF EXISTS trg_ligne_immutable $$
CREATE TRIGGER trg_ligne_immutable
BEFORE UPDATE ON ligne_commande
FOR EACH ROW
BEGIN
  IF NEW.quantite <> OLD.quantite
     OR NEW.id_produit <> OLD.id_produit
     OR NEW.prix_unitaire <> OLD.prix_unitaire THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE';
  END IF;
END $$

-- TRG-B2 — snapshot du prix : le prix enregistré dans LIGNE_COMMANDE est
--          TOUJOURS le prix TTC du produit à l'instant de l'achat, même si le
--          navigateur (ou un code mal écrit) envoie un autre prix (RB-06).
--          NB : NEW.<colonne> n'est pas une cible valide pour SELECT ... INTO
--          dans un trigger, d'où la variable locale.
DROP TRIGGER IF EXISTS trg_ligne_prix_snapshot $$
CREATE TRIGGER trg_ligne_prix_snapshot
BEFORE INSERT ON ligne_commande
FOR EACH ROW
BEGIN
  DECLARE v_prix DECIMAL(10,2);

  SELECT p.prix_ttc INTO v_prix
    FROM produit p WHERE p.id_produit = NEW.id_produit;
  IF v_prix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_INTROUVABLE';
  END IF;
  SET NEW.prix_unitaire = v_prix;
  SET NEW.total_ligne   = ROUND(NEW.quantite * v_prix, 2);
END $$

-- TRG-B3 — prix TTC calculé par la base, jamais transmis par le navigateur
DROP TRIGGER IF EXISTS trg_produit_ttc $$
CREATE TRIGGER trg_produit_ttc
BEFORE INSERT ON produit
FOR EACH ROW
BEGIN
  SET NEW.prix_ttc = ROUND(NEW.prix_ht * (1 + NEW.tva / 100), 2);
  -- etat de reference pour la campagne de tests : la quantite du seed est memorisee
  -- a l'insertion, ainsi la fixture peut toujours repartir du meme stock sans
  -- dependre de l'ordre ni du nombre de passages des tests.
  IF NEW.stock_initial IS NULL OR NEW.stock_initial < 0 THEN
    SET NEW.stock_initial = IFNULL(NEW.stock, 0);
  END IF;
END $$

-- TRG-B4 — idem en modification : toute évolution du prix HT ou du taux
--          recalcule le TTC, sans jamais toucher aux lignes déjà enregistrées
DROP TRIGGER IF EXISTS trg_produit_ttc_update $$
CREATE TRIGGER trg_produit_ttc_update
BEFORE UPDATE ON produit
FOR EACH ROW
  SET NEW.prix_ttc = ROUND(NEW.prix_ht * (1 + NEW.tva / 100), 2);

-- TRG-B5 — garde-fou sur le stock saisi en Back-office (RB-03). Le CHECK
--          ck_produit_stock bloque déjà la valeur ; ce trigger fournit un
--          message explicite à l'IHM.
DROP TRIGGER IF EXISTS trg_produit_regles $$
CREATE TRIGGER trg_produit_regles
BEFORE UPDATE ON produit
FOR EACH ROW
BEGIN
  IF NEW.stock < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF';
  END IF;
END $$

-- TRG-B6 — RB-14 : un produit référencé par une commande ne peut pas être
--          supprimé (le snapshot RB-06 doit rester explicable dans 5 ans).
DROP TRIGGER IF EXISTS trg_produit_delete $$
CREATE TRIGGER trg_produit_delete
BEFORE DELETE ON produit
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM ligne_commande WHERE id_produit = OLD.id_produit) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER';
  END IF;
END $$

-- =====================================================================
-- BLOC C — CATÉGORIES (RB-07, RB-08, RB-14)
-- =====================================================================

-- TRG-C1 — normalisation du slug (permet des URLs lisibles et stables)
DROP TRIGGER IF EXISTS trg_categorie_slug $$
CREATE TRIGGER trg_categorie_slug
BEFORE INSERT ON categorie
FOR EACH ROW
BEGIN
  IF NEW.slug IS NULL OR TRIM(NEW.slug) = '' THEN
    SET NEW.slug = LOWER(REPLACE(NEW.nom, ' ', '-'));
  END IF;
END $$

-- TRG-C2 — on ne supprime pas une catégorie qui porte encore des produits
DROP TRIGGER IF EXISTS trg_categorie_delete $$
CREATE TRIGGER trg_categorie_delete
BEFORE DELETE ON categorie
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM produit WHERE id_categorie = OLD.id_categorie) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CATEGORIE_NON_VIDE';
  END IF;
END $$

-- =====================================================================
-- BLOC D — COMMANDES, STATUTS, AUDIT (RB-04, RB-10, RB-11, RB-15)
-- =====================================================================

-- TRG-D1 — matrice des transitions autorisées + montants non saisissables
--          + rattachement à un client réel (RB-10, RB-11, RB-15)
DROP TRIGGER IF EXISTS trg_commande_transition_statut $$
CREATE TRIGGER trg_commande_transition_statut
BEFORE UPDATE ON commande
FOR EACH ROW
BEGIN
  IF NOT EXISTS (SELECT 1 FROM client WHERE id_client = NEW.id_client) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB10_COMMANDE_SANS_CLIENT';
  END IF;

  -- RB-15 / ENF-14 : une fois la commande validee, `montant_total` DOIT rester egal a
  -- (somme des lignes + port) : le controle n'est plus conditionne a un changement du
  -- montant, sinon un UPDATE qui ne touche QUE `frais_port` laisserait la facture
  -- incoherente sans jamais etre refuse. Les brouillons restent tolerants (RB-04
  -- n'exige une ligne qu'a la validation).
  IF (NEW.statut = 'BROUILLON'
        AND NEW.montant_total <> OLD.montant_total
        AND NEW.montant_total <> (SELECT IFNULL(SUM(total_ligne), 0)
                                    FROM ligne_commande
                                   WHERE id_commande = NEW.id_commande) + NEW.frais_port)
     OR (NEW.statut <> 'BROUILLON'
        AND NEW.montant_total <> (SELECT IFNULL(SUM(total_ligne), 0)
                                    FROM ligne_commande
                                   WHERE id_commande = NEW.id_commande) + NEW.frais_port) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB15_MONTANT_CALCULE_INTERDIT';
  END IF;

  IF NEW.statut <> OLD.statut THEN
    IF (OLD.statut = 'BROUILLON'      AND NEW.statut NOT IN ('EN_PREPARATION','PAYEE','ANNULEE'))
    OR (OLD.statut = 'EN_PREPARATION' AND NEW.statut NOT IN ('PAYEE','EXPEDIEE','ANNULEE'))
    OR (OLD.statut = 'PAYEE'          AND NEW.statut NOT IN ('EXPEDIEE','ANNULEE'))
    OR (OLD.statut = 'EXPEDIEE'       AND NEW.statut NOT IN ('LIVREE','ANNULEE'))
    OR (OLD.statut IN ('LIVREE','ANNULEE')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB11_TRANSITION_STATUT_INTERDITE';
    END IF;
  END IF;
END $$

-- TRG-D2 — RB-04 / RB-10 / RB-15 à la création : client réel, montant à 0,
--          statut initial forcé à BROUILLON
DROP TRIGGER IF EXISTS trg_commande_controle_insert $$
CREATE TRIGGER trg_commande_controle_insert
BEFORE INSERT ON commande
FOR EACH ROW
BEGIN
  IF NOT EXISTS (SELECT 1 FROM client WHERE id_client = NEW.id_client) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB10_COMMANDE_SANS_CLIENT';
  END IF;
  IF NEW.montant_total <> 0 THEN
    SET NEW.montant_total = 0;   -- RB-15 : le montant se calcule, il ne se saisit pas
  END IF;
  IF NEW.frais_port IS NULL OR NEW.frais_port < 0 THEN
    SET NEW.frais_port = 0;      -- RB-15 : le port est figure par sp_compute_shipping, jamais saisi a la main
  END IF;
  IF NEW.statut IS NULL THEN
    SET NEW.statut = 'BROUILLON';
  END IF;
END $$

-- TRG-D3 — on ne purge pas l'historique : suppression interdite dès que la
--          commande a été validée ; une commande ne disparaît que vidée de
--          ses lignes (cohérence avec RB-04)
DROP TRIGGER IF EXISTS trg_commande_delete $$
CREATE TRIGGER trg_commande_delete
BEFORE DELETE ON commande
FOR EACH ROW
BEGIN
  IF OLD.statut NOT IN ('BROUILLON','EN_PREPARATION') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB09_COMMANDE_VALIDATEE_INTERDITE_DE_SUPPRIMER';
  END IF;
  IF EXISTS (SELECT 1 FROM ligne_commande WHERE id_commande = OLD.id_commande) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE';
  END IF;
END $$

-- TRG-D4 — HISTORIQUE AUTOMATIQUE (exigence « Trigger 2 — Historique ») :
--          chaque changement de statut est tracé, que la modification vienne
--          d'une procédure stockée, d'un import de reprise ou d'un UPDATE
--          manuel en session.
DROP TRIGGER IF EXISTS trg_history_statut $$
CREATE TRIGGER trg_history_statut
AFTER UPDATE ON commande
FOR EACH ROW
BEGIN
  IF NEW.statut <> OLD.statut THEN
    INSERT INTO order_status_history (order_id, old_status, new_status, changed_by_role, commentaire)
      VALUES (NEW.id_commande, OLD.statut, NEW.statut, 'SYSTEME',
              CONCAT('Tracé par trg_history_statut le ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')));
  END IF;
END $$

-- TRG-D5 — le statut initial est également tracé (première ligne d'historique)
DROP TRIGGER IF EXISTS trg_history_creation $$
CREATE TRIGGER trg_history_creation
AFTER INSERT ON commande
FOR EACH ROW
  INSERT INTO order_status_history (order_id, old_status, new_status, changed_by_role)
    VALUES (NEW.id_commande, NULL, NEW.statut, 'SYSTEME')$$

DELIMITER ;


-- =====================================================================
-- Vérification : la liste des triggers installés
--   SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION
--     FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()
--    ORDER BY EVENT_OBJECT_TABLE, ACTION_TIMING;
-- =====================================================================
