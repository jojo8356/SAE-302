<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Port de persistance des opérations d'authentification client.
 *
 * Les stratégies JSON et SQL fournissent le même contrat métier au
 * ClientRepository ; le contrôleur et les règles d'authentification ne
 * dépendent ainsi ni du stockage ni du dialecte SQL.
 */
interface ClientAccountGateway
{
    /** Crée un compte à partir de données déjà validées et normalisées. */
    public function createAccount(string $nom, string $prenom, string $email, string $hash): int;

    /**
     * Retourne les informations nécessaires à password_verify().
     *
     * @return array{id_client:int|null,hash:string|null,statut:'OK'|'COMPTE_BLOQUE'|'INCONNU'}
     */
    public function getCredentials(string $email): array;

    /** Met à jour les coordonnées du compte et protège l'unicité de l'email. */
    public function updateClient(
        int $idClient,
        string $nom,
        string $prenom,
        string $email,
        ?string $telephone,
        ?string $adresse,
        ?string $codePostal,
        ?string $ville,
    ): void;
}
