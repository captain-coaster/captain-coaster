<?php

declare(strict_types=1);

namespace App\Tooling\Translation\Model;

use App\Tooling\Translation\Model\TranslationInterface as T;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Proxy;

trait TranslatableTrait
{
    private ?Collection $translations = null;

    private ?string $currentLocale = null;

    public function getTranslations(): Collection
    {
        return $this->translations ?? $this->translations = new ArrayCollection();
    }

    /** @throws \Exception */
    public function getTranslation(?string $locale = null): T
    {
        if ($this instanceof Proxy) {
            $this->__load();
        }

        if (null === $this->currentLocale && null === $locale) {
            throw new \Exception('No locale has been set and current locale is undefined. See TranslatableListener::postLoad().');
        }

        if (null === $locale) {
            $locale = $this->currentLocale;
        } elseif (null === $this->currentLocale) {
            $this->currentLocale = $locale;
        }

        if ($this->getTranslations()->containsKey($locale)) {
            return $this->getTranslations()->get($locale);
        }

        $translationClass = self::getTranslationEntityClass();
        $translation = new $translationClass();
        $translation->setLocale($locale);
        $translation->setTranslatable($this);

        $this->getTranslations()->set($locale, $translation);

        return $translation;
    }

    public function setCurrentLocale(string $currentLocale): void
    {
        $this->currentLocale = $currentLocale;
    }

    public function addTranslation(T $translation): void
    {
        $this->getTranslations()->add($translation);
    }

    public function removeTranslation(T $translation): void
    {
        $this->getTranslations()->removeElement($translation);
    }

    public static function getTranslationEntityClass(): string
    {
        return static::class.'Translation';
    }
}
