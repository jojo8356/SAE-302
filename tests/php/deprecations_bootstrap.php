<?php

declare(strict_types=1);

/** Chargé uniquement par les runners de tests, jamais par l'application Web. */
error_reporting(E_ALL);

set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
    if ($niveau !== E_DEPRECATED && $niveau !== E_USER_DEPRECATED) {
        return false;
    }

    // Même masqué par @ : ce sont des tests, on veut relever la dépréciation.
    // Ne pas lancer d'exception ici : les tests peuvent attraper Throwable et
    // masquer l'incident. Le runner repère ce marqueur et échoue en fin de test.
    file_put_contents('php://stderr', "❌ DÉPRÉCIATION PHP : {$message} ({$fichier}:{$ligne})\n");

    return true;
}, E_DEPRECATED | E_USER_DEPRECATED);
