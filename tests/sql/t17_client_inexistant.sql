-- =====================================================================
-- T-17 | RB-10 | commande rattachee a un client inexistant
-- Attendu : ERREUR:RB10_COMMANDE_SANS_CLIENT
-- Tentative d'INSERT direct dans COMMANDE avec un id_client farfelu
-- (999999, hors plage des ids du seed) : le trigger BEFORE INSERT
-- trg_commande_controle_insert doit SIGNAL RB10_COMMANDE_SANS_CLIENT
-- avant meme que la FK ne tire.
-- =====================================================================
INSERT INTO commande (numero, id_client, adresse_livraison, statut)
VALUES ('CMD-TST-T17', 999999, '11 rue du Test T17', 'BROUILLON');
