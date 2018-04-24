<?php

namespace BddBundle\Form\Type;

use BddBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ProfileType
 * @package BddBundle\Form\Type
 */
class ProfileType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $completeName = sprintf('%s %s', $options['firstname'], $options['lastname']);
        $partialName = sprintf(
            '%s %s.',
            $options['firstname'],
            substr($options['lastname'], 0, 1)
        );
        $locales = $options['locales'];

        $builder
            ->add(
                'displayName',
                ChoiceType::class,
                [
                    'choices' => [
                        $completeName => $completeName,
                        $partialName => $partialName,
                    ],
                    'label' => 'me.form.displayName',
                ]
            )
            ->add(
                'emailNotification',
                ChoiceType::class,
                [
                    'choices' => [
                        'me.form.choices.email' => true,
                        'me.form.choices.notif' => false,
                    ],
                    'label' => 'me.form.notificationPreference',
                ]
            )
            ->add(
                'preferredLocale',
                ChoiceType::class,
                [
                    'choices' => $locales,
                    'choice_label' => function ($value) {
                        return $value;
                    },
                    'label' => 'me.form.preferredLocale',
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
                'data_class' => User::class,
                'firstname' => null,
                'lastname' => null,
                'locales' => [],
            ]
        );
    }
}
