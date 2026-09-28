<?php

declare(strict_types=1);

/**
 * MiniShop — suite de tests des règles de gestion sur le moteur de données
 * (portage PHP du manifeste tests/sql/manifest.txt).
 *
 * Les 29 tests T-01…T-29 rejouent EXACTEMENT les scénarios de la campagne
 * SQL documentée (docs/02-document-tests-validation.md §2), en court-circuitant
 * volontairement l'application : écritures directes dans le magasin de données
 * et appels aux « procédures » (Repository). La conformité prouve que les
 * règles RB-01…RB-20 ne dépendent pas des contrôleurs ni des vues.
 *
 * Chaque test repart d'un magasin NEUF (fixture d'état avant chaque test,
 * comme le harnais SQL) dans un bac à sable temporaire : aucun test ne
 * dépend d'un autre, la suite est rejouable à volonté.
 *
 * Deux divergences documentées (cf. docs/04, choix D-08) :
 *   - T-09 : le portage JSON lève BusinessError('STOCK_INSUFFISANT') au lieu
 *     de rendre un code OUT — le test vérifie le refus ET l'absence de
 *     written partiel (équivalent strict) ;
 *   - T-19 : idem pour RB03 via adjustStock.
 *
 * Usage :  php tests/php/run_tests.php          (exit 0 = 29/29 conformes)
 *         node scripts/wasm_run.mjs tests/php/run_tests.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\ConstraintError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Model\Data\Views;
use App\Repository\CategorieRepository;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ProduitRepository;

// ------------------------------------------------------------- harnais

/** Magasin neuf + seed + produit de fixture TST-900 (comme la fixture SQL). */
function magasinNeuf(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_t' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        array_map('unlink', glob($dossier . '/*.json') ?: []);
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);
    (new ProduitRepository($store))->saveProduct(
        null, 'TST-900', 'Produit de fixture', 'produit-de-fixture',
        'Fixture de test (stock 5, seuil 2, 10,00 € HT)', 10.00, 20.0, 5, 2, 1, true, null
    );

    return $store;
}

/** Identifiant du produit TST-900 dans le magasin. */
function idTst(StoreInterface $store): int
{
    return (int) $store->findOne('produit', [Filter::eq('reference', 'TST-900')])['id_produit'];
}

/** Commande BROUILLON pour un client, avec lignes optionnelles [ref => qte]. */
function commandeAvec(StoreInterface $store, string $email, array $lignes = []): array
{
    $client = $store->findOne('client', [Filter::eq('email', $email)]);
    $repo = CommandeRepository::for($store);
    $creation = $repo->createOrder((int) $client['id_client'], 'Fixture, 06000 Nice');
    foreach ($lignes as $reference => $qte) {
        $produit = $store->findOne('produit', [Filter::eq('reference', $reference)]);
        $repo->addOrderLine((int) $creation['id_commande'], (int) $produit['id_produit'], (int) $qte);
    }

    return $repo->find((int) $creation['id_commande']);
}

$echecs = 0;
$compte = 0;
$journal = [];

/**
 * @param array{regle: string, attendu: string, travail: callable(StoreInterface): void} $test
 */
function jouer(string $id, array $test): void
{
    global $echecs, $compte, $journal;
    ++$compte;
    $store = magasinNeuf();
    $probleme = null;
    try {
        ($test['travail'])($store);
        if (str_starts_with($test['attendu'], 'ERREUR:')) {
            $probleme = 'aucune erreur levée (attendu : refus ' . substr($test['attendu'], 7) . ')';
        }
    } catch (ConstraintError $e) {
        if (($test['attendu'] ?? '') !== ('ERREUR:' . 'x')) {
            $motif = substr($test['attendu'], 7);
            if (!str_contains($test['attendu'], $motif) || !str_contains($e->getMessage(), $motif)) {
                $probleme = 'contrainte « ' . $e->getMessage() . ' » ≠ attendu « ' . $motif . ' »';
            }
        }
    } catch (BusinessError $e) {
        if (!str_starts_with($test['attendu'], 'ERREUR:')) {
            $probleme = 'erreur inattendue ' . $e->businessCode;
        } else {
            $motif = substr($test['attendu'], 7);
            if (!str_contains($e->businessCode, $motif) && !str_contains($e->getMessage(), $motif)) {
                $probleme = 'code « ' . $e->businessCode . ' » ≠ attendu « ' . $motif . ' »';
            }
        }
    } catch (Throwable $e) {
        $probleme = get_class($e) . ' non prévue : ' . $e->getMessage();
    }
    // les tests OK peuvent porter leurs propres assertions internes via $GLOBALS['assert']
    if ($probleme === null && ($GLOBALS['assert_echec'] ?? null) !== null) {
        $probleme = $GLOBALS['assert_echec'];
    }
    $GLOBALS['assert_echec'] = null;

    $ok = $probleme === null;
    if (!$ok) {
        ++$echecs;
    }
    $journal[] = sprintf('| %s | %s | %s | %s |', $id, $test['regle'], $ok ? '**conforme**' : '**NON CONFORME** — ' . $probleme, $test['attendu']);
    echo ($ok ? '  ✅ ' : '  ❌ ') . $id . ' (' . $test['regle'] . ') ' . ($ok ? '' : '— ' . $probleme) . "\n";
}

/** Assertion interne d'un test « OK » (équivalent des PASS du harnais SQL). */
function passe(string $libelle, bool $condition): void
{
    if (!$condition && ($GLOBALS['assert_echec'] ?? null) === null) {
        $GLOBALS['assert_echec'] = $libelle;
    }
}

// ------------------------------------------------------------- T-01…T-29

echo "MiniShop — règles de gestion sur le moteur de données (pilote actif : JSON)\n";
echo "Fixture neuve avant chaque test — 29 scénarios du manifeste SQL rejoués.\n\n";

jouer('T-01', ['regle' => 'RB-02', 'attendu' => 'ERREUR:ck_produit_prix', 'travail' => static function (StoreInterface $store): void {
    // INSERT produit avec prix HT = 0 : le CHECK ck_produit_prix refuse
    $store->insert('produit', [
        'reference' => 'TST-901', 'nom' => 'Prix nul', 'slug' => 'prix-nul',
        'description' => 'x', 'prix_ht' => 0, 'tva' => 20, 'stock' => 1,
        'seuil_alerte' => 1, 'id_categorie' => 1, 'visible' => true,
    ]);
}]);

jouer('T-02', ['regle' => 'RB-03', 'attendu' => 'ERREUR:ck_produit_stock', 'travail' => static function (StoreInterface $store): void {
    // INSERT direct avec stock négatif : le CHECK ck_produit_stock refuse
    $store->insert('produit', [
        'reference' => 'TST-902', 'nom' => 'Stock négatif', 'slug' => 'stock-negatif',
        'description' => 'x', 'prix_ht' => 5, 'tva' => 20, 'stock' => -3,
        'seuil_alerte' => 1, 'id_categorie' => 1, 'visible' => true,
    ]);
}]);

jouer('T-03', ['regle' => 'RB-03', 'attendu' => 'ERREUR:RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF', 'travail' => static function (StoreInterface $store): void {
    // UPDATE de stock négatif : le déclencheur trg_produit_regles refuse
    $store->update('produit', [Filter::eq('reference', 'TST-900')], ['stock' => -1]);
}]);

jouer('T-04', ['regle' => 'RB-01', 'attendu' => 'ERREUR:uk_client_email', 'travail' => static function (StoreInterface $store): void {
    // email déjà pris : la contrainte d'unicité uk_client_email refuse
    $store->insert('client', [
        'nom' => 'Dupont', 'prenom' => 'Hector', 'email' => 'alice@example.com',
        'mot_de_passe_hash' => str_repeat('x', 60),
    ]);
}]);

jouer('T-05', ['regle' => 'RB-12', 'attendu' => 'ERREUR:HASH_MOT_DE_PASSE_INVALIDE', 'travail' => static function (StoreInterface $store): void {
    // sp_create_account refuse un mot de passe non haché (< 60 caractères)
    (new ClientRepository($store))->createAccount('Dupont', 'Hector', 'hector@example.com', 'clair');
}]);

jouer('T-06', ['regle' => 'RB-05', 'attendu' => 'ERREUR:RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO', 'travail' => static function (StoreInterface $store): void {
    // sp_add_order_line refuse quantité = 0
    $commande = commandeAvec($store, 'alice@example.com');
    (CommandeRepository::for($store))->addOrderLine((int) $commande['id_commande'], idTst($store), 0);
}]);

jouer('T-07', ['regle' => 'RB-05', 'attendu' => 'ERREUR:ck_ligne_quantite', 'travail' => static function (StoreInterface $store): void {
    // INSERT direct d'une ligne quantité = 0 : le CHECK refuse
    $commande = commandeAvec($store, 'alice@example.com');
    $store->insert('ligne_commande', [
        'id_commande' => (int) $commande['id_commande'], 'id_produit' => idTst($store),
        'quantite' => 0, 'prix_unitaire' => 12.00,
    ]);
}]);

jouer('T-08', ['regle' => 'RB-18', 'attendu' => 'ERREUR:STOCK_INSUFFISANT', 'travail' => static function (StoreInterface $store): void {
    // stock insuffisant : le déclencheur de contrôle d'insertion bloque
    $commande = commandeAvec($store, 'alice@example.com');
    (CommandeRepository::for($store))->addOrderLine((int) $commande['id_commande'], idTst($store), 999);
}]);

jouer('T-09', ['regle' => 'RB-18', 'attendu' => 'OK (divergence D-08 : exception)', 'travail' => static function (StoreInterface $store): void {
    // stock insuffisant : refus SANS écriture partielle (rollback intégral)
    $commande = commandeAvec($store, 'alice@example.com');
    $avant = $store->count('ligne_commande');
    $stockAvant = (int) $store->find('produit', idTst($store))['stock'];
    try {
        (CommandeRepository::for($store))->addOrderLine((int) $commande['id_commande'], idTst($store), 999);
        passe('STOCK_INSUFFISANT devait être refusé', false);
    } catch (BusinessError $e) {
        passe('code STOCK_INSUFFISANT', $e->businessCode === 'STOCK_INSUFFISANT');
    }
    passe('aucune ligne ajoutée', $store->count('ligne_commande') === $avant);
    passe('stock intact', (int) $store->find('produit', idTst($store))['stock'] === $stockAvant);
    passe('montant du brouillon intact', (float) $store->find('commande', (int) $commande['id_commande'])['montant_total'] === 0.0);
}]);

jouer('T-10', ['regle' => 'RB-15', 'attendu' => 'ERREUR:RB15_MONTANT_CALCULE_INTERDIT', 'travail' => static function (StoreInterface $store): void {
    // montant_total non saisissable : le déclencheur refuse toute valeur forgée
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]); // montant 12.00 + port
    $store->update('commande', [Filter::eq('id_commande', (int) $commande['id_commande'])], ['montant_total' => 999.99]);
}]);

jouer('T-11', ['regle' => 'RB-06', 'attendu' => 'ERREUR:RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE', 'travail' => static function (StoreInterface $store): void {
    // immuabilité du prix d'une ligne après achat
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    $ligne = $store->findOne('ligne_commande', [Filter::eq('id_commande', (int) $commande['id_commande'])]);
    $store->update('ligne_commande', [Filter::eq('id_ligne', (int) $ligne['id_ligne'])], ['prix_unitaire' => 0.01]);
}]);

jouer('T-12', ['regle' => 'RB-11', 'attendu' => 'ERREUR:RB11_TRANSITION_STATUT_INTERDITE', 'travail' => static function (StoreInterface $store): void {
    // EN_PREPARATION → BROUILLON interdit (retour arrière)
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    (CommandeRepository::for($store))->confirmOrder((int) $commande['id_commande'], null, false); // → EN_PREPARATION
    (CommandeRepository::for($store))->updateOrderStatus((int) $commande['id_commande'], 'BROUILLON', 'interdit', null, 'ADMIN');
}]);

jouer('T-13', ['regle' => 'RB-11', 'attendu' => 'ERREUR:RB11_TRANSITION_STATUT_INTERDITE', 'travail' => static function (StoreInterface $store): void {
    // EXPEDIEE → BROUILLON interdit
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    $repo = CommandeRepository::for($store);
    $repo->confirmOrder((int) $commande['id_commande'], null, true);  // → PAYEE
    $repo->updateOrderStatus((int) $commande['id_commande'], 'EXPEDIEE', 'envoyée', null, 'ADMIN');
    $repo->updateOrderStatus((int) $commande['id_commande'], 'BROUILLON', 'interdit', null, 'ADMIN');
}]);

jouer('T-14', ['regle' => 'RB-14', 'attendu' => 'ERREUR:CATEGORIE_NON_VIDE', 'travail' => static function (StoreInterface $store): void {
    // suppression d'une catégorie occupée refusée
    $store->delete('categorie', [Filter::eq('id_categorie', 1)]); // Informatique porte plusieurs produits
}]);

jouer('T-15', ['regle' => 'RB-14', 'attendu' => 'ERREUR:PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER', 'travail' => static function (StoreInterface $store): void {
    // suppression d'un produit déjà commandé refusée ( physique)
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    $store->delete('produit', [Filter::eq('id_produit', idTst($store))]);
}]);

jouer('T-16', ['regle' => 'RB-04', 'attendu' => 'ERREUR:RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE', 'travail' => static function (StoreInterface $store): void {
    // validation d'une commande sans ligne refusée
    $commande = commandeAvec($store, 'alice@example.com'); // brouillon vide
    (CommandeRepository::for($store))->confirmOrder((int) $commande['id_commande'], null, false);
}]);

jouer('T-17', ['regle' => 'RB-10', 'attendu' => 'ERREUR:RB10_COMMANDE_SANS_CLIENT', 'travail' => static function (StoreInterface $store): void {
    // INSERT direct d'une commande pour un client inexistant : trigger + FK
    $store->insert('commande', [
        'id_client' => 99999, 'adresse_livraison' => 'Nulle part',
        'frais_port' => 4.90, 'montant_total' => 0,
    ]);
}]);

jouer('T-18', ['regle' => 'SEC-08', 'attendu' => 'ERREUR:ACCES_NON_AUTORISE', 'travail' => static function (StoreInterface $store): void {
    // sp_confirm_order refuse la commande d'un autre client
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    $bruno = $store->findOne('client', [Filter::eq('email', 'bruno@example.com')]);
    (CommandeRepository::for($store))->confirmOrder((int) $commande['id_commande'], (int) $bruno['id_client'], false);
}]);

jouer('T-19', ['regle' => 'RB-03', 'attendu' => 'OK (divergence D-08 : exception)', 'travail' => static function (StoreInterface $store): void {
    // sp_adjust_stock refuse le passage en négatif : refus propre, stock intact
    $produits = new ProduitRepository($store);
    try {
        $produits->adjustStock(idTst($store), 'DELTA', -99999, 'tentative de casse');
        passe('RB03 devait être refusé', false);
    } catch (BusinessError $e) {
        passe('code RB03', $e->businessCode === 'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF');
    }
    passe('stock intact (5)', (int) $store->find('produit', idTst($store))['stock'] === 5);
    // un mouvement LÉGAL reste possible ensuite
    $produits->adjustStock(idTst($store), 'DELTA', +2, 'réception');
    passe('mouvement légal accepté (5 → 7)', (int) $store->find('produit', idTst($store))['stock'] === 7);
}]);

jouer('T-20', ['regle' => 'RB-09', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // produit déjà commandé : bascule visible=0, la ligne survit
    commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    (new ProduitRepository($store))->deleteProduct(idTst($store));
    $produit = $store->find('produit', idTst($store));
    passe('produit conservé en base', $produit !== null);
    passe('visible = 0', (int) $produit['visible'] === 0);
    passe('ligne de commande conservée', $store->count('ligne_commande') === 1);
}]);

jouer('T-21', ['regle' => 'RB-18', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // annulation : restitution exacte, un seul crédit, soldes à zéro
    $commande = commandeAvec($store, 'carla@example.com', ['TST-900' => 2]); // stock 5 → 3
    passe('stock décrémenté 5 → 3', (int) $store->find('produit', idTst($store))['stock'] === 3);
    $resultat = (CommandeRepository::for($store))->cancelOrder((int) $commande['id_commande'], null);
    passe('2 unités restituées', (int) $resultat['unites_rendues'] === 2);
    passe('stock restauré à 5 exactement', (int) $store->find('produit', idTst($store))['stock'] === 5);
    $apres = $store->find('commande', (int) $commande['id_commande']);
    passe('statut ANNULEE', $apres['statut'] === 'ANNULEE');
    passe('frais_port = 0', (float) $apres['frais_port'] === 0.0);
    passe('montant_total = 0', (float) $apres['montant_total'] === 0.0);
    $histo = $store->select('order_status_history', [Filter::eq('order_id', (int) $commande['id_commande'])]);
    passe('trace EN_PREPARATION → ANNULEE', (bool) array_filter($histo, static fn ($h) => $h['new_status'] === 'ANNULEE'));
}]);

jouer('T-22', ['regle' => 'RB-11', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // piste d'audit alimentée automatiquement, sans doublon (anomalie A-02)
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]);
    $repo = CommandeRepository::for($store);
    $repo->confirmOrder((int) $commande['id_commande'], null, true);   // → PAYEE
    $repo->updateOrderStatus((int) $commande['id_commande'], 'EXPEDIEE', 'envoi', null, 'ADMIN');
    $histo = $repo->historique((int) $commande['id_commande']);
    passe('3 traces (création, confirmation, expédition)', count($histo) === 3);
    passe('première trace NULL → BROUILLON', $histo[0]['old_status'] === null && $histo[0]['new_status'] === 'BROUILLON');
    $seq = array_column($histo, 'new_status');
    passe('séquence BROUILLON, PAYEE, EXPEDIEE', $seq === ['BROUILLON', 'PAYEE', 'EXPEDIEE']);
}]);

jouer('T-23', ['regle' => 'RB-06', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // le prix de la ligne survit à une hausse tarifaire ultérieure
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]); // 12,00 TTC figé
    (new ProduitRepository($store))->saveProduct(
        idTst($store), 'TST-900', 'Produit de fixture', 'produit-de-fixture',
        'Fixture', 19.90, 20.0, 5, 2, 1, true, null
    ); // 23,88 TTC au catalogue
    $ligne = $store->findOne('ligne_commande', [Filter::eq('id_commande', (int) $commande['id_commande'])]);
    passe('prix figé 12,00 sur la ligne', (float) $ligne['prix_unitaire'] === 12.00);
    passe('total figé 12,00', (float) $ligne['total_ligne'] === 12.00);
    passe('catalogut affiche bien la hausse', (float) $store->find('produit', idTst($store))['prix_ttc'] === 23.88);
    // le brouillon garde son montant d'origine : lignes snapshot + port initial
    passe('montant du brouillon basé sur le snapshot (12,00 + 4,90)', (float) $store->find('commande', (int) $commande['id_commande'])['montant_total'] === 16.90);
}]);

jouer('T-24', ['regle' => 'RB-04', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // purge d'un BROUILLON VIDE autorisée (le trigger ne s'y oppose pas)
    $commande = commandeAvec($store, 'alice@example.com'); // vide
    $supprimees = $store->delete('commande', [Filter::eq('id_commande', (int) $commande['id_commande'])]);
    passe('suppression effective', $supprimees === 1);
    passe('commande absente du magasin', $store->find('commande', (int) $commande['id_commande']) === null);
}]);

jouer('T-25', ['regle' => 'EF-VIS-02', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // recherche, filtres cumulés, produits masqués exclus (RB-19)
    $repo = new ProduitRepository($store);
    $parMot = $repo->searchProducts(['mot_cle' => 'ecran']);
    passe('« ecran » trouve ECRA-003 (mot-cle insensible à la casse et aux accents)',
        $parMot['total'] >= 1 && in_array('ECRA-003', array_column($parMot['lignes'], 'reference'), true));
    $rien = $repo->searchProducts(['mot_cle' => 'zzzzzz']);
    passe('mot-clé inconnu → 0 résultat', $rien['total'] === 0);
    foreach ($repo->searchProducts(['mot_cle' => 'bracelet'])['lignes'] as $p) {
        passe('BRAC-012 masqué jamais retourné', $p['reference'] !== 'BRAC-012');
    }
    $filtres = $repo->searchProducts(['id_categorie' => 2, 'prix_max' => 160, 'en_stock' => true, 'tri' => 'prix_asc']);
    passe('filtres cumulés (Audio ≤ 160 €, en stock)', $filtres['total'] === 3);
    passe('tri prix croissant', $filtres['lignes'][0]['reference'] === 'ENCH-006');
    $admin = $repo->searchProducts(['mot_cle' => 'bracelet', 'admin' => true]);
    passe('le back-office voit le produit masqué (RB-19 inverse)', $admin['total'] === 1);
}]);

jouer('T-26', ['regle' => 'EF-ADM-05', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // états de stock : RUPTURE, TRES_BAS, DISPONIBLE — formule unique
    $produits = new ProduitRepository($store);
    passe('POWER-010 en RUPTURE (stock 0)', (bool) array_filter(
        $produits->alertesStock(),
        static fn ($a) => $a['reference'] === 'POWER-010' && $a['etat'] === 'RUPTURE'
    ));
    passe('TST-900 (5/seuil 2) hors alerte', !in_array('TST-900', array_column($produits->alertesStock(), 'reference'), true));
    $produits->adjustStock(idTst($store), 'SET', 2, 'vidage test'); // stock = seuil → TRES_BAS
    passe('TST-900 passe TRES_BAS (stock = seuil)', (bool) array_filter(
        $produits->alertesStock(),
        static fn ($a) => $a['reference'] === 'TST-900' && $a['etat'] === 'TRES_BAS'
    ));
    // cohérence exhaustive vue ↔ formule
    $etats = (new Views($store))->etatStocks();
    passe('la vue couvre les 13 produits', count($etats) === 13);
    foreach ($etats as $etat) {
        $attendu = $etat['stock'] === 0 ? 'RUPTURE' : ($etat['stock'] <= $etat['seuil_alerte'] ? 'TRES_BAS' : 'DISPONIBLE');
        passe('formule identique pour ' . $etat['reference'], $etat['etat'] === $attendu);
    }
}]);

jouer('T-27', ['regle' => 'EF-VIS-01', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // les 3 « vues » existent et sont cohérentes avec les tables
    $views = new Views($store);
    $catalogue = $views->catalogue();
    passe('v_catalogue : 12 produits visibles (seed 11 + TST-900, BRAC-012 masqué exclu)', count($catalogue) === 12);
    passe('v_catalogue ne contient jamais BRAC-012', !in_array('BRAC-012', array_column($catalogue, 'reference'), true));
    $etats = array_column($views->etatStocks(), 'etat', 'reference');
    foreach ($catalogue as $ligne) {
        passe('état cohérent pour ' . $ligne['reference'], $ligne['etat'] === $etats[$ligne['reference']]);
    }
    // v_commandes_client : enrichissement + invariant montant = lignes + port
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 2]);
    $vues = $views->commandesClient(['where' => [Filter::eq('id_commande', (int) $commande['id_commande'])]]);
    passe('v_commandes_client trouve la commande', count($vues) === 1);
    passe('client enrichi (Alice Dupont)', ($vues[0]['client'] ?? '') === 'Alice Dupont');
    passe('nb_lignes = 1', (int) $vues[0]['nb_lignes'] === 1);
    $somme = $store->sum('ligne_commande', 'total_ligne', [Filter::eq('id_commande', (int) $commande['id_commande'])]);
    passe('RB-15 : montant = lignes + port', (float) $vues[0]['montant_total'] === round($somme + (float) $commande['frais_port'], 2));
}]);

jouer('T-28', ['regle' => 'ENF-14', 'attendu' => 'ERREUR:RB15_MONTANT_CALCULE_INTERDIT', 'travail' => static function (StoreInterface $store): void {
    // frais de port calculés avant validation, figés ensuite, non saisissables
    $ports = new ParametreRepository($store);
    passe('port standard 4,90', $ports->computeShipping(63.70) === ['port' => 4.90, 'motif' => 'Livraison standard (4,90 €)']
        || $ports->computeShipping(63.70)['port'] === 4.90);
    passe('franchise à 80,00 €', $ports->computeShipping(80.00)['port'] === 0.0);
    $commande = commandeAvec($store, 'alice@example.com', ['TST-900' => 1]); // 12 € → port 4,90
    (CommandeRepository::for($store))->confirmOrder((int) $commande['id_commande'], null, false);
    // tentative d'écriture directe du port après validation : refus RB-15/RB-20
    $store->update('commande', [Filter::eq('id_commande', (int) $commande['id_commande'])], ['frais_port' => 0.00]);
}]);

jouer('T-29', ['regle' => 'ENF-16', 'attendu' => 'OK', 'travail' => static function (StoreInterface $store): void {
    // erreur en cours de commande : rollback intégral, aucune donnée partielle
    $alice = $store->findOne('client', [Filter::eq('email', 'alice@example.com')]);
    $avantCommandes = $store->count('commande');
    $avantLignes = $store->count('ligne_commande');
    $avantStockCasq = (int) $store->findOne('produit', [Filter::eq('reference', 'CASQ-005')])['stock'];
    $avantStockMicro = (int) $store->findOne('produit', [Filter::eq('reference', 'MICRO-007')])['stock'];
    try {
        (CommandeRepository::for($store))->createOrderFromBasket(
            (int) $alice['id_client'],
            'Rollback, Nice',
            [ // 1re ligne OK, 2e en survente (MICRO-007 stock 2)
                ['id_produit' => (int) $store->findOne('produit', [Filter::eq('reference', 'CASQ-005')])['id_produit'], 'quantite' => 1],
                ['id_produit' => (int) $store->findOne('produit', [Filter::eq('reference', 'MICRO-007')])['id_produit'], 'quantite' => 3],
            ],
            true
        );
        passe('la survente devait être refusée', false);
    } catch (BusinessError $e) {
        passe('code STOCK_INSUFFISANT', $e->businessCode === 'STOCK_INSUFFISANT');
    }
    passe('aucune commande créée', $store->count('commande') === $avantCommandes);
    passe('aucune ligne créée', $store->count('ligne_commande') === $avantLignes);
    passe('stock CASQ intact (rollback)', (int) $store->findOne('produit', [Filter::eq('reference', 'CASQ-005')])['stock'] === $avantStockCasq);
    passe('stock MICRO intact', (int) $store->findOne('produit', [Filter::eq('reference', 'MICRO-007')])['stock'] === $avantStockMicro);
}]);

// ------------------------------------------------------------- synthèse

echo "\n" . str_repeat('=', 72) . "\n";
echo sprintf("%d tests joués — %d conformes, %d non conformes\n", $compte, $compte - $echecs, $echecs);
if ($echecs > 0) {
    echo "\n| Test | Règle | Résultat | Attendu |\n|---|---|---|---|\n" . implode("\n", $journal) . "\n";
    exit(1);
}
echo "RÈGLES DE GESTION : 29/29 CONFORMES ✅ (moteur JSON, application court-circuitée)\n";
exit(0);
