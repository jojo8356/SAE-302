# DA — Direction artistique MiniShop

> **Version 1.0 — 23/10/2026.** Document de conception visuelle du front-office et du back-office.
> Il précède l'implémentation (`public/css/app.css`) et la **valide** : chaque décision ci-dessous
> correspond à un token ou un composant du code, et au test `tests/php/css_contract.php`.
>
> **Périmètre.** Interface web responsive (360 px → 1440 px), deux contextes (boutique, back-office),
> 14 écrans, accessibilité **WCAG 2.1 AA** (ENF-08), zéro dépendance externe (polices système, SVG inline,
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

- **Une seule famille** : pile système `system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.
  Avantages : zéro requête réseau (offline, soutenance sans Wi-Fi), rendu natif, `ENF-20` respecté.
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
| 8 | **Formulaire** | label au-dessus, aide en 14 px, erreur sous le champ + `aria-describedby`, focus 3 px |
| 9 | **Boutons** | primaire (accent), secondaire (contour), discret (lien) ; 3 tailles ; état `disabled` explicite |
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

- **Icônes SVG inline** (course 24 px, trait 1,75 px) : `panier`, `recherche`, `coche`, `alerte`,
  `croix`, `chevron`, `camion`, `cadenas`. Aucune bibliothèque externe, aucun sprite réseau.
- **Logo** : un « M » en boucle ouverte (circuit imprimé) dans un carré arrondi `--primaire`,
  décliné en `logo.svg` (horizontal) et `favicon.svg` (carré).
- **Images produit** : le jeu de démonstration n'a pas de photos ; la carte affiche un **placeholder
  génératif** (dégradé neutre + initiale du produit) — ce n'est jamais une image cassée.
- **Pas de photographie de banque d'images** : le projet est pédagogique, on assume une direction
  « fiche technique » plutôt qu'un e-commerce photographique.

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
- pas de police web : la pile système garantit un rendu identique hors ligne.
