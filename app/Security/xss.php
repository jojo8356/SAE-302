<?php

declare(strict_types=1);

/**
 * MiniShop — helpers d'affichage (chargés par app/bootstrap.php, portée
 * globale : utilisables dans toutes les vues sans configuration).
 *
 * Échappement systématique des sorties (S-02, SEC-02) : AUCUNE donnée n'est
 * affichée dans une vue sans passer par e().
 */

/** Échappe une valeur pour l'affichage HTML (XSS, S-02). */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formate un montant en euros (virgule décimale, espace des milliers). */
function euros(mixed $montant): string
{
    return number_format((float) $montant, 2, ',', ' ') . ' €';
}

/** Date/heure française lisible depuis « Y-m-d H:i:s ». */
function date_fr(?string $datetime, bool $avecHeure = false): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);

    if ($ts === false) {
        return '—';
    }
    $format = 'd/m/Y';
    if ($avecHeure) {
        $format = 'd/m/Y H\hi';
    }

    return date($format, $ts);
}

/** Libellé humain d'un statut de commande (RB-11). */
function statut_libelle(string $statut): string
{
    return [
        'BROUILLON' => 'Brouillon',
        'EN_PREPARATION' => 'En préparation',
        'PAYEE' => 'Payée',
        'EXPEDIEE' => 'Expédiée',
        'LIVREE' => 'Livrée',
        'ANNULEE' => 'Annulée',
        'RETOURNEE' => 'Retournée',
    ][$statut] ?? $statut;
}

/** Libellé humain d'un état de stock (vue v_etat_stock). */
function etat_libelle(string $etat): string
{
    return ['DISPONIBLE' => 'Disponible', 'TRES_BAS' => 'Très bas', 'RUPTURE' => 'Rupture'][$etat] ?? $etat;
}

/** Champ CSRF (raccourci vue). */
function csrf_field(): string
{
    return \App\Security\Csrf::champ();
}

/** Classe de badge associée à un statut de commande (DA §8). */
function statut_classe(string $statut): string
{
    return [
        'BROUILLON' => 'badge--neutre',
        'PAYEE' => 'badge--info',
        'EN_PREPARATION' => 'badge--alerte',
        'EXPEDIEE' => 'badge--info',
        'LIVREE' => 'badge--ok',
        'ANNULEE' => 'badge--rupture',
        'RETOURNEE' => 'badge--neutre',
    ][$statut] ?? 'badge--neutre';
}

/** Badge HTML d'un statut de commande. */
function badge_statut(string $statut): string
{
    return '<span class="badge ' . statut_classe($statut) . '">' . e(statut_libelle($statut)) . '</span>';
}

/** Badge HTML d'un état de stock (DISPONIBLE / TRES_BAS / RUPTURE). */
function badge_etat(string $etat): string
{
    $classe = ['DISPONIBLE' => 'badge--ok', 'TRES_BAS' => 'badge--alerte', 'RUPTURE' => 'badge--rupture'][$etat]
        ?? 'badge--neutre';

    return '<span class="badge ' . $classe . '">' . e(etat_libelle($etat)) . '</span>';
}

/** Badge HTML d'un stock (nombre d'unités → libellé + classe). */
function badge_stock(int $stock): string
{
    if ($stock <= 0) {
        return '<span class="badge badge--rupture">Rupture</span>';
    }
    if ($stock < 5) {
        return '<span class="badge badge--alerte">Bientôt épuisé (' . $stock . ')</span>';
    }

    return '<span class="badge badge--ok">En stock (' . $stock . ')</span>';
}

/**
 * Icône SVG inline (DA §10). Trait 1,75, 24 px, `currentColor` : hérite de la
 * couleur du contexte, aucune requête réseau, aucune dépendance.
 *
 * Géométrie reprise du jeu **Lucide** (https://lucide.dev, licence ISC — avis
 * complet dans `docs/DA.md` §10) : tracés 24 × 24, `stroke-width` ramené à
 * 1,75 pour rester cohérent avec la finesse du reste de l'interface. Les tracés
 * sont inlinés ici plutôt que chargés : la CSP `img-src 'self' data:` reste
 * stricte et une icône ne coûte aucune requête.
 *
 * @param string $nom clé française du tracé (voir $traces), repli `coche`
 */
function icone(string $nom, int $taille = 24): string
{
    $traces = [
        'panier' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
        'recherche' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'coche' => '<path d="M20 6 9 17l-5-5"/>',
        'alerte' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'croix' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'camion' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'cadenas' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'retour' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'eclair' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'menu' => '<path d="M4 12h16"/><path d="M4 18h16"/><path d="M4 6h16"/>',
        // Familles de produits (accueil et page « Nos catégories »)
        'ordinateur' => '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/>',
        'casque' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
        'sac' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'montre' => '<circle cx="12" cy="12" r="6"/><polyline points="12 10 12 12 13 13"/><path d="m16.13 7.66-.81-4.05a2 2 0 0 0-2-1.61h-2.68a2 2 0 0 0-2 1.61l-.78 4.05"/><path d="m7.88 16.36.8 4a2 2 0 0 0 2 1.61h2.72a2 2 0 0 0 2-1.61l.81-4.05"/>',
        'etiquette' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
    ];
    $contenu = $traces[$nom] ?? $traces['coche'];

    return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $contenu . '</svg>';
}

/**
 * Icône d'une famille de produits, choisie par le slug de la catégorie.
 *
 * Le slug vient de la base : il est donc inconnu au code à la compilation. Un
 * slug non prévu retombe sur une étiquette générique — on n'affiche jamais de
 * trou dans une carte, et aucune donnée saisie ne peut injecter de balise
 * (le tracé est choisi dans une table fermée, jamais concaténé depuis l'entrée).
 *
 * @param string $slug slug de `categorie.slug` (ex. « objets-connectes »)
 */
function icone_categorie(string $slug, int $taille = 24): string
{
    $parSlug = [
        'informatique' => 'ordinateur',
        'audio' => 'casque',
        'accessoires' => 'sac',
        'objets-connectes' => 'montre',
    ];

    return icone($parSlug[$slug] ?? 'etiquette', $taille);
}

/**
 * URL d'un visuel produit, validée (jamais d'URL distante, jamais de `javascript:`).
 *
 * Une image cassée est pire qu'une absence d'image : on ne renvoie donc une
 * URL que si elle est locale et que le fichier existe réellement. Le repli
 * (dégradé + initiale) prend le relais partout ailleurs.
 */
function image_produit(?string $url): ?string
{
    if ($url === null) {
        return null;
    }
    $url = trim($url);
    if ($url === '' || preg_match('#^(https?:)?//#i', $url) === 1) {
        return null;
    }
    // Traversée de répertoire refusée (SEC-07).
    if (str_contains($url, '..') || str_starts_with($url, '/')) {
        return null;
    }
    $chemin = dirname(__DIR__, 2) . '/public/' . $url;
    if (!is_file($chemin)) {
        return null;
    }

    return '/' . ltrim($url, '/');
}

/** Initiale affichée quand un produit n'a pas de visuel (ou un visuel cassé). */
function initiale_produit(string $nom): string
{
    $premiere = mb_substr($nom, 0, 1, 'UTF-8');
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($premiere, 'UTF-8');
    }

    return strtoupper($premiere);
}

/**
 * Balise <img> d'un visuel produit, ou `null` si le produit n'en a pas.
 *
 * `width`/`height` réservent toujours la place (aucun décalage de mise en
 * page), `loading="lazy"` : une grille de 12 produits ne doit pas retarder
 * l'affichage des premières cartes.
 */
function visuel_produit(?string $url, \App\View\Image\AlternativeImage $alternative, int $largeur = 600, int $hauteur = 450): ?string
{
    $source = image_produit($url);
    if ($source === null) {
        return null;
    }

    return '<img class="carte-produit__image" src="' . e($source) . '"'
        . ' alt="' . e($alternative->texte()) . '" width="' . $largeur . '" height="' . $hauteur . '"'
        . ' loading="lazy" decoding="async">';
}
