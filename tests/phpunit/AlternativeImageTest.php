<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\View\Image\AlternativeDecorative;
use App\View\Image\AlternativeInformative;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AlternativeImageTest extends TestCase
{
    public function testImageDecorativeEtDimensions(): void
    {
        $html = visuel_produit('img/produits/zen14.jpg', new AlternativeDecorative());
        self::assertNotNull($html);
        self::assertTrue(str_contains($html, 'alt=""'));
        self::assertTrue(str_contains($html, 'width="600" height="450"'));
        self::assertTrue(str_contains($html, 'loading="lazy"'));
    }

    public function testImageInformativeEchappeSonTexte(): void
    {
        $html = visuel_produit('img/produits/zen14.jpg', new AlternativeInformative('Écran "test" <script>'), 800, 600);
        self::assertNotNull($html);
        self::assertTrue(str_contains($html, 'alt="Écran &quot;test&quot; &lt;script&gt;"'));
        self::assertTrue(str_contains($html, 'width="800" height="600"'));
        self::assertFalse(str_contains($html, '<script>'));
    }

    public function testDescriptionInformativeVideRefusee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AlternativeInformative('   ');
    }

    public function testImageAbsenteOuDistanteNeProduitPasDeBalise(): void
    {
        $alternative = new AlternativeInformative('Un produit');
        self::assertNull(visuel_produit(null, $alternative));
        self::assertNull(visuel_produit('img/produits/inexistant.jpg', $alternative));
        self::assertNull(visuel_produit('https://exemple.fr/p.jpg', $alternative));
        self::assertNull(visuel_produit('../secret.jpg', $alternative));
    }

    public function testFicheProduitChoisitUneAlternativeInformative(): void
    {
        $produit = [
            'stock' => 0, 'image_url' => 'img/produits/zen14.jpg', 'nom' => 'Écran "test" <script>',
            'reference' => 'TEST', 'description' => 'Description', 'prix_ht' => 10, 'tva' => 20, 'prix_ttc' => 12,
        ];
        $categorie = null;
        $etat_stock = 'RUPTURE';
        ob_start();
        require dirname(__DIR__, 2) . '/app/View/catalogue/produit.php';
        $html = (string) ob_get_clean();
        self::assertTrue(str_contains($html, 'alt="Visuel indicatif : Écran &quot;test&quot; &lt;script&gt;"'));
        self::assertFalse(str_contains($html, '<script>'));
    }
}
