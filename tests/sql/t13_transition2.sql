-- =====================================================================
-- T-13 | RB-11 | transition EXPEDIEE -> BROUILLON interdite
-- Attendu : ERREUR:RB11_TRANSITION_STATUT_INTERDITE
-- La matrice n'autorise, depuis EXPEDIEE, que LIVREE ou ANNULEE.
-- On prend CMD2026-000003 (Carla, EXPEDIEE) et on tente de la remettre
-- en BROUILLON : trg_commande_transition_statut doit SIGNAL.
-- =====================================================================
UPDATE commande
   SET statut = 'BROUILLON'
 WHERE numero = 'CMD2026-000003';
