<?php

declare(strict_types=1);

/**
 * MiniShop — installation de la base MySQL/MariaDB en local.
 *
 *   php scripts/db_init.php                 # (re)crée la base locale + démo
 *   php scripts/db_init.php --yes           # sans demander de confirmation
 *   php scripts/db_init.php --no-demo       # schéma + routines, sans jeu de démo
 *   php scripts/db_init.php --keep          # ne touche à rien si tout est déjà là
 *   php scripts/db_init.php --check         # diagnostic seul (n'écrit rien)
 *   php scripts/db_init.php --switch        # écrit STORAGE_DRIVER="sql" dans .env
 *
 * La cible vient du fichier `.env` (modèle `.env.example`, jamais commité) :
 *
 *   DB_HOST="127.0.0.1"  DB_PORT="3306"  DB_NAME="minishop"
 *   DB_USER="root"       DB_PASSWORD=""  STORAGE_DRIVER="json"
 *
 * Cas de root sous Debian/Ubuntu : le compte n'accepte que l'authentification
 * par socket Unix. Rien à faire de particulier — la connexion bascule
 * automatiquement sur la socket locale, ou sur `DB_SOCKET=/run/mysqld/mysqld.sock`
 * si elle est ailleurs.
 *
 * Une variable d'environnement réelle gagne sur le fichier :
 *   DB_NAME=minishop_test php scripts/db_init.php --yes
 *
 * Codes de retour : 0 succès · 1 échec (serveur, chargement, vérification) ·
 *                   2 configuration invalide · 3 abandon demandé.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Config\Database;
use App\Config\DbConnexion;
use App\Tools\DbInstaller;
use App\Tools\EnvFile;

const LARGEUR = 74;

/**
 * Écrit sur la sortie d'erreur.
 *
 * La constante STDERR n'existe que sous le SAPI CLI : le runtime PHP-WASM
 * utilisé pour les tests de la sandbox ne la définit pas. On retombe alors sur
 * la sortie standard — le message reste affiché, seul le canal change.
 */
function erreur(string $texte): void
{
    if (defined('STDERR')) {
        fwrite(STDERR, $texte);

        return;
    }

    echo $texte;
}

/** Longueur d'une chaîne, avec ou sans l'extension mbstring. */
function longueur(string $texte): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texte);
    }

    return strlen($texte);
}

function titre(string $texte): void
{
    echo "\n" . $texte . "\n" . str_repeat('-', min(LARGEUR, max(20, longueur($texte)))) . "\n";
}

function pastille(bool $valeur): string
{
    if ($valeur) {
        return '✅';
    }

    return '❌';
}

/** Lit une confirmation sur l'entrée standard (refus par défaut si non interactive). */
function confirmer(string $question, bool $force): bool
{
    if ($force) {
        return true;
    }

    $interactif = function_exists('stream_isatty') && stream_isatty(STDIN);
    if (!$interactif) {
        echo "   (entrée non interactive : relancez avec --yes pour confirmer)\n";

        return false;
    }

    echo $question . ' [o/N] ';
    $reponse = trim((string) fgets(STDIN));

    return in_array(strtolower($reponse), ['o', 'oui', 'y', 'yes'], true);
}

/** @param list<array{controle:string, ok:bool, detail:string}> $controles */
function afficherControles(array $controles): bool
{
    $toutBon = true;
    foreach ($controles as $controle) {
        if (!$controle['ok']) {
            $toutBon = false;
        }
        printf("   %s %-26s %s\n", pastille($controle['ok']), $controle['controle'], $controle['detail']);
    }

    return $toutBon;
}

// --------------------------------------------------------------- arguments
$options = [
    'check' => false,
    'keep' => false,
    'demo' => true,
    'yes' => false,
    'switch' => false,
    'help' => false,
];

$argumentsBruts = [];
if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
    $argumentsBruts = array_slice($_SERVER['argv'], 1);
}

foreach ($argumentsBruts as $argument) {
    switch ($argument) {
        case '--check':
            $options['check'] = true;
            break;
        case '--keep':
            $options['keep'] = true;
            break;
        case '--no-demo':
            $options['demo'] = false;
            break;
        case '--yes':
        case '-y':
            $options['yes'] = true;
            break;
        case '--switch':
            $options['switch'] = true;
            break;
        case '--help':
        case '-h':
            $options['help'] = true;
            break;
        default:
            erreur('✘ option inconnue : ' . $argument . "\n");
            erreur("  options : --check --keep --no-demo --yes --switch --help\n");
            exit(2);
    }
}

if ($options['help']) {
    echo <<<'TXT'
MiniShop — base de données locale

  php scripts/db_init.php               (re)crée la base + le jeu de démonstration
  php scripts/db_init.php --check       diagnostic seul (aucune écriture)
  php scripts/db_init.php --keep        ne fait rien si tout est déjà en place
  php scripts/db_init.php --no-demo     schéma, procédures, triggers — sans données
  php scripts/db_init.php --yes         ne demande pas de confirmation (DESTRUCTIF)
  php scripts/db_init.php --switch      bascule .env sur STORAGE_DRIVER="sql"

Configuration : .env à la racine (modèle .env.example).
Variables lues : DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.

TXT;
    exit(0);
}

// ----------------------------------------------------------- configuration
$config = Database::config();
$installateur = DbInstaller::depuisConfiguration();

echo "== MiniShop — base de données locale ==\n";
$sources = Database::sources();
$listeSources = 'aucune (valeurs par défaut)';
if ($sources !== []) {
    $listeSources = implode(', ', $sources);
}
echo '   configuration : ' . $listeSources . "\n";
echo '   cible         : ' . $installateur->cible() . ' (' . $installateur->utilisateur() . ")\n";
echo '   pilote app    : ' . (string) ($config['storage_driver'] ?? 'json') . "  (STORAGE_DRIVER)\n";

$socketDetectee = DbConnexion::socketConfiguree();
if ($socketDetectee === null) {
    $socketDetectee = DbConnexion::socketLocale();
}
if ($socketDetectee !== null) {
    echo '   socket Unix   : ' . $socketDetectee . "  (repli si le compte refuse le TCP)\n";
}

if (!$installateur->baseValide()) {
    erreur("\n✘ nom de base invalide : « " . $installateur->base() . " »\n");
    erreur("  lettres, chiffres et _ uniquement (DB_NAME dans .env)\n");
    exit(2);
}

// ------------------------------------------------------------- diagnostic
if ($options['check']) {
    titre('Diagnostic');
    $controles = $installateur->diagnostiquer();
    $toutBon = afficherControles($controles);

    if (!$installateur->serveurJoignable()) {
        echo "\n   Remèdes :\n";
        echo "     • le service tourne-t-il ?   systemctl status mariadb\n";
        echo "     • compte applicatif créé ?   sudo mariadb  puis CREATE USER + GRANT\n";
        echo "     • identifiants justes ?      DB_USER / DB_PASSWORD dans .env\n";
        echo "     • socket ailleurs ?          DB_SOCKET=/run/mysqld/mysqld.sock dans .env\n";
    }

    $inventaire = $installateur->inventaire();
    if (($inventaire['tables'] ?? -1) >= 0) {
        titre('Contenu');
        printf("   tables %d · vues %d · procédures %d · fonctions %d · triggers %d\n",
            $inventaire['tables'], $inventaire['vues'], $inventaire['procedures'],
            $inventaire['fonctions'], $inventaire['triggers']);
        printf("   produits %d · clients %d · commandes %d\n",
            $inventaire['produits'], $inventaire['clients'], $inventaire['commandes']);
    }

    echo "\n";
    if ($toutBon) {
        echo "✓ base prête — lancez ./run.sh\n";
        exit(0);
    }

    echo "✘ base incomplète — lancez : php scripts/db_init.php --yes\n";
    exit(1);
}

// --------------------------------------------------------- accès au serveur
if (!$installateur->serveurJoignable()) {
    erreur("\n✘ serveur MySQL injoignable sur " . $installateur->hote() . ':' . $installateur->port() . "\n");
    erreur("  Démarrez le service :\n");
    erreur("    Linux   : sudo systemctl start mysql    (ou mariadb)\n");
    erreur("    macOS   : brew services start mysql\n");
    erreur("    Windows : net start MySQL80\n");
    erreur("  Vérifiez ensuite DB_USER / DB_PASSWORD dans .env (fichier .env.example).\n");
    exit(1);
}

// -------------------------------------------------------------- base déjà là
$existe = $installateur->baseExiste();

if ($existe && $options['keep']) {
    $controles = $installateur->verifier($installateur->inventaire(), $options['demo']);
    if (afficherControles($controles)) {
        echo "\n✓ base déjà installée (--keep) — rien à faire.\n";
        exit(0);
    }
    erreur("\n✘ base incomplète : relancez sans --keep pour la reconstruire.\n");
    exit(1);
}

if ($existe && !$options['keep']) {
    echo "\n⚠ la base « " . $installateur->base() . " » existe déjà et sera SUPPRIMÉE.\n";
    if (!confirmer('   Continuer ?', $options['yes'])) {
        echo "abandon — rien n'a été modifié.\n";
        exit(3);
    }
}

// --------------------------------------------------------------- création
titre('Création');
if ($existe) {
    $installateur->supprimerBase();
    echo "   base supprimée\n";
}
$installateur->creerBase();
echo "   base « " . $installateur->base() . " » créée (utf8mb4_unicode_ci)\n";

titre('Chargement');
$fichiers = DbInstaller::scripts($options['demo']);
try {
    $installateur->charger($fichiers, static function (string $fichier, int $nombre): void {
        printf("   %-32s %3d instruction(s)\n", $fichier, $nombre);
    });
} catch (Throwable $erreur) {
    erreur("\n✘ échec du chargement : " . $erreur->getMessage() . "\n");
    exit(1);
}

// -------------------------------------------------------------- vérification
titre('Vérification');
$inventaire = $installateur->inventaire();
$controles = $installateur->verifier($inventaire, $options['demo']);
$toutBon = afficherControles($controles);

printf("\n   tables %d · vues %d · procédures %d · fonctions %d · triggers %d\n",
    $inventaire['tables'], $inventaire['vues'], $inventaire['procedures'],
    $inventaire['fonctions'], $inventaire['triggers']);
printf("   produits %d · clients %d · commandes %d\n",
    $inventaire['produits'], $inventaire['clients'], $inventaire['commandes']);

// ------------------------------------------------------------------- .env
if ($options['switch']) {
    titre('Configuration .env');
    $cree = EnvFile::initialiser();
    if ($cree) {
        echo '   ' . EnvFile::chemin() . " créé depuis .env.example\n";
    }
    $paires = [
        'STORAGE_DRIVER' => 'sql',
        'DB_HOST' => $installateur->hote(),
        'DB_PORT' => $installateur->port(),
        'DB_NAME' => $installateur->base(),
        'DB_USER' => $installateur->utilisateur(),
    ];
    $modifiees = EnvFile::definirPlusieurs($paires);
    if ($modifiees === []) {
        echo "   déjà à jour\n";
    } else {
        echo '   mis à jour : ' . implode(', ', $modifiees) . "\n";
    }
    echo '   ' . EnvFile::chemin() . " (fichier local, ignoré par Git)\n";
}

echo "\n";
if (!$toutBon) {
    erreur("✘ la base est incomplète — voir les contrôles ci-dessus.\n");
    exit(1);
}

echo "✓ base prête : " . $installateur->cible() . "\n";
echo "   comptes : alice@example.com / Demo2026!   ·   admin@minishop.fr / Admin2026!\n";
if (!(bool) $options['switch'] && (string) ($config['storage_driver'] ?? 'json') !== 'sql') {
    echo "   ⚠ l'application utilise encore le pilote JSON :\n";
    echo "     php scripts/db_init.php --switch   (ou STORAGE_DRIVER=\"sql\" dans .env)\n";
}
echo "   lancer  : ./run.sh\n";
exit(0);
