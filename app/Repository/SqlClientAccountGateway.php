<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\StoredProcedureExecutor;
use UnexpectedValueException;

/** Stratégie SQL : adapte sp_create_account / sp_get_credentials au port métier. */
final class SqlClientAccountGateway implements ClientAccountGateway
{
    public function __construct(private readonly StoredProcedureExecutor $procedures)
    {
    }

    public function createAccount(string $nom, string $prenom, string $email, string $hash): int
    {
        $resultat = $this->procedures->callProcedure(
            'sp_create_account',
            [$nom, $prenom, $email, $hash],
            ['p_id_client', 'p_code_retour'],
        );
        $sorties = $resultat['out'] ?? [];
        $code = $sorties['p_code_retour'] ?? null;

        if ($code === 'EMAIL_DEJA_UTILISE') {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }
        if ($code !== 'OK') {
            throw new UnexpectedValueException('Réponse inattendue de sp_create_account.');
        }

        $idClient = $sorties['p_id_client'] ?? null;
        if (!is_numeric($idClient) || (int) $idClient < 1) {
            throw new UnexpectedValueException('sp_create_account n’a pas retourné un identifiant client valide.');
        }

        return (int) $idClient;
    }

    public function getCredentials(string $email): array
    {
        $resultat = $this->procedures->callProcedure(
            'sp_get_credentials',
            [$email],
            ['p_id_client', 'p_hash', 'p_statut'],
        );
        $sorties = $resultat['out'] ?? [];
        $statut = $sorties['p_statut'] ?? null;

        if ($statut === 'INCONNU') {
            return ['id_client' => null, 'hash' => null, 'statut' => 'INCONNU'];
        }
        if (!in_array($statut, ['OK', 'COMPTE_BLOQUE'], true)) {
            throw new UnexpectedValueException('Réponse inattendue de sp_get_credentials.');
        }

        $idClient = $sorties['p_id_client'] ?? null;
        $hash = $sorties['p_hash'] ?? null;
        if (!is_numeric($idClient) || (int) $idClient < 1 || !is_string($hash) || $hash === '') {
            throw new UnexpectedValueException('sp_get_credentials n’a pas retourné des identifiants valides.');
        }

        return [
            'id_client' => (int) $idClient,
            'hash' => $hash,
            'statut' => $statut,
        ];
    }

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
        $resultat = $this->procedures->callProcedure(
            'sp_update_client',
            [$idClient, $nom, $prenom, $email, $telephone, $adresse, $codePostal, $ville],
            ['p_code_retour'],
        );
        $code = $resultat['out']['p_code_retour'] ?? null;

        if ($code === 'EMAIL_DEJA_UTILISE') {
            throw new BusinessError('EMAIL_DEJA_UTILISE');
        }
        if ($code !== 'OK') {
            if (is_string($code) && preg_match('/^[A-Z][A-Z0-9_]+$/', $code) === 1) {
                throw new BusinessError($code);
            }
            throw new UnexpectedValueException('Réponse inattendue de sp_update_client.');
        }
    }
}
