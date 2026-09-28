<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Pick up to `max` entities from a short list, rendered as chips (theme block tag_choice_widget).
 * On phones only the first `visible` choices (plus any checked one) show until "Show all".
 *
 * @extends AbstractType<mixed>
 */
class TagChoiceType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['max'] = $options['max'];
        $view->vars['visible'] = $options['visible'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'max' => 3,
            'visible' => 8,
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'help' => 'form.tag_choice.help',
            'help_translation_parameters' => static fn (Options $options): array => ['max' => $options['max']],
            'constraints' => static fn (Options $options): array => [new Count(max: $options['max'])],
        ]);

        $resolver->setAllowedTypes('max', 'int');
        $resolver->setAllowedTypes('visible', 'int');
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'tag_choice';
    }
}
