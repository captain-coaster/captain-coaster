<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The AJAX-paginated pages (ranking, coaster search) render this template from
 * an XHR-only route, so hrefs built from knp's own `route` 404 on a real
 * navigation -- opening a page link in a new tab, or a crawler following it.
 * They must target `publicRoute` instead.
 */
class PaginationTemplateTest extends TestCase
{
    private const TEMPLATE = 'pagination/knp_custom_pagination.html.twig';

    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new FilesystemLoader(__DIR__.'/../../templates'));
        // Stand in for the real url generator: emit the route name so the
        // assertions below can tell which route each href was built from.
        // `_fragment` mirrors Symfony's UrlGenerator, which pulls it out of
        // the params and appends it as a URL fragment rather than a query key.
        $this->twig->addFunction(new TwigFunction(
            'path',
            static function (string $name, array $params = []): string {
                $fragment = $params['_fragment'] ?? null;
                unset($params['_fragment']);

                return $name.'?'.http_build_query($params).($fragment ? '#'.$fragment : '');
            }
        ));
    }

    public function testLinksUsePublicRouteWhenProvided(): void
    {
        $html = $this->render(['publicRoute' => 'coaster_search_index']);

        $this->assertStringContainsString('href="coaster_search_index?page=2"', $html);
        $this->assertStringNotContainsString('coaster_search_api', $html);
    }

    public function testLinksFallBackToPaginatedRouteWhenNoPublicRouteGiven(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('href="top_list?page=2"', $html);
    }

    /** Previous/next carry an href too, so they 404 the same way if missed. */
    public function testPreviousAndNextLinksUsePublicRoute(): void
    {
        $html = $this->render([
            'publicRoute' => 'ranking_index',
            'current' => 3,
            'previous' => 2,
            'next' => 4,
        ]);

        $this->assertStringContainsString('rel="prev" data-page="2"', $html);
        $this->assertStringContainsString('href="ranking_index?page=2"', $html);
        $this->assertStringContainsString('rel="next" data-page="4"', $html);
        $this->assertStringContainsString('href="ranking_index?page=4"', $html);
        $this->assertStringNotContainsString('ranking_search_async', $html);
    }

    /** The first/last shortcuts outside pagesInRange are separate href branches. */
    public function testFirstAndLastPageShortcutsUsePublicRoute(): void
    {
        $html = $this->render([
            'publicRoute' => 'ranking_index',
            'current' => 50,
            'pagesInRange' => [49, 50, 51],
            'startPage' => 49,
            'endPage' => 51,
            'pageCount' => 100,
        ]);

        $this->assertStringContainsString('href="ranking_index?page=1"', $html);
        $this->assertStringContainsString('href="ranking_index?page=100"', $html);
        $this->assertStringNotContainsString('ranking_search_async', $html);
    }

    public function testQueryParametersSurviveOnPublicRoute(): void
    {
        $html = $this->render([
            'publicRoute' => 'coaster_search_index',
            'query' => ['_locale' => 'fr', 'filters' => ['manufacturer' => '7']],
        ]);

        $this->assertStringContainsString(
            'href="coaster_search_index?_locale=fr&amp;filters%5Bmanufacturer%5D=7&amp;page=2"',
            $html
        );
    }

    /**
     * The coaster reviews panel (Coaster/_review_panel.html.twig) merges extra
     * route params (id, _fragment) into `query` alongside publicRoute -- the
     * same knp_pagination_render() call KnpPaginator's Processor builds these
     * from, so this covers that call shape rather than the simpler ranking/
     * search one above.
     */
    public function testReviewPanelStyleCallKeepsExtraRouteParams(): void
    {
        $html = $this->render([
            'publicRoute' => 'show_coaster',
            'query' => ['slug' => 'goudurix-parc-asterix', 'id' => 1, '_fragment' => 'coaster-reviews'],
        ]);

        $this->assertStringContainsString(
            'href="show_coaster?slug=goudurix-parc-asterix&amp;id=1&amp;page=2#coaster-reviews"',
            $html
        );
        $this->assertStringNotContainsString('coaster_reviews_ajax_load', $html);
    }

    /** @param array<string, mixed> $overrides */
    private function render(array $overrides = []): string
    {
        return $this->twig->render(self::TEMPLATE, [
            ...[
                'pageCount' => 4,
                'route' => 'top_list',
                'query' => [],
                'pageParameterName' => 'page',
                'current' => 1,
                'pagesInRange' => [1, 2, 3, 4],
                'startPage' => 1,
                'endPage' => 4,
            ],
            ...$overrides,
        ]);
    }
}
