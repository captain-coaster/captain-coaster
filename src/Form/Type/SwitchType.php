<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A checkbox rendered as a toggle switch (theme block switch_widget).
 * For settings that apply as soon as they change; a filter uses ChipType, a form with a submit button a checkbox.
 *
 * @extends AbstractType<bool>
 */
class SwitchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['required' => false]);
    }

    public function getParent(): string
    {
        return CheckboxType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'switch';
    }
}
