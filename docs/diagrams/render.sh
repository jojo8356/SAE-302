#!/usr/bin/env bash
# =====================================================================
# MiniShop — rendu des diagrammes PlantUML (CDC §5, CI job `docs`).
#
#   bash docs/diagrams/render.sh
#   PLANTUML_JAR=/chemin/plantuml.jar bash docs/diagrams/render.sh
#
# Lit docs/diagrams/src/*.puml et produit docs/diagrams/*.png.
# Le JAR est cherché dans $PLANTUML_JAR, ./plantuml.jar, ~/.local/share/plantuml,
# puis /usr/share/plantuml/plantuml.jar. Sans JAR ni réseau : on s'arrête
# proprement en rappelant la commande (les PNG versionnés restent valides).
# =====================================================================
set -uo pipefail

DIAGRAMS_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$DIAGRAMS_DIR/src"
ROOT_DIR="$(cd "$DIAGRAMS_DIR/../.." && pwd)"

JAR="${PLANTUML_JAR:-}"
if [ -z "$JAR" ]; then
    for candidat in "$ROOT_DIR/plantuml.jar" "$HOME/.local/share/plantuml/plantuml.jar" \
                    "/usr/share/plantuml/plantuml.jar" "$DIAGRAMS_DIR/plantuml.jar"; do
        if [ -f "$candidat" ]; then JAR="$candidat"; break; fi
    done
fi

if ! ls "$SRC_DIR"/*.puml >/dev/null 2>&1; then
    echo "✘ aucune source : $SRC_DIR/*.puml" >&2
    exit 1
fi

NOMBRE=$(ls "$SRC_DIR"/*.puml | wc -l | tr -d ' ')
echo "== MiniShop — rendu de $NOMBRE diagrammes =="

if ! command -v java >/dev/null 2>&1; then
    echo "✘ java introuvable : installez openjdk-17-jre (ou utilisez l'image CI openjdk:17)" >&2
    exit 127
fi

if [ -z "$JAR" ] || [ ! -f "$JAR" ]; then
    echo "→ plantuml.jar absent, téléchargement (1.2024.0)…"
    if command -v curl >/dev/null 2>&1; then
        curl -sSL -o "$ROOT_DIR/plantuml.jar" \
            https://github.com/plantuml/plantuml/releases/download/v1.2024.0/plantuml-1.2024.0.jar || true
    fi
    if [ -f "$ROOT_DIR/plantuml.jar" ]; then JAR="$ROOT_DIR/plantuml.jar"; fi
fi

if [ -z "$JAR" ] || [ ! -f "$JAR" ]; then
    echo "✘ plantuml.jar introuvable (réseau bloqué ?)." >&2
    echo "  Les 14 PNG versionnés restent la référence ; pour régénérer :" >&2
    echo "  curl -LO https://github.com/plantuml/plantuml/releases/download/v1.2024.0/plantuml-1.2024.0.jar" >&2
    exit 127
fi

java -jar "$JAR" -tpng -charset UTF-8 "$SRC_DIR"/*.puml -o "$DIAGRAMS_DIR"
code=$?

if [ "$code" -eq 0 ]; then
    echo "✓ $NOMBRE diagrammes rendus dans $DIAGRAMS_DIR"
else
    echo "✘ PlantUML a échoué (code $code)" >&2
fi
exit "$code"
