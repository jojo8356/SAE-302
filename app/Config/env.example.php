<?php

declare(strict_types=1);

/**
 * MiniShop — ancien modèle de configuration PHP.
 *
 * ⚠️  Déprécié : la configuration vit désormais dans `.env` à la racine
 *     (modèle versionné `.env.example`, lu par App\Config\Env). Les deux
 *     formats restent acceptés, `.env` étant prioritaire ; ce fichier n'est
 *     conservé que pour les dépôts déjà configurés de cette façon.
 *
 * Ordre de lecture (le dernier gagne) : valeurs par défaut du code →
 * ce fichier (copié vers env.php) → `.env` → variables d'environnement.
 *
 * Hors dépôt : app/Config/env.php est ignoré par .gitignore (règle §14.1).
 * Sans configuration explicite, l'application sélectionne le pilote SQL.
 * Pour le mode secondaire sans serveur, choisir explicitement 'json'.
 */

return [
    // Environnement : 'dev' | 'test' | 'prod'
    'env'     => 'dev',
    'APP_ENV' => 'dev',
    'debug'   => true,

    // ---- Pilote de gestion des données (énumération App\Model\Data\StorageDriver)
    // 'sql'  : MySQL/PDO — pilote applicatif par défaut ; base installée par
    //          scripts/db_init.php (schéma, données, procédures et triggers).
    // 'json' : moteur secondaire sans serveur (data/minishop/*.json), à choisir explicitement.
    'storage_driver' => 'sql',

    // Dossier des fichiers de données (défaut : <projet>/data/minishop).
    // Utile pour les tests (bac à sable) ou la volumétrie (jeu 200 produits).
    'data_path' => null,

    // ---- Connexion MySQL/MariaDB du pilote principal SQL ----
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'minishop',
    'db_user' => 'root',
    'db_pass' => '',
];
