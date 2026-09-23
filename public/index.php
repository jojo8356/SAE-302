<?php
declare(strict_types=1);

/**
 * MiniShop — front controller minimal (public/index.php)
 * Route unique du site (DocumentRoot = public/). En dev, expose aussi
 * /gen_volumes.php comme fichier réel (RewriteCond !-f) — pas besoin de route.
 *
 * Ce fichier est volontairement léger : l'arborescence cible complète est
 * décrite au §9.2 du CDC (app/Controller, app/Repository, etc.). Il affiche
 * une page d'accueil provisoire et un lien vers le générateur dev.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Laisse les fichiers réels (gen_volumes.php, css, js, img) être servis par Apache / php -S
$real = __DIR__ . $uri;
if ($uri !== '/' && is_file($real)) {
    return false; // php -S : sert le fichier tel quel
}

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MiniShop — dev</title>
<style>
  body{font-family:system-ui, sans-serif;max-width:720px;margin:3rem auto;padding:0 1rem;color:#0f172a;line-height:1.6}
  a{color:#0ea5e9} code{background:#f1f5f9;padding:.15rem .35rem;border-radius:6px}
  .card{border:1px solid #e2e8f0;border-radius:12px;padding:1rem;background:#fff}
</style>
<h1>MiniShop — environnement de développement</h1>
<p>DocumentRoot = <code>public/</code> · Front controller <code>public/index.php</code> (provisoire, lot L8→L13).</p>
<div class="card">
  <h3 style="margin:.2rem 0">Outils dev</h3>
  <ul>
    <li><a href="/gen_volumes.php">/gen_volumes.php</a> — générateur de volumétrie (ENF-01/ENF-02, navigateur uniquement, dev-only)</li>
    <li><code>php -S 0.0.0.0:8000 -t public</code> — serveur embarqué (prévisualisation Arena : <code>https://{port}-{sandboxId}.e2b.app</code>)</li>
  </ul>
  <p style="font-size:.85rem;color:#64748b">En prod ce fichier et <code>gen_volumes.php</code> répondent 404 si <code>APP_ENV</code> ≠ <code>dev</code>.</p>
</div>
<p style="font-size:.85rem;color:#64748b">CDC : <a href="../docs/01-cahier-des-charges-MiniShop.md">docs/01-cahier-des-charges-MiniShop.md</a> · SQL : <code>sql/01 → 02 → 03 → 04</code> → <code>scripts/load_db.sh</code></p>
