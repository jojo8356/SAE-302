-- =====================================================================
-- T-28 | ENF-14 | frais de port calcules avant validation, figures
--                 a la validation, non saisissables
-- Attendu : ERREUR:RB15_MONTANT_CALCULE_INTERDIT
-- 1. On prepare un scenario : commande en brouillon, une ligne avec le
--    produit cher TST-902 (prix TTC 72), x1 = 72 < franchise 80
--    => frais de port = 4.90 attendus apres validation.
-- 2. On tente de FORCER frais_port = 0 APRES validation
--    (doit etre refuse par trg_commande_transition_statut, car cela
--    casserait la coherence montant_total = SUM(lignes) + port).
-- 3. Le tout doit finir sur l'ERREUR attendue.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  '5 rue du Test T28',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-902'),
  1, @lid, @tot, @code_a);

-- Validation : le port doit etre figure (4.90)
CALL sp_confirm_order(@cid,
  (SELECT id_client FROM client WHERE email = 'alice@example.com' LIMIT 1),
  0, @montant, @c_code);

-- Verif prealable (n'arretera pas le test si ca echoue, on veut
-- l'ERREUR ci-dessous)
SELECT CASE WHEN (SELECT frais_port FROM commande WHERE id_commande = @cid) = 4.90
            THEN 'PRE_PORT_FIGURE_4_90'
            ELSE CONCAT('PRE_PORT_INATTENDU_',
                        (SELECT frais_port FROM commande WHERE id_commande = @cid))
       END AS T28_pre;

-- Tentative de saisie manuelle du port (doit etre refusee) :
UPDATE commande SET frais_port = 0.00 WHERE id_commande = @cid;
