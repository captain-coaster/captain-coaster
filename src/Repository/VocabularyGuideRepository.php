<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VocabularyGuide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VocabularyGuideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VocabularyGuide::class);
    }

    public function findByLanguage(string $language): ?VocabularyGuide
    {
        return $this->findOneBy(['language' => $language]);
    }
}
