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
        } finally {
            @unlink($fichier);
            @rmdir($dossier);
        }
    }
}
