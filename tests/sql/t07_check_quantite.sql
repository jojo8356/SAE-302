-- =====================================================================
-- T-07 | RB-05 | quantite = 0 refusee par le CHECK ck_ligne_quantite
-- Attendu : ERREUR:ck_ligne_quantite
-- Contournement de la procedure : INSERT direct d'une ligne avec
-- quantite = 0. Le CHECK DDL ck_ligne_quantite doit rejeter la ligne.
-- ATTENTION : le test creait sa propre commande. S'appuyer sur le brouillon
-- CMD2026-000002 du jeu de demonstration ne fonctionne pas : la fixture
-- supprime les brouillons vides au solde nul avant chaque test, donc le
-- INSERT ... SELECT ne trouvait aucune ligne et « reussissait » en n'ecrivant
-- rien — un faux PASS transforme en faux FAIL selon l'ordre des campagnes.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'bruno@example.com' LIMIT 1),
  '2 rue du Test T07',
  @cid, @cnum);

INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire, total_ligne)
SELECT @cid, p.id_produit, 0, 12.00, 0.00
  FROM produit p WHERE p.reference = 'TST-900';
