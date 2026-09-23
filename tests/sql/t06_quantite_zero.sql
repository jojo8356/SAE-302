-- =====================================================================
-- T-06 | RB-05 | quantite = 0 refusee par sp_add_order_line
-- Attendu : ERREUR:RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO
-- Cree un brouillon, puis tente d'ajouter une ligne avec quantite = 0 :
-- la procedure sp_add_order_line doit SIGNAL avant toute ecriture.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '1 rue du Test T06',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  0,    -- quantite nulle : doit etre refusee
  @lid, @tot, @code);
