-- =====================================================================
-- T-14 | RB-14 | suppression d'une categorie non vide
-- Attendu : ERREUR:CATEGORIE_NON_VIDE
-- On tente un DELETE direct sur la categorie 'Informatique'
-- (id_categorie porte 12 produits du seed) : le trigger BEFORE DELETE
-- trg_categorie_delete doit SIGNAL CATEGORIE_NON_VIDE.
-- (sp_delete_category emet un message plus explicite
-- CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS ; on vise ici le trigger.)
-- =====================================================================
DELETE FROM categorie
 WHERE slug = 'informatique';
