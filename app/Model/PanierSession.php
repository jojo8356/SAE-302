<?php

declare(strict_types=1);

/**
 * MiniShop — panier en session (UC-06, EF-VIS-09/EF-CLI-03…06, RB-18/19).
 *
 * Le panier vit UNIQUEMENT dans la session (aucune écriture en base tant
 * que la commande n'est pas validée — EF-VIS-09) et :
 *   - chaque ajout est PLAFONNÉ au stock disponible mesuré côté SERVEUR
 *     (jamais au montant envoyé par le navigateur, EF-GEN-01, RB-18) ;
 *   - les produits masqués (RB-19) ou en rupture sont refusés ;
 *   - le total et le port sont recalculés serveur (RB-15/20) ;
 *   - il est vidé après validation de la commande (EF-GEN-02).
 */

namespace App\Model;

use App\Model\Data\BusinessError;
use App\Model\Data\StoreInterface;
use App\Repository\ParametreRepository;
use App\Repository\ProduitRepository;

final class PanierSession
{
    private const CLE = 'panier'; // [id_produit => quantite]

    public function __construct(
        private readonly StoreInterface $store,
        private readonly ProduitRepository $produits,
        private readonly ParametreRepository $parametres,
    ) {
        AuthSession::demarrer();
    }

    /** Fabrique pratique branchée sur le store applicatif. */
    public static function chargé(): self
    {
        $store = \App\Config\Database::store();

        return new self($store, new ProduitRepository($store), new ParametreRepository($store));
    }

    /** @return array<int, int> id_produit => quantité */
    public function articlesBruts(): array
    {
        $panier = $_SESSION[self::CLE] ?? [];
        $nettoye = [];
        foreach (is_array($panier) ? $panier : [] as $id => $qte) {
            if (is_numeric($id) && (int) $qte > 0) {
                $nettoye[(int) $id] = (int) $qte;
            }
        }
        $_SESSION[self::CLE] = $nettoye;

        return $nettoye;
    }

    /**
     * Ajoute une quantité d'un produit (plafonnée au stock serveur, RB-18).
     *
     * @return int quantité réellement dans le panier après l'ajout
     */
    public function ajouter(int $idProduit, int $quantite): int
    {
        if ($quantite <= 0) {
            throw new BusinessError('RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO');
        }
        $produit = $this->store->find('produit', $idProduit);
        if ($produit === null || !$produit['visible']) {
            throw new BusinessError('PRODUIT_SUPPRIME_DU_CATALOGUE'); // RB-19
        }

        $panier = $this->articlesBruts();
        $demande = ($panier[$idProduit] ?? 0) + $quantite;
        $panier[$idProduit] = min($demande, (int) $produit['stock']); // plafond serveur
        $_SESSION[self::CLE] = $panier;

        return $panier[$idProduit];
    }

    /** Modifie la quantité d'une ligne (EF-CLI-04 : plafonnée au stock). */
    public function modifierQuantite(int $idProduit, int $quantite): void
    {
        $panier = $this->articlesBruts();
        if (!isset($panier[$idProduit])) {
            return;
        }
        if ($quantite <= 0) {
            unset($panier[$idProduit]);
            $_SESSION[self::CLE] = $panier;

            return;
        }
        $produit = $this->store->find('produit', $idProduit);
        $stock = $produit === null ? 0 : (int) $produit['stock'];
        $panier[$idProduit] = min($quantite, max(0, $stock));
        $_SESSION[self::CLE] = $panier;
    }

    public function retirer(int $idProduit): void
    {
        $panier = $this->articlesBruts();
        unset($panier[$idProduit]);
        $_SESSION[self::CLE] = $panier;
    }

    public function vider(): void
    {
        $_SESSION[self::CLE] = [];
    }

    public function estVide(): bool
    {
        return $this->articlesBruts() === [];
    }

    /** Nombre total d'articles (badge du panier). */
    public function nombreArticles(): int
    {
        return array_sum($this->articlesBruts());
    }

    /**
     * Panier complet pour l'affichage / la validation : chaque ligne joint
     * le produit courant (nom, prix TTC SERVEUR — jamais un prix client),
     * le total de ligne, et l'écart de stock éventuel.
     *
     * @return array{articles: list<array<string,mixed>>, sous_total: float, frais_port: float, total: float, port_motif: string}
     */
    public function recapitulatif(): array
    {
        $articles = [];
        $sousTotal = 0.0;
        foreach ($this->articlesBruts() as $idProduit => $quantite) {
            $produit = $this->store->find('produit', $idProduit);
            if ($produit === null || !$produit['visible']) {
                $this->retirer($idProduit); // produit retiré du catalogue entre-temps (RB-17/19)
                continue;
            }
            $quantiteEffective = min($quantite, (int) $produit['stock']);
            $prixTtc = (float) $produit['prix_ttc'];
            $totalLigne = round($quantiteEffective * $prixTtc, 2);
            $sousTotal = round($sousTotal + $totalLigne, 2);
            $articles[] = [
                'id_produit' => (int) $idProduit,
                'quantite_demandee' => $quantite,
                'quantite' => $quantiteEffective,
                'stock_disponible' => (int) $produit['stock'],
                'nom' => (string) $produit['nom'],
                'slug' => (string) $produit['slug'],
                'reference' => (string) $produit['reference'],
                'prix_unitaire' => $prixTtc,
                'total_ligne' => $totalLigne,
                'plafonne' => $quantiteEffective < $quantite,
            ];
        }

        $port = $this->parametres->computeShipping($sousTotal);

        return [
            'articles' => $articles,
            'sous_total' => $sousTotal,
            'frais_port' => $port['port'],
            'total' => round($sousTotal + $port['port'], 2),
            'port_motif' => $port['motif'],
        ];
    }

    /** Panier au format attendu par CommandeRepository::createOrderFromBasket(). */
    public function pourCommande(): array
    {
        return array_map(
            static fn (array $a): array => ['id_produit' => $a['id_produit'], 'quantite' => $a['quantite']],
            $this->recapitulatif()['articles']
        );
    }
}

/** Démarrage de session sans dépendance circulaire (délégation vers Auth). */
final class AuthSession
{
    public static function demarrer(): void
    {
        \App\Security\Auth::demarrer();
    }
}
