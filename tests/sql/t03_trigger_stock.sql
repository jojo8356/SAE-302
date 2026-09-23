-- =====================================================================
-- T-03 | RB-03 | stock negatif refuse par trg_produit_regles
-- Attendu : ERREUR:RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF
-- Un UPDATE direct mettant stock a -1 passe d'abord par le trigger
-- BEFORE UPDATE trg_produit_regles, qui emet le message metier
-- explicite RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF avant que le CHECK
-- DDL ne puisse repondre par son nom de contrainte.
-- =====================================================================
UPDATE produit
   SET stock = -1
 WHERE reference = 'TST-900';
