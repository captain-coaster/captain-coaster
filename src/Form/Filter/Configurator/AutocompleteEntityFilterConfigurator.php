<?php

declare(strict_types=1);

namespace App\Form\Filter\Configurator;

use App\Form\Filter\AutocompleteEntityFilter;
use App\Form\Filter\Type\AutocompleteEntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

final class AutocompleteEntityFilterConfigurator implements FilterConfiguratorInterface
{
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function supports(
        FilterDto $filterDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto,
        AdminContext $context
    ): bool {
        return AutocompleteEntityFilter::class === $filterDto->getFqcn();
    }

    public function configure(
        FilterDto $filterDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto,
        AdminContext $context
    ): void {
        $propertyName = $filterDto->getProperty();
        if (!$entityDto->isAssociation($propertyName)) {
            return;
        }

        $doctrineMetadata = $entityDto->getPropertyMetadata($propertyName);
        // TODO: add the 'em' form type option too?
        $filterDto->setFormTypeOptionIfNotSet('value_type_options.class', $doctrineMetadata->get('targetEntity'));
        $filterDto->setFormTypeOptionIfNotSet(
            'value_type_options.multiple',
            $entityDto->isToManyAssociation($propertyName)
        );
        $filterDto->setFormTypeOptionIfNotSet('value_type_options.attr.data-ea-widget', 'ea-autocomplete');

        if ($entityDto->isToOneAssociation($propertyName)) {
            // don't show the 'empty value' placeholder when all join columns are required,
            // because an empty filter value would always return no result
            $numberOfRequiredJoinColumns = \count(
                array_filter(
                    $doctrineMetadata->get('joinColumns'),
                    static fn (array $joinColumn): bool => false === ($joinColumn['nullable'] ?? false)
                )
            );

            $someJoinColumnsAreNullable = \count(
                $doctrineMetadata->get('joinColumns')
            ) !== $numberOfRequiredJoinColumns;

            if ($someJoinColumnsAreNullable) {
                $filterDto->setFormTypeOptionIfNotSet('value_type_options.placeholder', 'label.form.empty_value');
            }
        }

        $targetEntityFqcn = $doctrineMetadata->get('targetEntity');
        $targetCrudControllerFqcn = $context->getCrudControllers()->findCrudFqcnByEntityFqcn($targetEntityFqcn);

        if ($targetCrudControllerFqcn) {
            $filterDto->setFormTypeOptionIfNotSet('value_type', AutocompleteEntityFilterType::class);
            $filterDto->setFormTypeOptionIfNotSet('value_type_options.class', $doctrineMetadata->get('targetEntity'));
            $filterDto->setFormTypeOptionIfNotSet(
                'value_type_options.multiple',
                $entityDto->isToManyAssociation($propertyName)
            );
            $filterDto->setFormTypeOptionIfNotSet('value_type_options.attr.data-widget', 'select2');

            $autocompleteEndpointUrl = $this->adminUrlGenerator
                ->set('page', 1)
                ->setController($targetCrudControllerFqcn)
                ->setAction('autocomplete')
                ->setEntityId(null)
                ->unset(EA::SORT)
                ->set('autocompleteContext', [
                    EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                    'propertyName' => $propertyName,
                    'originatingPage' => $context->getCrud()->getCurrentAction(),
                ])
                ->generateUrl()
            ;
            $filterDto->setFormTypeOption(
                'value_type_options.attr.data-ea-autocomplete-endpoint-url',
                $autocompleteEndpointUrl
            );
        }
    }
}
