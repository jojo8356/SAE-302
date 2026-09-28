<?php

declare(strict_types=1);

/**
 * MiniShop — tests fonctionnels des features (chaque cas d'utilisation UC-01…UC-14
 * et exigence EF-xx / RB-xx, exécutés via la couche Repository = « procédures »).
 *
 * Contrairement à tests/php/run_tests.php (règles de gestion court-circuitant
 * l'application, portage du manifeste SQL), cette suite parcourt LES FONCTIONNALITÉS
 * vues par l'application : catalogue, compte, panier, commande, annulation,
 * back-office, indicateurs — en appelant les méthodes qu'utilisent les contrôleurs.
 *
 * Chaque section repart d'un magasin neuf (fixture d'état) : rejouable à volonté.
 *
 * Usage :  php tests/php/features_test.php
 *         node scripts/wasm_run.mjs tests/php/features_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

// Sortie bufferisée : les tests affichent au fur et à mesure, mais la session
// (ouverte par PanierSession/Auth) doit pouvoir émettre ses en-têtes sans cri
// « headers already sent » — purement cosmétique pour un harnais CLI.
ob_start();

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Model\Data\Views;
use App\Model\PanierSession;
use App\Repository\CategorieRepository;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ProduitRepository;

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ❌ ';
    if ($ok) {
        $icone = '  ✅ ';
    }
    $suffixe = '';
    if (!$ok && $detail !== '') {
        $suffixe = " — {$detail}";
    }
    echo $icone . $label . $suffixe . "\n";
    if (!$ok) {
        ++$echecs;
    }
};
/** Capture un code métier attendu (et seulement lui). */
$attendu = static function (string $code, callable $travail) use (&$echecs, &$tests): bool {
    ++$tests;
    try {
        $travail();
        echo "  ❌ {$code} non levé\n";
        ++$echecs;

        return false;
    } catch (BusinessError $e) {
        $ok = $e->businessCode === $code;
        $icone = '  ❌ ';
        $obtenu = ' (obtenu ' . $e->businessCode . ')';
        if ($ok) {
            $icone = '  ✅ ';
            $obtenu = '';
        }
        echo $icone . "refus {$code}" . $obtenu . "\n";
        if (!$ok) {
            ++$echecs;
        }

        return true;
    }
};

function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_f' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        $anciens = glob($dossier . '/*.json');
        if (is_array($anciens)) {
            array_map('unlink', $anciens);
        }
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

/** id d'un produit par sa référence. */
function pid(StoreInterface $store, string $reference): int
{
    return (int) $store->findOne('produit', [Filter::eq('reference', $reference)])['id_produit'];
}

/** Client par email → id. */
function cid(StoreInterface $store, string $email): int
{
    return (int) $store->findOne('client', [Filter::eq('email', $email)])['id_client'];
}

echo "MiniShop — tests fonctionnels des features (UC-01…UC-14 via les repositories)\n\n";

// ============================================== A. UC-01 · catégories (EF-VIS-01)
echo "A. UC-01 — consultation des catégories\n";
{
    $store = magasin();
    $categories = (new CategorieRepository($store))->listerAvecCompteur();
    $check('4 catégories classées par nom', count($categories) === 4 && $categories[0]['nom'] === 'Accessoires');
    $parNom = array_column($categories, 'nb_produits', 'nom');
    $check('compteur = produits VISIBLES uniquement (RB-19)', $parNom['Accessoires'] === 3 && $parNom['Objets connectés'] === 1);
    $check('find / findBySlug', (new CategorieRepository($store))->find(1)['nom'] === 'Informatique'
        && (new CategorieRepository($store))->findBySlug('audio')['nom'] === 'Audio');
}

// ============================================== B. UC-02 · recherche & filtres
echo "\nB. UC-02 — recherche et filtres du catalogue (EF-VIS-02/03, ENF-04)\n";
{
    $store = magasin();
    $repo = new ProduitRepository($store);
    $r = $repo->searchProducts(['mot_cle' => 'casque']);
    $check('recherche par mot-clé', $r['total'] === 1 && $r['lignes'][0]['reference'] === 'CASQ-005');
    $check('recherche insensible casse', $repo->searchProducts(['mot_cle' => 'CaSqUe'])['total'] === 1);
    $check('recherche insensible accent (nom + description)', $repo->searchProducts(['mot_cle' => 'ÉCRAN'])['total'] === 2);
    $r = $repo->searchProducts(['id_categorie' => 1, 'prix_max' => 800, 'tri' => 'prix_asc']);
    $check('filtres catégorie + prix TTC, tri prix croissant', $r['total'] === 2
        && array_column($r['lignes'], 'reference') === ['CLAV-004', 'ECRA-003']);
    $r = $repo->searchProducts(['en_stock' => true, 'id_categorie' => 3]);
    $check('filtre « en stock » exclut la rupture', $r['total'] === 2 && !in_array('POWER-010', array_column($r['lignes'], 'reference'), true));
    $check('tri inconnu → repli liste blanche (nom)', $repo->searchProducts(['tri' => 'DROP TABLE'])['lignes'][0]['reference'] === 'POWER-010');
    $r = $repo->searchProducts(['par_page' => 500, 'page' => 1]);
    $check('par_page borné à 60 (ENF-04)', $r['par_page'] === 60 && $r['pages'] === 1);
    $r = $repo->searchProducts(['par_page' => 5, 'page' => 999]);
    $check('page 999 : aucune erreur, liste vide', $r['lignes'] === [] && $r['page'] === 999);
    $check('page 0 ramenée à 1', $repo->searchProducts(['par_page' => 5, 'page' => 0])['page'] === 1);
    $check('le client ne voit jamais le masqué (RB-19)', $repo->searchProducts(['mot_cle' => 'bracelet'])['total'] === 0);
    $check('l\'admin voit le masqué', $repo->searchProducts(['mot_cle' => 'bracelet', 'admin' => true])['total'] === 1);
    $check('prix_min > prix_max : filtre ignoré sans exception', $repo->searchProducts(['prix_min' => 500, 'prix_max' => 10])['total'] === 0);
}

// ============================================== C. UC-03 · fiche produit
echo "\nC. UC-03 — fiche produit (EF-VIS-04, RB-16)\n";
{
    $store = magasin();
    $repo = new ProduitRepository($store);
    $fiche = $repo->findVisibleBySlug('casque-sans-fil-aura');
    $check('fiche par slug', $fiche['reference'] === 'CASQ-005');
    $check('prix TTC dérivé 154,80', (float) $fiche['prix_ttc'] === 154.80);
    $check('produit masqué introuvable par slug (RB-19)', $repo->findVisibleBySlug('bracelet-connecte-band') === null);
    $check('slug inconnu → null (404 côté contrôleur)', $repo->findVisibleBySlug('inexistant') === null);
    $check('disponible() = stock plafonné à 0', $repo->disponible(pid($store, 'POWER-010')) === 0 && $repo->disponible(pid($store, 'CASQ-005')) === 18);
}

// ============================================== D. UC-04/05 · compte client
echo "\nD. UC-04/05 — inscription, connexion, profil (EF-VIS-06/07, EF-CLI-01/02)\n";
{
    $store = magasin();
    $repo = new ClientRepository($store);
    $id = $repo->createAccount('Bernard', 'Xavier', 'xavier@example.com', password_hash('Xavier2026!', PASSWORD_BCRYPT));
    $check('inscription : hash bcrypt 60 caractères (RB-12)', strlen((string) $store->find('client', $id)['mot_de_passe_hash']) === 60);
    $attendu('EMAIL_DEJA_UTILISE', fn () => $repo->createAccount('Autre', 'Nom', 'xavier@example.com', str_repeat('h', 60)));
    $attendu('CHAMPS_OBLIGATOIRES', fn () => $repo->createAccount('', '', 'vide@example.com', str_repeat('h', 60)));
    $attendu('HASH_MOT_DE_PASSE_INVALIDE', fn () => $repo->createAccount('Ok', 'Ok', 'ok@example.com', 'trop-court'));
    $check('getCredentials OK', $repo->getCredentials('xavier@example.com')['statut'] === 'OK');
    $check('getCredentials INCONNU (message identique côté IHM, SEC-05)', $repo->getCredentials('personne@example.com')['statut'] === 'INCONNU');
    $store->update('client', [Filter::eq('id_client', $id)], ['actif' => 0]);
    $check('getCredentials COMPTE_BLOQUE', $repo->getCredentials('xavier@example.com')['statut'] === 'COMPTE_BLOQUE');
    $store->update('client', [Filter::eq('id_client', $id)], ['actif' => 1]);

    $repo->updateClient($id, 'Bernard', 'Xavier', 'xavier@example.com', '06 12 34 56 78', '1 rue Neuve', '06000', 'Nice');
    $check('profil mis à jour, email inchangé sans faux refus (RB-01)', $store->find('client', $id)['telephone'] === '06 12 34 56 78');
    $attendu('EMAIL_DEJA_UTILISE', fn () => $repo->updateClient($id, 'B', 'X', 'alice@example.com', null, null, null, null));
    $repo->changeMotDePasse($id, password_hash('Nouveau2026!', PASSWORD_BCRYPT));
    $check('changement de mot de passe : nouveau hash vérifiable', password_verify('Nouveau2026!', (string) $store->find('client', $id)['mot_de_passe_hash']));
    $repo->noteConnexion($id);
    $check('noteConnexion horodate la dernière visite', ($store->find('client', $id)['derniere_connexion'] ?? '') !== '');
    $check('listerTous pour le back-office', count($repo->listerTous()) === 4);
}

// ============================================== E. UC-06 · panier
echo "\nE. UC-06 — panier session (déjà couvert en unitaires : synthèse ici)\n";
{
    $store = magasin();
    $panier = new PanierSession($store, new ProduitRepository($store), new ParametreRepository($store));
    $panier->ajouter(pid($store, 'CASQ-005'), 1);
    $panier->ajouter(pid($store, 'MICRO-007'), 5); // stock 2
    $recap = $panier->recapitulatif();
    $check('ajouts plafonnés, 2 lignes', count($recap['articles']) === 2 && $panier->nombreArticles() === 3);
    $check('pourCommande prêt pour UC-07', $panier->pourCommande() === [
        ['id_produit' => pid($store, 'CASQ-005'), 'quantite' => 1],
        ['id_produit' => pid($store, 'MICRO-007'), 'quantite' => 2],
    ]);
    $panier->vider();
    $check('panier vidé après usage (EF-GEN-02 côté contrôleur)', $panier->estVide());
}

// ============================================== F. UC-07 · commande (création)
echo "\nF. UC-07 — passer commande : création et lignes (RB-04/05/06/15/18)\n";
{
    $store = magasin();
    $repo = CommandeRepository::for($store);
    $attendu('CLIENT_INTROUVABLE_OU_BLOQUE', fn () => $repo->createOrder(99999, 'Adresse'));
    $attendu('ADRESSE_LIVRAISON_REQUISE', fn () => $repo->createOrder(cid($store, 'alice@example.com'), '   '));
    $creation = $repo->createOrder(cid($store, 'alice@example.com'), '12 rue des Lilas, Nice');
    $commande = $repo->find($creation['id_commande']);
    $check('numéro au format CMD{année}-{id:6}', $commande['numero'] === 'CMD' . date('Y') . '-' . sprintf('%06d', (int) $commande['id_commande']));
    $check('findByNumero', $repo->findByNumero($commande['numero'])['id_commande'] === $commande['id_commande']);
    $check('statut initial BROUILLON', $commande['statut'] === 'BROUILLON');
    $check('trace de création NULL → BROUILLON (RB-11)', $repo->historique((int) $commande['id_commande'])[0]['new_status'] === 'BROUILLON');

    $attendu('RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO', fn () => $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CASQ-005'), 0));
    $attendu('COMMANDE_INTROUVABLE', fn () => $repo->addOrderLine(999999, pid($store, 'CASQ-005'), 1));
    $attendu('PRODUIT_SUPPRIME_DU_CATALOGUE', fn () => $repo->addOrderLine((int) $commande['id_commande'], 999999, 1));

    $ligne = $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CASQ-005'), 2);
    $check('ligne créée, prix figé 154,80 (RB-06)', (float) $store->find('ligne_commande', $ligne['id_ligne'])['prix_unitaire'] === 154.80);
    $check('stock décrémenté 18 → 16 (RB-18)', (int) $store->find('produit', pid($store, 'CASQ-005'))['stock'] === 16);
    $check('montant = lignes + port recalculé (RB-15)', (float) $repo->find($commande['id_commande'])['montant_total'] === 309.60 + 0.0);

    // réservation cumulée sur la MÊME commande : dispo = stock(16) − déjà réservé(2) = 14
    $attendu('STOCK_INSUFFISANT', fn () => $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CASQ-005'), 15));
    $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CASQ-005'), 14);
    $check('réservation cumulée : 2 + 14 = 16 réservés, stock 2', (int) $store->find('produit', pid($store, 'CASQ-005'))['stock'] === 2);

    // EN_PREPARATION reste ajustable ; le refus arrive une fois expédiée (RB-04/RB-11)
    $repo->confirmOrder((int) $commande['id_commande'], null, false);
    $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CABLE-009'), 1);
    $repo->updateOrderStatus((int) $commande['id_commande'], 'EXPEDIEE', null, 1, 'ADMIN');
    $attendu('COMMANDE_NON_MODIFIABLE', fn () => $repo->addOrderLine((int) $commande['id_commande'], pid($store, 'CABLE-009'), 1));
}

// ============================================== G. UC-07 · validation
echo "\nG. UC-07 — validation du panier (RB-04/19/20, ENF-16)\n";
{
    $store = magasin();
    $repo = CommandeRepository::for($store);
    $attendu('RB19_PANIER_VIDE', fn () => $repo->createOrderFromBasket(cid($store, 'alice@example.com'), 'Adresse', []));

    // panier avec survente sur la 2e ligne → rollback intégral
    $attendu('STOCK_INSUFFISANT', fn () => $repo->createOrderFromBasket(
        cid($store, 'alice@example.com'),
        'Adresse',
        [['id_produit' => pid($store, 'CASQ-005'), 'quantite' => 1], ['id_produit' => pid($store, 'MICRO-007'), 'quantite' => 3]],
        true
    ));
    $check('rollback : aucune commande créée (ENF-16)', $store->count('commande') === 0 && $store->count('ligne_commande') === 0);
    $check('rollback : stocks intacts', (int) $store->find('produit', pid($store, 'CASQ-005'))['stock'] === 18);

    $ok = $repo->createOrderFromBasket(cid($store, 'alice@example.com'), 'Adresse', [
        ['id_produit' => pid($store, 'CASQ-005'), 'quantite' => 2],
    ], true);
    $check('paiement simulé → statut PAYEE', $store->find('commande', $ok['id_commande'])['statut'] === 'PAYEE');
    $check('montant retourné = 309,60 + port offert', $ok['montant'] === 309.60);

    $ok2 = $repo->createOrderFromBasket(cid($store, 'bruno@example.com'), 'Adresse', [
        ['id_produit' => pid($store, 'CABLE-009'), 'quantite' => 1],
    ], false);
    $commande2 = $store->find('commande', $ok2['id_commande']);
    $check('sans paiement → EN_PREPARATION', $commande2['statut'] === 'EN_PREPARATION');
    $check('petit panier : port 4,90 appliqué', (float) $commande2['frais_port'] === 4.90 && (float) $commande2['montant_total'] === 19.90);

    // RB-20 : le port est FIGÉ — un changement de tarif ultérieur ne le retouche pas
    $store->update('parametre', [Filter::eq('cle', 'frais_port')], ['valeur' => '9.90']);
    $check('port figé après validation (RB-20)', (float) $store->find('commande', $ok2['id_commande'])['frais_port'] === 4.90);
    $attendu('COMMANDE_DEJA_VALIDEE', fn () => $repo->confirmOrder((int) $ok2['id_commande'], null, false));
    $attendu('ACCES_NON_AUTORISE', fn () => $repo->confirmOrder((int) $ok2['id_commande'], cid($store, 'carla@example.com'), false));
}

// ============================================== H. UC-08 · historique client
echo "\nH. UC-08 — historique et détail des commandes (EF-CLI-08/09)\n";
{
    $store = magasin();
    $repo = CommandeRepository::for($store);
    $okA = $repo->createOrderFromBasket(cid($store, 'alice@example.com'), 'Chez Alice', [
        ['id_produit' => pid($store, 'CASQ-005'), 'quantite' => 1],
        ['id_produit' => pid($store, 'CABLE-009'), 'quantite' => 2],
    ], false);
    $repo->createOrderFromBasket(cid($store, 'bruno@example.com'), 'Chez Bruno', [
        ['id_produit' => pid($store, 'MONTRE-011'), 'quantite' => 1],
    ], true);

    $lesSiennes = $repo->pourClient(cid($store, 'alice@example.com'));
    $check('pourClient ne renvoie QUE ses commandes (SEC-08)', count($lesSiennes) === 1 && (int) $lesSiennes[0]['id_client'] === cid($store, 'alice@example.com'));
    $lignes = $repo->lignes((int) $okA['id_commande']);
    $check('lignes jointes au produit (nom + référence)', $lignes[0]['produit_nom'] === 'Casque sans fil Aura' && $lignes[0]['produit_reference'] === 'CASQ-005');
    $check('deux lignes dans l\'ordre d\'ajout', count($lignes) === 2 && (int) $lignes[1]['quantite'] === 2);
    $histo = $repo->historique((int) $okA['id_commande']);
    $check('piste : création puis validation', array_column($histo, 'new_status') === ['BROUILLON', 'EN_PREPARATION']);
    $check('piste : auteur et rôle tracés (RB-11)', $histo[1]['changed_by_role'] === 'SYSTEME' || $histo[1]['changed_by_role'] === 'ADMIN');
}

// ============================================== I. UC-09 · annulation
echo "\nI. UC-09 — annulation client (EF-CLI-10, RB-11/18/20)\n";
{
    $store = magasin();
    $repo = CommandeRepository::for($store);
    $ok = $repo->createOrderFromBasket(cid($store, 'carla@example.com'), 'Chez Carla', [
        ['id_produit' => pid($store, 'SAC-008'), 'quantite' => 2], // stock 22 → 20
        ['id_produit' => pid($store, 'ENCH-006'), 'quantite' => 1],
    ], false);
    $attendu('ACCES_NON_AUTORISE', fn () => $repo->cancelOrder((int) $ok['id_commande'], cid($store, 'alice@example.com')));
    $r = $repo->cancelOrder((int) $ok['id_commande'], cid($store, 'carla@example.com'));
    $check('3 unités rendues au stock', $r['unites_rendues'] === 3);
    $check('stocks restitués exactement (RB-18)', (int) $store->find('produit', pid($store, 'SAC-008'))['stock'] === 22
        && (int) $store->find('produit', pid($store, 'ENCH-006'))['stock'] === 30);
    $commande = $store->find('commande', $ok['id_commande']);
    $check('ANNULEE, lignes purgées, soldes à zéro (RB-20)', $commande['statut'] === 'ANNULEE'
        && $store->count('ligne_commande', [Filter::eq('id_commande', (int) $ok['id_commande'])]) === 0
        && (float) $commande['montant_total'] === 0.0 && (float) $commande['frais_port'] === 0.0);
    $attendu('COMMANDE_NON_ANNULABLE', fn () => $repo->cancelOrder((int) $ok['id_commande'], cid($store, 'carla@example.com')));

    // trop tardive : commande expédiée (règle UC-09 côté repository)
    $ok2 = $repo->createOrderFromBasket(cid($store, 'carla@example.com'), 'Chez Carla', [
        ['id_produit' => pid($store, 'CABLE-009'), 'quantite' => 1],
    ], true);
    $repo->updateOrderStatus((int) $ok2['id_commande'], 'EXPEDIEE', 'parti', 1, 'ADMIN');
    $attendu('ANNULATION_TROP_TARDIVE', fn () => $repo->updateOrderStatus((int) $ok2['id_commande'], 'ANNULEE', 'trop tard', cid($store, 'carla@example.com'), 'CLIENT'));
    $check('l\'admin, lui, peut encore annuler (matrice RB-11)', $repo->updateOrderStatus((int) $ok2['id_commande'], 'ANNULEE', 'retour colis', 1, 'ADMIN') === 'OK');
}

// ============================================== J. UC-10 · produits (admin)
echo "\nJ. UC-10 — gestion des produits (EF-ADM-01/02, RB-02/03/07/09/16/17/19)\n";
{
    $store = magasin();
    $repo = new ProduitRepository($store);
    $id = $repo->saveProduct(null, 'WEB-100', 'Caméra Web HD', null, 'Full HD', 49.90, 20.0, 10, 3, 2, true, null);
    $produit = $store->find('produit', $id);
    $check('création : slug auto depuis le nom (RB-07)', $produit['slug'] === 'camera-web-hd');
    $check('création : prix TTC dérivé (RB-16)', (float) $produit['prix_ttc'] === 59.88);
    $check('création : visible immédiatement côté client', (new ProduitRepository($store))->findVisibleBySlug('camera-web-hd') !== null);
    $attendu('REFERENCE_DEJA_UTILISEE', fn () => $repo->saveProduct(null, 'WEB-100', 'Doublon', null, null, 5, 20, 1, 1, 2, true, null));
    $attendu('SLUG_DEJA_UTILISE', fn () => $repo->saveProduct(null, 'WEB-101', 'Doublon', 'camera-web-hd', null, 5, 20, 1, 1, 2, true, null));
    $attendu('CATEGORIE_INTROUVABLE', fn () => $repo->saveProduct(null, 'WEB-102', 'Sans catégorie', null, null, 5, 20, 1, 1, 99, true, null));
    $attendu('RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF', fn () => $repo->saveProduct(null, 'WEB-103', 'Prix nul', null, null, 0, 20, 1, 1, 2, true, null));
    $attendu('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF', fn () => $repo->saveProduct(null, 'WEB-104', 'Stock négatif', null, null, 5, 20, -1, 1, 2, true, null));
    $idSansTva = $repo->saveProduct(null, 'WEB-105', 'Sans TVA', null, null, 100.00, 0.0, 1, 1, 2, true, null);
    $check('TVA = 0 acceptée (produit non soumis)', (float) $store->find('produit', $idSansTva)['prix_ttc'] === 100.00);

    $repo->saveProduct($id, 'WEB-100', 'Caméra Web 4K', null, 'Mise à jour', 79.00, 20.0, 10, 3, 2, false, null);
    $check('modification : slug suit le nom, prix recalculé, masqué', $store->find('produit', $id)['slug'] === 'camera-web-4k'
        && (float) $store->find('produit', $id)['prix_ttc'] === 94.80 && (int) $store->find('produit', $id)['visible'] === 0);
    $repo->saveProduct($id, 'WEB-100', 'Caméra Web 4K', 'camera-web-hd', null, 79.00, 20.0, 10, 3, 2, true, null);
    $check('slug explicite préservé à l\'identique (RB-07)', $store->find('produit', $id)['slug'] === 'camera-web-hd');

    $repo->deleteProduct($id);
    $check('produit jamais commandé : suppression RÉELLE', $store->find('produit', $id) === null);

    // produit déjà commandé : la suppression devient un masquage (RB-09/14/17)
    $cmd = CommandeRepository::for($store)->createOrderFromBasket(cid($store, 'alice@example.com'), 'X', [
        ['id_produit' => pid($store, 'PC-PORT-001'), 'quantite' => 1],
    ], true);
    $repo->deleteProduct(pid($store, 'PC-PORT-001'));
    $produitCommande = $store->find('produit', pid($store, 'PC-PORT-001'));
    $check('produit déjà commandé : MASQUÉ, pas supprimé (RB-09/14/17)', $produitCommande !== null && (int) $produitCommande['visible'] === 0);
    $check('le prix payé reste lisible dans la commande (RB-06)', (float) CommandeRepository::for($store)->lignes((int) $cmd['id_commande'])[0]['prix_unitaire'] === 838.80); // 699 × 1,2
}

// ============================================== K. UC-11 · catégories (admin)
echo "\nK. UC-11 — gestion des catégories (EF-ADM-03, RB-07/14)\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $id = $repo->saveCategory(null, 'Stockage', null, 'SSD, disques');
    $check('création avec slug auto', $store->find('categorie', $id)['slug'] === 'stockage');
    $retour = $repo->saveCategory(null, 'Stockage', null, 'Fusionnée'); // même nom → fusion
    $check('même nom = mise à jour de l\'existante (UNIQUE)', $retour === $id && $store->find('categorie', $id)['description'] === 'Fusionnée');
    $attendu('CATEGORIE_NON_VIDE', fn () => $repo->deleteCategory(1)); // Informatique occupée
    $repo->deleteCategory($id);
    $check('catégorie vide supprimée', $store->find('categorie', $id) === null);
}

// ============================================== L. UC-12 · stocks (admin)
echo "\nL. UC-12 — gestion des stocks (EF-ADM-04/05, RB-03)\n";
{
    $store = magasin();
    $repo = new ProduitRepository($store);
    $id = pid($store, 'ENCH-006'); // stock 30, seuil 8
    $attendu('MOTIF_STOCK_REQUIS', fn () => $repo->adjustStock($id, 'DELTA', 5, ''));
    $check('DELTA +15 avec motif', $repo->adjustStock($id, 'DELTA', 15, 'réception fournisseur') === 45);
    $check('SET 0 avec motif', $repo->adjustStock($id, 'SET', 0, 'inventaire') === 0);
    $attendu('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF', fn () => $repo->adjustStock($id, 'DELTA', -99999, 'casse'));
    $check('stock inchangé après refus', (int) $store->find('produit', $id)['stock'] === 0);
    $etats = array_column((new Views($store))->etatStocks(), 'etat', 'reference');
    $check('v_etat_stock : ENCH en RUPTURE, POWER en RUPTURE', $etats['ENCH-006'] === 'RUPTURE' && $etats['POWER-010'] === 'RUPTURE');
    $alertes = array_column($repo->alertesStock(), 'reference');
    $check('alertes = tout état ≠ DISPONIBLE (EF-ADM-05)', in_array('ENCH-006', $alertes, true) && !in_array('CASQ-005', $alertes, true));
}

// ============================================== M. UC-13/14 · commandes (admin)
echo "\nM. UC-13/14 — pilotage des commandes et indicateurs (EF-ADM-06…09)\n";
{
    $store = magasin();
    $repo = CommandeRepository::for($store);
    $c1 = $repo->createOrderFromBasket(cid($store, 'alice@example.com'), 'A', [['id_produit' => pid($store, 'CASQ-005'), 'quantite' => 2]], true);   // 309,60 PAYEE
    $c2 = $repo->createOrderFromBasket(cid($store, 'bruno@example.com'), 'B', [['id_produit' => pid($store, 'MONTRE-011'), 'quantite' => 1]], false); // 183,70 EN_PREPARATION
    $c3 = $repo->createOrderFromBasket(cid($store, 'carla@example.com'), 'C', [['id_produit' => pid($store, 'CABLE-009'), 'quantite' => 4]], true);   // 60,00 PAYEE puis annulée
    $repo->cancelOrder((int) $c3['id_commande'], cid($store, 'carla@example.com'));

    $check('updateOrderStatus même statut → STATUT_DEJA_A_JOUR', $repo->updateOrderStatus((int) $c2['id_commande'], 'EN_PREPARATION', null, 1, 'ADMIN') === 'STATUT_DEJA_A_JOUR');
    $check('transition EN_PREPARATION → EXPEDIEE avec commentaire', $repo->updateOrderStatus((int) $c2['id_commande'], 'EXPEDIEE', 'colis parti', 1, 'ADMIN') === 'OK');
    $attendu('STATUT_NON_AUTORISE', fn () => $repo->updateOrderStatus((int) $c2['id_commande'], 'STATUT_FANTOME', null, 1, 'ADMIN'));
    $histo = $repo->historique((int) $c2['id_commande']);
    $check('piste complète avec commentaire (EF-ADM-08)', array_column($histo, 'new_status') === ['BROUILLON', 'EN_PREPARATION', 'EXPEDIEE']
        && ($histo[2]['commentaire'] ?? '') === 'colis parti');

    $rapport = $repo->revenueReport();
    $parStatut = array_column($rapport['par_statut'], null, 'statut');
    $check('CA par statut : PAYEE 309,60 (l\'annulée soldée à 0)', (float) $parStatut['PAYEE']['montant_total'] === 309.60
        && (float) $parStatut['ANNULEE']['montant_total'] === 0.0);
    $check('montant moyen par statut (c2 est EXPEDIEE ici)', (float) $parStatut['EXPEDIEE']['montant_moyen'] === 178.80); // MONTRE 178,80, port offert
    $check('tri par montant décroissant', array_column($rapport['par_statut'], 'statut')[0] === 'PAYEE');
    $check('top produits : l\'annulée n\'alimente pas le classement', !in_array('CABLE-009', array_column($rapport['top_produits'], 'reference'), true)
        && $rapport['top_produits'][0]['reference'] === 'CASQ-005');
    $check('top 5 maximum', count($rapport['top_produits']) <= 5);

    $vues = (new Views($store))->commandesClient(['where' => [Filter::eq('statut', 'PAYEE')]]);
    $check('v_commandes_client filtre par statut', count($vues) === 1 && $vues[0]['numero'] === $store->find('commande', $c1['id_commande'])['numero']);
}

// ============================================== synthèse
echo "\n" . str_repeat('=', 72) . "\n";
echo sprintf("%d vérifications — %d OK, %d échecs\n", $tests, $tests - $echecs, $echecs);
if ($echecs > 0) {
    echo "FEATURES : SUITE EN ÉCHEC ❌\n";
    exit(1);
}
echo "FEATURES : TOUT EST VERT ✅ (chaque cas d'utilisation couvert)\n";
ob_end_flush();
exit(0);
