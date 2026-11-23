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
use App\Model\Data\StoredFunctionExecutor;
use App\Model\Data\StoredProcedureExecutor;
use App\Model\Data\StoreInterface;
use UnexpectedValueException;

final class ParametreRepository
{
    /** Injecte le store abstrait servant de source aux paramètres commerciaux. */
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** fn_param(p_cle, p_defaut) : valeur lue dans la table, sinon défaut. */
    public function fnParam(string $cle, string $defaut): string
    {
        if ($this->store instanceof StoredFunctionExecutor) {
            $valeur = $this->store->callFunction('fn_param', [$cle, $defaut]);
            if ($valeur !== null && (string) $valeur !== '') {
                return (string) $valeur;
            }

            return $defaut;
        }

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
        if ($this->store instanceof StoredProcedureExecutor) {
            $resultat = $this->store->callProcedure(
                'sp_compute_shipping',
                [round($marchandises, 2)],
                ['p_frais_port', 'p_motif'],
            );
            $sorties = $resultat['out'] ?? [];
            $port = $sorties['p_frais_port'] ?? null;
            $motifSql = $sorties['p_motif'] ?? null;
            if (!is_numeric($port) || !is_string($motifSql)) {
                throw new UnexpectedValueException('sp_compute_shipping n’a pas retourné des sorties valides.');
            }

            if (str_starts_with($motifSql, 'PORT_OFFERT_A_PARTIR_DE_')) {
                $franchise = round((float) $this->fnParam('franchise_port', '80.00'), 2);

                return [
                    'port' => round((float) $port, 2),
                    'motif' => 'frais de port offerts dès ' . number_format($franchise, 2, ',', ' ') . ' € de marchandises',
                ];
            }

            return [
                'port' => round((float) $port, 2),
                'motif' => 'livraison standard (' . number_format((float) $port, 2, ',', ' ') . ' €)',
            ];
        }

        $portStandard = round((float) $this->fnParam('frais_port', '4.90'), 2);
        $franchise = round((float) $this->fnParam('franchise_port', '80.00'), 2);

        if ($marchandises >= $franchise) {
            return ['port' => 0.0, 'motif' => 'frais de port offerts dès ' . number_format($franchise, 2, ',', ' ') . ' € de marchandises'];
        }

        return ['port' => $portStandard, 'motif' => 'livraison standard (' . number_format($portStandard, 2, ',', ' ') . ' €)'];
    }

    /** TVA proposée par défaut à la création d'un produit (EF-ADM-01). */
    public function tvaDefaut(): float
    {
        return round((float) $this->fnParam('tva_defaut', '20.00'), 2);
    }

    /**
     * Met à jour un paramètre existant (écran back-office, EF-ADM-11).
     *
     * Les paramètres sont des données de pilotage commercial : frais de port,
     * franchise, TVA par défaut, durée de conservation. Aucune constante n'est
     * recopiée dans le code (voir l'en-tête de cette classe).
     *
     * @throws \App\Model\Data\BusinessError si le paramètre est inconnu ou la valeur invalide
     */
    public function enregistrer(string $cle, string $valeur): void
    {
        $row = $this->store->find('parametre', $cle);
        if ($row === null) {
            throw new \App\Model\Data\BusinessError('PARAMETRE_INTROUVABLE');
        }

        $valeur = trim($valeur);
        if ($valeur === '' || preg_match('/^-?\d+([.,]\d+)?$/', $valeur) !== 1) {
            throw new \App\Model\Data\BusinessError('PARAMETRE_VALEUR_INVALIDE');
        }

        if ($cle === 'conservation_compte' && (preg_match('/^[1-9][0-9]{0,2}$/', $valeur) !== 1 || (int) $valeur > 120)) {
            throw new \App\Model\Data\BusinessError('PARAMETRE_VALEUR_INVALIDE');
        }
        $this->store->update('parametre', [['cle', '=', $cle]], ['valeur' => str_replace(',', '.', $valeur)]);
    }

    /** Tous les paramètres (page d'affichage back-office). */
    public function tous(): array
    {
        return $this->store->select('parametre', ['order' => [Filter::sort('cle')]]);
    }
}
