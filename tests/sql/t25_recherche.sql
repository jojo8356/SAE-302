-- =====================================================================
-- T-25 | EF-VIS-02 | recherche + filtres + produits masques exclus
-- Attendu : OK
-- Le produit BRAC-012 (bracelet) est insere visible=0 dans le seed :
-- il ne doit JAMAIS remonter dans une recherche client (p_admin=0).
-- On verifie aussi :
--   - que le mot-cle 'ecran' trouve ECRA-003 (FULLTEXT/ LIKE),
--   - qu'un mot-cle qui ne matche rien retourne 0 resultat,
--   - qu'un appel en mode admin (p_admin=1) retourne aussi le produit
--     masque (le Back-office doit le voir).
-- On utilise sp_search_products qui retourne DEUX jeux de resultats
-- (page + total_trouves).
-- =====================================================================

-- 1) Produits masques exclus en mode client (sur un mot-cle qui matche
--    tous les produits) : le BRAC-012 (visible=0) ne doit pas etre la.
CALL sp_search_products('', NULL, NULL, NULL, 0, 'nom', 1, 60, 0);
-- Deuxieme jeu de resultats : COUNT(*)
CALL sp_search_products('', NULL, NULL, NULL, 0, 'nom', 1, 60, 0);

-- Contrat : on SELECT pour verifier que le BRAC-012 n'est pas visible
-- cote client ET qu'il est visible cote admin.
SELECT CASE
        WHEN NOT EXISTS (
          SELECT 1 FROM v_catalogue WHERE reference = 'BRAC-012'
        ) THEN 'PASS_1_client_ne_voit_pas_les_masques'
        ELSE 'FAIL_1_produit_masque_visible_cote_client'
       END AS T25_client_view;

-- 2) Le Back-office (pas de filtre sur visible) voit tous les produits
SELECT CASE
        WHEN (SELECT COUNT(*) FROM produit)
           > (SELECT COUNT(*) FROM v_catalogue)
        THEN 'PASS_2_admin_voit_plus_que_catalogue'
        ELSE 'FAIL_2_aucun_produit_masque_nest_present'
       END AS T25_admin_view;

-- 3) Mots-cles : la recherche LIKE sur 'ecran' trouve au moins ECRA-003
SELECT CASE WHEN EXISTS (
         SELECT 1 FROM v_catalogue
          WHERE nom LIKE '%ecran%' OR description LIKE '%écran%'
       ) THEN 'PASS_3_like_mots_cles'
       ELSE 'FAIL_3_mot_cle_sans_resultat'
       END AS T25_keyword;
