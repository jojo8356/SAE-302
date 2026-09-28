<?php

declare(strict_types=1);

/**
 * MiniShop — validation, historique, détail et annulation des commandes
 * (UC-07/08/09, EF-CLI-07…10, RB-04/06/08/10/11/15/18/20, SEC-08).
 *
 * Le contrôle d'appartenance est systématique côté serveur (SEC-08) : une
 * requête portant l'identifiant de la commande d'un AUTRE client répond
 * ACCES_NON_AUTORISE (test S-04 du document de tests).
 */

namespace App\Controller;

use App\Config\Database;
use App\Model\Data\BusinessError;
use App\Model\PanierSession;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Security\Auth;

final class CommandeController extends Controller
{
    /** Étape 1 — récapitulatif + adresse de livraison (port affiché AVANT validation, art. L221-5). */
    public function formulaire(): void
    {
        $client = Auth::exigeClient();
        $panier = PanierSession::chargé();
        $recap = $panier->recapitulatif();

        if ($panier->estVide()) {
            $this->flashErreur('Votre panier est vide (RB-19).');
            $this->rediriger('/catalogue');
        }

        $donnees = (new ClientRepository(Database::store()))->find($client['id']);
        $this->render('commande/valider', [
            'recap' => $recap,
            'client' => $donnees,
            'erreurs' => [],
        ], 'Valider ma commande');
    }

    /**
     * Étape 2 — validation : création transactionnelle depuis le panier
     * (UC-07), panier vidé ensuite (EF-GEN-02), stock décrémenté par les
     * déclencheurs (RB-18), prix et port figés (RB-06/RB-20).
     */
    public function valider(): void
    {
        $this->verifierCsrf();
        $session = Auth::exigeClient();
        $panier = PanierSession::chargé();

        $adresse = $this->str('adresse', 255);
        if ($adresse === '') {
            $this->flashErreur('Une adresse de livraison est requise.');
            $this->rediriger('/commande/valider');
        }

        $payee = $this->bool('paiement_simule'); // aucun paiement réel (§11.2 hors périmètre)

        try {
            Database::store()->setActor(['id' => $session['id'], 'role' => 'CLIENT', 'nom' => $session['prenom'] . ' ' . $session['nom']]);
            $resultat = (CommandeRepository::for(Database::store()))->createOrderFromBasket(
                $session['id'],
                $adresse,
                $panier->pourCommande(),
                $payee
            );
            $panier->vider(); // EF-GEN-02
            $this->flashSucces('Commande ' . $resultat['numero'] . ' enregistrée. Merci !');
            $this->rediriger('/mes-commandes/' . $resultat['id_commande']);
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
            $this->rediriger('/panier');
        }
    }

    /** UC-08 — historique des commandes du client (EF-CLI-08). */
    public function mes(): void
    {
        $client = Auth::exigeClient();
        $repo = CommandeRepository::for(Database::store());
        $commandes = [];
        foreach ($repo->pourClient($client['id']) as $commande) {
            $commande['nb_lignes'] = count($repo->lignes((int) $commande['id_commande']));
            $commandes[] = $commande;
        }

        $this->render('commande/mes', ['commandes' => $commandes], 'Mes commandes');
    }

    /** UC-08 — détail d'une de ses commandes (EF-CLI-09) + piste de statuts. */
    public function detail(string $id): void
    {
        $client = Auth::exigeClient();
        $repo = CommandeRepository::for(Database::store());

        try {
            $commande = $this->commandeDuClient($repo, (int) $id, $client['id']);
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
            $this->rediriger('/mes-commandes');
        }

        $this->render('commande/detail', [
            'commande' => $commande,
            'lignes' => $repo->lignes((int) $commande['id_commande']),
            'historique' => $repo->historique((int) $commande['id_commande']),
        ], 'Commande ' . $commande['numero']);
    }

    /** UC-09 — annulation tant que non expédiée (EF-CLI-10, RB-11/18/20). */
    public function annuler(string $id): void
    {
        $this->verifierCsrf();
        $client = Auth::exigeClient();
        $repo = CommandeRepository::for(Database::store());

        try {
            $commande = $this->commandeDuClient($repo, (int) $id, $client['id']);
            $resultat = $repo->cancelOrder((int) $commande['id_commande'], $client['id']);
            $this->flashSucces('Commande annulée : ' . $resultat['unites_rendues'] . ' unité(s) rendue(s) au stock.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/mes-commandes');
    }

    /** Contrôle d'appartenance serveur (SEC-08, RB-10). */
    private function commandeDuClient(CommandeRepository $repo, int $idCommande, int $idClient): array
    {
        $commande = $repo->find($idCommande);
        if ($commande === null) {
            throw new BusinessError('COMMANDE_INTROUVABLE');
        }
        if ((int) $commande['id_client'] !== $idClient) {
            throw new BusinessError('ACCES_NON_AUTORISE');
        }

        return $commande;
    }
}
