-- =====================================================================
-- T-01 | RB-02 | prix HT = 0 refusé par le CHECK ck_produit_prix
-- Attendu : ERREUR:ck_produit_prix
-- La fixture a déjà créé la catégorie 'Cat test' (slug cat-test-t01) ;
-- on tente un INSERT d'un produit à prix_ht = 0, que le CHECK doit
-- rejeter avant toute écriture.
-- =====================================================================
-- Le produit TST-900 est déjà présent (fixture) ; on insère un nouveau
-- produit référencé à prix_ht = 0 pour faire tirer le CHECK DDL.
INSERT INTO produit
  (reference, nom, slug, description, prix_ht, tva, prix_ttc, stock,
   seuil_alerte, id_categorie, visible)
SELECT
  'TST-T01','Produit T01 prix nul','tst-t01','RB-02 : prix_ht=0 doit être refusé.',
  0.00, 20.00, 0.00, 10, 2,
  (SELECT id_categorie FROM categorie WHERE slug = 'cat-test-t01'),
  1;
