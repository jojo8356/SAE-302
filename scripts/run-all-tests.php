<?php

/**
 * MiniShop — exécute TOUTES les suites de tests, en PHP NATIF.
 *
 *   php scripts/run-all-tests.php        (machine avec PHP 8.2+)
 *   node scripts/run-all-tests.mjs       (sandbox sans PHP : délègue ici si PHP existe)
 *
 * Aucun paquet npm ni runtime WASM requis pour les suites PHP ; Python 3 est
 * requis pour l'analyse des dépréciations et ses autotests.
 * Chaque suite est lancée dans son propre processus : un échec n'empêche pas
 * les suivantes de s'exécuter, et le code retour final est 1 si une seule a
 * échoué.
 *
 * Le parcours E2E (`scripts/e2e.mjs`) est une suite Node : il n'est joué que
 * si Node et sa dépendance `@php-wasm/node` sont présents — sinon l'étape est
 * annoncée comme NON JOUÉE (jamais comme réussie), avec la commande exacte.
 * Un « tout est vert » qui aurait ignoré l'E2E en silence serait un mensonge.
 */

declare(strict_types=1);

$options = array_slice($argv, 1);
foreach ($options as $option) {
    if (!in_array($option, ['--sans-sql', '--sans-e2e'], true)) {
        fwrite(STDERR, "Option inconnue : {$option}\nUsage : php scripts/run-all-tests.php [--sans-sql] [--sans-e2e]\n");
        exit(2);
    }
}
$sansSql = in_array('--sans-sql', $options, true);
$sansE2e = in_array('--sans-e2e', $options, true);

$racine = dirname(__DIR__);
chdir($racine);

// Le drapeau bloque la provision applicative (DbProvision::contexteDeTest()).
// Attention : la suite SQL explicite peut créer et supprimer minishop_test.
putenv('MINISHOP_TESTS=1');

const SUITES = [
    ['Linter strict types', 'scripts/strict_types_lint.php'],
    ['Autotests code potentiellement inutilisé', 'tests/php/code_mort_test.php'],
    ['Nouveaux candidats code potentiellement inutilisé', 'scripts/code_mort.php'],
    ['Moteur JSON', 'tests/php/engine_smoke.php'],
    ['Règles de gestion', 'tests/php/run_tests.php'],
    ['Unitaires', 'tests/php/unit_tests.php'],
    ['Fonctionnalités', 'tests/php/features_test.php'],
    ['Catalogue : ruptures en dernier', 'tests/php/catalogue_stock_test.php'],
    ['Accueil : derniers ajouts', 'tests/php/nouveautes_test.php'],
    ['Accueil : dates des ajouts', 'tests/php/date_ajout_test.php'],
    ['Cartes produit : accessibilité', 'tests/php/cartes_accessibilite_test.php'],
    ['Contrats de design', 'tests/php/css_contract.php'],
    ['Contrat responsive', 'tests/php/responsive_contract.php'],
    ['Contrats HTTP', 'tests/php/contract_http.php'],
    ['Information juridique et données', 'tests/php/information_juridique_test.php'],
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
    $commande = escapeshellarg(PHP_BINARY)
        . ' -d error_reporting=-1 -d display_errors=1 -d log_errors=0'
        . ' -d ' . escapeshellarg('auto_prepend_file=' . dirname(__DIR__) . '/tests/php/deprecations_bootstrap.php')
        . ' ' . escapeshellarg($fichier) . ' 2>&1';
    $sortie = [];
    $code = 0;
    exec($commande, $sortie, $code);
    $texte = implode("\n", $sortie);
    echo $texte . "\n";
    if (str_contains($texte, '❌ DÉPRÉCIATION PHP :') || preg_match('/^\s*(?:PHP )?(?:User )?Deprecated\s*:/mi', $texte)) {
        return 1;
    }

    return $code;
}

/** Lance un script avec un interpréteur donné (`php`, `node`…). */
function lancerAvec(string $interpreteur, string $fichier): int
{
    $commande = escapeshellarg($interpreteur) . ' ' . escapeshellarg($fichier) . ' 2>&1';
    $sortie = [];
    $code = 0;
    exec($commande, $sortie, $code);
    echo implode("\n", $sortie) . "\n";

    return $code;
}

/** Chemin de l'exécutable Node.js, ou `null` s'il est absent. */
function noeud(): ?string
{
    $sortie = [];
    $code = 0;
    exec('node --version 2>&1', $sortie, $code);
    if ($code !== 0) {
        return null;
    }
    $version = trim(implode(' ', $sortie));
    if (!str_starts_with($version, 'v')) {
        return null;
    }

    return 'node';
}

// Préflight : une extension absente produit des échecs trompeurs (recherche
// insensible aux accents, sessions, MySQL). On le dit AVANT les suites.
$extensionsRequises = [
    'mbstring' => 'pliage casse/accents (Text::fold, T-25, slugs)',
    'json' => 'moteur de données JSON',
];
// Facultatives : sans elles l'application fonctionne (repli documenté), mais
// la qualité du pliage et des tris diffère de la collation MySQL.
$extensionsOptionnelles = [
    'intl' => 'translittération ICU et collation française (repli : table latine)',
    'pdo_mysql' => 'pilote SQL (repli : pilote JSON)',
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

foreach ($extensionsOptionnelles as $extension => $role) {
    if (!extension_loaded($extension)) {
        echo '   ⓘ extension facultative absente : ' . $extension . ' (' . $role . ")\n";
    }
}

$echecs = 0;
$jouees = 0;

section('▸ Autotests du contrôle des dépréciations');
++$jouees;
if (lancerAvec('python3', 'tests/python/test_deprecations.py') !== 0) {
    ++$echecs;
}

section('▸ Autotests d’intégration code mort et inventaire');
foreach (['tests/python/test_code_mort_integration.py', 'tests/python/test_inventaire_app.py', 'tests/python/test_securite_gitignore.py', 'tests/python/test_audit_structurel.py'] as $testPython) {
    ++$jouees;
    if (lancerAvec('python3', $testPython) !== 0) {
        ++$echecs;
    }
}

section('▸ Analyse des dépréciations PHP — scripts/verifier_deprecations.py');
// Utiliser exactement la version PHP qui exécute ce runner.
$commandeAnalyse = 'python3 ' . escapeshellarg('scripts/verifier_deprecations.py')
    . ' --php ' . escapeshellarg(PHP_BINARY) . ' 2>&1';
$sortieAnalyse = [];
$codeAnalyse = 0;
exec($commandeAnalyse, $sortieAnalyse, $codeAnalyse);
echo implode("\n", $sortieAnalyse) . "\n";
++$jouees;
if ($codeAnalyse !== 0) {
    ++$echecs;
}

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

// ------------------------------------------------- intégration SQL (facultative)
// `sql_integration.php` sort en code 2 quand il n'y a pas de serveur : ce n'est
// PAS un échec, c'est une suite non jouable ici. La distinguer évite deux
// comportements également faux : un rouge qui accuse le code, ou un vert qui
// prétend avoir testé le SQL.
section('▸ Intégration SQL — tests/php/sql_integration.php');
$sqlEtat = 'NON JOUÉ ⏭ (serveur ou client MySQL absent)';
if ($sansSql) {
    $sqlEtat = 'NON JOUÉ ⏭ (--sans-sql)';
    echo "  ⏭ NON JOUÉ : option --sans-sql.\n";
} elseif (!is_file('tests/php/sql_integration.php')) {
    echo "  ❌ fichier absent\n";
    ++$echecs;
    $sqlEtat = 'fichier absent ❌';
} else {
    ++$jouees;
    $codeSql = lancer('tests/php/sql_integration.php');
    if ($codeSql === 0) {
        $sqlEtat = 'joué ✅';
    } elseif ($codeSql === 2) {
        --$jouees;
    } else {
        ++$echecs;
        $sqlEtat = 'ÉCHEC ❌';
    }
}

// --------------------------------------------------------------- parcours E2E
section('▸ Parcours E2E — scripts/e2e.mjs');
$e2eJoue = false;
$noeud = noeud();
$dependance = $racine . '/node_modules/@php-wasm/node';

if ($sansE2e) {
    echo "  ⏭ NON JOUÉ : option --sans-e2e.\n";
} elseif ($noeud === null) {
    echo "  ⏭  NON JOUÉ : Node.js est absent de cette machine.\n";
    echo "     Voir README : section tests E2E (données isolées indispensables).\n";
} elseif (!is_dir($dependance)) {
    echo "  ⏭  NON JOUÉ : la dépendance @php-wasm/node n'est pas installée.\n";
    echo "     Voir README : section tests E2E (données isolées indispensables).\n";
} elseif (!is_file('scripts/e2e.mjs')) {
    echo "  ❌ fichier absent\n";
    ++$echecs;
} else {
    $e2eJoue = true;
    ++$jouees;
    if (lancerAvec((string) $noeud, 'scripts/e2e.mjs') !== 0) {
        ++$echecs;
    }
}

section(str_repeat('=', 0));

$e2eEtat = 'joué ✅';
if (!$e2eJoue) {
    $e2eEtat = 'NON JOUÉ ⏭ (voir ci-dessus)';
}

if ($echecs > 0) {
    echo 'SUITES : ' . $echecs . ' étape(s) en échec ❌ — E2E : ' . $e2eEtat . "\n";
    exit(1);
}

echo 'SUITES : TOUT EST VERT ✅ (' . $jouees . " étapes) — SQL : " . $sqlEtat . ' — E2E : ' . $e2eEtat . "\n";
if (!$e2eJoue) {
    echo "nb : SQL et E2E non joués ne sont pas validés.\n";
}
exit(0);
