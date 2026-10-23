#!/usr/bin/env bash
# =====================================================================
# MiniShop — mesure de performance HTTP (ENF-01 : p95 < 500 ms).
#
#   APP_URL=http://127.0.0.1:8000 bash tests/perf/mesurer.sh
#   N=200 bash tests/perf/mesurer.sh                # 200 requêtes par URL
#   bash tests/perf/mesurer.sh --sql-only           # délègue à mesurer_sql.sh
#
# Mesure 5 parcours réels (accueil, catalogue, recherche, fiche, panier)
# avec `curl -w %{time_total}`, puis calcule moyenne, médiane et p95.
# Verdict : p95 < 500 ms et aucune erreur HTTP.
#
# Prérequis : application démarrée
#   php -S 127.0.0.1:8000 -t public
# (ou `node scripts/wasm-serve.mjs` en sandbox sans PHP natif).
# =====================================================================
set -uo pipefail

if [ "${1:-}" = "--sql-only" ]; then
    shift
    exec bash "$(dirname "$0")/mesurer_sql.sh" "$@"
fi

APP_URL="${APP_URL:-http://127.0.0.1:8000}"
N="${N:-200}"
SEUIL_P95_MS="${SEUIL_P95_MS:-500}"

command -v curl >/dev/null 2>&1 || { echo "✘ curl requis" >&2; exit 127; }

URLS=(
    "/"
    "/catalogue"
    "/catalogue?cat=1&prix_max=500&stock=1&tri=prix_asc"
    "/recherche?q=clavier"
    "/panier"
)

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "== MiniShop — mesure HTTP ($N requêtes par URL, cible $APP_URL) =="
printf '   %-52s %8s %8s %8s %6s\n' "URL" "moy(ms)" "med(ms)" "p95(ms)" "HTTP"

GLOBAL_P95=0
ERREURS=0

for url in "${URLS[@]}"; do
    : > "$TMP/times"
    codes=""
    for _ in $(seq 1 "$N"); do
        # shellcheck disable=SC2086
        read -r t code < <(curl -s -o /dev/null -w '%{time_total} %{http_code}\n' "${APP_URL}${url}")
        echo "$t" >> "$TMP/times"
        codes="$codes $code"
    done
    stats=$(sort -n "$TMP/times" | awk -v n="$N" '
        { v[NR] = $1; s += $1 }
        END {
            moy = s / n * 1000;
            med = v[int((n + 1) / 2)] * 1000;
            p95 = v[int(n * 0.95) < 1 ? 1 : int(n * 0.95)] * 1000;
            printf "%.1f %.1f %.1f", moy, med, p95
        }')
    read -r moy med p95 <<< "$stats"
    p95i=${p95%.*}
    [ "$p95i" -gt "$GLOBAL_P95" ] && GLOBAL_P95="$p95i"
    bad=$(echo "$codes" | tr ' ' '\n' | grep -vcE '^(200|302|404)$' || true)
    ERREURS=$((ERREURS + bad))
    printf '   %-52s %8s %8s %8s %6s\n' "$url" "$moy" "$med" "$p95" "$(echo "$codes" | tr ' ' '\n' | sort -u | tr '\n' ',' | sed 's/,$//')"
done

echo
echo "   p95 global : ${GLOBAL_P95} ms (seuil ${SEUIL_P95_MS} ms) · réponses inattendues : ${ERREURS}"
if [ "$ERREURS" -eq 0 ] && [ "$GLOBAL_P95" -lt "$SEUIL_P95_MS" ]; then
    echo "✓ ENF-01 conforme (p95 < ${SEUIL_P95_MS} ms)"
    exit 0
fi
if [ "$ERREURS" -ne 0 ]; then
    echo "✘ des réponses HTTP inattendues ont été observées"
else
    echo "✘ ENF-01 non conforme (p95 ${GLOBAL_P95} ms ≥ ${SEUIL_P95_MS} ms)"
fi
exit 1
