<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\SeatingType;
use App\Service\FilterService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: Continent::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: Continent::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: Continent::class)]
#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: Country::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: Country::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: Country::class)]
#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: Manufacturer::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: Manufacturer::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: Manufacturer::class)]
#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: MaterialType::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: MaterialType::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: MaterialType::class)]
#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: Model::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: Model::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: Model::class)]
#[AsEntityListener(event: Events::postPersist, method: 'invalidateFilterCache', entity: SeatingType::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateFilterCache', entity: SeatingType::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateFilterCache', entity: SeatingType::class)]
class FilterVocabularyListener
{
    public function __construct(
        private readonly FilterService $filterService
    ) {
    }

    public function invalidateFilterCache(): void
    {
        $this->filterService->clearFilterCache();
    }
}
