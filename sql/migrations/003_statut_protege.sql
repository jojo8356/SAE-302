-- Protection ciblée de la procédure de changement de statut.
-- DDL à commit implicite : sauvegarde, privilèges CREATE/ALTER ROUTINE et
-- fenêtre sans écritures requis ; vérifier le résultat avant réouverture.
DELIMITER $$
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

  -- Une annulation doit solder et gérer le stock, jamais un simple UPDATE.
  -- Utiliser sp_cancel_order pour le client ou le parcours administrateur applicatif.
  IF p_nouveau_statut = 'ANNULEE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ANNULATION_VIA_PARCOURS_DEDIE';
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

DELIMITER ;
