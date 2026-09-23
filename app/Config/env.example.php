<?php
declare(strict_types=1);

/**
 * MiniShop — modèle de configuration (jamais commité avec de vrais secrets).
 * Copier vers app/Config/env.php puis renseigner les valeurs locales.
 *
 * Hors dépôt : app/Config/env.php est ignoré par .gitignore (règle §14.1).
 * En dev, laisser 'env' => 'dev' pour autoriser public/gen_volumes.php.
 * En prod, passer à 'env' => 'prod' : le générateur de volumétrie se bloque (404).
 */
return [
    // Environnement : 'dev' | 'test' | 'prod'
    'env'       => 'dev',
    'APP_ENV'   => 'dev',

    // Base — par défaut minishop (jeu de démo) et minishop_perf (volumétrie)
    'db_host'   => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port'   => (int) (getenv('DB_PORT') ?: 3306),
    'db_name'   => getenv('DB') ?: getenv('DB_NAME') ?: 'minishop',
    'db_user'   => getenv('DB_USER') ?: 'root',
    'db_pass'   => getenv('DB_PASS') ?: '',
    // Alias compatibles scripts shell
    'DB'        => getenv('DB') ?: 'minishop',

    // Optionnel : jeton pour protéger le générateur même en dev
    // 'volumetry_token' => 'change-me',

    // Divers
    'debug'     => true,
];
