<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    // Placeholders pending real like-count distribution data -- tune both
    // once there's production numbers to look at.
    private const int FEATURED_MIN_LIKES = 15;
    private const int FEATURED_WINDOW_DAYS = 120;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /**
     * A heavily-liked, reasonably recent photo for the homepage hero --
     * not just the latest liked one, which said nothing about quality.
     * likeCounter is denormalized directly onto Image, so unlike the
     * queries below this needs no join and no result-cache-then-refilter
     * dance: filter and order all happen on the driving table.
     *
     * Picked randomly among the top matches so it isn't always the same
     * single photo.
     *
     * @throws NoResultException
     */
    public function findFeaturedImage(): Image
    {
        $since = new \DateTimeImmutable('-'.self::FEATURED_WINDOW_DAYS.' days');

        $candidateIds = $this->createQueryBuilder('i')
            ->select('i.id')
            ->where('i.enabled = 1')
            ->andWhere('i.credit IS NOT NULL')
            ->andWhere('i.likeCounter >= :minLikes')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('minLikes', self::FEATURED_MIN_LIKES)
            ->setParameter('since', $since)
            ->orderBy('i.likeCounter', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->enableResultCache(3600)
            ->getSingleColumnResult();

        if ([] === $candidateIds) {
            throw new NoResultException();
        }

        $image = $this->find($candidateIds[array_rand($candidateIds)]);

        // The candidate id list is cached for an hour; re-check enabled/credit here
        // so an image disabled by a moderator within that window can't surface.
        if (!$image instanceof Image || !$image->isEnabled() || null === $image->getCredit()) {
            throw new NoResultException();
        }

        return $image;
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

    /** @return array<Image> */
    public function findImageToBeValidated(): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.enabled = 0')
            ->andWhere('i.createdAt < :date')
            ->setParameter('date', new \DateTime('-23 hours'))
            ->getQuery()
            ->getResult();
    }
}
