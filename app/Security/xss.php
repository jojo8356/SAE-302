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

    return $ts === false ? '—' : date($avecHeure ? 'd/m/Y H\hi' : 'd/m/Y', $ts);
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
