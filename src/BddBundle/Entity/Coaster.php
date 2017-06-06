<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coaster
 *
 * @ORM\Table(name="coaster")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\CoasterRepository")
 */
class Coaster
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
     * @ORM\Column(name="name", type="string", length=255)
     *
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     */
    private $slug;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="openingDate", type="datetime", nullable=true)
     */
    private $openingDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="closingDate", type="datetime", nullable=true)
     */
    private $closingDate;

    /**
     * @var string
     *
     * @ORM\Column(name="averageRating", type="decimal", precision=5, scale=3, nullable=true)
     */
    private $averageRating;

    /**
     * @var BuiltCoaster
     *
     * @ORM\ManyToOne(targetEntity="BuiltCoaster", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $builtCoaster;

    /**
     * @var Park
     *
     * @ORM\ManyToOne(targetEntity="Park", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $park;

    /**
     * @var Status
     *
     * @ORM\ManyToOne(targetEntity="Status", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;


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
     * @return Coaster
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
     * @return Coaster
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
     * Set openingDate
     *
     * @param \DateTime $openingDate
     *
     * @return Coaster
     */
    public function setOpeningDate($openingDate)
    {
        $this->openingDate = $openingDate;

        return $this;
    }

    /**
     * Get openingDate
     *
     * @return \DateTime
     */
    public function getOpeningDate()
    {
        return $this->openingDate;
    }

    /**
     * Set closingDate
     *
     * @param \DateTime $closingDate
     *
     * @return Coaster
     */
    public function setClosingDate($closingDate)
    {
        $this->closingDate = $closingDate;

        return $this;
    }

    /**
     * Get closingDate
     *
     * @return \DateTime
     */
    public function getClosingDate()
    {
        return $this->closingDate;
    }

    /**
     * Set averageRating
     *
     * @param string $averageRating
     *
     * @return Coaster
     */
    public function setAverageRating($averageRating)
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    /**
     * Get averageRating
     *
     * @return string
     */
    public function getAverageRating()
    {
        return $this->averageRating;
    }

    /**
     * Set builtCoaster
     *
     * @param \BddBundle\Entity\BuiltCoaster $builtCoaster
     *
     * @return Coaster
     */
    public function setBuiltCoaster(\BddBundle\Entity\BuiltCoaster $builtCoaster = null)
    {
        $this->builtCoaster = $builtCoaster;

        return $this;
    }

    /**
     * Get builtCoaster
     *
     * @return \BddBundle\Entity\BuiltCoaster
     */
    public function getBuiltCoaster()
    {
        return $this->builtCoaster;
    }

    /**
     * Set park
     *
     * @param \BddBundle\Entity\Park $park
     *
     * @return Coaster
     */
    public function setPark(\BddBundle\Entity\Park $park)
    {
        $this->park = $park;

        return $this;
    }

    /**
     * Get park
     *
     * @return \BddBundle\Entity\Park
     */
    public function getPark()
    {
        return $this->park;
    }

    /**
     * Set status
     *
     * @param \BddBundle\Entity\Status $status
     *
     * @return Coaster
     */
    public function setStatus(\BddBundle\Entity\Status $status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return \BddBundle\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }
}
