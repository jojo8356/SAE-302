<?php

declare(strict_types=1);

namespace App\Model\Data;

/** Capacité optionnelle de lecture pessimiste, disponible sur les pilotes SQL. */
interface RowLockingStore
{
    /** Lit une ligne en la verrouillant jusqu'à la fin de la transaction courante. */
    public function findForUpdate(string $table, int|string $pk): ?array;
}
