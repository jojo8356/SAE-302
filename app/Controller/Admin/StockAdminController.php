<?php

declare(strict_types=1);

/**
 * MiniShop — gestion des stocks (UC-12, EF-ADM-04/05, RB-03/18) :
 * entrée, sortie, valeur d'inventaire — toujours avec un MOTIF.
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Model\Data\Views;
use App\Repository\ProduitRepository;
use App\Security\Auth;

final class StockAdminController extends Controller
{
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();

        $etats = (new Views($store))->etatStocks();

        // EF-ADM-05 — filtre d'état et tri par criticité (RUPTURE → TRES_BAS → DISPONIBLE).
        $filtre = strtoupper($this->str('etat', 20));
        if (in_array($filtre, ['RUPTURE', 'TRES_BAS', 'DISPONIBLE'], true)) {
            $etats = array_values(array_filter(
                $etats,
                static fn (array $etat): bool => strtoupper((string) $etat['etat']) === $filtre,
            ));
        }

        $criticite = ['RUPTURE' => 0, 'TRES_BAS' => 1, 'DISPONIBLE' => 2];
        usort($etats, static function (array $a, array $b) use ($criticite): int {
            $rangA = $criticite[strtoupper((string) $a['etat'])] ?? 3;
            $rangB = $criticite[strtoupper((string) $b['etat'])] ?? 3;
            if ($rangA !== $rangB) {
                return $rangA <=> $rangB;
            }

            return (int) $a['stock'] <=> (int) $b['stock'];
        });
        $valeurInventaire = 0.0;
        foreach ($store->select('produit') as $produit) {
            $valeurInventaire += (float) $produit['prix_ht'] * (int) $produit['stock'];
        }

        $compteurs = ['RUPTURE' => 0, 'TRES_BAS' => 0, 'DISPONIBLE' => 0];
        foreach ((new Views($store))->etatStocks() as $etat) {
            $cle = strtoupper((string) $etat['etat']);
            if (isset($compteurs[$cle])) {
                ++$compteurs[$cle];
            }
        }

        $filtreRetenu = '';
        if (in_array($filtre, ['RUPTURE', 'TRES_BAS', 'DISPONIBLE'], true)) {
            $filtreRetenu = $filtre;
        }

        $this->render('admin/stocks', [
            'etats' => $etats,
            'filtre' => $filtreRetenu,
            'compteurs' => $compteurs,
            'valeur_inventaire' => round($valeurInventaire, 2),
            'erreurs' => [],
        ], 'Back-office — stocks');
    }

    /** EF-ADM-04 — écriture de stock avec motif obligatoire (SET ou DELTA). */
    public function ajuster(): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $store = Database::store();
        $admin = Auth::admin();

        // Défaut sûr : « Ajouter / retirer » (DELTA), comme le propose le
        // formulaire — un mode absent ne doit jamais écraser le stock
        // d'une valeur absolue par accident (SET seulement si explicite).
        $mode = 'DELTA';
        if (strtoupper($this->str('mode', 5)) === 'SET') {
            $mode = 'SET';
        }
        $quantite = $this->int('quantite') ?? 0;
        $motif = $this->str('motif', 200);

        try {
            $store->setActor(['id' => $admin['id'], 'role' => 'ADMIN', 'nom' => $admin['prenom'] . ' ' . $admin['nom']]);
            $nouveauStock = (new ProduitRepository($store))->adjustStock(
                (int) ($this->int('id_produit') ?? 0),
                $mode,
                $quantite,
                $motif,
            );
            $this->flashSucces('Stock mis à jour : ' . $nouveauStock . ' unité(s).');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/stocks');
    }
}
