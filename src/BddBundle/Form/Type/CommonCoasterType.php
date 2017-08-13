<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\Coaster;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CommonCoasterType
 * @package BddBundle\Form\Type
 */
class CommonCoasterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('builtCoaster', BuiltCoasterType::class)
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