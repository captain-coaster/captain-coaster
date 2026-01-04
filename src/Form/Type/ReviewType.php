<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\RiddenCoaster;
use App\Entity\Tag;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewType extends AbstractType
{
    public function __construct(protected TranslatorInterface $translator)
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
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')
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
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('c')
                        ->where('c.type = :con')
                        ->setParameter('con', Tag::CON),
                    'label' => 'review.cons',
                ]
            )
            ->add(
                'language',
                ChoiceType::class,
                [
                    'choices' => $options['locales'],
                    'choice_label' => fn ($value) => $value,
                    'required' => true,
                    'label' => 'review.language',
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
        $this->sortTranslatedChoices($view->children['pros']->vars['choices']);
        $this->sortTranslatedChoices($view->children['cons']->vars['choices']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => RiddenCoaster::class,
                'locales' => [],
            ]
        );
    }

    private function sortTranslatedChoices(array &$choices): void
    {
        usort(
            $choices,
            fn ($a, $b): int => // could also use \Collator() to compare the two strings
            strcmp(
                $this->translator->trans($a->label, [], 'database'),
                $this->translator->trans($b->label, [], 'database')
            )
        );
    }
}
