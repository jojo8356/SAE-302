-- =====================================================================
-- T-12 | RB-11 | transition EN_PREPARATION -> BROUILLON interdite
-- Attendu : ERREUR:RB11_TRANSITION_STATUT_INTERDITE
-- La matrice des transitions autorisees n'autorise pas a revenir en
-- BROUILLON depuis EN_PREPARATION. On prend une commande existante
-- deja dans l'etat EN_PREPARATION (CMD2026-000001) et on tente de la
-- repasser en BROUILLON.
-- =====================================================================
UPDATE commande
   SET statut = 'BROUILLON'
 WHERE numero = 'CMD2026-000001';
