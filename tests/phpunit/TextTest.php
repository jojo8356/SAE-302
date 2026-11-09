<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\Text;
use PHPUnit\Framework\TestCase;

/**
 * Collation : le moteur doit se comporter comme `utf8mb4_unicode_ci` (RB-19).
 */
final class TextTest extends TestCase
{
    public function testFoldEstInsensibleALaCasseEtAuxAccents(): void
    {
        self::assertSame(Text::fold('ÉCRAN'), Text::fold('écran'));
    }

    public function testFoldPlieLesLigatures(): void
    {
        self::assertSame('oeuvre aether strasse', Text::fold('œuvre Æther straße'));
    }

    public function testFoldEstIdempotent(): void
    {
        $une = Text::fold('Çà et Là Œuf');
        self::assertSame($une, Text::fold($une));
    }

    public function testFoldAccepteNullEtVide(): void
    {
        self::assertSame('', Text::fold(null));
        self::assertSame('', Text::fold(''));
    }

    public function testSlug(): void
    {
        $cas = [
            'Écran 27 pouces' => 'ecran-27-pouces',
            'Objets connectés: santé & maison !' => 'objets-connectes-sante-maison',
            '  --Casque (sans fil)--  ' => 'casque-sans-fil',
            '???' => '',
        ];

        foreach ($cas as $entree => $attendu) {
            self::assertSame($attendu, Text::slug($entree), 'slug de « ' . $entree . ' »');
        }
    }

    public function testCompareSuitLaCollation(): void
    {
        self::assertSame(0, Text::compare('Écran', 'ecran'));
        self::assertLessThan(0, Text::compare('Casque', 'Clavier'));
    }
}
