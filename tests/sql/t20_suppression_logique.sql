-- =====================================================================
-- T-20 | RB-09 | produit deja commande : bascule visible=0 (pas de suppression)
-- Attendu : OK
-- Le test construit son propre cas (commande + ligne TST-900), puis appelle
-- sp_delete_product : la procedure ne doit PAS supprimer la ligne (RB-14
-- l'interdit de toute facon) et doit passer visible=0 a la place.
-- Précédent fautif : le test visait PC-PORT-001 en supposant qu'il figurait
-- dans CMD2026-000001. Il n'y figure pas : la procedure supprimait donc
-- reellement le produit, ce qui (a) ne testait pas RB-09 et (b) retirait un
-- produit du jeu de demonstration pour tous les tests suivants.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '5 rue du Test T20',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code);

CALL sp_delete_product(
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'));

SELECT CASE
        WHEN EXISTS (SELECT 1 FROM produit WHERE reference = 'TST-900')
         AND (SELECT visible FROM produit WHERE reference = 'TST-900') = 0
        THEN 'PASS_produit_masque_et_non_supprime'
        ELSE CONCAT('FAIL_supprime_ou_visible : exist=',
                    IF(EXISTS(SELECT 1 FROM produit WHERE reference='TST-900'),'1','0'),
                    ' visible=', IFNULL((SELECT visible FROM produit WHERE reference='TST-900'),'NULL'))
       END AS T20_result;

-- On remet visible=1 pour ne pas affecter les tests suivants.
UPDATE produit SET visible = 1 WHERE reference = 'TST-900';
