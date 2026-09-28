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
        $valeurInventaire = 0.0;
        foreach ($store->select('produit') as $produit) {
            $valeurInventaire += (float) $produit['prix_ht'] * (int) $produit['stock'];
        }

        $this->render('admin/stocks', [
            'etats' => $etats,
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

        $mode = strtoupper($this->str('mode', 5)) === 'DELTA' ? 'DELTA' : 'SET';
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
