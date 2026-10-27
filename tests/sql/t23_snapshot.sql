-- =====================================================================
-- T-23 | RB-06 | le prix de la ligne survit a une hausse tarifaire ulterieure
-- Attendu : OK
-- Cree une commande pour alice, ajoute 1 exemplaire de TST-900 (prix_ttc
-- = 12.00 a l'achat). Monte ensuite le prix de TST-900 a 19.90 HT
-- (23.88 TTC) puis verifie que :
--   1. la ligne de commande affiche TOUJOURS le prix d'achat (12.00) ;
--   2. le produit, lui, a bien augmente (sinon le test ne prouve rien) ;
--   3. le montant de l'en-tete vaut marchandises + port (RB-15) — et non
--      les seules marchandises.
-- Précédent fautif : le test exigeait montant_total = 12.00, alors que la
-- commande est sous la franchise de 80.00 EUR : le port de 4.90 est
-- normalement ajoute (montant = 16.90). L'assertion contredisait donc
-- ENF-14, verifiee par T-28.
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
         AND (SELECT total_ligne   FROM ligne_commande WHERE id_ligne = @lid) = 12.00
         AND (SELECT prix_ttc      FROM produit        WHERE reference = 'TST-900') = 23.88
         AND (SELECT montant_total FROM commande       WHERE id_commande = @cid)
             = 12.00 + (SELECT frais_port FROM commande WHERE id_commande = @cid)
        THEN 'PASS_snapshot_prix_conserve'
        ELSE CONCAT('FAIL_prix_ligne=',
                    (SELECT prix_unitaire FROM ligne_commande WHERE id_ligne=@lid),
                    ' total_ligne=', (SELECT total_ligne FROM ligne_commande WHERE id_ligne=@lid),
                    ' prix_produit=', (SELECT prix_ttc FROM produit WHERE reference='TST-900'),
                    ' montant_cmd=', (SELECT montant_total FROM commande WHERE id_commande=@cid),
                    ' port=', (SELECT frais_port FROM commande WHERE id_commande=@cid))
       END AS T23_result;

-- On restitue le prix d'origine pour ne pas perturber les tests suivants.
UPDATE produit SET prix_ht = 10.00 WHERE reference = 'TST-900';
