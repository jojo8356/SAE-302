<?php

/**
 * MiniShop — exécute TOUTES les suites de tests, en PHP NATIF.
 *
 *   php scripts/run-all-tests.php        (machine avec PHP 8.2+)
 *   node scripts/run-all-tests.mjs       (sandbox sans PHP : délègue ici si PHP existe)
 *
 * C'est la version sans dépendance : aucun paquet npm, aucun runtime WASM.
 * Chaque suite est lancée dans son propre processus : un échec n'empêche pas
 * les suivantes de s'exécuter, et le code retour final est 1 si une seule a
 * échoué.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
chdir($racine);

const SUITES = [
    ['Linter strict types', 'scripts/strict_types_lint.php'],
    ['Moteur JSON', 'tests/php/engine_smoke.php'],
    ['Règles de gestion', 'tests/php/run_tests.php'],
    ['Unitaires', 'tests/php/unit_tests.php'],
    ['Fonctionnalités', 'tests/php/features_test.php'],
    ['Contrats de design', 'tests/php/css_contract.php'],
    ['Contrat responsive', 'tests/php/responsive_contract.php'],
    ['Unitaires PHPUnit', 'tests/phpunit/run-local.php'],
];

/** Séparateur de section. */
function section(string $texte): void
{
    $ligne = str_repeat('=', 64);
    echo "\n" . $ligne . "\n" . $texte . "\n" . $ligne . "\n";
}

/** Lance un script PHP enfant et retourne son code de sortie. */
function lancer(string $fichier): int
{
    $commande = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fichier) . ' 2>&1';
    $sortie = [];
    $code = 0;
    exec($commande, $sortie, $code);
    echo implode("\n", $sortie) . "\n";

    return $code;
}

// Préflight : une extension absente produit des échecs trompeurs (recherche
// insensible aux accents, sessions, MySQL). On le dit AVANT les suites.
$extensionsRequises = [
    'mbstring' => 'pliage casse/accents (Text::fold, T-25, slugs)',
    'json' => 'moteur de données JSON',
];
$extensionsAbsentes = [];
foreach ($extensionsRequises as $extension => $role) {
    if (!extension_loaded($extension)) {
        $extensionsAbsentes[$extension] = $role;
    }
}

if ($extensionsAbsentes !== []) {
    echo "\n⚠ extensions PHP manquantes — les échecs ci-dessous en découleront :\n";
    foreach ($extensionsAbsentes as $extension => $role) {
        echo '   - ' . $extension . ' : ' . $role . "\n";
    }
    echo "   paquets Debian/Ubuntu : sudo apt install php" . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
        . "-mbstring php" . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . "-mysql\n\n";
}

$echecs = 0;
$jouees = 0;

foreach (SUITES as [$nom, $fichier]) {
    section('▸ ' . $nom . ' — ' . $fichier);
    if (!is_file($fichier)) {
        echo "  ❌ fichier absent\n";
        ++$echecs;
        continue;
    }
    ++$jouees;
    if (lancer($fichier) !== 0) {
        ++$echecs;
    }
}

section(str_repeat('=', 0));

if ($echecs > 0) {
    echo 'SUITES : ' . $echecs . ' étape(s) en échec ❌ — E2E non joué' . "\n";
    exit(1);
}

echo 'SUITES PHP : TOUT EST VERT ✅ (' . $jouees . " suites)\n";
echo "\nE2E : nécessite un serveur HTTP ; lancez « node scripts/e2e.mjs » ou « ./run.sh --e2e ».\n";
exit(0);
