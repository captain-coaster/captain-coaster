<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Badge.
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_image']])]
#[ORM\Table(name: 'image')]
#[ORM\Index(columns: ['hash'])]
#[ORM\Entity(repositoryClass: ImageRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['coaster' => 'exact'])]
class Image
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['read_coaster', 'read_image'])]
    private string $filename;

    #[ORM\ManyToOne(targetEntity: Coaster::class, inversedBy: 'images', fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read_image'])]
    private Coaster $coaster;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $watermarked;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'images', fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private User $uploader;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\NotBlank]
    #[Groups(['read_image'])]
    private ?string $credit = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $likeCounter = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'change', field: ['filename', 'enabled', 'watermarked', 'credit', 'hash'])]
    private \DateTime $updatedAt;

    #[ORM\Column(type: Types::STRING, length: 8, nullable: true)]
    private ?string $hash = null;

    // Normalized (0-1) coordinates of the photo's actual subject, from GenAI moderation
    // analysis -- DB is the golden source (supports recomputing/resyncing the crop without
    // re-running the LLM); also propagated to S3 object metadata for the captain-infra crop
    // Lambda, which has no DB access (see ImageManager::writeFocalPointMetadata()).
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $focalX = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $focalY = null;

    // Null means "never analyzed" -- the reprocess/backfill command's default target.
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $analyzedAt = null;

    #[Assert\File(mimeTypes: ['image/jpeg'], maxSize: '15M')]
    // minRatio/maxRatio: no aspect-ratio constraint existed before -- an upload could be any
    // shape, including one the resize pipeline's `cover` fit would crop almost entirely away
    // against a landscape UI slot. Bounds are deliberately generous (a tall lift-hill shot down
    // to a wide panoramic track shot both fit) -- this only rejects genuinely degenerate slivers.
    #[Assert\Image(minPixels: 786432, minRatio: 1 / 3, maxRatio: 3)]
    // Default matters: ImageListener::prePersist()/postPersist() call getFile() on every new
    // Image, including any future creation path that doesn't go through the upload form (a
    // fixture, a script) -- without a default, an unset typed property throws "must not be
    // accessed before initialization" instead of behaving like the nullable type it is.
    private ?UploadedFile $file = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    public function setCoaster(Coaster $coaster): static
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(?string $credit): static
    {
        $this->credit = $credit;

        return $this;
    }

    public function isWatermarked(): bool
    {
        return $this->watermarked;
    }

    public function setWatermarked(bool $watermarked): static
    {
        $this->watermarked = $watermarked;

        return $this;
    }

    public function getUploader(): User
    {
        return $this->uploader;
    }

    public function setUploader(User $uploader): static
    {
        $this->uploader = $uploader;

        return $this;
    }

    public function getLikeCounter(): int
    {
        return $this->likeCounter;
    }

    public function setLikeCounter(int $likeCounter): static
    {
        $this->likeCounter = $likeCounter;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getFocalX(): ?float
    {
        return $this->focalX;
    }

    public function setFocalX(?float $focalX): static
    {
        $this->focalX = $focalX;

        return $this;
    }

    public function getFocalY(): ?float
    {
        return $this->focalY;
    }

    public function setFocalY(?float $focalY): static
    {
        $this->focalY = $focalY;

        return $this;
    }

    public function getAnalyzedAt(): ?\DateTime
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(?\DateTime $analyzedAt): static
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }
}
