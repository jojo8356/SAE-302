<?php

declare(strict_types=1);

/**
 * MiniShop — API interne du panier (EF-CLI-03, §4.5.3 de la DA).
 *
 * Contrepartie serveur du panier sans rechargement. Trois principes :
 *   1. le serveur reste la source unique des prix, du stock et des totaux
 *      (EF-GEN-01) : le JSON renvoyé contient les valeurs AFFICHABLES ;
 *   2. toute écriture exige le jeton CSRF de la session (SEC-05), même en
 *      « fetch » — le jeton est envoyé dans le corps du formulaire ;
 *   3. les erreurs métier sont traduites en messages humains (EF-GEN-03)
 *      et jamais en trace technique.
 *
 * Sans JavaScript, les vues continuent d'utiliser les routes HTML
 * (`POST /panier/ajouter`) : ces routes ne sont qu'une amélioration (ENF-07).
 */

namespace App\Controller;

use App\Model\Data\BusinessError;
use App\Model\PanierSession;
use App\Security\Csrf;

final class ApiController extends Controller
{
    private PanierSession $panier;

    public function __construct()
    {
        $this->panier = PanierSession::chargé();
    }

    /** État courant du panier (badge, compteurs, total) — lecture seule. */
    public function etat(): void
    {
        $recap = $this->panier->recapitulatif();

        $this->json(200, [
            'ok' => true,
            'nombre_articles' => $this->panier->nombreArticles(),
            'nb_lignes' => count($recap['articles']),
            'sous_total' => $this->montant($recap['sous_total']),
            'frais_port' => $this->montant($recap['frais_port']),
            'total_ttc' => $this->montant($recap['total']),
        ]);
    }

    /** EF-CLI-03 — ajout avec plafonnement au stock serveur (RB-18). */
    public function ajouter(): void
    {
        $this->exigerPoste();

        $idProduit = $this->int('id_produit') ?? 0;
        $quantite = max(1, $this->int('quantite') ?? 1);

        try {
            $this->panier->ajouter($idProduit, $quantite);
        } catch (BusinessError $e) {
            $this->json(409, [
                'ok' => false,
                'code' => $e->businessCode,
                'message' => $e->messageHumain(),
                'nombre_articles' => $this->panier->nombreArticles(),
            ]);

            return;
        }

        $recap = $this->panier->recapitulatif();
        $this->json(200, [
            'ok' => true,
            'message' => 'Produit ajouté au panier.',
            'nombre_articles' => $this->panier->nombreArticles(),
            'sous_total' => $this->montant($recap['sous_total']),
            'frais_port' => $this->montant($recap['frais_port']),
            'total_ttc' => $this->montant($recap['total']),
            'lien_panier' => '/panier',
        ]);
    }

    /** EF-CLI-04 — modification de quantité (0 = retrait explicite). */
    public function quantite(): void
    {
        $this->exigerPoste();
        $idProduit = $this->int('id_produit') ?? 0;
        $quantite = $this->int('quantite') ?? 0;

        try {
            $this->panier->modifierQuantite($idProduit, $quantite);
        } catch (BusinessError $e) {
            $this->json(409, [
                'ok' => false,
                'code' => $e->businessCode,
                'message' => $e->messageHumain(),
                'nombre_articles' => $this->panier->nombreArticles(),
            ]);

            return;
        }

        $recap = $this->panier->recapitulatif();
        $this->json(200, [
            'ok' => true,
            'message' => 'Quantité mise à jour.',
            'nombre_articles' => $this->panier->nombreArticles(),
            'total_ttc' => $this->montant($recap['total']),
        ]);
    }

    /** EF-CLI-05 — retrait d'une ligne. */
    public function retirer(): void
    {
        $this->exigerPoste();
        $this->panier->retirer($this->int('id_produit') ?? 0);

        $recap = $this->panier->recapitulatif();
        $this->json(200, [
            'ok' => true,
            'message' => 'Article retiré du panier.',
            'nombre_articles' => $this->panier->nombreArticles(),
            'total_ttc' => $this->montant($recap['total']),
        ]);
    }

    /** Vérifie la méthode et le jeton CSRF d'une écriture JSON. */
    private function exigerPoste(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            $this->json(405, ['ok' => false, 'message' => 'Méthode non autorisée.']);
            exit;
        }

        $this->verifierCsrf();
    }

    /**
     * Redéfinition de Controller::verifierCsrf() pour le mode JSON.
     *
     * Le contrat de l'API (docs/05-api.md) impose un corps JSON et un code
     * 403 : on ne peut donc pas laisser Csrf::verifier() rediriger vers /.
     * L'invariant SEC-05 reste identique — jeton lu dans le corps de la
     * requête, comparaison en temps constant via hash_equals().
     */
    protected function verifierCsrf(): void
    {
        if (Csrf::estValide()) {
            return;
        }

        $this->json(403, [
            'ok' => false,
            'code' => 'CSRF_INVALIDE',
            'message' => 'Jeton de sécurité invalide ou expiré. Rechargez la page.',
        ]);
        exit;
    }

    private function montant(float $valeur): float
    {
        return round($valeur, 2);
    }

    /** @param array<string,mixed> $charge */
    private function json(int $statut, array $charge): void
    {
        http_response_code($statut);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
