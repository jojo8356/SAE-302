#!/usr/bin/env bash
# MiniShop — mesure du temps de reponse d'une URL (ENF-01 : p95 < 500 ms).
#   URL_BASE=http://127.0.0.1:8000 N=200 ./tests/perf/mesurer.sh '/catalogue?cat=1'
set -uo pipefail
base="${URL_BASE:-http://127.0.0.1:8000}"; url="${1:-/}"; n="${N:-200}"; c="${C:-4}"
command -v ab >/dev/null 2>&1 && { ab -n "$n" -c "$c" -k "$base$url" | grep -E 'Requests per second|Time per request|Failed requests'; exit $?; }
ttmp="$(mktemp)"; ok=0; fail=0
for i in $(seq 1 "$n"); do
  t="$(curl -s -o /dev/null -w '%{time_total} %{http_code}' "$base$url" || echo '0 000')"
  echo "${t% *}" >> "$ttmp"; [ "${t#* }" = "200" ] && ok=$((ok+1)) || fail=$((fail+1))
done
sort -n "$ttmp" | awk -v ok="$ok" -v fail="$fail" '
 { v[NR]=$1 } END {
   p95 = v[int(NR*0.95)>0?int(NR*0.95):NR]; p50 = v[int(NR/2)>0?int(NR/2):1];
   printf "requetes=%d (200: %d, autres: %d)\nmediane=%.3fs p95=%.3fs max=%.3fs\n", NR, ok, fail, p50, p95, v[NR];
   exit (p95 < 0.5 ? 0 : 1)
 }'; rc=$?; rm -f "$ttmp"
[ $rc -eq 0 ] && echo "ENF-01 conforme (p95 < 500 ms)" || echo "ENF-01 NON conforme : mesurer la requete (general_log) et verifier les index"
exit $rc
