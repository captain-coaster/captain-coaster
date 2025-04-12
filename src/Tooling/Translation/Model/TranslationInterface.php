<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Model;

interface TranslationInterface
{
    public static function getTranslatableEntityClass(): string;

    public function getLocale(): string;

    public function setLocale(string $locale): void;

    public function setTranslatable(TranslatableInterface $translatable): void;

    public function getLanguage(): string;
}
