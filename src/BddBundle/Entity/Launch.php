<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Launch
 *
 * @ORM\Table(name="launch")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\LaunchRepository")
 */
class Launch
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
     * @var BuiltCoaster
     *
     * @ORM\ManyToMany(targetEntity="BuiltCoaster", mappedBy="launchs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $builtCoasters;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->builtCoasters = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
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
     * @return Launch
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
     * @return Launch
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
     * Add builtCoaster
     *
     * @param \BddBundle\Entity\BuiltCoaster $builtCoaster
     *
     * @return Launch
     */
    public function addBuiltCoaster(\BddBundle\Entity\BuiltCoaster $builtCoaster)
    {
        $this->builtCoasters[] = $builtCoaster;

        return $this;
    }

    /**
     * Remove builtCoaster
     *
     * @param \BddBundle\Entity\BuiltCoaster $builtCoaster
     */
    public function removeBuiltCoaster(\BddBundle\Entity\BuiltCoaster $builtCoaster)
    {
        $this->builtCoasters->removeElement($builtCoaster);
    }

    /**
     * Get builtCoasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBuiltCoasters()
    {
        return $this->builtCoasters;
    }
}
