<?php

declare(strict_types=1);

/**
 * MiniShop — erreur métier de la couche de gestion des données.
 *
 * Levée par les « procédures » (Repository) et les « déclencheurs »
 * (Triggers) du moteur — équivalent exact du
 * SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<CODE>' du SQL d'origine
 * (sql/02_minishop_procedures.sql et sql/03_minishop_triggers.sql).
 *
 * Le code (ex. EMAIL_DEJA_UTILISE) est TOUJOURS un identifiant SNAKE_UPPER
 * identique à celui du SQL : les vues et les tests n'ont donc pas besoin de
 * connaître la couche de stockage, et la migration finale vers
 * StorageDriver::SQL fera remonter les mêmes codes depuis la base.
 */

namespace App\Model\Data;

final class BusinessError extends \RuntimeException
{
    public function __construct(public readonly string $businessCode)
    {
        parent::__construct($businessCode);
    }

    /** Message lisible associé au code métier (utilisé par les vues). */
    public function messageHumain(): string
    {
        return self::MESSAGES[$this->businessCode] ?? $this->businessCode;
    }

    /** @var array<string,string> codes -> libellés affichables (extrait du CDC §4/§6) */
    public const MESSAGES = [
        'CHAMPS_OBLIGATOIRES'                          => 'Tous les champs obligatoires doivent être renseignés.',
        'HASH_MOT_DE_PASSE_INVALIDE'                   => 'Le mot de passe doit être haché avant stockage (RB-12).',
        'EMAIL_DEJA_UTILISE'                           => 'Cette adresse email est déjà utilisée (RB-01).',
        'CLIENT_INTROUVABLE'                           => 'Client introuvable.',
        'CLIENT_INTROUVABLE_OU_BLOQUE'                 => 'Compte client introuvable ou bloqué.',
        'ADRESSE_LIVRAISON_REQUISE'                    => 'Une adresse de livraison est requise.',
        'RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF'      => 'Le prix doit être strictement positif (RB-02).',
        'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF'          => 'Le stock ne peut pas être négatif (RB-03).',
        'NOM_REQUIS'                                   => 'Le nom est obligatoire.',
        'CATEGORIE_INTROUVABLE'                        => 'Catégorie introuvable.',
        'CATEGORIE_NON_VIDE'                           => 'Cette catégorie contient des produits : réassignez-les avant suppression (RB-14).',
        'CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS'   => 'Cette catégorie contient des produits : réassignez-les avant suppression (RB-14).',
        'PRODUIT_INTROUVABLE'                          => 'Produit introuvable.',
        'PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER'      => 'Produit déjà commandé : masquez-le au lieu de le supprimer (RB-14/RB-17).',
        'QUANTITE_REQUISE'                             => 'Une quantité est requise.',
        'RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE' => 'La commande doit contenir au moins une ligne (RB-04).',
        'RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO'    => 'La quantité doit être supérieure à zéro (RB-05).',
        'RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE'         => 'Une ligne de commande est définitive : prix et quantité non modifiables (RB-06/RB-09).',
        'RB09_COMMANDE_VALIDATEE_INTERDITE_DE_SUPPRIMER' => 'Une commande validée ne se supprime pas (RB-09).',
        'RB10_COMMANDE_SANS_CLIENT'                    => 'La commande doit appartenir à un client existant (RB-10).',
        'RB11_TRANSITION_STATUT_INTERDITE'             => 'Transition de statut interdite (RB-11).',
        'RB15_MONTANT_CALCULE_INTERDIT'                => 'Le montant total est calculé par le moteur, jamais saisi (RB-15).',
        'RB18_STOCK_INSUFFISANT'                       => 'Stock insuffisant pour cette quantité (RB-18).',
        'STOCK_INSUFFISANT'                            => 'Stock insuffisant pour cette quantité (RB-18).',
        'RB19_PANIER_VIDE'                             => 'Le panier est vide (RB-19).',
        'COMMANDE_INTROUVABLE'                         => 'Commande introuvable.',
        'COMMANDE_NON_MODIFIABLE'                      => 'Cette commande n’est plus modifiable.',
        'COMMANDE_DEJA_VALIDEE'                        => 'Cette commande est déjà validée.',
        'COMMANDE_NON_ANNULABLE'                       => 'Cette commande ne peut plus être annulée (déjà expédiée/livrée).',
        'ANNULATION_TROP_TARDIVE'                      => 'Annulation impossible : la commande est déjà expédiée.',
        'STATUT_NON_AUTORISE'                          => 'Statut inconnu ou non autorisé.',
        'STATUT_DEJA_A_JOUR'                           => 'Le statut demandé est déjà en vigueur.',
        'ACCES_NON_AUTORISE'                           => 'Accès non autorisé : cette commande n’est pas la vôtre.',
        'STOCK_NEGATIF_DETECTE'                        => 'Un stock négatif a été détecté lors de la validation.',
        'PRODUIT_SUPPRIME_DU_CATALOGUE'                => 'Ce produit n’est plus disponible au catalogue.',
        'MOTIF_STOCK_REQUIS'                           => 'Toute écriture de stock doit porter un motif (EF-ADM-04).',
        'REFERENCE_DEJA_UTILISEE'                      => 'Cette référence produit existe déjà.',
        'SLUG_DEJA_UTILISE'                            => 'Ce slug produit existe déjà.',
        'NOM_DEJA_UTILISE'                             => 'Ce nom de catégorie existe déjà.',
        'AUCUNE_MODIFICATION'                          => 'Aucune modification à appliquer.',
    ];
}
