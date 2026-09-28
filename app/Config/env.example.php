<?php

declare(strict_types=1);

/**
 * MiniShop — modèle de configuration (jamais commité avec de vrais secrets).
 * Copier vers app/Config/env.php puis renseigner les valeurs locales.
 *
 * Hors dépôt : app/Config/env.php est ignoré par .gitignore (règle §14.1).
 * Sans ce fichier, l'application fonctionne avec les valeurs par défaut
 * ci-dessous (pilote JSON, données dans data/minishop).
 */

return [
    // Environnement : 'dev' | 'test' | 'prod'
    'env'     => 'dev',
    'APP_ENV' => 'dev',
    'debug'   => true,

    // ---- Pilote de gestion des données (énumération App\Model\Data\StorageDriver)
    // 'json' : moteur JSON natif (data/minishop/*.json) — pilote actif.
    // 'sql'  : MySQL/PDO — réservé à la migration finale (lots L14+), les
    //          scripts sql/01→04 sont déjà prêts dans le dépôt.
    'storage_driver' => 'json',

    // Dossier des fichiers de données (défaut : <projet>/data/minishop).
    // Utile pour les tests (bac à sable) ou la volumétrie (jeu 200 produits).
    'data_path' => null,

    // ---- Réservé à la migration SQL finale (aucun usage en pilote JSON) ----
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'minishop',
    'db_user' => 'root',
    'db_pass' => '',
];
