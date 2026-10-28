# DA — Direction artistique MiniShop

> **Version 1.0 — 23/10/2026.** Document de conception visuelle du front-office et du back-office.
> Il précède l'implémentation (`public/css/app.css`) et la **valide** : chaque décision ci-dessous
> correspond à un token ou un composant du code, et au test `tests/php/css_contract.php`.
>
> **Périmètre.** Interface web responsive (360 px → 1440 px), deux contextes (boutique, back-office),
> 14 écrans, accessibilité **WCAG 2.1 AA** (ENF-08), zéro dépendance externe (polices système et glyphe euro local, SVG inline,
> aucune CDN) pour rester conforme à `ENF-20` (« aucune dépendance runtime hors PHP/MySQL/Apache »).

---

## 1. Méthode

1. **Benchmark** de 5 acteurs français du e-commerce high-tech (Amazon, Cdiscount, Fnac, Boulanger, Back Market).
2. **Grille d'analyse** en 10 critères : hiérarchie visuelle, densité, navigation, fiche produit, panier,
   confiance, filtres, back-office, accessibilité, cohérence.
3. **Synthèse** : ce qu'on reprend, ce qu'on refuse, et pourquoi.
4. **Direction artistique** : positionnement, palette, typographie, grille, composants, mouvement.
5. **Implémentation** : tokens CSS, composants, tests contractuels.

Le benchmark porte sur des **patterns observables** (structures, hiérarchies, comportements), pas sur des
captures : aucune marque, logo ou ressource de ces sites n'est réutilisée.

---

## 2. Benchmark — 5 concurrents

### 2.1 Amazon.fr — la référence de la densité

| Critère | Observation |
|---|---|
| Navigation | Mega-menu à 3 niveaux + barre de recherche omniprésente, collante, avec suggestions |
| Fiche produit | Colonne média / colonne « buy box » / blocs de réassurance empilés ; le prix et le CTA sont dans un encadré à droite |
| Confiance | Date de livraison estimée, retours 30 jours, avis clients notés et filtrables |
| Densité | Très élevée : 8 à 12 blocs visibles sans scroller ; peu d'air |
| Accessibilité | Contrastes corrects mais liens peu différenciés du texte, focus parfois discret |

**On reprend** : la *buy box* (prix + disponibilité + quantité + CTA dans un même cadre), la date/le délai
de livraison, les badges de réassurance.
**On refuse** : la densité publicitaire, l'empilement de carrousels, les faux compteurs d'urgence.

### 2.2 Cdiscount.com — le prix comme argument central

| Critère | Observation |
|---|---|
| Hiérarchie | Prix en très gros, rouge/orange ; remise en pourcentage en pastille |
| Navigation | Recherche + catégories larges ; beaucoup de promotions en page d'accueil |
| Fiche produit | Vendeurs multiples dans un même « buy box » (marketplace) |
| Confiance | Labels de paiement, retrait magasin |
| Risque | La couleur saturée partout brouille la hiérarchie : tout crie, donc rien ne ressort |

**On reprend** : la lisibilité du prix TTC et du taux de TVA, le contraste prix / reste.
**On refuse** : l'usage du rouge pour un prix (le rouge est réservé aux **erreurs** dans notre système).

### 2.3 Fnac.com — l'éditorialisation

| Critère | Observation |
|---|---|
| Structure | Blocs éditoriaux (« sélection », « à la une ») alternant avec des grilles produits |
| Typographie | Serif pour les titres éditoriaux, sans-serif pour l'interface |
| Fiche produit | Conseils, avis, produits complémentaires |
| Densité | Moyenne, beaucoup de blanc ; rythme visuel net |
| Back-office | Non observable (public) |

**On reprend** : le **rythme** (alternance sections pleine largeur / grilles) et la hiérarchie de titres
claire ; la double famille typographique est écartée (dépendance à une police web).

### 2.4 Boulanger.com — la recherche produit outillée

| Critère | Observation |
|---|---|
| Filtres | Colonne de filtres à facettes, compteurs, « voir plus », filtres actifs en pastilles retirables |
| Grille | Cartes produit homogènes (image, titre 2 lignes max, prix, dispo magasin) |
| Fiche produit | Sélecteur de variantes, comparaison, disponibilité par magasin |
| Confiance | Retrait en 1 h, installation, garantie |
| Accessibilité | Pastilles de filtres annoncées, focus visible sur les champs |

**On reprend** : les **filtres à facettes avec état dans l'URL**, les pastilles de filtres actifs, la carte
produit homogène (hauteur fixe du bloc titre).
**On refuse** : la comparaison multi-produits et le sélecteur de magasin (hors périmètre §11.2).

### 2.5 Back Market — la confiance par le design

| Critère | Observation |
|---|---|
| Ton | Éditorial, décalé, très lisible ; la marque porte la promesse écologique |
| Confiance | Garantie, « satisfait ou remboursé » et notes visibles au-dessus de la ligne de flottaison |
| Couleur | Une couleur de marque unique (turquoise) + neutres ; CTA sombre, très contrasté |
| Densité | Faible : grande respiration, une idée par section |
| Accessibilité | Très bon contraste, focus visible, cibles ≥ 44 px |

**On reprend** : la **restriction chromatique** (une couleur de marque + un accent d'action), le CTA sombre
ou très contrasté, la respiration, les cibles tactiles ≥ 44 px.

---

## 3. Synthèse comparative

| Critère | Amazon | Cdiscount | Fnac | Boulanger | Back Market | **MiniShop** |
|---|---|---|---|---|---|---|
| Densité | très haute | haute | moyenne | haute | basse | **moyenne-basse** |
| Couleur d'action | orange | rouge | jaune | orange | noir | **ambre `#C2410C`** |
| Hiérarchie du prix | forte | très forte | moyenne | forte | moyenne | **forte, sobre** |
| Filtres dans l'URL | oui | partiel | oui | oui | oui | **oui (URL partageable)** |
| Buy box | oui | oui | oui | oui | oui | **oui** |
| Réassurance | forte | moyenne | moyenne | forte | très forte | **3 preuves, pas 10** |
| Typographie | 1 famille | 2 | 2 | 2 | 2 | **1 famille système** |
| Respect AA | partiel | partiel | oui | oui | oui | **AA vérifié, contrastes calculés** |
| Back-office soigné | inconnu | inconnu | inconnu | inconnu | inconnu | **oui (différenciateur)** |

**Conclusion du benchmark.** Les concurrents gagnent sur la densité et la réassurance, mais payent en
charge visuelle et en accessibilité. Notre projet est un **exercice noté** : la démonstration de la
cohérence (prix TTC, stock réel, règles métier) doit être **plus lisible** qu'un site commercial.
Nous choisissons donc : **peu d'éléments, très contrastés, hiérarchisés, accessibles**, avec un
back-office au même niveau de soin que la boutique — c'est rare et c'est un argument de soutenance.

---

## 4. Positionnement

> **« La précision technique, sans la froideur. »**

Trois piliers :

1. **Clarté du prix** — le prix TTC domine, le HT et la TVA sont disponibles mais discrets ; le port est
   toujours annoncé avant validation (`ENF-14`, obligation légale).
2. **Honnêteté de l'état** — le stock est réel : `En stock`, `Bientôt épuisé (N)`, `Rupture` sont trois
   états visuellement distincts, jamais un faux compte à rebours.
3. **Sobriété outillée** — un fond papier chaud, une encre bleu-nuit, un seul accent d'action ; le
   back-office utilise la même grammaire en version dense.

---

## 5. Palette

Contrastes calculés selon WCAG 2.1 (ratio `(L1+0.05)/(L2+0.05)`), vérifiés par `tests/php/css_contract.php`.

| Token | Valeur | Rôle | Contraste |
|---|---|---|---|
| `--papier` | `#FBFAF7` | fond général (blanc chaud, moins fatigant qu'un blanc pur) | — |
| `--surface` | `#FFFFFF` | cartes, tableaux, formulaires | — |
| `--encre` | `#0F172A` | texte principal, titres | **17,1:1** sur papier |
| `--encre-douce` | `#475569` | texte secondaire, légendes | **7,3:1** sur papier |
| `--bordure` | `#E2E8F0` | séparateurs, contours de champs | — |
| `--primaire` | `#1D4ED8` | liens, éléments actifs, focus | **6,7:1** sur blanc |
| `--primaire-fonce` | `#1E3A8A` | survol, en-têtes de tableau | **10,4:1** |
| `--accent` | `#C2410C` | **action d'achat uniquement** (CTA) | **5,2:1** sur blanc |
| `--accent-fonce` | `#9A3412` | survol du CTA | 7,0:1 |
| `--succes` / `--succes-fond` | `#166534` / `#DCFCE7` | confirmation, stock disponible | **6,5:1** |
| `--alerte` / `--alerte-fond` | `#9A3412` / `#FFEDD5` | stock faible | **6,4:1** |
| `--danger` / `--danger-fond` | `#991B1B` / `#FEE2E2` | erreurs, rupture, annulation | **6,8:1** |
| `--info` / `--info-fond` | `#1E3A8A` / `#DBEAFE` | informations, neutre | **8,5:1** |

**Règles d'usage**

- Le **rouge ne sert jamais à afficher un prix** (contrairement à Cdiscount) : il signifie *erreur* ou *rupture*.
- L'**accent ambre** est réservé aux actions qui engagent (ajouter au panier, valider la commande).
  Un seul CTA accent par écran.
- Les **badges d'état** utilisent la paire `couleur / couleur-fond` : jamais de texte sur fond saturé
  sans vérification de contraste.
- Aucune couleur n'est porteuse d'information seule : chaque badge a un **libellé texte** (daltonisme).

---

## 6. Typographie

- **Texte système** : pile `system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.
  Le caractère **€** utilise seul le glyphe de Lato Regular, adapté et renommé `MiniShopEuro`,
  limité à `U+20AC` par `unicode-range` : deux barres lisibles, même avec un prix en gras.
  Le fichier local `public/fonts/minishop-euro.woff2` est servi par l'application, sans CDN ;
  la licence SIL OFL 1.1 et le copyright sont dans `public/fonts/OFL.txt` (source : `google/fonts`, `ofl/lato/Lato-Regular.ttf`, blob `0f3d0f837d24834b9b5b0a6b735459c56f5e75c3`).
  CSP : `font-src 'self'`. En cas d'échec du chargement, le symbole texte et la pile système restent disponibles.
  Les montants restent du texte Unicode, lisible, sélectionnable et accessible.
- **Chiffres tabulaires** (`font-variant-numeric: tabular-nums`) sur les prix, montants et stocks :
  les colonnes de tableaux s'alignent.
- Échelle modulaire 1,25 (base 16 px) :

| Token | px | Usage |
|---|---|---|
| `--t-xs` | 12 | mentions, légendes |
| `--t-sm` | 14 | texte secondaire, tableaux |
| `--t-md` | 16 | texte courant |
| `--t-lg` | 20 | sous-titres, prix de fiche |
| `--t-xl` | 25 | titre de section |
| `--t-2xl` | 31 | titre de page (h1) |
| `--t-3xl` | 39 | prix principal de la fiche, chiffre-clé du back-office |

- Longueur de ligne cible : **60 à 75 caractères** (`max-width: 68ch` sur les paragraphes de contenu).
- Interlignage : 1,5 pour le texte, 1,15 pour les titres.

---

## 7. Grille, espacement, formes

- **Unité de base 8 px**, échelle : 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64.
- Conteneur : `max-width: 1180px`, gouttières 16 px mobile, 24 px tablette, 32 px desktop.
- Grilles : catalogue 1 / 2 / 3 colonnes (360 / 640 / 1024 px) ; back-office en `sidebar + contenu`.
- Rayons : 8 px (champs), 12 px (cartes), 999 px (pastilles).
- Ombres : deux niveaux seulement (`--ombre-1` au repos, `--ombre-2` au survol des cartes).
- Bordures : 1 px `--bordure` ; jamais de bordure décorative colorée en dehors des états.

---

## 8. Composants (12)

| # | Composant | Règle de conception |
|---|---|---|
| 1 | **En-tête collant** | logo SVG + navigation + badge panier (`aria-live`) ; hauteur 64 px, ombre au scroll |
| 2 | **Recherche** | champ unique, `type=search`, bouton 44 px ; jamais de recherche « plein écran » (coût d'implémentation) |
| 3 | **Carte produit** | image 4:3 (placeholder SVG si absente), catégorie, titre 2 lignes max, prix TTC, badge de stock, CTA |
| 4 | **Buy box** | encadré collant à droite sur desktop : prix, TVA, disponibilité, quantité, CTA, réassurance |
| 5 | **Filtres à facettes** | colonne à gauche desktop, accordéon mobile ; pastilles actives retirables ; état dans l'URL |
| 6 | **Pagination** | Précédent / numéros / Suivant ; page courante `aria-current="page"` |
| 7 | **Tableau** (back-office) | en-têtes sombres, lignes zébrées, `caption`, chiffres alignés à droite |
| 8 | **Formulaire** | label au-dessus, aide en 14 px, erreur sous le champ + `aria-describedby`, focus 3 px ; contact et authentification : carte centrée 575 px maximum (460 px + 25 %), titre intérieur centré, légende sémantique conservée |
| 9 | **Boutons** | primaire (accent), secondaire (contour), discret (lien) ; 3 tailles ; état `disabled` explicite : fond et texte inchangés au survol, curseur interdit |
| 10 | **Badges d'état** | `Disponible` / `Bientôt épuisé` / `Rupture` / statuts de commande, toujours avec texte |
| 11 | **Messages flash** | bandeau `role="status"` (succès) ou `role="alert"` (erreur), refermable, non bloquant |
| 12 | **État vide** | une phrase qui explique quoi faire + un lien d'action (jamais une page blanche) |

---

## 9. Accessibilité (WCAG 2.1 AA)

| Exigence | Mise en œuvre | Vérification |
|---|---|---|
| 1.4.3 Contraste texte | palette calculée (min 5,2:1) | `tests/php/css_contract.php` |
| 1.4.11 Contraste non-textuel | bordures de champs ≥ 3:1 sur fond | audit du thème |
| 2.1.1 Clavier | tous les composants natifs (`button`, `a`, `input`), aucun `div` cliquable | revue de code |
| 2.4.1 Contournement | **lien d'évitement** « Aller au contenu » premier élément focusable | `layout.php` |
| 2.4.7 Focus visible | `:focus-visible` → contour 3 px + `outline-offset: 2px` | CSS |
| 3.3.1 Erreurs identifiées | message sous le champ + résumé `role="alert"` | formulaires |
| 4.1.3 Statuts | `aria-live="polite"` sur le badge panier et le total | `layout.php`, `panier.js` |
| 2.3.3 Animations | `prefers-reduced-motion` → transitions à 0,01 ms | CSS |
| 1.4.4 Zoom 200 % | aucune hauteur fixe sur les blocs de texte ; `rem` partout | CSS |
| 2.5.5 Cibles tactiles | minimum 44 × 44 px sur mobile | CSS |

**Décision assumée** : pas de mode sombre en v1 (coût de test doublé pour un gain nul en soutenance) ;
les tokens sont prêts (`[data-theme="dark"]` documenté) si la suite du projet le demande.

---

## 10. Iconographie et imagerie

- **Icônes : jeu Lucide, inliné** (gabarit 24 × 24, `stroke-width` 1,75 px, `currentColor`).
  Tracés utilisés : `panier`, `recherche`, `coche`, `alerte`, `croix`, `camion`, `cadenas`,
  `retour`, `user`, `eclair`, `menu`, plus **une icône par famille de produits** :
  `laptop` → Informatique, `headphones` → Audio, `shopping-bag` → Accessoires,
  `watch` → Objets connectés, `tag` en repli pour toute catégorie créée après coup
  (le slug vient de la base : un slug inconnu affiche une étiquette, jamais un trou).
  Répartition : la pastille de catégorie fait 44 × 44 px (`--primaire-fond`, rayon `--r-sm`),
  elle passe en `--primaire` plein au survol et au focus — l'icône est **décorative**
  (`aria-hidden`, `focusable="false"`), le nom de la catégorie reste dans le `<h2>`/`<h3>`.
  Aucun CDN, aucun sprite réseau : les tracés sont dans `app/Security/xss.php` (`icone()`,
  `icone_categorie()`), donc servis par la page elle-même et compatibles avec la CSP
  `img-src 'self' data:`. Un test vérifie qu'aucune icône ne contient d'URL (`css_contract.php`).
- **Licence des icônes** — Lucide, licence ISC :
  `Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part of Feather (MIT).`
  `All other copyright (c) for Lucide are held by Lucide Contributors 2022.`
  Permission to use, copy, modify, and/or distribute this software for any purpose with or
  without fee is hereby granted, provided that the above copyright notice and this permission
  notice appear in all copies. THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL
  WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
  AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR
  CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS,
  WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN
  CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
- **Logo** : un « M » en boucle ouverte (circuit imprimé) dans un carré arrondi `--primaire`,
  décliné en `logo.svg` (horizontal) et `favicon.svg` (carré).
- **Images produit** : les 36 produits du jeu de démonstration ont un **packshot réel** — 12 visuels distincts, un par famille de produit, réutilisés à l'identique dans la famille (cadre homogène, 36 fiches cohérentes)
  (`public/img/produits/*.jpg`, 800 × 600, 15–49 Ko, tous produits selon la même recette : objet seul,
  fond neutre `--surface-2`, ombre de contact douce, lumière venant du haut à gauche). C'est ce qui
  distingue un site crédible d'une maquette. Le **repli** (dégradé + initiale, cadre en pointillés)
  subsiste pour tout produit sans visuel ou dont le fichier a disparu : une image cassée est pire
  qu'une absence d'image, et `image_produit()` ne renvoie jamais d'URL non résolue.
- **Photographie assumée, à deux niveaux** : une image d'**ambiance** dans le hero
  (`public/img/illustration-achat.jpg`, 960 × 524, 63 Ko) et un **packshot par produit**
  (`public/img/produits/*.jpg`). L'interface reste par ailleurs en direction « fiche technique »
  (aplats, filets, tokens) : la photo sert la preuve, pas la décoration.
  - **Pourquoi deux niveaux** : le hero vend une *ambiance*, la carte vend un *objet*. Le même
    cadrage ne peut pas servir les deux — d'où l'ambiance en 16/9 et les packshots en 4/3.
  - **Cohérence imposée** : mêmes fond, même lumière, même cadrage sur les 12 packshots. Un site
    dont les vignettes viennent de sources différentes « sent » l'assemblage ; c'est la première
    chose qu'un correcteur remarque.
  - **Provenance** : image générée pour le projet (aucun droit de tiers, aucune banque d'images,
    aucune requête réseau — ENF-20 tenu). Palette alignée sur `--primaire` (cobalt) et `--accent`
    (terracotta), fond crème proche de `--papier`.
  - **Contraintes tenues** : `width`/`height` déclarés (aucun décalage de mise en page au chargement),
    `loading="eager"` + `fetchpriority="high"` pour le hero (au-dessus de la ligne de flottaison),
    `loading="lazy"` pour les vignettes (une grille de 12 produits ne doit pas retarder le premier
    écran), `alt` descriptif pour l'ambiance et **`alt=""` pour les packshots** — le nom du produit
    est déjà le titre de la carte, le répéter ferait bavarder un lecteur d'écran.
    `object-fit: cover` en carte, `contain` sur la fiche (on ne rogne pas le sujet). Poids ≤ 250 Ko
    pour le hero, ≤ 120 Ko par packshot : vérifié par `tests/php/css_contract.php`.
  - **Règle** : toute nouvelle photo de produit devra suivre la même grille (fond neutre, lumière
    douce, 16/9 ou 4/3, ≤ 250 Ko) sous peine de casser la cohérence du hero.

---

## 11. Règles éditoriales (microcopy)

| Contexte | On écrit | On n'écrit pas |
|---|---|---|
| Prix | `129,90 € TTC` (+ `soit 108,25 € HT · TVA 20 %`) | `129.9€`, `PROMO` |
| Stock | `En stock (12)`, `Bientôt épuisé (2)`, `Rupture` | `Plus que 2 !!!`, `Dépêchez-vous` |
| Erreur | `Cet email est déjà utilisé. Connectez-vous ou choisissez-en un autre.` | `Erreur 1062` |
| Succès | `Commande validée. Votre numéro : CMD-2026-0042.` | `OK` |
| Vide | `Votre panier est vide. Parcourir le catalogue →` | `Aucun résultat.` |
| Bouton | `Ajouter au panier` (verbe + objet) | `Envoyer`, `Valider` (seul) |

Ton : **direct, factuel, sans urgence artificielle**. Tutoiement interdit, vouvoiement systématique.

---

## 12. Mise en œuvre

| Fichier | Contenu |
|---|---|
| `public/css/app.css` | tokens, remise à zéro, typographie, 12 composants, 3 paliers responsive, impression |
| `public/css/print.css` | impression des CGV et des récapitulatifs de commande |
| `public/js/panier.js` | ajout sans rechargement, badge `aria-live`, plafond serveur, confirmations |
| `public/js/ui.js` | menu mobile, compteur de caractères, focus après erreur |
| `public/img/logo.svg`, `logo-mark.svg`, `favicon.svg` | identité |
| `public/img/illustration-achat.jpg` | photographie du hero (960×524, 63 Ko) |
| `app/View/layout.php` | gabarit : skip-link, en-tête, nav, `aria-live`, pied de page |
| `tests/php/css_contract.php` | vérifie que chaque token existe, que les contrastes passent, que les classes utilisées dans les vues sont définies |

**Tests contractuels** (extrait) :

```bash
node scripts/wasm_run.mjs tests/php/css_contract.php
# attendu : 24 vérifications — 24 OK
#  ✓ tous les tokens sont définis
#  ✓ contraste encre/papier 17.10 ≥ 4.5
#  ✓ contraste accent/blanc 5.18 ≥ 4.5
#  ✓ chaque classe utilisée dans les vues existe dans app.css
#  ✓ prefers-reduced-motion géré
#  ✓ focus-visible défini
```

---

## 13. Ce que la DA ne fait pas (bornes)

- pas de thème sombre, pas de personnalisation par utilisateur ;
- pas de photos produit (jeu de démo sans médias) ;
- pas d'animations au-delà de 150 ms ;
- pas de framework CSS (ni Bootstrap ni Tailwind) : le design system est écrit à la main, ~700 lignes,
  lisible et testable — argument de soutenance sur la maîtrise du CSS ;
- pas de CDN de polices : la pile système et le glyphe euro local fonctionnent hors ligne.

---

## 14. Responsive et adaptation continue

> Ajout demandé après la première revue : « aucun argument contre le design ».
> Cette section documente la stratégie, les paliers et les preuves — elle est
> vérifiée automatiquement par `tests/php/responsive_contract.php` (47 contrôles).

### 14.1 Principe : fluide d'abord, paliers pour la composition

La mise en page ne « saute » pas d'un écran à l'autre : elle se dilate. Les
tailles de titres, les gouttières et les espacements verticaux utilisent
`clamp(min, préféré, max)`, la largeur utile `min(100% - 2 × gouttière, 1280px)`.
Les points de rupture ne servent qu'aux **changements de composition** —
c'est la règle que suivent Stripe, GOV.UK ou Material 3, et elle évite le
« responsive à trois tailles figées » qui casse sur les formats pliables.

### 14.2 Paliers

| Palier | Ce qui change | Justification |
|---|---|---|
| ≥ 87,5 em (1400 px) | largeur utile 1280 px, page centrée | au-delà, la mesure de lecture dépasse 75 caractères |
| ≤ 64 em (1024 px) | navigation repliée en `<details>` (« Menu ») ; catalogue / fiche / panier / back-office en une colonne ; filtres en grille ; barre latérale d'admin horizontale | tablette paysage et portrait : 8 entrées ne tiennent plus sur une ligne |
| ≤ 45 em (720 px) | résultats avant les filtres (`order`) ; hero réduit ; KPI sur 2 colonnes ; pied sur 2 colonnes | mobile large : le produit doit être visible sans défiler |
| ≤ 40 em (640 px) | formulaires pleine largeur, tableaux à 14 px, récapitulatif non collant | pouce + lisibilité |
| ≤ 35 em (560 px) | panier en **cartes étiquetées** (`data-label`) | un tableau de 5 colonnes n'est pas « reflowable » (WCAG 1.4.10) |
| ≤ 25 em (400 px) | étiquette au-dessus de la valeur dans le panier | 320 px utiles seulement |
| ≤ 400 px | pied et KPI sur 1 colonne, titres réduits | iPhone SE / Galaxy A0x |
| paysage ≤ 520 px de haut | en-tête non collant, hero compact | récupérer la hauteur utile |
| impression | navigation masquée, tableaux non rognés, `break-inside: avoid` | CGV et factures |

### 14.3 Techniques appliquées (et pourquoi)

| Technique | Effet | Référence |
|---|---|---|
| `clamp()` sur 8 tokens | titres lisibles de 320 px à 1536 px sans 6 media queries | type fluide |
| Paliers en `em` (25 / 35 / 40 / 45 / 64 / 87,5) | suivent la taille de police choisie par l'utilisateur, pas seulement la largeur de l'écran | MDN, WCAG 1.4.4 |
| `--gouttiere: clamp(1rem, 4vw, 2rem)` | marges latérales proportionnées | grille 8 px |
| `@container` + `@supports` | une carte produit se compacte selon **sa** largeur, pas celle de l'écran | container queries |
| `<details>` pour le menu, ouvert sans JS | navigation utilisable JavaScript désactivé (ENF-07) | disclosure pattern (WAI-ARIA APG) |
| `min-width: 0` sur les enfants de grille | supprime la cause n° 1 de scroll horizontal | CSS Grid |
| Régions défilantes `tabindex="0"` + ombres | tableaux accessibles au clavier avec affordance visuelle | WCAG 2.1.1 |
| Champs à `max(16px, 1rem)` | empêche le zoom automatique iOS | Safari |
| Cibles ≥ 44 px (48 px pour le CTA d'achat) | dépasse le minimum AA (24 px) et couvre Apple HIG (44 pt) | WCAG 2.5.8 / 2.5.5, Apple HIG |
| `100dvh`, `env(safe-area-inset-*)` | encoches et barre d'outils mobile | iOS / Android |
| `forced-colors`, `prefers-contrast`, `prefers-reduced-motion` | respect des réglages système | WCAG 1.4.12 / 2.3.3 |
| `overflow-x: clip` sur `html` | filet anti-débordement sans casser `position: sticky` | CSS Overflow 3 |
| `text-wrap: balance / pretty` | pas de veuves sur les titres | typographie |

### 14.4 Matrice de validation

| Largeur | Attendu | Vérifié par |
|---|---|---|
| 320 px | aucun défilement horizontal, menu replié, panier en cartes | contrat responsive + revue manuelle |
| 375 / 414 px | idem, cibles ≥ 44 px | `@media (pointer: coarse)` |
| 768 px | une colonne, filtres en grille, résultats avant filtres | palier 720/1024 |
| 1024 px | deux colonnes d'admin conservées, catalogue empilé | palier 1024 |
| 1440 px | largeur bornée 1280 px, mesure ≤ 75 caractères | `--mesure` |
| 200 % de zoom | pas de contenu coupé (aucune hauteur fixe sur les blocs de texte) | revue manuelle |
| impression | navigation absente, tableaux complets | `print.css` |

### 14.5 Sources et règles appliquées

| Règle retenue | Source |
|---|---|
| Reflow à 320 px, exception pour les tableaux de données, interdiction de bloquer le zoom | [W3C — Understanding SC 1.4.10 Reflow](https://www.w3.org/WAI/WCAG21/Understanding/reflow.html) |
| Concevoir petit d'abord, une colonne, maximum ~75 caractères par ligne, largeur de page bornée | [GOV.UK Design System — Layout](https://design-system.service.gov.uk/styles/layout/) |
| Paliers en unités relatives plutôt qu'en pixels de terminal | [MDN — Responsive design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design) |
| Cible tactile : 24 px minimum (AA), 44 px (AAA), 44 pt Apple, 48 dp Material | [WCAG 2.5.8](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) · Apple HIG · Material 3 |
| Un seul `<h1>`, hiérarchie de titres, liens d'évitement, focus visible | WCAG 2.4.1 / 2.4.7 · WAI-ARIA APG (disclosure) |

Les paliers GOV.UK (40 em / 48 em / 64 em) ont servi de référence ; nous
utilisons 25 / 35 / 40 / 45 / 64 / 87,5 em parce que le catalogue a besoin
d'un palier supplémentaire pour le passage en cartes du panier.

### 14.6 Limites assumées

- Aucun navigateur n'est installé dans l'environnement de production des
  livrables : la validation est **statique** (contrat de 47 vérifications) plus
  un protocole manuel de 5 largeurs décrit ci-dessus. Une revue sur appareils
  réels reste à cocher dans le PV de recette (doc `02`).
- Pas de `:has()` ni de `color-mix()` indispensables : les rares usages de
  `color-mix()` ont un repli par couleur littérale dans la même règle.

## 12. Pages d'information et confiance — étude préalable (28/10/2026)

Les pages juridiques de [Boulanger](https://www.boulanger.com/evenement/conditions-generales-vente-boulanger) séparent l'identification, les conditions de vente et les informations de livraison/retour ; celles de [Fnac](https://www.fnac.com/Help/cgv-fnac-marketplace) et [Back Market](https://www.backmarket.fr/fr-fr/legal/terms-of-use) distinguent en outre les rôles des vendeurs de marketplace. MiniShop est une **maquette de vente directe**, non une marketplace : on reprend la navigation identifiable, les titres explicites et la séparation des sujets, pas leurs clauses ni leurs obligations propres aux plateformes. Les éléments juridiques et les limites sont étudiés dans `docs/12-information-juridique.md`.

**Décisions avant réalisation :** pied de page organisé en rubriques courtes, lien direct vers la rétractation ; pages centrées et de largeur de lecture limitée, hiérarchie H1/H2, encadrés visibles pour le statut de maquette et le texte réglementaire, liens soulignés, retours à la ligne et marges adaptables, pas de tableau à défilement imposé. Aucun bandeau de choix décoratif pour les seuls traceurs nécessaires. La disposition doit fonctionner au clavier et à 320 px sans défilement horizontal ; les principes de [reflow WCAG](https://www.w3.org/WAI/WCAG21/Understanding/reflow.html) et de [mise en page GOV.UK](https://design-system.service.gov.uk/styles/layout/) guident le choix. Les contrôles statiques ne remplacent pas un audit d'accessibilité ni un rendu navigateur réel.

## 15. Date des derniers ajouts — décision préalable

Le benchmark des cartes d'Amazon, Fnac, Boulanger et Back Market (§2–3) privilégie
la hiérarchie image → nom → prix → disponibilité → action ; il ne démontre pas que
ces sites affichent une heure de création sur leurs cartes. Pour le besoin propre à
MiniShop, ajouter cette métadonnée **après l'action**, centrée, en taille secondaire
lisible et séparée par un trait discret, sans détourner l'attention du produit ni
modifier les autres cartes du catalogue. Un élément HTML `time` rend la date
compréhensible aux outils ; si elle manque ou n'est pas valide, afficher un repli
explicite sans date inventée. L'alignement reste fluide et le texte peut revenir à
la ligne sur mobile, conformément aux principes de reflow W3C et de mise en page
GOV.UK documentés en §14.5. Aucune nouvelle image ni icône n'est nécessaire.


## 16. Lisibilité du pied de page — correction de contraste

La comparaison des enseignes et des systèmes de design (§2 et §14) retient une
hiérarchie discrète mais lisible pour les informations secondaires. La capture
WAVE fournie pour le catalogue signale un texte du pied de page trop peu contrasté.
Pour conserver ce design sans modifier sa structure responsive, le texte d'aide
du pied hérite d'une couleur claire explicitement adaptée au fond sombre ; les
autres textes d'aide conservent leur couleur sur fond clair. La règle cible le
contexte du pied pour qu'une classe générique définie plus loin ne l'écrase pas.

La cible pour du texte normal est un rapport d'au moins 4,5:1 selon le
[critère WCAG 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
Le calcul des couleurs déclarées donne `#475569` sur `#0f172a` = 2,36:1
avant correction et `#cbd5e1` sur `#0f172a` = 12,02:1 après correction.
C’est un contrôle statique, pas une analyse de rendu : confirmer le résultat
sur le navigateur et la page réellement déployés.
