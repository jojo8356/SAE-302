-- =====================================================================
-- T-15 | RB-14 | suppression d'un produit deja commande
-- Attendu : ERREUR:PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER
-- Le test se construit son propre cas : il cree une commande, y place
-- TST-900, puis tente un DELETE direct — trg_produit_delete doit SIGNAL.
-- Précédent fautif : le test supposait PC-PORT-001 présent dans
-- CMD2026-000001, or le jeu de démonstration commande CASQ-005 et
-- CABLE-009 (CMD2026-000001) puis ECRA-003 et CLAV-004 (CMD2026-000003).
-- Le DELETE réussissait donc, et le test échouait pour une raison sans
-- rapport avec RB-14.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '4 rue du Test T15',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code);

DELETE FROM produit WHERE reference = 'TST-900';
