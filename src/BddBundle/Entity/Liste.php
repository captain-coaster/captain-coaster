<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use BddBundle\Validator\Constraints as CaptainConstraints;

/**
 * Liste
 *
 * @ORM\Table
 * @ORM\Entity(repositoryClass="BddBundle\Repository\ListeRepository")
 *
 * List is a reserved keyword :(
 */
class Liste
{
    CONST MAIN_LISTE = 'topcoasters';

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=false)
     */
    private $type;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\User", inversedBy="listes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var ListeCoaster
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\ListeCoaster", mappedBy="liste", cascade={"persist"})
     * @ORM\OrderBy({"position" = "ASC"})
     * @CaptainConstraints\UniqueCoaster
     */
    private $listeCoasters;

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
        $this->listeCoasters = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Liste
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
     * Set type
     *
     * @param string $type
     *
     * @return Liste
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
     * Set user
     *
     * @param \BddBundle\Entity\User $user
     *
     * @return Liste
     */
    public function setUser(\BddBundle\Entity\User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \BddBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add listeCoster
     *
     * @param \BddBundle\Entity\ListeCoaster $listeCoaster
     *
     * @return Liste
     */
    public function addListeCoaster(\BddBundle\Entity\ListeCoaster $listeCoaster)
    {
        $listeCoaster->setListe($this);

        $this->listeCoasters->add($listeCoaster);

        return $this;
    }

    /**
     * Remove listeCoaster
     *
     * @param \BddBundle\Entity\ListeCoaster $listeCoaster
     */
    public function removeListeCoaster(\BddBundle\Entity\ListeCoaster $listeCoaster)
    {
        $this->listeCoasters->removeElement($listeCoaster);
    }

    /**
     * Get listeCoasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getListeCoasters()
    {
        return $this->listeCoasters;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Liste
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
     * @return Liste
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
}
