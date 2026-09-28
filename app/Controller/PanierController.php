<?php

declare(strict_types=1);

/**
 * MiniShop — panier (UC-06, EF-CLI-03/04/06, EF-VIS-09, RB-18/19).
 *
 * Le panier « hors session » est consultable sans compte, mais la commande
 * exige une connexion (contrôleur Commande). Aucun prix ni stock n'est lu
 * dans le navigateur : le serveur est la source unique (EF-GEN-01).
 */

namespace App\Controller;

use App\Model\Data\BusinessError;
use App\Model\PanierSession;

final class PanierController extends Controller
{
    private PanierSession $panier;

    public function __construct()
    {
        $this->panier = PanierSession::chargé();
    }

    public function index(): void
    {
        $this->render('panier/index', ['recap' => $this->panier->recapitulatif()], 'Mon panier');
    }

    /** EF-CLI-03 — ajout avec quantité, plafonné au stock serveur (RB-18). */
    public function ajouter(): void
    {
        $this->verifierCsrf();
        $idProduit = $this->int('id_produit') ?? 0;
        $quantite = $this->int('quantite') ?? 1;

        try {
            $this->panier->ajouter($idProduit, max(1, $quantite));
            $this->flashSucces('Produit ajouté au panier.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/panier');
    }

    /** EF-CLI-04 — modification des quantités (plafonnée au stock disponible). */
    public function quantite(): void
    {
        $this->verifierCsrf();
        $idProduit = $this->int('id_produit') ?? 0;
        $quantite = $this->int('quantite') ?? 0;

        $this->panier->modifierQuantite($idProduit, $quantite);
        $this->rediriger('/panier');
    }

    public function retirer(): void
    {
        $this->verifierCsrf();
        $this->panier->retirer($this->int('id_produit') ?? 0);
        $this->flashSucces('Article retiré du panier.');
        $this->rediriger('/panier');
    }
}
