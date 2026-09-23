<?php
declare(strict_types=1);

/**
 * MiniShop — mesure du temps de réponse d'une URL (ENF-01 : p95 < 500 ms).
 *
 * Remplace tests/perf/mesurer.sh par un programme PHP affichable UNIQUEMENT
 * dans le navigateur en environnement de développement. En production le script
 * répond 404 (aucune fuite d'existence) — même logique que RB-10 / ENF-12.
 *
 * Pourquoi ce programme existe : l'exigence ENF-01 impose p95 < 500 ms sur la
 * volumétrie retenue (200 produits / 1 000 commandes). Le .sh mesurait avec
 * `ab` ou une boucle `curl` + `awk`. Le .php offre la même mesure mais depuis
 * le navigateur (soutenance, prévisualisation Arena) sans dépendre d'un shell.
 *
 * Usage (dev uniquement) :
 *   php -S 0.0.0.0:8000 -t public
 *   → http://localhost:8000/mesurer.php
 *   → http://localhost:8000/mesurer.php?url=/catalogue&n=200&c=4
 *   → ou : URL_BASE=http://127.0.0.1:8000 N=200 C=4 → formulaire
 *
 *   Équivalence shell :
 *     URL_BASE=http://127.0.0.1:8000 N=200 ./tests/perf/mesurer.sh '/catalogue?cat=1'
 *     URL_BASE=http://127.0.0.1:8000 N=200 C=4 ./tests/perf/mesurer.sh '/'
 *
 * Modes :
 *   1) Navigateur (JS, par défaut, recommandé avec `php -S`) : le navigateur
 *      exécute N fetch() avec concurrences C, mesure performance.now(),
 *      calcule p50/p95/max. Avantage : aucune impasse avec `php -S` mono-thread
 *      (le .sh curl depuis l'extérieur, le JS fetch depuis le navigateur).
 *   2) Serveur (PHP curl_multi, POST) : le serveur PHP refait la boucle curl
 *      comme le .sh (utile pour comparer, et pour URL externe).
 *
 * Garde-fou :
 *   - exécution CLI pure interdite (PHP_SAPI === 'cli' sans requête HTTP)
 *   - accès refusé si l'environnement n'est pas 'dev' (APP_ENV, env.php, host)
 *   - en prod : 404 volontaire (pas 403) pour ne pas révéler l'existence de l'outil
 *   - CSRF sur les POST serveur
 */

// ---------------------------------------------------------------------
// 0) Gardes : CLI pur et environnement
// ---------------------------------------------------------------------
if (PHP_SAPI === 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
    fwrite(STDERR, "MiniShop ENF-01 — ce programme ne s'exécute QUE dans le navigateur en dev.\n");
    fwrite(STDERR, "  php -S 0.0.0.0:8000 -t public\n");
    fwrite(STDERR, "  → http://localhost:8000/mesurer.php\n");
    fwrite(STDERR, "  → http://localhost:8000/mesurer.php?url=/catalogue&n=200&c=4\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 1) Détection d'environnement dev (strict) — identique à gen_volumes.php
// ---------------------------------------------------------------------
function minishop_is_dev(): bool
{
    $envVars = [
        getenv('APP_ENV'),
        getenv('MINISHOP_ENV'),
        getenv('APP_ENVIRONMENT'),
        $_ENV['APP_ENV'] ?? null,
        $_SERVER['APP_ENV'] ?? null,
    ];
    foreach ($envVars as $v) {
        if (is_string($v) && in_array(strtolower(trim($v)), ['dev', 'development', 'local', 'developpement'], true)) {
            return true;
        }
    }
    if ((getenv('APP_DEV') === '1') || (getenv('APP_DEBUG') === '1') || (getenv('MINISHOP_DEV') === '1')) {
        return true;
    }
    $candidates = [
        __DIR__ . '/../app/Config/env.php',
        __DIR__ . '/../config/env.php',
        __DIR__ . '/../.env',
        __DIR__ . '/../../app/Config/env.php',
    ];
    foreach ($candidates as $f) {
        if (!is_file($f)) continue;
        $cfg = @include $f;
        if (is_array($cfg)) {
            $v = $cfg['env'] ?? $cfg['APP_ENV'] ?? $cfg['app_env'] ?? $cfg['environment'] ?? $cfg['ENV'] ?? null;
            if (is_string($v) && in_array(strtolower(trim($v)), ['dev', 'development', 'local'], true)) return true;
            if (!empty($cfg['APP_DEV']) || !empty($cfg['debug']) || !empty($cfg['dev'])) {
                if (isset($cfg['env']) && strtolower((string)$cfg['env']) === 'dev') return true;
                if (!isset($cfg['env']) && !empty($cfg['debug'])) return true;
            }
        } else {
            $raw = @file_get_contents($f);
            if ($raw !== false && preg_match('/^\s*APP_ENV\s*=\s*dev\s*$/mi', $raw)) return true;
            if ($raw !== false && preg_match('/^\s*env\s*=\s*dev\s*$/mi', $raw)) return true;
        }
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $addr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $isPreview = str_contains($host, '.e2b.app') || str_contains($host, '.arena.') || str_contains($host, '.internal');
    $isLocalHost = $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
        || str_starts_with($host, 'localhost:') || str_starts_with($host, '127.0.0.1:') || str_starts_with($host, '[::1]:')
        || str_contains($host, '.local') || str_contains($host, '.localhost')
        || str_contains($host, ':8000') || str_contains($host, ':3000') || str_contains($host, ':8080');
    $isLocalAddr = $addr === '127.0.0.1' || $addr === '::1' || $addr === '::ffff:127.0.0.1'
        || str_starts_with($addr, '10.') || str_starts_with($addr, '192.168.') || str_starts_with($addr, '172.');
    if ($isPreview || $isLocalHost) return true;
    if ($isLocalAddr && PHP_SAPI === 'cli-server') return true;
    if (is_file(__DIR__ . '/../var/.allow_volumetry') || is_file(__DIR__ . '/../.allow_dev')) return true;
    return false;
}
$isDev = minishop_is_dev();
if (!$isDev) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><meta charset="utf-8"><title>404 — MiniShop</title>';
    echo '<body style="font-family:system-ui;padding:4rem;text-align:center;color:#555">';
    echo '<h1 style="font-size:5rem;margin:0">404</h1><p>Page non trouvée.</p>';
    echo '<p style="font-size:.85rem;color:#999">MiniShop — ressource inexistante ou déplacée.</p>';
    echo '</body>';
    exit;
}

// ---------------------------------------------------------------------
// 2) Session + CSRF
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('MINISHOPSESSID');
    $cookieParams = ['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>false,'httponly'=>true,'samesite'=>'Lax'];
    if (PHP_VERSION_ID >= 70300) session_set_cookie_params($cookieParams);
    else session_set_cookie_params(0,'/','',false,true);
    @session_start();
}
if (empty($_SESSION['csrf_mesurer'])) $_SESSION['csrf_mesurer'] = bin2hex(random_bytes(16));
$csrfToken = $_SESSION['csrf_mesurer'];
function csrf_check_mesurer(string $t): bool { return hash_equals($_SESSION['csrf_mesurer'] ?? '', $t); }

// ---------------------------------------------------------------------
// 3) Paramètres (GET/POST/ENV) — même noms que le .sh
// ---------------------------------------------------------------------
$urlBaseEnv = getenv('URL_BASE') ?: '';
$defaultBase = $urlBaseEnv ?: (
    isset($_SERVER['HTTP_HOST'])
        ? (( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'])
        : 'http://127.0.0.1:8000'
);
$defaultUrl  = '/';
$defaultN    = 200;
$defaultC    = 4;

// Valeurs affichées (priorité : POST > GET > défaut)
$viewBase = trim((string)($_POST['base'] ?? $_GET['base'] ?? $_GET['URL_BASE'] ?? $defaultBase));
$viewUrl  = (string)($_POST['url'] ?? $_GET['url'] ?? $_GET['path'] ?? $defaultUrl);
// le .sh prend $1 comme url, on supporte aussi ?url=/catalogue
if (isset($_GET['url']) && $_GET['url'] === '' && isset($_SERVER['QUERY_STRING'])) {
    // cas ?/catalogue non encodé — rare
}
// Nettoyage
if ($viewBase === '') $viewBase = $defaultBase;
$viewBase = rtrim($viewBase, '/');
if ($viewUrl === '' || $viewUrl[0] !== '/') $viewUrl = '/' . ltrim($viewUrl, '/');
$viewN = (int)($_POST['n'] ?? $_GET['n'] ?? $_GET['N'] ?? $defaultN);
$viewC = (int)($_POST['c'] ?? $_GET['c'] ?? $_GET['C'] ?? $defaultC);
$viewN = max(1, min($viewN, 5000));
$viewC = max(1, min($viewC, 32));

// Pour le JS : url complète
$fullUrl = $viewBase . $viewUrl;

// ---------------------------------------------------------------------
// 4) Mesure côté serveur (PHP curl) — reproduit exactement le .sh
//    Utilisé uniquement sur POST action=server (évite le deadlock JS)
// ---------------------------------------------------------------------
function mesurer_serveur(string $fullUrl, int $n, int $c): array
{
    $times = [];
    $ok = 0; $fail = 0;
    $hasCurl = function_exists('curl_init');
    $hasMulti = function_exists('curl_multi_init');

    if ($hasCurl) {
        if ($c > 1 && $hasMulti && $n > $c) {
            // curl_multi avec fenêtre glissante de taille $c
            $mh = curl_multi_init();
            $handles = [];
            $next = 0;

            // initialise la première fenêtre
            for ($i = 0; $i < min($c, $n); $i++) {
                $ch = curl_init($fullUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_HEADER => false,
                    CURLOPT_USERAGENT => 'MiniShop-ENF01/php-curl',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[(int)$ch] = ['ch'=>$ch, 'start'=>microtime(true)];
                $next++;
            }
            do {
                $status = curl_multi_exec($mh, $active);
                if ($status !== CURLM_OK) break;
                // attend une activité
                curl_multi_select($mh, 1.0);
                // récupère les terminés
                while ($info = curl_multi_info_read($mh)) {
                    $ch = $info['handle'];
                    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $t = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                    if ($t === 0.0 && isset($handles[(int)$ch]['start'])) {
                        $t = microtime(true) - $handles[(int)$ch]['start'];
                    }
                    $times[] = $t;
                    if ($code === 200) $ok++; else $fail++;
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[(int)$ch]);
                    // ajoute le suivant s'il en reste
                    if ($next < $n) {
                        $ch2 = curl_init($fullUrl);
                        curl_setopt_array($ch2, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_TIMEOUT => 10,
                            CURLOPT_CONNECTTIMEOUT => 4,
                            CURLOPT_HEADER => false,
                            CURLOPT_USERAGENT => 'MiniShop-ENF01/php-curl',
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => 0,
                        ]);
                        curl_multi_add_handle($mh, $ch2);
                        $handles[(int)$ch2] = ['ch'=>$ch2, 'start'=>microtime(true)];
                        $next++;
                    }
                }
            } while ($active || $next < $n);
            curl_multi_close($mh);
        } else {
            // séquentiel (c=1 ou pas de multi)
            for ($i = 0; $i < $n; $i++) {
                $ch = curl_init($fullUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_HEADER => false,
                    CURLOPT_NOBODY => false,
                    CURLOPT_USERAGENT => 'MiniShop-ENF01/php-curl',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $t0 = microtime(true);
                curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $t = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                if ($t === 0.0) $t = microtime(true) - $t0;
                $times[] = $t;
                if ($code === 200) $ok++; else $fail++;
                $err = curl_error($ch);
                if ($err !== '' && $code === 0) $fail = $fail; // déjà compté
                curl_close($ch);
            }
        }
    } else {
        // fallback sans ext-curl : file_get_contents + timing
        for ($i = 0; $i < $n; $i++) {
            $t0 = microtime(true);
            $ctx = stream_context_create(['http'=>['timeout'=>10, 'ignore_errors'=>true, 'header'=>"User-Agent: MiniShop-ENF01/php\r\n"]]);
            $res = @file_get_contents($fullUrl, false, $ctx);
            $t = microtime(true) - $t0;
            $times[] = $t;
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $code = (int)$m[1];
            if ($code === 200 && $res !== false) $ok++; else $fail++;
        }
    }

    sort($times, SORT_NUMERIC);
    $cnt = count($times);
    $p95 = $cnt ? $times[max(0, (int)($cnt * 0.95) - 1)] : 0; // awk int(NR*0.95) ; awk est 1-indexed → -1
    // le .sh fait : p95=v[int(NR*0.95)>0?int(NR*0.95):NR] avec v[1..NR] → en php 0-indexed c'est -1
    // pour rester identique on garde la même formule 95e percentile
    // p50 : v[int(NR/2)>0?int(NR/2):1]
    $p50 = $cnt ? $times[max(0, (int)($cnt / 2) - 1)] : 0;
    $max = $cnt ? $times[$cnt - 1] : 0;
    $verdict = ($p95 < 0.5);
    return [
        'n' => $n, 'c' => $c, 'ok' => $ok, 'fail' => $fail,
        'times' => $times,
        'p50' => $p50, 'p95' => $p95, 'max' => $max,
        'verdict' => $verdict,
        'fullUrl' => $fullUrl,
    ];
}

$serverResult = null;
$serverError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'server') {
    if (!csrf_check_mesurer((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        $serverError = "Jeton CSRF invalide — rechargez la page.";
    } else {
        // re-lit les valeurs POST (déjà clampées)
        $fullUrl = $viewBase . $viewUrl;
        @set_time_limit(0);
        @ignore_user_abort(true);
        $t0 = microtime(true);
        try {
            $serverResult = mesurer_serveur($fullUrl, $viewN, $viewC);
            $serverResult['elapsed'] = microtime(true) - $t0;
        } catch (Throwable $e) {
            $serverError = "Erreur mesure serveur : " . htmlspecialchars($e->getMessage());
        }
        // Si la requête est AJAX (fetch JSON), on peut répondre JSON
        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => $serverResult['ok'] ?? 0,
                'fail' => $serverResult['fail'] ?? 0,
                'p50' => $serverResult['p50'] ?? 0,
                'p95' => $serverResult['p95'] ?? 0,
                'max' => $serverResult['max'] ?? 0,
                'verdict' => $serverResult['verdict'] ?? false,
                'n' => $viewN, 'c' => $viewC, 'url' => $fullUrl,
                'error' => $serverError,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// 5) Rendu HTML (dev-only)
// ---------------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MiniShop — Mesure ENF-01 (p95 &lt; 500 ms) — DEV uniquement</title>
<style>
  :root{--bg:#f8fafc;--card:#fff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--accent:#0ea5e9;--ok:#16a34a;--warn:#d97706;--bad:#dc2626}
  *{box-sizing:border-box} body{margin:0;font-family:ui-sans-system,system-ui,Segoe UI,Roboto,Helvetica,Arial;color:var(--ink);background:var(--bg);line-height:1.5}
  header{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.95);backdrop-filter:blur(6px);border-bottom:1px solid var(--line)}
  .wrap{max-width:1050px;margin:0 auto;padding:1.2rem 1rem} h1{font-size:1.35rem;margin:.2rem 0} h1 small{color:var(--muted);font-weight:500;font-size:.9rem}
  .badge{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--line);background:#fff;border-radius:999px;padding:.2rem .6rem;font-size:.78rem;color:var(--muted)}
  .badge.dev{background:#fef3c7;border-color:#fde68a;color:#92400e} .badge.ok{background:#dcfce7;border-color:#bbf7d0;color:#14532d}
  .grid{display:grid;gap:1rem} @media(min-width:900px){.grid.cols-2{grid-template-columns:1.05fr .95fr}}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:1rem;box-shadow:0 1px 2px rgba(15,23,42,.05)}
  .card h3{margin:.2rem 0 .6rem;font-size:.95rem}
  label{font-size:.85rem;color:#334155;display:block;margin:.6rem 0 .2rem}
  input[type=text],input[type=number]{width:100%;padding:.55rem .65rem;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit}
  input:focus{outline:2px solid #7dd3fc;border-color:#7dd3fc}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem} .row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:.8rem}
  @media(max-width:640px){.row,.row3{grid-template-columns:1fr}}
  .btn{appearance:none;border:1px solid transparent;border-radius:10px;padding:.6rem .9rem;font-weight:600;cursor:pointer;font:inherit}
  .btn-primary{background:var(--ink);color:#fff} .btn-primary:hover{background:#1e293b}
  .btn-ghost{background:#fff;border-color:var(--line)} .btn-ghost:hover{background:#f1f5f9}
  .btn-warn{background:#fffbeb;border-color:#fde68a;color:#92400e} .btn:disabled{opacity:.55;cursor:not-allowed}
  .actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}
  pre{margin:0;white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;border-radius:10px;padding:.8rem;font-size:.82rem;line-height:1.45;max-height:460px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:.85rem} th,td{padding:.45rem .5rem;border-bottom:1px solid var(--line);text-align:left}
  th{color:var(--muted);font-weight:600;background:#f8fafc}
  .muted{color:var(--muted)} .small{font-size:.82rem}
  .alert{border-radius:10px;padding:.7rem .8rem;border:1px solid} .alert-err{background:#fef2f2;border-color:#fecaca;color:#7f1d1d}
  .alert-ok{background:#f0fdf4;border-color:#bbf7d0;color:#14532d} .alert-warn{background:#fffbeb;border-color:#fde68a;color:#78350f}
  code{background:#f1f5f9;padding:.15rem .35rem;border-radius:6px;font-size:.85em}
  footer{color:var(--muted);font-size:.8rem;text-align:center;padding:2rem 1rem}
  .sep{height:1px;background:var(--line);margin:1rem 0}
  .kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem}
  @media(max-width:700px){.kpi{grid-template-columns:repeat(2,1fr)}}
  .kpi div{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:.6rem .7rem}
  .kpi b{font-size:1.15rem} .kpi span{font-size:.72rem;color:var(--muted);display:block}
  .bar{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden} .bar>i{display:block;height:100%;background:var(--accent);width:0%}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
</style>
</head>
<body>
<header>
  <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <h1>MiniShop — Mesure <small>ENF-01 · p95 &lt; 500 ms</small></h1>
      <div class="small muted">Remplace <code>tests/perf/mesurer.sh</code> — <strong>navigateur uniquement, environnement dev</strong>. En prod → 404.</div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <span class="badge dev">● DEV uniquement</span>
      <span class="badge">PHP <?=htmlspecialchars(PHP_VERSION)?> <?=function_exists('curl_init') ? '· curl' : '· no-curl'?></span>
      <span class="badge ok">ENF-01 · URL_BASE/N/C</span>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="card" style="border-left:4px solid var(--accent)">
    <div class="small muted">Pourquoi ce programme existe</div>
    <p class="small" style="margin:.4rem 0">
      <code>ENF-01</code> exige <strong>p95 &lt; 500 ms</strong> sur la volumétrie retenue (200 produits / 1 000 commandes, générée par
      <a href="/gen_volumes.php">/gen_volumes.php</a>). Le <code>.sh</code> mesurait avec <code>ab</code> ou une boucle <code>curl</code> + <code>awk</code>
      trié pour p50/p95. Le <code>.php</code> offre la même mesure depuis le navigateur : pas de shell en soutenance, pas de dépendance <code>ab</code>,
      et un affichage direct dans la prévisualisation Arena (<code>e2b.app</code>).
    </p>
    <p class="small muted" style="margin:.2rem 0">
      Équivalences : <code>URL_BASE=http://127.0.0.1:8000 N=200 C=4 ./tests/perf/mesurer.sh '/catalogue?cat=1'</code>
      → formulaire ci-dessous (GET <code>?base=&amp;url=&amp;n=&amp;c=</code>). Seuil : <code>p95 &lt; 0.5s</code> → exit&nbsp;0.
    </p>
  </div>

  <?php if ($serverError): ?>
    <div class="alert alert-err" style="margin-top:1rem"><?= $serverError ?></div>
  <?php endif; ?>

  <div class="grid cols-2" style="margin-top:1rem">
    <!-- Formulaire -->
    <form class="card" id="form" method="get" autocomplete="off" onsubmit="return false">
      <h3>Paramètres (comme le .sh)</h3>
      <div class="small muted" style="margin-bottom:.6rem">URL testée = <code>base</code> + <code>url</code> (1er arg du .sh). <code>N</code>=nb requêtes, <code>C</code>=concurrence.</div>

      <label for="base">URL_BASE <span class="muted">— base (ex: http://127.0.0.1:8000)</span></label>
      <input id="base" name="base" type="text" value="<?=htmlspecialchars($viewBase)?>" placeholder="http://127.0.0.1:8000" required>

      <label for="url">URL <span class="muted">— chemin ($1 du .sh, défaut /)</span></label>
      <input id="url" name="url" type="text" value="<?=htmlspecialchars($viewUrl)?>" placeholder="/catalogue?cat=1" required>

      <div class="row">
        <div>
          <label for="n">N <span class="muted">— requêtes (déf. 200)</span></label>
          <input id="n" name="n" type="number" min="1" max="5000" value="<?= (int)$viewN ?>">
        </div>
        <div>
          <label for="c">C <span class="muted">— concurrence (déf. 4)</span></label>
          <input id="c" name="c" type="number" min="1" max="32" value="<?= (int)$viewC ?>">
        </div>
      </div>

      <div class="row" style="margin-top:.6rem">
        <div>
          <label for="mode">Mode <span class="muted">— JS vs PHP serveur</span></label>
          <select id="mode" style="width:100%;padding:.55rem .65rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff">
            <option value="js" selected>Navigateur (JS fetch, recommandé avec php -S)</option>
            <option value="server">Serveur (PHP curl, comme le .sh)</option>
          </select>
          <div class="small muted" id="modeHint">JS = pas d'impasse mono-thread, mesure depuis votre navigateur.</div>
        </div>
        <div style="display:flex;align-items:flex-end">
          <label style="display:flex;gap:.4rem;align-items:center;margin:0"><input type="checkbox" id="keepCache" value="1"> <span class="small">cache: no-store</span></label>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" id="btnJs" type="button">▶ Mesurer (JS)</button>
        <button class="btn btn-ghost" id="btnServer" type="button">↻ Mesurer côté serveur</button>
        <a class="btn btn-ghost" id="btnLink" href="#">Lien GET</a>
      </div>
      <div class="small muted" style="margin-top:.6rem">
        Full URL : <code class="mono" id="fullUrl"><?=htmlspecialchars($fullUrl)?></code> ·
        <span id="curlHint">curl ≈ <code id="curlCmd"></code></span>
      </div>
      <div class="bar" style="margin-top:.6rem"><i id="prog"></i></div>
      <div class="small muted" id="progText" style="margin-top:.2rem"></div>
    </form>

    <!-- Résultat -->
    <div class="card" id="resultCard">
      <h3>Résultat — ENF-01</h3>
      <div class="kpi">
        <div><b id="kReq">—</b><span>requêtes (200 : ok / autres)</span></div>
        <div><b id="kP50">—</b><span>médiane (p50)</span></div>
        <div><b id="kP95">—</b><span>p95 (seuil 500 ms)</span></div>
        <div><b id="kMax">—</b><span>max</span></div>
      </div>
      <div style="margin-top:.8rem" id="verdict" class="alert alert-warn">En attente de mesure…</div>

      <div class="sep"></div>
      <h3>Détail</h3>
      <pre id="out">requetes= — (200: —, autres: —)
mediane=—.---s p95=—.---s max=—.---s
ENF-01 —</pre>
      <p class="small muted" style="margin:.4rem 0 0">Calcul identique au .sh : tri numérique, <code>p95=v[int(NR*0.95)]</code>, <code>p50=v[int(NR/2)]</code>, exit&nbsp;1 si p95≥0.5s. Le serveur PHP utilise <code>curl_getinfo(TOTAL_TIME)</code>, le navigateur <code>performance.now()</code>.</p>

      <?php if ($serverResult): ?>
        <div class="sep"></div>
        <div class="alert <?= $serverResult['verdict'] ? 'alert-ok' : 'alert-err' ?>">
          <strong>Mesure serveur (POST) — <?=htmlspecialchars($serverResult['fullUrl'])?></strong><br>
          requetes=<?= (int)$serverResult['n'] ?> (200: <?= (int)$serverResult['ok'] ?>, autres: <?= (int)$serverResult['fail'] ?>)<br>
          mediane=<?= number_format((float)$serverResult['p50'], 3) ?>s p95=<?= number_format((float)$serverResult['p95'], 3) ?>s max=<?= number_format((float)$serverResult['max'], 3) ?>s<br>
          <?= $serverResult['verdict'] ? 'ENF-01 conforme (p95 &lt; 500 ms)' : 'ENF-01 NON conforme : mesurer la requête (general_log) et vérifier les index' ?>
          — <?= number_format((float)$serverResult['elapsed'], 2) ?>s côté serveur
        </div>
        <pre style="margin-top:.6rem"><?=htmlspecialchars(
            sprintf("requetes=%d (200: %d, autres: %d)\nmediane=%.3fs p95=%.3fs max=%.3fs\n%s",
                $serverResult['n'], $serverResult['ok'], $serverResult['fail'],
                $serverResult['p50'], $serverResult['p95'], $serverResult['max'],
                $serverResult['verdict'] ? 'ENF-01 conforme (p95 < 500 ms)' : 'ENF-01 NON conforme : mesurer la requête (general_log) et vérifier les index'
            ))?></pre>
      <?php endif; ?>

      <div class="sep"></div>
      <h3>Astuce `php -S`</h3>
      <p class="small" style="margin:.2rem 0">
        <code>php -S</code> est mono-thread : mesurer <code>http://127.0.0.1:8000/…</code> <em>depuis le même processus</em> bloque.
        Le mode <strong>JS</strong> mesure depuis le navigateur (2ᵉ processus), le mode <strong>serveur</strong> mesure depuis PHP (bloque si même port).
        En prévisualisation Arena, <code>fetch()</code> vers l'origine courante est la méthode la plus fiable.
      </p>
    </div>
  </div>

  <!-- Form POST serveur caché (pour le bouton serveur) -->
  <form id="formServer" method="post" style="display:none">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
    <input type="hidden" name="action" value="server">
    <input type="hidden" name="base" id="postBase">
    <input type="hidden" name="url" id="postUrl">
    <input type="hidden" name="n" id="postN">
    <input type="hidden" name="c" id="postC">
  </form>

  <div class="card" style="margin-top:1rem">
    <h3>Reproductibilité</h3>
    <ul class="small" style="margin:.4rem 0;padding-left:1.2rem">
      <li>Volumétrie : <a href="/gen_volumes.php">/gen_volumes.php</a> (200 produits / 1 000 commandes) → <code>./tests/perf/mesurer_sql.sh</code> pour la base seule.</li>
      <li>Le <code>.sh</code> historique utilisait <code>ab -n $N -c $C</code> s'il était présent, sinon <code>curl -w '%{time_total} %{http_code}'</code> + <code>sort | awk</code>. Le <code>.php</code> reproduit la 2ᵉ branche en JS/PHP.</li>
      <li>En CI : le PHP n'est pas requis ; le rapport <code>general_log</code> (≤5 requêtes par page) reste la preuve complémentaire.</li>
    </ul>
  </div>
</main>

<footer>
  MiniShop — SAE 302 — Mesure ENF-01 PHP (dev-only, navigateur) — remplace <code>tests/perf/mesurer.sh</code>.<br>
  DocumentRoot = <code>public/</code> · <code>php -S 0.0.0.0:8000 -t public</code> → <code>http://localhost:8000/mesurer.php?url=/&amp;n=200&amp;c=4</code>
</footer>

<script>
const $ = s => document.querySelector(s);
const baseEl = $('#base'), urlEl=$('#url'), nEl=$('#n'), cEl=$('#c'), fullEl=$('#fullUrl'), curlEl=$('#curlCmd'), linkEl=$('#btnLink');
const progBar=$('#prog'), progText=$('#progText'), modeEl=$('#mode'), hintEl=$('#modeHint');
const kReq=$('#kReq'), kP50=$('#kP50'), kP95=$('#kP95'), kMax=$('#kMax'), verdictEl=$('#verdict'), outEl=$('#out');
const btnJs=$('#btnJs'), btnServer=$('#btnServer');

function fullUrl(){ const b=baseEl.value.trim().replace(/\/$/,''); let u=urlEl.value.trim(); if(!u.startsWith('/')) u='/'+u; return b+u; }
function updateLink(){
  const b=encodeURIComponent(baseEl.value.trim()), u=encodeURIComponent(urlEl.value.trim()), n=nEl.value, c=cEl.value;
  const url=`?base=${b}&url=${u}&n=${n}&c=${c}`;
  linkEl.href=url; fullEl.textContent=fullUrl();
  curlEl.textContent=`URL_BASE=${baseEl.value} N=${n} C=${c} → ${fullUrl()}`;
  history.replaceState(null,'',url);
}
['input','change'].forEach(ev=>{
  baseEl.addEventListener(ev, updateLink);
  urlEl.addEventListener(ev, updateLink);
  nEl.addEventListener(ev, updateLink);
  cEl.addEventListener(ev, updateLink);
});
modeEl.addEventListener('change', ()=>{
  hintEl.textContent = modeEl.value==='js'
    ? 'JS = pas d\'impasse mono-thread, mesure depuis votre navigateur.'
    : 'Serveur = comme le .sh curl, bloque si même port php -S — préférez une URL externe.';
});
updateLink();

function fmt(s){ return s.toFixed(3)+'s'; }
function setVerdict(p95, ok, fail, n){
  const conforme = p95 < 0.5;
  kP95.textContent = fmt(p95); kP95.style.color = conforme ? '#16a34a' : '#dc2626';
  verdictEl.className = 'alert ' + (conforme ? 'alert-ok' : 'alert-err');
  verdictEl.innerHTML = conforme
    ? 'ENF-01 conforme (p95 &lt; 500 ms)'
    : 'ENF-01 NON conforme : mesurer la requête (general_log) et vérifier les index';
  return conforme;
}

// --- Mesure JS (fetch) ---
async function measureJs(){
  const n = Math.max(1, Math.min(5000, parseInt(nEl.value,10)||200));
  const c = Math.max(1, Math.min(32, parseInt(cEl.value,10)||4));
  const url = fullUrl();
  const noStore = $('#keepCache').checked ? 'no-store' : 'default';
  btnJs.disabled = btnServer.disabled = true;
  progBar.style.width='0%'; progText.textContent=`0 / ${n}`;
  verdictEl.className='alert alert-warn'; verdictEl.textContent='Mesure en cours…';
  outEl.textContent='…'; kReq.textContent='…'; kP50.textContent='…'; kP95.textContent='…'; kMax.textContent='…';

  const times=[]; let ok=0, fail=0; let done=0;

  async function one(){
    const t0 = performance.now();
    try{
      const res = await fetch(url, {cache: noStore, credentials: 'same-origin'});
      const t = (performance.now()-t0)/1000;
      times.push(t);
      if(res.status===200) ok++; else fail++;
      // consomme le body pour ne pas laisser de flux ouvert
      try{ await res.text(); }catch(e){}
    }catch(e){
      const t=(performance.now()-t0)/1000; times.push(t); fail++;
    }
    done++; progBar.style.width=((done/n)*100).toFixed(1)+'%'; progText.textContent=`${done} / ${n} — ok:${ok} fail:${fail}`;
  }

  // pool avec concurrence c
  for(let i=0;i<n;i+=c){
    const batch=[];
    for(let j=0;j<c && i+j<n;j++) batch.push(one());
    await Promise.all(batch);
  }

  times.sort((a,b)=>a-b);
  const cnt=times.length;
  const p95 = cnt ? times[Math.max(0, Math.floor(cnt*0.95)-1)] : 0;
  const p50 = cnt ? times[Math.max(0, Math.floor(cnt/2)-1)] : 0;
  const max = cnt ? times[cnt-1] : 0;
  kReq.textContent = `requetes=${cnt} (200:${ok} autres:${fail})`;
  kP50.textContent = fmt(p50); kMax.textContent=fmt(max);
  kP95.textContent = fmt(p95);
  const conforme = setVerdict(p95, ok, fail, cnt);
  outEl.textContent =
    `requetes=${cnt} (200: ${ok}, autres: ${fail})\n`+
    `mediane=${p50.toFixed(3)}s p95=${p95.toFixed(3)}s max=${max.toFixed(3)}s\n`+
    (conforme ? 'ENF-01 conforme (p95 < 500 ms)' : 'ENF-01 NON conforme : mesurer la requête (general_log) et vérifier les index');

  // met à jour le GET pour partage
  updateLink();
  btnJs.disabled = btnServer.disabled = false;
}

btnJs.addEventListener('click', measureJs);

// --- Mesure serveur (POST) ---
btnServer.addEventListener('click', ()=>{
  const f=$('#formServer');
  $('#postBase').value=baseEl.value.trim();
  $('#postUrl').value=urlEl.value.trim();
  $('#postN').value=nEl.value; $('#postC').value=cEl.value;
  f.submit();
});

// Auto-lance si ?autorun=1 ou si n est petit et on veut démonstration
if(new URLSearchParams(location.search).has('autorun')) measureJs();
</script>
</body>
</html>
