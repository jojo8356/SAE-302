-- =====================================================================
-- T-20 | RB-09 | produit deja commande : bascule visible=0 (pas de suppression)
-- Attendu : OK
-- CASQ-005 a deja ete commande (CMD2026-000001 ligne 2 si la demo
-- a tourne ; on choisit un produit dont on est certain : PC-PORT-001
-- est present dans CMD2026-000001). On appelle sp_delete_product :
-- la procedure ne doit pas supprimer la ligne (trigger l'interdirait
// de toute facon), mais doit passer visible=0 a la place.
-- =====================================================================
CALL sp_delete_product(
  (SELECT id_produit FROM produit WHERE reference = 'PC-PORT-001'));

SELECT CASE
        WHEN EXISTS (SELECT 1 FROM produit WHERE reference = 'PC-PORT-001')
         AND (SELECT visible FROM produit WHERE reference = 'PC-PORT-001') = 0
        THEN 'PASS_produit_masque_et_non_supprime'
        ELSE CONCAT('FAIL_supprime_ou_visible : exist=',
                    IF(EXISTS(SELECT 1 FROM produit WHERE reference='PC-PORT-001'),'1','0'),
                    ' visible=', IFNULL((SELECT visible FROM produit WHERE reference='PC-PORT-001'),'NULL'))
       END AS T20_result;

-- On remet visible=1 pour ne pas affecter les tests suivants.
UPDATE produit SET visible = 1 WHERE reference = 'PC-PORT-001';
