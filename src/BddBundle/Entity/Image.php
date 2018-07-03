<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Badge
 *
 * @ORM\Table(name="image")
 * @ORM\Entity
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
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster")
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
     * @Assert\File(mimeTypes={"image/jpeg"})
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
}
