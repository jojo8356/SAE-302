-- =====================================================================
-- T-09 | RB-18 | stock insuffisant : code de retour de la procedure
-- Attendu : OK
-- La procedure sp_add_order_line ne SIGNAL pas quand le stock est
-- insuffisant : elle positionne p_code_retour = 'STOCK_INSUFFISANT'
-- sans lever d'exception (permet a l'IHM d'afficher un message
-- convivial sans interrompre la transaction).
-- On cree un brouillon dedie, puis on ajoute 999 exemplaires du
-- produit TST-900 (stock 5). On SELECT le code de retour pour que
-- le harnais puisse verifier qu'il n'y a pas d'erreur SQL et que
-- le code rendu vaut bien 'STOCK_INSUFFISANT'.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '2 rue du Test T09',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  999,        -- bien au-dela du stock
  @lid, @tot, @code);

SELECT CASE WHEN @code = 'STOCK_INSUFFISANT' THEN 'PASS'
            ELSE CONCAT('FAIL: code retour inattendu : ', IFNULL(@code,'NULL'))
       END AS T09_result;
