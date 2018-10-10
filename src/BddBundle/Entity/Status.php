<?php

namespace BddBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Status
 *
 * @ORM\Table(name="status")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\StatusRepository")
 */
class Status
{
    CONST OPERATING = 'status.operating';

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
     * @ORM\Column(name="name", type="string", length=255, unique=true, nullable=false)
     * @Groups({"read"})
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true, nullable=false)
     * @Gedmo\Slug(fields={"name"})
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255, unique=false, nullable=false)
     */
    private $type;

    /**
     * @var Coaster
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="status")
     */
    private $coasters;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isRateable;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Status
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug
     *
     * @param string $slug
     *
     * @return Status
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Add coaster
     *
     * @param Coaster $coaster
     *
     * @return Status
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    /**
     * Remove coaster
     *
     * @param Coaster $coaster
     */
    public function removeCoaster(Coaster $coaster)
    {
        $this->coasters->removeElement($coaster);
    }

    /**
     * Get coasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCoasters()
    {
        return $this->coasters;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Status
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param bool $isRateable
     * @return Status
     */
    public function setIsRateable(bool $isRateable): Status
    {
        $this->isRateable = $isRateable;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRateable(): bool
    {
        return $this->isRateable;
    }
}
