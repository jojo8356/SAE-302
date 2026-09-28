<?php

declare(strict_types=1);

/**
 * MiniShop — erreur de la couche de gestion des données (app/Model/Data).
 *
 * Taxonomie complète documentée dans Errors.php. Une classe par fichier
 * (autoloading PSR-4 de app/bootstrap.php).
 */

namespace App\Model\Data;

/** Violation de contrainte d'intégrité (UNIQUE / CHECK / ENUM / FK) — ≈ SQLSTATE 23000. */
final class ConstraintError extends \RuntimeException
{
}
