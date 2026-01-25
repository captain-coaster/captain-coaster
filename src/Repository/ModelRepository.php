<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Model;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModelRepository extends ServiceEntityRepository
{
    use FilterableRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Model::class);
    }

    // Override to get Manufacturer ID
    public function findForFilter(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.name', 'manufacturer.id as manufacturerId')
            ->leftjoin('m.manufacturer', 'manufacturer')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
