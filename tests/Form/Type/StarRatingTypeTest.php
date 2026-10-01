<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Form\Type\StarRatingType;
use Symfony\Component\Form\Test\TypeTestCase;

class StarRatingTypeTest extends TypeTestCase
{
    public function testSubmittedStarsMapToAFloatRating(): void
    {
        $form = $this->factory->create(StarRatingType::class);
        $form->submit('3.5');

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(3.5, $form->getData());
    }

    public function testOffersHalfStepsFromHalfToFive(): void
    {
        $view = $this->factory->create(StarRatingType::class)->createView();

        $this->assertCount(10, $view->children);
    }

    /** No star checked must fail on the field, never map null onto the non-nullable rating. */
    public function testNoStarCheckedIsAFieldError(): void
    {
        $form = $this->factory->create(StarRatingType::class);
        $form->submit(null);

        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
    }
}
