-- =====================================================================
-- fixture jouee par le harnais AVANT chaque test : etat deterministe.
-- Les lignes de commande des tests sont purgees VIA UNE TABLE
-- TEMPORAIRE : un `DELETE ... WHERE id_produit IN (SELECT ... produit)`
-- echouerait (la table PRODUIT serait lue par la requete declenchante et
-- modifiee par trg_ligne_restore_stock).
-- =====================================================================
-- Etat de reference des stocks : la campagne rejoue la fixture AVANT CHAQUE test, donc
-- un stock diminue par un test precedent doit etre restitue ; sinon les tests finissent
-- par echouer pour une raison sans rapport avec la regle testee (stock epuise par
-- l'accumulation des reservations). `stock_initial` porte la quantite du seed, renseignee
-- a l'insertion par trg_produit_ttc.
UPDATE produit SET stock_initial = stock WHERE stock_initial < 0;
UPDATE produit SET stock = stock_initial;

INSERT IGNORE INTO categorie (nom, slug, description)
  VALUES ('Cat test','cat-test-t01','categorie de test pour RB-14 / RB-18');

INSERT IGNORE INTO produit (reference, nom, slug, description, prix_ht, prix_ttc, stock, seuil_alerte, id_categorie, visible)
  VALUES ('TST-900','Produit test 900','tst-900','Stock 5, prix HT 10.00 / TTC 12.00 (attendu par les tests).',
          10.00, 12.00, 5, 2, (SELECT id_categorie FROM categorie WHERE slug='cat-test-t01'), 1),
         ('TST-901','Produit test 901','tst-901','Stock 0 : produit en rupture.',
          20.00, 24.00, 0, 1, (SELECT id_categorie FROM categorie WHERE slug='cat-test-t01'), 1),
         ('TST-902','Produit test 902','tst-902','Prix HT 60.00 / TTC 72.00 : sert aux tests de frais de port (T-28).',
          60.00, 72.00, 50, 5, (SELECT id_categorie FROM categorie WHERE slug='cat-test-t01'), 1);

CREATE TEMPORARY TABLE IF NOT EXISTS tmp_lignes_test (id_ligne BIGINT UNSIGNED PRIMARY KEY);
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_cmd_test    (id_commande INT UNSIGNED PRIMARY KEY);
TRUNCATE tmp_lignes_test; TRUNCATE tmp_cmd_test;

INSERT IGNORE INTO tmp_cmd_test
  SELECT id_commande FROM commande WHERE numero LIKE 'TMP' OR numero LIKE 'CMD-TST%';
INSERT IGNORE INTO tmp_lignes_test
  SELECT l.id_ligne FROM ligne_commande l
    JOIN produit p ON p.id_produit = l.id_produit AND p.reference LIKE 'TST-%'
   UNION
  SELECT l.id_ligne FROM ligne_commande l JOIN tmp_cmd_test t ON t.id_commande = l.id_commande;

DELETE FROM ligne_commande WHERE id_ligne IN (SELECT id_ligne FROM tmp_lignes_test);
DELETE FROM commande        WHERE id_commande IN (SELECT id_commande FROM tmp_cmd_test);
TRUNCATE tmp_lignes_test; TRUNCATE tmp_cmd_test;

-- Purge des brouillons sans ligne laisses par les tests precedents. Un brouillon
-- vide conserve son montant_total tant qu'on ne le corrige pas ; le trigger RB-15
-- refuserait alors la normalisation ci-dessous et l'erreur se propagerait a TOUS les
-- tests suivants (effet de bord en chaine). Un brouillon sans ligne n'a aucune valeur
-- metier (RB-04 n'est exige qu'a la validation) : on le supprime proprement.
INSERT IGNORE INTO tmp_cmd_test
  SELECT c.id_commande FROM commande c
   WHERE c.statut = 'BROUILLON'
     AND NOT EXISTS (SELECT 1 FROM ligne_commande l WHERE l.id_commande = c.id_commande)
     AND (c.montant_total <> 0 OR c.frais_port <> 0);
DELETE FROM commande WHERE id_commande IN (SELECT id_commande FROM tmp_cmd_test);
TRUNCATE tmp_cmd_test;

-- Dernier filet : un brouillon vide et au solde nul est un residu de test (le seed n'en
-- produit qu'un seul, inoffensif) ; le supprimer garde la base dans un etat ou RB-15 ne
-- peut plus refuser la normalisation ci-dessous.
INSERT IGNORE INTO tmp_cmd_test
  SELECT c.id_commande FROM commande c
   WHERE c.statut = 'BROUILLON' AND c.montant_total = 0 AND c.frais_port = 0
     AND NOT EXISTS (SELECT 1 FROM ligne_commande l WHERE l.id_commande = c.id_commande);
DELETE FROM commande WHERE id_commande IN (SELECT id_commande FROM tmp_cmd_test);
TRUNCATE tmp_cmd_test;

UPDATE produit SET prix_ht = 10.00, stock = 5, visible = 1, seuil_alerte = 2
 WHERE reference = 'TST-900';
UPDATE produit SET prix_ht = 20.00, stock = 0, visible = 1 WHERE reference = 'TST-901';
UPDATE produit SET prix_ht = 60.00, stock = 50, visible = 1 WHERE reference = 'TST-902';

-- Un brouillon sans ligne avec un port figure est un residu de test : on le retire
-- avant de normaliser (le trigger interdit de « reparer » son montant sans corriger
-- le port dans la meme instruction, et un residu bloquerait tous les tests suivants).
INSERT IGNORE INTO tmp_cmd_test
  SELECT c.id_commande FROM commande c
   WHERE c.statut = 'BROUILLON' AND c.frais_port > 0
     AND NOT EXISTS (SELECT 1 FROM ligne_commande l WHERE l.id_commande = c.id_commande);
DELETE FROM commande WHERE id_commande IN (SELECT id_commande FROM tmp_cmd_test);
TRUNCATE tmp_cmd_test;

-- Normalisation des montants des commandes rescapées : le retrait des lignes de test
-- est fait par DELETE direct (la sous-requête sur produit est interdite à cause du
-- trigger qui écrit sur produit), donc `trg_ligne_decrement_stock` et le recalcul du
-- total n'ont pas pu se déclencher. On remet montant_total = somme des lignes pour
-- qu'aucun test ne bute sur une incohérence créée par la fixture elle-même.
-- On remet FRAIS_PORT ET montant_total dans la meme instruction : le trigger
-- trg_commande_transition_statut verifie « montant = somme des lignes + port » a
-- partir de NEW.frais_port ; changer le port tout seul violerait done RB-15 et
-- bloquerait la fixture (et, par contagion, les tests suivants).
UPDATE commande c
   SET c.frais_port = 0.00,
       c.montant_total = (SELECT COALESCE(SUM(l.total_ligne), 0)
                            FROM ligne_commande l WHERE l.id_commande = c.id_commande)
 WHERE c.statut = 'BROUILLON'
   AND c.montant_total <> (SELECT COALESCE(SUM(l.total_ligne), 0)
                             FROM ligne_commande l WHERE l.id_commande = c.id_commande)
       + c.frais_port;
UPDATE commande c
   SET c.montant_total = (SELECT COALESCE(SUM(l.total_ligne), 0)
                            FROM ligne_commande l WHERE l.id_commande = c.id_commande)
                        + c.frais_port
 WHERE c.statut <> 'BROUILLON'
   AND c.montant_total <> (SELECT COALESCE(SUM(l.total_ligne), 0)
                             FROM ligne_commande l WHERE l.id_commande = c.id_commande)
       + c.frais_port;
