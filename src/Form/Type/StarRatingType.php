<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A 0.5 to 5 star rating, rendered as ten radios (theme block star_rating_widget).
 *
 * @extends AbstractType<float>
 */
class StarRatingType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $values = range(0.5, 5.0, 0.5);

        $resolver->setDefaults([
            'choices' => array_combine(array_map(strval(...), $values), $values),
            'choice_label' => static fn (float $value): TranslatableMessage => new TranslatableMessage('form.star_rating.choice', ['value' => $value], 'messages'),
            'expanded' => true,
            'multiple' => false,
            'placeholder' => false,
            // No star checked: '0' isn't a choice, so the field fails with invalid_message
            // instead of mapping null onto a non-nullable rating.
            'empty_data' => '0',
            'invalid_message' => 'star_rating.required',
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'star_rating';
    }
}
