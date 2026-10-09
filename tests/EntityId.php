<?php

declare(strict_types=1);

namespace App\Tests;

/** Gives an entity the id the database would have assigned. */
final class EntityId
{
    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    public static function set(object $entity, int $id): object
    {
        new \ReflectionProperty($entity, 'id')->setValue($entity, $id);

        return $entity;
    }
}
