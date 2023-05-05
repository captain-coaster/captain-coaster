<?php

namespace App\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
/**
 * Badge
 *
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_image']])]
#[ORM\Table(name: 'image')]
#[ORM\Entity(repositoryClass: \App\Repository\ImageRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['coaster' => 'exact'])]
class Image
{
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private int $id;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private string $filename;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Coaster::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read_image'])]
    private Coaster $coaster;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $optimized = false;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $enabled = false;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $watermarked;
    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class, inversedBy: 'images')]
    #[Assert\NotBlank]
    private User $uploader;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    #[Assert\NotBlank]
    #[Groups(['read_image'])]
    private ?string $credit = null;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $likeCounter = 0;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTime $createdAt;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTime $updatedAt;
    #[Assert\File(mimeTypes: ['image/jpeg'], maxSize: '13M')]
    #[Assert\Image(minPixels: 720000)]
    private $file;
    #[Groups(['read_coaster', 'read_image'])]
    private string $path;
    public function getId() : int
    {
        return $this->id;
    }
    public function setFilename(string $filename) : Image
    {
        $this->filename = $filename;
        return $this;
    }
    public function getFilename() : string
    {
        return $this->filename;
    }
    public function setOptimized(bool $optimized) : Image
    {
        $this->optimized = $optimized;
        return $this;
    }
    public function isOptimized() : bool
    {
        return $this->optimized;
    }
    public function setCoaster(Coaster $coaster) : Image
    {
        $this->coaster = $coaster;
        return $this;
    }
    public function getCoaster() : Coaster
    {
        return $this->coaster;
    }
    public function getPath() : string
    {
        return $this->filename;
    }
    public function setFile($file) : Image
    {
        $this->file = $file;
        return $this;
    }
    public function getFile()
    {
        return $this->file;
    }
    public function setEnabled(bool $enabled) : Image
    {
        $this->enabled = $enabled;
        return $this;
    }
    public function isEnabled() : bool
    {
        return $this->enabled;
    }
    public function setCreatedAt(\DateTime $createdAt) : Image
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function getCreatedAt() : \DateTime
    {
        return $this->createdAt;
    }
    public function setUpdatedAt(\DateTime $updatedAt) : Image
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function getUpdatedAt() : \DateTime
    {
        return $this->updatedAt;
    }
    public function setCredit(?string $credit) : Image
    {
        $this->credit = $credit;
        return $this;
    }
    public function getCredit() : ?string
    {
        return $this->credit;
    }
    public function setWatermarked(bool $watermarked) : Image
    {
        $this->watermarked = $watermarked;
        return $this;
    }
    public function isWatermarked() : bool
    {
        return $this->watermarked;
    }
    public function setUploader(User $uploader) : Image
    {
        $this->uploader = $uploader;
        return $this;
    }
    public function getUploader() : ?User
    {
        return $this->uploader;
    }
    public function setLikeCounter(int $likeCounter) : Image
    {
        $this->likeCounter = $likeCounter;
        return $this;
    }
    public function getLikeCounter() : int
    {
        return $this->likeCounter;
    }
}
