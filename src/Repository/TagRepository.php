<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Tags of one type (Tag::PRO or Tag::CON), most used in reviews first.
     *
     * @return list<Tag>
     */
    public function findByTypeMostUsedFirst(string $type): array
    {
        $collection = Tag::PRO === $type ? 'pros' : 'cons';

        return $this->createQueryBuilder('t')
            ->addSelect(\sprintf('(SELECT COUNT(rc.id) FROM App\Entity\RiddenCoaster rc WHERE t MEMBER OF rc.%s) AS HIDDEN uses', $collection))
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('uses', 'DESC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->enableResultCache(86400, 'tags_most_used_'.$type)
            ->getResult();
    }
}
