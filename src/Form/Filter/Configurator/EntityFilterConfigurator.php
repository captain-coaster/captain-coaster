<?php

declare(strict_types=1);

namespace App\Form\Filter\Configurator;

use App\Form\Filter\Type\AutocompleteEntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class EntityFilterConfigurator implements FilterConfiguratorInterface
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
        return EntityFilter::class === $filterDto->getFqcn();
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

        if ($fieldDto && $fieldDto->getCustomOption(AssociationField::OPTION_AUTOCOMPLETE)) {
            $doctrineMetadata = $entityDto->getPropertyMetadata($propertyName);

            $targetEntityFqcn = $doctrineMetadata->get('targetEntity');
            $targetCrudControllerFqcn = $context->getCrudControllers()->findCrudFqcnByEntityFqcn($targetEntityFqcn);
            if (null === $targetCrudControllerFqcn) {
                throw new \RuntimeException(\sprintf('The "%s" field cannot be autocompleted because it doesn\'t define the related CRUD controller FQCN with the "setCrudController()" method.', $filterDto->getProperty()));
            }

            if ($targetCrudControllerFqcn) {
                $filterDto->setFormTypeOptionIfNotSet('value_type', AutocompleteEntityFilterType::class);

                try {
                    $autocompleteEndpointUrl = $this->adminUrlGenerator
                        ->unsetAll()
                        ->set('page', 1)
                        ->setController($targetCrudControllerFqcn)
                        ->setAction('autocomplete')
                        ->set(AssociationField::PARAM_AUTOCOMPLETE_CONTEXT, [
                            // when using pretty URLs, the data is in the request attributes instead of the autocomplete context
                            EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->attributes->get(
                                EA::CRUD_CONTROLLER_FQCN
                            ) ?? $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                            'propertyName' => $propertyName,
                            'originatingPage' => $context->getCrud()->getCurrentAction(),
                        ])
                        ->generateUrl()
                    ;
                } catch (RouteNotFoundException $e) {
                    // this may throw a "route not found" exception if the associated entity is not
                    // accessible from this dashboard; do nothing in that case.
                }

                $filterDto->setFormTypeOptionIfNotSet(
                    'value_type_options.attr.data-ea-autocomplete-endpoint-url',
                    $autocompleteEndpointUrl ?? null
                );
            }
        }
    }
}
