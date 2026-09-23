-- =====================================================================
-- T-22 | RB-11 | historique des statuts ecrit automatiquement par trigger
-- Attendu : OK
-- Cree une commande (trg_history_creation ecrit une ligne NULL->BROUILLON),
-- ajoute une ligne, confirme (->EN_PREPARATION), puis passe en PAYEE via
-- sp_update_order_status : trg_history_statut doit ecrire DEUX lignes
-- supplementaires. On verifie qu'on a bien 3 lignes d'historique pour
-- cette commande (creation, validation en EN_PREPARATION, passage PAYEE).
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '14 rue du Test T22',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code_a);

CALL sp_confirm_order(@cid,
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  0, @montant, @code_v);

CALL sp_update_order_status(@cid, 'PAYEE', 'test T22',
  (SELECT id_admin FROM administrateur WHERE role='SUPER' LIMIT 1),
  'ADMIN', @code_s);

SELECT CASE
        WHEN (SELECT COUNT(*) FROM order_status_history WHERE order_id = @cid) >= 3
         AND EXISTS (SELECT 1 FROM order_status_history WHERE order_id = @cid
                      AND old_status IS NULL AND new_status = 'BROUILLON')
         AND EXISTS (SELECT 1 FROM order_status_history WHERE order_id = @cid
                      AND old_status = 'BROUILLON' AND new_status = 'EN_PREPARATION')
         AND EXISTS (SELECT 1 FROM order_status_history WHERE order_id = @cid
                      AND old_status = 'EN_PREPARATION' AND new_status = 'PAYEE')
        THEN 'PASS_historique_tracé'
        ELSE CONCAT('FAIL_nb_lignes=',
                    (SELECT COUNT(*) FROM order_status_history WHERE order_id=@cid))
       END AS T22_result;
