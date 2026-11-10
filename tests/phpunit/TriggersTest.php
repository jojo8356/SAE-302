<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\Triggers;
use PHPUnit\Framework\TestCase;

/**
 * RB-11 : la matrice de transitions est une donnée de sécurité.
 * Un élargissement accidentel doit faire échouer ce test.
 */
final class TriggersTest extends TestCase
{
    public function testLaMatriceCouvreTousLesStatuts(): void
    {
        foreach (Triggers::STATUTS as $statut) {
            self::assertArrayHasKey($statut, Triggers::TRANSITIONS, "statut sans entrée : $statut");
        }
    }

    public function testAucunStatutNeRevientEnArriere(): void
    {
        $ordre = ['BROUILLON' => 0, 'EN_PREPARATION' => 1, 'PAYEE' => 2, 'EXPEDIEE' => 3, 'LIVREE' => 4];

        foreach (Triggers::TRANSITIONS as $depuis => $cibles) {
            foreach ($cibles as $vers) {
                if ($vers === 'ANNULEE' || !isset($ordre[$depuis], $ordre[$vers])) {
                    continue;
                }
                self::assertGreaterThan($ordre[$depuis], $ordre[$vers], "$depuis → $vers remonte le cycle");
            }
        }
    }

    public function testLesEtatsTerminauxSontFermes(): void
    {
        self::assertSame([], Triggers::TRANSITIONS['LIVREE']);
        self::assertSame([], Triggers::TRANSITIONS['ANNULEE']);
    }

    public function testAnnulationImpossibleApresLivraisonEtParcoursExpedition(): void
    {
        self::assertNotContains('ANNULEE', Triggers::TRANSITIONS['LIVREE']);
        self::assertNotContains('BROUILLON', Triggers::TRANSITIONS['EXPEDIEE']);
        self::assertContains('ANNULEE', Triggers::TRANSITIONS['EN_PREPARATION']);
        self::assertContains('ANNULEE', Triggers::TRANSITIONS['PAYEE']);
        // Une commande EXPEDIEE n'est annulable que par un administrateur (sans retour de stock).
        self::assertContains('ANNULEE', Triggers::TRANSITIONS['EXPEDIEE']);
    }
}
