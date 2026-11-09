<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Security\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Contrats de sécurité vérifiables statiquement : ils protègent des régressions
 * invisibles en test fonctionnel (une CSP affaiblie, un innerHTML réintroduit,
 * un style inline qui casse la CSP, un jeton CSRF trop court).
 */
final class SecurityContractTest extends TestCase
{
    private static function racine(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEchappementHtml(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        self::assertSame('&quot;quoted&quot; &amp; &#039;single&#039;', e('"quoted" & \'single\''));
        self::assertSame('', e(null));
    }

    public function testJetonCsrfLongEtAleatoire(): void
    {
        $jeton = Csrf::jeton();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $jeton);
        self::assertSame($jeton, Csrf::jeton(), 'le jeton doit être stable dans la session');
    }

    public function testLaCspNautoriseQueLOrigine(): void
    {
        $index = (string) file_get_contents(self::racine() . '/public/index.php');
        self::assertStringContainsString("default-src 'none'", $index);
        self::assertStringContainsString("script-src 'self'", $index);
        self::assertStringContainsString("style-src 'self'", $index);
        self::assertStringNotContainsString("'unsafe-inline'", $index, 'CSP affaiblie');
        self::assertStringNotContainsString("'unsafe-eval'", $index, 'CSP affaiblie');
    }

    public function testAucunStyleInlineDansLesVues(): void
    {
        $vues = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::racine() . '/app/View'));
        foreach ($vues as $fichier) {
            if (!$fichier instanceof \SplFileInfo || $fichier->getExtension() !== 'php') {
                continue;
            }
            $contenu = (string) file_get_contents($fichier->getPathname());
            self::assertDoesNotMatchRegularExpression(
                '/<[a-z][^>]*\sstyle\s*=/i',
                $contenu,
                'style inline interdit par la CSP : ' . $fichier->getFilename(),
            );
        }
    }

    public function testLeJsNutilisePasInnerHtml(): void
    {
        foreach (glob(self::racine() . '/public/js/*.js') ?: [] as $fichier) {
            $contenu = (string) file_get_contents($fichier);
            self::assertStringNotContainsString('innerHTML', $contenu, 'innerHTML interdit : ' . basename($fichier));
            self::assertStringNotContainsString('document.write', $contenu, 'document.write interdit : ' . basename($fichier));
        }
    }

    public function testAucunSecretEnDurDansLeCode(): void
    {
        $fichiers = array_merge(
            glob(self::racine() . '/app/**/*.php') ?: [],
            glob(self::racine() . '/app/*/*.php') ?: [],
            glob(self::racine() . '/app/*/*/*.php') ?: [],
            glob(self::racine() . '/public/*.php') ?: [],
        );

        self::assertNotEmpty($fichiers, 'aucun fichier analysé');

        foreach ($fichiers as $fichier) {
            $contenu = (string) file_get_contents($fichier);
            self::assertDoesNotMatchRegularExpression(
                '/\$(password|mot_de_passe|secret|api_key|token)\s*=\s*[\'"][^\'"]{4,}[\'"]/i',
                $contenu,
                'secret en dur : ' . basename($fichier),
            );
        }
    }

    public function testLesEntetesDeSecuriteSontEmis(): void
    {
        $index = (string) file_get_contents(self::racine() . '/public/index.php');
        foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Content-Security-Policy'] as $entete) {
            self::assertStringContainsString($entete, $index, "en-tête manquant : $entete");
        }
    }
}
