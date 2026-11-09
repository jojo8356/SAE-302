<?php

declare(strict_types=1);

/**
 * MiniShop — couverture de chargement du code applicatif.
 *
 *   php scripts/coverage.php               # rapport
 *   php scripts/coverage.php --min=80      # échoue sous 80 % (CI)
 *   php scripts/coverage.php --liste       # liste les fichiers jamais chargés
 *
 * **Ce que ce script mesure, et ce qu'il ne mesure pas.** Il n'y a pas de
 * couverture de LIGNES ici : elle exigerait Xdebug ou PCOV, c'est-à-dire une
 * dépendance que le projet s'interdit (ENF-20 : rien à installer pour noter).
 * Ce qui est mesuré est honnête et néanmoins utile : **quels fichiers de
 * `app/` sont chargés par au moins une suite de tests**. Un fichier jamais
 * chargé n'est pas « mal couvert » : il n'est jamais exécuté, et aucun test ne
 * peut donc détecter sa régression. C'est le bon signal pour repérer du code
 * mort ou une fonctionnalité non testée.
 *
 * Méthode : chaque suite PHP est lancée dans un processus enfant avec
 * `auto_prepend_file` / `auto_append_file`, qui capturent
 * `get_included_files()` à la fin de l'exécution. Aucun fichier du projet
 * n'est modifié.
 *
 * Limites assumées, écrites ici pour ne pas les découvrir en soutenance :
 *   - un fichier « chargé » n'est pas un fichier « correctement exercé » ;
 *   - les fichiers de vue (`app/View`) ne sont chargés qu'à l'affichage : la
 *     couverture de cette zone est structurellement faible hors E2E, qui est
 *     une suite Node et n'entre donc pas dans ce calcul ;
 *   - `public/index.php` n'est pas mesuré (front controller, testé par l'E2E).
 */

$racine = dirname(__DIR__);
chdir($racine);

const SUITES = [
    'moteur' => 'tests/php/engine_smoke.php',
    'règles' => 'tests/php/run_tests.php',
    'unitaires' => 'tests/php/unit_tests.php',
    'fonctionnalités' => 'tests/php/features_test.php',
    'contrats HTTP' => 'tests/php/contract_http.php',
    'design' => 'tests/php/css_contract.php',
    'responsive' => 'tests/php/responsive_contract.php',
    'phpunit' => 'tests/phpunit/run-local.php',
];

/** Interpréteur : PHP courant, ou MINISHOP_PHP (utile en test/sandbox). */
function interpreteur(): string
{
    $force = getenv('MINISHOP_PHP');
    if (is_string($force) && $force !== '') {
        return $force;
    }

    return PHP_BINARY;
}

/** `exec` disponible ET capable de créer un processus ? */
function peutLancer(): bool
{
    if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        return false;
    }
    $sortie = [];
    $code = 1;
    @exec(escapeshellarg(interpreteur()) . ' -r ' . escapeshellarg('echo "ok";') . ' 2>&1', $sortie, $code);

    return $code === 0 && implode('', $sortie) === 'ok';
}

/** Liste récursive des fichiers PHP d'un dossier. @return list<string> */
function fichiersPhp(string $dossier): array
{
    if (!is_dir($dossier)) {
        return [];
    }
    $fichiers = [];
    $iterateur = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterateur as $fichier) {
        if ($fichier->isFile() && $fichier->getExtension() === 'php') {
            $fichiers[] = str_replace('\\', '/', $fichier->getPathname());
        }
    }
    sort($fichiers);

    return $fichiers;
}

/**
 * Lance une suite et retourne les fichiers qu'elle a chargés.
 *
 * @return array{charge:list<string>, code:int, sortie:string}|null
 */
function mesurer(string $suite, string $racine): ?array
{
    $journal = tempnam(sys_get_temp_dir(), 'minishop-cov-');
    if ($journal === false) {
        return null;
    }
    // Le script témoin note get_included_files() puis s'efface.
    $temoin = sys_get_temp_dir() . '/minishop-cov-temoin-' . bin2hex(random_bytes(4)) . '.php';
    $code = '<?php $f = getenv("MINISHOP_COV_OUT");'
        . ' if (is_string($f) && $f !== "") { file_put_contents($f, json_encode(get_included_files())); }';
    file_put_contents($temoin, $code);

    $commande = escapeshellarg(interpreteur())
        . ' -d auto_append_file=' . escapeshellarg($temoin)
        . ' ' . escapeshellarg($suite) . ' 2>&1';
    $sortie = [];
    $codeSortie = 0;
    @exec('cd ' . escapeshellarg($racine) . ' && MINISHOP_COV_OUT=' . escapeshellarg($journal) . ' ' . $commande, $sortie, $codeSortie);

    $brut = (string) file_get_contents($journal);
    @unlink($journal);
    @unlink($temoin);

    $liste = json_decode($brut, true);
    if (!is_array($liste)) {
        return null;
    }

    $charge = [];
    foreach ($liste as $chemin) {
        if (!is_string($chemin)) {
            continue;
        }
        $normalise = str_replace('\\', '/', $chemin);
        if (str_starts_with($normalise, $racine . '/')) {
            $charge[] = substr($normalise, strlen($racine) + 1);
        }
    }

    return ['charge' => $charge, 'code' => $codeSortie, 'sortie' => implode("\n", array_slice($sortie, -5))];
}

// ------------------------------------------------------------------ options
$minimum = 0;
$lister = false;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
    if (preg_match('/^--min=(\d+)$/', (string) $argument, $trouve) === 1) {
        $minimum = (int) $trouve[1];
        continue;
    }
    if ($argument === '--liste') {
        $lister = true;
        continue;
    }
    if ($argument === '--help' || $argument === '-h') {
        echo "usage : php scripts/coverage.php [--min=N] [--liste]\n";
        exit(0);
    }
    fwrite(STDERR, '✘ option inconnue : ' . $argument . "\n");
    exit(2);
}

echo "MiniShop — couverture de chargement (app/)\n";
echo str_repeat('-', 60) . "\n";

if (!peutLancer()) {
    echo "⏭  NON JOUÉ : ce script lance chaque suite dans un processus enfant,\n";
    echo "    ce que cet environnement ne permet pas (PHP-WASM : exec() indisponible).\n";
    echo "    Sur une machine avec PHP natif : php scripts/coverage.php\n";
    exit(2);
}

$tous = fichiersPhp($racine . '/app');
$charges = [];

printf("%-18s %6s  %s\n", 'SUITE', 'CHARGÉS', 'ÉTAT');
foreach (SUITES as $nom => $suite) {
    if (!is_file($racine . '/' . $suite)) {
        printf("%-18s %6s  %s\n", $nom, '—', 'fichier absent');
        continue;
    }
    $resultat = mesurer($suite, $racine);
    if ($resultat === null) {
        printf("%-18s %6s  %s\n", $nom, '—', 'non mesurable');
        continue;
    }
    $applicatifs = array_filter($resultat['charge'], static fn (string $f): bool => str_starts_with($f, 'app/'));
    foreach ($applicatifs as $fichier) {
        $charges[$fichier] = true;
    }
    $etat = '✅ suite verte';
    if ($resultat['code'] !== 0) {
        $etat = '❌ suite en échec (code ' . $resultat['code'] . ')';
    }
    printf("%-18s %6d  %s\n", $nom, count($applicatifs), $etat);
}

$charges = array_keys($charges);
sort($charges);
$jamais = array_values(array_diff($tous, $charges));
$pourcentage = 0;
if ($tous !== []) {
    $pourcentage = (int) round(100 * count($charges) / count($tous));
}

echo str_repeat('-', 60) . "\n";
printf("fichiers applicatifs      : %d\n", count($tous));
printf("chargés par au moins 1 suite : %d (%d %%)\n", count($charges), $pourcentage);
printf("jamais chargés            : %d\n", count($jamais));

if ($lister && $jamais !== []) {
    echo "\nJamais chargés (code mort possible, ou fonctionnalité non testée) :\n";
    foreach ($jamais as $fichier) {
        echo '  - ' . $fichier . "\n";
    }
}

echo "\n";
if ($minimum > 0 && $pourcentage < $minimum) {
    echo 'COUVERTURE INSUFFISANTE ❌ — ' . $pourcentage . ' % < ' . $minimum . " %\n";
    exit(1);
}
echo 'COUVERTURE : mesure terminée ✅ (' . $pourcentage . " %)\n";
exit(0);
