#!/usr/bin/env bash
# =====================================================================
# MiniShop — génération du jeu de volumétrie (ENF-01, ENF-02)
# ---------------------------------------------------------------------
# Enveloppe de scripts/gen_volumes_cli.php : 200 produits / 1 000 commandes
# par défaut, écrits dans MySQL via les procédures stockées.
#
#   ./scripts/gen_volumes.sh                      # valeurs par défaut
#   ./scripts/gen_volumes.sh --produits 500 --commandes 5000
#   ./scripts/gen_volumes.sh --load               # charge sql/01→04 avant
#   ./scripts/gen_volumes.sh --purge              # retire le jeu de mesure
#
# Variables : DB_HOST, DB_PORT, DB_NAME (minishop_perf), DB_USER, DB_PASS.
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

if ! command -v php >/dev/null 2>&1; then
    echo "✘ PHP 8.2+ requis (voir README §Prérequis)." >&2
    exit 1
fi

exec php "$RACINE/scripts/gen_volumes_cli.php" "$@"
