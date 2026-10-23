<?php

declare(strict_types=1);

/**
 * MiniShop — tableau de bord back-office (EF-ADM-00, EF-ADM-05/07).
 * Portage de sp_revenue_report + vue v_etat_stock + alertes.
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\Filter;
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

        // EF-ADM-09 : le tableau de bord distingue le jour même du cumul.
        $debutJour = date('Y-m-d 00:00:00');
        $duJour = [];
        foreach ($store->select('commande', ['where' => [Filter::gte('date_commande', $debutJour)]]) as $commande) {
            $statut = (string) $commande['statut'];
            $duJour[$statut] ??= 0;
            $duJour[$statut] += (float) $commande['montant_total'];
        }

        $indicateurs = [
            'commandes' => $store->count('commande'),
            'en_preparation' => $store->count('commande', [['statut', '=', 'EN_PREPARATION']]),
            'clients' => $store->count('client'),
            'produits' => $store->count('produit'),
            'ca_total' => array_sum(array_column($rapport['par_statut'], 'montant_total')),
            'commandes_jour' => $store->count('commande', [Filter::gte('date_commande', $debutJour)]),
            'ca_jour' => array_sum($duJour),
        ];

        $this->render('admin/dashboard', [
            'indicateurs' => $indicateurs,
            'rapport' => $rapport,
            'alertes' => $produits->alertesStock(),
        ], 'Back-office — tableau de bord');
    }
}
