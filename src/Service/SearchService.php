<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SearchService
{
    final public const COASTER = [
        'emoji' => '🎢',
        'route' => 'bdd_show_coaster',
    ];

    final public const PARK = [
        'emoji' => '🎡',
        'route' => 'park_show',
    ];

    final public const USER = [
        'emoji' => '👦',
        'route' => 'user_show',
    ];

    /** SearchService constructor. */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getAutocompleteValues(): array
    {
        $coasters = $this->em->getRepository(Coaster::class)->findAllForSearch();
        $coasters = $this->formatValues($coasters, self::COASTER);

        $parks = $this->em->getRepository(Park::class)->findAllForSearch();
        $parks = $this->formatValues($parks, self::PARK);

        $users = $this->em->getRepository(User::class)->getAllForSearch();
        $users = $this->formatValues($users, self::USER);

        return array_merge($parks, $coasters, $users);
    }

    private function formatValues($results, $options): array
    {
        return array_map(
            fn ($result) => [
                'n' => $options['emoji'].' '.$result['name'],
                'r' => $options['route'],
                's' => $result['slug'],
            ],
            $results
        );
    }
}
