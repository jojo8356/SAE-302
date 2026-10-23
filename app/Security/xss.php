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
 */
function icone(string $nom, int $taille = 24): string
{
    $traces = [
        'panier' => '<path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
        'recherche' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'coche' => '<path d="M4 12.5l5 5L20 6.5"/>',
        'alerte' => '<path d="M12 3l9.5 17h-19z"/><path d="M12 9v5"/><circle cx="12" cy="17.5" r="0.8" fill="currentColor"/>',
        'croix' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'camion' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/>',
        'cadenas' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/>',
        'retour' => '<path d="M4 9h11a5 5 0 0 1 0 10H8"/><path d="M8 5L4 9l4 4"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'eclair' => '<path d="M13 2L5 13h6l-1 9 8-11h-6z"/>',
    ];
    $contenu = $traces[$nom] ?? $traces['coche'];

    return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $contenu . '</svg>';
}
