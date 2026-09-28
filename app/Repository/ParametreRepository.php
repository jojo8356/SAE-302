<?php

declare(strict_types=1);

/**
 * MiniShop — paramètres métier (portage de fn_param + sp_compute_shipping).
 *
 * Les règles chiffrées (frais de port, franchise, TVA par défaut) sont des
 * DONNÉES (table « parametre »), pas des constantes recopiées dans le code :
 * c'est ce qui permet de les faire évoluer sans toucher une ligne de PHP
 * (RB-15, ENF-14).
 */

namespace App\Repository;

use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;

final class ParametreRepository
{
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** fn_param(p_cle, p_defaut) : valeur lue dans la table, sinon défaut. */
    public function fnParam(string $cle, string $defaut): string
    {
        $row = $this->store->find('parametre', $cle);

        if (($row['valeur'] ?? '') !== '') {
            return (string) $row['valeur'];
        }

        return $defaut;
    }

    /**
     * sp_compute_shipping(p_marchandises) : frais de port applicables,
     * calculés AVANT la validation (obligation d'affichage, art. L221-5
     * 4° C. conso.) — 4,90 € en standard, offert à partir de 80,00 €.
     *
     * @return array{port: float, motif: string}
     */
    public function computeShipping(float $marchandises): array
    {
        $portStandard = round((float) $this->fnParam('frais_port', '4.90'), 2);
        $franchise = round((float) $this->fnParam('franchise_port', '80.00'), 2);

        if ($marchandises >= $franchise) {
            return ['port' => 0.0, 'motif' => 'PORT_OFFERT_A_PARTIR_DE_' . number_format($franchise, 2, '.', '')];
        }

        return ['port' => $portStandard, 'motif' => 'LIVRAISON_STANDARD'];
    }

    /** TVA proposée par défaut à la création d'un produit (EF-ADM-01). */
    public function tvaDefaut(): float
    {
        return round((float) $this->fnParam('tva_defaut', '20.00'), 2);
    }

    /** Tous les paramètres (page d'affichage back-office). */
    public function tous(): array
    {
        return $this->store->select('parametre', ['order' => [Filter::sort('cle')]]);
    }
}
