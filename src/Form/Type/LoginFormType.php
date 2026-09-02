<?php

declare(strict_types=1);

namespace App\Form\Type;

use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => false,
            ])
            ->add('recaptcha', TurnstileType::class, ['mapped' => false, 'label' => false, 'attr' => ['data-appearance' => 'interaction-only']]);
    }
}
