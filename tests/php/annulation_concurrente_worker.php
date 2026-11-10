<?php

declare(strict_types=1);

/** Auxiliaire du test multiprocessus : uniquement sur un dossier temporaire vide. */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Repository\CommandeRepository;

if (getenv('MINISHOP_CONCURRENCY_TEST') !== '1' || count($argv) < 3) {
    fwrite(STDERR, "Usage réservé au test concurrent isolé.\n");
    exit(2);
}
$repertoire = realpath($argv[2]);
$tmp = realpath(sys_get_temp_dir());
if ($repertoire === false || $tmp === false || !str_starts_with($repertoire, $tmp . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Le dossier doit être sous le répertoire temporaire.\n");
    exit(2);
}
$store = JsonStore::open($repertoire);
$repo = CommandeRepository::for($store);
if ($argv[1] === 'seed') {
    $fichiers = glob($repertoire . '/*.json');
    if ($fichiers === false || $fichiers !== []) {
        throw new RuntimeException('Refus : dossier de données déjà rempli.');
    }
    Seed::force($store);
    $client = (int) $store->findOne('client', [Filter::eq('email', 'carla@example.com')])['id_client'];
    $produit = (int) $store->findOne('produit', [Filter::eq('reference', 'SAC-008')])['id_produit'];
    $stock = (int) $store->find('produit', $produit)['stock'];
    $commandes = [];
    foreach ([2, 1] as $quantite) {
        $commandes[] = $repo->createOrderFromBasket($client, '12 rue du Test', [
            ['id_produit' => $produit, 'quantite' => $quantite],
        ], true);
    }
    echo json_encode(['client' => $client, 'produit' => $produit, 'stock_initial' => $stock, 'commandes' => $commandes], JSON_THROW_ON_ERROR);
    exit(0);
}
if ($argv[1] === 'hold') {
    $store->beginTransaction();
    touch($repertoire . '/verrou-pret');
    // Le parent termine ce processus pour vérifier la libération OS du verrou.
    sleep(30);
    $store->rollback();
    exit(0);
}
if (in_array($argv[1], ['cancel', 'cancel-admin'], true) && count($argv) === 6) {
    $commande = (int) $argv[3];
    $client = (int) $argv[4];
    $numero = (int) $argv[5];
    // Les deux processus chargent le même instantané avant de démarrer :
    // le test détecte aussi les caches périmés, même sans parallélisme parfait.
    $store->find('commande', $commande);
    $store->all('produit');
    touch($repertoire . '/pret-' . $numero);
    $limite = microtime(true) + 20;
    while (!is_file($repertoire . '/depart')) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('Barrière de test expirée.');
        }
        usleep(10000);
        clearstatcache();
    }
    try {
        if ($argv[1] === 'cancel-admin') {
            $repo->updateOrderStatus($commande, 'ANNULEE', 'Test concurrent', 1, 'ADMIN');
            echo json_encode(['resultat' => 'OK'], JSON_THROW_ON_ERROR);
        } else {
            echo json_encode(['resultat' => 'OK', 'valeur' => $repo->cancelOrder($commande, $client)], JSON_THROW_ON_ERROR);
        }
    } catch (BusinessError $erreur) {
        echo json_encode(['resultat' => $erreur->businessCode], JSON_THROW_ON_ERROR);
    }
    exit(0);
}
if ($argv[1] === 'etat') {
    $idProduit = (int) $argv[3];
    echo json_encode([
        'stock' => $store->find('produit', $idProduit)['stock'],
        'commandes' => array_values($store->all('commande')),
        'lignes' => array_values($store->all('ligne_commande')),
        'historique' => array_values($store->all('order_status_history')),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}
throw new RuntimeException('Action de test inconnue.');
