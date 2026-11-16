<?php

declare(strict_types=1);

/**
 * MiniShop — un acteur du test de concurrence HTTP (`concurrence_http.php`).
 *
 *   php tests/php/concurrence_worker.php <port> <index> <id_produit> <fichier_sortie>
 *
 * Chaque acteur joue le parcours client COMPLET sur le serveur de test :
 * inscription (email unique par acteur), ajout d'1 unité au panier, puis
 * validation de la commande. Le résultat est écrit en JSON dans
 * `<fichier_sortie>` : `{index, statut: commande|refuse|erreur, detail}`.
 *
 * « refuse » = la commande a été refusée par le stock (redirection vers le
 * panier avec le flash d'erreur) ; « commande » = commande créée (numéro
 * extrait de la redirection) ; « erreur » = tout autre comportement.
 *
 * Ce fichier n'est jamais lancé à la main : `concurrence_http.php` le lance
 * en 20 processus parallèles via `proc_open`.
 */

require_once __DIR__ . '/http_support.php';

use MiniShop\Tests\HttpClient;

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
if (count($arguments) < 4) {
    fwrite(STDERR, "Usage : php concurrence_worker.php <port> <index> <id_produit> <fichier_sortie>\n");
    exit(2);
}
$port = (int) $arguments[0];
$index = (int) $arguments[1];
$idProduit = (int) $arguments[2];
$fichierSortie = $arguments[3];

$resultat = ['index' => $index, 'statut' => 'erreur', 'detail' => 'non exécuté'];

try {
    $client = new HttpClient($port);
    $email = 'charge' . $index . '@example.test';

    $inscription = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Charge', 'prenom' => 'Acteur' . $index, 'email' => $email,
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!', 'cgv' => '1',
    ]);
    if ($inscription->statut !== 302 || $inscription->location() !== '/compte') {
        $resultat['detail'] = 'inscription refusée (statut ' . $inscription->statut . ')';
    } else {
        $ajout = $client->postFormulaire('/panier', '/panier/ajouter', [
            'id_produit' => (string) $idProduit, 'quantite' => '1',
        ]);
        if ($ajout->statut !== 302) {
            $resultat['detail'] = 'ajout au panier refusé (statut ' . $ajout->statut . ')';
        } else {
            $commande = $client->postFormulaire('/commande/valider', '/commande/valider', [
                'adresse' => '1 rue de la Charge, 06000 Nice', 'paiement_simule' => '1',
            ]);
            $cible = $commande->location() ?? '';
            if ($commande->statut === 302 && str_starts_with($cible, '/mes-commandes/')) {
                $resultat['statut'] = 'commande';
                $resultat['detail'] = 'commande créée (' . $cible . ')';
            } elseif ($commande->statut === 302 && $cible === '/panier') {
                $resultat['statut'] = 'refuse';
                $resultat['detail'] = 'stock insuffisant au moment de la validation';
            } else {
                $resultat['detail'] = 'validation inattendue (statut ' . $commande->statut . ', cible « ' . $cible . ' »)';
            }
        }
    }
} catch (Throwable $erreur) {
    $resultat['detail'] = 'exception : ' . $erreur->getMessage();
}

file_put_contents($fichierSortie, json_encode($resultat, JSON_UNESCAPED_UNICODE) . "\n");
exit(0);
