<?php

namespace App\Form\Type;

use App\Entity\RiddenCoaster;
use App\Entity\Tag;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class ReviewType
 * @package App\Form\Type
 */
class ReviewType extends AbstractType
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * ReviewType constructor.
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', TextType::class, ['required' => true])
            ->add(
                'pros',
                EntityType::class,
                [
                    'class' => 'App:Tag',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('p')
                            ->where('p.type = :pro')
                            ->setParameter('pro', Tag::PRO);
                    },
                    'label' => 'review.pros',
                ]
            )
            ->add(
                'cons',
                EntityType::class,
                [
                    'class' => 'App:Tag',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('c')
                            ->where('c.type = :con')
                            ->setParameter('con', Tag::CON);
                    },
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
                    'html5' => false,
                ]
            );
    }

    /**
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->sortTranslatedChoices($view->children['pros']->vars['choices']);
        $this->sortTranslatedChoices($view->children['cons']->vars['choices']);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => RiddenCoaster::class,
            ]
        );
    }

    /**
     * @param array $choices
     */
    private function sortTranslatedChoices(array &$choices)
    {
        usort(
            $choices,
            function ($a, $b) {
                // could also use \Collator() to compare the two strings
                return strcmp(
                    $this->translator->trans($a->label, array(), 'database'),
                    $this->translator->trans($b->label, array(), 'database')
                );
            }
        );
    }
}
