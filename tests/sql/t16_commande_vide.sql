-- =====================================================================
-- T-16 | RB-04 | validation d'une commande sans ligne
-- Attendu : ERREUR:RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE
-- On cree un brouillon pour alice, puis on tente de le valider avec
-- sp_confirm_order SANS ajouter de ligne. La procedure doit SIGNAL.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '10 rue du Test T16',
  @cid, @cnum);

CALL sp_confirm_order(@cid,
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  0, @montant, @code);
