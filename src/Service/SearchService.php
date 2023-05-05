<?php

namespace App\Service;

use App\Entity\Coaster;
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

    final public const USER =[
        'emoji' => '👦',
        'route' => 'user_show'
    ];

    /**
     * SearchService constructor.
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getAutocompleteValues(): array
    {
        $coasters = $this->em->getRepository(Coaster::class)->findAllForSearch();
        $coasters = $this->formatValues($coasters, self::COASTER);

        $parks = $this->em->getRepository('App:Park')->findAllForSearch();
        $parks = $this->formatValues($parks, self::PARK);

        $users = $this->em->getRepository('App:User')->getAllForSearch();
        $users = $this->formatValues($users, self::USER);

        return array_merge($parks, $coasters, $users);
    }

    /**
     * @param $results
     * @param $options
     * @return array
     */
    private function formatValues($results, $options)
    {
        return array_map(
            fn($result) => [
                'n' => $options['emoji'].' '.$result['name'],
                'r' => $options['route'],
                's' => $result['slug'],
            ],
            $results
        );
    }
}
