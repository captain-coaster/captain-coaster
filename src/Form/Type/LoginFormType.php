<?php

declare(strict_types=1);

namespace App\Form\Type;

use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

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
                // rejects non-email input server-side; the raw value is later reflected
                // (unescaped) into the "link sent" flash message
                'constraints' => [new Assert\Email()],
            ])
            ->add('recaptcha', TurnstileType::class, ['mapped' => false, 'label' => false, 'attr' => ['data-appearance' => 'interaction-only']]);
    }
}
