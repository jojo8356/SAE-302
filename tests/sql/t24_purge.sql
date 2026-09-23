-- =====================================================================
-- T-24 | RB-04 | suppression d'une commande BROUILLON vide autorisee
-- Attendu : OK
-- Cree un brouillon VIDE (pas de sp_add_order_line) puis le DELETE
-- directement : trg_commande_delete ne doit pas s'y opposer
-- (RB-04 n'interdit que la purge d'une commande qui CONTIENT des lignes,
-- et RB-09 n'interdit la suppression que des commandes dont le statut
-- n'est ni BROUILLON ni EN_PREPARATION).
-- On verifie que le DELETE reussit et que la commande a disparu.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'bruno@example.com' LIMIT 1),
  '16 rue du Test T24',
  @cid, @cnum);

SET @existe_avant = (SELECT COUNT(*) FROM commande WHERE id_commande = @cid);

DELETE FROM commande WHERE id_commande = @cid;

SELECT CASE
        WHEN @existe_avant = 1
         AND NOT EXISTS (SELECT 1 FROM commande WHERE id_commande = @cid)
        THEN 'PASS_brouillon_vide_supprime'
        ELSE CONCAT('FAIL_avant=', IFNULL(@existe_avant,'NULL'),
                    ' apres=', (SELECT COUNT(*) FROM commande WHERE id_commande=@cid))
       END AS T24_result;
