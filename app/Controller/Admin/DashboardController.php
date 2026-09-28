<?php

declare(strict_types=1);

/**
 * MiniShop — tableau de bord back-office (EF-ADM-00, EF-ADM-05/07).
 * Portage de sp_revenue_report + vue v_etat_stock + alertes.
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Security\Auth;

final class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();

        $rapport = (CommandeRepository::for($store))->revenueReport();
        $produits = new ProduitRepository($store);

        $indicateurs = [
            'commandes' => $store->count('commande'),
            'en_preparation' => $store->count('commande', [['statut', '=', 'EN_PREPARATION']]),
            'clients' => $store->count('client'),
            'produits' => $store->count('produit'),
            'ca_total' => array_sum(array_column($rapport['par_statut'], 'montant_total')),
        ];

        $this->render('admin/dashboard', [
            'indicateurs' => $indicateurs,
            'rapport' => $rapport,
            'alertes' => $produits->alertesStock(),
        ], 'Back-office — tableau de bord');
    }
}
