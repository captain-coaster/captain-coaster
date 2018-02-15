<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\RiddenCoaster;
use BddBundle\Entity\Tag;
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
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('value', TextType::class, ['required' => true])
            ->add(
                'pros',
                EntityType::class,
                [
                    'class' => 'BddBundle:Tag',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('p')
                            ->where('p.type = :pro')
                            ->setParameter('pro', Tag::PRO)
                            ->orderBy('p.name', 'ASC');
                    },
                    'label' => 'review.pros',
                ]
            )
            ->add(
                'cons',
                EntityType::class,
                [
                    'class' => 'BddBundle:Tag',
                    'choice_label' => 'name',
                    'choice_translation_domain' => 'database',
                    'multiple' => true,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('c')
                            ->where('c.type = :con')
                            ->setParameter('con', Tag::CON)
                            ->orderBy('c.name', 'ASC');
                    },
                    'label' => 'review.pros',
                ]
            )
            ->add(
                'review',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => 'review.comment',
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
                'data_class' => RiddenCoaster::class,
            ]
        );
    }
}
