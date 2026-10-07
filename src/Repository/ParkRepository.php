<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Park>
 */
class ParkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Park::class);
    }

    /** @throws NonUniqueResultException */
    public function countForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(DISTINCT(p.id))')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Every park that can be placed on a map and has an operating coaster. Shared by every "near you" lookup,
     * which measures the distances itself (NearbyParks).
     *
     * @return list<array{id: int, latitude: float, longitude: float}>
     */
    public function findCoordinates(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.id', 'p.latitude', 'p.longitude')
            ->innerJoin('p.coasters', 'c')
            ->innerJoin('c.status', 's')
            ->where('p.latitude IS NOT NULL')
            ->andWhere('p.longitude IS NOT NULL')
            ->andWhere('s.code = :operating')
            ->setParameter('operating', Status::OPERATING)
            ->getQuery()
            ->enableResultCache(3600)
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        ], array_values($rows));
    }

    /**
     * Name and operating coasters of the given parks, with how many of them $user has ridden.
     *
     * @param list<int> $parkIds
     *
     * @return array<int, array{id: int, name: string, slug: string, total: int, ridden: int}> by park id
     */
    public function findProgress(array $parkIds, ?User $user): array
    {
        if ([] === $parkIds) {
            return [];
        }

        $rows = $this->progressQuery($user)
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $parkIds)
            ->getQuery()
            ->getArrayResult();

        return array_column(self::castProgress($rows), null, 'id');
    }

    /**
     * The park $user is closest to finishing: started, not complete, at least $minCoasters operating coasters.
     *
     * @return array{id: int, name: string, slug: string, total: int, ridden: int}|null
     */
    public function findClosestToCompletion(User $user, int $minCoasters = 3): ?array
    {
        $rows = $this->progressQuery($user)
            ->addSelect('(COUNT(c.id) - COUNT(r.id)) AS HIDDEN remaining')
            ->having('COUNT(r.id) > 0')
            ->andHaving('COUNT(r.id) < COUNT(c.id)')
            ->andHaving('COUNT(c.id) >= :minCoasters')
            ->setParameter('minCoasters', $minCoasters)
            ->orderBy('remaining', 'ASC')
            ->addOrderBy('total', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return self::castProgress($rows)[0] ?? null;
    }

    /** One row per park: its operating coasters and those $user has ridden (none without a user). */
    private function progressQuery(?User $user): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->select('p.id', 'p.name', 'p.slug', 'COUNT(c.id) AS total', 'COUNT(r.id) AS ridden')
            ->innerJoin('p.coasters', 'c')
            ->innerJoin('c.status', 's', Join::WITH, 's.code = :operating')
            ->leftJoin(RiddenCoaster::class, 'r', Join::WITH, 'r.coaster = c AND r.user = :user')
            ->setParameter('operating', Status::OPERATING)
            ->setParameter('user', $user)
            ->groupBy('p.id', 'p.name', 'p.slug');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<array{id: int, name: string, slug: string, total: int, ridden: int}>
     */
    private static function castProgress(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'total' => (int) $row['total'],
            'ridden' => (int) $row['ridden'],
        ], array_values($rows));
    }

    /**
     * The photo standing for each park: the main image of its best-ranked coaster that has one.
     *
     * @param list<int> $parkIds
     *
     * @return array<int, Image> by park id
     */
    public function findCovers(array $parkIds): array
    {
        if ([] === $parkIds) {
            return [];
        }

        /** @var list<Coaster> $coasters */
        $coasters = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'mi')
            ->addSelect('CASE WHEN c.rank IS NULL THEN 1 ELSE 0 END AS HIDDEN unranked')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.mainImage', 'mi')
            ->where('c.park IN (:ids)')
            ->andWhere('mi.enabled = 1')
            ->orderBy('unranked', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->addOrderBy('c.totalRatings', 'DESC')
            ->setParameter('ids', $parkIds)
            ->getQuery()
            ->getResult();

        $covers = [];
        foreach ($coasters as $coaster) {
            $parkId = $coaster->getPark()?->getId();
            $image = $coaster->getMainImage();
            if (null !== $parkId && null !== $image) {
                $covers[$parkId] ??= $image;
            }
        }

        return $covers;
    }

    /** @return array<int, array<string, mixed>> */
    public function getClosestParks(Park $park, int $minScore, int $maxDistance): array
    {
        $parkLatitude = $park->getLatitude();
        $parkLongitude = $park->getLongitude();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('distinct p.name as name, ROUND(( 6371 * acos( cos( radians(:parkLatitude) )
              * cos( radians( p.latitude ) )
              * cos( radians( p.longitude ) - radians(:parkLongitude) )
              + sin( radians(:parkLatitude) )
              * sin( radians( p.latitude ) ) ) ) ) AS distance, p.slug as slug, p.id as id')
            ->from(Park::class, 'p')
            ->join('p.coasters', 'c')
            ->where('p.latitude between :parkLatitudeMin and :parkLatitudeMax')
            ->andwhere('p.longitude between :parkLongitudeMin and :parkLongitudeMax')
            ->andwhere('c.score > :minScore')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->andWhere('s.code = :operating')
            ->setParameter('operating', Status::OPERATING)
            ->andwhere('p.id != :parkId')
            ->having('distance < :maxDistance')
            ->orderBy('distance')
            ->setParameter('parkLatitude', $parkLatitude)
            ->setParameter('parkLongitude', $parkLongitude)
            ->setParameter('parkLatitudeMin', $parkLatitude - 3)
            ->setParameter('parkLatitudeMax', $parkLatitude + 3)
            ->setParameter('parkLongitudeMin', $parkLongitude - 3)
            ->setParameter('parkLongitudeMax', $parkLongitude + 3)
            ->setParameter('minScore', $minScore)
            ->setParameter('parkId', $park->getId())
            ->setParameter('maxDistance', $maxDistance)
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimized search method for API with limited results and better performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id', 'p.name', 'p.slug', 'co.code as countryCode', 'co.name as countryName')
            ->leftJoin('p.country', 'co')
            ->where('p.name LIKE :query OR p.slug LIKE :slugQuery')
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('slugQuery', '%'.str_replace(' ', '-', $query).'%')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Id and slug of every park, for the pages sitemap.
     *
     * @return list<array{id: int, slug: string}>
     */
    public function findForSitemap(): array
    {
        /** @var list<array{id: int, slug: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.id AS id', 'p.slug AS slug')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }
}
