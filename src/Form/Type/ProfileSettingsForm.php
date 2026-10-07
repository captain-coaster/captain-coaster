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
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\When;

/**
 * @extends AbstractType<User>
 */
class ProfileSettingsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canChangeName = $options['can_change_name'];
        $locales = $options['locales'];
        // Get translator service from the container
        $translator = $options['translator'];

        // Regex explanation:
        // ^ - start of string
        // [\p{L}\p{M}] - Unicode letter + combining marks (for accents like é, ñ, ü)
        // (?:[\p{L}\p{M}]+(?:[\s\'\-][\p{L}\p{M}]+)*) - one or more letters, optionally followed by space/apostrophe/hyphen and more letters
        // $ - end of string
        // This prevents: leading/trailing spaces, consecutive special chars, numbers, and most special characters
        $nameRegex = new Regex(
            pattern: '/^[\p{L}\p{M}]+(?:[\s\'\-][\p{L}\p{M}]+)*$/u',
            message: $translator->trans('profile.settings.form.name_invalid'),
        );

        // Name section
        // First name field (disabled if can't change name)
        $builder->add('firstName', TextType::class, [
            'label' => 'profile.settings.form.first_name',
            'help' => 'profile.settings.form.first_name_help',
            'attr' => ['autocomplete' => 'given-name'],
            'disabled' => !$canChangeName,
            // An empty submission must reach NotBlank as '', not fail on User's string setter as null
            'empty_data' => '',
            // Blank is reported once, by User's NotBlank; the format rules only apply to a value
            'constraints' => [
                new When(expression: 'value != ""', constraints: [
                    new Length(min: 2, max: 50),
                    $nameRegex,
                ]),
            ],
        ]);

        // Last name field (optional, disabled if can't change name)
        $builder->add('lastName', TextType::class, [
            'label' => 'profile.settings.form.last_name',
            'attr' => ['autocomplete' => 'family-name'],
            'required' => false,
            'disabled' => !$canChangeName,
            'constraints' => [
                new Length(min: 2, max: 50),
                $nameRegex,
            ],
        ]);

        // Get preview names from the User entity
        $user = $options['data'];

        $builder->add('displayNameFormat', ChoiceType::class, [
            'label' => 'profile.settings.form.display_name',
            'choices' => [
                $translator->trans('profile.settings.form.display_name_full').' ('.$user->getFullNameFormat().')' => 'full',
                $translator->trans('profile.settings.form.display_name_partial').' ('.$user->getPartialNameFormat().')' => 'partial',
                $translator->trans('profile.settings.form.display_name_first_only').' ('.$user->getFirstNameOnlyFormat().')' => 'first_only',
            ],
            'expanded' => false,
            'help' => 'profile.settings.form.display_name_help',
        ]);

        // Profile picture
        $builder->add('profilePicture', FileType::class, [
            'label' => 'profile.settings.form.profile_picture',
            'required' => false,
            'mapped' => false,
            'constraints' => [
                new File(
                    maxSize: '10M',
                    mimeTypes: [
                        'image/jpeg',
                        'image/png',
                    ],
                ),
            ],
        ]);

        // Preferences section
        $builder->add('emailNotification', CheckboxType::class, [
            'required' => false,
            'label' => 'profile.settings.form.email_notification',
            'help' => 'profile.settings.form.email_notification_help',
        ]);

        $builder->add('preferredLocale', ChoiceType::class, [
            'choices' => $locales,
            'choice_label' => static fn ($value) => $value,
            'label' => 'profile.settings.form.locale',
        ]);

        $builder->add('homePark', EntityType::class, [
            'required' => false,
            'label' => 'profile.settings.form.home_park',
            'class' => Park::class,
            'placeholder' => 'profile.settings.form.home_park_placeholder',
            'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('p')
                ->orderBy('p.name', 'ASC'),
        ]);

        $builder->add('preferredUnits', ChoiceType::class, [
            'choices' => [
                'units.metric' => 'metric',
                'units.imperial' => 'imperial',
            ],
            'label' => 'profile.settings.form.units',
        ]);

        $builder->add('preferredReviewLanguages', ChoiceType::class, [
            'choices' => array_combine($locales, $locales),
            'choice_label' => static fn ($value) => $value,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'label' => 'profile.settings.form.review_languages',
            'block_prefix' => 'chip_choice',
            'help' => 'profile.settings.form.review_languages_help',
        ]);

        $builder->add('addTodayDateWhenRating', CheckboxType::class, [
            'required' => false,
            'label' => 'profile.settings.form.add_today_date',
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
