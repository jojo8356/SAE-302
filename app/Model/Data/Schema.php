<?php

declare(strict_types=1);

/**
 * MiniShop — schéma de données descriptif (portrait fidèle de sql/01_minishop_schema.sql).
 *
 * Le schéma est décrit en DONNÉES (pas en SQL) : chaque table, colonne,
 * contrainte UNIQUE, CHECK, ENUM et clé étrangère est déclaré ici une seule
 * fois. Le pilote JSON (JsonStore) l'applique à chaque écriture ; le pilote
 * SQL de la migration finale le traduira en DDL (l'équivalent existe déjà
 * dans sql/01, chargé par scripts/load_db.sh).
 *
 * Types gérés : int, decimal (2 décimales, arrondi américain), string,
 * bool (0/1), datetime ('Y-m-d H:i:s'), text.
 * Les CHECK sont exprimés avec la même grammaire déclarative que les filtres
 * de StoreInterface : [['prix_ht', '>', 0]] → CHECK (prix_ht > 0).
 */

namespace App\Model\Data;

final class Schema
{
    /**
     * @return array<string, array{
     *   pk: string,
     *   autoIncrement: bool,
     *   columns: array<string, array{type: string, required?: bool, default?: mixed, enum?: list<string>, max?: int}>,
     *   unique: array<string, list<string>>,
     *   checks: array<string, list<mixed>>,
     *   foreignKeys: array<string, array{table: string, column: string, onDelete: 'RESTRICT'|'CASCADE'}>,
     *   indexes: list<string>
     * }>
     */
    public static function tables(): array
    {
        return self::$tables ??= [
            // ------------------------------------------------ back-office
            'administrateur' => [
                'pk' => 'id_admin', 'autoIncrement' => true,
                'columns' => [
                    'id_admin' => ['type' => 'int', 'required' => true],
                    'nom' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'prenom' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'email' => ['type' => 'string', 'required' => true, 'max' => 190],
                    'mot_de_passe_hash' => ['type' => 'string', 'required' => true, 'max' => 255],
                    'role' => ['type' => 'string', 'default' => 'GESTIONNAIRE', 'enum' => ['SUPER', 'GESTIONNAIRE']],
                    'actif' => ['type' => 'bool', 'default' => true],
                    'date_creation' => ['type' => 'datetime'],
                    'derniere_connexion' => ['type' => 'datetime'],
                ],
                'unique' => ['uk_admin_email' => ['email']],
                'checks' => [],
                'foreignKeys' => [],
                'indexes' => ['email'],
            ],
            // ------------------------------------------------ front-office
            'client' => [
                'pk' => 'id_client', 'autoIncrement' => true,
                'columns' => [
                    'id_client' => ['type' => 'int', 'required' => true],
                    'nom' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'prenom' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'email' => ['type' => 'string', 'required' => true, 'max' => 190],
                    'mot_de_passe_hash' => ['type' => 'string', 'required' => true, 'max' => 255],
                    'telephone' => ['type' => 'string', 'max' => 20],
                    'adresse_livraison' => ['type' => 'string', 'max' => 255],
                    'code_postal' => ['type' => 'string', 'max' => 10],
                    'ville' => ['type' => 'string', 'max' => 100],
                    'actif' => ['type' => 'bool', 'default' => true],
                    'date_creation' => ['type' => 'datetime'],
                    'derniere_connexion' => ['type' => 'datetime'],
                ],
                'unique' => ['uk_client_email' => ['email']],
                'checks' => [],
                'foreignKeys' => [],
                'indexes' => ['email'],
            ],
            'categorie' => [
                'pk' => 'id_categorie', 'autoIncrement' => true,
                'columns' => [
                    'id_categorie' => ['type' => 'int', 'required' => true],
                    'nom' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'slug' => ['type' => 'string', 'required' => true, 'max' => 120],
                    'description' => ['type' => 'string', 'max' => 500],
                ],
                'unique' => ['uk_categorie_nom' => ['nom'], 'uk_categorie_slug' => ['slug']],
                'checks' => [],
                'foreignKeys' => [],
                'indexes' => ['slug'],
            ],
            'produit' => [
                'pk' => 'id_produit', 'autoIncrement' => true,
                'columns' => [
                    'id_produit' => ['type' => 'int', 'required' => true],
                    'reference' => ['type' => 'string', 'required' => true, 'max' => 30],
                    'nom' => ['type' => 'string', 'required' => true, 'max' => 150],
                    'slug' => ['type' => 'string', 'required' => true, 'max' => 180],
                    'description' => ['type' => 'text'],
                    'prix_ht' => ['type' => 'decimal', 'required' => true],
                    'tva' => ['type' => 'decimal', 'default' => 20.0],
                    'prix_ttc' => ['type' => 'decimal'],
                    'stock' => ['type' => 'int', 'default' => 0],
                    'stock_initial' => ['type' => 'int', 'default' => -1],
                    'seuil_alerte' => ['type' => 'int', 'default' => 3],
                    'id_categorie' => ['type' => 'int', 'required' => true],
                    'visible' => ['type' => 'bool', 'default' => true],
                    'image_url' => ['type' => 'string', 'max' => 255],
                    'date_creation' => ['type' => 'datetime'],
                    'date_modification' => ['type' => 'datetime'],
                ],
                'unique' => ['uk_produit_reference' => ['reference'], 'uk_produit_slug' => ['slug']],
                'checks' => [
                    'ck_produit_prix' => [['prix_ht', '>', 0]],        // RB-02
                    'ck_produit_stock' => [['stock', '>=', 0]],        // RB-03
                    'ck_produit_visible' => [['visible', 'in', [0, 1]]],
                ],
                'foreignKeys' => [
                    'id_categorie' => ['table' => 'categorie', 'column' => 'id_categorie', 'onDelete' => 'RESTRICT'],
                ],
                'indexes' => ['id_categorie', 'visible', 'reference', 'slug'],
            ],
            'parametre' => [
                'pk' => 'cle', 'autoIncrement' => false,
                'columns' => [
                    'cle' => ['type' => 'string', 'required' => true, 'max' => 50],
                    'valeur' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'unite' => ['type' => 'string', 'max' => 10],
                    'comment' => ['type' => 'string', 'max' => 255],
                    'date_modification' => ['type' => 'datetime'],
                ],
                'unique' => [],
                'checks' => [],
                'foreignKeys' => [],
                'indexes' => [],
            ],
            'commande' => [
                'pk' => 'id_commande', 'autoIncrement' => true,
                'columns' => [
                    'id_commande' => ['type' => 'int', 'required' => true],
                    'numero' => ['type' => 'string', 'required' => true, 'max' => 20],
                    'id_client' => ['type' => 'int', 'required' => true],
                    'adresse_livraison' => ['type' => 'string', 'required' => true, 'max' => 255],
                    'statut' => ['type' => 'string', 'default' => 'BROUILLON',
                        'enum' => ['BROUILLON', 'EN_PREPARATION', 'PAYEE', 'EXPEDIEE', 'LIVREE', 'ANNULEE']],
                    'montant_total' => ['type' => 'decimal', 'default' => 0.0],
                    'frais_port' => ['type' => 'decimal', 'default' => 0.0],
                    'commentaire' => ['type' => 'string', 'max' => 500],
                    'date_commande' => ['type' => 'datetime'],
                    'date_modification' => ['type' => 'datetime'],
                ],
                'unique' => ['uk_commande_numero' => ['numero']],
                'checks' => [
                    'ck_commande_montant' => [['montant_total', '>=', 0]],
                    'ck_commande_port' => [['frais_port', '>=', 0]],    // RB-20
                ],
                'foreignKeys' => [
                    'id_client' => ['table' => 'client', 'column' => 'id_client', 'onDelete' => 'RESTRICT'],
                ],
                'indexes' => ['id_client', 'statut', 'numero'],
            ],
            'ligne_commande' => [
                'pk' => 'id_ligne', 'autoIncrement' => true,
                'columns' => [
                    'id_ligne' => ['type' => 'int', 'required' => true],
                    'id_commande' => ['type' => 'int', 'required' => true],
                    'id_produit' => ['type' => 'int', 'required' => true],
                    'quantite' => ['type' => 'int', 'required' => true],
                    'prix_unitaire' => ['type' => 'decimal', 'required' => true],
                    'total_ligne' => ['type' => 'decimal'],
                ],
                'unique' => [],
                'checks' => [
                    'ck_ligne_quantite' => [['quantite', '>', 0]],      // RB-05
                    'ck_ligne_prix' => [['prix_unitaire', '>', 0]],     // RB-06
                ],
                'foreignKeys' => [
                    'id_commande' => ['table' => 'commande', 'column' => 'id_commande', 'onDelete' => 'CASCADE'],
                    'id_produit' => ['table' => 'produit', 'column' => 'id_produit', 'onDelete' => 'RESTRICT'],
                ],
                'indexes' => ['id_commande', 'id_produit'],
            ],
            'order_status_history' => [
                'pk' => 'id', 'autoIncrement' => true,
                'columns' => [
                    'id' => ['type' => 'int', 'required' => true],
                    'order_id' => ['type' => 'int', 'required' => true],
                    'old_status' => ['type' => 'string', 'max' => 20],
                    'new_status' => ['type' => 'string', 'required' => true, 'max' => 20],
                    'changed_by' => ['type' => 'int'],
                    'changed_by_role' => ['type' => 'string', 'default' => 'SYSTEME', 'enum' => ['CLIENT', 'ADMIN', 'SYSTEME']],
                    'commentaire' => ['type' => 'string', 'max' => 500],
                    'changed_at' => ['type' => 'datetime'],
                ],
                'unique' => [],
                'checks' => [],
                'foreignKeys' => [
                    'order_id' => ['table' => 'commande', 'column' => 'id_commande', 'onDelete' => 'CASCADE'],
                ],
                'indexes' => ['order_id'],
            ],
        ];
    }

    /** @var array<string,mixed>|null cache du schéma (immuable en pratique) */
    private static ?array $tables = null;

    /** Définition d'une table ou erreur SchemaError (liste blanche). */
    public static function table(string $name): array
    {
        $tables = self::tables();
        if (!isset($tables[$name])) {
            throw new SchemaError("Table inconnue : « {$name} » (tables : " . implode(', ', array_keys($tables)) . ')');
        }

        return $tables[$name];
    }

    /** Nom de la clé primaire d'une table. */
    public static function primaryKey(string $table): string
    {
        return self::table($table)['pk'];
    }

    /** Les tables qui référencent $table par une clé étrangère (enfants). */
    public static function childrenOf(string $table): array
    {
        $children = [];
        foreach (self::tables() as $name => $def) {
            foreach ($def['foreignKeys'] as $fk) {
                if ($fk['table'] === $table) {
                    $children[] = ['table' => $name, 'column' => $fk['column'], 'onDelete' => $fk['onDelete']];
                }
            }
        }

        return $children;
    }
}
