<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use App\Service\VocabularyLabeler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\TagRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewTypeTest extends TestCase
{
    public function testConfigureOptionsEnablesTheReviewTextAndRideDateValidationGroups(): void
    {
        $tagRepository = $this->createStub(TagRepository::class);
        $reviewType = new ReviewType($tagRepository, new VocabularyLabeler($this->createStub(TranslatorInterface::class)));

        $resolver = new OptionsResolver();
        $reviewType->configureOptions($resolver);
        $options = $resolver->resolve([]);

        $this->assertSame(RiddenCoaster::class, $options['data_class']);
        $this->assertSame(['Default', 'review_text', 'ride_date'], $options['validation_groups']);
    }

    public function testLanguageFieldIsNotBuiltIntoTheForm(): void
    {
        $tagRepository = $this->createStub(TagRepository::class);
        $reviewType = new ReviewType($tagRepository, new VocabularyLabeler($this->createStub(TranslatorInterface::class)));

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
