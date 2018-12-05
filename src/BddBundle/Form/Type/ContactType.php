<?php

namespace BddBundle\Form\Type;

use BddBundle\Validator\Constraints\ReCaptcha;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ContactType
 * @package BddBundle\Form\Type
 */
class ContactType extends AbstractType
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
                    'label' => 'contact.form.name',
                    'translation_domain' => 'messages',
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
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'constraints' => [
                    new ReCaptcha(),
                ],
            ]
        );
    }
}
