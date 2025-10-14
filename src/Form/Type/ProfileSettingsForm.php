<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Park;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileSettingsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canChangeName = $options['can_change_name'];
        $locales = $options['locales'];

        // Name section
        // First name field (disabled if can't change name)
        $builder->add('firstName', TextType::class, [
            'label' => 'profile.settings.name.firstName',
            'disabled' => !$canChangeName,
            'constraints' => [
                new NotBlank(),
                new Length(['min' => 2, 'max' => 50]),
                new Regex([
                    'pattern' => '/^[A-Za-z\s\'\-\.]+$/',
                    'message' => 'profile.settings.name.invalid_characters',
                ]),
            ],
            'attr' => [
                'class' => 'form-control',
            ],
        ]);

        // Last name field (disabled if can't change name)
        $builder->add('lastName', TextType::class, [
            'label' => 'profile.settings.name.lastName',
            'disabled' => !$canChangeName,
            'constraints' => [
                new NotBlank(),
                new Length(['min' => 2, 'max' => 50]),
                new Regex([
                    'pattern' => '/^[A-Za-z\s\'\-\.]+$/',
                    'message' => 'profile.settings.name.invalid_characters',
                ]),
            ],
            'attr' => [
                'class' => 'form-control',
            ],
        ]);

        // Get preview names from the User entity
        $user = $options['data'];

        // Get translator service from the container
        $translator = $options['translator'];

        $builder->add('displayNameFormat', ChoiceType::class, [
            'label' => 'profile.settings.name.format',
            'choices' => [
                $translator->trans('profile.settings.name.format.full').' ('.$user->getFullNameFormat().')' => 'full',
                $translator->trans('profile.settings.name.format.partial').' ('.$user->getPartialNameFormat().')' => 'partial',
                $translator->trans('profile.settings.name.format.first_only').' ('.$user->getFirstNameOnlyFormat().')' => 'first_only',
            ],
            'expanded' => false,
            'help' => 'profile.settings.name.format.help',
            'attr' => [
                'class' => 'form-control',
            ],
        ]);

        // Profile picture
        $builder->add('profilePicture', FileType::class, [
            'label' => 'profile.settings.preferences.profilePicture',
            'required' => false,
            'mapped' => false,
            'constraints' => [
                new File([
                    'maxSize' => '2M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                    ],
                ]),
            ],
            'attr' => ['class' => 'form-control'],
        ]);

        // Preferences section
        $builder->add('emailNotification', ChoiceType::class, [
            'choices' => [
                'profile.settings.preferences.choices.email' => true,
                'profile.settings.preferences.choices.notif' => false,
            ],
            'label' => 'profile.settings.preferences.notificationPreference',
            'attr' => ['class' => 'form-control'],
        ]);

        $builder->add('preferredLocale', ChoiceType::class, [
            'choices' => $locales,
            'choice_label' => fn ($value) => $value,
            'label' => 'profile.settings.preferences.preferredLocale',
            'attr' => ['class' => 'form-control'],
        ]);

        $builder->add('homePark', EntityType::class, [
            'required' => false,
            'label' => 'profile.settings.preferences.homePark.label',
            'class' => Park::class,
            'placeholder' => 'profile.settings.preferences.homePark.placeholder',
            'attr' => ['class' => 'form-control'],
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')
                ->orderBy('p.name', 'ASC'),
        ]);

        $builder->add('imperial', ChoiceType::class, [
            'choices' => [
                'profile.settings.preferences.units.metric' => false,
                'profile.settings.preferences.units.imperial' => true,
            ],
            'label' => 'profile.settings.preferences.units.label',
            'attr' => ['class' => 'form-control'],
        ]);

        $builder->add('displayReviewsInAllLanguages', CheckboxType::class, [
            'required' => false,
            'label' => 'profile.settings.preferences.displayReviewsInAllLanguages.label',
        ]);

        $builder->add('addTodayDateWhenRating', CheckboxType::class, [
            'required' => false,
            'label' => 'profile.settings.preferences.addTodayDateWhenRating.label',
        ]);

        // Advanced section
        $builder->add('apiKey', TextType::class, [
            'required' => false,
            'disabled' => true,
            'label' => 'profile.settings.advanced.apiKey.label',
            'attr' => ['class' => 'form-control'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'can_change_name' => true,
            'locales' => [],
            'translator' => null,
        ]);

        $resolver->setAllowedTypes('translator', ['null', 'Symfony\Contracts\Translation\TranslatorInterface']);
    }
}
