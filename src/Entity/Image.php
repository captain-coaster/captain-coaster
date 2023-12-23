<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Badge
 *
 * @ORM\Table(name="image")
 * @ORM\Entity(repositoryClass="App\Repository\ImageRepository")
 * @ApiResource(
 *     attributes={
 *         "normalization_context"={"groups"={"read_image"}}
 *     },
 *     collectionOperations={"get"={"method"="GET"}},
 *     itemOperations={"get"={"method"="GET"}}
 * )
 * @ApiFilter(SearchFilter::class, properties={"coaster": "exact"})
 */
class Image
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255, unique=false, nullable=false)
     */
    private string $filename;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Coaster", inversedBy="images")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"read_image"})
     */
    private Coaster $coaster;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $optimized = false;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $enabled = false;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $watermarked;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="images")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotBlank()
     */
    private User $uploader;

    /**
     * @ORM\Column(type="string", length=255, unique=false, nullable=true)
     * @Assert\NotBlank()
     * @Groups({"read_image"})
     */
    private ?string $credit;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $likeCounter = 0;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private \DateTime $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private \DateTime $updatedAt;

    /**
     * @Assert\File(
     *     mimeTypes = {"image/jpeg"},
     *     maxSize = "13M"
     * )
     * @Assert\Image(
     *     minPixels = 720000
     * )
     */
    private $file;

    /**
     * @Groups({"read_coaster", "read_image"})
     */
    private string $path;

    public function getId(): int
    {
        return $this->id;
    }

    public function setFilename(string $filename): Image
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setOptimized(bool $optimized): Image
    {
        $this->optimized = $optimized;

        return $this;
    }

    public function isOptimized(): bool
    {
        return $this->optimized;
    }

    public function setCoaster(Coaster $coaster): Image
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    public function getPath(): string
    {
        return $this->filename;
    }

    public function setFile($file): Image
    {
        $this->file = $file;

        return $this;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setEnabled(bool $enabled): Image
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setCreatedAt(\DateTime $createdAt): Image
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): Image
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setCredit(?string $credit): Image
    {
        $this->credit = $credit;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setWatermarked(bool $watermarked): Image
    {
        $this->watermarked = $watermarked;

        return $this;
    }

    public function isWatermarked(): bool
    {
        return $this->watermarked;
    }

    public function setUploader(User $uploader): Image
    {
        $this->uploader = $uploader;

        return $this;
    }

    public function getUploader(): ?User
    {
        return $this->uploader;
    }

    public function setLikeCounter(int $likeCounter): Image
    {
        $this->likeCounter = $likeCounter;

        return $this;
    }

    public function getLikeCounter(): int
    {
        return $this->likeCounter;
    }
}
