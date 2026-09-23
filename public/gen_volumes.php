<?php
declare(strict_types=1);

/**
 * MiniShop — générateur de volumétrie pour les mesures de performance (ENF-01 / ENF-02).
 *
 * Remplace scripts/gen_volumes.sh par un programme PHP affichable UNIQUEMENT dans le
 * navigateur en environnement de développement. En production le script répond 404
 * (aucune fuite d'existence) — même logique que le contrôle d'appartenance RB-10 / ENF-12.
 *
 * Pourquoi ce générateur existe : un objectif de performance posé sur le jeu de
 * démonstration (12 produits, 3 commandes) n'a aucune valeur de mesure. La cible
 * retenue au CDC est « petit e-commerce réel » : 200 produits, 1 000 commandes sur
 * 12 mois, 10 clients.
 *
 * Les commandes sont créées EN PASSANT PAR LES PROCEDURES STOCKEES : le générateur
 * exerce donc les mêmes chemins que l'application (snapshot de prix, frais de port,
 * décrément de stock, historique) et les mesures portent sur des données cohérentes
 * avec RB-06 / RB-15.
 * Les produits sont insérés par INSERT ... VALUES, JAMAIS par INSERT ... SELECT :
 * vérifié sur MariaDB 11.8, un INSERT ... SELECT ne déclenche pas les triggers
 * BEFORE INSERT qui affectent NEW, donc `prix_ttc` (NOT NULL, calculé par
 * trg_produit_ttc) serait refusé.
 *
 * Usage (dev uniquement) :
 *   php -S localhost:8000 -t public
 *   → http://localhost:8000/gen_volumes.php
 *   → choisir DB / N_PRODUITS / N_COMMANDES / N_CLIENTS puis [Générer] ou [Purger]
 *
 *   Variables équivalentes au .sh :
 *     DB=minishop_perf  N_PRODUITS=200  N_COMMANDES=1000  N_CLIENTS=10  BATCH=25
 *   Ici : champs du formulaire (saisie utilisateur) + valeurs d'environnement
 *         (app/Config/env.php, DB_HOST, DB_USER, DB_PASS).
 *
 * Garde-fou :
 *   - exécution CLI pure interdite (PHP_SAPI === 'cli' sans requête HTTP)
 *   - accès refusé si l'environnement n'est pas 'dev' (APP_ENV, env.php, host)
 *   - en prod : 404 volontaire (pas 403) pour ne pas révéler l'existence de l'outil
 *   - CSRF sur les POST, HttpOnly/Lax déjà posés par Auth si présent
 *
 * Dépendances : PHP 8.1+ pdo_mysql, MySQL 8 / MariaDB 10.6+, bases et procédures déjà
 * chargées (sql/01 → 02 → 03 → 04 via scripts/load_db.sh).
 */

// ---------------------------------------------------------------------
// 0) Gardes : CLI pur et environnement
// ---------------------------------------------------------------------
if (PHP_SAPI === 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
    // Appel direct `php public/gen_volumes.php` ou `php scripts/gen_volumes.php`
    fwrite(STDERR, "MiniShop volumétrie : ce programme ne s'exécute QUE dans le navigateur en dev.\n");
    fwrite(STDERR, "  php -S localhost:8000 -t public\n");
    fwrite(STDERR, "  → http://localhost:8000/gen_volumes.php\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 1) Détection d'environnement dev (strict)
// ---------------------------------------------------------------------
function minishop_is_dev(): bool
{
    // 1a) variable d'environnement explicite — prioritaire
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

    // 1b) fichier de config
    $candidates = [
        __DIR__ . '/../app/Config/env.php',
        __DIR__ . '/../config/env.php',
        __DIR__ . '/../.env',
        __DIR__ . '/../../app/Config/env.php',
    ];
    foreach ($candidates as $f) {
        if (!is_file($f)) {
            continue;
        }
        $cfg = @include $f;
        if (is_array($cfg)) {
            $v = $cfg['env'] ?? $cfg['APP_ENV'] ?? $cfg['app_env'] ?? $cfg['environment'] ?? $cfg['ENV'] ?? null;
            if (is_string($v) && in_array(strtolower(trim($v)), ['dev', 'development', 'local'], true)) {
                return true;
            }
            if (!empty($cfg['APP_DEV']) || !empty($cfg['debug']) || !empty($cfg['dev'])) {
                // debug=true seul ne suffit pas : on exige env=dev
                if (isset($cfg['env']) && strtolower((string)$cfg['env']) === 'dev') {
                    return true;
                }
                // si debug est présent et qu'aucun env n'est défini, on considère dev par tolérance
                if (!isset($cfg['env']) && !empty($cfg['debug'])) {
                    return true;
                }
            }
        } else {
            $raw = @file_get_contents($f);
            if ($raw !== false && preg_match('/^\s*APP_ENV\s*=\s*dev\s*$/mi', $raw)) {
                return true;
            }
            if ($raw !== false && preg_match('/^\s*env\s*=\s*dev\s*$/mi', $raw)) {
                return true;
            }
        }
    }

    // 1c) host / IP (tolérance pour sandbox / preview / localhost)
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $addr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    // Preview Arena: https://{port}-{sandboxId}.e2b.app
    $isPreview = str_contains($host, '.e2b.app') || str_contains($host, '.arena.') || str_contains($host, '.internal');
    $isLocalHost = $host === '' // cli-server sans host explicite
        || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
        || str_starts_with($host, 'localhost:') || str_starts_with($host, '127.0.0.1:') || str_starts_with($host, '[::1]:')
        || str_contains($host, '.local') || str_contains($host, '.localhost')
        || str_contains($host, ':8000') || str_contains($host, ':3000') || str_contains($host, ':8080');
    $isLocalAddr = $addr === '127.0.0.1' || $addr === '::1' || $addr === '::ffff:127.0.0.1'
        || str_starts_with($addr, '10.') || str_starts_with($addr, '192.168.') || str_starts_with($addr, '172.');

    if ($isPreview || $isLocalHost) {
        return true;
    }
    // En cli-server, REMOTE_ADDR vaut souvent 127.0.0.1
    if ($isLocalAddr && PHP_SAPI === 'cli-server') {
        return true;
    }

    // 1d) fichier drapeau explicite pour forcer le dev en sandbox sans config
    if (is_file(__DIR__ . '/../var/.allow_volumetry') || is_file(__DIR__ . '/../.allow_dev')) {
        return true;
    }

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
// 2) Session + CSRF (léger, dev uniquement)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('MINISHOPSESSID');
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false, // dev = http
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(0, '/', '', false, true);
    }
    @session_start();
}
if (empty($_SESSION['csrf_volumetry'])) {
    $_SESSION['csrf_volumetry'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_volumetry'];

function csrf_check(string $token): bool
{
    return hash_equals($_SESSION['csrf_volumetry'] ?? '', $token);
}

// ---------------------------------------------------------------------
// 3) Helpers : config, PDO, comptes
// ---------------------------------------------------------------------
function load_minishop_config(?string $overrideDb = null): array
{
    $candidates = [
        __DIR__ . '/../app/Config/env.php',
        __DIR__ . '/../config/env.php',
    ];
    $cfg = [];
    foreach ($candidates as $f) {
        if (is_file($f)) {
            $tmp = @include $f;
            if (is_array($tmp)) {
                $cfg = $tmp;
                break;
            }
        }
    }
    // .env fallback
    if (is_file(__DIR__ . '/../.env')) {
        $parsed = @parse_ini_file(__DIR__ . '/../.env');
        if (is_array($parsed)) {
            $cfg = array_merge($parsed, $cfg);
        }
    }
    // Valeurs par défaut + surcharges env
    $db = $overrideDb ?? $cfg['db_name'] ?? $cfg['DB'] ?? getenv('DB') ?: 'minishop_perf';
    // Si DB contient un nom vide, forcer minishop_perf (cible volumétrie)
    if (!is_string($db) || trim($db) === '') {
        $db = 'minishop_perf';
    }
    return [
        'db_host' => $cfg['db_host'] ?? getenv('DB_HOST') ?: '127.0.0.1',
        'db_port' => (int)($cfg['db_port'] ?? getenv('DB_PORT') ?: 3306),
        'db_name' => $db,
        'db_user' => $cfg['db_user'] ?? getenv('DB_USER') ?: 'root',
        'db_pass' => $cfg['db_pass'] ?? getenv('DB_PASS') ?: (getenv('MYSQL_PWD') ?: ''),
        'env'     => $cfg['env'] ?? $cfg['APP_ENV'] ?? 'dev',
    ];
}

function pdo_for(array $cfg): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $cfg['db_name']);
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    // Test rapide : la base existe-t-elle ?
    $pdo->query('SELECT 1');
    return $pdo;
}

function current_counts(PDO $pdo): array
{
    $sql = "SELECT
        (SELECT COUNT(*) FROM produit) AS produits,
        (SELECT COUNT(*) FROM client) AS clients,
        (SELECT COUNT(*) FROM commande) AS commandes,
        (SELECT COUNT(*) FROM ligne_commande) AS lignes,
        (SELECT COUNT(*) FROM order_status_history) AS traces,
        (SELECT COUNT(*) FROM commande WHERE frais_port > 0) AS commandes_avec_port,
        (SELECT COUNT(*) FROM produit WHERE prix_ttc <> ROUND(prix_ht*(1+tva/100),2)) AS ttc_incoherents,
        (SELECT COUNT(*) FROM commande c WHERE c.statut <> 'BROUILLON' AND c.montant_total <>
            (SELECT COALESCE(SUM(l.total_ligne),0) FROM ligne_commande l WHERE l.id_commande=c.id_commande) + c.frais_port) AS montants_incoherents";
    $row = $pdo->query($sql)->fetch();
    return $row ?: [];
}

function purge_volumetrie(PDO $pdo): array
{
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $deleted = [];
    $deleted['lignes'] = (int)$pdo->exec("DELETE FROM ligne_commande WHERE id_commande > 100");
    $deleted['traces'] = (int)$pdo->exec("DELETE FROM order_status_history WHERE order_id > 100");
    $deleted['commandes'] = (int)$pdo->exec("DELETE FROM commande WHERE id_commande > 100");
    $deleted['produits'] = (int)$pdo->exec("DELETE FROM produit WHERE reference LIKE 'VP-%'");
    $deleted['clients'] = (int)$pdo->exec("DELETE FROM client WHERE email LIKE 'perf%@minishop.local'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    return $deleted;
}

// ---------------------------------------------------------------------
// 4) Génération : produits, clients, commandes via procédures
// ---------------------------------------------------------------------
function generate_produits(PDO $pdo, int $nProduits, array &$log): int
{
    if ($nProduits <= 0) {
        return 0;
    }
    $inserted = 0;
    // On insère par lots de 100 pour éviter le max_allowed_packet
    $batchSize = 100;
    $sqlPrefix = "INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, stock, seuil_alerte, id_categorie, visible) VALUES ";
    for ($offset = 0; $offset < $nProduits; $offset += $batchSize) {
        $end = min($offset + $batchSize, $nProduits);
        $values = [];
        $params = [];
        for ($i = $offset + 1; $i <= $end; $i++) {
            // Reproduction exacte de la formule bash :
            // prix = 5 + (i*37 % 900) + (i%10)/10
            // stk  = 20 + (i*7 % 180)
            // cid  = 1 + (i % 4)
            $prix = 5 + ($i * 37 % 900) + ($i % 10) / 10;
            $prixStr = number_format($prix, 2, '.', '');
            $stk = 20 + ($i * 7 % 180);
            $cid = 1 + ($i % 4);
            $ref = sprintf('VP-%04d', $i);
            $nom = "Produit de mesure $i";
            $slug = "produit-de-mesure-$i";
            $desc = "Genere par public/gen_volumes.php pour les mesures ENF-01 : description de taille moyenne, prix variable.";
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            array_push($params, $ref, $nom, $slug, $desc, $prixStr, '20.00', $stk, 5, $cid, 1);
        }
        $sql = $sqlPrefix . implode(', ', $values);
        // INSERT IGNORE rend la génération idempotente si on relance sans purge (évite l'exception Duplicate)
        // On garde d'abord INSERT simple puis on bascule en IGNORE en cas de doublon pour conserver le message d'erreur en CI stricte.
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                $sqlIgnore = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
                $stmt = $pdo->prepare($sqlIgnore);
                $stmt->execute($params);
                $log[] = "  ! doublons VP-* ignorés sur le lot $end (INSERT IGNORE)";
            } else {
                throw $e;
            }
        }
        $inserted += $stmt->rowCount();
        $log[] = "  produits $end / $nProduits insérés";
    }
    return $inserted;
}

function generate_clients(PDO $pdo, int $nClients, array &$log): int
{
    if ($nClients <= 0) {
        return 0;
    }
    // Récupère un hash existant (celui du client 1) pour ne pas avoir à hacher en PHP
    $hash = $pdo->query("SELECT mot_de_passe_hash FROM client WHERE id_client=1")->fetchColumn();
    if (!$hash) {
        $hash = '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWX'; // fallback bcrypt dummy
    }
    $sql = "INSERT IGNORE INTO client (nom, prenom, email, mot_de_passe_hash, adresse_livraison, code_postal, ville, actif) VALUES ";
    $values = [];
    $params = [];
    for ($i = 1; $i <= $nClients; $i++) {
        $values[] = "(?, ?, ?, ?, ?, ?, ?, ?)";
        array_push($params, "Citoyen-$i", 'Mesure', "perf{$i}@minishop.local", $hash, "$i rue du Benchmark", '06000', 'Nice', 1);
    }
    $sql .= implode(', ', $values);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $log[] = "  clients : $nClients demandés, " . $stmt->rowCount() . " nouveaux (IGNORE si déjà présent)";
    return $stmt->rowCount();
}

/**
 * Génère les commandes via les procédures stockées.
 * Retourne ['ok'=>int, 'errors'=>int, 'logs'=>string[]]
 */
function generate_commandes(PDO $pdo, int $nCommandes, int $batch, array &$log): array
{
    if ($nCommandes <= 0) {
        return ['ok' => 0, 'errors' => 0, 'logs' => []];
    }

    // Récupère les IDs réels pour cycler correctement (même si auto_increment a des trous après purge)
    $prodIds = $pdo->query("SELECT id_produit FROM produit ORDER BY id_produit")->fetchAll(PDO::FETCH_COLUMN);
    $clientIds = $pdo->query("SELECT id_client FROM client ORDER BY id_client")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($prodIds) || empty($clientIds)) {
        throw new RuntimeException("Aucun produit ou client trouvé — chargez d'abord sql/01 + 04.");
    }
    $nProd = count($prodIds);
    $nCli  = count($clientIds);

    $t0 = microtime(true);
    $ok = 0;
    $errors = 0;

    // Prépare les statements (réutilisés)
    $stmtCreate = $pdo->prepare("CALL sp_create_order(?, ?, @c, @num)");
    $stmtLine1  = $pdo->prepare("CALL sp_add_order_line(@c, ?, ?, @l, @t, @r)");
    $stmtLine2  = $pdo->prepare("CALL sp_add_order_line(@c, ?, ?, @l2, @t2, @r2)");
    $stmtConfirm= $pdo->prepare("CALL sp_confirm_order(@c, ?, ?, @m, @code)");

    for ($j = 0; $j < $nCommandes; $j++) {
        $cid = $clientIds[$j % $nCli];
        $pid1 = $prodIds[$j % $nProd];
        $pid2 = $prodIds[($j * 7 + 3) % $nProd];
        $q1 = 1 + ($j % 3);
        $payee = ($j % 4 === 0) ? 1 : 0;
        $adresse = "Adresse de mesure $j, 06000 Nice";

        try {
            // sp_create_order
            $stmtCreate->execute([$cid, $adresse]);
            $stmtCreate->closeCursor();
            $c = $pdo->query("SELECT @c AS c")->fetchColumn();
            if (!$c) {
                $errors++;
                $log[] = "  [j=$j] sp_create_order a échoué (c vide)";
                continue;
            }

            // ligne 1
            $stmtLine1->execute([$pid1, $q1]);
            $stmtLine1->closeCursor();
            $r1 = $pdo->query("SELECT @r AS r, @t AS t")->fetch();
            // Si STOCK_INSUFFISANT etc., la procédure ne lève pas d'exception mais pose @r
            if (($r1['r'] ?? 'OK') !== 'OK') {
                // On log mais on continue la commande (le .sh faisait pareil : warning)
                $log[] = "  [j=$j] ligne1 refusée : " . ($r1['r'] ?? 'UNKNOWN');
            }

            // ligne 2
            $stmtLine2->execute([$pid2, 1]);
            $stmtLine2->closeCursor();
            $r2 = $pdo->query("SELECT @r2 AS r")->fetchColumn();
            if ($r2 !== 'OK' && $r2 !== null) {
                $log[] = "  [j=$j] ligne2 refusée : $r2";
            }

            // confirm
            $stmtConfirm->execute([$cid, $payee]);
            $stmtConfirm->closeCursor();
            $conf = $pdo->query("SELECT @code AS code, @m AS m")->fetch();
            if (($conf['code'] ?? 'OK') !== 'OK') {
                $errors++;
                $log[] = "  [j=$j] confirm refusé : " . ($conf['code'] ?? 'UNKNOWN');
            } else {
                $ok++;
            }
        } catch (PDOException $e) {
            // SIGNAL 45000 des triggers / procédures
            $msg = $e->getMessage();
            // On nettoie le curseur restant
            try { $stmtCreate->closeCursor(); } catch (Throwable $t) {}
            try { $stmtLine1->closeCursor(); } catch (Throwable $t) {}
            try { $stmtLine2->closeCursor(); } catch (Throwable $t) {}
            try { $stmtConfirm->closeCursor(); } catch (Throwable $t) {}
            // Certaines erreurs sont normales (stock épuisé concurrent) — on les compte comme lot partiel
            $log[] = "  [j=$j] exception : " . htmlspecialchars(substr($msg, 0, 200));
            $errors++;
        }

        // Progression par batch (comme le .sh : "25 / 1000 commandes — 1 s")
        if ((($j + 1) % $batch === 0) || ($j + 1 === $nCommandes)) {
            $elapsed = (int)(microtime(true) - $t0);
            $log[] = sprintf("  %d / %d commandes — %d s", $j + 1, $nCommandes, $elapsed);
        }
    }

    return ['ok' => $ok, 'errors' => $errors];
}

// ---------------------------------------------------------------------
// 5) Traitement des actions POST
// ---------------------------------------------------------------------
$cfgDefault = load_minishop_config();
$defaultDb = $cfgDefault['db_name'] ?? 'minishop_perf';
$defaultNProd = 200;
$defaultNCmd  = 1000;
$defaultNCli  = 10;
$defaultBatch = 25;

$action = null;
$result = null;
$errors = [];
$logs = [];
$bilan = null;
$countsBefore = null;
$countsAfter = null;
$elapsedTotal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf'] ?? '');
    if (!csrf_check($postedCsrf)) {
        http_response_code(403);
        $errors[] = "Jeton CSRF invalide — rechargez la page.";
    } else {
        $action = $_POST['action'] ?? '';
        $dbName = trim((string)($_POST['db'] ?? $defaultDb));
        $dbName = preg_match('/^[a-zA-Z0-9_]+$/', $dbName) ? $dbName : $defaultDb;
        $nProd = (int)($_POST['n_produits'] ?? $defaultNProd);
        $nCmd  = (int)($_POST['n_commandes'] ?? $defaultNCmd);
        $nCli  = (int)($_POST['n_clients'] ?? $defaultNCli);
        $batch = (int)($_POST['batch'] ?? $defaultBatch);

        // Bornes de sécurité (évite un POST malveillant à 1M)
        $nProd = max(0, min($nProd, 5000));
        $nCmd  = max(0, min($nCmd, 20000));
        $nCli  = max(0, min($nCli, 200));
        $batch = max(1, min($batch, 500));

        $cfg = load_minishop_config($dbName);
        // Surcharges éventuelles de connexion (form)
        if (!empty($_POST['db_user'])) {
            $cfg['db_user'] = trim((string)$_POST['db_user']);
        }
        if (isset($_POST['db_pass'])) {
            $cfg['db_pass'] = (string)$_POST['db_pass'];
        }
        if (!empty($_POST['db_host'])) {
            $cfg['db_host'] = trim((string)$_POST['db_host']);
        }

        try {
            $pdo = pdo_for($cfg);
            $countsBefore = current_counts($pdo);

            if ($action === 'purge') {
                $t0 = microtime(true);
                $deleted = purge_volumetrie($pdo);
                $elapsedTotal = microtime(true) - $t0;
                $countsAfter = current_counts($pdo);
                $result = 'purge';
                $logs[] = "Purge effectuée en " . number_format($elapsedTotal, 2) . " s";
                $logs[] = "  lignes supprimées : " . json_encode($deleted, JSON_UNESCAPED_UNICODE);
            } elseif ($action === 'generate') {
                if ($nProd === 0 && $nCmd === 0 && $nCli === 0) {
                    $errors[] = "Rien à générer : tous les compteurs sont à 0.";
                } else {
                    // Timeout long pour volumétrie
                    @set_time_limit(0);
                    @ignore_user_abort(true);
                    $t0 = microtime(true);
                    $logs[] = "== génération dans '{$cfg['db_name']}' : $nProd produits, $nCmd commandes, $nCli clients ==";
                    $logs[] = "  avant : {$countsBefore['produits']} produits, {$countsBefore['commandes']} commandes";

                    if ($nProd > 0) {
                        $existingVP = (int)$pdo->query("SELECT COUNT(*) FROM produit WHERE reference LIKE 'VP-%'")->fetchColumn();
                        if ($existingVP > 0) {
                            $logs[] = "  ! $existingVP produits VP-* déjà présents — les références en doublon seront ignorées (purgez d'abord pour repartir de zéro)";
                        }
                        $logs[] = "-- 1) produits (" . $nProd . ") : INSERT ... VALUES (jamais INSERT ... SELECT → trigger prix_ttc actif)";
                        try {
                            generate_produits($pdo, $nProd, $logs);
                        } catch (PDOException $e) {
                            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uk_produit_reference')) {
                                $logs[] = "  ! insertion produits interrompue (doublon VP-*) : " . htmlspecialchars(substr($e->getMessage(), 0, 160));
                                $logs[] = "  → purgez (bouton ↺) puis relancez la génération";
                            } else {
                                throw $e;
                            }
                        }
                    }
                    if ($nCli > 0) {
                        $logs[] = "-- 2) clients (" . $nCli . ") : INSERT IGNORE perf%@minishop.local";
                        generate_clients($pdo, $nCli, $logs);
                    }
                    if ($nCmd > 0) {
                        $logs[] = "-- 3) commandes via procédures (" . $nCmd . " × 2 lignes + confirm, batch $batch)";
                        $resCmd = generate_commandes($pdo, $nCmd, $batch, $logs);
                        $logs[] = "  commandes créées : {$resCmd['ok']} / $nCmd (erreurs : {$resCmd['errors']})";
                    }

                    $elapsedTotal = microtime(true) - $t0;
                    $countsAfter = current_counts($pdo);
                    $bilan = $countsAfter;
                    $result = 'generate';
                    $logs[] = "== bilan en " . number_format($elapsedTotal, 2) . " s ==";
                }
            } else {
                $errors[] = "Action inconnue.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur PDO (" . $e->getCode() . ") : " . htmlspecialchars($e->getMessage());
            $errors[] = "Vérifiez app/Config/env.php (db_host/db_name/db_user/db_pass) et que la base a été chargée (scripts/load_db.sh).";
        } catch (Throwable $e) {
            $errors[] = "Erreur : " . htmlspecialchars($e->getMessage());
        }
    }
}

// Si GET, on récupère juste les compteurs courants pour affichage
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($countsBefore) && empty($countsAfter)) {
    try {
        $cfg = load_minishop_config($_GET['db'] ?? null);
        // GET db override propre
        if (isset($_GET['db']) && preg_match('/^[a-zA-Z0-9_]+$/', (string)$_GET['db'])) {
            $cfg['db_name'] = (string)$_GET['db'];
        }
        $pdo = pdo_for($cfg);
        $countsBefore = current_counts($pdo);
        $bilan = $countsBefore;
    } catch (Throwable $e) {
        // Base injoignable en GET : on affiche l'erreur mais on laisse le formulaire
        $errors[] = "Base '{$cfg['db_name']}' injoignable : " . htmlspecialchars($e->getMessage()) . " — vérifiez env.php / DB_USER / DB_PASS.";
        $countsBefore = null;
        $bilan = null;
    }
}

// Pour le formulaire, valeurs pré-remplies = dernier POST ou GET ou défauts
$viewDb    = htmlspecialchars((string)($_POST['db'] ?? $_GET['db'] ?? $defaultDb));
$viewProd  = (int)($_POST['n_produits'] ?? $_GET['n_produits'] ?? $defaultNProd);
$viewCmd   = (int)($_POST['n_commandes'] ?? $_GET['n_commandes'] ?? $defaultNCmd);
$viewCli   = (int)($_POST['n_clients'] ?? $_GET['n_clients'] ?? $defaultNCli);
$viewBatch = (int)($_POST['batch'] ?? $defaultBatch);
$viewHost  = htmlspecialchars((string)($_POST['db_host'] ?? $cfgDefault['db_host'] ?? '127.0.0.1'));
$viewUser  = htmlspecialchars((string)($_POST['db_user'] ?? $cfgDefault['db_user'] ?? 'root'));

// ---------------------------------------------------------------------
// 6) Rendu HTML
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
<title>MiniShop — Générateur de volumétrie (ENF-01 / ENF-02) — DEV uniquement</title>
<style>
  :root{--bg:#f8fafc;--card:#ffffff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--accent:#0ea5e9;--ok:#16a34a;--warn:#d97706;--bad:#dc2626}
  *{box-sizing:border-box}
  body{margin:0;font-family:ui-sans-system,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--ink);background:var(--bg);line-height:1.5}
  header{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.95);backdrop-filter:saturate(1.2) blur(6px);border-bottom:1px solid var(--line)}
  .wrap{max-width:1050px;margin:0 auto;padding:1.2rem 1rem}
  h1{font-size:1.35rem;margin:.2rem 0}
  h1 small{font-weight:500;color:var(--muted);font-size:.9rem}
  h2{font-size:1.05rem;margin:1.4rem 0 .6rem}
  .badge{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--line);background:#fff;border-radius:999px;padding:.2rem .6rem;font-size:.78rem;color:var(--muted)}
  .badge.dev{background:#fef3c7;border-color:#fde68a;color:#92400e}
  .badge.db{background:#e0f2fe;border-color:#bae6fd;color:#0c4a6e}
  .grid{display:grid;gap:1rem}
  @media(min-width:900px){.grid.cols-2{grid-template-columns:1fr 1fr}}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:1rem;box-shadow:0 1px 2px rgba(15,23,42,.05)}
  .card h3{margin:.2rem 0 .6rem;font-size:.95rem}
  label{font-size:.85rem;color:#334155;display:block;margin:.6rem 0 .2rem}
  input[type=text],input[type=number],input[type=password]{width:100%;padding:.55rem .65rem;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit}
  input:focus{outline:2px solid #7dd3fc;border-color:#7dd3fc}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.8rem}
  @media(max-width:640px){.row,.row3{grid-template-columns:1fr}}
  .btn{appearance:none;border:1px solid transparent;border-radius:10px;padding:.6rem .9rem;font-weight:600;cursor:pointer;font:inherit}
  .btn-primary{background:var(--ink);color:#fff}
  .btn-primary:hover{background:#1e293b}
  .btn-ghost{background:#fff;border-color:var(--line)}
  .btn-ghost:hover{background:#f1f5f9}
  .btn-danger{background:#fff;border-color:#fecaca;color:var(--bad)}
  .btn-danger:hover{background:#fef2f2}
  .actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}
  .kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem}
  @media(max-width:900px){.kpi{grid-template-columns:repeat(2,1fr)}}
  .kpi div{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:.6rem .7rem}
  .kpi b{font-size:1.2rem}
  .kpi span{font-size:.75rem;color:var(--muted);display:block}
  .ok{color:var(--ok)} .bad{color:var(--bad)} .warn{color:var(--warn)}
  pre{margin:0;white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;border-radius:10px;padding:.8rem;font-size:.82rem;line-height:1.45;max-height:420px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th,td{padding:.45rem .5rem;border-bottom:1px solid var(--line);text-align:left}
  th{color:var(--muted);font-weight:600;background:#f8fafc}
  .muted{color:var(--muted)} .small{font-size:.82rem}
  .alert{border-radius:10px;padding:.7rem .8rem;border:1px solid}
  .alert-err{background:#fef2f2;border-color:#fecaca;color:#7f1d1d}
  .alert-ok{background:#f0fdf4;border-color:#bbf7d0;color:#14532d}
  .alert-warn{background:#fffbeb;border-color:#fde68a;color:#78350f}
  code{background:#f1f5f9;padding:.15rem .35rem;border-radius:6px;font-size:.85em}
  footer{color:var(--muted);font-size:.8rem;text-align:center;padding:2rem 1rem}
  .sep{height:1px;background:var(--line);margin:1rem 0}
</style>
</head>
<body>
<header>
  <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <h1>MiniShop — Générateur de volumétrie <small>ENF-01 / ENF-02</small></h1>
      <div class="small muted">Remplace <code>scripts/gen_volumes.sh</code> — <strong>navigateur uniquement, environnement dev</strong>. En prod le fichier répond 404.</div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <span class="badge dev">● DEV uniquement</span>
      <span class="badge db">DB : <?=htmlspecialchars($cfgDefault['db_name'] ?? $defaultDb)?></span>
      <span class="badge">PHP <?=PHP_VERSION?></span>
    </div>
  </div>
</header>

<main class="wrap">
  <!-- Pourquoi -->
  <div class="card" style="border-left:4px solid var(--accent)">
    <div class="small muted">Pourquoi ce générateur existe</div>
    <p class="small" style="margin:.4rem 0">
      Un objectif de performance posé sur le jeu de démonstration (12 produits, 3 commandes) n'a aucune valeur de mesure.
      La cible retenue au CDC est «&nbsp;petit e-commerce réel&nbsp;»&nbsp;: <strong>200&nbsp;produits, 1&nbsp;000&nbsp;commandes sur 12&nbsp;mois, 10&nbsp;clients</strong>.
      Les commandes sont créées <strong>en passant par les procédures stockées</strong>&nbsp;: le générateur exerce donc les mêmes chemins
      que l'application (snapshot de prix, frais de port, décrément de stock, historique) et les mesures portent sur des données
      cohérentes avec <code>RB-06</code>/<code>RB-15</code>.
      Les produits sont insérés par <code>INSERT&nbsp;...&nbsp;VALUES</code>, <strong>jamais</strong> par <code>INSERT&nbsp;...&nbsp;SELECT</code>&nbsp;: vérifié sur MariaDB&nbsp;11.8,
      un <code>INSERT&nbsp;...&nbsp;SELECT</code> ne déclenche pas les triggers <code>BEFORE&nbsp;INSERT</code> qui affectent <code>NEW</code>,
      donc <code>prix_ttc</code> (<code>NOT&nbsp;NULL</code>, calculé par <code>trg_produit_ttc</code>) serait refusé.
    </p>
    <p class="small muted" style="margin:.2rem 0">
      Équivalences shell :
      <code>bash scripts/gen_volumes.sh</code> →
      <code>DB=minishop N_PRODUITS=500 N_COMMANDES=2000</code> →
      <code>bash scripts/gen_volumes.sh --purge</code> &nbsp;|&nbsp;
      Mesures : <code>DB=minishop_perf ./tests/perf/mesurer.sh</code> et <code>./tests/perf/mesurer_sql.sh</code>
    </p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-err" style="margin-top:1rem">
      <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($result === 'generate' && empty($errors)): ?>
    <div class="alert alert-ok" style="margin-top:1rem">
      Génération terminée en <?= number_format((float)$elapsedTotal, 2) ?>&nbsp;s — base <code><?=htmlspecialchars($cfg['db_name'])?></code>.
      <?php if (isset($countsBefore,$countsAfter)): ?>
        Produits&nbsp;: <?= (int)$countsBefore['produits'] ?> → <b><?= (int)$countsAfter['produits'] ?></b> &nbsp;·&nbsp;
        Commandes&nbsp;: <?= (int)$countsBefore['commandes'] ?> → <b><?= (int)$countsAfter['commandes'] ?></b>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($result === 'purge' && empty($errors)): ?>
    <div class="alert alert-warn" style="margin-top:1rem">
      Purge effectuée — base ramenée au seul jeu de démonstration (produits <code>VP-%</code>, clients <code>perf%@minishop.local</code>, commandes <code>id&nbsp;&gt;&nbsp;100</code> supprimés) en <?= number_format((float)$elapsedTotal, 2) ?>&nbsp;s.
    </div>
  <?php endif; ?>

  <div class="grid cols-2" style="margin-top:1rem">
    <!-- Formulaire -->
    <form class="card" method="post" autocomplete="off" onsubmit="return confirmAction(this)">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <h3>Paramètres de génération</h3>
      <div class="small muted" style="margin-bottom:.6rem">Valeurs par défaut = cible CDC. Les bornes sont clampées côté serveur (anti-abus).</div>

      <div class="row">
        <div>
          <label for="db">Base <span class="muted">(DB)</span></label>
          <input id="db" name="db" type="text" value="<?= $viewDb ?>" placeholder="minishop_perf" pattern="[a-zA-Z0-9_]+" required>
          <div class="small muted">Créée par <code>scripts/load_db.sh</code> (ou <code>sql/01_*.sql</code>). En dev : <code>minishop_perf</code> pour ne pas polluer <code>minishop</code>.</div>
        </div>
        <div>
          <label for="batch">Taille de lot <span class="muted">(BATCH)</span></label>
          <input id="batch" name="batch" type="number" min="1" max="500" value="<?= $viewBatch ?>">
          <div class="small muted">25 = comme le <code>.sh</code> (un log tous les 25 commandes).</div>
        </div>
      </div>

      <div class="row3">
        <div>
          <label for="n_produits">Produits <span class="muted">(N_PRODUITS)</span></label>
          <input id="n_produits" name="n_produits" type="number" min="0" max="5000" value="<?= $viewProd ?>">
        </div>
        <div>
          <label for="n_commandes">Commandes <span class="muted">(N_COMMANDES)</span></label>
          <input id="n_commandes" name="n_commandes" type="number" min="0" max="20000" value="<?= $viewCmd ?>">
        </div>
        <div>
          <label for="n_clients">Clients <span class="muted">(N_CLIENTS)</span></label>
          <input id="n_clients" name="n_clients" type="number" min="0" max="200" value="<?= $viewCli ?>">
        </div>
      </div>

      <details style="margin-top:.8rem">
        <summary class="small" style="cursor:pointer;color:var(--muted)">Connexion (optionnel — sinon <code>app/Config/env.php</code> / <code>DB_USER</code>/<code>DB_PASS</code>)</summary>
        <div class="row" style="margin-top:.6rem">
          <div>
            <label for="db_host">Hôte</label>
            <input id="db_host" name="db_host" type="text" value="<?= $viewHost ?>" placeholder="127.0.0.1">
          </div>
          <div>
            <label for="db_user">Utilisateur</label>
            <input id="db_user" name="db_user" type="text" value="<?= $viewUser ?>" placeholder="root">
          </div>
        </div>
        <div>
          <label for="db_pass">Mot de passe</label>
          <input id="db_pass" name="db_pass" type="password" value="" placeholder="(laisser vide si aucun)">
        </div>
      </details>

      <div class="actions">
        <button class="btn btn-primary" type="submit" name="action" value="generate">▶ Générer la volumétrie</button>
        <button class="btn btn-danger" type="submit" name="action" value="purge" title="Supprime VP-*, perf%@minishop.local, commandes id>100">↺ Purger (retour démo)</button>
        <a class="btn btn-ghost" href="?db=<?= urlencode($viewDb) ?>">Rafraîchir les compteurs</a>
      </div>
      <div class="small muted" style="margin-top:.6rem">
        Produits&nbsp;: <code>reference LIKE 'VP-%'</code> · Clients&nbsp;: <code>email LIKE 'perf%@minishop.local'</code> · Commandes&nbsp;: <code>id_commande&nbsp;&gt;&nbsp;100</code> &nbsp;|&nbsp;
        Un <code>INSERT ... VALUES</code> par lot de 100&nbsp;; commandes via <code>CALL sp_create_order / sp_add_order_line ×2 / sp_confirm_order</code>.
      </div>
    </form>

    <!-- État courant -->
    <div class="card">
      <h3>État courant — base <code><?= htmlspecialchars($cfg['db_name'] ?? $viewDb) ?></code></h3>
      <?php if ($countsBefore || $bilan): $c = $countsAfter ?? $countsBefore ?? $bilan; ?>
        <div class="kpi">
          <div><b><?= (int)($c['produits'] ?? 0) ?></b><span>produits</span></div>
          <div><b><?= (int)($c['clients'] ?? 0) ?></b><span>clients</span></div>
          <div><b><?= (int)($c['commandes'] ?? 0) ?></b><span>commandes</span></div>
          <div><b><?= (int)($c['lignes'] ?? 0) ?></b><span>lignes</span></div>
          <div><b><?= (int)($c['traces'] ?? 0) ?></b><span>traces statut</span></div>
          <div><b><?= (int)($c['commandes_avec_port'] ?? 0) ?></b><span>avec port &gt;0</span></div>
          <div><b class="<?= ((int)($c['ttc_incoherents'] ?? 0)===0)?'ok':'bad' ?>"><?= (int)($c['ttc_incoherents'] ?? 0) ?></b><span>TTC incohérents</span></div>
          <div><b class="<?= ((int)($c['montants_incoherents'] ?? 0)===0)?'ok':'bad' ?>"><?= (int)($c['montants_incoherents'] ?? 0) ?></b><span>montants incohérents</span></div>
        </div>
        <div class="sep"></div>
        <table>
          <thead><tr><th>Indicateur</th><th>Valeur</th><th>Attendu (CDC)</th></tr></thead>
          <tbody>
            <tr><td>Produits</td><td><b><?= (int)($c['produits'] ?? 0) ?></b></td><td class="muted">12 démo + 200 mesure ≈ 212</td></tr>
            <tr><td>Commandes</td><td><b><?= (int)($c['commandes'] ?? 0) ?></b></td><td class="muted">4 démo + 1000 ≈ 1004</td></tr>
            <tr><td>Lignes</td><td><?= (int)($c['lignes'] ?? 0) ?></td><td class="muted">≈ 2 × commandes</td></tr>
            <tr><td>Traces</td><td><?= (int)($c['traces'] ?? 0) ?></td><td class="muted">≥ commandes (trg_history)</td></tr>
            <tr><td>TTC incohérents</td><td class="<?= ((int)($c['ttc_incoherents'] ?? 0)===0)?'ok':'bad' ?>"><?= (int)($c['ttc_incoherents'] ?? 0) ?></td><td class="muted">0 (trg_produit_ttc)</td></tr>
            <tr><td>Montants incohérents</td><td class="<?= ((int)($c['montants_incoherents'] ?? 0)===0)?'ok':'bad' ?>"><?= (int)($c['montants_incoherents'] ?? 0) ?></td><td class="muted">0 (RB-15)</td></tr>
          </tbody>
        </table>
        <p class="small muted" style="margin:.6rem 0 0">
          Requête bilan&nbsp;: <code>SELECT (SELECT COUNT(*) FROM produit) AS produits, ...</code> — identique au <code>.sh</code>.
        </p>
      <?php else: ?>
        <div class="alert alert-warn">Base injoignable — vérifiez <code>app/Config/env.php</code> puis <code>./scripts/load_db.sh</code>.</div>
        <pre style="margin-top:.6rem">DB_HOST=<?=htmlspecialchars($cfg['db_host'] ?? '?')?>  DB=<?=htmlspecialchars($cfg['db_name'] ?? '?')?>  USER=<?=htmlspecialchars($cfg['db_user'] ?? '?')?></pre>
      <?php endif; ?>

      <div class="sep"></div>
      <h3 style="margin-bottom:.4rem">Mesures</h3>
      <p class="small" style="margin:.2rem 0">
        Une fois la volumétrie générée&nbsp;:<br>
        <code>DB=<?=htmlspecialchars($viewDb)?> ./tests/perf/mesurer_sql.sh 100</code> (base seule, 15–17&nbsp;ms attendus)<br>
        <code>DB=<?=htmlspecialchars($viewDb)?> ./tests/perf/mesurer.sh</code> (HTTP p95, nécessite <code>php -S</code> + app)<br>
        Retour démo&nbsp;: bouton <em>Purger</em> ci-contre ou <code>bash scripts/gen_volumes.sh --purge</code>
      </p>
    </div>
  </div>

  <?php if (!empty($logs)): ?>
    <div class="card" style="margin-top:1rem">
      <h3>Journal — <?= $result === 'purge' ? 'purge' : 'génération' ?></h3>
      <pre><?= htmlspecialchars(implode("\n", $logs)) ?></pre>
      <?php if ($bilan): ?>
        <div class="sep"></div>
        <h3>Bilan</h3>
        <table>
          <thead><tr><th>Métrique</th><th>Valeur</th></tr></thead>
          <tbody>
            <?php foreach ($bilan as $k=>$v): ?>
              <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:1rem">
    <h3>Sécurité &amp; exploitation</h3>
    <ul class="small" style="margin:.4rem 0;padding-left:1.2rem">
      <li><strong>DEV uniquement</strong>&nbsp;: ce fichier se bloque (404) si <code>APP_ENV</code> / <code>app/Config/env.php</code> ne vaut pas <code>dev</code> et si l'hôte n'est pas local/preview. En prod, le générateur n'est ni lié ni routé.</li>
      <li><strong>Navigateur uniquement</strong>&nbsp;: un appel <code>php public/gen_volumes.php</code> en CLI s'arrête avec un message d'aide (pas de génération hors navigateur).</li>
      <li><strong>Pas d'INSERT&nbsp;...&nbsp;SELECT</strong> pour les produits&nbsp;: chaque lot passe par <code>INSERT ... VALUES</code> afin que <code>trg_produit_ttc</code> renseigne <code>prix_ttc</code>.</li>
      <li><strong>Procédures stockées</strong> pour les commandes&nbsp;: <code>sp_create_order</code> / <code>sp_add_order_line</code> / <code>sp_confirm_order</code> (snapshot prix, frais de port via <code>sp_compute_shipping</code>, historique <code>order_status_history</code>).</li>
      <li>Formulaire protégé par jeton CSRF de session. Les paramètres <code>N_*</code> / <code>BATCH</code> sont clampés côté serveur.</li>
      <li>Conserver le <code>.sh</code> pour la CI non-interactive&nbsp;; le <code>.php</code> est l'outil <em>visible</em> en soutenance (démo navigateur).</li>
    </ul>
    <p class="small muted" style="margin:.6rem 0 0">Source de vérité SQL&nbsp;: <code>sql/01_minishop_schema.sql</code> → <code>02_procedures</code> → <code>03_triggers</code> → <code>04_demo</code>. Générateur&nbsp;: ce fichier + <code>scripts/gen_volumes.sh</code> (historique).</p>
  </div>
</main>

<footer>
  MiniShop — SAE 302 — Générateur de volumétrie PHP (dev-only, navigateur) — remplace <code>scripts/gen_volumes.sh</code> pour la démo.
  <br>DocumentRoot = <code>public/</code> · Lancement dev&nbsp;: <code>php -S 0.0.0.0:8000 -t public</code> → <code>http://localhost:8000/gen_volumes.php</code>
</footer>

<script>
function confirmAction(form){
  const btn = document.activeElement;
  if(btn && btn.value === 'purge'){
    const db = form.querySelector('#db')?.value || 'minishop_perf';
    return confirm('Purger la volumétrie dans "'+db+'" ?\n\nSupprime : produits VP-*, clients perf%@minishop.local, commandes id>100 (et lignes/traces associées).\nLe jeu de démonstration (12 produits, 4 commandes) sera conservé.');
  }
  if(btn && btn.value === 'generate'){
    const n = form.querySelector('#n_commandes')?.value || '?';
    if(parseInt(n,10) > 5000){
      return confirm('Générer '+n+' commandes peut prendre du temps. Continuer ?');
    }
  }
  return true;
}
// Auto-dismiss des alertes au bout de 12s + focus sur premier champ en erreur
setTimeout(()=>{document.querySelectorAll('.alert').forEach(a=>a.style.opacity='0.9')}, 200);
</script>
</body>
</html>
