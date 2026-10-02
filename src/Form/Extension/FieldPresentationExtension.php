<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Presentation options read by the form theme (templates/form/fields.html.twig).
 * Set on a form, they apply to all its fields; a field can override them.
 *
 * - control_size: "default" (48px controls) or "compact" (44px, filter panels)
 * - mark_optional: whether optional fields get the "(optional)" mark
 */
class FieldPresentationExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['control_size' => null, 'mark_optional' => null]);
        $resolver->setAllowedValues('control_size', [null, 'default', 'compact']);
        $resolver->setAllowedTypes('mark_optional', ['null', 'bool']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['control_size'] = $options['control_size'] ?? $view->parent?->vars['control_size'] ?? 'default';
        $view->vars['mark_optional'] = $options['mark_optional'] ?? $view->parent?->vars['mark_optional'] ?? true;
    }
}
