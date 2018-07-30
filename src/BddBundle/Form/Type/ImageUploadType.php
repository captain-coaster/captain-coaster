<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\Image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ImageUploadType
 * @package BddBundle\Form\Type
 */
class ImageUploadType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, ['required' => true])
            ->add(
                'watermark',
                ChoiceType::class,
                [
                    'required' => true,
                    'choices' => [
                        'Captain Coaster' => 'cc',
                        'Aucun' => null,
                    ],
                ]
            )
            ->add('credit', TextType::class, ['required' => false])
            ->add('upload', SubmitType::class);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Image::class,]);
    }
}
