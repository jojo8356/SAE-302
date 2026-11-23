<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Data\StoredProcedureExecutor;
use App\Model\Data\StoreInterface;

/**
 * Fabrique la stratégie d'accès au compte selon les capacités du pilote.
 *
 * Le test porte sur une interface de capacité plutôt que sur SqlStore : un
 * autre pilote SQL peut réutiliser la stratégie sans modifier le repository.
 */
final class ClientAccountGatewayFactory
{
    /** Sélectionne la stratégie SQL (procédures stockées) ou générique selon le pilote. */
    public static function forStore(StoreInterface $store): ClientAccountGateway
    {
        if ($store instanceof StoredProcedureExecutor) {
            return new SqlClientAccountGateway($store);
        }

        return new StoreClientAccountGateway($store);
    }
}
