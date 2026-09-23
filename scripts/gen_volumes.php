<?php
declare(strict_types=1);

/**
 * MiniShop — gen_volumes.php (wrapper PHP du générateur de volumétrie)
 *
 * Ce fichier existe pour offrir un point d'entrée PHP « à la place du .sh »
 * côté scripts/. Il ne s'exécute QUE dans le navigateur en dev, comme
 * public/gen_volumes.php. Un appel CLI pur affiche l'aide et sort en erreur.
 *
 * Usage recommandé (dev) : ouvrir public/gen_volumes.php dans le navigateur
 *   php -S localhost:8000 -t public
 *   → http://localhost:8000/gen_volumes.php
 *
 * Appel direct de ce wrapper via le navigateur (si scripts/ est exposé par
 * erreur) : redirige vers public/gen_volumes.php. En CLI : message d'aide.
 *
 * Le .sh historique reste utilisable en CI non-interactive :
 *   bash scripts/gen_volumes.sh
 *   bash scripts/gen_volumes.sh --purge
 */

if (PHP_SAPI === 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
    fwrite(STDERR, "MiniShop — générateur de volumétrie\n");
    fwrite(STDERR, "  Ce programme ne s'exécute QUE dans le navigateur en dev.\n");
    fwrite(STDERR, "  Lancez : php -S localhost:8000 -t public\n");
    fwrite(STDERR, "  Puis ouvrez : http://localhost:8000/gen_volumes.php\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "  (CI non-interactive : bash scripts/gen_volumes.sh [--purge])\n");
    exit(1);
}

// Si on arrive ici via HTTP, on sert le vrai générateur de public/
$public = __DIR__ . '/../public/gen_volumes.php';
if (is_file($public)) {
    // Transfère la requête au fichier public (même garde dev, même UI)
    require $public;
    exit;
}

http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Erreur — MiniShop</title>';
echo '<body style="font-family:system-ui;padding:2rem"><h1>500</h1><p>public/gen_volumes.php introuvable.</p></body>';
