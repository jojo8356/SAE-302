<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Tools\Heure;

/** Stratégie de persistance générique, utilisée notamment par le pilote JSON. */
final class StoreClientAccountGateway implements ClientAccountGateway
{
    /** Injecte le store générique (JSON ou SQL sans procédures). */
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** Crée un compte client dans le magasin générique (contrôle d'unicité applicatif). */
    public function createAccount(string $nom, string $prenom, string $email, string $hash): int
    {
        if ($this->store->exists('client', [Filter::eq('email', $email)])) {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }

        return $this->store->insert('client', [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'mot_de_passe_hash' => $hash,
            'date_creation' => Heure::horodatageStockage(),
        ]);
    }

    /** Lit les identifiants client (id, hash, statut actif/bloqué) par email. */
    public function getCredentials(string $email): array
    {
        $row = $this->store->findOne('client', [Filter::eq('email', $email)]);
        if ($row === null) {
            return ['id_client' => null, 'hash' => null, 'statut' => 'INCONNU'];
        }

        $statut = 'COMPTE_BLOQUE';
        if ($row['actif']) {
            $statut = 'OK';
        }

        return [
            'id_client' => (int) $row['id_client'],
            'hash' => (string) $row['mot_de_passe_hash'],
            'statut' => $statut,
        ];
    }

    /** Met à jour les coordonnées client après contrôle d'unicité d'adresse email. */
    public function updateClient(
        int $idClient,
        string $nom,
        string $prenom,
        string $email,
        ?string $telephone,
        ?string $adresse,
        ?string $codePostal,
        ?string $ville,
    ): void {
        $doublon = $this->store->findOne('client', [
            Filter::eq('email', $email),
            Filter::neq('id_client', $idClient),
        ]);
        if ($doublon !== null) {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }

        $this->store->update('client', [Filter::eq('id_client', $idClient)], [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'adresse_livraison' => $adresse,
            'code_postal' => $codePostal,
            'ville' => $ville,
        ]);
    }
}
