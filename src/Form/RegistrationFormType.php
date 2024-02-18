<?php

declare(strict_types=1);
 
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', null, ['attr' => ['class' => 'form-control', 'placeholder' => 'Email'], 'label' => false])
            ->add('firstName', null, ['attr' => ['class' => 'form-control', 'placeholder' => 'First Name'], 'label' => false])
            ->add('lastName', null, ['attr' => ['class' => 'form-control', 'placeholder' => 'Last Name'], 'label' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
