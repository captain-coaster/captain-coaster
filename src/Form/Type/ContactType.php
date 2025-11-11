<?php

declare(strict_types=1);

namespace App\Form\Type;

use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaV3Type;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrueV3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ContactType.
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isLoggedIn = $options['is_logged_in'];

        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'required' => true,
                    'label' => 'contact.form.name',
                    'translation_domain' => 'messages',
                    'disabled' => $isLoggedIn,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'email',
                EmailType::class,
                [
                    'required' => false,
                    'label' => 'contact.form.email',
                    'translation_domain' => 'messages',
                    'disabled' => $isLoggedIn,
                    'constraints' => [
                        new Email(),
                    ],
                ]
            )
            ->add(
                'message',
                TextareaType::class,
                [
                    'required' => true,
                    'label' => 'contact.form.message',
                    'translation_domain' => 'messages',
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            );

        $builder->add('recaptcha', EWZRecaptchaV3Type::class, ['mapped' => false, 'constraints' => [new IsTrueV3()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_logged_in' => false,
        ]);

        $resolver->setAllowedTypes('is_logged_in', 'bool');
    }
}
