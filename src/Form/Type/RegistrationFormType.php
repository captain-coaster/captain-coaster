<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaV3Type;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrueV3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'register.form.email'],
                'label' => false,
            ])
            ->add('firstName', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'register.form.first_name'],
                'label' => false,
            ])
            ->add('lastName', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'register.form.last_name'],
                'label' => false,
            ])
            ->add(
                'register',
                SubmitType::class,
                [
                    'attr' => [
                        'class' => 'bg-blue btn btn-block',
                    ],
                    'label' => 'register.form.submit',
                ]
            );

        $builder->add('recaptcha', EWZRecaptchaV3Type::class, ['mapped' => false, 'constraints' => [new IsTrueV3()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
