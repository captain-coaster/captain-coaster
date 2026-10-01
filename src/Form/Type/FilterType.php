<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The coaster filter panel (ranking, map, coaster search). It only renders:
 * filters apply on change through filter_controller.js, and the endpoints
 * validate the query with FilterService::validateFilters(). Created by
 * FilterService::createForm() under the name "filters", so fields post as
 * filters[key], the format those endpoints read.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class FilterType extends AbstractType
{
    private const array SWITCHES = ['status', 'notridden', 'new', 'kiddie', 'sortByDistance'];

    /** Select filters, with the translation domain of their choices (false: names shown as stored). */
    private const array SELECTS = [
        'manufacturer' => false,
        'model' => false,
        'materialType' => 'database',
        'seatingType' => 'database',
        'continent' => 'database',
        'country' => 'database',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $excluded = $options['excluded'];
        $data = $options['filter_data'];

        if (!\in_array('name', $excluded, true)) {
            $builder->add('name', SearchType::class, [
                'label' => 'filters.name',
                'required' => false,
                'attr' => ['maxlength' => 100, 'enterkeyhint' => 'search', 'autocomplete' => 'off'],
            ]);
        }

        foreach (array_diff(self::SWITCHES, $excluded) as $name) {
            $builder->add($name, SwitchType::class, [
                'label' => 'filters.'.$name,
                'value' => 'on',
                'attr' => 'sortByDistance' === $name ? ['data-action' => 'filter#toggleGeolocation'] : [],
            ]);
            // The filters array carries 'on', a checkbox a boolean
            $builder->get($name)->addModelTransformer(new CallbackTransformer(
                static fn (mixed $value): bool => 'on' === $value || true === $value,
                static fn (mixed $checked): ?string => $checked ? 'on' : null,
            ));
        }

        if (!\in_array('score', $excluded, true)) {
            $builder->add('score', ChoiceType::class, [
                'label' => 'filters.score',
                'required' => false,
                'choices' => range(10, 90, 10),
                'choice_label' => static fn (int $score): TranslatableMessage => new TranslatableMessage('filters.atleast', ['count' => $score]),
                'placeholder' => 'filters.any',
            ]);
        }

        foreach (self::SELECTS as $name => $domain) {
            if (\in_array($name, $excluded, true)) {
                continue;
            }
            $names = array_column($data[$name] ?? [], 'name', 'id');
            $builder->add($name, ChoiceType::class, [
                'label' => 'filters.'.$name,
                'required' => false,
                'choices' => array_keys($names),
                'choice_label' => static fn (int $id): string => (string) $names[$id],
                'choice_translation_domain' => $domain,
                'placeholder' => 'filters.any',
            ]);
        }

        if (!\in_array('openingDate', $excluded, true)) {
            $builder->add('openingDate', ChoiceType::class, [
                'label' => 'filters.openingDate',
                'required' => false,
                'choices' => array_map(intval(...), array_column($data['openingDate'] ?? [], 'year')),
                'choice_label' => static fn (int $year): string => (string) $year,
                'choice_translation_domain' => false,
                'placeholder' => 'filters.any',
            ]);
        }

        $builder
            ->add('user', HiddenType::class)
            ->add('ridden', HiddenType::class);

        if (!\in_array('sortByDistance', $excluded, true)) {
            $builder
                ->add('latitude', HiddenType::class, ['attr' => ['data-filter-target' => 'latitude']])
                ->add('longitude', HiddenType::class, ['attr' => ['data-filter-target' => 'longitude']]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'control_size' => 'compact',
            'mark_optional' => false,
            'excluded' => [],
            'filter_data' => [],
        ]);
        $resolver->setAllowedTypes('excluded', 'string[]');
        $resolver->setAllowedTypes('filter_data', 'array');
    }
}
