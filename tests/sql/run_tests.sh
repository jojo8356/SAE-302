#!/usr/bin/env bash
# =====================================================================
# MiniShop — harnais de tests SQL
# ---------------------------------------------------------------------
# Lit le manifeste tests/sql/manifest.txt, joue la fixture AVANT CHAQUE
# test, puis le fichier tNN_*.sql. Compare le resultat obtenu avec
# l'attendu :
#   - "ERREUR:<motif>" : le fichier SQL doit echouer et la sortie
#                        standard de mysql DOIT mentionner <motif> ;
#   - "OK"             : le fichier SQL doit reussir (code 0) ; on
#                        surligne en PASS toute ligne rendue par le
#                        SELECT 'PASS…' / 'FAIL…' du test.
# Variables d'environnement utilisées par le harnais SQL et GitHub Actions :
#   DB_HOST (defaut mysql)
#   DB_USER (defaut root)
#   DB_PASS (defaut '')
#   DB      (defaut minishop / DB_TEST si definie)
# Sortie :
#   - 1 ligne de synthese par test
#   - un journal par test dans $OUT_DIR/<fichier>.log
#   - un rapport $OUT_DIR/rapport_tests_sql.md regenerable
#   - code de retour 0 ssi tous les tests sont conformes.
# =====================================================================
set -uo pipefail

# Chemins absolus (le script peut etre appele depuis n'importe ou)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
MANIFEST="$SCRIPT_DIR/manifest.txt"
FIXTURE="$SCRIPT_DIR/fixture.sql"
OUT_DIR="${OUT_DIR:-$SCRIPT_DIR/out}"
RAPPORT="$SCRIPT_DIR/rapport_tests_sql.md"

mkdir -p "$OUT_DIR"

DB_HOST="${DB_HOST:-mysql}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
# DB_TEST (CI) > DB > 'minishop'
if [ -n "${DB_TEST:-}" ]; then DB="$DB_TEST"
elif [ -z "${DB:-}" ];    then DB="minishop"; fi

# Client mysql (compatible MariaDB)
MYSQL="$(command -v mysql || command -v mariadb)"
if [ -z "$MYSQL" ]; then
  echo "ERREUR : client mysql/mariadb introuvable dans PATH" >&2
  exit 2
fi

# Collation de session alignée sur celle du schéma (sql/01 : SET NAMES … unicode_ci).
# Sans elle, sur MariaDB >= 11.4 le défaut utf8mb4_uca1400_ai_ci fait échouer
# toute comparaison littéral <-> colonne de VUE : ERROR 1267 Illegal mix of
# collations. Une colonne de table (collation implicite) l'emporte sur le
# littéral, une colonne de vue non — d'où un échec qui ne concerne que les
# requêtes sur les vues (T-26 sur v_etat_stock).
COLLATION_SESSION_SQL='SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;'
avec_collation() { printf '%s\n' "$COLLATION_SESSION_SQL"; cat "$1"; }

MYSQL_AUTH=()
[ -n "$DB_USER" ] && MYSQL_AUTH+=(-u"$DB_USER")
[ -n "$DB_PASS" ] && MYSQL_AUTH+=("-p$DB_PASS")
MYSQL_AUTH+=(-h"$DB_HOST")
if [ -n "${DB_PORT:-}" ]; then MYSQL_AUTH+=(-P"$DB_PORT"); fi

# Fonction d'appel : fixe la base en premier argument
mysqlq() {
  "$MYSQL" "${MYSQL_AUTH[@]}" --default-character-set=utf8mb4 "$DB" "$@"
}

echo "== MiniShop — tests SQL sur la base '$DB' ($DB_HOST) =="
# Deux valeurs distinctes, et c'est voulu : celle du client TEL QUEL (défaut du
# serveur, souvent utf8mb4_uca1400_ai_ci sur MariaDB >= 11.4) et celle réellement
# utilisée par les scénarios, préfixés par SET NAMES. N'afficher que la première
# laissait croire que la campagne tournait dans la collation qui provoque
# l'erreur 1267, alors que les 29 tests passaient.
COLLATION_CLIENT="$(mysqlq -N -B -e "SELECT @@collation_connection" 2>/dev/null)"
COLLATION_SESSION="$(printf '%s\n' "$COLLATION_SESSION_SQL" | mysqlq -N -B -e "SELECT @@collation_connection" 2>/dev/null)"
if [ "$COLLATION_CLIENT" = "$COLLATION_SESSION" ]; then
  echo "   Collation de session : $COLLATION_SESSION (identique au défaut du client)"
else
  echo "   Collation de session : $COLLATION_SESSION  (défaut du client : $COLLATION_CLIENT — réaligné sur le schéma)"
fi
echo "   Sorties journaux : $OUT_DIR"
echo ""

# Inventaire rapide avant les tests
inv=$(mysqlq -N -B -e "
  SELECT 'tables', COUNT(*) FROM information_schema.tables    WHERE table_schema = DATABASE()
  UNION ALL SELECT 'procedures', COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_type='PROCEDURE'
  UNION ALL SELECT 'fonctions',  COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_type='FUNCTION'
  UNION ALL SELECT 'triggers',   COUNT(*) FROM (SELECT DISTINCT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()) t
  UNION ALL SELECT 'vues',       COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()
  UNION ALL SELECT 'produits',   COUNT(*) FROM produit
  UNION ALL SELECT 'clients',    COUNT(*) FROM client
  UNION ALL SELECT 'commandes',  COUNT(*) FROM commande;" 2>&1) || {
    echo "ERREUR : connexion/lecture impossible :" >&2
    echo "$inv" >&2; exit 2; }
echo "Inventaire :"
echo "$inv" | while IFS=$'\t' read -r k v; do printf '   %-12s %s\n' "$k" "$v"; done
echo ""

pass=0; fail=0; total=0
: > "$OUT_DIR/_resume.tsv"

# Boucle sur les lignes du manifeste (en ignorant commentaires et lignes vides)
while IFS='|' read -r tid regle desc attendu fichier; do
  tid="${tid// /}"; regle="${regle// }"; desc="${desc# }"; desc="${desc% }"
  attendu="${attendu# }"; attendu="${attendu% }"; fichier="${fichier// }"
  [ -z "${tid:-}" ] && continue
  case "$tid" in \#*) continue;; esac
  total=$((total+1))

  log="$OUT_DIR/${fichier}.log"
  : > "$log"

  # 1. Fixture (etat deterministe)
  avec_collation "$FIXTURE" | mysqlq -v >> "$log" 2>&1
  fix_rc=$?
  if [ "$fix_rc" -ne 0 ]; then
    printf ' [FIX] %-6s %-6s %s\n' "$tid" "$regle" "fixture en echec (rc=$fix_rc) — voir $log"
    echo "$tid|$regle|FIX|fixture a echoue avant le test" >> "$OUT_DIR/_resume.tsv"
    fail=$((fail+1)); continue
  fi

  # 2. Test lui-meme
  avec_collation "$SCRIPT_DIR/$fichier" | mysqlq -v > "$log.out" 2> "$log.err"
  rc=$?
  stdout=$(cat "$log.out" 2>/dev/null)
  stderr=$(cat "$log.err" 2>/dev/null)
  cat "$log.out" "$log.err" >> "$log"; rm -f "$log.out" "$log.err"

  ok=0; raison=""
  case "$attendu" in
    OK)
      if [ "$rc" -eq 0 ]; then
        # signale un FAIL explicite si le test a produit un SELECT 'FAIL…'
        if echo "$stdout" | grep -q '^FAIL'; then
          ok=0
          raison="FAIL explicite du test : $(echo "$stdout" | grep '^FAIL' | head -1)"
        else
          ok=1; raison="OK"
        fi
      else
        # Sans le message, un « rc=1 » n'est pas diagnosticable : on affiche la
        # fin de stderr (premiere erreur) au lieu de la seule valeur du code.
        detail="$(echo "$stderr" | tr '\n' ' ' | tr -s ' ' | cut -c1-220)"
        if [ -z "$detail" ]; then
          detail="(aucun message sur stderr — voir $log)"
        fi
        ok=0; raison="attendu OK mais le script a echoue (rc=$rc) : $detail"
      fi ;;
    ERREUR:*)
      motif="${attendu#ERREUR:}"
      # MySQL renvoie le message d'erreur sur stderr ; CHECK constraints
      # produisent "Check constraint '<nom>' is violated" sur les versions
      # recentes de MySQL/MariaDB. On cherche le motif dans stderr ET stdout.
      if echo "$stderr$stdout" | grep -F -q "$motif"; then
        if [ "$rc" -ne 0 ]; then ok=1; raison="ERREUR:$motif"; fi
      fi
      if [ "$ok" -eq 0 ]; then
        # L'erreur etait attendue mais rc=0 ou motif absent
        if [ "$rc" -eq 0 ]; then
          raison="erreur attendue ($motif) mais le script a reussi"
        else
          raison="erreur attendue ($motif) mais le message trouve est : $(echo "$stderr$stdout" | tr '\n' ' ' | head -c 200)"
        fi
      fi ;;
    *)
      ok=0; raison="attendu invalide dans le manifeste : $attendu" ;;
  esac

  if [ "$ok" -eq 1 ]; then
    printf ' [PASS] %-6s %-7s %s\n' "$tid" "$regle" "$desc"
    echo "$tid|$regle|PASS|$raison" >> "$OUT_DIR/_resume.tsv"
    pass=$((pass+1))
  else
    printf ' [FAIL] %-6s %-7s %s\n' "$tid" "$regle" "$desc"
    printf '         %s\n' "$raison"
    echo "$tid|$regle|FAIL|$raison" >> "$OUT_DIR/_resume.tsv"
    fail=$((fail+1))
  fi
done < <(grep -E '^T-[0-9]+\|' "$MANIFEST")

echo ""
echo "-----------------------------------------------------------------"
printf 'Tests conformes : %s / %s' "$pass" "$total"
[ "$fail" -gt 0 ] && printf '  (%s echecs)' "$fail"
echo ""

# --- generation du rapport Markdown ---------------------------------------
{
  echo "# Rapport de la campagne de tests SQL — MiniShop"
  echo ""
  echo "*Genere automatiquement par \`tests/sql/run_tests.sh\` le $(date '+%Y-%m-%d %H:%M:%S')*"
  echo ""
  echo "| Conforme(s) | Echec(s) | Total | Base |"
  echo "|---:|---:|---:|---|"
  echo "| $pass | $fail | $total | \`$DB\` @ \`$DB_HOST\` |"
  echo ""
  echo "| # | Regle | Description | Attendu | Resultat | Commentaire |"
  echo "|---|---|---|---|---|---|"
  while IFS='|' read -r tid regle statut raison; do
    # retrouve description et attendu dans le manifeste
    ligne=$(grep -m1 "^${tid}|" "$MANIFEST")
    desc=$(echo "$ligne" | cut -d'|' -f3)
    attendu=$(echo "$ligne" | cut -d'|' -f4)
    case "$statut" in
      PASS) emo="✅";;
      FAIL) emo="❌";;
      FIX)  emo="🛑";;
      *)    emo="?";;
    esac
    echo "| $tid | $regle | $desc | $attendu | $emo $statut | $raison |"
  done < "$OUT_DIR/_resume.tsv"
} > "$RAPPORT"

echo "Rapport : $RAPPORT"
[ "$fail" -eq 0 ] && exit 0 || exit 1
