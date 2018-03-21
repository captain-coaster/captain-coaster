<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\Report;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ReportCreateType
 * @package BddBundle\Form\Type
 */
class ReportCreateType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'park',
                EntityType::class,
                [
                    'class' => 'BddBundle\Entity\Park',
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('p')
                            ->orderBy('p.name', 'ASC');
                    },
                    'label' => 'report.form.park',
                ]
            )
            ->add(
                'visitDate',
                DateType::class,
                [
                    'widget' => 'single_text',
                    'required' => true,
                    'label' => 'report.form.visitdate',
                ]
            )
            ->add(
                'language',
                ChoiceType::class,
                [
                    'choices' => $options['languages'],
                    'choice_label' => function ($value) {
                        return $value;
                    },
                    'choice_translation_domain' => 'messages',
                    'label' => 'report.form.language',

                ]
            )
            ->add(
                'title',
                TextType::class,
                [
                    'label' => 'report.form.title',
                ]
            )
            ->add(
                'cover',
                FileType::class,
                [
                    'label' => 'Cover'
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
                'data_class' => Report::class,
            ]
        );
        $resolver->setRequired('languages');
    }
}