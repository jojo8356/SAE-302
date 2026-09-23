-- =====================================================================
-- T-27 | EF-VIS-01 | les 3 vues existent et sont coherentes
-- Attendu : OK
-- Les 3 vues sont : v_etat_stock, v_catalogue, v_commandes_client.
-- On verifie :
--   1. elles sont bien declarees dans information_schema.VIEWS ;
--   2. v_catalogue ne contient que des produits visibles (pas BRAC-012) ;
--   3. v_catalogue expose l'etat de stock (coherence avec v_etat_stock) ;
--   4. v_commandes_client expose bien le nom du client.
-- =====================================================================

SELECT CASE
        WHEN (SELECT COUNT(*) FROM information_schema.VIEWS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME IN ('v_etat_stock','v_catalogue','v_commandes_client')) = 3
        THEN 'PASS_1_trois_vues_definies'
        ELSE 'FAIL_1_vues_manquantes'
       END AS T27_vues;

SELECT CASE
        WHEN NOT EXISTS (
          SELECT 1 FROM v_catalogue WHERE reference = 'BRAC-012'
        ) THEN 'PASS_2_catalogue_exclut_les_masques'
        ELSE 'FAIL_2_catalogue_voit_le_bracelet_masque'
       END AS T27_catalogue;

SELECT CASE
        WHEN NOT EXISTS (
          SELECT 1 FROM v_catalogue c
            LEFT JOIN v_etat_stock e ON e.id_produit = c.id_produit
           WHERE e.id_produit IS NULL
        ) THEN 'PASS_3_catalogue_joint_etat_stock'
        ELSE 'FAIL_3_catalogue_et_etat_stock_incoherents'
       END AS T27_coherence;

SELECT CASE
        WHEN EXISTS (
          SELECT 1 FROM v_commandes_client WHERE client LIKE '%Alice%'
        ) THEN 'PASS_4_commandes_client_liste_alice'
        ELSE 'FAIL_4_v_commandes_client_incomplete'
       END AS T27_cmd_client;
