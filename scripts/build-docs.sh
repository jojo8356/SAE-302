#!/usr/bin/env bash
# =====================================================================
# MiniShop — assemblage documentaire complet (CDC §5, README §14.1)
#
#   bash scripts/build-docs.sh                 # HTML + DOCX + PDF + diaporama
#   bash scripts/build-docs.sh --only html     # un seul format
#   bash scripts/build-docs.sh --out dist      # dossier de sortie (défaut : dist)
#   bash scripts/build-docs.sh --no-livrables  # ne pas recopier dans livrables/
#
# Entrées  : README.md, docs/*.md, docs/annexes/*.md, docs/sql-a-ecrire/*.md
# Sorties  : <out>/html/*.html   HTML autonome (images en base64, hors ligne)
#            <out>/pdf/*.pdf     A4 paginé (fpdf2)
#            <out>/docx/*.docx   Word éditable (python-docx)
#            <out>/minishop-soutenance.pptx   diaporama 16:9
#            livrables/          copie versionnée des trois formats + index
#
# Dépendances Python : markdown, fpdf2, python-docx, python-pptx.
# Aucun accès réseau n'est nécessaire. Chaque convertisseur est appelé
# document par document : un échec isolé n'annule pas les autres rendus.
# =====================================================================
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE" || exit 1

SORTIE="dist"
LIVRABLES="livrables"
FORMATS="html pdf docx slides"
COPIER=1

while [ $# -gt 0 ]; do
    case "$1" in
        --only)      FORMATS="$2"; shift 2 ;;
        --out)       SORTIE="$2"; shift 2 ;;
        --no-livrables) COPIER=0; shift ;;
        -h|--help)   sed -n '2,26p' "$0"; exit 0 ;;
        *) echo "✘ option inconnue : $1" >&2; exit 2 ;;
    esac
done

bleu()  { printf '\033[1;34m%s\033[0m\n' "$*"; }
vert()  { printf '\033[1;32m%s\033[0m\n' "$*"; }
rouge() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; }

command -v python3 >/dev/null 2>&1 || { rouge "✘ python3 introuvable"; exit 2; }

manquants=""
python3 - <<'PY' || manquants="1"
import importlib, sys
attendus = {"markdown": "markdown", "fpdf": "fpdf2", "docx": "python-docx", "pptx": "python-pptx"}
absents = []
for module, paquet in attendus.items():
    try:
        importlib.import_module(module)
    except ImportError:
        absents.append(paquet)
if absents:
    print("  paquets manquants : " + ", ".join(absents) + " → pip install " + " ".join(absents), file=sys.stderr)
    sys.exit(1)
PY
if [ -n "$manquants" ]; then
    rouge "✘ dépendances Python incomplètes : pip install markdown fpdf2 python-docx python-pptx"
    exit 2
fi

# --- sources ---------------------------------------------------------------
SOURCES=()
[ -f README.md ] && SOURCES+=("README.md")
while IFS= read -r fichier; do SOURCES+=("$fichier"); done < <(ls docs/[0-9]*.md docs/DA.md 2>/dev/null | sort)
while IFS= read -r fichier; do SOURCES+=("$fichier"); done < <(ls docs/annexes/*.md 2>/dev/null | sort)
while IFS= read -r fichier; do SOURCES+=("$fichier"); done < <(ls docs/sql-a-ecrire/*.md 2>/dev/null | sort)

if [ ${#SOURCES[@]} -eq 0 ]; then
    rouge "✘ aucune source Markdown trouvée"
    exit 2
fi

# --- préparation -----------------------------------------------------------
mkdir -p "$SORTIE"/{html,pdf,docx}
for format in $FORMATS; do
    case "$format" in
        html|pdf|docx|slides) ;;
        *) rouge "✘ format inconnu : $format (html, pdf, docx, slides)"; exit 2 ;;
    esac
done

nombre_documents=0
nombre_erreurs=0
declare -a LIGNES=()

traiter() {
    local format="$1" source="$2" destination="$3" etiquette="$4"
    local dossier
    dossier="$(dirname "$destination")"
    mkdir -p "$dossier"
    if python3 "scripts/md_to_${format}.py" "$source" --out "$destination" >/dev/null 2>"$SORTIE/.erreur"; then
        local taille
        taille=$(($(wc -c <"$destination") / 1024))
        LIGNES+=("  ✓ ${etiquette} — $(basename "$destination") (${taille} Ko)")
        nombre_documents=$((nombre_documents + 1))
    else
        rouge "  ✘ ${etiquette} : $(tail -n 2 "$SORTIE/.erreur" | tr '\n' ' ')"
        nombre_erreurs=$((nombre_erreurs + 1))
    fi
}

bleu "== MiniShop — assemblage documentaire =="
echo "   sources   : ${#SOURCES[@]} documents Markdown"
echo "   formats   : $FORMATS"
echo "   sortie    : $SORTIE/"
echo

for source in "${SOURCES[@]}"; do
    nom="$(basename "${source%.md}")"
    for format in $FORMATS; do
        case "$format" in
            html)   traiter html "$source" "$SORTIE/html/$nom.html" "HTML" ;;
            pdf)    traiter pdf  "$source" "$SORTIE/pdf/$nom.pdf"   "PDF" ;;
            docx)   traiter docx "$source" "$SORTIE/docx/$nom.docx" "DOCX" ;;
            slides) ;;
        esac
    done
done

if printf '%s' "$FORMATS" | grep -qw slides; then
    if [ -f docs/12-diaporama-soutenance.md ]; then
        traiter pptx docs/12-diaporama-soutenance.md "$SORTIE/minishop-soutenance.pptx" "Diaporama"
    else
        rouge "  ✘ diaporama : docs/12-diaporama-soutenance.md absent"
        nombre_erreurs=$((nombre_erreurs + 1))
    fi
fi

rm -f "$SORTIE/.erreur"

# --- copie versionnée ------------------------------------------------------
if [ "$COPIER" -eq 1 ]; then
    mkdir -p "$LIVRABLES"/{html,pdf,docx}
    for dossier in html pdf docx; do
        if [ -d "$SORTIE/$dossier" ]; then
            cp -f "$SORTIE/$dossier"/*."$dossier" "$LIVRABLES/$dossier"/ 2>/dev/null || true
        fi
    done
    [ -f "$SORTIE/minishop-soutenance.pptx" ] && cp -f "$SORTIE/minishop-soutenance.pptx" "$LIVRABLES"/
    {
        echo "# Livrables — MiniShop (SAÉ 302)"
        echo
        echo "Copie **versionnée** des documents rendus, régénérée par \`bash scripts/build-docs.sh\`."
        echo "Les mêmes fichiers existent dans \`dist/\` (répertoire généré, hors Git, cf. \`.gitignore\`)."
        echo
        echo "| Format | Dossier | Usage |"
        echo "| --- | --- | --- |"
        echo "| HTML autonome | \`livrables/html/\` | lecture hors ligne, images intégrées en base64 |"
        echo "| PDF A4 | \`livrables/pdf/\` | dépôt et impression |"
        echo "| Word | \`livrables/docx/\` | relecture et annotations |"
        echo "| Diaporama | \`livrables/minishop-soutenance.pptx\` | soutenance (12 diapositives, 16:9) |"
        echo
        echo "## Documents"
        echo
        for fichier in "$LIVRABLES"/html/*.html; do
            [ -e "$fichier" ] || continue
            nom="$(basename "$fichier" .html)"
            echo "- **${nom}** — [HTML](html/${nom}.html) · [PDF](pdf/${nom}.pdf) · [Word](docx/${nom}.docx)"
        done
        echo
        echo "## Régénérer"
        echo
        echo '```bash'
        echo "bash scripts/build-docs.sh              # tout (HTML + PDF + DOCX + diaporama)"
        echo "bash scripts/build-docs.sh --only pdf   # un seul format"
        echo '```'
        echo
        echo "Dépendances : \`markdown\`, \`fpdf2\`, \`python-docx\`, \`python-pptx\` (\`pip install -r requirements-docs.txt\`)."
    } > "$LIVRABLES/README.md"
fi

# --- bilan -----------------------------------------------------------------
echo
printf '%s\n' "${LIGNES[@]}"
echo
if [ "$nombre_erreurs" -gt 0 ]; then
    rouge "✘ ${nombre_erreurs} rendu(s) en échec — ${nombre_documents} document(s) produit(s)"
    exit 1
fi
vert "✓ ${nombre_documents} rendu(s) produit(s) — HTML autonome, PDF A4, Word et diaporama."
[ "$COPIER" -eq 1 ] && echo "  copie versionnée : $LIVRABLES/"
exit 0
