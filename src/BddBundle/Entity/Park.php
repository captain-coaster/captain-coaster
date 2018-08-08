<?php

namespace BddBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
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
     * @var Coaster
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="park")
     * @ORM\OrderBy({"status" = "ASC", "name" = "ASC"})
     */
    private $coasters;

    /**
     * @var Country
     *
     * @ORM\ManyToOne(targetEntity="Country")
     * @ORM\JoinColumn(nullable=false)
     */
    private $country;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float", precision=8, scale=6, nullable=true)
     */
    private $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float", precision=8, scale=6, nullable=true)
     */
    private $longitude;

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
     * Add coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return Park
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    /**
     * Remove coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
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
     * Set country
     *
     * @param \BddBundle\Entity\Country $country
     *
     * @return Park
     */
    public function setCountry(Country $country)
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
     * @return Park
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
     * @return Park
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

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Park
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Park
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getOpenedCoasters()
    {
        return $this->getCoasters()->filter(
            function (Coaster $coaster) {
                return $coaster->getStatus()->getId() == 1;
            }
        );
    }

    /**
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getKiddies()
    {
        return $this->getCoasters()->filter(
            function (Coaster $coaster) {
                return $coaster->getBuiltCoaster()->isKiddie() == 1;
            }
        );
    }
}
