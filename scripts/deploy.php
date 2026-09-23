#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * MiniShop — scripts/deploy.php
 * -----------------------------------------------------------------------------
 * Prépare une instance de DÉMONSTRATION PUBLIQUE :
 *   1. régénère des HACHES BCRYPT ALÉATOIRES pour les comptes de démo
 *      (les mots de passe connus Demo2026! / Admin2026! restent valables,
 *       mais le hash change à chaque déploiement — un hash divulgué dans un
 *       dump / screenshot ne sert plus rien) ;
 *   2. régénère des EMAILS DE DÉMO UNIQUES (suffixe aléatoire) afin qu'une
 *      instance publique ne tente pas d'envoyer des courriels vers de vraies
 *      adresses (alice@example.com, bruno@example.com, carla@example.com,
 *      admin@minishop.fr) et que deux démos parallèles n'entrent pas en
 *      collision si la base est partagée.
 *
 * Modes d'action (combinables) :
 *   --dry-run              affiche ce qui serait fait, n'écrit rien (par défaut
 *                          si ni --write-sql ni --db n'est fourni)
 *   --write-sql            réécrit sql/01_minishop_schema.sql avec les nouvelles
 *                          valeurs (idempotent : le prochain load_db.sh les
 *                          injectera dans une base neuve)
 *   --db                   applique directement les modifications sur la base
 *                          configurée (via app/Config/env.php ou les variables
 *                          d'environnement DB_HOST / DB_USER / DB_PASS / DB)
 *   --emails=<mode>        comment régénérer les emails :
 *                            demo      (défaut)  demo-<aleatoire>@example.invalid
 *                                                 admin-demo-<aleatoire>@minishop.invalid
 *                            keep               conserve adresses@exactes du schéma
 *                            suffix=<n>         <n> caractères aléatoires en suffixe
 *                                                 local-part (-X…X) mais domaines
 *                                                 d'origine en .invalid
 *   --passwords=<mode>     gestion des mots de passe :
 *                            keep-hash (défaut)  nouveaux hashes pour les mdp de
 *                                                démo connus (Demo2026! / Admin2026!)
 *                            random              mots de passe aléatoires (affichés)
 *                            fixed="<p>:<q>"     client=<p>  admin=<q>
 *   --seed=<s>             graine aléatoire déterministe (reproductibilité de CI)
 *   -h / --help            cette aide
 *
 * Usage typique :
 *   # Déploiement public : emails réécrits, SQL et base mis à jour
 *   php scripts/deploy.php --write-sql --db
 *
 *   # Juste voir ce qui serait changé
 *   php scripts/deploy.php
 *
 *   # CI : graine fixe, vérification reproductible
 *   php scripts/deploy.php --seed=demo2026 --write-sql
 *
 * Le script N'EST PAS un .sh (version bash historique supprimée) : il est
 * conforme au passage « PHP pour les scripts de prod » prévu au lot L15/D1.
 * -----------------------------------------------------------------------------
 */

// ---------- autodéfense ------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — ce script ne s'exécute qu'en CLI.\n";
    exit(1);
}

// ---------- chemins et dépendances ------------------------------------------
const ROOT        = __DIR__ . '/..';
const SCHEMA_SQL  = ROOT . '/sql/01_minishop_schema.sql';
const ENV_FILE    = ROOT . '/app/Config/env.php';

// ---------- parse des arguments ---------------------------------------------
/** @var array<string,string|bool> */
$opts = [
    'dry-run'    => false,
    'write-sql'  => false,
    'db'         => false,
    'emails'     => 'demo',     // demo | keep | suffix=<n>
    'passwords'  => 'keep-hash',// keep-hash | random | fixed="p:a"
    'seed'       => null,
    'help'       => false,
];
$fixedPasswords = ['client' => 'Demo2026!', 'admin' => 'Admin2026!'];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') { $opts['dry-run'] = true; continue; }
    if ($arg === '--write-sql')                { $opts['write-sql'] = true; continue; }
    if ($arg === '--db')                       { $opts['db'] = true; continue; }
    if ($arg === '-h' || $arg === '--help')    { $opts['help'] = true; continue; }
    if (str_starts_with($arg, '--emails='))    { $opts['emails'] = substr($arg, 9); continue; }
    if (str_starts_with($arg, '--passwords=')) {
        $v = substr($arg, 12);
        if (str_starts_with($v, 'fixed=')) {
            $parts = explode(':', substr($v, 6), 2);
            if (count($parts) !== 2) fail("--passwords=fixed=\"<client>:<admin>\" attend deux valeurs séparées par ':'");
            $opts['passwords'] = 'fixed';
            $fixedPasswords = ['client' => $parts[0], 'admin' => $parts[1]];
        } else {
            $opts['passwords'] = $v;
        }
        continue;
    }
    if (str_starts_with($arg, '--seed='))      { $opts['seed'] = substr($arg, 7); continue; }
    fail("option inconnue : $arg (lancer avec --help pour l'usage)");
}

if ($opts['help']) { usage(); exit(0); }

if (!in_array($opts['emails'], ['demo', 'keep'], true) && !str_starts_with($opts['emails'], 'suffix=')) {
    fail("--emails doit valoir 'demo', 'keep' ou 'suffix=<n>'");
}
if (!in_array($opts['passwords'], ['keep-hash', 'random', 'fixed'], true)) {
    fail("--passwords doit valoir 'keep-hash', 'random' ou 'fixed=\"c:a\"'");
}

// Par défaut (rien demandé) → dry-run explicite
if (!$opts['write-sql'] && !$opts['db']) {
    $opts['dry-run'] = true;
}

// ---------- graine aléatoire ------------------------------------------------
if ($opts['seed'] !== null) {
    // Graine déterministe : on utilise mt_srand pour la partie "aléatoire"
    // reproductible, tout en laissant random_bytes pour le sel bcrypt
    // (sinon tous les déploiements avec la même graine auraient le même hash).
    mt_srand(crc32((string) $opts['seed']));
} else {
    mt_srand(random_int(0, PHP_INT_MAX));
}

// ---------- construction des nouvelles valeurs ------------------------------
$rand = fn(int $len = 8): string => substr(strtr(base64_encode(random_bytes(12)), '+/', 'ab'), 0, $len);
$randAlpha = function (int $len = 6): string {
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789'; // sans l/O/0/1 pour la lisibilité
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
};

// Mot de passe des comptes de démo
if ($opts['passwords'] === 'random') {
    $clientPass = $randAlpha(10) . 'A1!';
    $adminPass  = $randAlpha(10) . 'A1!';
} else {
    $clientPass = $fixedPasswords['client'];
    $adminPass  = $fixedPasswords['admin'];
}

$clientHash = password_hash($clientPass, PASSWORD_BCRYPT, ['cost' => 12]);
$adminHash  = password_hash($adminPass,  PASSWORD_BCRYPT, ['cost' => 12]);

if ($clientHash === false || $adminHash === false) {
    fail("password_hash() a échoué");
}
if (strlen($clientHash) !== 60 || strlen($adminHash) !== 60) {
    fail("hash bcrypt inattendu (longueur attendue : 60)");
}

// Vérification rapide (santé)
if (!password_verify($clientPass, $clientHash) || !password_verify($adminPass, $adminHash)) {
    fail("vérification des nouveaux hashes a échoué (bug interne)");
}

// Emails
$suffix = match (true) {
    $opts['emails'] === 'keep'     => null,
    $opts['emails'] === 'demo'     => '-' . $randAlpha(6),
    str_starts_with($opts['emails'], 'suffix=') => '-' . $randAlpha((int) substr($opts['emails'], 7) ?: 6),
    default => null,
};

// Domaines en .invalid (RFC 2606) : aucune boîte réelle ne pourra recevoir
// de courriel, et Postfix/Sendmail les rejetteront immédiatement en dev.
$emails = [
    'alice' => $suffix === null ? 'alice@example.com'   : 'demo-alice' . $suffix . '@example.invalid',
    'bruno' => $suffix === null ? 'bruno@example.com'   : 'demo-bruno' . $suffix . '@example.invalid',
    'carla' => $suffix === null ? 'carla@example.com'   : 'demo-carla' . $suffix . '@example.invalid',
    'admin' => $suffix === null ? 'admin@minishop.fr'   : 'admin-demo' . $suffix . '@minishop.invalid',
];

// ---------- compte rendu ----------------------------------------------------
out("== MiniShop — scripts/deploy.php ==");
out("");
out("Paramètres :");
out("   emails    = {$opts['emails']}");
out("   passwords = {$opts['passwords']}" . ($opts['seed'] !== null ? "   (seed={$opts['seed']})" : ""));
out("   écriture  SQL : " . ($opts['write-sql'] ? 'OUI' : 'non') . "   base directe : " . ($opts['db'] ? 'OUI' : 'non'));
out("");
out("Comptes de démonstration qui seront en place :");
out(sprintf("   [ADMIN ] %-45s  mdp : %s", $emails['admin'], $adminPass));
out(sprintf("   [client] %-45s  mdp : %s", $emails['alice'], $clientPass));
out(sprintf("   [client] %-45s  mdp : %s", $emails['bruno'], $clientPass));
out(sprintf("   [client] %-45s  mdp : %s", $emails['carla'], $clientPass));
out("");
out("(Les 3 clients partagent le même mot de passe ; les hashes diffèrent car");
out(" le sel bcrypt est aléatoire à chaque appel.)");
out("");

if ($opts['dry-run']) {
    out("→ DRY-RUN : aucune écriture n'est réalisée.");
    out("   Ajoutez --write-sql pour mettre à jour sql/01_minishop_schema.sql,");
    out("            --db        pour appliquer sur la base configurée.");
    exit(0);
}

// ---------- mise à jour du fichier SQL --------------------------------------
if ($opts['write-sql']) {
    if (!is_file(SCHEMA_SQL) || !is_writable(SCHEMA_SQL)) {
        fail("impossible d'écrire dans " . SCHEMA_SQL);
    }
    $sql = file_get_contents(SCHEMA_SQL);
    if ($sql === false) fail("lecture de " . SCHEMA_SQL . " impossible");

    // 1) Ligne INSERT administrateur
    //    colonnes : (nom, prenom, email, mot_de_passe_hash, role) → on remplace
    //    les deux champs littéraux qui suivent le couple (Nguyen, Alice).
    $oldAdminRe = "/(\\('Nguyen','Alice',)'[^']*','[^']*'(,'SUPER'\\);)/u";
    $newAdmin  = "${1}'{$emails['admin']}','{$adminHash}'${2}";
    if (!preg_match($oldAdminRe, $sql)) {
        fail("pattern INSERT administrateur non trouvé dans le schéma");
    }
    $sql = preg_replace($oldAdminRe, $newAdmin, $sql, 1, $countAdmin);

    // 2) 3 lignes clients — colonnes : (nom, prenom, email, mot_de_passe_hash,
    //    telephone, adresse_livraison, code_postal, ville). On remplace email
    //    et hash, puis on retombe sur la virgule qui ouvre le téléphone.
    $clients = [
        ['Dupont','Alice', $emails['alice']],
        ['Martin','Bruno', $emails['bruno']],
        ['Moretti','Carla',$emails['carla']],
    ];
    $countClients = 0;
    foreach ($clients as [$nom, $prenom, $newEmail]) {
        $re = "/(\\('" . preg_quote($nom, '/') . "','" . preg_quote($prenom, '/') . "',)'[^']*','[^']*'(,)/u";
        if (!preg_match($re, $sql)) {
            fail("pattern client $prenom $nom non trouvé dans le schéma");
        }
        $sql = preg_replace($re, "${1}'{$newEmail}','{$clientHash}'${2}", $sql, 1, $c);
        $countClients += $c;
    }

    if ($countAdmin !== 1 || $countClients !== 3) {
        fail("substitutions SQL incomplètes (admin=$countAdmin, clients=$countClients)");
    }

    // On met à jour le commentaire au-dessus des INSERT avec l'horodatage
    // (ligne unique pour éviter les soucis d'encodage/retours-ligne entre CRLF/LF).
    $ts = date('Y-m-d H:i:s');
    $sql = preg_replace(
        "{-- \\(mot de passe des comptes de test : Demo2026! pour les clients,\\R--  Admin2026! pour le back-office .+ password_hash\\(\\)\\)\\.}u",
        "-- (mot de passe des comptes de test : {$clientPass} pour les clients,\n--  {$adminPass} pour le back-office — hashes régénérés par scripts/deploy.php le {$ts})",
        $sql,
        1,
        $cComment
    );

    if (file_put_contents(SCHEMA_SQL, $sql) === false) {
        fail("écriture dans " . SCHEMA_SQL . " impossible");
    }
    out("✓ sql/01_minishop_schema.sql mis à jour (admin + 3 clients" . ($cComment ? ', commentaire' : '') . ").");
}

// ---------- application directe en base -------------------------------------
if ($opts['db']) {
    $pdo = connectDb();
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    out("→ Connexion OK — base : {$dbName}");

    $pdo->beginTransaction();
    try {
        // Mise à jour robuste : on retrouve les comptes par (nom, prénom) pour
        // rester idempotent même si les emails ont déjà été régénérés par un
        // précédent passage du script.

        // ADMIN : ('Nguyen','Alice') — rôle SUPER
        $stmt = $pdo->prepare("UPDATE administrateur
                                 SET email = :email, mot_de_passe_hash = :hash
                               WHERE nom = 'Nguyen' AND prenom = 'Alice'");
        $stmt->execute([':email' => $emails['admin'], ':hash' => $adminHash]);
        out("   administrateur(Nguyen Alice) : {$stmt->rowCount()} ligne(s) → " . $emails['admin']);

        $clientMap = [
            ['Dupont','Alice', $emails['alice']],
            ['Martin','Bruno', $emails['bruno']],
            ['Moretti','Carla',$emails['carla']],
        ];
        $stmt = $pdo->prepare("UPDATE client
                                 SET email = :email, mot_de_passe_hash = :hash
                               WHERE nom = :nom AND prenom = :prenom");
        foreach ($clientMap as [$nom, $prenom, $email]) {
            $stmt->execute([
                ':email' => $email,
                ':hash'  => $clientHash,
                ':nom'   => $nom,
                ':prenom'=> $prenom,
            ]);
            out("   client($prenom $nom) : {$stmt->rowCount()} ligne(s) → $email");
        }

        // Sécurité : on neutralise tout autre compte en base qui aurait encore
        // une adresse de démo historique (empêche un compte oublié de rester
        // accessible avec le mot de passe Demo2026! / Admin2026!).
        $leakedPwdHashes = [
            // Hash du mot de passe Demo2026! tels que livrés dans le dépôt
            '$2b$10$ldtyUy7EyQ4YJ0L8qBWZxuTuefzalZmCDOnXNnBHHZNm8cZqOerjO',
            '$2b$10$ASp02qmyg5r3VvXRIV60/ObRO3IDB6bJ7DWgH20FOECvoNtDPNNW2',
        ];
        $in = implode(',', array_fill(0, count($leakedPwdHashes), '?'));
        foreach (['client', 'administrateur'] as $tbl) {
            $neutralise = $pdo->prepare("UPDATE $tbl
                                           SET actif = 0
                                         WHERE mot_de_passe_hash IN ($in)
                                           AND email NOT IN (?, ?, ?, ?)");
            $neutralise->execute(array_merge($leakedPwdHashes, array_values($emails)));
            $n = $neutralise->rowCount();
            if ($n > 0) {
                out("   ⚠ {$n} compte(s) $tbl supplémentaire(s) avec un hash connu désactivé(s).");
            }
        }

        $pdo->commit();
        out("✓ Base mise à jour.");
    } catch (Throwable $e) {
        $pdo->rollBack();
        fail("échec de la mise à jour en base : " . $e->getMessage());
    }
}

out("");
out("Déploiement de démo prêt. Pensez à :");
out("  - ne PAS ré-exécuter sql/04_minishop_demo.sql (crée des commandes de démo)");
out("    sur une instance exposée publiquement (cf. README § « Comptes de démo ») ;");
out("  - passer APP_ENV=prod pour désactiver public/gen_volumes.php.");
exit(0);

// ---------- helpers ---------------------------------------------------------

function out(string $line): void
{
    fwrite(STDOUT, $line . "\n");
}

function fail(string $msg): never
{
    fwrite(STDERR, "ERREUR : $msg\n");
    exit(2);
}

function usage(): void
{
    $me = basename($_SERVER['argv'][0] ?? 'deploy.php');
    out("Usage : php scripts/$me [--dry-run] [--write-sql] [--db]");
    out("              [--emails=demo|keep|suffix=<n>]");
    out("              [--passwords=keep-hash|random|fixed=\"<client>:<admin>\"]");
    out("              [--seed=<graine>] [-h|--help]");
    out("");
    out("Régénère les hashes bcrypt des comptes de démo et leurs adresses email");
    out("afin de préparer une instance de démonstration publique (cf. README).");
}

function connectDb(): PDO
{
    // Charge la config comme Database.php si disponible
    $config = null;
    if (is_file(ENV_FILE)) {
        $cfg = include ENV_FILE;
        if (is_array($cfg)) {
            $config = $cfg;
        }
    }
    $host = $config['db_host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int) ($config['db_port'] ?? getenv('DB_PORT') ?: 3306);
    $name = $config['db_name'] ?? getenv('DB') ?: getenv('DB_NAME') ?: 'minishop';
    $user = $config['db_user'] ?? getenv('DB_USER') ?: 'root';
    $pass = $config['db_pass'] ?? getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        fail("connexion à la base impossible ({$host}:{$port}/{$name}) : " . $e->getMessage());
    }
    return $pdo;
}
