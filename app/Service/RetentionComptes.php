<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Data\Filter;
use App\Model\Data\SqlStore;
use App\Model\Data\StoreInterface;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Suppression conservatrice en base active ; ni commandes, ni journaux ni sauvegardes. */
final class RetentionComptes
{
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** @return array{examinés:int,supprimés:int,éligibles:int,commandes:int,dates_invalides:int} */
    public function traiter(DateTimeImmutable $maintenant, bool $executer): array
    {
        $parametre = $this->store->find('parametre', 'conservation_compte');
        $mois = (string) ($parametre['valeur'] ?? '');
        if (preg_match('/^[1-9][0-9]{0,2}$/D', $mois) !== 1 || (int) $mois > 120) {
            throw new RuntimeException('Durée de conservation absente ou invalide : aucune suppression.');
        }
        $maintenant = $maintenant->setTimezone(new DateTimeZone('UTC'));
        // Marge de deux jours : activité arrondie à l'heure et anciens fuseaux incertains.
        // Calculer l'échéance depuis chaque date évite les effets de bord du
        // décalage de mois aux dates de fin de mois (29, 30 et 31).
        $bilan = ['examinés' => 0, 'supprimés' => 0, 'éligibles' => 0, 'commandes' => 0, 'dates_invalides' => 0];
        foreach ($this->store->select('client') as $candidat) {
            $id = (int) $candidat['id_client'];
            $resultat = $this->store->transactional(function () use ($id, $maintenant, $executer, $mois): string {
                if ($this->store instanceof SqlStore) {
                    $client = $this->store->findForUpdate('client', $id);
                } else {
                    $client = $this->store->find('client', $id);
                }
                if ($client === null) {
                    return 'absent';
                }
                $parametreCourant = $this->store->find('parametre', 'conservation_compte');
                if ((string) ($parametreCourant['valeur'] ?? '') !== $mois) {
                    throw new RuntimeException('Durée modifiée pendant le traitement : arrêt.');
                }
                // Ne jamais traiter sans date de création fiable ou avec une date future.
                $creation = self::dateUtc($client['date_creation'] ?? null);
                $texteActivite = $client['derniere_connexion'] ?? null;
                $activite = self::dateUtc($texteActivite);
                if ($creation === null || $creation > $maintenant || ($texteActivite !== null && $texteActivite !== '' && ($activite === null || $activite > $maintenant))) {
                    return 'dates_invalides';
                }
                $expirationCreation = $creation->modify('+' . (int) $mois . ' months')->modify('+2 days');
                if ($expirationCreation > $maintenant) {
                    return 'recent';
                }
                if ($activite !== null && $activite->modify('+' . (int) $mois . ' months')->modify('+2 days') > $maintenant) {
                    return 'recent';
                }
                if ($this->store->exists('commande', [Filter::eq('id_client', $id)])) {
                    return 'commandes'; // Conservation à examiner ; aucune prétention d'archivage RGPD.
                }
                if (!$executer) {
                    return 'éligibles';
                }
                $this->store->delete('client', [Filter::eq('id_client', $id)]);
                return 'supprimés';
            });
            ++$bilan['examinés'];
            if (isset($bilan[$resultat])) {
                ++$bilan[$resultat];
            }
        }
        return $bilan;
    }

    public static function dateUtc(mixed $valeur): ?DateTimeImmutable
    {
        if (!is_string($valeur) || $valeur === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valeur, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s') !== $valeur) {
            return null;
        }
        return $date;
    }
}
