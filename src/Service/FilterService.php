<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\SeatingType;
use App\Repository\CoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class FilterService
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Get filter dropdown data with caching
     */
    public function getFilterData(): array
    {
        return $this->cache->get('filter_dropdown_data', function () {
            $data = [];
            $data['continent'] = $this->em->getRepository(Continent::class)->findBy([], ['name' => 'asc']);
            $data['country'] = $this->em->getRepository(Country::class)->findBy([], ['name' => 'asc']);
            $data['materialType'] = $this->em->getRepository(MaterialType::class)->findBy([], ['name' => 'asc']);
            $data['seatingType'] = $this->em->getRepository(SeatingType::class)->findBy([], ['name' => 'asc']);
            $data['model'] = $this->em->getRepository(Model::class)->findBy([], ['name' => 'asc']);
            $data['manufacturer'] = $this->em->getRepository(Manufacturer::class)->findBy([], ['name' => 'asc']);
            $data['openingDate'] = $this->em->getRepository(Coaster::class)->getDistinctOpeningYears();
            
            return $data;
        }, 604800); // 7 days TTL
    }

    /**
     * Get filtered coasters for ranking page
     */
    public function getFilteredRanking(array $filters = [])
    {
        return $this->coasterRepository->getFilteredCoasters($filters, 'ranking');
    }

    /**
     * Get filtered coasters for nearby page
     */
    public function getFilteredNearby(array $filters = [])
    {
        return $this->coasterRepository->getFilteredCoasters($filters, 'nearby');
    }

    /**
     * Get filtered markers for map page
     */
    public function getFilteredMarkers(array $filters = []): array
    {
        return $this->coasterRepository->getFilteredCoasters($filters, 'markers');
    }


}