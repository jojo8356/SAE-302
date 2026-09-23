-- =====================================================================
-- T-07 | RB-05 | quantite = 0 refusee par le CHECK ck_ligne_quantite
-- Attendu : ERREUR:ck_ligne_quantite
-- Contournement de la procedure : INSERT direct d'une ligne avec
-- quantite = 0. Le CHECK DDL ck_ligne_quantite doit rejeter la ligne.
-- On reutilise le brouillon CMD2026-000002 (bruno, BROUILLON, 0 ligne)
-- pour ne pas dependre de l'existence d'une commande creee par T-06.
-- =====================================================================
INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire, total_ligne)
SELECT c.id_commande, p.id_produit, 0, 12.00, 0.00
  FROM commande c JOIN produit p ON p.reference = 'TST-900'
 WHERE c.numero = 'CMD2026-000002';
