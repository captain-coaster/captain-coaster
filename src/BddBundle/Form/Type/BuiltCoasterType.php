<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\BuiltCoaster;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class BuiltCoasterType
 * @package BddBundle\Form\Type
 */
class BuiltCoasterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id', TextType::class, ['disabled' => true])
            ->add('speed', TextType::class)
            ->add('height', TextType::class)
            ->add('length', TextType::class)
            ->add('inversionsNumber', TextType::class)
            ->add('gForce', TextType::class)
            ->add(
                'manufacturer',
                EntityType::class,
                [
                    'class' => 'BddBundle:Manufacturer',
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('m')
                            ->orderBy('m.name', 'ASC');
                    },
                ]
            )
            ->add(
                'restraint',
                EntityType::class,
                [
                    'class' => 'BddBundle:Restraint',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('r')
                            ->orderBy('r.name', 'ASC');
                    },
                ]
            )
            ->add(
                'launchs',
                EntityType::class,
                [
                    'class' => 'BddBundle:Launch',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'expanded' => true,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('l')
                            ->orderBy('l.name', 'ASC');
                    },
                ]
            )
            ->add(
                'types',
                EntityType::class,
                [
                    'class' => 'BddBundle:Type',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'expanded' => true,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('t')
                            ->orderBy('t.name', 'ASC');
                    },
                ]
            )
            ->add('kiddie', CheckboxType::class);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => BuiltCoaster::class,
            ]
        );
    }
}
