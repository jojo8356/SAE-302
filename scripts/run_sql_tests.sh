#!/usr/bin/env bash
# =====================================================================
# MiniShop — campagne de tests SQL (CDC §15.2, lot L13)
# ---------------------------------------------------------------------
# Enveloppe de tests/sql/run_tests.sh : vérifie que la base est chargée,
# joue les 29 tests (règles RB, procédures, déclencheurs, vues) et affiche
# le rapport régénéré.
#
# Prérequis : base chargée (./scripts/load_db.sh) et client `mysql`.
# Variables : DB_TEST (minishop_test), DB (minishop), DB_HOST, DB_USER, DB_PASS.
#
# Code retour : 0 si les 29 tests sont conformes, 1 sinon.
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

if ! command -v mysql >/dev/null 2>&1; then
    echo "✘ client mysql introuvable : les tests SQL comparent des sorties du client." >&2
    echo "  Installez mysql-client (Debian/Ubuntu) puis relancez." >&2
    exit 1
fi

export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-}"
export DB_TEST="${DB_TEST:-minishop_test}"
export DB="${DB:-minishop}"

echo "== MiniShop : tests SQL (base « $DB_TEST ») =="
bash "$RACINE/tests/sql/run_tests.sh"
code=$?

if [ -f "$RACINE/tests/sql/rapport_tests_sql.md" ]; then
    echo
    grep -E '^\| *(Total|Conformes|Échecs)' "$RACINE/tests/sql/rapport_tests_sql.md" || true
fi

exit "$code"
