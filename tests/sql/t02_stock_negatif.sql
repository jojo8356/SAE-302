-- =====================================================================
-- T-02 | RB-03 | stock negatif refuse par le CHECK ck_produit_stock
-- Attendu : ERREUR:ck_produit_stock
-- Un INSERT de produit avec stock < 0 ne passe jamais le CHECK
-- (il n'existe pas de BEFORE INSERT trigger qui produirait un message
-- métier sur le stock ; c'est donc bien le CHECK DDL qui répond).
-- =====================================================================
INSERT INTO produit
  (reference, nom, slug, description, prix_ht, tva, prix_ttc, stock,
   seuil_alerte, id_categorie, visible)
SELECT
  'TST-T02','Produit T02 stock negatif','tst-t02',
  'RB-03/CHECK : stock = -10 doit etre refuse.',
  10.00, 20.00, 12.00, -10, 2,
  (SELECT id_categorie FROM categorie WHERE slug = 'cat-test-t01'),
  1;
