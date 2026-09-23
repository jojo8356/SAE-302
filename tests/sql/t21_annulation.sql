-- =====================================================================
-- T-21 | RB-18 | annulation : stock restaure ligne par ligne
-- Attendu : OK
-- On cree une commande pour carla, ajoute 2 exemplaires de TST-900
-- (stock passe 5 -> 3), puis on annule via sp_cancel_order (role client).
-- Attendu : le stock doit etre restitue a 5, la commande en ANNULEE,
-- frais_port et montant_total a 0, et une ligne d'historique ajoutee.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'carla@example.com' LIMIT 1),
  '13 rue du Test T21',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  2, @lid, @tot, @code_a);

-- Verif intermediaire : le stock doit etre 3
SET @stock_avant_annulation = (SELECT stock FROM produit WHERE reference='TST-900');

CALL sp_cancel_order(@cid,
  (SELECT id_client FROM client WHERE email = 'carla@example.com' LIMIT 1),
  @code_c);

SELECT CASE
        WHEN @code_c = 'OK'
         AND (SELECT stock FROM produit WHERE reference = 'TST-900') = 5
         AND (SELECT statut FROM commande WHERE id_commande = @cid) = 'ANNULEE'
         AND (SELECT frais_port FROM commande WHERE id_commande = @cid) = 0.00
         AND (SELECT montant_total FROM commande WHERE id_commande = @cid) = 0.00
         AND @stock_avant_annulation = 3
        THEN 'PASS_stock_restitue_et_commande_annulee'
        ELSE CONCAT('FAIL : code=', IFNULL(@code_c,'NULL'),
                    ' stock=', (SELECT stock FROM produit WHERE reference='TST-900'),
                    ' (avant=', IFNULL(@stock_avant_annulation,'NULL'),')',
                    ' statut=', (SELECT statut FROM commande WHERE id_commande=@cid),
                    ' mnt=', (SELECT montant_total FROM commande WHERE id_commande=@cid),
                    ' port=', (SELECT frais_port FROM commande WHERE id_commande=@cid))
       END AS T21_result;
