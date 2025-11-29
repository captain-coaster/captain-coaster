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
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class FilterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache
    ) {
    }

    /** Clear the filter data cache. */
    public function clearFilterCache(): void
    {
        $this->cache->delete('filter_dropdown_data');
    }

    /**
     * Get filter dropdown data for UI.
     * Returns simple arrays with id/name for efficiency.
     * Data is cached for 1 days (86400 seconds).
     */
    public function getFilterData(): array
    {
        return $this->cache->get('filter_dropdown_data', fn () => [
            'continent' => $this->em->getRepository(Continent::class)->findForFilter(),
            'country' => $this->em->getRepository(Country::class)->findForFilter(),
            'materialType' => $this->em->getRepository(MaterialType::class)->findForFilter(),
            'seatingType' => $this->em->getRepository(SeatingType::class)->findForFilter(),
            'model' => $this->em->getRepository(Model::class)->findForFilter(),
            'manufacturer' => $this->em->getRepository(Manufacturer::class)->findForFilter(),
            'openingDate' => $this->em->getRepository(Coaster::class)->findDistinctOpeningYears(),
        ], 86400);
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

        // Get filter data for validation
        $filterData = $this->getFilterData();

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
                // Integer ID filters - validate against available options
                case 'continent':
                case 'country':
                case 'manufacturer':
                case 'materialType':
                case 'seatingType':
                case 'model':
                    if (is_numeric($value) && (int) $value > 0) {
                        $id = (int) $value;
                        // Check if ID exists in filter data
                        $validIds = array_column($filterData[$key], 'id');
                        if (\in_array($id, $validIds, true)) {
                            $sanitized[$key] = $id;
                        }
                    }
                    break;

                case 'user':
                    // User filter validated separately via checkUserFilterPermission
                    if (is_numeric($value) && (int) $value > 0) {
                        $sanitized[$key] = (int) $value;
                    }
                    break;

                    // Year filter - validate against available opening dates
                case 'openingDate':
                    if (is_numeric($value)) {
                        $year = (int) $value;
                        $validYears = array_column($filterData['openingDate'], 'year');
                        if (\in_array($year, $validYears, true)) {
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
