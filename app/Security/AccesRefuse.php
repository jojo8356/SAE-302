<?php

declare(strict_types=1);

namespace App\Security;

/** Accès authentifié interdit : traité en réponse HTTP 403 par le contrôleur frontal. */
final class AccesRefuse extends \RuntimeException
{
}
