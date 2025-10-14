<?php

declare(strict_types=1);

namespace App\Form\Filter\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutocompleteEntityFilterType extends CrudAutocompleteType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // Needed because EasyCorp\Bundle\EasyAdminBundle\Filter\Configurator\EntityConfigurator
        // sets a placeholder which CrudAutocompleteType does not support
        $resolver->setDefault('placeholder', '');
    }
}
