-- =====================================================================
-- T-08 | RB-18 | stock insuffisant : trg_ligne_controle_insert bloque
-- Attendu : ERREUR:STOCK_INSUFFISANT
-- Tentative d'INSERT direct d'une ligne avec quantite superieure au
-- stock disponible (produit TST-900 : stock 5, on demande 50).
-- Le trigger BEFORE INSERT trg_ligne_controle_insert doit SIGNAL.
-- La commande est creee par le test : le brouillon vide du jeu de
-- demonstration (CMD2026-000002) est supprime par la fixture, et un
-- INSERT ... SELECT sans ligne cible « reussit » sans rien controler.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'bruno@example.com' LIMIT 1),
  '3 rue du Test T08',
  @cid, @cnum);

INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire, total_ligne)
SELECT @cid, p.id_produit, 50, 12.00, 600.00
  FROM produit p WHERE p.reference = 'TST-900';
