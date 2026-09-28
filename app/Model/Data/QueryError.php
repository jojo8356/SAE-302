<?php

declare(strict_types=1);

/**
 * MiniShop — erreur de la couche de gestion des données (app/Model/Data).
 *
 * Taxonomie complète documentée dans Errors.php. Une classe par fichier
 * (autoloading PSR-4 de app/bootstrap.php).
 */

namespace App\Model\Data;

/** Appel incorrect de l'API du store (arguments, spécification de filtre…). */
final class QueryError extends \RuntimeException
{
}
