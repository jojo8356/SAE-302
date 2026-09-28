<?php

declare(strict_types=1);

/**
 * MiniShop — constructeur déclaratif de conditions (helper de StoreInterface).
 *
 * Les Repository décrivent leurs filtres avec ces helpers plutôt qu'en
 * écrivant des tableaux à la main : l'intention reste lisible ET la
 * spécification reste sérialisable (transposable telle quelle en clause
 * SQL préparée le jour du basculement StorageDriver::SQL).
 *
 * Exemple :
 *   $store->select('produit', [
 *       'where' => [
 *           Filter::eq('visible', 1),
 *           Filter::like('nom', '%clavier%'),
 *           Filter::between('prix_ttc', 20, 100),
 *       ],
 *       'order' => [Filter::sort('prix_ttc', 'desc')],
 *       'limit' => 12,
 *   ]);
 */

namespace App\Model\Data;

final class Filter
{
    /** @return list{string,string,mixed} */
    public static function eq(string $col, mixed $value): array
    {
        return [$col, '=', $value];
    }

    /** @return list{string,string,mixed} */
    public static function neq(string $col, mixed $value): array
    {
        return [$col, '!=', $value];
    }

    /** @return list{string,string,mixed} */
    public static function lt(string $col, mixed $value): array
    {
        return [$col, '<', $value];
    }

    /** @return list{string,string,mixed} */
    public static function lte(string $col, mixed $value): array
    {
        return [$col, '<=', $value];
    }

    /** @return list{string,string,mixed} */
    public static function gt(string $col, mixed $value): array
    {
        return [$col, '>', $value];
    }

    /** @return list{string,string,mixed} */
    public static function gte(string $col, mixed $value): array
    {
        return [$col, '>=', $value];
    }

    /** Recherche insensible à la casse et aux accents, jokers % et _. */
    public static function like(string $col, string $pattern): array
    {
        return [$col, 'like', $pattern];
    }

    public static function notLike(string $col, string $pattern): array
    {
        return [$col, 'not-like', $pattern];
    }

    /** @param list<mixed> $values */
    public static function in(string $col, array $values): array
    {
        return [$col, 'in', $values];
    }

    /** @param list<mixed> $values */
    public static function notIn(string $col, array $values): array
    {
        return [$col, 'not-in', $values];
    }

    /** @return list{string,string,list{mixed,mixed}} */
    public static function between(string $col, mixed $min, mixed $max): array
    {
        return [$col, 'between', [$min, $max]];
    }

    /** @return list{string,string,null} */
    public static function isNull(string $col): array
    {
        return [$col, 'null', null];
    }

    /** @return list{string,string,null} */
    public static function isNotNull(string $col): array
    {
        return [$col, 'not-null', null];
    }

    /** Groupe OU : au moins une des conditions internes doit être vraie. */
    public static function or(array $conditions): array
    {
        return ['or', $conditions];
    }

    /** Groupe ET explicite. */
    public static function and(array $conditions): array
    {
        return ['and', $conditions];
    }

    /** @return list{string,string} */
    public static function sort(string $col, string $direction = 'asc'): array
    {
        $sens = 'asc';
        if (strtolower($direction) === 'desc') {
            $sens = 'desc';
        }

        return [$col, $sens];
    }
}
