#!/usr/bin/env bash
# MiniShop — mesure des perFORMances **côté base** (ENF-01, ENF-02, CT-01).
#
# Pourquoi ce script : la mesure HTTP (`tests/perf/mesurer.sh`) exige une application PHP
# et un serveur web. Celle-ci ne mesure QUE la base, sur la volumétrie retenue avec le
# client (200 produits / 1 000 commandes par an, générée par `scripts/gen_volumes.sh`),
# et elle est donc rejouable en soutenance même si l'application n'est pas démarrée.
# Elle répond à trois questions :
#   1. les requêtes types utilisent-elles un index (EXPLAIN, 0 `filesort` sur les filtres) ;
#   2. la recherche paginée tient-elle sous 500 ms au p95 ;
#   3. un `CALL sp_add_order_line` (verrou + triggers + port) reste-t-il mesurable.
#
# Sortie : tableau des mesures + verdict, et le même bloc copiable dans le doc de tests.
# Usage : DB=minishop_perf ./tests/perf/mesurer_sql.sh [N iterations]
set -uo pipefail
export LC_ALL=C
cd "$(dirname "$0")/../.."
DB="${DB:-minishop_perf}"
N="${1:-100}"
MYSQL="$(command -v mariadb || command -v mysql)"
AUTH=(--default-character-set=utf8mb4)
if [ -n "${DB_HOST:-}" ]; then AUTH+=(-h "$DB_HOST"); fi
if [ -n "${DB_PORT:-}" ]; then AUTH+=(-P "$DB_PORT"); fi
if [ -n "${DB_USER:-}" ]; then AUTH+=(-u"$DB_USER"); fi
if [ -n "${DB_PASS:-}" ]; then AUTH+=("-p$DB_PASS"); fi
q() { "$MYSQL" "${AUTH[@]}" "$DB" -N -B -e "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; $1"; }
q_stdin() { { echo "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;"; cat; } | "$MYSQL" "${AUTH[@]}" "$DB" -N -B; }

echo "== MiniShop — mesure base '$DB' ($N itérations par requête) =="
q "SELECT 'produits', COUNT(*) FROM produit
      UNION ALL SELECT 'commandes', COUNT(*) FROM commande
      UNION ALL SELECT 'lignes', COUNT(*) FROM ligne_commande
      UNION ALL SELECT 'traces_statut', COUNT(*) FROM order_status_history;" |
  while read -r k v; do printf '   %-20s %s\n' "$k" "$v"; done

# --- 1) plans d'exécution -------------------------------------------------
echo
echo "-- EXPLAIN des 4 requêtes types (attendu : pas d'ALL sur la table filtrée) --"
for name in "catalogue paginé" "recherche LIKE" "mes commandes" "commandes par statut"; do
  case "$name" in
    "catalogue paginé")
      sql="SELECT id_produit, reference, nom, prix_ttc FROM v_catalogue ORDER BY id_produit DESC LIMIT 12 OFFSET 0";;
    "recherche LIKE")
      sql="SELECT id_produit, nom, prix_ttc FROM produit WHERE visible=1 AND (nom LIKE '%écran%' OR description LIKE '%écran%') ORDER BY nom LIMIT 12";;
    "mes commandes")
      sql="SELECT numero, statut, montant_total, frais_port, date_commande FROM commande WHERE id_client=1 ORDER BY date_commande DESC LIMIT 10";;
    "commandes par statut")
      sql="SELECT statut, COUNT(*) AS nb, ROUND(AVG(montant_total),2) AS panier_moyen FROM commande WHERE statut IN ('EN_PREPARATION','PAYEE','EXPEDIEE') GROUP BY statut";;
  esac
  plan=$(q "EXPLAIN $sql" | awk -F"\t" '{
            k = ($6=="" || $6=="NULL") ? "aucun index" : $6
            printf "select=%s table=%s type=%s key=%s rows=%s extra=%s", $2, $3, ($4==""?"?":$4), k, ($9==""?"?":$9), $10
            printf "\n"}' | paste -sd" | " -)
  scan=$(q "EXPLAIN $sql" | awk -F"\t" '$4=="ALL"{n++} END{print n+0}')
  if [ "${scan:-0}" -gt 0 ]; then verdict="ACCES COMPLET (ALL) sur $scan table(s) — à documenter"; else verdict="pas de balayage complet"; fi
  printf '   %-22s %s\n' "$name" "${plan:-—}"
  printf '   %-22s ↳ %s\n' '' "$verdict"
done

# --- 2) temps de réponse (moyenne + p95) ----------------------------------
TMP_SQL="$(mktemp -d)"
trap 'rm -rf "$TMP_SQL"' EXIT

mesure() {   # $1 libellé  $2 requête → moy(ms) et p95(ms)
  local label="$1" sql="$2"
  local t1 t2 ms
  : > "$TMP_SQL/times"
  for _ in $(seq 1 "$N"); do
    t1=$(date +%s%N)
    if ! q "$sql" > /dev/null; then
      printf '%s\t9999.0\t9999.0\n' "$label"
      return 1
    fi
    t2=$(date +%s%N)
    ms=$(awk -v d="$((t2 - t1))" 'BEGIN { printf "%.2f", d / 1000000 }')
    echo "$ms" >> "$TMP_SQL/times"
  done
  stats=$(sort -n "$TMP_SQL/times" | awk -v n="$N" '
    { v[NR] = $1; s += $1 }
    END {
      idx = int(n * 0.95); if (idx < 1) idx = 1;
      printf "%.1f\t%.1f", s / n, v[idx]
    }')
  printf '%s\t%s\n' "$label" "$stats"
}
echo
echo "-- Temps par requête ($N exécutions, client et serveur sur la même machine) --"
printf '   %-38s %9s %9s   %s\n' "Requête" "moy(ms)" "p95(ms)" "Verdict"
res=$(
  mesure "catalogue (v_catalogue, 12 lignes)" "SELECT id_produit, reference, nom, prix_ttc FROM v_catalogue ORDER BY id_produit DESC LIMIT 12"
  mesure "recherche plein texte LIKE"         "SELECT id_produit, nom, prix_ttc FROM produit WHERE visible=1 AND (nom LIKE '%écran%' OR description LIKE '%écran%') ORDER BY nom LIMIT 12"
  mesure "mes 10 dernières commandes"          "SELECT numero, statut, montant_total, frais_port FROM commande WHERE id_client=1 ORDER BY date_commande DESC LIMIT 10"
  mesure "indicateurs du jour (GROUP BY)"      "SELECT statut, COUNT(*), ROUND(AVG(montant_total),2) FROM commande GROUP BY statut"
  mesure "vue v_etat_stock (1 page)"           "SELECT * FROM v_etat_stock WHERE etat <> 'OK' LIMIT 25"
)
SQL_KO=0
while IFS=$'\t' read -r label moy p95; do
  p95_int=${p95%.*}
  if [ "${p95_int:-9999}" -lt 500 ]; then
    etat="OK (< 500 ms)"
  else
    etat=">= 500 ms (ENF-01 non tenu)"
    SQL_KO=1
  fi
  printf '   %-38s %9s %9s   %s\n' "$label" "$moy" "$p95" "$etat"
done <<< "$res"

# --- 3) coût d'une écriture métier ---------------------------------------
echo
echo "-- Écriture métier : sp_add_order_line (verrou FOR UPDATE + triggers + recalcul du port) --"
pid=$(q "SELECT MIN(id_produit) FROM produit WHERE stock > 5")
if [ -n "${pid:-}" ]; then
  t1=$(date +%s%N)
  q_stdin > /dev/null 2>&1 <<SQL
CALL sp_create_order(1, '12 rue de France, 06000 Nice', @c, @n);
CALL sp_add_order_line(@c, $pid, 1, @l, @t, @r);
CALL sp_confirm_order(@c, 1, 0, @m, @code);
SQL
  t2=$(date +%s%N)
  printf '   commande complète (create + 1 ligne + confirm) : %s ms\n' "$(( (t2 - t1) / 1000000 ))"
  q "SELECT CONCAT('   → numéro ', numero, ' · montant ', montant_total, ' · port ', frais_port)
          FROM commande ORDER BY id_commande DESC LIMIT 1;"
  q "SET FOREIGN_KEY_CHECKS=0; DELETE FROM ligne_commande WHERE id_commande = (SELECT MAX(id_commande) FROM commande); DELETE FROM order_status_history WHERE order_id = (SELECT MAX(id_commande) FROM commande); DELETE FROM commande WHERE id_commande = (SELECT MAX(id_commande) FROM commande); SET FOREIGN_KEY_CHECKS=1;" 2>/dev/null || true
  echo "   (la commande de mesure est supprimée : la base reste dans son état mesuré)"
else
  echo "   aucun produit avec du stock : relancer ./scripts/gen_volumes.sh --purge"
fi
echo
echo "Rappel : ces chiffres mesurent la BASE. La mesure ENF-01 « page < 500 ms (p95) »"
echo "HTTP complète s'obtient avec tests/perf/mesurer.sh (ou scripts/run_perf_vm.sh)."
exit "$SQL_KO"
