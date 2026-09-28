<?php

declare(strict_types=1);

/**
 * MiniShop — config php-cs-fixer (outil de développement, non requis pour
 * faire tourner l'application).
 *
 * Périmètre volontairement MINIMAL : ce projet n'est pas formaté par un outil
 * (style maison) — on n'embarque que les règles de SÛRETÉ, pas de cosmétique.
 *
 *   composer cs:check   (dry-run + diff)   /   composer cs:fix
 *
 * En sandbox (sans Packagist joignable), la règle declare_strict_types est
 * vérifiée de façon équivalente par scripts/strict_types_lint.php, exécutable
 * sous PHP-WASM : npm run lint:types.
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/app', __DIR__ . '/public', __DIR__ . '/scripts', __DIR__ . '/tests/php'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true) // declare_strict_types est classée « risky »
    ->setRules([
        // Force un mode strict dans tous les fichiers (règle du même nom que
        // l'outil) : ajoute declare(strict_types=1) là où il manque.
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
