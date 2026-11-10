-- Mise à jour ciblée des deux déclencheurs d’historique ; aucune donnée n’est modifiée.
-- MySQL/MariaDB : DDL à commit implicite. Sauvegarder et vérifier les privilèges
-- TRIGGER avant application, prévoir une fenêtre sans écritures et vérifier le résultat.
DELIMITER $$
DROP TRIGGER IF EXISTS trg_history_statut $$
CREATE TRIGGER trg_history_statut
AFTER UPDATE ON commande
FOR EACH ROW
BEGIN
  IF NEW.statut <> OLD.statut THEN
    INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, changed_by_role, commentaire)
      VALUES (NEW.id_commande, OLD.statut, NEW.statut, @minishop_actor_id,
              COALESCE(@minishop_actor_role, 'SYSTEME'),
              CASE WHEN NEW.commentaire IS NOT NULL AND TRIM(NEW.commentaire) <> ''
                        AND NOT (NEW.commentaire <=> OLD.commentaire)
                   THEN TRIM(NEW.commentaire)
                   ELSE CONCAT('Changement de statut le ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')) END);
  END IF;
END $$

-- TRG-D5 — le statut initial est également tracé (première ligne d'historique)
DROP TRIGGER IF EXISTS trg_history_creation $$
CREATE TRIGGER trg_history_creation
AFTER INSERT ON commande
FOR EACH ROW
  INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, changed_by_role, commentaire)
    VALUES (NEW.id_commande, NULL, NEW.statut, @minishop_actor_id,
            COALESCE(@minishop_actor_role, 'SYSTEME'), 'Création de la commande')$$

DELIMITER ;
