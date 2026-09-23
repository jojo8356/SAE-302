#!/usr/bin/env bash
# =====================================================================
# MiniShop — point d'entree utilisateur/CI pour les tests SQL
# Ceci est un wrapper mince autour du harnais situe dans
# tests/sql/run_tests.sh : il positionne les variables d'environnement
# (DB_HOST par defaut en local 127.0.0.1, en CI mysql), change de
# repertoire pour etre appele depuis n'importe ou puis delegue.
# =====================================================================
set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Variables lisant l'environnement (defauts adaptes au dev local)
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-}"
# DB_TEST (CI) > DB (utilisateur) > 'minishop' (defaut dev)
if [ -n "${DB_TEST:-}" ]; then export DB="$DB_TEST"
elif [ -z "${DB:-}" ];    then export DB="minishop"; fi

exec bash "$ROOT_DIR/tests/sql/run_tests.sh" "$@"
