<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // empty_data: an empty field must reach validation as '', not fail on User's string setters as null
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'attr' => ['autocomplete' => 'email'],
                'empty_data' => '',
            ])
            ->add('firstName', TextType::class, [
                'label' => 'register.form.first_name',
                'attr' => ['autocomplete' => 'given-name'],
                'empty_data' => '',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'register.form.last_name',
                'attr' => ['autocomplete' => 'family-name'],
            ]);

        $builder->add('recaptcha', TurnstileType::class, ['mapped' => false, 'label' => false, 'attr' => ['data-appearance' => 'interaction-only']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
