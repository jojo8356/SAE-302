# ADR-0003 — Sessions strictes, RBAC et zéro dépendance CDN hors ligne

**Statut :** Accepté · **Date :** 2026-10-02 · **Propriétaire :** Équipe MiniShop  
**Fichiers concernés :** `app/Security/Auth.php`, `app/Security/Csrf.php`, `app/Router.php`, `public/index.php`, `public/css/app.css`

---

## 1. Contexte

MiniShop doit garantir la sécurité OWASP (injections SQL, XSS, CSRF, IDOR, fixation de session) et rester 100 % fonctionnel sur un réseau isolé ou en soutenance hors ligne (`ENF-20`), sans CDN externe ni dépendances JavaScript lourdes.

## 2. Décision

1. **Contrôle d’accès centralisé :** `app/Router.php` impose une garde explicite sur chaque route (`public`, `client`, `admin`, `super`), relayée par `Auth::exigeClient()`, `Auth::exigeAdmin()` et `Auth::exigeSuper()`.
2. **Durcissement natif PHP :** cookies de session `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, régénération d’ID à la connexion (`Auth::regenererId()`), jetons CSRF vérifiés par `hash_equals()`, en-têtes CSP dans `public/index.php`.
3. **Zéro CDN externe :** polices (`public/fonts/minishop-euro.woff2` sous licence `OFL.txt`), icônes SVG et CSS natif servis localement depuis `public/`.

## 3. Alternatives écartées

- **Chargement de Bootstrap / Google Fonts via CDN :** viole `ENF-20` et introduit une dépendance tiers dans la CSP.

## 4. Conséquences

- **Positives :** score `11/11` sur `tests/security/controles.sh`, latence HTTP `p95 = 6,0 ms` sur VM Debian 13, exécution hors ligne garantie.
- **Évolution prévue en production :** en multi-nœuds (`docs/27` § `4.1.4` & `4.1.13`), externaliser le stockage des sessions PHP vers Redis, renommer le cookie en `__Host-MINISHOPSESS` (*host-only*) et déléguer l’authentification employé à un IdP OIDC (`sso.minishop.fr`).
