<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\Journal;
use App\Model\Data\JsonTable;
use PHPUnit\Framework\TestCase;

/** Contrats sur fichiers temporaires indépendants des données de l'application. */
final class JournalEtTableTest extends TestCase
{
    public function testLimitesEtLignesInvalidesDuJournal(): void
    {
        $dossier = MINISHOP_TESTS_TMP . '/journal_' . bin2hex(random_bytes(4));
        mkdir($dossier, 0775, true);
        $fichier = $dossier . '/journal.jsonl';
        try {
            $journal = Journal::create($fichier);
            self::assertNotNull($journal);
            self::assertSame([], $journal->tail());
            $journal->log('insert', 'produit', 1, null, ['nom' => 'test'], []);
            $journal->log('update', 'produit', 2, null, ['nom' => 'test2'], []);
            file_put_contents($fichier, "pas du JSON\n", FILE_APPEND);
            self::assertSame([], $journal->tail(0));
            self::assertSame([], $journal->tail(-1));
            self::assertSame([], $journal->tail(1));
            self::assertSame([2], array_column($journal->tail(2), 'pk'));
            self::assertSame([1, 2], array_column($journal->tail(20), 'pk'));
        } finally {
            @unlink($fichier);
            @rmdir($dossier);
        }
    }

    public function testComptesSansCoordonneesNiHashDansLesNouvellesTraces(): void
    {
        $dossier = MINISHOP_TESTS_TMP . '/journal_prive_' . bin2hex(random_bytes(4));
        mkdir($dossier, 0700, true);
        $fichier = $dossier . '/journal.jsonl';
        try {
            $journal = Journal::create($fichier);
            self::assertNotNull($journal);
            $avant = ['id_admin' => 7, 'nom' => 'NOM_SECRET', 'email' => 'ancien@example.invalid',
                'mot_de_passe_hash' => 'HASH_ANCIEN', 'role' => 'GESTIONNAIRE', 'actif' => true];
            $apres = ['id_admin' => 7, 'nom' => 'NOM_SECRET', 'email' => 'nouveau@example.invalid',
                'mot_de_passe_hash' => 'HASH_NOUVEAU', 'role' => 'SUPER', 'actif' => false];
            $journal->log('update', 'administrateur', 7, $avant, $apres, []);
            $journal->log('insert', 'client', 8, null, ['id_client' => 8, 'email' => 'client@example.invalid', 'mot_de_passe_hash' => 'HASH_CLIENT', 'actif' => true], ['role' => 'CLIENT', 'nom' => 'NOM_CLIENT']);
            $trace = (string) file_get_contents($fichier);
            foreach (['HASH_ANCIEN', 'HASH_NOUVEAU', 'HASH_CLIENT', 'NOM_SECRET', 'NOM_CLIENT', '@example.invalid', 'mot_de_passe_hash'] as $secret) {
                self::assertStringNotContainsString($secret, $trace);
            }
            $lignes = $journal->tail();
            self::assertSame(['id_admin' => 7, 'role' => 'GESTIONNAIRE', 'actif' => true], $lignes[0]['old']);
            self::assertSame(['id_admin' => 7, 'role' => 'SUPER', 'actif' => false], $lignes[0]['new']);
            self::assertSame(null, $lignes[1]['actor']['nom']);
        } finally {
            foreach (glob($dossier . '/*') ?: [] as $path) { @unlink($path); }
            foreach (glob($dossier . '/.*') ?: [] as $path) { if (is_file($path)) { @unlink($path); } }
            @rmdir($dossier);
        }
    }

    public function testTransitionDirtyEtPersistance(): void
    {
        $dossier = MINISHOP_TESTS_TMP . '/table_' . bin2hex(random_bytes(4));
        mkdir($dossier, 0775, true);
        $fichier = $dossier . '/table.json';
        try {
            $table = new JsonTable('test', $fichier, true);
            self::assertFalse($table->isDirty());
            $table->append(['id' => 1]);
            self::assertTrue($table->isDirty());
            $table->flush();
            self::assertFalse($table->isDirty());
            self::assertSame([['id' => 1]], (new JsonTable('test', $fichier, true))->rows());
            $snapshot = $table->snapshot();
            $table->append(['id' => 2]);
            $table->restore($snapshot);
            self::assertSame([['id' => 1]], $table->rows());
            $table->flush();
            self::assertSame([['id' => 1]], (new JsonTable('test', $fichier, true))->rows());
        } finally {
            @unlink($fichier);
            @rmdir($dossier);
        }
    }
}
