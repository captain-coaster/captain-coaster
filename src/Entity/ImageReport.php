<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImageReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * A GenAI moderation flag on an uploaded Image, pending human review.
 *
 * Unlike ReviewReport, there is no human-submitted path for images (no "report this photo"
 * feature exists) -- every row here is AI-generated, so this entity skips the user/AI
 * distinction ReviewReport needs. categories is a JSON array rather than a single reason
 * column because a photo can trigger multiple checks at once (e.g. watermark and
 * people-as-subject together).
 */
#[ORM\Table(name: 'image_report')]
#[ORM\Entity(repositoryClass: ImageReportRepository::class)]
class ImageReport
{
    public const CATEGORY_OFFTOPIC = 'offtopic';
    public const CATEGORY_WATERMARK = 'watermark';
    public const CATEGORY_RETOUCHED = 'retouched';
    public const CATEGORY_PEOPLE_SUBJECT = 'people_subject';
    public const CATEGORY_ONRIDE_PHOTO = 'onride_photo';
    public const CATEGORY_ANALYSIS_FAILED = 'analysis_failed';
    public const CATEGORY_UNCERTAIN = 'uncertain';

    public const CATEGORIES = [
        self::CATEGORY_OFFTOPIC,
        self::CATEGORY_WATERMARK,
        self::CATEGORY_RETOUCHED,
        self::CATEGORY_PEOPLE_SUBJECT,
        self::CATEGORY_ONRIDE_PHOTO,
        self::CATEGORY_ANALYSIS_FAILED,
        self::CATEGORY_UNCERTAIN,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private int $id;

    // Nullable + SET NULL so this report survives a Reject action's hard-delete of the Image.
    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(name: 'image_id', nullable: true, onDelete: 'SET NULL')]
    private ?Image $image = null;

    // Snapshots, for display after the image is gone.
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $coasterName = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $resolved = false;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $aiConfidence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiExplanation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $resolvedAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getCoasterName(): ?string
    {
        return $this->coasterName;
    }

    public function setCoasterName(?string $coasterName): static
    {
        $this->coasterName = $coasterName;

        return $this;
    }

    /** @return string[] */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @param string[] $categories */
    public function setCategories(array $categories): static
    {
        foreach ($categories as $category) {
            if (!\in_array($category, self::CATEGORIES, true)) {
                throw new \InvalidArgumentException(\sprintf('Invalid image report category "%s".', $category));
            }
        }

        $this->categories = array_values($categories);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid image report status "%s".', $status));
        }

        $this->status = $status;
        $this->resolved = self::STATUS_PENDING !== $status;

        if ($this->resolved && null === $this->resolvedAt) {
            $this->resolvedAt = new \DateTime();
        }

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function getAiConfidence(): ?string
    {
        return $this->aiConfidence;
    }

    public function setAiConfidence(?string $aiConfidence): static
    {
        $this->aiConfidence = $aiConfidence;

        return $this;
    }

    public function getAiExplanation(): ?string
    {
        return $this->aiExplanation;
    }

    public function setAiExplanation(?string $aiExplanation): static
    {
        $this->aiExplanation = $aiExplanation;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTime
    {
        return $this->resolvedAt;
    }
}
