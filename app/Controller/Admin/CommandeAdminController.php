<?php

declare(strict_types=1);

/**
 * MiniShop — consultation et pilotage des commandes (UC-13/14, EF-ADM-06/07/08,
 * RB-09/11) : liste filtrable, détail + piste d'audit, transitions contrôlées.
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\Triggers;
use App\Model\Data\Views;
use App\Repository\CommandeRepository;
use App\Security\Auth;

final class CommandeAdminController extends Controller
{
    /** EF-ADM-06 — liste filtrable : statut, client, période, montant. */
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();

        $where = [];
        $statut = $this->str('statut', 20);
        if ($statut !== '' && in_array($statut, Triggers::STATUTS, true)) {
            $where[] = Filter::eq('statut', $statut);
        }
        $client = $this->str('client', 120); // email ou numéro
        if ($client !== '') {
            $commandeParNumero = $store->findOne('commande', [Filter::eq('numero', $client)]);
            $clientRow = $store->findOne('client', [Filter::like('email', '%' . $client . '%')]);
            if ($commandeParNumero !== null || $clientRow !== null) {
                $where[] = Filter::or([
                    $commandeParNumero !== null ? Filter::eq('id_commande', (int) $commandeParNumero['id_commande']) : Filter::eq('id_commande', -1),
                    $clientRow !== null ? Filter::eq('id_client', (int) $clientRow['id_client']) : Filter::eq('id_client', -1),
                ]);
            } else {
                $where[] = Filter::eq('id_commande', -1); // aucun résultat : filtre explicitement vide
            }
        }
        $du = $this->str('du', 10);
        if ($du !== '') {
            $where[] = Filter::gte('date_commande', $du . ' 00:00:00');
        }
        $au = $this->str('au', 10);
        if ($au !== '') {
            $where[] = Filter::lte('date_commande', $au . ' 23:59:59');
        }
        $montantMin = $this->float('montant_min');
        if ($montantMin !== null) {
            $where[] = Filter::gte('montant_total', $montantMin);
        }

        $commandes = (new Views($store))->commandesClient([
            'where' => $where,
            'limit' => 100,
        ]);

        $this->render('admin/commandes', [
            'commandes' => $commandes,
            'statuts' => Triggers::STATUTS,
            'filtres' => ['statut' => $statut, 'client' => $client, 'du' => $du, 'au' => $au, 'montant_min' => $montantMin],
        ], 'Back-office — commandes');
    }

    /** EF-ADM-08 — détail + piste d'audit des statuts (RB-11). */
    public function detail(string $id): void
    {
        Auth::exigeAdmin();
        $store = Database::store();
        $repo = CommandeRepository::for($store);

        $commande = $repo->find((int) $id);
        if ($commande === null) {
            $this->flashErreur('Commande introuvable.');
            $this->rediriger('/admin/commandes');
        }

        $client = $store->find('client', (int) $commande['id_client']);

        $this->render('admin/commande_detail', [
            'commande' => $commande,
            'client' => $client,
            'lignes' => $repo->lignes((int) $id),
            'historique' => $repo->historique((int) $id),
            'transitions' => Triggers::TRANSITIONS[$commande['statut']] ?? [],
        ], 'Commande ' . $commande['numero']);
    }

    /** EF-ADM-07 / UC-14 — transition contrôlée (matrice RB-11 + commentaire). */
    public function changerStatut(string $id): void
    {
        $this->verifierCsrf();
        $admin = Auth::exigeAdmin();
        $repo = CommandeRepository::for(Database::store());

        $nouveauStatut = $this->str('statut', 20);
        $commentaire = $this->str('commentaire', 500) ?: null;

        // un commentaire est exigé pour une annulation (EF-ADM-07)
        if ($nouveauStatut === 'ANNULEE' && ($commentaire === null || trim($commentaire) === '')) {
            $this->flashErreur('Un commentaire est obligatoire pour annuler une commande.');
            $this->rediriger('/admin/commandes/' . $id);
        }

        try {
            $resultat = $repo->updateOrderStatus((int) $id, $nouveauStatut, $commentaire, (int) $admin['id'], 'ADMIN');
            $this->flashSucces($resultat === 'OK' ? 'Statut mis à jour : ' . $nouveauStatut . '.' : 'Le statut était déjà à jour.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/commandes/' . $id);
    }
}
