<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\RiddenCoaster;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\VocabularyLabeler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RiddenCoaster>
 */
class ReviewType extends AbstractType
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly VocabularyLabeler $labeler,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', StarRatingType::class, [
                'label' => 'review.value',
            ])
            ->add('pros', TagChoiceType::class, [
                'class' => Tag::class,
                // A closure: the form passes the choice key as second argument, label() would read it as a locale.
                'choice_label' => fn (Tag $tag): string => $this->labeler->label($tag),
                'choice_translation_domain' => false,
                'choices' => $this->tagRepository->findByTypeMostUsedFirst(Tag::PRO),
                'label' => 'review.pros',
            ])
            ->add('cons', TagChoiceType::class, [
                'class' => Tag::class,
                // A closure: the form passes the choice key as second argument, label() would read it as a locale.
                'choice_label' => fn (Tag $tag): string => $this->labeler->label($tag),
                'choice_translation_domain' => false,
                'choices' => $this->tagRepository->findByTypeMostUsedFirst(Tag::CON),
                'label' => 'review.cons',
            ])
            ->add('review', TextareaType::class, [
                'required' => false,
                'label' => 'review.form.comment',
                'attr' => ['placeholder' => 'review.form.comment_placeholder'],
            ])
            ->add('riddenAt', DateType::class, [
                'required' => false,
                'label' => 'review.form.ridden_at',
                'widget' => 'single_text',
                'row_attr' => ['class' => 'md:max-w-56'],
            ]);
    }

    /** Bounds the ride date picker to the coaster's operating period. */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $coaster = $form->getData()?->getCoaster();
        $today = new \DateTimeImmutable('today');
        $riddenAt = $view->children['riddenAt'];
        if (null !== $coaster) {
            $riddenAt->vars['attr']['min'] = $coaster->getFirstRideDate()->format('Y-m-d');
        }
        $riddenAt->vars['attr']['max'] = min($today, $coaster?->getLastRideDate() ?? $today)->format('Y-m-d');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => RiddenCoaster::class,
                'validation_groups' => ['Default', 'review_text', 'ride_date'],
            ]
        );
    }
}
