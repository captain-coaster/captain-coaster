<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Badge
 *
 * @ORM\Table(name="image")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\ImageRepository")
 */
class Image
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=false)
     */
    private $filename;

    /**
     * @var Coaster
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster", inversedBy="images")
     * @ORM\JoinColumn(nullable=false)
     */
    private $coaster;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $optimized = false;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $enabled = false;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=2, unique=false, nullable=true)
     */
    private $watermark;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=false, nullable=true)
     */
    private $credit;

    /**
     * @var \DateTime $createdAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @Assert\File(
     *     mimeTypes = {"image/jpeg"},
     *     maxSize = "5M"
     * )
     */
    private $file;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $filename
     * @return Image
     */
    public function setFilename(string $filename): Image
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @param bool $optimized
     * @return Image
     */
    public function setOptimized(bool $optimized): Image
    {
        $this->optimized = $optimized;

        return $this;
    }

    /**
     * @return bool
     */
    public function isOptimized(): bool
    {
        return $this->optimized;
    }

    /**
     * @param Coaster $coaster
     * @return Image
     */
    public function setCoaster(Coaster $coaster): Image
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * @return Coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return sprintf('%s/%s', substr($this->filename, 0, 1), $this->filename);
    }

    /**
     * @param mixed $file
     * @return Image
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param bool $enabled
     * @return Image
     */
    public function setEnabled(bool $enabled): Image
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param \DateTime $createdAt
     * @return Image
     */
    public function setCreatedAt(\DateTime $createdAt): Image
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $updatedAt
     * @return Image
     */
    public function setUpdatedAt(\DateTime $updatedAt): Image
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param null|string $watermark
     * @return Image
     */
    public function setWatermark(?string $watermark): Image
    {
        $this->watermark = $watermark;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getWatermark(): ?string
    {
        return $this->watermark;
    }

    /**
     * @param null|string $credit
     * @return Image
     */
    public function setCredit(?string $credit): Image
    {
        $this->credit = $credit;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getCredit(): ?string
    {
        return $this->credit;
    }
}
