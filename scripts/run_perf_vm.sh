#!/usr/bin/env bash
# =====================================================================
# MiniShop — campagne complète de performances SQL + HTTP (D5-L14-06).
#
#   bash scripts/run_perf_vm.sh
#   N_SQL=100 N_HTTP=200 bash scripts/run_perf_vm.sh
#   bash scripts/run_perf_vm.sh --sans-volumes
#
# Enchaîne sur la VM (sans jamais modifier .env ni la base applicative) :
#   1. scripts/capture_perf.sh (volumétrie minishop_perf + EXPLAIN + moy/p95 SQL) ;
#   2. serveur PHP temporaire pointé sur minishop_perf (STORAGE_DRIVER=sql) ;
#   3. tests/perf/mesurer.sh (moy/med/p95 HTTP sur 6 parcours réels, seuil < 500 ms).
#
# Enregistre le rapport complet dans :
#   - tests/perf/dernieres_mesures.txt
#   - tests/perf/mesures_YYYY-MM-DD.txt
# =====================================================================
set -uo pipefail
export LC_ALL=C

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

BASE_PERF="${BASE_PERF:-minishop_perf}"
N_SQL="${N_SQL:-100}"
N_HTTP="${N_HTTP:-200}"
PERF_PORT="${PERF_PORT:-18080}"
CAPTURE_ARGS=()

for argument in "$@"; do
    case "$argument" in
        --sans-volumes) CAPTURE_ARGS+=(--sans-volumes) ;;
        -h|--help)
            sed -n '2,18p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "✘ option inconnue : $argument" >&2
            exit 2
            ;;
    esac
done

command -v php >/dev/null 2>&1 || { echo "✘ php CLI requis." >&2; exit 2; }
command -v curl >/dev/null 2>&1 || { echo "✘ curl requis." >&2; exit 2; }

eval "$(php -r '
    require "app/bootstrap.php";
    $c = App\Config\Database::config();
    printf("CFG_USER=%s\nCFG_PASS=%s\nCFG_HOST=%s\nCFG_PORT=%s\n",
        escapeshellarg((string) ($c["db_user"] ?? "root")),
        escapeshellarg((string) ($c["db_pass"] ?? "")),
        escapeshellarg((string) ($c["db_host"] ?? "127.0.0.1")),
        escapeshellarg((string) ($c["db_port"] ?? "3306")));
' 2>/dev/null || true)"

DB_USER="${DB_USER:-${CFG_USER:-root}}"
DB_PASS="${DB_PASS:-${CFG_PASS:-}}"
DB_HOST="${DB_HOST:-${CFG_HOST:-127.0.0.1}}"
DB_PORT="${DB_PORT:-${CFG_PORT:-3306}}"

echo "╔════════════════════════════════════════════════════════════════════╗"
echo "║  MiniShop — campagne de performances SQL + HTTP (D5-L14-06)        ║"
echo "╚════════════════════════════════════════════════════════════════════╝"
echo "   base isolée : $BASE_PERF @ $DB_HOST:$DB_PORT (utilisateur : $DB_USER)"
echo "   itérations  : SQL=$N_SQL · HTTP=$N_HTTP"
echo

# --- 1) SQL + volumétrie ---------------------------------------------------
BASE_PERF="$BASE_PERF" N="$N_SQL" DB_USER="$DB_USER" DB_PASS="$DB_PASS" \
    DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
    bash scripts/capture_perf.sh "${CAPTURE_ARGS[@]}"
SQL_CODE=$?
if [ "$SQL_CODE" -ne 0 ]; then
    echo "✘ étape SQL échouée (code $SQL_CODE)" >&2
    exit "$SQL_CODE"
fi

# --- 2) Serveur HTTP temporaire sur minishop_perf --------------------------
SERV_LOG="$(mktemp)"
SERV_PID=""
nettoyer() {
    if [ -n "$SERV_PID" ] && kill -0 "$SERV_PID" 2>/dev/null; then
        kill "$SERV_PID" 2>/dev/null || true
        wait "$SERV_PID" 2>/dev/null || true
    fi
    rm -f "$SERV_LOG"
}
trap nettoyer EXIT INT TERM

STORAGE_DRIVER=sql DB_NAME="$BASE_PERF" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
    DB_USER="$DB_USER" DB_PASSWORD="$DB_PASS" \
    php -S "127.0.0.1:$PERF_PORT" -t public scripts/dev-router.php >"$SERV_LOG" 2>&1 &
SERV_PID=$!

PRET=0
for _ in $(seq 1 40); do
    if ! kill -0 "$SERV_PID" 2>/dev/null; then
        echo "✘ le serveur PHP temporaire s'est arrêté :" >&2
        cat "$SERV_LOG" >&2
        exit 1
    fi
    if curl -sf "http://127.0.0.1:$PERF_PORT/" >/dev/null 2>&1; then
        PRET=1
        break
    fi
    sleep 0.25
done

if [ "$PRET" -ne 1 ]; then
    echo "✘ le serveur PHP temporaire ne répond pas sur 127.0.0.1:$PERF_PORT" >&2
    cat "$SERV_LOG" >&2
    exit 1
fi

echo
echo "3. Mesures HTTP (tests/perf/mesurer.sh sur $BASE_PERF)"
HTTP_OUT="$(mktemp)"
APP_URL="http://127.0.0.1:$PERF_PORT" N="$N_HTTP" \
    bash tests/perf/mesurer.sh 2>&1 | tee "$HTTP_OUT"
HTTP_CODE=${PIPESTATUS[0]}

{
    echo
    echo "======================================================================"
    echo "Campagne HTTP SQL (base : $BASE_PERF, N=$N_HTTP, date : $(date -Iseconds))"
    echo "======================================================================"
    cat "$HTTP_OUT"
} >> tests/perf/dernieres_mesures.txt

DIRECTIVE="$RACINE/tests/perf/mesures_$(date +%Y-%m-%d).txt"
cp tests/perf/dernieres_mesures.txt "$DIRECTIVE" 2>/dev/null || true
rm -f "$HTTP_OUT"

echo
if [ "$HTTP_CODE" -eq 0 ]; then
    echo "✓ campagne SQL + HTTP conforme (ENF-01 p95 < 500 ms)"
    echo "  Rapports : tests/perf/dernieres_mesures.txt et $(basename "$DIRECTIVE")"
else
    echo "✘ campagne HTTP non conforme ou en erreur (code $HTTP_CODE)" >&2
fi
exit "$HTTP_CODE"
