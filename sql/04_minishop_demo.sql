-- =====================================================================
-- MiniShop — 04 : jeu de données de démonstration (commandes)
-- ---------------------------------------------------------------------
-- Les commandes sont créées EN PASSANT PAR LES PROCEDURES STOCKÉES :
-- c'est la seule façon d'exécuter réellement les triggers (snapshot du
-- prix, décrément de stock, historique des statuts).
-- Rejeu possible : le script 01 reconstruit la base de zéro.
-- 4 commandes : une validee sous franchise de port, un brouillon vide, une expediee,
-- et une petite commande qui PAYE les frais de port standard (demonstration ENF-14).
-- =====================================================================

-- Nom de la base : passe au client (`mariadb minishop < fichier`) par
-- scripts/load_db.sh ; en mode interactif, taper `USE minishop;` d'abord.

-- Client 1 : commande complète (2 x Casque Aura + 1 x Cable USB-C) validée
CALL sp_create_order(1, '12 rue de France, 06000 Nice', @id_c1, @num_c1);
CALL sp_add_order_line(@id_c1, 5, 2, @l, @t, @r);
CALL sp_add_order_line(@id_c1, 9, 1, @l, @t, @r);
-- validation par la procedure : c'est elle qui FIGE les frais de port et solde le
-- montant (RB-15, ENF-14). Marchandises = 324.60 EUR >= franchise de 80.00 EUR :
-- port offert (frais_port = 0.00) et montant_total = 324.60 EUR.
CALL sp_confirm_order(@id_c1, 1, 0, @mnt_c1, @r_c1);

-- Client 2 : panier en préparation (brouillon) — 1 x Batterie externe (stock 0)
CALL sp_create_order(2, '5 avenue Jean Médecin, 06000 Nice', @id_c2, @num_c2);

-- Client 3 : commande payée puis expédiée (statuts posés par UPDATE direct : le trigger
-- trg_commande_transition_statut contrôle la matrice RB-11 exactement comme la procédure)
CALL sp_create_order(3, '8 rue Papin, 06300 Nice', @id_c3, @num_c3);
CALL sp_add_order_line(@id_c3, 3, 1, @l, @t, @r);
CALL sp_add_order_line(@id_c3, 4, 2, @l, @t, @r);
UPDATE commande SET statut = 'EN_PREPARATION' WHERE id_commande = @id_c3;
UPDATE commande SET statut = 'PAYEE'          WHERE id_commande = @id_c3;
UPDATE commande SET statut = 'EXPEDIEE'       WHERE id_commande = @id_c3;

-- Client 1 (bis) : petit panier SOUS la franchise, pour que les frais de port soient
-- visibles dans la demonstration et dans le tableau de bord (4,90 EUR de livraison
-- standard ajoutes au montant) ; C1 et C3 beneficient en revanche de la franchise.
CALL sp_create_order(1, '12 rue de France, 06000 Nice', @id_c4, @num_c4);
CALL sp_add_order_line(@id_c4, 8, 1, @l, @t, @r);    -- 1 x Sac à dos 25 L (SAC-008) : 58.80 EUR TTC
CALL sp_confirm_order(@id_c4, 1, 0, @mnt_c4, @r_c4); -- 58.80 < 80.00 -> port 4.90, total 63.70

SELECT c.numero, cl.email, c.statut, c.montant_total, c.frais_port,
       (SELECT COUNT(*) FROM ligne_commande l WHERE l.id_commande = c.id_commande) AS lignes
  FROM commande c JOIN client cl ON cl.id_client = c.id_client
 ORDER BY c.id_commande;
