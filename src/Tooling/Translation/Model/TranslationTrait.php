<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Model;

trait TranslationTrait
{
    private TranslatableInterface $translatable;

    private string $locale;

    public function getTranslatable(): TranslatableInterface
    {
        return $this->translatable;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLanguage(): string
    {
        [$language] = explode('_', $this->locale);

        return strtolower($language);
    }

    public function getCountry(): string
    {
        [,$country] = explode('_', $this->locale);

        return strtolower($country);
    }

    public function setTranslatable(TranslatableInterface $translatable): void
    {
        $this->translatable = $translatable;
    }

    public static function getTranslatableEntityClass(): string
    {
        return str_replace('Translation', '', static::class);
    }
}
