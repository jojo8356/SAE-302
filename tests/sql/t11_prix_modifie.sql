-- =====================================================================
-- T-11 | RB-06 | prix unitaire non modifiable (immutabilite)
-- Attendu : ERREUR:RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE
-- Une fois inseree, une ligne de commande ne doit plus pouvoir etre
-- modifiee ni en prix, ni en quantite, ni en produit (trg_ligne_immutable,
-- BEFORE UPDATE). On cree une commande + 1 ligne, puis on tente
-- d'ecraser le prix_unitaire.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '4 rue du Test T11',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  2, @lid, @tot, @code_add);

-- Tentative d'UPDATE du prix de la ligne qui vient d'etre inseree :
UPDATE ligne_commande
   SET prix_unitaire = 0.01
 WHERE id_ligne = @lid;
