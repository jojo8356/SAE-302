-- =====================================================================
-- T-29 | ENF-16 | erreur en cours de commande : ROLLBACK integral,
--                 aucune donnee partielle
-- Attendu : OK
-- sp_create_order_from_basket demarre une transaction, ajoute les
-- lignes, et ROLLBACK si STOCK_INSUFFISANT. On lui passe un panier
-- qui demande 999 exemplaires de TST-900 (stock=5) : la procedure
-- doit rendre p_code_retour = 'STOCK_INSUFFISANT', p_id_commande=NULL,
-- et AUCUNE nouvelle commande ne doit apparaitre en base, AUCUN stock
-- decremente.
-- =====================================================================

-- Capture des compteurs avant dans des variables utilisateur.
SELECT COUNT(*) INTO @cmd_avant FROM commande;
SELECT SUM(stock) INTO @stock_avant
  FROM produit WHERE reference IN ('TST-900','TST-901','TST-902');

-- Panier mixte : 1 exemplaire disponible (TST-902) + 999 en rupture (TST-900).
CALL sp_create_order_from_basket(
  (SELECT id_client FROM client WHERE email = 'bruno@example.com' LIMIT 1),
  '6 rue du Test T29',
  JSON_ARRAY(
    JSON_OBJECT('id_produit', (SELECT id_produit FROM produit WHERE reference = 'TST-902'), 'quantite', 1),
    JSON_OBJECT('id_produit', (SELECT id_produit FROM produit WHERE reference = 'TST-900'), 'quantite', 999)
  ),
  0, @new_cid, @new_num, @new_montant, @code);

SELECT CASE WHEN @code = 'STOCK_INSUFFISANT' THEN 'PASS_1_code_retour'
            ELSE CONCAT('FAIL_1_code_retour_', IFNULL(@code,'NULL'))
       END AS T29_code;

SELECT CASE WHEN @new_cid IS NULL THEN 'PASS_2_id_commande_null'
            ELSE CONCAT('FAIL_2_id_commande_non_null_', @new_cid)
       END AS T29_id_null;

SELECT CASE WHEN (SELECT COUNT(*) FROM commande) = @cmd_avant
            THEN 'PASS_3_pas_de_commande_creee'
            ELSE CONCAT('FAIL_3_ecart_commandes_',
                        (SELECT COUNT(*) FROM commande) - @cmd_avant)
       END AS T29_cmd;

SELECT CASE
        WHEN (SELECT stock FROM produit WHERE reference = 'TST-902') = 50
         AND (SELECT stock FROM produit WHERE reference = 'TST-900') = 5
        THEN 'PASS_4_stock_inchange'
        ELSE CONCAT('FAIL_4_stock_modifie : TST-900=',
                    (SELECT stock FROM produit WHERE reference='TST-900'),
                    ' TST-902=',
                    (SELECT stock FROM produit WHERE reference='TST-902'))
       END AS T29_stock;
