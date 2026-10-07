<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\VocabularyLabeler;

/**
 * A member's figures on their profile.
 */
class MemberFigures
{
    /** The main Top's first positions that name the favorite manufacturer. */
    private const int FAVORITE_POSITIONS = 10;

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ParkRepository $parkRepository,
        private readonly CountryRepository $countryRepository,
        private readonly VocabularyLabeler $labeler,
    ) {
    }

    /**
     * Empty for a member who hasn't ridden anything.
     *
     * @return array{}|array{
     *     nb_coasters: int,
     *     nb_park: int,
     *     nb_country: int,
     *     country: array{name: string, nb: int},
     *     top_100: int,
     *     top_100_operating: int,
     *     manufacturer: array{name: string, nb: int},
     *     top_rated_manufacturer: array{name: string, nb: int},
     * }
     */
    public function get(User $user): array
    {
        $ridden = $this->riddenCoasterRepository->countForUser($user);
        if (0 === $ridden) {
            return [];
        }

        $country = $this->riddenCoasterRepository->findMostRiddenCountry($user);
        $top100 = $this->riddenCoasterRepository->countTop100ForUser($user);

        return [
            'nb_coasters' => $ridden,
            'nb_park' => $this->parkRepository->countForUser($user),
            'nb_country' => $this->countryRepository->countForUser($user),
            'country' => ['name' => $this->labeler->country($country['code'], $country['name']), 'nb' => $country['nb']],
            'top_100' => $top100['nb_top100'],
            'top_100_operating' => $top100['nb_top100_operating'],
            'manufacturer' => $this->riddenCoasterRepository->getMostRiddenManufacturer($user),
            'top_rated_manufacturer' => $this->riddenCoasterRepository->getTopListManufacturer($user, self::FAVORITE_POSITIONS),
        ];
    }
}
