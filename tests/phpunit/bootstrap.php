<?php

declare(strict_types=1);

/**
 * MiniShop — amorçage de la suite PHPUnit.
 *
 * Charge l'autoloader applicatif (aucune dépendance runtime), coupe les
 * cookies de session (les tests n'en ont pas besoin) et prépare un dossier
 * de magasin temporaire pour les tests d'intégration du moteur.
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!defined('MINISHOP_TESTS_TMP')) {
    define('MINISHOP_TESTS_TMP', sys_get_temp_dir() . '/minishop_phpunit_' . getmypid());
}

if (!is_dir(MINISHOP_TESTS_TMP)) {
    mkdir(MINISHOP_TESTS_TMP, 0o775, true);
}

ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
