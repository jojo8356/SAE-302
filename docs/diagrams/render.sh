#!/usr/bin/env bash
# =====================================================================
# MiniShop — vérification de l'environnement et rendu des diagrammes PlantUML
# (CDC §5, CI job `docs`).
#
#   bash docs/diagrams/render.sh
#   bash docs/diagrams/render.sh --check
#   PLANTUML_JAR=/chemin/plantuml.jar bash docs/diagrams/render.sh
#   bash docs/diagrams/render.sh -o /tmp/out
#
# Vérifie d'abord si `java` et `plantuml` (commande système ou plantuml.jar)
# ainsi que `dot` (Graphviz) sont déjà installés sur le PC actuel.
# Lit docs/diagrams/src/*.puml et produit docs/diagrams/*.png (ou -o <dir>).
# =====================================================================
set -uo pipefail

DIAGRAMS_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$DIAGRAMS_DIR/src"
ROOT_DIR="$(cd "$DIAGRAMS_DIR/../.." && pwd)"

CHECK_ONLY=0
OUT_DIR="${PLANTUML_OUT:-$DIAGRAMS_DIR}"

while [ $# -gt 0 ]; do
    case "$1" in
        --check|-c)
            CHECK_ONLY=1
            shift
            ;;
        -o|--output)
            if [ $# -lt 2 ]; then
                echo "✘ option $1 : dossier de sortie manquant" >&2
                exit 2
            fi
            OUT_DIR="$2"
            shift 2
            ;;
        -h|--help)
            sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "✘ option inconnue : $1" >&2
            exit 2
            ;;
    esac
done

if ! ls "$SRC_DIR"/*.puml >/dev/null 2>&1; then
    echo "✘ aucune source : $SRC_DIR/*.puml" >&2
    exit 1
fi

NOMBRE=$(ls "$SRC_DIR"/*.puml | wc -l | tr -d ' ')
echo "== MiniShop — vérification des prérequis ($NOMBRE diagrammes) =="

# ---------------------------------------------------------------------
# 1. Vérification de Java sur le PC actuel
# ---------------------------------------------------------------------
JAVA_CMD=""
if [ -n "${JAVA_BIN:-}" ] && [ -x "$JAVA_BIN" ]; then
    JAVA_CMD="$JAVA_BIN"
elif command -v java >/dev/null 2>&1; then
    JAVA_CMD="$(command -v java)"
elif [ -n "${JAVA_HOME:-}" ] && [ -x "$JAVA_HOME/bin/java" ]; then
    JAVA_CMD="$JAVA_HOME/bin/java"
fi

if [ -n "$JAVA_CMD" ]; then
    JAVA_VER="$("$JAVA_CMD" -version 2>&1 | head -n 1 | tr -d '\r')"
    echo "✓ java déjà installé : $JAVA_CMD ($JAVA_VER)"
else
    echo "✘ java non installé sur ce PC (commande 'java' et JAVA_HOME introuvables)"
fi

# ---------------------------------------------------------------------
# 2. Vérification de PlantUML sur le PC actuel (binaire ou JAR)
# ---------------------------------------------------------------------
PLANTUML_MODE=""
PLANTUML_CMD=""
JAR=""

if [ -n "${PLANTUML_JAR:-}" ]; then
    if [ -f "$PLANTUML_JAR" ]; then
        JAR="$PLANTUML_JAR"
        PLANTUML_MODE="jar"
    else
        echo "⚠ PLANTUML_JAR défini mais introuvable : $PLANTUML_JAR"
    fi
fi

if [ -z "$PLANTUML_MODE" ]; then
    if [ -n "${PLANTUML_BIN:-}" ] && [ -x "$PLANTUML_BIN" ]; then
        PLANTUML_CMD="$PLANTUML_BIN"
        PLANTUML_MODE="bin"
    elif command -v plantuml >/dev/null 2>&1; then
        PLANTUML_CMD="$(command -v plantuml)"
        PLANTUML_MODE="bin"
    else
        for candidat in \
            "$ROOT_DIR/plantuml.jar" \
            "$DIAGRAMS_DIR/plantuml.jar" \
            "$HOME/.local/share/plantuml/plantuml.jar" \
            "$HOME/.cache/plantuml/plantuml.jar" \
            "/usr/share/plantuml/plantuml.jar" \
            "/usr/share/java/plantuml.jar" \
            "/usr/local/share/plantuml/plantuml.jar" \
            "/opt/plantuml/plantuml.jar"; do
            if [ -f "$candidat" ]; then
                JAR="$candidat"
                PLANTUML_MODE="jar"
                break
            fi
        done
    fi
fi

if [ "$PLANTUML_MODE" = "bin" ]; then
    PLANTUML_VER="$("$PLANTUML_CMD" -version 2>/dev/null | head -n 1 | tr -d '\r' || true)"
    echo "✓ plantuml déjà installé : $PLANTUML_CMD${PLANTUML_VER:+ ($PLANTUML_VER)}"
elif [ "$PLANTUML_MODE" = "jar" ]; then
    PLANTUML_VER=""
    if [ -n "$JAVA_CMD" ]; then
        PLANTUML_VER="$("$JAVA_CMD" -jar "$JAR" -version 2>/dev/null | head -n 1 | tr -d '\r' || true)"
    fi
    echo "✓ plantuml déjà installé (JAR) : $JAR${PLANTUML_VER:+ ($PLANTUML_VER)}"
else
    echo "✘ plantuml non installé sur ce PC (ni commande 'plantuml', ni plantuml.jar)"
fi

# ---------------------------------------------------------------------
# 3. Vérification informative de Graphviz (dot)
# ---------------------------------------------------------------------
DOT_CMD=""
if [ -n "${GRAPHVIZ_DOT:-}" ] && [ -x "$GRAPHVIZ_DOT" ]; then
    DOT_CMD="$GRAPHVIZ_DOT"
elif command -v dot >/dev/null 2>&1; then
    DOT_CMD="$(command -v dot)"
fi

if [ -n "$DOT_CMD" ]; then
    DOT_VER="$("$DOT_CMD" -V 2>&1 | head -n 1 | tr -d '\r' || true)"
    echo "✓ graphviz (dot) déjà installé : $DOT_CMD${DOT_VER:+ ($DOT_VER)}"
else
    echo "ℹ graphviz (dot) absent : certains diagrammes (classes, cas d'utilisation, MCD/MLD) peuvent en avoir besoin"
fi

# ---------------------------------------------------------------------
# Mode --check : s'arrête après le diagnostic
# ---------------------------------------------------------------------
if [ "$CHECK_ONLY" -eq 1 ]; then
    if [ -n "$JAVA_CMD" ] && [ -n "$PLANTUML_MODE" ]; then
        echo "✓ Environnement prêt pour le rendu des $NOMBRE diagrammes."
        exit 0
    fi
    echo "✘ Prérequis incomplets pour rendre les diagrammes." >&2
    echo "  Installation conseillée (Debian/Ubuntu) : sudo apt install -y default-jre plantuml graphviz" >&2
    echo "  Installation conseillée (macOS)         : brew install openjdk plantuml graphviz" >&2
    exit 127
fi

# ---------------------------------------------------------------------
# Exécution du rendu
# ---------------------------------------------------------------------
if [ -z "$JAVA_CMD" ]; then
    echo "✘ java introuvable : installez openjdk-17-jre (ou utilisez l'image CI openjdk:17)" >&2
    exit 127
fi

if [ -z "$PLANTUML_MODE" ]; then
    echo "→ plantuml absent, tentative de téléchargement de plantuml.jar (1.2024.0)…"
    if command -v curl >/dev/null 2>&1; then
        if curl -fsSL -o "$ROOT_DIR/plantuml.jar" \
            https://github.com/plantuml/plantuml/releases/download/v1.2024.0/plantuml-1.2024.0.jar; then
            if [ -s "$ROOT_DIR/plantuml.jar" ]; then
                JAR="$ROOT_DIR/plantuml.jar"
                PLANTUML_MODE="jar"
            else
                rm -f "$ROOT_DIR/plantuml.jar"
            fi
        else
            rm -f "$ROOT_DIR/plantuml.jar"
        fi
    fi
fi

if [ -z "$PLANTUML_MODE" ]; then
    echo "✘ plantuml introuvable (réseau bloqué ?)." >&2
    echo "  Installez le paquet 'plantuml' ou téléchargez plantuml.jar :" >&2
    echo "  curl -LO https://github.com/plantuml/plantuml/releases/download/v1.2024.0/plantuml-1.2024.0.jar" >&2
    exit 127
fi

mkdir -p "$OUT_DIR"
echo "== MiniShop — rendu de $NOMBRE diagrammes vers $OUT_DIR =="

EXTRA_ARGS=()
if [ -n "${GRAPHVIZ_DOT:-}" ] && [ -x "$GRAPHVIZ_DOT" ]; then
    EXTRA_ARGS+=(-graphvizdot "$GRAPHVIZ_DOT")
fi

if [ "$PLANTUML_MODE" = "bin" ]; then
    "$PLANTUML_CMD" "${EXTRA_ARGS[@]}" -tpng -charset UTF-8 "$SRC_DIR"/*.puml -o "$OUT_DIR"
else
    "$JAVA_CMD" -jar "$JAR" "${EXTRA_ARGS[@]}" -tpng -charset UTF-8 "$SRC_DIR"/*.puml -o "$OUT_DIR"
fi
code=$?

if [ "$code" -eq 0 ]; then
    echo "✓ $NOMBRE diagrammes rendus dans $OUT_DIR"
else
    echo "✘ PlantUML a échoué (code $code)" >&2
fi
exit "$code"
