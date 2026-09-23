-- =====================================================================
-- T-26 | EF-ADM-05 | alerte stock (RUPTURE / TRES_BAS) par la vue
-- Attendu : OK
-- On verifie les trois etats possibles de la vue v_etat_stock :
--   - RUPTURE : POWER-010 (stock=0 dans le seed)
--   - TRES_BAS : MICRO-007 (stock=2, seuil_alerte=1 — non, le seed
--     fixe MICRO a stock=2 seuil=1 -> 2 > 1 donc DISPONIBLE). On
--     utilise le produit TST-900 (fixture : stock=5, seuil_alerte=2)
--     que l'on ajuste temporairement a stock=1 via sp_adjust_stock
--     (DELTA -4) pour tomber sous le seuil.
--   - DISPONIBLE : le reste.
-- Le test est OK si les trois etats existent bien sur la vue.
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
       END AS T26_result;

-- On restitue TST-900 a son stock initial (5) pour ne pas casser T-08/T-09
CALL sp_adjust_stock(
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  'SET', 5, 'test T-26 : restitution',
  @s2, @c2);
