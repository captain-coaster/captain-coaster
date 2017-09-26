<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BuiltCoaster
 *
 * @ORM\Table(name="built_coaster")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\BuiltCoasterRepository")
 */
class BuiltCoaster
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
     * @var int
     *
     * @ORM\Column(name="speed", type="integer", nullable=true)
     */
    private $speed;

    /**
     * @var int
     *
     * @ORM\Column(name="height", type="integer", nullable=true)
     */
    private $height;

    /**
     * @var int
     *
     * @ORM\Column(name="length", type="integer", nullable=true)
     */
    private $length;

    /**
     * @var int
     *
     * @ORM\Column(name="inversionsNumber", type="integer", nullable=true)
     */
    private $inversionsNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="gForce", type="float", precision=3, scale=2, nullable=true)
     */
    private $gForce;

    /**
     * @var Coaster
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="builtCoaster")
     */
    private $coasters;

    /**
     * @var Manufacturer
     *
     * @ORM\ManyToOne(targetEntity="Manufacturer", inversedBy="builtCoasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $manufacturer;

    /**
     * @var Restraint
     *
     * @ORM\ManyToOne(targetEntity="Restraint", inversedBy="builtCoasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $restraint;

    /**
     * @var Launch
     *
     * @ORM\ManyToMany(targetEntity="Launch", inversedBy="builtCoasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $launchs;

    /**
     * @var Type
     *
     * @ORM\ManyToMany(targetEntity="Type", inversedBy="builtCoasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $types;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="duration", type="time", unique=false, nullable=true)
     */
    private $duration;

    /**
     * @var string
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isKiddie = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->coasters = new \Doctrine\Common\Collections\ArrayCollection();
        $this->launchs = new \Doctrine\Common\Collections\ArrayCollection();
        $this->types = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set speed
     *
     * @param integer $speed
     *
     * @return BuiltCoaster
     */
    public function setSpeed($speed)
    {
        $this->speed = $speed;

        return $this;
    }

    /**
     * Get speed
     *
     * @return int
     */
    public function getSpeed()
    {
        return $this->speed;
    }

    /**
     * Set height
     *
     * @param integer $height
     *
     * @return BuiltCoaster
     */
    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get height
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Set length
     *
     * @param integer $length
     *
     * @return BuiltCoaster
     */
    public function setLength($length)
    {
        $this->length = $length;

        return $this;
    }

    /**
     * Get length
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Set inversionsNumber
     *
     * @param integer $inversionsNumber
     *
     * @return BuiltCoaster
     */
    public function setInversionsNumber($inversionsNumber)
    {
        $this->inversionsNumber = $inversionsNumber;

        return $this;
    }

    /**
     * Get inversionsNumber
     *
     * @return int
     */
    public function getInversionsNumber()
    {
        return $this->inversionsNumber;
    }

    /**
     * Set gForce
     *
     * @param string $gForce
     *
     * @return BuiltCoaster
     */
    public function setGForce($gForce)
    {
        $this->gForce = $gForce;

        return $this;
    }

    /**
     * Get gForce
     *
     * @return string
     */
    public function getGForce()
    {
        return $this->gForce;
    }

    /**
     * Add coaster
     *
     * @param Coaster $coaster
     *
     * @return BuiltCoaster
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
     * Set manufacturer
     *
     * @param Manufacturer $manufacturer
     *
     * @return BuiltCoaster
     */
    public function setManufacturer(Manufacturer $manufacturer)
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * Get manufacturer
     *
     * @return Manufacturer
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set restraint
     *
     * @param Restraint $restraint
     *
     * @return BuiltCoaster
     */
    public function setRestraint(Restraint $restraint)
    {
        $this->restraint = $restraint;

        return $this;
    }

    /**
     * Get restraint
     *
     * @return Restraint
     */
    public function getRestraint()
    {
        return $this->restraint;
    }

    /**
     * Add launch
     *
     * @param Launch $launch
     *
     * @return BuiltCoaster
     */
    public function addLaunch(Launch $launch)
    {
        $this->launchs[] = $launch;

        return $this;
    }

    /**
     * Remove launch
     *
     * @param Launch $launch
     */
    public function removeLaunch(Launch $launch)
    {
        $this->launchs->removeElement($launch);
    }

    /**
     * Get launchs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLaunchs()
    {
        return $this->launchs;
    }

    /**
     * Add type
     *
     * @param Type $type
     *
     * @return BuiltCoaster
     */
    public function addType(Type $type)
    {
        $this->types[] = $type;

        return $this;
    }

    /**
     * Remove type
     *
     * @param Type $type
     */
    public function removeType(Type $type)
    {
        $this->types->removeElement($type);
    }

    /**
     * Get types
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Set duration
     *
     * @param \DateTime $duration
     *
     * @return BuiltCoaster
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Get duration
     *
     * @return \DateTime
     */
    public function getDuration()
    {
        return $this->duration;
    }


    /**
     * Set isKiddie
     *
     * @param boolean $isKiddie
     *
     * @return BuiltCoaster
     */
    public function setIsKiddie($isKiddie)
    {
        $this->isKiddie = $isKiddie;

        return $this;
    }

    /**
     * Get isKiddie
     *
     * @return boolean
     */
    public function getIsKiddie()
    {
        return $this->isKiddie;
    }
}
