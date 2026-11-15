<?php

declare(strict_types=1);

namespace App\Model\Data;

/** Capacité optionnelle à tracer un ajustement de stock hors commande. */
interface StockMovementAuditor
{
    /**
     * Enregistre l'ajustement réussi après sa validation transactionnelle.
     *
     * @param 'SET'|'DELTA' $mode
     * @param array{id?: int|null, role?: string, nom?: string|null} $actor
     */
    public function logStockMovement(
        int $idProduit,
        int $stockAvant,
        int $stockApres,
        string $mode,
        int $quantite,
        string $motif,
        array $actor,
    ): void;
}
