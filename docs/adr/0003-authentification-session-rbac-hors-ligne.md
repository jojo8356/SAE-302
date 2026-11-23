# ADR-0003 : Utiliser des sessions PHP durcies, un routeur RBAC à liste blanche et zéro CDN externe

## Statut

Accepted — Date: 2026-10-02 · Auteurs: Johan Polsinelli, Chahd Gouiaa · Reviewers: Équipe MiniShop

## Contexte et problème

MiniShop expose des parcours authentifiés (espace client, back-office `GESTIONNAIRE` et `SUPER`) manipulés via formulaires web et API interne. L’application doit prévenir les vulnérabilités OWASP courantes (fixation de session, CSRF, XSS, IDOR, élévation de privilèges) tout en restant 100 % fonctionnelle sur réseau isolé ou en soutenance hors ligne (`ENF-20`).

## Decision Drivers

- Garantir la défense en profondeur OWASP Top 10 (CSRF, XSS, IDOR, sessions)
- Fonctionner intégralement sans accès Internet ni CDN tiers (`ENF-20`) avec CSP stricte (`'self'`)
- Conserver un déploiement simple sans serveur tiers de jetons ou d’annuaire en contexte pédagogique

## Options considérées

### Option 1 : Jeton JWT côté navigateur + librairies CSS/JS via CDN

**Avantages** : session stateless côté serveur et mise en forme rapide via Bootstrap/Google Fonts.
**Inconvénients** : révocation immédiate complexe au blocage d’un compte, violation de `ENF-20` (réseau) et surface CSP/XSS élargie.

### Option 2 : Session PHP par défaut avec routage dynamique automatique

**Avantages** : mise en place immédiate sans table de routage explicite.
**Inconvénients** : risque d’exposition accidentelle de méthodes par URL et absence de garde d’accès centralisée par rôle.

### Option 3 : Routeur à liste blanche + sessions PHP durcies + ressources 100 % locales

**Avantages** : `app/Router.php` impose une garde explicite (`public`, `client`, `admin`, `super`), cookies `HttpOnly` / `SameSite=Lax` / `use_strict_mode=1`, régénération d’ID (`Auth::regenererId()`), CSRF par `hash_equals()` et polices/CSS/SVG servis depuis `public/`.
**Inconvénients** : les sessions PHP locales limitent la montée en charge horizontale sans magasin partagé, et chaque nouvelle URL exige une déclaration dans `public/index.php`.

## Décision

**Nous choisissons un routeur à liste blanche couplé aux sessions PHP durcies et à des actifs 100 % locaux**. Aucune URL non déclarée n’est exécutable, chaque POST appelle `verifierCsrf()` et aucune requête externe n’est émise par le navigateur.

## Conséquences

### Positives

- Score `11/11` sur `tests/security/controles.sh`, contrat HTTP 403 vérifié en CI et latence `p95 = 6,0 ms` sur VM Debian 13.

### Négatives

- En architecture multi-nœuds future (`docs/27`), les sessions devront migrer vers Redis avec cookie `__Host-MINISHOPSESS` et SSO OIDC.
- Chaque nouvel actif graphique ou police doit être hébergé et maintenu dans `public/` (`minishop-euro.woff2`).

### Neutres

- Les préférences non sensibles de langue et de fuseau restent locales au navigateur (`localStorage` sans secret).

## Liens

- `public/index.php`, `app/Router.php`, `app/Security/Auth.php`, `app/Security/Csrf.php` · `docs/07-note-cybersecurite.md` · `tests/security/controles.sh`
