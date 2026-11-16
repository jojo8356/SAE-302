<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\BusinessError;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\SqlStore;
use App\Model\Data\StoredProcedureExecutor;
use App\Repository\ClientAccountGateway;
use App\Repository\ClientAccountGatewayFactory;
use App\Repository\ClientRepository;
use App\Repository\SqlClientAccountGateway;
use App\Repository\StoreClientAccountGateway;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientAccountGatewayTest extends TestCase
{
    public function testCreateAccountAdaptsSqlProcedureAndReturnsTheGeneratedId(): void
    {
        $procedures = new RecordingStoredProcedureExecutor([
            ['out' => ['p_id_client' => '42', 'p_code_retour' => 'OK']],
        ]);
        $gateway = new SqlClientAccountGateway($procedures);

        self::assertSame(42, $gateway->createAccount('Ada', 'Lovelace', 'ada@example.test', str_repeat('h', 60)));
        self::assertSame([
            'procedure' => 'sp_create_account',
            'inputs' => ['Ada', 'Lovelace', 'ada@example.test', str_repeat('h', 60)],
            'outputs' => ['p_id_client', 'p_code_retour'],
        ], $procedures->calls[0]);
    }

    public function testCreateAccountTranslatesProcedureDuplicateCodeToBusinessError(): void
    {
        $procedures = new RecordingStoredProcedureExecutor([
            ['out' => ['p_id_client' => null, 'p_code_retour' => 'EMAIL_DEJA_UTILISE']],
        ]);
        $gateway = new SqlClientAccountGateway($procedures);

        try {
            $gateway->createAccount('Ada', 'Lovelace', 'ada@example.test', str_repeat('h', 60));
            self::fail('Le code EMAIL_DEJA_UTILISE devait devenir une erreur métier.');
        } catch (BusinessError $erreur) {
            self::assertSame('EMAIL_DEJA_UTILISE', $erreur->businessCode);
        }
    }

    public function testUpdateClientCallsSqlProcedureAndTranslatesDuplicateEmail(): void
    {
        $procedures = new RecordingStoredProcedureExecutor([
            ['out' => ['p_code_retour' => 'OK']],
            ['out' => ['p_code_retour' => 'EMAIL_DEJA_UTILISE']],
        ]);
        $gateway = new SqlClientAccountGateway($procedures);
        $gateway->updateClient(7, 'Ada', 'Lovelace', 'ada@example.test', null, null, null, null);
        self::assertSame([
            'procedure' => 'sp_update_client',
            'inputs' => [7, 'Ada', 'Lovelace', 'ada@example.test', null, null, null, null],
            'outputs' => ['p_code_retour'],
        ], $procedures->calls[0]);

        try {
            $gateway->updateClient(7, 'Ada', 'Lovelace', 'taken@example.test', null, null, null, null);
            self::fail('Le doublon d’email devait devenir une erreur métier.');
        } catch (BusinessError $erreur) {
            self::assertSame('EMAIL_DEJA_UTILISE', $erreur->businessCode);
        }
    }

    public function testGetCredentialsUsesProcedureAndPreservesThePasswordVerifyContract(): void
    {
        $hash = password_hash('MotDePasseTest!', PASSWORD_BCRYPT, ['cost' => 4]);
        self::assertTrue(is_string($hash));
        $procedures = new RecordingStoredProcedureExecutor([
            ['out' => ['p_id_client' => '15', 'p_hash' => $hash, 'p_statut' => 'OK']],
            ['out' => ['p_id_client' => '16', 'p_hash' => $hash, 'p_statut' => 'COMPTE_BLOQUE']],
            ['out' => ['p_id_client' => null, 'p_hash' => null, 'p_statut' => 'INCONNU']],
        ]);
        $gateway = new SqlClientAccountGateway($procedures);

        $actif = $gateway->getCredentials('ada@example.test');
        $bloque = $gateway->getCredentials('bloque@example.test');
        $inconnu = $gateway->getCredentials('absent@example.test');

        self::assertSame(['id_client' => 15, 'hash' => $hash, 'statut' => 'OK'], $actif);
        self::assertTrue(password_verify('MotDePasseTest!', (string) $actif['hash']));
        self::assertSame(['id_client' => 16, 'hash' => $hash, 'statut' => 'COMPTE_BLOQUE'], $bloque);
        self::assertSame(['id_client' => null, 'hash' => null, 'statut' => 'INCONNU'], $inconnu);
        self::assertSame([
            [
                'procedure' => 'sp_get_credentials',
                'inputs' => ['ada@example.test'],
                'outputs' => ['p_id_client', 'p_hash', 'p_statut'],
            ],
            [
                'procedure' => 'sp_get_credentials',
                'inputs' => ['bloque@example.test'],
                'outputs' => ['p_id_client', 'p_hash', 'p_statut'],
            ],
            [
                'procedure' => 'sp_get_credentials',
                'inputs' => ['absent@example.test'],
                'outputs' => ['p_id_client', 'p_hash', 'p_statut'],
            ],
        ], $procedures->calls);
    }

    public function testJsonStoreSelectsTheGenericStrategy(): void
    {
        $store = JsonStore::open(MINISHOP_TESTS_TMP . '/account_gateway_' . bin2hex(random_bytes(4)));

        self::assertTrue(ClientAccountGatewayFactory::forStore($store) instanceof StoreClientAccountGateway);
    }

    public function testSqlStoreSelectsProcedureStrategyWithoutOpeningAConnection(): void
    {
        self::assertTrue(ClientAccountGatewayFactory::forStore(new SqlStore()) instanceof SqlClientAccountGateway);
    }

    public function testRepositoryNormalizesInputAndDelegatesToInjectedGateway(): void
    {
        $store = JsonStore::open(MINISHOP_TESTS_TMP . '/account_repository_' . bin2hex(random_bytes(4)));
        Seed::force($store);
        $gateway = new RecordingClientAccountGateway();
        $repository = new ClientRepository($store, $gateway);
        $hash = str_repeat('h', 60);

        self::assertSame(73, $repository->createAccount(' Ada ', ' Lovelace ', ' ADA@Example.Test ', $hash));
        self::assertSame(['Ada', 'Lovelace', 'ada@example.test', $hash], $gateway->created);

        $attendu = ['id_client' => 73, 'hash' => $hash, 'statut' => 'OK'];
        $gateway->credentials = $attendu;
        self::assertSame($attendu, $repository->getCredentials(' ADA@Example.Test '));
        self::assertSame('ada@example.test', $gateway->lookedUpEmail);

        $repository->updateClient(1, ' Ada ', ' Lovelace ', ' ADA@Example.Test ', '06 00 00 00 00', '1 rue Test', '06000', 'Nice');
        self::assertSame([
            'id_client' => 1,
            'nom' => 'Ada',
            'prenom' => 'Lovelace',
            'email' => 'ada@example.test',
            'telephone' => '06 00 00 00 00',
            'adresse' => '1 rue Test',
            'code_postal' => '06000',
            'ville' => 'Nice',
        ], $gateway->updated);
    }
}

/** Test double du port SQL : mémorise les appels et renvoie les sorties en file. */
final class RecordingStoredProcedureExecutor implements StoredProcedureExecutor
{
    /** @var list<array{procedure:string,inputs:list<mixed>,outputs:list<string>}> */
    public array $calls = [];

    /** @param list<array{out:array<string,mixed>}> $responses */
    public function __construct(private array $responses)
    {
    }

    public function callProcedure(string $procedure, array $inputs = [], array $outputs = []): array
    {
        $this->calls[] = ['procedure' => $procedure, 'inputs' => $inputs, 'outputs' => $outputs];
        $response = array_shift($this->responses);
        if (!is_array($response)) {
            throw new RuntimeException('Aucune réponse de procédure de test configurée.');
        }

        return $response;
    }
}

/** Test double injectable dans ClientRepository pour vérifier le port métier. */
final class RecordingClientAccountGateway implements ClientAccountGateway
{
    /** @var list<string>|null */
    public ?array $created = null;

    public ?string $lookedUpEmail = null;

    /** @var array{id_client:int|null,hash:string|null,statut:'OK'|'COMPTE_BLOQUE'|'INCONNU'} */
    public array $credentials = ['id_client' => null, 'hash' => null, 'statut' => 'INCONNU'];

    /** @var array<string,mixed>|null */
    public ?array $updated = null;

    public function createAccount(string $nom, string $prenom, string $email, string $hash): int
    {
        $this->created = [$nom, $prenom, $email, $hash];

        return 73;
    }

    public function getCredentials(string $email): array
    {
        $this->lookedUpEmail = $email;

        return $this->credentials;
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
        $this->updated = [
            'id_client' => $idClient,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'adresse' => $adresse,
            'code_postal' => $codePostal,
            'ville' => $ville,
        ];
    }
}
