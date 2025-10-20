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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
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
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTime $updatedAt;

    #[ORM\Column(type: Types::STRING, length: 8, nullable: true)]
    private ?string $hash = null;

    #[Assert\File(mimeTypes: ['image/jpeg'], maxSize: '15M')]
    #[Assert\Image(minPixels: 786432)]
    private ?UploadedFile $file;

    #[Groups(['read_coaster', 'read_image'])]
    private string $path;

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

    public function getPath(): string
    {
        return $this->filename;
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
}
