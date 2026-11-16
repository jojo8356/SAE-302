<?php

declare(strict_types=1);

namespace App\Model\Data;

/** Capacité optionnelle à appeler une fonction stockée SQL. */
interface StoredFunctionExecutor
{
    /** @param list<mixed> $inputs */
    public function callFunction(string $function, array $inputs = []): mixed;
}
