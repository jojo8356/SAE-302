<?php

declare(strict_types=1);

/**
 * MiniShop — énumération du pilote de gestion des données.
 *
 * La couche modèle parle EXCLUSIVEMENT à StoreInterface. Le pilote concret
 * est choisi par cette énumération (config app/Config/env.php, clé
 * « storage_driver ») :
 *
 *   - StorageDriver::JSON → App\Model\Data\JsonStore  (pilote actif) :
 *     chaque table est un fichier JSON, toutes les opérations (filtre, tri,
 *     jointure, agrégation) sont des opérations natives sur des tableaux
 *     associatifs. AUCUNE requête SQL n'est construite, ni ici ni ailleurs.
 *
 *   - StorageDriver::SQL → App\Model\Data\SqlStore (pilote prévu pour la
 *     migration finale, lot L14+) : mêmes méthodes d'interface, mêmes codes
 *     d'erreur, mais implémentées au-dessus de MySQL/PDO (sql/01→04 livrés
 *     dans le dépôt). Le basculement ne touchera ni les contrôleurs, ni les
 *     vues, ni les tests fonctionnels : seule la factory change.
 */

namespace App\Model\Data;

enum StorageDriver: string
{
    /** Stockage fichier JSON natif — moteur « intelligent » du projet. */
    case JSON = 'json';

    /** Stockage relationnel MySQL/PDO — cible de la migration finale. */
    case SQL = 'sql';

    /** Pilote par défaut si la configuration ne dit rien. */
    public const DEFAULT = self::JSON;

    /** Instancie le pilote correspondant à la valeur de configuration. */
    public function createStore(): StoreInterface
    {
        return match ($this) {
            self::JSON => JsonStore::open(self::defaultDataPath()),
            self::SQL  => new SqlStore(),
        };
    }

    /** Chemin par défaut des fichiers de données (racine du projet). */
    public static function defaultDataPath(): string
    {
        return dirname(__DIR__, 3) . '/data/minishop';
    }

    /** Depuis une chaîne de configuration ('json' / 'sql'), avec repli. */
    public static function fromConfig(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::DEFAULT;
    }
}
