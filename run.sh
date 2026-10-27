#!/usr/bin/env bash
# =====================================================================
# MiniShop — lancement local en une commande.
#
#   ./run.sh                 # http://localhost:8000 (pilote lu dans .env)
#   ./run.sh --check         # diagnostic complet, ne démarre rien
#   ./run.sh --db            # crée la base MySQL locale puis bascule .env sur sql
#                            # (crée aussi un compte applicatif via sudo si « root »
#                            #  n'accepte pas le TCP : greffon unix_socket de Debian)
#   ./run.sh --db-reset      # idem en supprimant la base existante (--yes)
#   ./run.sh --db-check      # diagnostic de la base MySQL seulement
#   ./run.sh --reset         # réinstalle le jeu de démonstration JSON
#   ./run.sh --no-seed       # ne touche pas aux données
#   PORT=8080 ./run.sh       # autre port (prioritaire sur .env)
#
# Ce que fait le script :
#   1. vérifie PHP ≥ 8.2 et les extensions utiles (pdo_mysql si pilote sql) ;
#   2. crée `.env` depuis `.env.example` s'il est absent (jamais commité) ;
#   3. lit la configuration par App\Config\Database (donc `.env`) ;
#   4. prépare les données : seed JSON, ou base MySQL via scripts/db_init.php ;
#   5. démarre le serveur intégré avec scripts/dev-router.php, qui reproduit
#      les règles du .htaccess (sinon /catalogue renvoie 404).
#
# Variables d'environnement prioritaires : PORT, HOST, APP_ENV,
# STORAGE_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SOCKET.
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RACINE" || exit 1

ACTION="servir"
FORCER=0

for argument in "$@"; do
    case "$argument" in
        --db)       ACTION="db" ;;
        --db-reset) ACTION="db-reset" ;;
        --db-check) ACTION="db-check" ;;
        --reset)    ACTION="reset" ;;
        --no-seed)  ACTION="servir-sans-seed" ;;
        --check)    ACTION="diagnostic" ;;
        --yes|-y)   FORCER=1 ;;
        -h|--help)  sed -n '2,26p' "$0"; exit 0 ;;
        *) echo "✘ option inconnue : $argument" >&2
           echo "  options : --db --db-reset --db-check --check --reset --no-seed --yes --help" >&2
           exit 2 ;;
    esac
done

bleu()  { printf '\033[1;34m%s\033[0m\n' "$*"; }
vert()  { printf '\033[1;32m%s\033[0m\n' "$*"; }
jaune() { printf '\033[1;33m%s\033[0m\n' "$*"; }
rouge() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; }

bleu "== MiniShop — lancement =="

# --- 1. PHP ----------------------------------------------------------------
if ! command -v php >/dev/null 2>&1; then
    rouge "✘ php introuvable."
    echo "  Installez PHP 8.2+ : sudo apt install php8.2-cli php8.2-mbstring php8.2-mysql php8.2-intl"
    echo "  (sous Windows : https://windows.php.net — ou WAMP/Laragon)"
    exit 127
fi

VERSION_PHP="$(php -r 'echo PHP_VERSION;' 2>/dev/null)"
if [ -z "$VERSION_PHP" ]; then
    rouge "✘ php présent mais inutilisable (php -r a échoué)."
    exit 1
fi
MAJEURE="${VERSION_PHP%%.*}"
MINEURE="$(printf '%s' "$VERSION_PHP" | cut -d. -f2)"
if [ "$MAJEURE" -lt 8 ] || { [ "$MAJEURE" -eq 8 ] && [ "$MINEURE" -lt 2 ]; }; then
    rouge "✘ PHP $VERSION_PHP détecté — 8.2 minimum requis (types, readonly, énumérations)."
    exit 1
fi
echo "   PHP        : $VERSION_PHP"

# --- 2. configuration locale (.env) ---------------------------------------
CREATION_ENV=0
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        : > .env
    fi
    CREATION_ENV=1
    jaune "→ .env créé depuis .env.example (fichier local, ignoré par Git)."
fi

# Lecture unique de la configuration effective (défauts + .env + environnement).
# Soutiré ligne à ligne (pas de `mapfile` : absent du bash 3.2 livré avec macOS).
CONFIG="$(php -r '
    require "app/bootstrap.php";
    $config = \App\Config\Database::config();
    echo (string) ($config["storage_driver"] ?? "json"), "\n";
    echo (string) ($config["db_host"] ?? "127.0.0.1"), "\n";
    echo (string) ($config["db_port"] ?? "3306"), "\n";
    echo (string) ($config["db_name"] ?? "minishop"), "\n";
    echo (string) ($config["db_user"] ?? "root"), "\n";
    echo (string) \App\Config\Env::get("PORT", "8000"), "\n";
    echo (string) \App\Config\Env::get("HOST", "0.0.0.0"), "\n";
    $sources = \App\Config\Database::sources();
    echo $sources === [] ? "(défauts)" : implode(", ", $sources), "\n";
' 2>/dev/null)"

ligne_config() {
    printf '%s\n' "$CONFIG" | sed -n "${1}p"
}

PILOTE="$(ligne_config 1)"
DB_HOTE="$(ligne_config 2)"
DB_PORT="$(ligne_config 3)"
DB_NOM="$(ligne_config 4)"
DB_UTILISATEUR="$(ligne_config 5)"
PORT_CONFIG="$(ligne_config 6)"
HOST_CONFIG="$(ligne_config 7)"
SOURCES="$(ligne_config 8)"

PILOTE="${PILOTE:-json}"
DB_HOTE="${DB_HOTE:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NOM="${DB_NOM:-minishop}"
DB_UTILISATEUR="${DB_UTILISATEUR:-root}"
PORT="${PORT:-${PORT_CONFIG:-8000}}"
HOST="${HOST:-${HOST_CONFIG:-0.0.0.0}}"
SOURCES="${SOURCES:-(défauts)}"

# Empreinte du code : un message d'erreur d'une révision antérieure (fichiers
# récupérés un par un) ressemble sinon à un bug de la révision courante. À
# comparer avec « git log -1 --oneline github/<branche> ».
REVISION="$(git -C "$RACINE" rev-parse --short HEAD 2>/dev/null || echo 'inconnue')"
if [ -n "$(git -C "$RACINE" status --porcelain --untracked-files=no 2>/dev/null)" ]; then
    REVISION="$REVISION + modifications locales"
fi

echo "   config     : $SOURCES"
echo "   code       : $REVISION"
echo "   pilote     : $PILOTE"
if [ "$PILOTE" = "sql" ]; then
    echo "   base       : $DB_NOM @ $DB_HOTE:$DB_PORT ($DB_UTILISATEUR)"
fi
echo "   adresse    : http://localhost:$PORT/"

# --- 3. extensions ---------------------------------------------------------
MANQUANTES=""
for extension in mbstring json; do
    if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);" 2>/dev/null; then
        MANQUANTES="$MANQUANTES $extension"
    fi
done
if [ "$PILOTE" = "sql" ] || [ "$ACTION" = "db" ] || [ "$ACTION" = "db-reset" ] || [ "$ACTION" = "db-check" ]; then
    if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then
        MANQUANTES="$MANQUANTES pdo_mysql"
    fi
fi

if [ -n "$MANQUANTES" ]; then
    # Les paquets Debian/Ubuntu sont versionnés : php8.2-mbstring, php8.4-mysql…
    # On reprend la version réellement installée (8.4 ici) au lieu de supposer 8.2.
    SUITE_PHP="$MAJEURE.$MINEURE"
    rouge "✘ extension(s) PHP manquante(s) :$MANQUANTES"
    echo "  Debian / Ubuntu / Mint :"
    echo "      sudo apt install php$SUITE_PHP-mbstring php$SUITE_PHP-mysql php$SUITE_PHP-intl"
    echo "  (vérifier ensuite : php -m | grep -Ei 'mbstring|pdo_mysql|intl')"
    echo "  Fedora / RHEL :  sudo dnf install php-mbstring php-mysqlnd php-intl"
    echo "  Arch          :  sudo pacman -S php  (mbstring et pdo_mysql sont compilés dans php)"
    echo "  macOS (brew)  :  brew install php"
    exit 1
fi

# --- 4. base MySQL ---------------------------------------------------------
lancer_db_init() {
    # --compte : si « root » refuse le TCP (greffon unix_socket de Debian),
    # db_init.php crée un compte applicatif dédié via « sudo mariadb ».
    if [ "$FORCER" -eq 1 ]; then
        php scripts/db_init.php "$@" --yes
    else
        php scripts/db_init.php "$@"
    fi
}

case "$ACTION" in
    db)
        echo
        lancer_db_init --switch --compte
        code=$?
        if [ "$code" -ne 0 ]; then
            rouge "✘ installation de la base interrompue (code $code)."
            exit "$code"
        fi
        echo
        vert "✓ base prête et .env basculé sur STORAGE_DRIVER=\"sql\"."
        echo "   relancer : ./run.sh"
        exit 0
        ;;
    db-reset)
        echo
        if [ "$FORCER" -ne 1 ]; then
            echo "⚠ la base « $DB_NOM » sera supprimée puis recréée."
            echo "  relancez avec : ./run.sh --db-reset --yes"
            exit 3
        fi
        lancer_db_init --switch --compte
        code=$?
        [ "$code" -ne 0 ] && exit "$code"
        vert "✓ base recréée et .env basculé sur sql — relancer : ./run.sh"
        exit 0
        ;;
    db-check)
        echo
        php scripts/db_init.php --check
        exit $?
        ;;
esac

# --- 5. données ------------------------------------------------------------
if [ "$PILOTE" = "sql" ]; then
    if php scripts/db_init.php --check >/dev/null 2>&1; then
        echo "   données    : MySQL ($DB_NOM @ $DB_HOTE:$DB_PORT)"
    else
        jaune "→ base MySQL absente ou incomplète."
        echo "  création : ./run.sh --db"
        if [ "$ACTION" = "diagnostic" ]; then
            php scripts/db_init.php --check
        fi
    fi
else
    if [ "$ACTION" = "reset" ]; then
        jaune "→ réinstallation du jeu de démonstration…"
        php scripts/seed.php || { rouge "✘ seed en échec"; exit 1; }
    elif [ ! -f data/minishop/produit.json ]; then
        jaune "→ jeu de démonstration absent : création…"
        php scripts/seed.php || { rouge "✘ seed en échec"; exit 1; }
    else
        echo "   données    : data/minishop (12 produits, 3 clients)"
    fi
fi

# --- 6. diagnostic seul ----------------------------------------------------
if [ "$ACTION" = "diagnostic" ]; then
    echo
    if [ "$PILOTE" = "sql" ]; then
        php scripts/db_init.php --check || true
        echo
    fi
    if ! command -v npm >/dev/null 2>&1; then
        echo "  npm absent : les suites de tests se lancent en PHP natif (php scripts/run-all-tests.php)."
    fi
    vert "✓ environnement prêt — lancez : ./run.sh"
    echo "  ouvrir   : http://localhost:$PORT/  ·  /catalogue  ·  /admin/connexion"
    echo "  comptes  : alice@example.com / Demo2026!   ·   admin@minishop.fr / Admin2026!"
    if [ "$CREATION_ENV" -eq 1 ]; then
        echo "  .env créé : relisez-le si vous passez sur MySQL (DB_USER / DB_PASSWORD)."
    fi
    exit 0
fi

# --- 7. serveur ------------------------------------------------------------
echo
vert "✓ serveur : http://localhost:$PORT/"
echo "   front    : http://localhost:$PORT/"
echo "   catalogue: http://localhost:$PORT/catalogue"
echo "   admin    : http://localhost:$PORT/admin/connexion"
echo "   comptes  : alice@example.com / Demo2026!   ·   admin@minishop.fr / Admin2026!"
echo "   arrêt    : Ctrl+C"
echo

PORT="$PORT" HOST="$HOST" exec php -S "$HOST:$PORT" -t public scripts/dev-router.php
