-- =====================================================================
-- T-08 | RB-18 | stock insuffisant : trg_ligne_controle_insert bloque
-- Attendu : ERREUR:STOCK_INSUFFISANT
-- Tentative d'INSERT direct d'une ligne avec quantite superieure au
-- stock disponible (produit TST-900 : stock 5, on demande 50).
-- Le trigger BEFORE INSERT trg_ligne_controle_insert doit SIGNAL.
-- On reutilise le brouillon vide de Bruno (CMD2026-000002).
-- =====================================================================
INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire, total_ligne)
SELECT c.id_commande, p.id_produit, 50, 12.00, 600.00
  FROM commande c JOIN produit p ON p.reference = 'TST-900'
 WHERE c.numero = 'CMD2026-000002';
