#!/usr/bin/env bash
# =====================================================================
# MiniShop — met la copie de travail à jour depuis une branche distante.
#
#   scripts/maj-depuis-branche.sh github/arena/6e1d3706-sae-302
#   scripts/maj-depuis-branche.sh github/arena/… app scripts tests run.sh
#   scripts/maj-depuis-branche.sh github/arena/… --force    (arbre sale accepté)
#
# Pourquoi ce script existe : « git checkout <branche> -- fichier1 fichier2 … »
# avec une liste de noms écrite à la main laisse régulièrement un fichier
# périmé derrière soi — et un message d'erreur d'une révision antérieure
# ressemble alors à un bug de la révision courante. Deuxième piège, découvert
# en vrai : la liste calculée par `git diff HEAD <branche> --name-only` contient
# les fichiers que la branche n'a JAMAIS eus (ils n'existent que dans la copie
# locale), et `git checkout` échoue sur eux avec « ne correspond à aucun fichier
# connu de git ». Ce script ne raisonne donc que sur la divergence réelle :
#
#   base = git merge-base HEAD <branche>
#   - fichier ajouté/modifié depuis la base  → git checkout <branche> -- <fichier>
#   - fichier supprimé depuis la base        → git rm <fichier>
#   - fichier absent de la base              → JAMAIS touché (il n'appartient
#                                              qu'à la copie locale)
#
# Il n'écrit rien avant d'avoir listé ce qu'il va faire, refuse un arbre sale
# (les modifications locales seraient écrasées) et affiche la révision obtenue.
# =====================================================================
set -uo pipefail

usage() {
    cat <<'TXT'
usage : scripts/maj-depuis-branche.sh <branche> [chemins…] [--force]

  <branche>   référence distante, ex. github/arena/6e1d3706-sae-302
  [chemins]   limite la mise à jour (défaut : tout le dépôt)
  --force     accepte un arbre de travail sale (les modifications locales
              des fichiers concernés seront écrasées)
TXT
}

BRANCHE=""
FORCER=0
CHEMINS=()

for argument in "$@"; do
    case "$argument" in
        -h|--help) usage; exit 0 ;;
        --force)   FORCER=1 ;;
        -*)        echo "✘ option inconnue : $argument" >&2; usage; exit 2 ;;
        *)
            if [ -z "$BRANCHE" ]; then
                BRANCHE="$argument"
            else
                CHEMINS+=("$argument")
            fi
            ;;
    esac
done

if [ -z "$BRANCHE" ]; then
    usage >&2
    exit 2
fi

if [ "${#CHEMINS[@]}" -eq 0 ]; then
    CHEMINS=('.')
fi

RACINE="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    echo "✘ ce dossier n'est pas un dépôt Git." >&2
    exit 2
}
cd "$RACINE" || exit 2

if ! git rev-parse --verify --quiet "$BRANCHE^{commit}" >/dev/null; then
    echo "✘ référence inconnue : $BRANCHE" >&2
    echo "  → git fetch <remote>   puis réessayez (git branch -r pour la liste)." >&2
    exit 2
fi

BASE="$(git merge-base HEAD "$BRANCHE" 2>/dev/null)" || {
    echo "✘ aucune histoire commune avec $BRANCHE." >&2
    exit 2
}

if [ -n "$(git status --porcelain)" ] && [ "$FORCER" -ne 1 ]; then
    echo "✘ arbre de travail sale : les modifications locales seraient écrasées." >&2
    echo "  git stash        (ou git commit) puis relancez, ou --force pour assumer." >&2
    exit 3
fi

echo "base commune : $(git rev-parse --short "$BASE")   branche : $BRANCHE ($(git rev-parse --short "$BRANCHE"))"
echo

# --no-renames : une réinstallation apparaît comme « supprimé + ajouté », ce
# qui évite d'avoir à traiter le cas R (renommage) séparément.
ETATS="$(git diff --no-renames --name-status "$BASE" "$BRANCHE" -- "${CHEMINS[@]}")"
if [ -z "$ETATS" ]; then
    echo "✓ rien à mettre à jour : la copie est déjà alignée sur $BRANCHE."
    exit 0
fi

A_CHARGER=()
A_SUPPRIMER=()

while IFS=$'\t' read -r statut chemin; do
    [ -z "${statut:-}" ] && continue
    [ -z "${chemin:-}" ] && continue
    case "$statut" in
        A|M|C|T) A_CHARGER+=("$chemin") ;;
        D)       A_SUPPRIMER+=("$chemin") ;;
        *)       A_CHARGER+=("$chemin") ;;
    esac
done <<< "$ETATS"

echo "à mettre à jour : ${#A_CHARGER[@]} fichier(s)"
echo "à supprimer     : ${#A_SUPPRIMER[@]} fichier(s)"
echo

if [ "${#A_CHARGER[@]}" -gt 0 ]; then
    # Un seul appel : rapide, et atomique du point de vue de l'index.
    if ! git checkout "$BRANCHE" -- "${A_CHARGER[@]}"; then
        echo "✘ le chargement a échoué — rien n'est perdu, relancez après vérification." >&2
        exit 1
    fi
fi

for fichier in "${A_SUPPRIMER[@]:-}"; do
    [ -z "$fichier" ] && continue
    if git ls-files --error-unmatch "$fichier" >/dev/null 2>&1; then
        git rm -q -- "$fichier"
        echo "  supprimé : $fichier"
    fi
done

echo
echo "✓ copie alignée sur $BRANCHE"
echo "  appliqué : $(git --no-pager diff --shortstat "$BASE" "$BRANCHE" -- "${CHEMINS[@]}")"
RESTANT="$(git --no-pager diff --name-only "$BRANCHE" -- "${CHEMINS[@]}" | wc -l | tr -d ' ')"
if [ "$RESTANT" -gt 0 ]; then
    echo "  $RESTANT fichier(s) diffèrent encore (fichiers propres à cette copie, ex. travail local) :"
    git --no-pager diff --name-only "$BRANCHE" -- "${CHEMINS[@]}" | head -8 | sed 's/^/    /'
fi
echo
echo "révision du code : $(git rev-parse --short HEAD)$(if [ -n "$(git status --porcelain --untracked-files=no)" ]; then echo ' + modifications locales'; fi)"
echo "vérification : php scripts/db_init.php --check | head -6   (ligne « code : … »)"
