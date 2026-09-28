<?php

declare(strict_types=1);

/**
 * MiniShop — comptes clients (portage de sp_create_account, sp_get_credentials,
 * sp_update_client).
 *
 * Les mots de passe ne sont JAMAIS stockés ni renvoyés en clair : la couche
 * modèle compare les hash avec password_verify() côté contrôleur (RB-12,
 * SEC-04) — le hash ne quitte ce repository que pour la vérification.
 */

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;

final class ClientRepository
{
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /**
     * sp_create_account — création d'un compte (RB-01 : email unique).
     *
     * @param string $hash sortie de password_hash() (jamais du clair)
     * @return int id_client créé
     */
    public function createAccount(string $nom, string $prenom, string $email, string $hash): int
    {
        if (trim($nom) === '' || trim($prenom) === '') {
            throw new BusinessError('CHAMPS_OBLIGATOIRES');
        }
        if (strlen($hash) < 60) {
            // garde-fou : refuser une chaîne qui n'est pas une sortie de password_hash()
            throw new BusinessError('HASH_MOT_DE_PASSE_INVALIDE');
        }
        if ($this->store->exists('client', [Filter::eq('email', $email)])) {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }

        return $this->store->insert('client', [
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'email' => trim(strtolower($email)),
            'mot_de_passe_hash' => $hash,
        ]);
    }

    /**
     * sp_get_credentials — identifiants pour la connexion.
     * Le compte bloqué est refusé en couche modèle, pas seulement en PHP.
     *
     * @return array{id_client: int|null, hash: string|null, statut: 'OK'|'COMPTE_BLOQUE'|'INCONNU'}
     */
    public function getCredentials(string $email): array
    {
        $row = $this->store->findOne('client', [Filter::eq('email', trim(strtolower($email)))]);
        if ($row === null) {
            // message volontairement identique à « mot de passe erroné » côté IHM (SEC-05)
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

    /** Client complet par id (le hash n'est jamais affiché par les vues). */
    public function find(int $idClient): ?array
    {
        return $this->store->find('client', $idClient);
    }

    /**
     * sp_update_client — modification de ses propres informations (EF-CLI-01).
     * Le changement d'email ré-active la règle RB-01.
     */
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
        if ($this->store->find('client', $idClient) === null) {
            throw new BusinessError('CLIENT_INTROUVABLE');
        }
        $email = trim(strtolower($email));
        $doublon = $this->store->findOne('client', [
            Filter::eq('email', $email),
            Filter::neq('id_client', $idClient),
        ]);
        if ($doublon !== null) {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }
        $this->store->update('client', [Filter::eq('id_client', $idClient)], [
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'email' => $email,
            'telephone' => $telephone,
            'adresse_livraison' => $adresse,
            'code_postal' => $codePostal,
            'ville' => $ville,
        ]);
    }

    /**
     * Change le mot de passe d'un client (EF-CLI-02) : l'ancien mot de passe
     * est exigé et vérifié par l'appelant, le nouveau est re-haché (RB-12).
     */
    public function changeMotDePasse(int $idClient, string $nouveauHash): void
    {
        if ($this->store->find('client', $idClient) === null) {
            throw new BusinessError('CLIENT_INTROUVABLE');
        }
        if (strlen($nouveauHash) < 60) {
            throw new BusinessError('HASH_MOT_DE_PASSE_INVALIDE');
        }
        $this->store->update('client', [Filter::eq('id_client', $idClient)], ['mot_de_passe_hash' => $nouveauHash]);
    }

    /** Horodate la dernière connexion (traçabilité, sans bloquer le flux). */
    public function noteConnexion(int $idClient): void
    {
        $this->store->update('client', [Filter::eq('id_client', $idClient)], ['derniere_connexion' => date('Y-m-d H:i:s')]);
    }

    /** Admin : liste des clients (EF-ADM, lecture seule — RB-13 : l'admin ne GÈRE pas les comptes). */
    public function listerTous(): array
    {
        return $this->store->select('client', ['order' => [Filter::sort('nom'), Filter::sort('prenom')]]);
    }
}
