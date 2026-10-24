#!/usr/bin/env bash
# =====================================================================
# MiniShop — lancement local en une commande.
#
#   ./run.sh                 # http://localhost:8000 (port 8000)
#   PORT=8080 ./run.sh       # autre port
#   ./run.sh --reset         # réinstalle le jeu de démonstration JSON
#   ./run.sh --no-seed       # ne touche pas aux données
#   ./run.sh --check         # diagnostic seul, ne lance pas le serveur
#
# Ce que fait le script :
#   1. vérifie PHP ≥ 8.2 et les extensions utiles (pdo_mysql si pilote SQL) ;
#   2. crée app/Config/env.php depuis le modèle s'il est absent (jamais commité) ;
#   3. sème data/minishop si le jeu de démonstration manque (pilote JSON) ;
#   4. démarre le serveur intégré avec scripts/dev-router.php, qui reproduit
#      les règles du .htaccess (sinon /catalogue renvoie 404).
#
# Variables d'environnement : PORT, HOST (défaut 0.0.0.0), APP_ENV (dev|test).
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RACINE" || exit 1

PORT="${PORT:-8000}"
HOST="${HOST:-0.0.0.0}"
ACTION="servir"

for argument in "$@"; do
    case "$argument" in
        --reset)   ACTION="reset" ;;
        --no-seed) ACTION="servir-sans-seed" ;;
        --check)   ACTION="diagnostic" ;;
        -h|--help) sed -n '2,24p' "$0"; exit 0 ;;
        *) echo "✘ option inconnue : $argument (--reset, --no-seed, --check, --help)" >&2; exit 2 ;;
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

# --- 2. extensions ---------------------------------------------------------
MANQUANTES=""
for extension in mbstring json; do
    if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);" 2>/dev/null; then
        MANQUANTES="$MANQUANTES $extension"
    fi
done

# --- 3. configuration locale ----------------------------------------------
CREATION_ENV=0
if [ ! -f app/Config/env.php ]; then
    cp app/Config/env.example.php app/Config/env.php
    CREATION_ENV=1
    jaune "→ app/Config/env.php créé depuis le modèle (fichier ignoré par Git)."
fi

PILOTE="$(php -r '
$fichier = "app/Config/env.php";
$config = is_file($fichier) ? (array) include $fichier : [];
echo isset($config["storage_driver"]) ? (string) $config["storage_driver"] : "json";
' 2>/dev/null)"
if [ -z "$PILOTE" ]; then
    PILOTE="json"
fi
echo "   pilote     : $PILOTE"
echo "   adresse    : http://localhost:$PORT/"

if [ "$PILOTE" = "sql" ]; then
    if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then
        MANQUANTES="$MANQUANTES pdo_mysql"
    fi
fi

if [ -n "$MANQUANTES" ]; then
    rouge "✘ extension(s) PHP manquante(s) :$MANQUANTES"
    echo "  sudo apt install php8.2-mbstring php8.2-mysql php8.2-intl"
    exit 1
fi

# --- 4. données ------------------------------------------------------------
if [ "$PILOTE" = "sql" ]; then
    echo "   données    : MySQL (vérifier la base : ./scripts/load_db.sh puis ./scripts/run_sql_tests.sh)"
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

# --- 5. diagnostic seul ----------------------------------------------------
if [ "$ACTION" = "diagnostic" ]; then
    echo
    vert "✓ environnement prêt — lancez : ./run.sh"
    echo "  ouvrir   : http://localhost:$PORT/  ·  /admin  ·  /catalogue"
    echo "  comptes  : alice@example.com / Demo2026!   ·   admin@minishop.fr / Admin2026!"
    [ "$CREATION_ENV" -eq 1 ] && echo "  pensez à relire app/Config/env.php (db_user / db_pass) si vous passez en MySQL."
    exit 0
fi

# --- 6. serveur ------------------------------------------------------------
echo
vert "✓ serveur : http://localhost:$PORT/"
echo "   front    : http://localhost:$PORT/"
echo "   catalogue: http://localhost:$PORT/catalogue"
echo "   admin    : http://localhost:$PORT/admin/connexion"
echo "   comptes  : alice@example.com / Demo2026!   ·   admin@minishop.fr / Admin2026!"
echo "   arrêt    : Ctrl+C"
echo

PORT="$PORT" HOST="$HOST" exec php -S "$HOST:$PORT" -t public scripts/dev-router.php
