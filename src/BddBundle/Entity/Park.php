<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Park
 *
 * @ORM\Table(name="park")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\ParkRepository")
 */
class Park
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
     * @var string
     *
     * @ORM\Column(name="website", type="string", length=255, nullable=true)
     */
    private $website;

    /**
     * @var Coaster
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="park")
     */
    private $coasters;

    /**
     * @var Country
     *
     * @ORM\ManyToOne(targetEntity="Country", inversedBy="parks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $country;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="decimal", precision=8, scale=6, nullable=true)
     */
    private $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="decimal", precision=8, scale=6, nullable=true)
     */
    private $longitude;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->coasters = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Park
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
     * @return Park
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
     * Set website
     *
     * @param string $website
     *
     * @return Park
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    /**
     * Get website
     *
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * Add coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return Park
     */
    public function addCoaster(\BddBundle\Entity\Coaster $coaster)
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    /**
     * Remove coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     */
    public function removeCoaster(\BddBundle\Entity\Coaster $coaster)
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
     * Set country
     *
     * @param \BddBundle\Entity\Country $country
     *
     * @return Park
     */
    public function setCountry(\BddBundle\Entity\Country $country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country
     *
     * @return \BddBundle\Entity\Country
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set latitude
     *
     * @param string $latitude
     *
     * @return Coaster
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param string $longitude
     *
     * @return Coaster
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }
}
