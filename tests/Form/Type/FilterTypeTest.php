<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Form\Extension\FieldPresentationExtension;
use App\Form\Type\FilterType;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\TypeTestCase;

class FilterTypeTest extends TypeTestCase
{
    private const array FILTER_DATA = [
        'manufacturer' => [['id' => 7, 'name' => 'Intamin'], ['id' => 9, 'name' => 'Mack Rides']],
        'model' => [['id' => 3, 'name' => 'Blitz']],
        'materialType' => [],
        'seatingType' => [],
        'continent' => [],
        'country' => [],
        'openingDate' => [['year' => '2025'], ['year' => '2024']],
    ];

    /** @return list<FormTypeExtensionInterface<mixed>> */
    protected function getTypeExtensions(): array
    {
        return [new FieldPresentationExtension()];
    }

    /** The endpoints read filters[key], with 'on' for a checked switch. */
    public function testFieldsPostAsFiltersArrayWithOnForSwitches(): void
    {
        $view = $this->view(['status' => 'on', 'manufacturer' => 9, 'openingDate' => 2024, 'name' => 'ta']);

        $this->assertSame('filters[status]', $view['status']->vars['full_name']);
        $this->assertSame('on', $view['status']->vars['value']);
        $this->assertTrue($view['status']->vars['checked']);
        $this->assertFalse($view['kiddie']->vars['checked']);
        $this->assertSame('9', $view['manufacturer']->vars['value']);
        $this->assertSame('2024', $view['openingDate']->vars['value']);
        $this->assertSame('ta', $view['name']->vars['value']);
    }

    public function testExcludedFiltersAreNotRendered(): void
    {
        $view = $this->view([], ['score', 'kiddie', 'sortByDistance']);

        $this->assertArrayNotHasKey('score', $view->children);
        $this->assertArrayNotHasKey('kiddie', $view->children);
        $this->assertArrayNotHasKey('sortByDistance', $view->children);
        $this->assertArrayNotHasKey('latitude', $view->children);
        $this->assertArrayHasKey('status', $view->children);
    }

    public function testFieldsAreCompactAndNotMarkedOptional(): void
    {
        $view = $this->view([]);

        $this->assertSame('compact', $view['manufacturer']->vars['control_size']);
        $this->assertFalse($view['manufacturer']->vars['mark_optional']);
        $this->assertFalse($view['manufacturer']->vars['required']);
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<string>         $excluded
     */
    private function view(array $filters, array $excluded = []): FormView
    {
        return $this->factory
            ->createNamed('filters', FilterType::class, $filters, ['excluded' => $excluded, 'filter_data' => self::FILTER_DATA])
            ->createView();
    }
}
