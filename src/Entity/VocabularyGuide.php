<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VocabularyGuideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entity representing a language-specific vocabulary guide for translations.
 *
 * Stores vocabulary guides generated from native speaker reviews
 * plus manual additions to improve translation quality.
 */
#[ORM\Entity(repositoryClass: VocabularyGuideRepository::class)]
#[ORM\Table(name: 'vocabulary_guide')]
#[UniqueEntity(fields: ['language'], message: 'A vocabulary guide for this language already exists.')]
class VocabularyGuide
{
    public const SUPPORTED_LANGUAGES = ['en', 'fr', 'es', 'de'];

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 2, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGES, message: 'Language must be one of: {{ choices }}')]
    private string $language;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $content;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $reviewsAnalyzed = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        if (!\in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            throw new \InvalidArgumentException(\sprintf('Language must be one of: %s', implode(', ', self::SUPPORTED_LANGUAGES)));
        }

        $this->language = $language;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getReviewsAnalyzed(): int
    {
        return $this->reviewsAnalyzed;
    }

    public function setReviewsAnalyzed(int $reviewsAnalyzed): static
    {
        $this->reviewsAnalyzed = max(0, $reviewsAnalyzed);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public static function isSupportedLanguage(string $language): bool
    {
        return \in_array($language, self::SUPPORTED_LANGUAGES, true);
    }
}
