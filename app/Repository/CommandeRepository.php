<?php

declare(strict_types=1);

/**
 * MiniShop — commandes & lignes (portage de sp_create_order, sp_add_order_line,
 * sp_create_order_from_basket, sp_confirm_order, sp_update_order_status,
 * sp_cancel_order, sp_revenue_report).
 *
 * L'ordre des opérations de sp_add_order_line est critique et conservé :
 *   1) contrôle RB-05 (quantité > 0) AVANT toute écriture ;
 *   2) verrou « commande » (statut modifiable : BROUILLON/EN_PREPARATION) ;
 *   3) lecture produit + disponibilité agrégée (stock − déjà réservé) ;
 *   4) INSERT de la ligne (le déclencheur fige le prix RB-06) ;
 *   5) décrément du stock par le déclencheur (RB-18) ;
 *   6) recalcul du montant = Σ lignes + port (RB-15) — le port est recalculé
 *      à chaque ligne tant que la commande est BROUILLON (la franchise ne doit
 *      pas dépendre de l'ordre d'ajout), puis figé à la validation (RB-20).
 *
 * Toute écriture multi-tables est transactionnelle (ENF-16) : un refus ne
 * laisse JAMAIS de données incohérentes (rollback automatique).
 */

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Model\Data\Triggers;

final class CommandeRepository
{
    public function __construct(
        private readonly StoreInterface $store,
        ?ParametreRepository $parametres = null,
    ) {
        $this->parametres = $parametres ?? new ParametreRepository($store);
    }

    /** @var ParametreRepository */
    private readonly ParametreRepository $parametres;

    /** Fabrique pratique : les deux repositories partagent le même store. */
    public static function for(StoreInterface $store): self
    {
        return new self($store, new ParametreRepository($store));
    }

    public function withStore(StoreInterface $store): self
    {
        return new self($store, new ParametreRepository($store));
    }

    // ------------------------------------------------------------ lecture

    public function find(int $idCommande): ?array
    {
        return $this->store->find('commande', $idCommande);
    }

    public function findByNumero(string $numero): ?array
    {
        return $this->store->findOne('commande', [Filter::eq('numero', $numero)]);
    }

    /** Lignes d'une commande (avec nom/référence du produit pour l'affichage). */
    public function lignes(int $idCommande): array
    {
        $produits = $this->store->all('produit');
        $lignes = [];
        foreach ($this->store->select('ligne_commande', [
            'where' => [Filter::eq('id_commande', $idCommande)],
            'order' => [Filter::sort('id_ligne')],
        ]) as $ligne) {
            $produit = $produits[(int) $ligne['id_produit']] ?? null;
            $ligne['produit_nom'] = $produit['nom'] ?? '(produit retiré)';
            $ligne['produit_reference'] = $produit['reference'] ?? '—';
            $ligne['produit_slug'] = $produit['slug'] ?? null;
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    /** Piste d'audit des statuts d'une commande (RB-11, EF-ADM-08). */
    public function historique(int $idCommande): array
    {
        return $this->store->select('order_status_history', [
            'where' => [Filter::eq('order_id', $idCommande)],
            'order' => [Filter::sort('id')],
        ]);
    }

    /** Historique de commandes d'un client (UC-08, EF-CLI-08). */
    public function pourClient(int $idClient): array
    {
        return $this->store->select('commande', [
            'where' => [Filter::eq('id_client', $idClient)],
            'order' => [Filter::sort('date_commande', 'desc')],
        ]);
    }

    // ----------------------------------------------------------- écriture

    /**
     * sp_create_order — ouverture d'une commande BROUILLON (première étape
     * du cas d'usage « Passer une commande », UC-07).
     *
     * @return array{id_commande: int, numero: string}
     */
    public function createOrder(int $idClient, string $adresse): array
    {
        $client = $this->store->find('client', $idClient);
        if ($client === null || !$client['actif']) {
            throw new BusinessError('CLIENT_INTROUVABLE_OU_BLOQUE');
        }
        $adresse = trim($adresse);
        if ($adresse === '') {
            throw new BusinessError('ADRESSE_LIVRAISON_REQUISE');
        }

        // numéro lisible CMD2026-000123 : la valeur d'auto-incrément est lue
        // AVANT l'insertion (le store ne consomme pas le compteur)
        $id = $this->store->nextId('commande');
        $numero = 'CMD' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $idCommande = $this->store->insert('commande', [
            'numero' => $numero,
            'id_client' => $idClient,
            'adresse_livraison' => $adresse,
            'statut' => 'BROUILLON',
        ]);

        return ['id_commande' => $idCommande, 'numero' => $numero];
    }

    /**
     * sp_add_order_line — ajout d'une ligne : RB-05/06/18 puis recalcul RB-15.
     *
     * @return array{id_ligne: int, total: float} total = somme des lignes de la commande
     */
    public function addOrderLine(int $idCommande, int $idProduit, int $quantite): array
    {
        if ($quantite <= 0) {
            throw new BusinessError('RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO'); // (1)
        }

        $commande = $this->store->find('commande', $idCommande);
        if ($commande === null) {
            throw new BusinessError('COMMANDE_INTROUVABLE'); // (2)
        }
        if (!in_array($commande['statut'], ['BROUILLON', 'EN_PREPARATION'], true)) {
            throw new BusinessError('COMMANDE_NON_MODIFIABLE');
        }

        $produit = $this->store->find('produit', $idProduit);
        if ($produit === null) {
            throw new BusinessError('PRODUIT_SUPPRIME_DU_CATALOGUE'); // (3) RB-17
        }

        // (3 bis) disponibilité = stock − quantité déjà réservée SUR CETTE commande
        $dejaReserve = 0;
        foreach ($this->store->select('ligne_commande', [
            'where' => [Filter::eq('id_commande', $idCommande), Filter::eq('id_produit', $idProduit)],
        ]) as $ligne) {
            $dejaReserve += (int) $ligne['quantite'];
        }
        if ((int) $produit['stock'] - $dejaReserve < $quantite) {
            throw new BusinessError('STOCK_INSUFFISANT'); // RB-18
        }

        // (4)+(5) une seule écriture : les déclencheurs figent le prix (RB-06)
        // et retirent les unités du stock (RB-18)
        $idLigne = $this->store->insert('ligne_commande', [
            'id_commande' => $idCommande,
            'id_produit' => $idProduit,
            'quantite' => $quantite,
            'prix_unitaire' => (float) $produit['prix_ttc'], // écrasé par le déclencheur
            'total_ligne' => round($quantite * (float) $produit['prix_ttc'], 2),
        ]);

        // (6) RB-15 / RB-20 : montant = Σ lignes + port ; le port est recalculé
        // tant que la commande est BROUILLON (franchise indépendante de l'ordre)
        $somme = $this->store->sum('ligne_commande', 'total_ligne', [Filter::eq('id_commande', $idCommande)]);
        $port = (float) $commande['frais_port'];
        if ($commande['statut'] === 'BROUILLON') {
            $port = $this->parametres->computeShipping($somme)['port'];
        }
        $this->store->update('commande', [Filter::eq('id_commande', $idCommande)], [
            'frais_port' => $port,
            'montant_total' => round($somme + $port, 2),
        ]);

        return ['id_ligne' => $idLigne, 'total' => $somme];
    }

    /**
     * sp_create_order_from_basket — commande complète depuis le panier session
     * (UC-07) : une seule transaction, un contrôle de stock par produit.
     *
     * @param list<array{id_produit: int, quantite: int}> $panier
     * @param bool $payee true => statut initial PAYEE, sinon EN_PREPARATION
     * @return array{id_commande: int, numero: string, montant: float}
     */
    public function createOrderFromBasket(int $idClient, string $adresse, array $panier, bool $payee = false): array
    {
        if ($panier === []) {
            throw new BusinessError('RB19_PANIER_VIDE');
        }

        return $this->store->transactional(function () use ($idClient, $adresse, $panier, $payee): array {
            $creation = $this->createOrder($idClient, $adresse);
            foreach ($panier as $article) {
                $this->addOrderLine(
                    (int) $creation['id_commande'],
                    (int) $article['id_produit'],
                    (int) $article['quantite']
                );
            }
            // statut initial de la commande validée : PAYEE (paiement simulé)
            // ou EN_PREPARATION — la matrice RB-11 autorise BROUILLON → les deux
            $nouveauStatut = $payee ? 'PAYEE' : 'EN_PREPARATION';
            $this->store->update('commande', [Filter::eq('id_commande', $creation['id_commande'])], ['statut' => $nouveauStatut]);
            $commande = $this->store->find('commande', $creation['id_commande']);

            return [
                'id_commande' => (int) $creation['id_commande'],
                'numero' => (string) $creation['numero'],
                'montant' => (float) $commande['montant_total'],
            ];
        });
    }

    /**
     * sp_confirm_order — validation du panier (RB-04 : au moins une ligne ;
     * RB-20 : le port est figé au snapshot, comme le prix).
     *
     * @return float montant total confirmé
     */
    public function confirmOrder(int $idCommande, ?int $idClient, bool $payee = false): float
    {
        $commande = $this->store->find('commande', $idCommande);
        if ($commande === null) {
            throw new BusinessError('COMMANDE_INTROUVABLE');
        }
        // RB-15 / SEC-08 : contrôle d'appartenance côté serveur — la requête
        // HTTP peut très bien porter le numéro d'une commande d'un autre client
        if ($idClient !== null && (int) $commande['id_client'] !== $idClient) {
            throw new BusinessError('ACCES_NON_AUTORISE');
        }
        if ($commande['statut'] !== 'BROUILLON') {
            throw new BusinessError('COMMANDE_DEJA_VALIDEE');
        }

        $nbLignes = $this->store->count('ligne_commande', [Filter::eq('id_commande', $idCommande)]);
        if ($nbLignes === 0) {
            throw new BusinessError('RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE');
        }

        // RB-18 : re-vérification qu'aucun stock n'est passé négatif entre-temps
        foreach ($this->lignes($idCommande) as $ligne) {
            $produit = $this->store->find('produit', (int) $ligne['id_produit']);
            if ($produit !== null && (int) $produit['stock'] < 0) {
                throw new BusinessError('STOCK_NEGATIF_DETECTE');
            }
        }

        $somme = $this->store->sum('ligne_commande', 'total_ligne', [Filter::eq('id_commande', $idCommande)]);
        $port = $this->parametres->computeShipping($somme)['port']; // snapshot RB-20

        $this->store->update('commande', [Filter::eq('id_commande', $idCommande)], [
            'frais_port' => $port,
            'montant_total' => round($somme + $port, 2),
            'statut' => $payee ? 'PAYEE' : 'EN_PREPARATION',
        ]);

        return round($somme + $port, 2);
    }

    /**
     * sp_update_order_status — changement de statut (EF-ADM-07, RB-11).
     * La matrice est contrôlée par le déclencheur (défense en profondeur) ;
     * la règle « le client n'annule que tant que ce n'est pas expédié » est ici.
     *
     * @param 'CLIENT'|'ADMIN'|'SYSTEME' $roleAuteur
     * @return 'OK'|'STATUT_DEJA_A_JOUR'
     */
    public function updateOrderStatus(int $idCommande, string $nouveauStatut, ?string $commentaire, ?int $idAuteur, string $roleAuteur): string
    {
        if (!in_array($nouveauStatut, Triggers::STATUTS, true)) {
            throw new BusinessError('STATUT_NON_AUTORISE');
        }
        $commande = $this->store->find('commande', $idCommande);
        if ($commande === null) {
            throw new BusinessError('COMMANDE_INTROUVABLE');
        }
        // règle métier : le client annule tant que ce n'est pas expédié ; l'admin
        // peut en plus piloter tout le cycle (matrice RB-11 côté déclencheur)
        if ($roleAuteur === 'CLIENT' && !in_array($commande['statut'], ['BROUILLON', 'EN_PREPARATION', 'PAYEE'], true)) {
            throw new BusinessError('ANNULATION_TROP_TARDIVE');
        }

        if ($commande['statut'] === $nouveauStatut) {
            return 'STATUT_DEJA_A_JOUR';
        }

        $this->store->setActor(['id' => $idAuteur, 'role' => $roleAuteur]);
        $changes = ['statut' => $nouveauStatut];
        if ($commentaire !== null && trim($commentaire) !== '') {
            $changes['commentaire'] = trim($commentaire);
        }
        $this->store->update('commande', [Filter::eq('id_commande', $idCommande)], $changes);
        $this->store->setActor(['id' => null, 'role' => 'SYSTEME']);

        return 'OK';
    }

    /**
     * sp_cancel_order — annulation par le client (UC-09, EF-CLI-10) :
     * restitution du stock LIGNE À LIGNE par le déclencheur trg_ligne_restore_stock,
     * puis statut ANNULEE ; le port est ANNULÉ avec les lignes (RB-20 : une
     * commande annulée n'a plus rien à facturer) et le montant soldé (RB-15).
     *
     * @return array{unites_rendues: int, montant_rembourse: float}
     */
    public function cancelOrder(int $idCommande, ?int $idClient): array
    {
        $commande = $this->store->find('commande', $idCommande);
        if ($commande === null) {
            throw new BusinessError('COMMANDE_INTROUVABLE');
        }
        if ($idClient !== null && (int) $commande['id_client'] !== $idClient) {
            throw new BusinessError('ACCES_NON_AUTORISE');
        }
        if (!in_array($commande['statut'], ['BROUILLON', 'EN_PREPARATION', 'PAYEE'], true)) {
            throw new BusinessError('COMMANDE_NON_ANNULABLE');
        }

        return $this->store->transactional(function () use ($idCommande, $idClient, $commande): array {
            $this->store->setActor(['id' => $idClient, 'role' => 'CLIENT']);
            $unitesRendues = 0;
            foreach ($this->lignes($idCommande) as $ligne) {
                $unitesRendues += (int) $ligne['quantite'];
                $this->store->delete('ligne_commande', [Filter::eq('id_ligne', $ligne['id_ligne'])]);
            }
            $this->store->update('commande', [Filter::eq('id_commande', $idCommande)], [
                'statut' => 'ANNULEE',
                'frais_port' => 0.0,
                'montant_total' => 0.0,
                'commentaire' => $commande['commentaire'] ?? 'Annulation demandée par le client',
            ]);
            $this->store->setActor(['id' => null, 'role' => 'SYSTEME']);

            return ['unites_rendues' => $unitesRendues, 'montant_rembourse' => (float) $commande['montant_total']];
        });
    }

    /**
     * sp_revenue_report — tableau de bord back-office (EF-ADM-07) :
     * ventes par statut + top 5 produits (commandes non annulées).
     *
     * @return array{par_statut: list<array<string,mixed>>, top_produits: list<array<string,mixed>>}
     */
    public function revenueReport(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $where = [];
        if ($dateDebut !== null) {
            $where[] = Filter::gte('date_commande', $dateDebut);
        }
        if ($dateFin !== null) {
            $where[] = Filter::lt('date_commande', $dateFin);
        }

        // 1) par statut
        $parStatut = [];
        foreach ($this->store->select('commande', ['where' => $where]) as $commande) {
            $statut = (string) $commande['statut'];
            $parStatut[$statut] ??= ['statut' => $statut, 'nb_commandes' => 0, 'montant_total' => 0.0];
            ++$parStatut[$statut]['nb_commandes'];
            $parStatut[$statut]['montant_total'] = round($parStatut[$statut]['montant_total'] + (float) $commande['montant_total'], 2);
        }
        foreach ($parStatut as &$ligne) {
            $ligne['montant_moyen'] = $ligne['nb_commandes'] > 0
                ? round($ligne['montant_total'] / $ligne['nb_commandes'], 2)
                : 0.0;
        }
        unset($ligne);
        usort($parStatut, static fn (array $a, array $b): int => $b['montant_total'] <=> $a['montant_total']);

        // 2) top 5 produits : lignes des commandes NON annulées de la période
        $commandesValides = [];
        foreach ($this->store->select('commande', ['where' => $where !== [] ? array_merge($where, [Filter::neq('statut', 'ANNULEE')]) : [Filter::neq('statut', 'ANNULEE')]]) as $commande) {
            $commandesValides[(int) $commande['id_commande']] = true;
        }
        $produits = $this->store->all('produit');
        $top = [];
        foreach ($this->store->select('ligne_commande') as $ligne) {
            if (!isset($commandesValides[(int) $ligne['id_commande']])) {
                continue;
            }
            $idProduit = (int) $ligne['id_produit'];
            $top[$idProduit] ??= ['reference' => $produits[$idProduit]['reference'] ?? '—', 'nom' => $produits[$idProduit]['nom'] ?? '(retiré)', 'unites' => 0, 'ca' => 0.0];
            $top[$idProduit]['unites'] += (int) $ligne['quantite'];
            $top[$idProduit]['ca'] = round($top[$idProduit]['ca'] + (float) $ligne['total_ligne'], 2);
        }
        usort($top, static fn (array $a, array $b): int => $b['ca'] <=> $a['ca']);

        return ['par_statut' => array_values($parStatut), 'top_produits' => array_slice(array_values($top), 0, 5)];
    }
}
