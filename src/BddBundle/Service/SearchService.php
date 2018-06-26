<?php

namespace BddBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

class SearchService
{
    const COASTER = [
        'emoji' => '🎢',
        'route' => 'bdd_show_coaster',
    ];

    const PARK = [
        'emoji' => '🎡',
        'route' => 'park_show',
    ];

    const USER =[
        'emoji' => '👦',
        'route' => 'user_show'
    ];

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * SearchService constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @return array
     */
    public function getAutocompleteValues(): array
    {
        $coasters = $this->em->getRepository('BddBundle:Coaster')->findAllForSearch();
        $coasters = $this->formatValues($coasters, self::COASTER);

        $parks = $this->em->getRepository('BddBundle:Park')->findAllForSearch();
        $parks = $this->formatValues($parks, self::PARK);

        $users = $this->em->getRepository('BddBundle:User')->getAllForSearch();
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
            function ($result) use ($options) {
                return [
                    'n' => $options['emoji'].' '.$result['name'],
                    'r' => $options['route'],
                    's' => $result['slug'],
                ];
            },
            $results
        );
    }
}
