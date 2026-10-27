#!/usr/bin/env bash
# =====================================================================
# MiniShop — capture des performances côté base (ENF-01, ENF-02, CT-01).
#
#   sudo bash scripts/capture_perf.sh              # tout : volumétrie + mesures
#   bash scripts/capture_perf.sh --sans-volumes    # mesure la base existante
#   N=200 bash scripts/capture_perf.sh             # 200 itérations par requête
#
# Pourquoi cette enveloppe existe : `docs/02-document-tests-validation.md`
# (scénario P-06) cite « scripts/capture_perf.sh », qui n'existait pas — le
# travail se faisait à la main en enchaînant `gen_volumes.sh` puis
# `tests/perf/mesurer_sql.sh`. Ce script rend la commande du document vraie, et
# rejouable en soutenance.
#
# Ce qu'il produit :
#   - une base de volumétrie dédiée (`minishop_perf`, ~200 produits /
#     ~1 000 commandes), JAMAIS la base applicative ;
#   - un rapport de mesures (temps par requête type, EXPLAIN) ;
#   - une copie datée dans `tests/perf/`, pour la soutenance.
#
# Le script ne mesure QUE la base : la mesure HTTP vit dans
# `tests/perf/mesurer.sh` et exige un serveur démarré.
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

BASE_PERF="${BASE_PERF:-minishop_perf}"
ITERATIONS="${N:-100}"
VOLUMES=1
SORTIE="tests/perf/dernieres_mesures.txt"

for argument in "$@"; do
    case "$argument" in
        --sans-volumes) VOLUMES=0 ;;
        -h|--help)
            sed -n '2,22p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "✘ option inconnue : $argument" >&2
            exit 2
            ;;
    esac
done

CLIENT="$(command -v mariadb || command -v mysql || true)"
if [ -z "$CLIENT" ]; then
    echo "✘ aucun client « mariadb » ou « mysql » dans le PATH." >&2
    echo "  Debian/Ubuntu : sudo apt install mariadb-client" >&2
    exit 2
fi

if [ -z "${DB_USER:-}" ]; then
    # Reprend les identifiants du .env : jamais de mot de passe en argument.
    eval "$(php -r '
        require "app/bootstrap.php";
        $c = App\Config\Database::config();
        printf("DB_USER=%s\nDB_PASS=%s\nDB_HOST=%s\nDB_PORT=%s\n",
            escapeshellarg((string) ($c["db_user"] ?? "root")),
            escapeshellarg((string) ($c["db_pass"] ?? "")),
            escapeshellarg((string) ($c["db_host"] ?? "127.0.0.1")),
            escapeshellarg((string) ($c["db_port"] ?? "3306")));
    ' 2>/dev/null || true)"
fi
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

echo "== MiniShop — capture des performances =="
echo "   base de mesure : $BASE_PERF @ $DB_HOST:$DB_PORT"
echo "   itérations     : $ITERATIONS"
echo

if [ "$VOLUMES" -eq 1 ]; then
    echo "1. Volumétrie (scripts/gen_volumes.sh)"
    echo "   La base « $BASE_PERF » est recréée : elle ne contient que des données de mesure."
    # gen_volumes.sh attend DB_NAME (et non DB) : les deux sont transmis.
    if ! DB_NAME="$BASE_PERF" DB="$BASE_PERF" DB_USER="$DB_USER" DB_PASS="$DB_PASS" \
        DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
        bash scripts/gen_volumes.sh >/dev/null 2>&1; then
        echo "   ✘ génération impossible (relancer sans redirection pour voir l'erreur)." >&2
        exit 1
    fi
    echo "   ✓ volumétrie générée"
    echo
fi

echo "2. Mesures (tests/perf/mesurer_sql.sh)"
mkdir -p tests/perf
if ! DB="$BASE_PERF" DB_USER="$DB_USER" DB_PASS="$DB_PASS" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
    bash tests/perf/mesurer_sql.sh "$ITERATIONS" 2>&1 | tee "$SORTIE"; then
    echo "✘ la mesure a échoué." >&2
    exit 1
fi

DIRECTIVE="$RACINE/tests/perf/mesures_$(date +%Y-%m-%d).txt"
cp "$SORTIE" "$DIRECTIVE" 2>/dev/null || true

echo
echo "✓ mesures enregistrées"
echo "   tests/perf/dernieres_mesures.txt   (écrasé à chaque campagne)"
echo "   $(basename "$DIRECTIVE")             (copie datée, à joindre en soutenance)"
echo
echo "Rappel : ces chiffres portent sur la base SEULE. Les temps de réponse"
echo "HTTP complets se mesurent avec tests/perf/mesurer.sh, serveur démarré."
