-- =====================================================================
-- T-19 | RB-03 | sp_adjust_stock refuse le passage en negatif (OUT)
-- Attendu : OK
-- On appelle sp_adjust_stock avec p_mode='DELTA' et une quantite tres
-- negative sur TST-900 (stock=5) : la procedure ne doit PAS lever
-- d'exception, mais rendre p_stock=NULL et p_code_retour=
-- RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF, et le stock ne doit PAS bouger.
-- =====================================================================
CALL sp_adjust_stock(
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  'DELTA', -1000, 'test T-19 : tentative de delta -1000',
  @stock_after, @code);

SELECT CASE
        WHEN @code = 'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF'
         AND @stock_after IS NULL
         AND (SELECT stock FROM produit WHERE reference = 'TST-900') = 5
        THEN 'PASS_code_et_stock_inchanges'
        ELSE CONCAT('FAIL_code=', IFNULL(@code,'NULL'),
                    ' stock_sortie=', IFNULL(@stock_after,'NULL'),
                    ' stock_reel=', (SELECT stock FROM produit WHERE reference='TST-900'))
       END AS T19_result;
