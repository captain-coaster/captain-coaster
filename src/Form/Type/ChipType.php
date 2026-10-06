<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A checkbox rendered as a filter chip (theme block chip_widget): on or off, applied as soon as it changes.
 * For the yes/no filters of a filter panel; a setting uses SwitchType, a form with a submit button a checkbox.
 *
 * @extends AbstractType<bool>
 */
class ChipType extends AbstractType
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
        return 'chip';
    }
}
