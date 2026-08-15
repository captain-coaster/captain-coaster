<?php

declare(strict_types=1);

namespace App\Tests\Components;

use App\Components\SearchDropdown;
use App\DTO\SearchResponseDTO;
use App\Service\SearchService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class SearchDropdownTest extends TestCase
{
    public function testGetResultsReturnsNullWhenQueryTooShort(): void
    {
        $service = $this->createMock(SearchService::class);
        $service->expects($this->never())->method('searchAll');

        $component = new SearchDropdown($service, $this->createMock(RouterInterface::class));

        $component->query = '';
        $this->assertNull($component->getResults());

        $component->query = 'a';
        $this->assertNull($component->getResults());
    }

    public function testGetResultsCallsServiceForQueryOfTwoOrMoreChars(): void
    {
        $expected = new SearchResponseDTO('ab', ['coasters' => [], 'parks' => [], 'users' => []], [], false);

        $service = $this->createMock(SearchService::class);
        $service->expects($this->once())
            ->method('searchAll')
            ->with('ab', 5)
            ->willReturn($expected);

        $component = new SearchDropdown($service, $this->createMock(RouterInterface::class));
        $component->query = 'ab';

        $this->assertSame($expected, $component->getResults());
    }

    public function testGetResultsUrlGeneratesSearchIndexRoute(): void
    {
        $service = $this->createMock(SearchService::class);
        $router  = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('search_index', ['_locale' => 'en', 'query' => 'test'])
            ->willReturn('/en/search/?query=test');

        $component = new SearchDropdown($service, $router);
        $component->query = 'test';
        $component->locale = 'en';

        $this->assertSame('/en/search/?query=test', $component->getResultsUrl());
    }

    public function testGetResultsUrlUsesComponentLocale(): void
    {
        $service = $this->createMock(SearchService::class);
        $router  = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('search_index', ['_locale' => 'fr', 'query' => 'test'])
            ->willReturn('/fr/search/?query=test');

        $component = new SearchDropdown($service, $router);
        $component->query = 'test';
        $component->locale = 'fr';

        $this->assertSame('/fr/search/?query=test', $component->getResultsUrl());
    }
}
