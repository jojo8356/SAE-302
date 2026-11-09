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
        'HASH_MOT_DE_PASSE_INVALIDE'                   => 'Le mot de passe doit être haché avant stockage.',
        'EMAIL_DEJA_UTILISE'                           => 'Cette adresse email est déjà utilisée.',
        'CLIENT_INTROUVABLE'                           => 'Client introuvable.',
        'CLIENT_INTROUVABLE_OU_BLOQUE'                 => 'Compte client introuvable ou bloqué.',
        'ADRESSE_LIVRAISON_REQUISE'                    => 'Une adresse de livraison est requise.',
        'RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF'      => 'Le prix doit être strictement positif.',
        'RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF'          => 'Le stock ne peut pas être négatif.',
        'NOM_REQUIS'                                   => 'Le nom est obligatoire.',
        'CATEGORIE_INTROUVABLE'                        => 'Catégorie introuvable.',
        'CATEGORIE_NON_VIDE'                           => 'Cette catégorie contient des produits : réassignez-les avant suppression.',
        'CATEGORIE_NON_VIDE_REASSIGNER_LES_PRODUITS'   => 'Cette catégorie contient des produits : réassignez-les avant suppression.',
        'PRODUIT_INTROUVABLE'                          => 'Produit introuvable.',
        'PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER'      => 'Produit déjà commandé : masquez-le au lieu de le supprimer.',
        'QUANTITE_REQUISE'                             => 'Une quantité est requise.',
        'RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE' => 'La commande doit contenir au moins une ligne.',
        'RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO'    => 'La quantité doit être supérieure à zéro.',
        'RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE'         => 'Une ligne de commande est définitive : prix et quantité non modifiables.',
        'RB09_COMMANDE_VALIDATEE_INTERDITE_DE_SUPPRIMER' => 'Une commande validée ne se supprime pas.',
        'RB10_COMMANDE_SANS_CLIENT'                    => 'La commande doit appartenir à un client existant.',
        'RB11_TRANSITION_STATUT_INTERDITE'             => 'Transition de statut interdite.',
        'RB15_MONTANT_CALCULE_INTERDIT'                => 'Le montant total est calculé par le moteur, jamais saisi.',
        'RB18_STOCK_INSUFFISANT'                       => 'Stock insuffisant pour cette quantité.',
        'STOCK_INSUFFISANT'                            => 'Stock insuffisant pour cette quantité.',
        'RB19_PANIER_VIDE'                             => 'Le panier est vide.',
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
        'MOTIF_STOCK_REQUIS'                           => 'Toute écriture de stock doit porter un motif.',
        'REFERENCE_DEJA_UTILISEE'                      => 'Cette référence produit existe déjà.',
        'SLUG_DEJA_UTILISE'                            => 'Ce slug produit existe déjà.',
        'NOM_DEJA_UTILISE'                             => 'Ce nom de catégorie existe déjà.',
        'SLUG_CATEGORIE_DEJA_UTILISE'                  => 'Ce slug de catégorie existe déjà.',
        'AUCUNE_MODIFICATION'                          => 'Aucune modification à appliquer.',
        'PARAMETRE_INTROUVABLE'                        => 'Paramètre inconnu : rechargez la page du back-office.',
        'PARAMETRE_VALEUR_INVALIDE'                    => 'Valeur invalide : un nombre positif est attendu (ex. 4.90).',
        'MESSAGE_CONTACT_REQUIS'                       => 'Merci de préciser votre nom, un email valide et un message.',
    ];
}
