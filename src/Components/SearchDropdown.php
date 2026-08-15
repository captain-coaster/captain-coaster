<?php

declare(strict_types=1);

namespace App\Components;

use App\DTO\SearchResponseDTO;
use App\Service\SearchService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'SearchDropdown', template: 'components/SearchDropdown.html.twig')]
class SearchDropdown
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public string $variant = 'desktop';

    #[LiveProp]
    public string $locale = 'en';

    public function __construct(
        private readonly SearchService $searchService,
        private readonly RouterInterface $router,
    ) {
    }

    public function getResults(): ?SearchResponseDTO
    {
        if (mb_strlen($this->query) < 2) {
            return null;
        }

        return $this->searchService->searchAll($this->query, 5);
    }

    public function getResultsUrl(): string
    {
        return $this->router->generate('search_index', ['_locale' => $this->locale, 'query' => $this->query]);
    }
}
