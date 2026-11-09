<?php

declare(strict_types=1);

/**
 * MiniShop — routeur du serveur de développement `php -S` (outil local).
 *
 * JAMAIS utilisé en production : Apache sert `public/` et applique
 * `public/.htaccess`. Le serveur intégré de PHP n'évalue pas les `.htaccess`
 * et répond 404 sur `/catalogue` ou `/produit/…` : ce fichier reproduit les
 * deux règles utiles du `.htaccess` (fichier existant → servi tel quel,
 * sinon → contrôleur frontal) et ajoute les gardes de la défense en
 * profondeur (SEC-11).
 *
 * Lancement (voir `run.sh`, qui fait tout) :
 *   php -S 127.0.0.1:8000 -t public scripts/dev-router.php
 *
 * Retour `false` = « laisse le serveur intégré servir le fichier », ce qui
 * conserve les bons types MIME (CSS, JS, SVG, images) et le cache.
 */

if (PHP_SAPI !== 'cli-server') {
    $message = "MiniShop — routeur du serveur PHP intégré, pas un serveur.\n"
        . "Lancer depuis la racine : php -S localhost:8000 -t public scripts/dev-router.php\n";
    if (defined('STDERR')) {
        fwrite(STDERR, $message);
    } else {
        echo $message;
    }
    exit(1);
}

$racine = realpath(dirname(__DIR__) . '/public');
if ($racine === false) {
    http_response_code(500);
    echo 'Dossier public/ introuvable.';

    return true;
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$chemin = parse_url($uri, PHP_URL_PATH);
if (!is_string($chemin)) {
    $chemin = '/';
}
$chemin = str_replace('\\', '/', rawurldecode($chemin));
if ($chemin === '') {
    $chemin = '/';
}

// --- 1. gardes du .htaccess : rien de sensible ne doit être servi -----------
$fichierCache = preg_match('#(^|/)\.#', $chemin) === 1;          // .env, .git/…
$extensionInterdite = preg_match('#\.(md|sql|log|ini|sh|bak)$#i', $chemin) === 1;
if ($fichierCache || $extensionInterdite) {
    http_response_code(404);
    echo 'Not Found';

    return true;
}

// --- 2. fichiers réels : le serveur intégré s'en charge (MIME corrects) -----
if ($chemin !== '/') {
    $candidat = realpath($racine . $chemin);
    $dansRacine = $candidat !== false && str_starts_with($candidat, $racine . DIRECTORY_SEPARATOR);
    if ($dansRacine && is_file($candidat)) {
        return false;
    }
}

// --- 3. tout le reste : contrôleur frontal unique (§9.2) --------------------
require $racine . '/index.php';

return true;
