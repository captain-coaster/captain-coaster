<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Country
 *
 * @ORM\Table(name="country")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\CountryRepository")
 */
class Country
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
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     */
    private $slug;

    /**
     * @var Park
     *
     * @ORM\OneToMany(targetEntity="Park", mappedBy="country")
     */
    private $parks;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parks = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Country
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
     * @return Country
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
     * Add park
     *
     * @param \BddBundle\Entity\Park $park
     *
     * @return Country
     */
    public function addPark(\BddBundle\Entity\Park $park)
    {
        $this->parks[] = $park;

        return $this;
    }

    /**
     * Remove park
     *
     * @param \BddBundle\Entity\Park $park
     */
    public function removePark(\BddBundle\Entity\Park $park)
    {
        $this->parks->removeElement($park);
    }

    /**
     * Get parks
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getParks()
    {
        return $this->parks;
    }
}
