<?php

declare(strict_types=1);

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
 * Class TopDetailsType.
 */
class TopDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => Top::class,
            ]
        );
    }
}
