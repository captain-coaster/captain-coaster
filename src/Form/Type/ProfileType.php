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

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $completeName = \sprintf('%s %s', $options['firstname'], $options['lastname']);
        $partialName = \sprintf('%s %s.', $options['firstname'], substr((string) $options['lastname'], 0, 1));
        $locales = $options['locales'];

        $builder
            ->add('displayName', ChoiceType::class, [
                'choices' => [
                    $completeName => $completeName,
                    $partialName => $partialName,
                ],
                'label' => 'me.form.displayName',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('emailNotification', ChoiceType::class, [
                'choices' => [
                    'me.form.choices.email' => true,
                    'me.form.choices.notif' => false,
                ],
                'label' => 'me.form.notificationPreference',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('preferredLocale', ChoiceType::class, [
                'choices' => $locales,
                'choice_label' => fn ($value) => $value,
                'label' => 'me.form.preferredLocale',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('homePark', EntityType::class, [
                'required' => false,
                'label' => 'me.form.homePark.label',
                'class' => Park::class,
                'placeholder' => 'me.form.homePark.placeholder',
                'attr' => ['class' => 'form-control'],
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')
                    ->orderBy('p.name', 'ASC'),
            ])
            ->add('imperial', ChoiceType::class, [
                'choices' => [
                    'me.form.units.metric' => false,
                    'me.form.units.imperial' => true,
                ],
                'label' => 'me.form.units.label',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('addTodayDateWhenRating', CheckboxType::class, [
                'required' => false,
                'label' => 'me.form.addTodayDateWhenRating.label',
            ])
            ->add('apiKey', TextType::class, [
                'required' => false,
                'disabled' => true,
                'label' => 'me.form.apiKey.label',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('profilePicture', FileType::class, [
                'label' => 'me.form.profilePicture',
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'firstname' => null,
            'lastname' => null,
            'locales' => [],
        ]);
    }
}
