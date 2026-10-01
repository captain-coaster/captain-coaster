<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\TagRepository;

class ReviewTypeTest extends TestCase
{
    /**
     * Regression test for the whole-branch review finding: the review
     * submission form (the entry point where NoBlockedWords is actually
     * meant to be enforced) must validate the "review_text" group in
     * addition to "Default", otherwise scoping the constraint to a
     * dedicated group (to unbreak RatingCoasterController/EasyAdmin) would
     * have silently stopped the form itself from rejecting blocked words.
     */
    public function testConfigureOptionsEnablesReviewTextValidationGroup(): void
    {
        $tagRepository = $this->createStub(TagRepository::class);
        $reviewType = new ReviewType($tagRepository);

        $resolver = new OptionsResolver();
        $reviewType->configureOptions($resolver);
        $options = $resolver->resolve([]);

        $this->assertSame(RiddenCoaster::class, $options['data_class']);
        $this->assertSame(['Default', 'review_text'], $options['validation_groups']);
    }

    public function testLanguageFieldIsNotBuiltIntoTheForm(): void
    {
        $tagRepository = $this->createStub(TagRepository::class);
        $reviewType = new ReviewType($tagRepository);

        $builder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $addedFieldNames = [];
        $builder->method('add')
            ->willReturnCallback(function (string $name) use (&$addedFieldNames, $builder) {
                $addedFieldNames[] = $name;

                return $builder;
            });

        $reviewType->buildForm($builder, []);

        $this->assertNotContains('language', $addedFieldNames);
    }
}
