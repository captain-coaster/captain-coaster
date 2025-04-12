<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Admin;

use App\Tooling\Translation\Form\TranslationsType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

class TranslationsField implements FieldInterface
{
    use FieldTrait;

    public function setValue(mixed $value): self
    {
        $this->dto->setValue($value);

        return $this;
    }

    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->onlyOnForms()
            ->addFormTheme('bundles/EasyAdminBundle/crud/form/field/translations.html.twig')
            ->setRequired(true)
            ->setFormType(TranslationsType::class)
        ;
    }
}
