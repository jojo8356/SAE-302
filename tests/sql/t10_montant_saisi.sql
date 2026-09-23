-- =====================================================================
-- T-10 | RB-15 | montant_total non saisissable (trigger)
-- Attendu : ERREUR:RB15_MONTANT_CALCULE_INTERDIT
-- On positionne une commande PAYEE (ex: CMD2026-000001 qui est deja
-- EN_PREPARATION) puis on tente d'en forcer le montant_total a une
-- valeur arbitraire (999.99) : le trigger BEFORE UPDATE
-- trg_commande_transition_statut doit SIGNAL RB15_MONTANT_CALCULE_INTERDIT.
-- Pour un controle fiable, on cree un brouillon, y ajoute une ligne
-- (le calcul est fait par la procedure), on le passe dans un etat ou
-- le controle est strict (EN_PREPARATION) puis on tente de forcer
-- le montant a la main.
-- =====================================================================
CALL sp_create_order(
  (SELECT id_client FROM client WHERE email = 'carla@example.com' LIMIT 1),
  '3 rue du Test T10',
  @cid, @cnum);

CALL sp_add_order_line(
  @cid,
  (SELECT id_produit FROM produit WHERE reference = 'TST-900'),
  1, @lid, @tot, @code_add);

-- Passage en EN_PREPARATION (statut autorise depuis BROUILLON) puis
-- tentative de saisie manuelle du montant :
UPDATE commande
   SET statut = 'EN_PREPARATION'
 WHERE id_commande = @cid;

UPDATE commande
   SET montant_total = 999.99
 WHERE id_commande = @cid;
