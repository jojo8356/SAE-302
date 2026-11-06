<?php

declare(strict_types=1);

/**
 * MiniShop — routeur du serveur de développement PHP intégré (`php -S`).
 *
 * Émule, pour `php -S`, ce que `public/.htaccess` fait sous Apache :
 *   - un fichier réel de `public/` (ex. `gen_volumes.php`, `mesurer.php`)
 *     est servi/exécuté directement (`return false`) ;
 *   - toute autre URL passe par le contrôleur frontal `public/index.php`
 *     (routeur à liste blanche, 404 propre pour les URL inconnues).
 *
 * Sans ce routeur, `php -S localhost:8000 -t public` fonctionne aussi
 * (le serveur intégré retombe sur `public/index.php`) : ce fichier n'est
 * qu'une fidélité supplémentaire à la règle Apache, pas une obligation.
 *
 * Chemins : tout est résolu depuis `__DIR__`, JAMAIS depuis le dossier de
 * travail courant. C'est le piège du lancement Windows/XAMPP signalé au
 * départ : lancé depuis `C:\xampp` (ou tout autre dossier), un chemin
 * relatif `scripts/dev-router.php` — comme `-t public` — ne désigne rien,
 * d'où le « Fatal error: Failed opening required … ». Utiliser les
 * commandes du README (on commence par `cd` à la racine du projet), ou
 * donner `-t` et le routeur en chemins absolus.
 *
 * Usage (Linux/macOS, depuis la racine du projet) :
 *   php -S localhost:8000 -t public scripts/dev-router.php
 * Usage (Windows/XAMPP, PowerShell, depuis la racine du projet) :
 *   C:\xampp\php\php.exe -S localhost:8000 -t public scripts\dev-router.php
 *
 * Production : ce fichier ne sert à rien — le serveur de production est
 * sous Linux/Apache (DocumentRoot = `public/`, `.htaccess` fait foi).
 *
 * Exécuté « à la main » (`php scripts/dev-router.php`), ce fichier n'est
 * PAS un serveur : il affiche l'aide et sort en erreur, plutôt que de
 * s'arrêter sans message.
 */

if (PHP_SAPI !== 'cli-server') {
    $message = "MiniShop — scripts/dev-router.php est le routeur du serveur PHP intégré, pas un serveur.\n"
        . "  Linux/macOS : php -S localhost:8000 -t public scripts/dev-router.php\n"
        . "  Windows     : C:\\xampp\\php\\php.exe -S localhost:8000 -t public scripts\\dev-router.php\n"
        . "  (à lancer depuis la racine du projet — cf. README, « Lancement »)\n";
    if (defined('STDERR')) {
        fwrite(STDERR, $message);
    } else {
        echo $message;
    }
    exit(1);
}

$public = dirname(__DIR__) . '/public';
$publicReel = realpath($public);
if ($publicReel === false) {
    http_response_code(500);
    echo 'MiniShop : dossier public/ introuvable.';
    return true;
}

$uri = '/';
if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
}
$chemin = parse_url($uri, PHP_URL_PATH);
if (!is_string($chemin) || $chemin === '') {
    $chemin = '/';
}
// Décodage + séparateurs Windows éventuels (%5c) : on ne compare que des
// chemins en « / », puis realpath() tranche (anti-traversée, cf. plus bas).
$chemin = str_replace('\\', '/', rawurldecode($chemin));

if ($chemin !== '/') {
    $candidat = realpath($publicReel . $chemin);
    if ($candidat !== false && is_file($candidat)) {
        // Le candidat doit rester DANS public/ : un realpath() qui en sort
        // (/../app/…, /../data/…) n'est jamais servi directement — il passe
        // par le contrôleur frontal, qui répondra 404.
        $prefixe = rtrim($publicReel, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($candidat, $prefixe)) {
            return false; // fichier réel : le serveur intégré le sert/l'exécute
        }
    }
}

require $publicReel . '/index.php';
return true;
