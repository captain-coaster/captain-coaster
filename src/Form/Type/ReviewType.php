<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\RiddenCoaster;
use App\Entity\Tag;
use App\Service\VocabularyLabeler;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
    public function __construct(private readonly VocabularyLabeler $labeler)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', NumberType::class, [
                'required' => true,
                'html5' => true,
            ])
            ->add(
                'pros',
                EntityType::class,
                [
                    'class' => Tag::class,
                    // A closure: the form passes the choice key as second argument, label() would read it as a locale.
                    'choice_label' => fn (Tag $tag): string => $this->labeler->label($tag),
                    'choice_translation_domain' => false,
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('p')
                        ->where('p.type = :pro')
                        ->setParameter('pro', Tag::PRO),
                    'label' => 'review.pros',
                ]
            )
            ->add(
                'cons',
                EntityType::class,
                [
                    'class' => Tag::class,
                    // A closure: the form passes the choice key as second argument, label() would read it as a locale.
                    'choice_label' => fn (Tag $tag): string => $this->labeler->label($tag),
                    'choice_translation_domain' => false,
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('c')
                        ->where('c.type = :con')
                        ->setParameter('con', Tag::CON),
                    'label' => 'review.cons',
                ]
            )
            ->add(
                'review',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => 'review.comment',
                ]
            )
            ->add(
                'riddenAt',
                DateType::class,
                [
                    'required' => false,
                    'label' => 'review.ridden_at',
                    'widget' => 'single_text',
                    'html5' => true,
                ]
            );
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (['pros', 'cons'] as $field) {
            $view->children[$field]->vars['choices'] = $this->labeler->sortByLabel(
                $view->children[$field]->vars['choices'],
                static fn (ChoiceView $choice): string => \is_string($choice->label) ? $choice->label : '',
            );
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => RiddenCoaster::class,
                'locales' => [],
                'validation_groups' => ['Default', 'review_text'],
            ]
        );
    }
}
