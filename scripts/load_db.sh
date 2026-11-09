#!/usr/bin/env bash
# =====================================================================
# MiniShop — chargement de la base de données (CDC EF-GEN-06, docs 02/04)
# ---------------------------------------------------------------------
# Encapsule les quatre scripts sql/ dans l'ordre, avec deux moteurs :
#   1. PHP/PDO (scripts/load_db.php) — recommandé : gère les DELIMITER,
#      fonctionne sans client mysql (Windows, CI minimale) ;
#   2. client `mysql` en repli si PHP est absent de la machine.
#
# Variables : DB (minishop), DB_HOST (127.0.0.1), DB_PORT (3306),
#             DB_USER (root), DB_PASS (''), SKIP_DEMO (0).
#
# Exemples :
#   ./scripts/load_db.sh
#   DB=minishop_test SKIP_DEMO=1 ./scripts/load_db.sh
#   DB_USER=minishop DB_PASS="$MINISHOP_DB_PASS" ./scripts/load_db.sh
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

DB="${DB:-minishop}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
SKIP_DEMO="${SKIP_DEMO:-0}"

if [[ ! "$DB" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "✘ nom de base invalide : $DB" >&2
    exit 2
fi

echo "== MiniShop : chargement de la base « $DB » sur $DB_HOST:$DB_PORT =="

if command -v php >/dev/null 2>&1; then
    DB="$DB" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_USER="$DB_USER" \
        DB_PASS="$DB_PASS" SKIP_DEMO="$SKIP_DEMO" \
        exec php "$RACINE/scripts/load_db.php"
fi

if ! command -v mysql >/dev/null 2>&1; then
    echo "✘ ni php ni mysql disponibles : installez PHP 8.2+ (voir README)." >&2
    exit 1
fi

MYSQL=(mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER")
if [ -n "$DB_PASS" ]; then
    MYSQL+=(--password="$DB_PASS")
fi

echo "   (repli client mysql)"
"${MYSQL[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || exit 1

fichiers=(sql/01_minishop_schema.sql sql/02_minishop_procedures.sql sql/03_minishop_triggers.sql)
if [ "$SKIP_DEMO" != "1" ]; then
    fichiers+=(sql/04_minishop_demo.sql)
fi

for fichier in "${fichiers[@]}"; do
    # 01 contient CREATE DATABASE/USE minishop : on le rejoue sur la base choisie
    # en retirant ces lignes, pour que DB=minishop_test fonctionne réellement.
    if [ "$DB" = "minishop" ]; then
        "${MYSQL[@]}" < "$fichier" || { echo "✘ échec sur $fichier" >&2; exit 1; }
    else
        grep -viE '^(DROP|CREATE) DATABASE|^USE ' "$fichier" | "${MYSQL[@]}" "$DB" \
            || { echo "✘ échec sur $fichier" >&2; exit 1; }
    fi
    echo "   $fichier : OK"
done

echo "✔ base « $DB » prête."
