<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    private const int FEATURED_MIN_LIKES = 15;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /**
     * Ids of the most-liked photos, for the homepage hero (see HeroService) -- no age limit,
     * the point is the best photos. Uncached -- HeroService caches the resolved pick.
     *
     * @return array<int>
     */
    public function findFeaturedImageIds(): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.id')
            ->where('i.enabled = 1')
            ->andWhere('i.credit IS NOT NULL')
            ->andWhere('i.likeCounter >= :minLikes')
            ->setParameter('minLikes', self::FEATURED_MIN_LIKES)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Fetch-joins coaster: User/images.html.twig reads image.coaster.name for
     * every image, and it's a plain LAZY ManyToOne -- without this, up to 30
     * extra queries per page (one per image).
     *
     * @return Query<mixed, mixed>
     */
    public function findUserImages(User $user): Query
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i', 'c')
            ->from(Image::class, 'i')
            ->innerJoin('i.coaster', 'c')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->andWhere('i.uploader = :uploader')
            ->setParameter('uploader', $user->getId())
            ->getQuery();
    }

    public function countUserEnabledImages(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->andWhere('i.uploader = :uploader')
            ->setParameter('uploader', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find the top N visible images for a coaster, same ordering/filter as
     * Coaster::getImages() (enabled, likeCounter desc, updatedAt desc).
     *
     * Coaster::getImages() applies that via ->matching(Criteria), which
     * Doctrine backs with a LazyCriteriaCollection for EXTRA_LAZY
     * associations -- efficient for count()/contains(), but its slice()
     * isn't optimized at all: it always initializes (loads every image)
     * before slicing in PHP. The coaster page only ever needs the first
     * $limit, so query for exactly that instead.
     *
     * @return array<Image>
     */
    public function findVisibleForCoaster(Coaster $coaster, int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.enabled = 1')
            ->andWhere('i.coaster = :coaster')
            ->setParameter('coaster', $coaster)
            ->orderBy('i.likeCounter', 'DESC')
            ->addOrderBy('i.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Images never analyzed by GenAI moderation -- the backfill/reprocess command's default target.
     *
     * @return array<Image>
     */
    public function findUnanalyzed(int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.analyzedAt IS NULL')
            ->orderBy('i.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
