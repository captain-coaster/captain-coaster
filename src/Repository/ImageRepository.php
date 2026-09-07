<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\LikedImage;
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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /** @throws NoResultException */
    public function findLatestLikedImage(): Image
    {
        // Two-step, same reasoning as RiddenCoasterRepository::getLatestRatings():
        // ORDER BY + LIMIT on the joined `li` table combined with a WHERE on
        // the driving `image` table is the same MariaDB optimizer pathology
        // in mirror image -- get the most-recently-liked candidate ids off
        // liked_image alone first (cheap, no join), then filter/hydrate that
        // small set. Ranking is redone in PHP instead of an SQL `ORDER BY
        // FIELD(...)` -- DQL has no equivalent function registered here.
        $candidateIds = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(li.image)')
            ->from(LikedImage::class, 'li')
            ->orderBy('li.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->enableResultCache(600)
            ->getSingleColumnResult();

        if ([] === $candidateIds) {
            throw new NoResultException();
        }

        $query = $this->createQueryBuilder('i')
            ->addSelect('c', 'st', 'mi')
            ->innerJoin('i.coaster', 'c')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.mainImage', 'mi')
            ->where('i.id IN (:ids)')
            ->andWhere('i.enabled = 1')
            ->andWhere('i.credit IS NOT NULL')
            ->setParameter('ids', $candidateIds)
            ->getQuery();

        $query->enableResultCache(600);

        /** @var array<int, Image> $matchesById */
        $matchesById = [];
        foreach ($query->getResult() as $match) {
            $matchesById[$match->getId()] = $match;
        }

        foreach ($candidateIds as $id) {
            if (isset($matchesById[$id])) {
                return $matchesById[$id];
            }
        }

        throw new NoResultException();
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
