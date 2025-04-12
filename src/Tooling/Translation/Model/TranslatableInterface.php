<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Model;

use Doctrine\Common\Collections\Collection;

interface TranslatableInterface
{
    public static function getTranslationEntityClass(): string;

    public function setCurrentLocale(string $locale): void;

    public function getTranslations(): Collection;

    public function getTranslation(?string $locale = null): TranslationInterface;
}
