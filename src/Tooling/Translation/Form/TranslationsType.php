<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Form;

use App\Tooling\LocaleProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslationsType extends AbstractType
{
    public function __construct(private LocaleProvider $localeProvider)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $translations = $event->getData();
            $model = $event->getForm()->getParent()->getData();
            foreach ($this->localeProvider->getAvailableLocales() as $locale) {
                if ($translations->containsKey($locale)) {
                    continue;
                }
                $translations->set($locale, $model->getTranslation($locale));
            }
            $event->setData($translations);
        }, 900);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_add' => false,
            'allow_delete' => false,
        ]);
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }
}
