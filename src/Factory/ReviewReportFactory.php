<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\ReviewReport;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ReviewReport>
 */
final class ReviewReportFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ReviewReport::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::random(),
            'reason' => self::faker()->randomElement(ReviewReport::REASONS),
        ];
    }
}
