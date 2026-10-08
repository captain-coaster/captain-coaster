<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Service\Contact\ContactTopic;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<mixed>
 */
class ContactType extends AbstractType
{
    private const int NAME_LENGTH = 100;
    private const int SUBJECT_LENGTH = 80;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Signed-in riders write as themselves: the controller takes their name and email.
        if (!$options['is_logged_in']) {
            $builder
                ->add('name', TextType::class, [
                    'label' => 'contact.form.name',
                    'attr' => ['autocomplete' => 'name', 'maxlength' => self::NAME_LENGTH],
                    'constraints' => [new NotBlank(), new Length(max: self::NAME_LENGTH)],
                ])
                ->add('email', EmailType::class, [
                    'required' => false,
                    'label' => 'contact.form.email',
                    'help' => 'contact.form.email_help',
                    'attr' => ['autocomplete' => 'email'],
                    'constraints' => [new Email()],
                ]);
        }

        $builder
            ->add('topic', EnumType::class, [
                'class' => ContactTopic::class,
                'label' => 'contact.form.topic',
                'placeholder' => 'contact.form.topic_placeholder',
                'choice_label' => static fn (ContactTopic $topic): string => $topic->translationKey(),
                'constraints' => [new NotBlank()],
            ])
            // Shown once "Other" is chosen (contact.html.twig).
            ->add('subject', TextType::class, [
                'required' => false,
                'label' => 'contact.form.subject',
                'attr' => ['maxlength' => self::SUBJECT_LENGTH],
                'constraints' => [new Length(max: self::SUBJECT_LENGTH)],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',
                'constraints' => [new NotBlank()],
            ]);

        $builder->add('recaptcha', TurnstileType::class, ['mapped' => false, 'label' => false, 'attr' => ['data-appearance' => 'interaction-only']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_logged_in' => false,
        ]);

        $resolver->setAllowedTypes('is_logged_in', 'bool');
    }
}
