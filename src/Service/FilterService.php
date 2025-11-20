<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\SeatingType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class FilterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Get filter dropdown data for UI.
     * Data is cached for 7 days (604800 seconds).
     */
    public function getFilterData(): array
    {
        return $this->cache->get('filter_dropdown_data', function () {
            $rsm = new ResultSetMapping();
            $rsm->addScalarResult('year', 'year');
            $openingYears = $this->em
                ->createNativeQuery('SELECT DISTINCT YEAR(c.openingDate) as year from coaster c ORDER by year DESC', $rsm)
                ->getScalarResult();

            return [
                'continent' => $this->em->getRepository(Continent::class)->findBy([], ['name' => 'asc']),
                'country' => $this->em->getRepository(Country::class)->findBy([], ['name' => 'asc']),
                'materialType' => $this->em->getRepository(MaterialType::class)->findBy([], ['name' => 'asc']),
                'seatingType' => $this->em->getRepository(SeatingType::class)->findBy([], ['name' => 'asc']),
                'model' => $this->em->getRepository(Model::class)->findBy([], ['name' => 'asc']),
                'manufacturer' => $this->em->getRepository(Manufacturer::class)->findBy([], ['name' => 'asc']),
                'openingDate' => $openingYears,
            ];
        }, 604800);
    }

    /**
     * Validates and sanitizes filter inputs.
     *
     * @param array<string, mixed> $filters Raw filter array from request
     * @param string               $context Context where filters are applied (ranking, search, map)
     *
     * @return array<string, mixed> Sanitized filter array with only valid filters
     */
    public function validateFilters(array $filters, string $context = 'search'): array
    {
        $sanitized = [];
        $currentYear = (int) date('Y');

        // Define supported filters
        $supportedFilters = [
            'continent', 'country', 'manufacturer', 'materialType', 'seatingType',
            'model', 'openingDate', 'status', 'kiddie', 'user', 'ridden',
            'notridden', 'name', 'score',
        ];

        // Add 'new' filter only for ranking context
        if ('ranking' === $context) {
            $supportedFilters[] = 'new';
        }

        foreach ($filters as $key => $value) {
            // Silently ignore unsupported filter keys
            if (!\in_array($key, $supportedFilters, true)) {
                continue;
            }

            // Skip empty values (null, empty string, empty array)
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            // Validate and sanitize based on filter type
            switch ($key) {
                // Integer ID filters
                case 'continent':
                case 'country':
                case 'manufacturer':
                case 'materialType':
                case 'seatingType':
                case 'model':
                case 'user':
                    // Validate integer type
                    if (is_numeric($value) && (int) $value > 0) {
                        $sanitized[$key] = (int) $value;
                    }
                    break;

                    // Year filter (1900 to current year)
                case 'openingDate':
                    if (is_numeric($value)) {
                        $year = (int) $value;
                        if ($year >= 1900 && $year <= $currentYear) {
                            $sanitized[$key] = $year;
                        }
                    }
                    break;

                    // Score filter (0-100)
                case 'score':
                    if (is_numeric($value)) {
                        $score = (int) $value;
                        if ($score >= 0 && $score <= 100) {
                            $sanitized[$key] = $score;
                        }
                    }
                    break;

                    // String filter
                case 'name':
                    // Sanitize string, max 100 characters
                    $name = trim((string) $value);
                    if ('' !== $name && \strlen($name) <= 100) {
                        $sanitized[$key] = $name;
                    }
                    break;

                    // Boolean-like filters (checkbox values)
                case 'status':
                case 'kiddie':
                case 'new':
                case 'ridden':
                case 'notridden':
                    // Only accept 'on' value (checkbox checked)
                    if ('on' === $value || true === $value || '1' === $value || 1 === $value) {
                        $sanitized[$key] = 'on';
                    }
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Checks if the current user has permission to filter by the specified user ID.
     * Permission is granted if:
     * - The current user is the same as the filter user
     * - The filter user's profile is public (enabled).
     *
     * @param int       $filterUserId The user ID from the filter
     * @param User|null $currentUser  The currently authenticated user
     *
     * @throws AccessDeniedHttpException If permission check fails
     */
    public function checkUserFilterPermission(int $filterUserId, ?User $currentUser): void
    {
        // If current user matches filter user, allow
        if ($currentUser && $currentUser->getId() === $filterUserId) {
            return;
        }

        // Check if the filter user exists and has a public profile
        $filterUser = $this->em->getRepository(User::class)->find($filterUserId);

        // If user doesn't exist or profile is not public (not enabled), deny access
        if (!$filterUser || !$filterUser->isEnabled()) {
            throw new AccessDeniedHttpException('You do not have permission to filter by this user.');
        }
    }

    /**
     * Validates filters and checks permissions in one call.
     *
     * @param array<string, mixed> $filters     Raw filter array from request
     * @param string               $context     Context where filters are applied
     * @param User|null            $currentUser The currently authenticated user
     *
     * @return array<string, mixed> Sanitized and authorized filter array
     *
     * @throws AccessDeniedHttpException If user filter permission check fails
     */
    public function validateAndAuthorize(array $filters, string $context, ?User $currentUser): array
    {
        // First validate and sanitize
        $sanitized = $this->validateFilters($filters, $context);

        // Then check user filter permissions if applicable
        if (!empty($sanitized['user'])) {
            $this->checkUserFilterPermission($sanitized['user'], $currentUser);
        }

        return $sanitized;
    }
}
