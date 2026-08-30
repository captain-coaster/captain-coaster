<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Status;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Status>
 */
final class StatusFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Status::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => Status::OPERATING,
            'type' => Status::OPERATING,
            'isRateable' => true,
            'order' => 1,
        ];
    }
}
