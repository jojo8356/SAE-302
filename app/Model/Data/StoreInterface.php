<?php

declare(strict_types=1);

/**
 * MiniShop — contrat commun des pilotes de gestion des données.
 *
 * Ce contrat est l'unique frontière entre le modèle (Repository) et le
 * stockage. Il est écrit pour être implémentable AU CHOIX par des opérations
 * JSON natives (JsonStore, pilote actif) ou par un SGBD relationnel
 * (SqlStore, migration finale) : chaque méthode correspond à une opération
 * relationnelle standard, mais AUCUNE méthode ne reçoit ni ne produit du
 * SQL — les filtres sont déclaratifs (tableaux associatifs sérialisables).
 *
 * ---------------------------------------------------------------------------
 * Spécification déclarative des filtres (« where »)
 * ---------------------------------------------------------------------------
 * Une condition est un tableau numérique :  [colonne, opérateur, valeur]
 *   opérateurs : =  !=  <>  <  <=  >  >=  like  not-like  in  not-in
 *                between  null  not-null
 *   - « like » est insensible à la casse ET aux accents (comme la collation
 *     utf8mb4_unicode_ci du schéma MySQL cible), jokers % et _.
 *   - « in » / « not-in » : valeur = tableau de valeurs.
 *   - « between » : valeur = [min, max] (bornes incluses).
 *   - « null » / « not-null » : pas de valeur.
 *
 * Un « where » est une liste de conditions combinées par AND implicite :
 *   [['visible', '=', 1], ['prix_ttc', 'between', [20, 100]]]
 * Des groupes explicites sont possibles :
 *   [['or', [[['nom','like','%clavier%'], [['reference','like','%CLAV%']]]]]
 *
 * Traduction future : cette spécification se convertit mécaniquement en
 * clause SQL (mêmes opérateurs, mêmes jokers) — c'est le but du pilote SQL.
 *
 * ---------------------------------------------------------------------------
 * Spécification des tris (« order »)
 * ---------------------------------------------------------------------------
 *   [['colonne', 'asc'|'desc'], ...]   — multi-clés, stable.
 *
 * ---------------------------------------------------------------------------
 * Cycle de vie et intégrité
 * ---------------------------------------------------------------------------
 * insert()/update()/delete() appliquent : valeurs par défaut, coercition de
 * type, NOT NULL, ENUM, UNIQUE, CHECK (déclarés dans Schema) puis les
 * déclencheurs métier (Triggers) — l'équivalent exact des 16 triggers SQL.
 * Les écritures multi-tables passent par beginTransaction()/commit() avec
 * rollback automatique si une exception est levée (ENF-16).
 */

namespace App\Model\Data;

/**
 * @phpstan-type Condition list{string, string, mixed}|list{string, list<mixed>}
 * @phpstan-type Where list<Condition>
 * @phpstan-type Order list<list{string, string}>
 * @phpstan-type Spec array{where?: Where, order?: Order, limit?: int, offset?: int, fields?: list<string>}
 */
interface StoreInterface
{
    // ----------------------------------------------------------- lecture

    /**
     * Sélectionne des lignes d'une table.
     *
     * @param string $table Nom de table (ex. 'produit') — liste blanche Schema.
     * @param array{where?: array, order?: array, limit?: int, offset?: int, fields?: array} $spec
     * @return list<array<string,mixed>> lignes (tableaux associatifs colonne => valeur)
     */
    public function select(string $table, array $spec = []): array;

    /** Première ligne correspondante, ou null. */
    public function findOne(string $table, array $where = []): ?array;

    /** Ligne par clé primaire, ou null. */
    public function find(string $table, int|string $pk): ?array;

    /** Nombre de lignes correspondantes. */
    public function count(string $table, array $where = []): int;

    /** Somme d'une colonne (0 si aucune ligne). */
    public function sum(string $table, string $column, array $where = []): float;

    /** Teste l'existence d'au moins une ligne correspondante. */
    public function exists(string $table, array $where = []): bool;

    /**
     * Toutes les lignes d'une table, indexées par clé primaire (lecture seule,
     * utile aux jointures hash du pilote JSON). Ne pas muter le retour.
     *
     * @return array<int|string, array<string,mixed>>
     */
    public function all(string $table): array;

    // ---------------------------------------------------------- écriture

    /**
     * Insère une ligne ; retourne la clé primaire auto-générée.
     *
     * @param array<string,mixed> $row
     */
    public function insert(string $table, array $row): int;

    /**
     * Met à jour les lignes correspondantes ; retourne le nombre de lignes
     * réellement modifiées (les valeurs identiques ne comptent pas).
     *
     * @param array<string,mixed> $changes
     */
    public function update(string $table, array $where, array $changes): int;

    /** Supprime les lignes correspondantes ; retourne le nombre supprimé. */
    public function delete(string $table, array $where): int;

    /**
     * Prochaine valeur de la clé primaire auto-incrémentée SANS consommer
     * le compteur (usage : numéro lisible CMD2026-000123 avant insertion).
     */
    public function nextId(string $table): int;

    // -------------------------------------------------- transactions / io

    /** Ouvre une transaction (imbrication interdite : une seule profondeur). */
    public function beginTransaction(): void;

    /** Valide et écrit sur disque les tables modifiées. */
    public function commit(): void;

    /** Annule la transaction et restaure l'état du dernier commit. */
    public function rollback(): void;

    /** Vrai si une transaction est ouverte. */
    public function inTransaction(): bool;

    /**
     * Acteur courant (traçabilité des déclencheurs : qui écrit ?).
     *
     * @param array{id?: int|null, role?: 'CLIENT'|'ADMIN'|'SYSTEME', nom?: string|null} $actor
     */
    public function setActor(array $actor): void;

    /** @return array{id?: int|null, role?: string, nom?: string|null} */
    public function actor(): array;
}
