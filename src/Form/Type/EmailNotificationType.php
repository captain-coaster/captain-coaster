<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The email preference on the notifications page: one switch, saved as soon as it changes.
 *
 * @extends AbstractType<User>
 */
class EmailNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('emailNotification', SwitchType::class, [
            'label' => 'notif.form.email_notification',
            'help' => 'notif.form.email_notification_help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'notification',
        ]);
    }
}
