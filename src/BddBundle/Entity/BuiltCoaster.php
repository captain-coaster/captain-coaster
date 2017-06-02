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
     * @ORM\Column(name="gForce", type="decimal", precision=3, scale=2, nullable=true)
     */
    private $gForce;

    /**
     * @var Coaster
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="builtCoaster")
     */
    private $coasters;


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
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return BuiltCoaster
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
}
