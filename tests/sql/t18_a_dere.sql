-- =====================================================================
-- T-18 | SEC-08 | sp_confirm_order refuse l'acces a la commande d'un autre
-- Attendu : ERREUR:ACCES_NON_AUTORISE
-- Cree un brouillon pour alice, ajoute une ligne, puis tente de le
-- valider en passant p_id_client = l'id de bruno (tentative de
-- confirmation de la commande d'autrui). La procedure doit SIGNAL
-- ACCES_NON_AUTORISE.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '12 rue du Test T18',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code_add);

CALL sp_confirm_order(@cid,
  (SELECT id_client FROM client WHERE email = 'bruno@example.com' LIMIT 1),
  0, @montant, @code_confirm);
