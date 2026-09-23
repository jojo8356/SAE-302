-- =====================================================================
-- T-23 | RB-06 | le prix de la ligne survit a une hausse tarifaire ulterieure
-- Attendu : OK
-- Cree une commande pour alice, ajoute 1 exemplaire de TST-900 (prix_ttc
-- = 12.00 a l'achat). Monte ensuite le prix de TST-900 a 19.90 HT
-- (23.88 TTC) puis verifie que la ligne de commande affiche TOUJOURS
-- l'ancien prix snapshot (12.00) et que le total de la commande
-- (brouillon) est base sur le snapshot et non sur le nouveau prix.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '15 rue du Test T23',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code_a);

-- Hausse du prix produit (back-office) : 19.90 HT -> 23.88 TTC
UPDATE produit SET prix_ht = 19.90 WHERE reference = 'TST-900';

SELECT CASE
        WHEN (SELECT prix_unitaire FROM ligne_commande WHERE id_ligne = @lid) = 12.00
         AND (SELECT total_ligne  FROM ligne_commande WHERE id_ligne = @lid) = 12.00
         AND (SELECT montant_total FROM commande       WHERE id_commande = @cid) = 12.00
        THEN 'PASS_snapshot_prix_conserve'
        ELSE CONCAT('FAIL_prix_ligne=',
                    (SELECT prix_unitaire FROM ligne_commande WHERE id_ligne=@lid),
                    ' total_ligne=', (SELECT total_ligne FROM ligne_commande WHERE id_ligne=@lid),
                    ' montant_cmd=', (SELECT montant_total FROM commande WHERE id_commande=@cid))
       END AS T23_result;

-- On restitue le prix d'origine pour ne pas perturber les tests suivants.
UPDATE produit SET prix_ht = 10.00 WHERE reference = 'TST-900';
