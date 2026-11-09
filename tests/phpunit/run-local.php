<?php

declare(strict_types=1);

/**
 * MiniShop — exécuteur PHPUnit hors ligne (aucune installation Composer).
 *
 * Pourquoi : la sandbox de développement n'a ni Composer ni Packagist. Pour
 * autant, les tests doivent rester écrivables en PHPUnit (standard de
 * l'industrie) et exécutables EN LOCAL. Ce fichier fournit donc un substitut
 * minimal de `PHPUnit\Framework\TestCase` (assertions utilisées par la suite,
 * setUp/tearDown, expectException) : les mêmes fichiers de test tournent
 * tels quels avec le vrai PHPUnit en CI (`composer test:unit`).
 *
 * Usage :  php tests/phpunit/run-local.php
 *          node scripts/wasm_run.mjs tests/phpunit/run-local.php
 */

namespace PHPUnit\Framework {

    class AssertionFailedError extends \Exception
    {
    }

    abstract class TestCase
    {
        private ?string $exceptionAttendue = null;

        protected function setUp(): void
        {
        }

        protected function tearDown(): void
        {
        }

        /** Appel interne par l'exécuteur (setUp/tearDown sont protégés en PHPUnit). */
        public function preparer(): void
        {
            $this->setUp();
        }

        /** Appel interne par l'exécuteur. */
        public function nettoyer(): void
        {
            $this->tearDown();
        }

        public function exceptionAttendue(): ?string
        {
            return $this->exceptionAttendue;
        }

        private static function echec(string $message): never
        {
            throw new AssertionFailedError($message);
        }

        public static function fail(string $message = ''): never
        {
            self::echec($message === '' ? 'échec' : $message);
        }

        public static function assertSame(mixed $attendu, mixed $obtenu, string $message = ''): void
        {
            if ($attendu !== $obtenu) {
                self::echec($message . ' — attendu ' . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true));
            }
        }

        public static function assertNotSame(mixed $attendu, mixed $obtenu, string $message = ''): void
        {
            if ($attendu === $obtenu) {
                self::echec($message . ' — valeurs identiques alors qu\'elles devaient différer');
            }
        }

        public static function assertEquals(mixed $attendu, mixed $obtenu, string $message = ''): void
        {
            if ($attendu != $obtenu) {
                self::echec($message . ' — attendu ' . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true));
            }
        }

        public static function assertEqualsWithDelta(mixed $attendu, mixed $obtenu, float $delta, string $message = ''): void
        {
            if (!is_numeric($attendu) || !is_numeric($obtenu) || abs((float) $attendu - (float) $obtenu) > $delta) {
                self::echec($message . " — attendu $attendu ± $delta, obtenu " . var_export($obtenu, true));
            }
        }

        public static function assertTrue(mixed $valeur, string $message = ''): void
        {
            if ($valeur !== true) {
                self::echec($message . ' — vrai attendu');
            }
        }

        public static function assertFalse(mixed $valeur, string $message = ''): void
        {
            if ($valeur !== false) {
                self::echec($message . ' — faux attendu');
            }
        }

        public static function assertNull(mixed $valeur, string $message = ''): void
        {
            if ($valeur !== null) {
                self::echec($message . ' — null attendu');
            }
        }

        public static function assertNotNull(mixed $valeur, string $message = ''): void
        {
            if ($valeur === null) {
                self::echec($message . ' — valeur non nulle attendue');
            }
        }

        public static function assertCount(int $nombre, iterable $ensemble, string $message = ''): void
        {
            $reel = is_countable($ensemble) ? count($ensemble) : iterator_count($ensemble);
            if ($reel !== $nombre) {
                self::echec($message . " — $nombre éléments attendus, $reel obtenus");
            }
        }

        public static function assertNotEmpty(mixed $valeur, string $message = ''): void
        {
            if (empty($valeur)) {
                self::echec($message . ' — valeur non vide attendue');
            }
        }

        public static function assertEmpty(mixed $valeur, string $message = ''): void
        {
            if (!empty($valeur)) {
                self::echec($message . ' — valeur vide attendue');
            }
        }

        public static function assertArrayHasKey(mixed $cle, array $tableau, string $message = ''): void
        {
            if (!array_key_exists($cle, $tableau)) {
                self::echec($message . ' — clé absente : ' . (string) $cle);
            }
        }

        public static function assertGreaterThan(mixed $seuil, mixed $valeur, string $message = ''): void
        {
            if (!($valeur > $seuil)) {
                self::echec($message . " — $valeur n'est pas > $seuil");
            }
        }

        public static function assertGreaterThanOrEqual(mixed $seuil, mixed $valeur, string $message = ''): void
        {
            if (!($valeur >= $seuil)) {
                self::echec($message . " — $valeur n'est pas ≥ $seuil");
            }
        }

        public static function assertLessThan(mixed $seuil, mixed $valeur, string $message = ''): void
        {
            if (!($valeur < $seuil)) {
                self::echec($message . " — $valeur n'est pas < $seuil");
            }
        }

        public static function assertLessThanOrEqual(mixed $seuil, mixed $valeur, string $message = ''): void
        {
            if (!($valeur <= $seuil)) {
                self::echec($message . " — $valeur n'est pas ≤ $seuil");
            }
        }

        public static function assertContains(mixed $aiguille, iterable $botte, string $message = ''): void
        {
            foreach ($botte as $element) {
                if ($element === $aiguille) {
                    return;
                }
            }
            self::echec($message . ' — valeur absente : ' . var_export($aiguille, true));
        }

        public static function assertNotContains(mixed $aiguille, iterable $botte, string $message = ''): void
        {
            foreach ($botte as $element) {
                if ($element === $aiguille) {
                    self::echec($message . ' — valeur présente alors qu\'elle devait être absente');
                }
            }
        }

        public static function assertStringContainsString(string $aiguille, string $botte, string $message = ''): void
        {
            if (!str_contains($botte, $aiguille)) {
                self::echec($message . ' — chaîne absente : ' . $aiguille);
            }
        }

        public static function assertStringNotContainsString(string $aiguille, string $botte, string $message = ''): void
        {
            if (str_contains($botte, $aiguille)) {
                self::echec($message . ' — chaîne présente alors qu\'elle devait être absente : ' . $aiguille);
            }
        }

        public static function assertMatchesRegularExpression(string $motif, string $sujet, string $message = ''): void
        {
            if (preg_match($motif, $sujet) !== 1) {
                self::echec($message . ' — motif non trouvé : ' . $motif);
            }
        }

        public static function assertDoesNotMatchRegularExpression(string $motif, string $sujet, string $message = ''): void
        {
            if (preg_match($motif, $sujet) === 1) {
                self::echec($message . ' — motif interdit trouvé : ' . $motif);
            }
        }

        public function expectException(string $classe): void
        {
            $this->exceptionAttendue = $classe;
        }
    }
}

namespace {

    ob_start();
    require __DIR__ . '/bootstrap.php';

    $fichiers = glob(__DIR__ . '/*Test.php') ?: [];
    sort($fichiers);

    $tests = 0;
    $echecs = 0;
    $details = [];

    foreach ($fichiers as $fichier) {
        require_once $fichier;
    }

    foreach (get_declared_classes() as $classe) {
        if (!str_starts_with($classe, 'MiniShop\\Tests\\') || !is_subclass_of($classe, PHPUnit\Framework\TestCase::class)) {
            continue;
        }
        if ((new ReflectionClass($classe))->isAbstract()) {
            continue;
        }

        $methodes = array_filter(
            (new ReflectionClass($classe))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $m): bool => str_starts_with($m->getName(), 'test'),
        );

        echo "\n" . basename(str_replace('\\', '/', $classe)) . "\n";
        foreach ($methodes as $methode) {
            ++$tests;
            $instance = new $classe();
            $avant = count(get_declared_classes());

            try {
                $instance->preparer();
                $methode->invoke($instance);

                $attendue = $instance->exceptionAttendue();
                if ($attendue !== null) {
                    throw new PHPUnit\Framework\AssertionFailedError("exception $attendue attendue, aucune levée");
                }
                echo '  ✅ ' . $methode->getName() . "\n";
            } catch (Throwable $e) {
                $attendue = null;
                try {
                    $attendue = $instance->exceptionAttendue();
                } catch (Throwable) {
                    $attendue = null;
                }

                $estAttendue = false;
                if ($attendue !== null) {
                    if ($e instanceof PHPUnit\Framework\AssertionFailedError) {
                        $estAttendue = false;
                    } else {
                        $estAttendue = $e instanceof $attendue;
                    }
                }

                if ($estAttendue) {
                    echo '  ✅ ' . $methode->getName() . ' (exception ' . $attendue . " attendue)\n";
                } else {
                    ++$echecs;
                    $details[] = $classe . '::' . $methode->getName() . ' — ' . $e->getMessage();
                    echo '  ❌ ' . $methode->getName() . ' — ' . $e->getMessage() . "\n";
                }
            } finally {
                try {
                    $instance->nettoyer();
                } catch (Throwable) {
                    // un tearDown défaillant ne doit pas masquer le résultat
                }
                unset($instance);
                gc_collect_cycles();
            }
        }
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    if ($echecs === 0) {
        echo "PHPUnit (exécuteur local) : $tests tests, TOUT EST VERT ✅\n";
        ob_end_flush();
        exit(0);
    }

    echo "PHPUnit (exécuteur local) : $echecs échec(s) sur $tests tests ❌\n";
    foreach ($details as $detail) {
        echo '  - ' . $detail . "\n";
    }
    ob_end_flush();
    exit(1);
}
