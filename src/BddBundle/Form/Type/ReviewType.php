<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\RiddenCoaster;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ReviewType
 * @package BddBundle\Form\Type
 */
class ReviewType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', TextType::class, ['required' => true])
            ->add(
                'positiveKeywords',
                EntityType::class,
                [
                    'class' => 'BddBundle:PositiveKeyword',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('m')
                            ->orderBy('m.name', 'ASC');
                    },
                ]
            )
            ->add(
                'negativeKeywords',
                EntityType::class,
                [
                    'class' => 'BddBundle:NegativeKeyword',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('m')
                            ->orderBy('m.name', 'ASC');
                    },
                ]
            )
            ->add(
                'review',
                TextareaType::class,
                [
                    'required' => false,
                ]
            );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => RiddenCoaster::class,
            )
        );
    }
}