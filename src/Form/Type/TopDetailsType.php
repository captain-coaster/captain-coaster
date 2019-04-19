<?php

namespace App\Form\Type;

use App\Entity\Top;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class TopDetailsType
 * @package App\Form\Type
 */
class TopDetailsType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'required' => true,
                    'label' => 'top.new.form.name',
                    'translation_domain' => 'messages',
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'top.new.form.top' => 'top',
                        'top.new.form.flop' => 'flop',
                    ],
                    'choice_translation_domain' => 'messages',
                    'expanded' => true,
                    'required' => true,
                    'label' => 'top.new.form.type',
                    'translation_domain' => 'messages',
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add('save', SubmitType::class);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => Top::class,
            ]
        );
    }
}
