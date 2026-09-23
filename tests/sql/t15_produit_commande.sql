-- =====================================================================
-- T-15 | RB-14 | suppression d'un produit deja commande
-- Attendu : ERREUR:PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER
-- PC-PORT-001 (portable Zen 14) a ete commande dans CMD2026-000001 :
-- tenter un DELETE direct doit etre rejete par trg_produit_delete.
-- =====================================================================
DELETE FROM produit
 WHERE reference = 'PC-PORT-001';
