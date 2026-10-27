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
    DISTANT="${BRANCHE%%/*}"
    if [ "$DISTANT" != "$BRANCHE" ] && ! git remote get-url "$DISTANT" >/dev/null 2>&1; then
        echo "  ✘ il n'existe aucun remote nommé « $DISTANT » dans cette copie." >&2
        echo "    Un « git fetch $DISTANT … » interrogerait donc un autre serveur (typiquement" >&2
        echo "    le dépôt d'école, qui demande un mot de passe). Ajoutez le remote GitHub :" >&2
        echo "      git remote add github https://github.com/jojo8356/SAE-302.git" >&2
        echo "      git fetch github arena/6e1d3706-sae-302" >&2
        echo "      bash scripts/maj-depuis-branche.sh $BRANCHE" >&2
    else
        echo "  → git fetch $DISTANT   puis réessayez (git branch -r pour la liste)." >&2
    fi
    exit 2
fi

BASE="$(git merge-base HEAD "$BRANCHE" 2>/dev/null)" || {
    echo "✘ aucune histoire commune avec $BRANCHE." >&2
    exit 2
}

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

# ---------------------------------------------------------------------------
# Tri par fichier — indispensable : `git checkout <branche> -- …` INDEXE les
# fichiers chargés. Après une synchro réussie, l'arbre est donc sale par
# construction, et un refus global bloquerait toute mise à jour ultérieure.
#
# Le difficile est de distinguer :
#   a) un fichier que NOUS avons écrit lors d'une synchro précédente (donc
#      modifiable sans risque) ;
#   b) un fichier réellement édité par l'utilisateur (à ne jamais écraser).
# `git status` ne sait pas faire la différence : dans les deux cas le fichier
# diffère de HEAD. D'où le registre .git/minishop-applied, qui mémorise le
# contenu exact écrit par la dernière synchro (chemin + empreinte du blob).
#   - contenu actuel == empreinte mémorisée  → c'est le nôtre, on remplace ;
#   - contenu actuel != empreinte mémorisée  → vrai travail local, on refuse.
# ---------------------------------------------------------------------------
REGISTRE="$RACINE/.git/minishop-applied"

A_JOUR=()
A_ECRIRE=()
A_REECRIRE=()
CONFLITS=()
MOI="scripts/maj-depuis-branche.sh"

for fichier in "${A_CHARGER[@]:-}"; do
    [ -z "$fichier" ] && continue
    # Ne JAMAIS se réécrire en cours d'exécution : bash lit le fichier pendant
    # qu'il tourne, le remplacer peut lui faire exécuter un mélange des deux
    # versions. Sa mise à jour est annoncée, à appliquer au tour suivant.
    if [ "$fichier" = "$MOI" ]; then
        continue
    fi
    # Contenu déjà celui de la branche ? (fichier absent = à écrire)
    if git cat-file -e "$BRANCHE:$fichier" 2>/dev/null \
        && git diff --quiet "$BRANCHE" -- "$fichier" 2>/dev/null; then
        A_JOUR+=("$fichier")
        continue
    fi
    A_ECRIRE+=("$fichier")
done

for fichier in "${A_ECRIRE[@]:-}"; do
    [ -z "$fichier" ] && continue
    if [ -z "$(git status --porcelain -- "$fichier" 2>/dev/null)" ]; then
        continue                                    # intact depuis HEAD : sans risque
    fi
    # Sale : est-ce nous qui l'avons écrit ?
    ACTUEL="$(git hash-object "$fichier" 2>/dev/null || echo '')"
    MEMOIRE=""
    if [ -f "$REGISTRE" ]; then
        MEMOIRE="$(awk -F'\t' -v f="$fichier" '$1 == f { print $2 }' "$REGISTRE" 2>/dev/null | tail -1)"
    fi
    if [ -n "$MEMOIRE" ] && [ "$ACTUEL" = "$MEMOIRE" ] && [ -n "$ACTUEL" ]; then
        A_REECRIRE+=("$fichier")                    # inchangé depuis notre écriture
    else
        CONFLITS+=("$fichier")
    fi
done

if [ "${#CONFLITS[@]}" -gt 0 ] && [ "$FORCER" -ne 1 ]; then
    echo "✘ ${#CONFLITS[@]} fichier(s) modifiés localement seraient écrasés :" >&2
    printf '    %s\n' "${CONFLITS[@]:0:12}" >&2
    if [ "${#CONFLITS[@]}" -gt 12 ]; then
        echo "    … et $(( ${#CONFLITS[@]} - 12 )) autre(s)" >&2
    fi
    echo >&2
    echo "  Ce ne sont PAS des fichiers écrits par une synchro précédente : leur" >&2
    echo "  contenu diffère de ce que ce script avait déposé (ou aucun registre" >&2
    echo "  n'existe encore). Vérifiez ce que vous perdriez :" >&2
    echo "      git diff --stat -- ${CONFLITS[0]}" >&2
    echo "  puis :" >&2
    echo "      git stash            (conserver votre version, relancer)" >&2
    echo "      … --force            (assumer l'écrasement)" >&2
    exit 3
fi

if git cat-file -e "$BRANCHE:$MOI" 2>/dev/null \
    && ! git diff --quiet "$BRANCHE" -- "$MOI" 2>/dev/null; then
    echo "  ⓘ une version plus récente de ce script existe sur $BRANCHE"
    echo "    (non appliquée ici : il ne peut pas se réécrire en cours d'exécution)"
    echo "    git checkout $BRANCHE -- $MOI   puis relancer"
    echo
fi

echo "déjà à jour      : ${#A_JOUR[@]} fichier(s)"
echo "à mettre à jour  : ${#A_ECRIRE[@]} fichier(s) (dont ${#A_REECRIRE[@]} écrit(s) par une synchro précédente)"
echo "à supprimer      : ${#A_SUPPRIMER[@]} fichier(s)"
echo

if [ "${#A_ECRIRE[@]}" -gt 0 ]; then
    # Un seul appel : rapide, et atomique du point de vue de l'index.
    if ! git checkout "$BRANCHE" -- "${A_ECRIRE[@]}"; then
        echo "✘ le chargement a échoué — rien n'est perdu, relancez après vérification." >&2
        exit 1
    fi
    echo "  ${#A_ECRIRE[@]} fichier(s) écrits"

    # Registre : empreinte du contenu réellement déposé, pour reconnaître au
    # prochain passage nos propres écritures et ne pas les confondre avec du
    # travail local.
    : > "$REGISTRE"
    for fichier in "${A_ECRIRE[@]:-}"; do
        [ -z "$fichier" ] && continue
        [ -f "$fichier" ] || continue
        printf '%s\t%s\n' "$fichier" "$(git hash-object "$fichier" 2>/dev/null || echo '')" >> "$REGISTRE"
    done
fi

for fichier in "${A_SUPPRIMER[@]:-}"; do
    [ -z "$fichier" ] && continue
    if git ls-files --error-unmatch "$fichier" >/dev/null 2>&1; then
        git rm -q -- "$fichier"
        echo "  supprimé : $fichier"
    fi
done

# ---------------------------------------------------------------------------
# Fichiers fantômes : présents ici, absents de la base commune ET de la
# branche suivie. Ni du travail local, ni des fichiers de la branche : ce sont
# des résidus d'une synchro ANTÉRIEURE (la branche contenait le fichier à
# l'époque, elle l'a supprimé depuis). Le diff base→branche ne peut pas les
# voir, d'où cette passe séparée.
#
#   - au registre avec la même empreinte → c'est notre dépôt, on supprime ;
#   - sinon → listé « à vérifier » : on ne détruit jamais un fichier dont on
#     ne peut pas prouver qu'il vient de nous (travail local possible).
# ---------------------------------------------------------------------------
FANTOMES_SUPPRIMES=()
FANTOMES_A_VERIFIER=()

while IFS= read -r fichier; do
    [ -z "$fichier" ] && continue
    git cat-file -e "$BRANCHE:$fichier" 2>/dev/null && continue
    git cat-file -e "$BASE:$fichier" 2>/dev/null && continue
    [ -f "$fichier" ] || continue
    MEMOIRE=""
    if [ -f "$REGISTRE" ]; then
        MEMOIRE="$(awk -F'\t' -v f="$fichier" '$1 == f { print $2 }' "$REGISTRE" 2>/dev/null | tail -1)"
    fi
    ACTUEL="$(git hash-object "$fichier" 2>/dev/null || echo '')"
    if [ -n "$MEMOIRE" ] && [ -n "$ACTUEL" ] && [ "$ACTUEL" = "$MEMOIRE" ]; then
        FANTOMES_SUPPRIMES+=("$fichier")
    else
        FANTOMES_A_VERIFIER+=("$fichier")
    fi
done <<< "$(git ls-files -- "${CHEMINS[@]}" 2>/dev/null)"

for fichier in "${FANTOMES_SUPPRIMES[@]:-}"; do
    [ -z "$fichier" ] && continue
    git rm -q -- "$fichier" 2>/dev/null || rm -f "$fichier"
done

if [ "${#FANTOMES_SUPPRIMES[@]}" -gt 0 ] && [ -n "${FANTOMES_SUPPRIMES[0]:-}" ]; then
    echo "  ${#FANTOMES_SUPPRIMES[@]} résidu(s) d'une synchro antérieure supprimé(s) :"
    printf '    %s\n' "${FANTOMES_SUPPRIMES[@]}"
fi

if [ "${#FANTOMES_A_VERIFIER[@]}" -gt 0 ] && [ -n "${FANTOMES_A_VERIFIER[0]:-}" ]; then
    echo "  ${#FANTOMES_A_VERIFIER[@]} fichier(s) absent(s) de la base ET de la branche — à vérifier vous-même"
    echo "  (travail local possible : ce script n'y touche pas) :"
    printf '    %s\n' "${FANTOMES_A_VERIFIER[@]:0:10}"
    echo "    suppression, si ce sont bien des résidus : git rm <fichier>"
fi

echo
echo "✓ copie alignée sur $BRANCHE"
echo "  appliqué : $(git --no-pager diff --shortstat "$BASE" "$BRANCHE" -- "${CHEMINS[@]}")"
RESTANT="$(git --no-pager diff --name-only "$BRANCHE" -- "${CHEMINS[@]}" | wc -l | tr -d ' ')"
if [ "$RESTANT" -gt 0 ]; then
    echo "  $RESTANT fichier(s) diffèrent encore — travail PROPRE à cette copie (jamais touché) :"
    git --no-pager diff --name-only "$BRANCHE" -- "${CHEMINS[@]}" | head -8 | sed 's/^/    /'
fi
# Empreinte de synchro : run.sh s'en sert pour dire si la copie est à jour
# SANS dépendre de HEAD (une synchro de fichiers ne déplace pas la tête de
# branche — la comparer à HEAD produisait une alerte permanente, donc ignorée).
if [ -d .git ]; then
    printf '%s\n%s\n' "$(git rev-parse "$BRANCHE")" "$BRANCHE" > .git/minishop-synchro 2>/dev/null || true
fi

echo
echo "révision du code : $(git rev-parse --short HEAD)$(if [ -n "$(git status --porcelain --untracked-files=no)" ]; then echo ' + modifications locales'; fi)"
echo "vérification : php scripts/db_init.php --check | head -6   (ligne « code : … »)"
