<?php

namespace App\Entity;

use App\Validator\Constraints as CaptainConstraints;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Liste (not "list" because it's a reserved keyword)
 *
 * @ORM\Table
 * @ORM\Entity(repositoryClass="App\Repository\ListeRepository")
 */
class Liste
{
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
     * @ORM\Column(type="string", length=255, unique=false, nullable=false)
     */
    private $type;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="listes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var ListeCoaster
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ListeCoaster", mappedBy="liste", cascade={"persist", "remove"})
     * @ORM\OrderBy({"position" = "ASC"})
     * @CaptainConstraints\UniqueCoaster
     */
    private $listeCoasters;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $main = false;

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
        $this->listeCoasters = new ArrayCollection();
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
     * @param User $user
     *
     * @return Liste
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add listeCoster
     *
     * @param ListeCoaster $listeCoaster
     *
     * @return Liste
     */
    public function addListeCoaster(ListeCoaster $listeCoaster)
    {
        $listeCoaster->setListe($this);

        $this->listeCoasters->add($listeCoaster);

        return $this;
    }

    /**
     * Remove listeCoaster
     *
     * @param ListeCoaster $listeCoaster
     */
    public function removeListeCoaster(ListeCoaster $listeCoaster)
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
     * @param bool $main
     * @return Liste
     */
    public function setMain(bool $main): Liste
    {
        $this->main = $main;

        return $this;
    }

    /**
     * @return bool
     */
    public function isMain(): bool
    {
        return $this->main;
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
