<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Restraint
 *
 * @ORM\Table(name="restraint")
 * @ORM\Entity(repositoryClass="App\Repository\RestraintRepository")
 */
class Restraint
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
     * @ORM\Column(name="name", type="string", length=255, unique=true, nullable=false)
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
     * @var Coaster[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Coaster", mappedBy="restraint")
     */
    private $coasters;

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return Restraint
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $slug
     * @return Restraint
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param Coaster $coaster
     * @return Restraint
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    /**
     * @param Coaster $coaster
     */
    public function removeCoaster(Coaster $coaster)
    {
        $this->coasters->removeElement($coaster);
    }

    /**
     * @return Coaster[]|ArrayCollection
     */
    public function getCoasters()
    {
        return $this->coasters;
    }
}
