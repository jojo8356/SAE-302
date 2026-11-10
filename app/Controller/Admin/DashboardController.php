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
use App\Tools\Heure;

final class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();

        $rapport = (CommandeRepository::for($store))->revenueReport();
        $produits = new ProduitRepository($store);

        // EF-ADM-09 : le tableau de bord distingue le jour même du cumul.
        $jourLocal = Heure::maintenant()->format('Y-m-d');
        $debutJour = Heure::borneJourUtc($jourLocal);
        $finJour = Heure::borneJourUtc($jourLocal, true);
        if ($debutJour === null || $finJour === null) {
            throw new \RuntimeException('Jour courant invalide.');
        }
        $jourTransmisAccepte = false;
        $jourTransmis = $this->str('jour_local', 10);
        $debutTransmis = Heure::borneTransmise($jourTransmis, $this->str('debut_utc', 19));
        $finTransmise = Heure::borneTransmise($jourTransmis, $this->str('fin_utc', 19), true);
        if ($debutTransmis !== null && $finTransmise !== null) {
            $debutTs = strtotime($debutTransmis . ' UTC');
            $finTs = strtotime($finTransmise . ' UTC');
            if ($debutTs !== false && $finTs !== false && $finTs - $debutTs >= 20 * 3600
                && $finTs - $debutTs <= 28 * 3600 && $debutTs <= time() && $finTs > time()) {
                $jourTransmisAccepte = true;
                $debutJour = $debutTransmis;
                $finJour = $finTransmise;
            }
        }
        $filtreJour = [Filter::gte('date_commande', $debutJour), Filter::lt('date_commande', $finJour)];
        $duJour = [];
        foreach ($store->select('commande', ['where' => $filtreJour]) as $commande) {
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
            'commandes_jour' => $store->count('commande', $filtreJour),
            'ca_jour' => array_sum($duJour),
        ];

        $this->render('admin/dashboard', [
            'indicateurs' => $indicateurs,
            'jourTransmisAccepte' => $jourTransmisAccepte,
            'rapport' => $rapport,
            'alertes' => $produits->alertesStock(),
        ], 'Back-office — tableau de bord');
    }
}
