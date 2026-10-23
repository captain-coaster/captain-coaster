<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\ReviewUpvote;
use App\Entity\RiddenCoaster;
use App\Entity\Top;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Hides what a disabled member (banned, or awaiting deletion) wrote, from every Doctrine query: their content is
 * kept as evidence but shown nowhere. Off in the admin (DisableEnabledUserFilterSubscriber). Plain SQL bypasses it
 * and filters on users.enabled itself.
 */
final class EnabledUserFilter extends SQLFilter
{
    public const string NAME = 'enabled_user';

    /** Entity => its association to the member who owns it */
    private const array OWNER = [
        RiddenCoaster::class => 'user',
        Top::class => 'user',
        Image::class => 'uploader',
        LikedImage::class => 'user',
        ReviewUpvote::class => 'user',
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $field = self::OWNER[$targetEntity->getName()] ?? null;
        if (null === $field) {
            return '';
        }

        // Materialized once per query: a lookup in the few disabled ids, not a subquery per row
        return \sprintf(
            '%s.%s NOT IN (SELECT id FROM users WHERE enabled = 0)',
            $targetTableAlias,
            $targetEntity->getSingleAssociationJoinColumnName($field),
        );
    }
}
