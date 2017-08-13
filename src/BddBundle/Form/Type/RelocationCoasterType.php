<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\Coaster;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CoasterType
 * @package BddBundle\Form\Type
 */
class RelocationCoasterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'builtCoaster',
                EntityType::class,
                [
                    'class' => 'BddBundle\Entity\BuiltCoaster',
                    'choice_label' => 'id',
                ]
            )
            ->add(
                'Coaster',
                CoasterType::class,
                [
                    'data_class' => CommonCoasterType::class,
                ]
            );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => Coaster::class,
            ]
        );
    }
}