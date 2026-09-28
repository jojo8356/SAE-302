#!/usr/bin/env bash
#
# MiniShop — 10 contrôles statiques de sécurité (CDC §15.3, CT-08, PRÉ-4).
# Exécuté par le job `security-scan` de .gitlab-ci.yml (prod) et de
# .github/workflows/ci.yml (dev). Un contrôle rouge bloque le merge.
#
# ----------------------------------------------------------------------------
# PRINCIPE : ne jamais chercher un MOT, toujours chercher un MOTIF DANGEREUX.
# ----------------------------------------------------------------------------
# Un balayage naïf du genre `grep -rniE "mot.?de.?passe" app/` remonte 63 lignes
# sur ce dépôt, dont une écrasante majorité de HTML parfaitement légitime :
#
#     <input type="password" id="mot_de_passe" name="mot_de_passe" required>
#
# Un nom de champ de formulaire n'est pas un secret. Chaque contrôle ci-dessous
# exige donc une CONSTRUCTION syntaxique précise (affectation d'un littéral,
# interpolation dans une chaîne SQL, appel de fonction interdit…) et filtre
# explicitement le bruit HTML. Objectif : zéro faux positif, sinon l'équipe
# apprend à ignorer la CI — ce qui est pire que pas de CI du tout.
#
# Usage :
#   bash tests/security/controles.sh              # les 10 contrôles
#   bash tests/security/controles.sh --verbose    # + détail des lignes inspectées
#   bash tests/security/controles.sh --selftest   # prouve que les motifs mordent
#                                                 # (fixtures jouets, hors dépôt)
#
# Dépendances : bash, grep (ERE/POSIX, sans -P), find, et git pour le contrôle 10
# (secrets absents de l'index). Testé sous alpine:3.20
# (`apk add bash grep findutils git`) et ubuntu-latest.
#
# Sortie : 0 si les 10 contrôles sont conformes, 1 sinon (bloque le pipeline),
# 2 en cas d'erreur d'usage.

set -uo pipefail

# ---------------------------------------------------------------- contexte ---
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$RACINE" || exit 2

VERBEUX=0
SELFTEST=0
for arg in "$@"; do
    case "$arg" in
        -v|--verbose)  VERBEUX=1 ;;
        --selftest)    SELFTEST=1 ;;
        -h|--help)
            sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) printf 'Option inconnue : %s\n' "$arg" >&2; exit 2 ;;
    esac
done

# Couleurs seulement en terminal (les logs de CI restent lisibles en texte brut).
if [ -t 1 ] && [ "${TERM:-dumb}" != "dumb" ]; then
    C_OK=$'\033[32m'; C_KO=$'\033[31m'; C_INFO=$'\033[36m'; C_GRAS=$'\033[1m'; C_RAZ=$'\033[0m'
else
    C_OK=''; C_KO=''; C_INFO=''; C_GRAS=''; C_RAZ=''
fi

ECHECS=0
NUMERO=0
declare -a RESUME=()

# ------------------------------------------------------------- utilitaires ---

# Classes de caractères réutilisables : Q = une quote (simple ou double),
# NQ = tout sauf une quote. Écrites une fois ici pour rester lisibles ensuite.
Q="[\"']"
NQ="[^\"']"

titre() {
    NUMERO=$((NUMERO + 1))
    printf '%s[%02d/10]%s %s\n' "$C_GRAS" "$NUMERO" "$C_RAZ" "$1"
}

ok() {
    printf '        %s✓%s %s\n' "$C_OK" "$C_RAZ" "$1"
    RESUME+=("ok|$NUMERO|$1")
}

ko() {
    printf '        %s✘ %s%s\n' "$C_KO" "$1" "$C_RAZ"
    ECHECS=$((ECHECS + 1))
    RESUME+=("ko|$NUMERO|$1")
}

info() {
    [ "$VERBEUX" -eq 1 ] && printf '        %sℹ%s %s\n' "$C_INFO" "$C_RAZ" "$1"
    return 0
}

# Liste des fichiers PHP de l'application (jamais vendor/, node_modules/…).
fichiers_php() {
    find app public scripts -type f -name '*.php' \
        -not -path '*/vendor/*' \
        -not -path '*/node_modules/*' \
        2>/dev/null | sort
}

# Périmètre applicatif seul : ce qui est exposé à une requête HTTP.
# scripts/ en est exclu (outils d'exploitation lancés à la main en console,
# avec les identifiants de la base : ils ne reçoivent aucune entrée client).
fichiers_php_application() {
    find app public -type f -name '*.php' \
        -not -path '*/vendor/*' \
        -not -path '*/node_modules/*' \
        2>/dev/null | sort
}

# Liste des vues.
fichiers_vues() {
    find app/View -type f -name '*.php' 2>/dev/null | sort
}

# grep ERE sur une liste de fichiers passée en entrée standard.
# Renvoie 0 (et imprime) s'il y a au moins une correspondance.
# Le motif passe par -e : certains commencent par « -> » et seraient sinon
# pris pour des options (grep: invalid option -- '>').
chercher() {
    local motif="$1"; shift
    local fichiers
    fichiers="$(cat)"
    [ -z "$fichiers" ] && return 1
    printf '%s\n' "$fichiers" | tr '\n' '\0' | xargs -0 grep -nHE "$@" -e "$motif" 2>/dev/null
}

# Affiche les premières lignes fautives, tronquées pour ne pas noyer le log.
extrait() {
    printf '%s\n' "$1" | head -n 10 | sed 's/^/          → /'
    local total
    total="$(printf '%s\n' "$1" | wc -l | tr -d ' ')"
    [ "$total" -gt 10 ] && printf '          → … (%s lignes au total)\n' "$total"
    return 0
}

# =============================================================================
#  CONTRÔLE 1 — Injection SQL : aucune interpolation ni concaténation
# =============================================================================
# Ne mord que si une variable entre DANS la chaîne SQL : query("… $id …") ou
# query('… ' . $id). Une requête statique — pdo->exec("SET FOREIGN_KEY_CHECKS=0")
# dans l'outil de volumétrie — reste autorisée : rien d'externe n'y entre.
#
# Périmètre BLOQUANT : app/ et public/, exactement celui du CDC §15.3, c'est-à-dire
# tout ce qu'une requête HTTP peut atteindre. scripts/ est balayé en INFORMATIF :
# ce sont des outils d'exploitation (déploiement, volumétrie) lancés en console
# par un opérateur qui possède déjà les identifiants de la base ; on y interpole
# parfois un nom de TABLE issu d'une liste littérale — chose qu'aucun paramètre
# lié ne permet d'exprimer en SQL. Les faire échouer bloquerait le pipeline sur
# une construction sûre, exactement le travers qu'on corrige ici.
controle_sql() {
    titre "Injection SQL — aucune interpolation/concaténation dans query()/exec()/prepare()"

    local m_interp='(query|exec|prepare)[[:space:]]*\([[:space:]]*"[^"]*\$'
    local m_concat='(query|exec|prepare)[[:space:]]*\([[:space:]]*'"$Q$NQ"'*(SELECT|INSERT|UPDATE|DELETE|CALL|WHERE|FROM)'"$NQ"'*'"$Q"'[[:space:]]*\.'

    local interp concat trouve=0
    interp="$(fichiers_php_application | chercher "$m_interp")"
    concat="$(fichiers_php_application | chercher "$m_concat")"

    if [ -n "$interp" ]; then
        ko "variable interpolée dans une chaîne SQL (app/ public/)"
        extrait "$interp"
        trouve=1
    fi
    if [ -n "$concat" ]; then
        ko "concaténation d'une variable dans une chaîne SQL (app/ public/)"
        extrait "$concat"
        trouve=1
    fi

    if [ "$trouve" -eq 0 ]; then
        ok "tout le SQL applicatif passe par des requêtes préparées / procédures (SEC-01)"
    fi

    # Volet informatif sur les scripts d'exploitation.
    local hors_perimetre
    hors_perimetre="$(find scripts -type f -name '*.php' 2>/dev/null | sort \
        | chercher "$m_interp")"
    if [ -n "$hors_perimetre" ]; then
        info "scripts/ (hors périmètre HTTP, non bloquant) : $(printf '%s\n' "$hors_perimetre" | wc -l | tr -d ' ') interpolation(s) — revue manuelle"
        [ "$VERBEUX" -eq 1 ] && extrait "$hors_perimetre"
    fi
    return 0
}

# =============================================================================
#  CONTRÔLE 2 — XSS : aucune sortie brute dans les vues
# =============================================================================
# Toute donnée affichée passe par e() (htmlspecialchars) ou par un formateur
# qui échappe lui-même. Exception documentée : $contenu dans layout.php, qui
# est le HTML de la sous-vue déjà rendu par Controller::render().
# Toute autre exception doit porter le marqueur « HTML-SAFE » en commentaire.
controle_xss() {
    titre "XSS — aucune sortie non échappée dans app/View/ (e() obligatoire)"

    local brut
    brut="$(fichiers_vues | chercher '(echo|<\?=)[[:space:]]*\$[A-Za-z_]' \
        | grep -vE '\be\(|euros\(|date_fr\(|statut_libelle\(|etat_libelle\(|csrf_field\(' \
        | grep -vE '\$v\(|\$qs\(' \
        | grep -vE 'HTML-SAFE' \
        | grep -vE '^app/View/layout\.php:[0-9]+:[[:space:]]*<\?=[[:space:]]*\$contenu[[:space:]]*\?>')"

    if [ -n "$brut" ]; then
        ko "sortie non échappée dans une vue (SEC-02 / test S-02)"
        extrait "$brut"
    else
        ok "toutes les sorties de vue sont échappées (helper e())"
    fi

    # Garde-fou : le helper doit exister et utiliser ENT_QUOTES.
    if grep -qE 'htmlspecialchars\(.*ENT_QUOTES' app/Security/xss.php 2>/dev/null; then
        info "e() = htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')"
    else
        ko "app/Security/xss.php : e() absent ou sans ENT_QUOTES"
    fi
}

# =============================================================================
#  CONTRÔLE 3 — Mot de passe / secret en dur   ← le contrôle historiquement faux
# =============================================================================
# On exige une AFFECTATION D'UN LITTÉRAL, pas la simple présence du mot :
#   a) $password = "hunter2"           variable PHP ← chaîne littérale
#   b) define('DB_PASS', 'hunter2')    constante hachée en dur
#   c) 'db_pass' => 'hunter2'          clé de tableau de configuration
#   d) ->setPassword("hunter2")        setter appelé avec un littéral
#   e) mysqli_connect($h, $u, "hunter2")
# Ne mordent PAS : name="mot_de_passe", id=, for=, <input type="password">,
# les libellés, ni $mdp = $this->str('mot_de_passe') (valeur issue d'une
# variable, pas d'un littéral), ni password_hash()/password_verify().
controle_secret_en_dur() {
    titre "Secret en dur — affectation d'un littéral à un mot de passe / une clé"

    # (a) variable PHP « mot de passe » = littéral d'au moins 3 caractères,
    #     sans $ à l'intérieur (sinon c'est une interpolation, pas un secret figé).
    local m_var='\$(mot_de_passe|motdepasse|mdp|password|passwd|pass|pwd|secret|token|api_key|apikey)[A-Za-z0-9_]*[[:space:]]*=[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"

    # (b) constante / variable d'environnement définie en dur
    local m_const='(define|putenv|setenv)[[:space:]]*\([[:space:]]*'"$Q"'[A-Za-z_]*(PASS|PASSWD|PWD|MOT_DE_PASSE|SECRET|TOKEN|API_KEY)[A-Za-z_]*'"$Q"'[[:space:]]*,[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"

    # (c) clé de configuration = littéral (la clé doit se FERMER juste après le
    #     mot : 'mot_de_passe_hash' => … n'est donc pas concerné)
    local m_conf="$Q"'(db_pass|db_password|mot_de_passe|motdepasse|password|passwd|pwd|secret|api_key)'"$Q"'[[:space:]]*=>[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"

    # (d) setter appelé avec un littéral
    local m_setter='->set[Pp]ass(word)?[[:space:]]*\([[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"

    # (e) connexion SGBD avec le mot de passe en 3e argument littéral
    local m_dsn='(mysqli_connect|new[[:space:]]+(PDO|mysqli))[[:space:]]*\(.*,[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"'[[:space:]]*[,)]'

    # Filet de sécurité : même si un motif mordait dans un fichier de vue,
    # on écarte tout ce qui est manifestement du balisage de formulaire.
    local bruit_html='name=|id=|for=|placeholder=|autocomplete=|<input|<label|type="password"'
    # Et tout ce qui relève du hachage (ce n'est pas un secret stocké en clair).
    local bruit_hash='password_hash|password_verify|PASSWORD_BCRYPT|PASSWORD_DEFAULT|PASSWORD_ARGON'

    local trouve=""
    local motif
    for motif in "$m_var" "$m_const" "$m_conf" "$m_setter" "$m_dsn"; do
        local hits
        hits="$(fichiers_php | chercher "$motif" \
            | grep -vEi -e "$bruit_html" \
            | grep -vE -e "$bruit_hash" \
            | grep -vE '^[^:]+:[0-9]+:[[:space:]]*(\*|//|#)')"
        [ -n "$hits" ] && trouve="${trouve}${hits}"$'\n'
    done
    trouve="$(printf '%s' "$trouve" | grep -v '^$')"

    if [ -n "$trouve" ]; then
        ko "secret littéral affecté dans le code (SEC-06)"
        extrait "$trouve"
    else
        ok "aucun secret littéral — les identifiants viennent de app/Config/env.php"
    fi

    # Contre-épreuve : le balisage HTML « mot de passe » est bien présent et
    # bien ignoré. C'est ce que l'ancien contrôle prenait pour une faille.
    local champs
    champs="$(fichiers_vues | chercher 'type="password"' | wc -l | tr -d ' ')"
    info "$champs champ(s) <input type=\"password\"> ignoré(s) : un nom de champ n'est pas un secret"
}

# =============================================================================
#  CONTRÔLE 4 — Stockage des mots de passe : haché, jamais en clair
# =============================================================================
controle_hachage() {
    titre "Hachage — password_hash/password_verify, aucun MD5/SHA1, aucun clair persisté"

    if ! fichiers_php | chercher 'password_hash[[:space:]]*\(' -l >/dev/null; then
        ko "password_hash() introuvable : les mots de passe seraient stockés en clair (RB-12)"
    else
        ok "password_hash() utilisé à la création et au changement de mot de passe"
    fi

    if ! fichiers_php | chercher 'password_verify[[:space:]]*\(' -l >/dev/null; then
        ko "password_verify() introuvable : comparaison de mots de passe non sûre"
    else
        ok "password_verify() utilisé à l'authentification (client et admin)"
    fi

    local faible
    faible="$(fichiers_php | chercher '(md5|sha1|crypt)[[:space:]]*\([^)]*(pass|mdp|mot_de_passe|secret)')"
    if [ -n "$faible" ]; then
        ko "algorithme de hachage obsolète appliqué à un mot de passe"
        extrait "$faible"
    else
        ok "aucun md5()/sha1()/crypt() sur un mot de passe"
    fi

    # Le champ persisté doit être le hash, pas le clair.
    local clair
    clair="$(fichiers_php | chercher "$Q"'mot_de_passe'"$Q"'[[:space:]]*=>[[:space:]]*\$' | grep -vE 'hash')"
    if [ -n "$clair" ]; then
        ko "un mot de passe en clair semble écrit dans le store (colonne 'mot_de_passe')"
        extrait "$clair"
    else
        ok "seule la colonne mot_de_passe_hash est écrite"
    fi
}

# =============================================================================
#  CONTRÔLE 5 — Contrôle d'appartenance (IDOR) sur les commandes
# =============================================================================
# Test offensif S-07 : /mes-commandes/25 ne doit rien révéler si la commande 25
# appartient à quelqu'un d'autre. Il faut une comparaison explicite.
controle_appartenance() {
    titre "IDOR — toute commande servie à un client est comparée à son id_client"

    local ctrl repo
    ctrl="$(chercher 'id_client.*!==.*\$idClient|\$idClient.*!==.*id_client' <<< 'app/Controller/CommandeController.php')"
    repo="$(chercher 'id_client.*!==.*\$idClient|\$idClient.*!==.*id_client' <<< 'app/Repository/CommandeRepository.php')"

    if [ -n "$ctrl" ]; then
        ok "CommandeController vérifie l'appartenance avant d'afficher une commande"
        info "$(printf '%s' "$ctrl" | head -n 1)"
    else
        ko "CommandeController : aucune comparaison id_client détectée (test S-07)"
    fi

    if [ -n "$repo" ]; then
        ok "CommandeRepository refuse confirmOrder/cancelOrder sur une commande tierce"
    else
        ko "CommandeRepository : aucune garde d'appartenance sur confirm/cancel"
    fi

    # Les routes client doivent toutes porter la garde 'client'.
    local sans_garde
    sans_garde="$(grep -nE "routeur->(get|post)\('/(mes-commandes|compte|commande)" public/index.php 2>/dev/null \
        | grep -vE ",[[:space:]]*'client'\)")"
    if [ -n "$sans_garde" ]; then
        ko "route de l'espace client déclarée sans garde 'client'"
        extrait "$sans_garde"
    else
        ok "toutes les routes de l'espace client portent la garde 'client'"
    fi
}

# =============================================================================
#  CONTRÔLE 6 — Session durcie
# =============================================================================
controle_session() {
    titre "Session — HttpOnly, SameSite, use_strict_mode, régénération d'identifiant"

    local f=app/Security/Auth.php
    if [ ! -f "$f" ]; then
        ko "$f introuvable"
        return
    fi

    local manquant=""
    grep -qE "'httponly'[[:space:]]*=>[[:space:]]*true" "$f" || manquant="$manquant HttpOnly"
    grep -qE "'samesite'[[:space:]]*=>[[:space:]]*'(Lax|Strict)'" "$f" || manquant="$manquant SameSite"
    grep -qE "session\.use_strict_mode" "$f" || manquant="$manquant use_strict_mode"
    grep -qE "session_regenerate_id" "$f" || manquant="$manquant session_regenerate_id"

    if [ -n "$manquant" ]; then
        ko "durcissement de session incomplet :$manquant (SEC-04 / test S-04)"
    else
        ok "cookie HttpOnly + SameSite=Lax, mode strict, identifiant régénéré à l'élévation"
    fi

    # L'identifiant doit être régénéré à CHAQUE connexion (client et admin).
    local connexions regen
    connexions="$(grep -cE 'function connecter(Client|Admin)' "$f")"
    regen="$(grep -cE 'self::regenererId\(\)' "$f")"
    if [ "$connexions" -gt 0 ] && [ "$regen" -ge "$connexions" ]; then
        ok "régénération appelée $regen fois pour $connexions point(s) d'entrée d'authentification"
    else
        ko "une connexion au moins n'appelle pas regenererId() (fixation de session)"
    fi
}

# =============================================================================
#  CONTRÔLE 7 — CSRF sur toutes les écritures
# =============================================================================
controle_csrf() {
    titre "CSRF — jeton comparé en temps constant et vérifié par les contrôleurs mutatifs"

    if grep -qE 'hash_equals[[:space:]]*\(' app/Security/Csrf.php 2>/dev/null; then
        ok "Csrf::verifier() compare avec hash_equals() (pas de ==)"
    else
        ko "app/Security/Csrf.php : comparaison du jeton non constante ou absente"
    fi

    if grep -qE 'random_bytes[[:space:]]*\(' app/Security/Csrf.php 2>/dev/null; then
        ok "jeton tiré de random_bytes() (CSPRNG)"
    else
        ko "app/Security/Csrf.php : jeton non cryptographique"
    fi

    # Quels contrôleurs sont RÉELLEMENT la cible d'une route POST ? On lit la
    # table de routage plutôt que de deviner : Controller::str() lit
    # « $_POST[$cle] ?? $_GET[$cle] », donc un contrôleur en lecture seule
    # (CatalogueController : /catalogue, /recherche, /produit/{slug}, tous en
    # GET) utilise str() sans traiter le moindre POST. Exiger un jeton CSRF de
    # sa part serait un faux positif — et un formulaire de recherche en GET n'a
    # pas à en porter (OWASP : CSRF ne concerne que les requêtes qui modifient
    # un état).
    local cibles_post
    cibles_post="$(grep -oE "routeur->post\('[^']*',[[:space:]]*\[[A-Za-z_]+::class" public/index.php 2>/dev/null \
        | grep -oE '[A-Za-z_]+::class' | sed 's/::class//' | sort -u)"

    if [ -z "$cibles_post" ]; then
        ko "aucune route POST trouvée dans public/index.php : table de routage illisible"
    else
        local manquants="" classe fichier
        for classe in $cibles_post; do
            fichier="$(find app/Controller -name "${classe}.php" | head -n 1)"
            if [ -z "$fichier" ]; then
                manquants="$manquants ${classe}(introuvable)"
                continue
            fi
            grep -qE 'verifierCsrf\(\)|Csrf::verifier\(\)' "$fichier" || manquants="$manquants $classe"
        done

        if [ -n "$manquants" ]; then
            ko "contrôleur(s) cible(s) d'une route POST sans vérification CSRF :$manquants"
        else
            ok "les $(printf '%s\n' "$cibles_post" | wc -l | tr -d ' ') contrôleurs cibles d'une route POST appellent verifierCsrf() (SEC-05 / S-05)"
        fi
    fi

    # Chaque formulaire POST doit embarquer le champ caché.
    local formulaires sans_jeton=""
    for fichier in $(fichiers_vues); do
        formulaires="$(grep -cE '<form[^>]*method="post"' "$fichier")"
        if [ "$formulaires" -gt 0 ]; then
            grep -qE 'csrf_field\(\)|Csrf::champ\(\)|name="_csrf"' "$fichier" || sans_jeton="$sans_jeton $fichier"
        fi
    done
    if [ -n "$sans_jeton" ]; then
        ko "formulaire(s) POST sans champ _csrf :$sans_jeton"
    else
        ok "tous les formulaires POST embarquent le champ _csrf"
    fi
}

# =============================================================================
#  CONTRÔLE 8 — Cloisonnement du back-office
# =============================================================================
controle_back_office() {
    titre "Back-office — aucune route d'inscription admin, toute route /admin gardée"

    local inscription
    inscription="$(grep -nEi "routeur->(get|post)\('/admin/(inscription|register|signup)" public/index.php 2>/dev/null)"
    if [ -n "$inscription" ]; then
        ko "route publique de création de compte administrateur (RB-13)"
        extrait "$inscription"
    else
        ok "aucune route d'inscription admin : les comptes sont créés par un SUPER"
    fi

    # Toute route /admin doit porter 'admin' ou 'super'. Seules exceptions : les
    # routes d'authentification elles-mêmes (on ne peut pas exiger d'être
    # connecté pour se connecter).
    local sans_garde
    sans_garde="$(grep -nE "routeur->(get|post)\('/admin" public/index.php 2>/dev/null \
        | grep -vE "/admin/(connexion|deconnexion)'" \
        | grep -vE ",[[:space:]]*'(admin|super)'\)")"
    if [ -n "$sans_garde" ]; then
        ko "route /admin sans garde d'accès"
        extrait "$sans_garde"
    else
        ok "toutes les routes /admin portent une garde 'admin' ou 'super'"
    fi

    # La gestion de l'équipe est réservée au rôle SUPER.
    if grep -qE "routeur->(get|post)\('/admin/equipe[^']*',[[:space:]]*\[[^]]*\],[[:space:]]*'super'\)" public/index.php 2>/dev/null; then
        ok "/admin/equipe réservé au rôle SUPER"
    else
        ko "/admin/equipe accessible à un gestionnaire (élévation de privilège)"
    fi
}

# =============================================================================
#  CONTRÔLE 9 — Exécution de code / fonctions interdites
# =============================================================================
controle_fonctions_interdites() {
    titre "Exécution de code — eval, shell_exec, extract(\$_GET), variables variables"

    local dangereux
    dangereux="$(fichiers_php | chercher '(^|[^A-Za-z0-9_>$])(eval|shell_exec|passthru|proc_open|popen|create_function)[[:space:]]*\(')"
    if [ -n "$dangereux" ]; then
        ko "appel à une fonction d'exécution interdite"
        extrait "$dangereux"
    else
        ok "aucun eval()/shell_exec()/passthru()/proc_open()"
    fi

    # extract() sur une superglobale = import massif de variables contrôlées par
    # le client. extract($donnees, EXTR_SKIP) sur un tableau maîtrisé est permis.
    local extraction
    extraction="$(fichiers_php | chercher 'extract[[:space:]]*\([[:space:]]*\$_(GET|POST|REQUEST|COOKIE)')"
    if [ -n "$extraction" ]; then
        ko "extract() appliqué à une superglobale"
        extrait "$extraction"
    else
        ok "extract() jamais appliqué à \$_GET/\$_POST/\$_REQUEST"
    fi

    local variables_variables
    variables_variables="$(fichiers_php | chercher '\$\$[A-Za-z_]')"
    if [ -n "$variables_variables" ]; then
        ko "variable variable (\$\$x) : flux de données non traçable"
        extrait "$variables_variables"
    else
        ok "aucune variable variable"
    fi

    # Inclusion dynamique pilotée par l'utilisateur (LFI/RFI).
    local inclusion
    inclusion="$(fichiers_php | chercher '(include|require)(_once)?[[:space:]]*\(?[[:space:]]*\$_(GET|POST|REQUEST|COOKIE)')"
    if [ -n "$inclusion" ]; then
        ko "inclusion de fichier pilotée par une entrée utilisateur (LFI)"
        extrait "$inclusion"
    else
        ok "aucune inclusion dynamique depuis une entrée utilisateur"
    fi
}

# =============================================================================
#  CONTRÔLE 10 — Aucun secret dans le dépôt
# =============================================================================
controle_secrets_depot() {
    titre "Dépôt — configuration locale et clés privées hors versionnement"

    local suivis=""
    if command -v git >/dev/null 2>&1 && [ -d .git ]; then
        suivis="$(git ls-files 2>/dev/null \
            | grep -iE '(^|/)(\.env(\..*)?|env\.php|id_rsa|id_ed25519|.*\.pem|.*\.key|.*\.p12)$' \
            | grep -vE 'env\.example\.php')"

        if [ -n "$suivis" ]; then
            ko "fichier sensible versionné"
            extrait "$suivis"
        else
            ok "aucun fichier de configuration locale ni clé privée versionné"
        fi
    else
        # Ne JAMAIS afficher un ✓ pour une vérification qu'on n'a pas pu faire :
        # un vert obtenu par défaut d'outil est pire qu'un rouge.
        ko "git indisponible : impossible de vérifier l'index (installer git dans l'image CI)"
    fi

    # .gitignore doit couvrir les chemins de configuration réels.
    local non_ignores=""
    local chemin
    for chemin in 'app/Config/env.php' 'config/env.php' '.env'; do
        grep -qxF "$chemin" .gitignore 2>/dev/null || non_ignores="$non_ignores $chemin"
    done
    if [ -n "$non_ignores" ]; then
        ko ".gitignore n'exclut pas :$non_ignores"
    else
        ok ".gitignore couvre app/Config/env.php, config/env.php et .env"
    fi

    # Le modèle versionné ne doit contenir que des valeurs vides/neutres.
    if [ -f app/Config/env.example.php ]; then
        local exemple
        exemple="$(grep -nE "'db_pass'[[:space:]]*=>[[:space:]]*$Q$NQ{3,}$Q" app/Config/env.example.php)"
        if [ -n "$exemple" ]; then
            ko "app/Config/env.example.php contient un mot de passe réel"
            extrait "$exemple"
        else
            ok "app/Config/env.example.php ne contient aucune valeur secrète"
        fi
    fi
}

# =============================================================================
#  AUTO-TEST — la CI doit prouver que ses motifs mordent
# =============================================================================
# Un contrôle qui ne détecte plus rien passe au vert pour une mauvaise raison.
# --selftest confronte les motifs du contrôle 3 à des fixtures jouets : le vrai
# secret DOIT être vu, le balisage HTML NE DOIT PAS l'être.
auto_test() {
    printf '%sAuto-test des motifs du contrôle 3%s\n' "$C_GRAS" "$C_RAZ"
    local tmp erreurs=0
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT

    cat > "$tmp/vrai_positif.php" <<'PHP'
<?php
$password = "hunter2";
define('DB_PASS', 'S3cr3tDeProd');
$config = ['db_pass' => 'p4ssw0rd'];
$c->setPassword("motdepasse");
PHP

    cat > "$tmp/faux_positif.php" <<'PHP'
<?php // ce fichier ne doit JAMAIS être signalé
?>
<label for="mot_de_passe">Mot de passe</label>
<input type="password" id="mot_de_passe" name="mot_de_passe" required>
<input type="password" id="ancien" name="ancien_mot_de_passe" required>
<?php
$motDePasse = $this->str('mot_de_passe', 72);
$hash = password_hash($motDePasse, PASSWORD_BCRYPT);
$ok = password_verify($motDePasse, (string) $client['mot_de_passe_hash']);
$config = ['db_pass' => ''];
PHP

    local m_var='\$(mot_de_passe|motdepasse|mdp|password|passwd|pass|pwd|secret|token|api_key|apikey)[A-Za-z0-9_]*[[:space:]]*=[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"
    local m_const='(define|putenv|setenv)[[:space:]]*\([[:space:]]*'"$Q"'[A-Za-z_]*(PASS|PASSWD|PWD|MOT_DE_PASSE|SECRET|TOKEN|API_KEY)[A-Za-z_]*'"$Q"'[[:space:]]*,[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"
    local m_conf="$Q"'(db_pass|db_password|mot_de_passe|motdepasse|password|passwd|pwd|secret|api_key)'"$Q"'[[:space:]]*=>[[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"
    local m_setter='->set[Pp]ass(word)?[[:space:]]*\([[:space:]]*'"$Q"'[^"'"'"'$]{3,}'"$Q"
    local bruit_html='name=|id=|for=|placeholder=|autocomplete=|<input|<label|type="password"'
    local bruit_hash='password_hash|password_verify|PASSWORD_BCRYPT|PASSWORD_DEFAULT|PASSWORD_ARGON'

    balayer() {
        local cible="$1" motif total=0
        for motif in "$m_var" "$m_const" "$m_conf" "$m_setter"; do
            total=$((total + $(grep -nE -e "$motif" "$cible" 2>/dev/null \
                | grep -vEi -e "$bruit_html" \
                | grep -vE -e "$bruit_hash" \
                | grep -cE '.' )))
        done
        printf '%s' "$total"
    }

    local vus_positifs vus_negatifs
    vus_positifs="$(balayer "$tmp/vrai_positif.php")"
    vus_negatifs="$(balayer "$tmp/faux_positif.php")"

    if [ "$vus_positifs" -ge 4 ]; then
        printf '        %s✓%s 4 secrets en dur sur 4 détectés dans la fixture positive\n' "$C_OK" "$C_RAZ"
    else
        printf '        %s✘ seulement %s/4 secrets détectés : les motifs ont régressé%s\n' "$C_KO" "$vus_positifs" "$C_RAZ"
        erreurs=$((erreurs + 1))
    fi

    if [ "$vus_negatifs" -eq 0 ]; then
        printf '        %s✓%s 0 faux positif sur le balisage HTML « mot_de_passe »\n' "$C_OK" "$C_RAZ"
    else
        printf '        %s✘ %s faux positif(s) sur du HTML légitime%s\n' "$C_KO" "$vus_negatifs" "$C_RAZ"
        grep -nE -e "$m_var" -e "$m_conf" "$tmp/faux_positif.php" | grep -vEi -e "$bruit_html" | sed 's/^/          → /'
        erreurs=$((erreurs + 1))
    fi

    rm -rf "$tmp"
    trap - EXIT
    return "$erreurs"
}

# =================================================================== main ====

printf '%s╔════════════════════════════════════════════════════════════════════╗%s\n' "$C_GRAS" "$C_RAZ"
printf '%s║  MiniShop — contrôles statiques de sécurité (CDC §15.3, 10 points) ║%s\n' "$C_GRAS" "$C_RAZ"
printf '%s╚════════════════════════════════════════════════════════════════════╝%s\n' "$C_GRAS" "$C_RAZ"
printf 'dépôt : %s\n\n' "$RACINE"

if [ "$SELFTEST" -eq 1 ]; then
    auto_test
    sortie=$?
    printf '\n'
    if [ "$sortie" -eq 0 ]; then
        printf '%sAuto-test réussi.%s\n' "$C_OK" "$C_RAZ"
    else
        printf '%sAuto-test en échec.%s\n' "$C_KO" "$C_RAZ"
    fi
    exit "$sortie"
fi

controle_sql
controle_xss
controle_secret_en_dur
controle_hachage
controle_appartenance
controle_session
controle_csrf
controle_back_office
controle_fonctions_interdites
controle_secrets_depot

printf '\n%s────────────────────────────────────────────────────────────────────%s\n' "$C_GRAS" "$C_RAZ"
if [ "$ECHECS" -eq 0 ]; then
    printf '%s10 contrôles conformes%s — aucune règle de sécurité court-circuitée.\n' "$C_OK" "$C_RAZ"
    exit 0
fi

printf '%s%d assertion(s) en échec sur %d contrôles.%s\n' "$C_KO" "$ECHECS" "$NUMERO" "$C_RAZ"
for ligne in "${RESUME[@]}"; do
    case "$ligne" in
        ko\|*) printf '  %s✘%s contrôle %s — %s\n' "$C_KO" "$C_RAZ" "$(printf '%s' "$ligne" | cut -d'|' -f2)" "$(printf '%s' "$ligne" | cut -d'|' -f3-)" ;;
    esac
done
exit 1
