<?php

declare(strict_types=1);

/**
 * MiniShop — tests du routeur de développement `scripts/dev-router.php`.
 *
 * Suite NOIRE, de bout en bout : elle lance le vrai serveur intégré PHP
 * (`php -S … scripts/dev-router.php`) en sous-processus et lui parle en
 * HTTP brut (fsockopen, aucune extension requise). Elle couvre les deux
 * échecs Windows/XAMPP signalés à l'origine :
 *
 *   R-01  le routeur existait pas → « Failed opening required » : le
 *         fichier est présent et le serveur DÉMARRE (et reste démarré) ;
 *   R-02  lancé hors racine projet, les chemins relatifs désignaient le
 *         vide : le serveur est ici lancé depuis un dossier temporaire,
 *         avec `-t` et routeur en chemins ABSOLUS (variante README
 *         « depuis n'importe quel dossier ») — tout doit répondre ;
 *   R-03  la même chose en chemins RELATIFS depuis la racine du projet
 *         (la commande README de référence, Linux comme Windows).
 *
 * Et le contrat du routeur (équivalent `public/.htaccess`) :
 *   - `/` et les routes du frontal (catalogue, recherche…) → 200 ;
 *   - un fichier réel de `public/` (statique ou PHP) → servi/exécuté
 *     directement, jamais réécrit vers le frontal ;
 *   - une URL inconnue, même avec extension (`.css`) → 404 du frontal ;
 *   - aucune traversée (`../`, `%2e%2e`, `%5c`, `/.git/…`,
 *     `/../data/minishop/client.json`) ne sert de contenu hors `public/`.
 *
 * Exécution directe du routeur (`php scripts/dev-router.php`) : ce n'est
 * pas un serveur — aide affichée, code de sortie 1, pas d'arrêt silencieux.
 *
 * Usage (PHP natif requis — la sandbox PHP-WASM ne sait pas lancer
 * `php -S`, la suite s'y déclare SKIP, code 0) :
 *   php tests/php/dev_router_test.php        (exit 0 = tout vert)
 * En CI (GitHub Actions, job `dev-router`, Linux ET Windows),
 * MINISHOP_ROUTER_TEST_STRICT=1 rend le SKIP bloquant : un environnement
 * qui ne sait pas exécuter la suite y est un échec, pas un passe-droit.
 */

$nbTests = 0;
$nbEchecs = 0;

/** Enregistre un verdict. */
function verdict(bool $ok, string $etiquette): void
{
    global $nbTests, $nbEchecs;
    ++$nbTests;
    if ($ok) {
        echo "  ✓ {$etiquette}\n";
    } else {
        ++$nbEchecs;
        echo "  ✗ ÉCHEC — {$etiquette}\n";
        // En CI GitHub, l'échec est aussi publié en annotation du job :
        // visible dans l'onglet Checks d'une PR sans ouvrir les logs.
        if (getenv('GITHUB_ACTIONS') === 'true') {
            $message = str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $etiquette);
            echo "::error title=dev-router::{$message}\n";
        }
    }
}

/** Déclare la suite inapplicable ici (SKIP) et termine proprement. */
function sauter(string $raison): never
{
    echo "SKIP — {$raison}\n";
    if (getenv('MINISHOP_ROUTER_TEST_STRICT') === '1') {
        echo "MINISHOP_ROUTER_TEST_STRICT=1 : SKIP interdit en CI.\n";
        exit(1);
    }
    exit(0);
}

/** Port TCP libre (demandé à l'OS puis relâché pour le serveur de test). */
function portLibre(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    if ($socket === false) {
        return 18000 + random_int(0, 2000);
    }
    $nom = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($nom)) {
        return 18000 + random_int(0, 2000);
    }
    $port = (int) substr($nom, strrpos($nom, ':') + 1);
    if ($port > 0) {
        return $port;
    }

    return 18000 + random_int(0, 2000);
}

/**
 * Requête HTTP/1.1 brute vers le serveur de test.
 *
 * @return array{statut: int, corps: string} statut 0 = serveur injoignable
 */
function requeteHttp(int $port, string $chemin): array
{
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2.0);
    if ($socket === false) {
        return ['statut' => 0, 'corps' => ''];
    }
    fwrite($socket, "GET {$chemin} HTTP/1.1\r\nHost: 127.0.0.1:{$port}\r\nConnection: close\r\n\r\n");
    stream_set_timeout($socket, 5);
    $brut = '';
    while (!feof($socket)) {
        $morceau = fread($socket, 8192);
        if ($morceau === false) {
            break;
        }
        if ($morceau === '') {
            $infos = stream_get_meta_data($socket);
            if ($infos['timed_out']) {
                break;
            }
            continue;
        }
        $brut .= $morceau;
    }
    fclose($socket);

    $statut = 0;
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $brut, $m) === 1) {
        $statut = (int) $m[1];
    }
    $corps = $brut;
    $position = strpos($brut, "\r\n\r\n");
    if ($position !== false) {
        $corps = substr($brut, $position + 4);
    }
    // Corps « chunked » éventuel : le frontal ne l'utilise pas, mais les
    // fichiers servis directement pourraient — on décode prudemment.
    if (str_contains(strtolower($brut), 'transfer-encoding: chunked')) {
        $decode = '';
        $reste = $corps;
        while ($reste !== '') {
            $finLigne = strpos($reste, "\r\n");
            if ($finLigne === false) {
                break;
            }
            $taille = (int) hexdec(trim(substr($reste, 0, $finLigne)));
            if ($taille <= 0) {
                break;
            }
            $decode .= substr($reste, $finLigne + 2, $taille);
            $reste = substr($reste, $finLigne + 2 + $taille + 2);
        }
        if ($decode !== '') {
            $corps = $decode;
        }
    }

    return ['statut' => $statut, 'corps' => $corps];
}

/**
 * Lance `php -S` en sous-processus et attend sa première réponse.
 * Retourne null (avec diagnostic sur STDERR) si le serveur meurt au
 * démarrage — LA régression « démarre puis s'arrête aussitôt ».
 *
 * @param list<string> $commande
 * @return array{process: mixed, tuyaux: array<int, mixed>}|null
 */
function demarrerServeur(array $commande, string $cwd, int $port): ?array
{
    $descripteurs = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $tuyaux = [];
    $process = proc_open($commande, $descripteurs, $tuyaux, $cwd);
    if (!is_resource($process)) {
        fwrite(STDERR, "proc_open a échoué pour : " . implode(' ', $commande) . "\n");

        return null;
    }
    foreach ($tuyaux as $tuyau) {
        stream_set_blocking($tuyau, false);
    }

    $echeance = microtime(true) + 15.0;
    while (microtime(true) < $echeance) {
        $statut = proc_get_status($process);
        if (!$statut['running']) {
            $sortie = '';
            if (isset($tuyaux[1])) {
                $lu = stream_get_contents($tuyaux[1]);
                if (is_string($lu)) {
                    $sortie .= $lu;
                }
            }
            if (isset($tuyaux[2])) {
                $lu = stream_get_contents($tuyaux[2]);
                if (is_string($lu)) {
                    $sortie .= $lu;
                }
            }
            fwrite(STDERR, "Le serveur s'est arrêté au démarrage. Sortie :\n{$sortie}\n");

            return null;
        }
        $reponse = requeteHttp($port, '/');
        if ($reponse['statut'] !== 0) {
            return ['process' => $process, 'tuyaux' => $tuyaux];
        }
        usleep(100000);
    }
    fwrite(STDERR, "Le serveur n'a pas répondu dans les 15 s (port {$port}).\n");

    return null;
}

/** Arrête un serveur lancé par demarrerServeur(). @param array{process: mixed, tuyaux: array<int, mixed>} $serveur */
function arreterServeur(array $serveur): void
{
    foreach ($serveur['tuyaux'] as $tuyau) {
        if (is_resource($tuyau)) {
            fclose($tuyau);
        }
    }
    if (is_resource($serveur['process'])) {
        proc_terminate($serveur['process']);
        proc_close($serveur['process']);
    }
}

/**
 * Exécute une commande courte et capture sortie + code de retour.
 *
 * @param list<string> $commande
 * @return array{statut: int, sortie: string}
 */
function executerCommande(array $commande, string $cwd): array
{
    $descripteurs = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $tuyaux = [];
    $process = proc_open($commande, $descripteurs, $tuyaux, $cwd);
    if (!is_resource($process)) {
        return ['statut' => -1, 'sortie' => ''];
    }
    fclose($tuyaux[0]);
    $sortie = stream_get_contents($tuyaux[1]);
    $erreurs = stream_get_contents($tuyaux[2]);
    fclose($tuyaux[1]);
    fclose($tuyaux[2]);
    $statut = proc_close($process);
    $texte = '';
    if (is_string($sortie)) {
        $texte .= $sortie;
    }
    if (is_string($erreurs)) {
        $texte .= $erreurs;
    }

    return ['statut' => $statut, 'sortie' => $texte];
}

// ------------------------------------------------------------ prérequis
if (PHP_SAPI !== 'cli') {
    sauter('PHP natif CLI requis pour lancer `php -S` (SAPI actuel : ' . PHP_SAPI . ').');
}
if (PHP_BINARY === '') {
    sauter('PHP_BINARY vide : impossible de relancer le même binaire PHP en sous-processus.');
}
if (!function_exists('proc_open')) {
    sauter('proc_open indisponible (disable_functions ?) : impossible de lancer le serveur de test.');
}

$racine = dirname(__DIR__, 2);
$routeur = $racine . '/scripts/dev-router.php';
$public = $racine . '/public';

echo "== dev-router — tests du serveur de développement ==\n";
echo 'PHP ' . PHP_VERSION . ' (' . PHP_OS_FAMILY . ") — racine : {$racine}\n";

// ------------------------------------------------------------ R-00 : présence et contrat statique
echo "\n-- R-00 · fichier livré et résolu via __DIR__ --\n";
verdict(is_file($routeur), 'scripts/dev-router.php existe dans le dépôt');
$sourceRouteur = '';
if (is_file($routeur)) {
    $lu = file_get_contents($routeur);
    if (is_string($lu)) {
        $sourceRouteur = $lu;
    }
}
verdict(str_contains($sourceRouteur, "__DIR__"), 'le routeur résout ses chemins via __DIR__ (jamais le dossier courant)');
verdict(str_contains($sourceRouteur, 'return false'), 'le routeur sait rendre la main au serveur (fichiers réels)');

// ------------------------------------------------------------ R-01 : exécution directe
echo "\n-- R-01 · `php scripts/dev-router.php` seul : aide, pas d'arrêt silencieux --\n";
$direct = executerCommande([PHP_BINARY, $routeur], $racine);
verdict($direct['statut'] === 1, "code de sortie 1 (obtenu : {$direct['statut']})");
verdict(str_contains($direct['sortie'], 'pas un serveur'), "l'aide explique que ce n'est pas un serveur");
verdict(
    str_contains($direct['sortie'], 'php -S localhost:8000') && str_contains($direct['sortie'], 'Windows'),
    "l'aide donne les commandes Linux/macOS et Windows"
);

// ------------------------------------------------------------ fichiers sondes (fichiers réels)
$sondeStatique = $public . '/__dev_router_probe.txt';
$sondePhp = $public . '/__dev_router_probe.php';
file_put_contents($sondeStatique, 'MINISHOP-ROUTEUR-STATIQUE-OK');
file_put_contents($sondePhp, "<?php echo 'MINISHOP-ROUTEUR-PHP-OK';");

try {
    // ------------------------------------------------------------ R-02 : chemins absolus, cwd étranger (= cas XAMPP)
    echo "\n-- R-02 · serveur lancé depuis un dossier étranger, chemins absolus --\n";
    $port = portLibre();
    $adresse = '127.0.0.1:' . $port;
    $serveur = demarrerServeur([PHP_BINARY, '-S', $adresse, '-t', $public, $routeur], sys_get_temp_dir(), $port);
    verdict($serveur !== null, "le serveur démarre et RESTE démarré (port {$port}, cwd = " . sys_get_temp_dir() . ')');
    if ($serveur !== null) {
        try {
            $accueil = requeteHttp($port, '/');
            verdict(
                $accueil['statut'] === 200 && str_contains($accueil['corps'], 'Bienvenue chez MiniShop'),
                "GET / → 200 via le frontal (obtenu : {$accueil['statut']})"
            );

            $catalogue = requeteHttp($port, '/catalogue');
            verdict(
                $catalogue['statut'] === 200 && str_contains($catalogue['corps'], 'Catalogue'),
                "GET /catalogue → 200 (obtenu : {$catalogue['statut']})"
            );

            $recherche = requeteHttp($port, '/recherche?q=clavier');
            verdict(
                $recherche['statut'] === 200 && str_contains($recherche['corps'], 'Recherche'),
                "GET /recherche?q=clavier → 200, query string préservée (obtenu : {$recherche['statut']})"
            );

            // /index.php est un fichier réel : il s'exécute, puis le routeur
            // à liste blanche rejette cette URL (aucune route « /index.php »)
            // — exactement comme sous Apache + .htaccess.
            $indexDirect = requeteHttp($port, '/index.php');
            verdict(
                $indexDirect['statut'] === 404 && str_contains($indexDirect['corps'], 'Page introuvable'),
                "GET /index.php → 404 du frontal, parité Apache (obtenu : {$indexDirect['statut']})"
            );

            $statique = requeteHttp($port, '/__dev_router_probe.txt');
            verdict(
                $statique['statut'] === 200 && trim($statique['corps']) === 'MINISHOP-ROUTEUR-STATIQUE-OK',
                "GET fichier statique réel → servi directement, contenu exact (obtenu : {$statique['statut']})"
            );

            $phpReel = requeteHttp($port, '/__dev_router_probe.php');
            verdict(
                $phpReel['statut'] === 200 && trim($phpReel['corps']) === 'MINISHOP-ROUTEUR-PHP-OK',
                "GET fichier PHP réel → exécuté directement, pas réécrit vers le frontal (obtenu : {$phpReel['statut']})"
            );

            $phpReelQs = requeteHttp($port, '/__dev_router_probe.php?x=1');
            verdict(
                $phpReelQs['statut'] === 200 && trim($phpReelQs['corps']) === 'MINISHOP-ROUTEUR-PHP-OK',
                "GET fichier PHP réel + query string → exécuté (obtenu : {$phpReelQs['statut']})"
            );

            $mesurer = requeteHttp($port, '/mesurer.php');
            verdict(
                $mesurer['statut'] === 200 && str_contains($mesurer['corps'], 'Mesure'),
                "GET /mesurer.php (outil dev de public/) → 200 direct (obtenu : {$mesurer['statut']})"
            );

            $inconnu = requeteHttp($port, '/route-qui-nexiste-pas');
            verdict(
                $inconnu['statut'] === 404 && str_contains($inconnu['corps'], 'Page introuvable'),
                "GET /route-qui-nexiste-pas → 404 du frontal (obtenu : {$inconnu['statut']})"
            );

            $cssAbsent = requeteHttp($port, '/css/__absent.css');
            verdict(
                $cssAbsent['statut'] === 404 && str_contains($cssAbsent['corps'], 'Page introuvable'),
                "GET /css/__absent.css → 404 du frontal, pas le 404 nu du serveur (obtenu : {$cssAbsent['statut']})"
            );

            echo "\n-- R-02b · traversées : rien hors public/ ne doit fuir --\n";
            $fuites = ['declare(strict_types', 'Bienvenue chez MiniShop', 'alice@example.com', 'root:', '$2y$'];
            $traversees = [
                '/../app/bootstrap.php',
                '/%2e%2e/app/bootstrap.php',
                '/..%5capp%5cbootstrap.php',
                '/../data/minishop/client.json',
                '/%2e%2e/%2e%2e/etc/passwd',
                '/.git/config',
                '/app/Config/env.example.php',
            ];
            foreach ($traversees as $chemin) {
                $reponse = requeteHttp($port, $chemin);
                $propre = $reponse['statut'] >= 400 && $reponse['statut'] < 500;
                foreach ($fuites as $marqueur) {
                    // « Bienvenue » seul n'est pas une fuite (page 404 du frontal) :
                    // le marqueur sensible est le CONTENU du fichier visé.
                    if ($marqueur === 'Bienvenue chez MiniShop') {
                        continue;
                    }
                    if (str_contains($reponse['corps'], $marqueur)) {
                        $propre = false;
                    }
                }
                verdict($propre, "GET {$chemin} → refusé sans fuite (obtenu : {$reponse['statut']})");
            }
        } finally {
            arreterServeur($serveur);
        }
    }

    // ------------------------------------------------------------ R-03 : chemins relatifs depuis la racine (commande README)
    echo "\n-- R-03 · commande README relative, lancée depuis la racine du projet --\n";
    $portRelatif = portLibre();
    $adresseRelative = '127.0.0.1:' . $portRelatif;
    $serveurRelatif = demarrerServeur(
        [PHP_BINARY, '-S', $adresseRelative, '-t', 'public', 'scripts/dev-router.php'],
        $racine,
        $portRelatif
    );
    verdict(
        $serveurRelatif !== null,
        "le serveur démarre en relatifs (port {$portRelatif}) — `php -S … -t public scripts/dev-router.php`"
    );
    if ($serveurRelatif !== null) {
        try {
            $accueilRelatif = requeteHttp($portRelatif, '/');
            verdict(
                $accueilRelatif['statut'] === 200 && str_contains($accueilRelatif['corps'], 'Bienvenue chez MiniShop'),
                "GET / → 200 en mode relatif (obtenu : {$accueilRelatif['statut']})"
            );
            $statiqueRelative = requeteHttp($portRelatif, '/__dev_router_probe.txt');
            verdict(
                $statiqueRelative['statut'] === 200 && trim($statiqueRelative['corps']) === 'MINISHOP-ROUTEUR-STATIQUE-OK',
                "GET fichier statique → 200 en mode relatif (obtenu : {$statiqueRelative['statut']})"
            );
        } finally {
            arreterServeur($serveurRelatif);
        }
    }
} finally {
    if (is_file($sondeStatique)) {
        unlink($sondeStatique);
    }
    if (is_file($sondePhp)) {
        unlink($sondePhp);
    }
}

// ------------------------------------------------------------ bilan
echo "\n============================================================\n";
if ($nbEchecs > 0) {
    echo "dev-router : {$nbEchecs}/{$nbTests} vérification(s) en échec ❌\n";
    exit(1);
}
echo "dev-router : {$nbTests}/{$nbTests} vérifications vertes ✅\n";
exit(0);
