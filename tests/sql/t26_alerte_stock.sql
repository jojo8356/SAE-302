-- =====================================================================
-- T-26 | EF-ADM-05 | alertes stock par la vue + audit des ajustements
-- Attendu : OK
-- On verifie les trois etats possibles de la vue v_etat_stock :
--   - RUPTURE : POWER-010 (stock=0 dans le seed)
--   - TRES_BAS : MICRO-007 (stock=2, seuil_alerte=2 -> 2 <= 2), et
--     accessoirement TST-900 (fixture : stock=5, seuil_alerte=2) que
--     l'on ajuste temporairement a stock=1 via sp_adjust_stock.
--   - DISPONIBLE : le reste.
-- Les deux ajustements doivent aussi être conservés avec leur motif et leurs
-- valeurs avant/après dans l'audit SQL transactionnel.
-- =====================================================================

-- On force TST-900 a 1 exemplaire (< seuil_alerte=2) -> TRES_BAS
CALL sp_adjust_stock(
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  'SET', 1, 'test T-26 : passage sous le seuil',
  @s, @c);

SELECT CASE
        WHEN EXISTS (SELECT 1 FROM v_etat_stock WHERE etat = 'RUPTURE'
                       AND reference IN ('POWER-010','TST-901'))
         AND EXISTS (SELECT 1 FROM v_etat_stock WHERE etat = 'TRES_BAS')
         AND EXISTS (SELECT 1 FROM v_etat_stock WHERE etat = 'DISPONIBLE')
        THEN 'PASS_3_etats_presents'
        ELSE CONCAT('FAIL_manque_un_etat ',
                    'rupture=', (SELECT COUNT(*) FROM v_etat_stock WHERE etat='RUPTURE'),
                    ' tbas=',     (SELECT COUNT(*) FROM v_etat_stock WHERE etat='TRES_BAS'),
                    ' disp=',     (SELECT COUNT(*) FROM v_etat_stock WHERE etat='DISPONIBLE'))
       END AS T26_etats_result;

-- On restitue TST-900 a son stock initial (5), puis on contrôle les deux traces.
CALL sp_adjust_stock(
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  'SET', 5, 'test T-26 : restitution',
  @s2, @c2);

SELECT CASE
        WHEN @c = 'OK' AND @c2 = 'OK'
         AND (SELECT COUNT(*) FROM stock_movement_history
               WHERE reference_produit = 'TST-900'
                 AND motif IN ('test T-26 : passage sous le seuil', 'test T-26 : restitution')) = 2
         AND EXISTS (SELECT 1 FROM stock_movement_history
                      WHERE reference_produit = 'TST-900'
                        AND mode_mouvement = 'SET' AND quantite = 1
                        AND stock_avant = 5 AND stock_apres = 1
                        AND motif = 'test T-26 : passage sous le seuil')
         AND EXISTS (SELECT 1 FROM stock_movement_history
                      WHERE reference_produit = 'TST-900'
                        AND mode_mouvement = 'SET' AND quantite = 5
                        AND stock_avant = 1 AND stock_apres = 5
                        AND motif = 'test T-26 : restitution')
        THEN 'PASS_2_mouvements_audites'
        ELSE CONCAT('FAIL_audit_stock_incoherent mouvements=',
                    (SELECT COUNT(*) FROM stock_movement_history WHERE reference_produit='TST-900'))
       END AS T26_audit_result;
