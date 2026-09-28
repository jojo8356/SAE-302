<?php

declare(strict_types=1);

/**
 * MiniShop — amorce applicative : autoloader PSR-4 minimal (aucune dépendance
 * Composer nécessaire) + constantes de racine.
 *
 * Espace unique : App\ → app/  (ex. App\Model\Data\JsonStore → app/Model/Data/JsonStore.php)
 * Chargé par public/index.php, scripts/*.php et tests/php/*.
 */

if (!defined('MINISHOP_ROOT')) {
    define('MINISHOP_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

// Helpers d'affichage globaux (e(), euros(), date_fr()…) pour toutes les vues
require_once __DIR__ . '/Security/xss.php';

// Sécurité de base : timezone explicite (évite les avertissements date()) et
// rapport d'erreurs piloté par la config en environnement Web.
date_default_timezone_set('UTC');
