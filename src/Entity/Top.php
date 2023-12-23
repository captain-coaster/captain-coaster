<?php

namespace App\Entity;

use App\Validator\Constraints as CaptainConstraints;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Top (rename mysql table later)
 *
 * @ORM\Table(name="liste")
 * @ORM\Entity(repositoryClass="App\Repository\TopRepository")
 */
class Top
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
    private $type = 'top';

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="tops")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $user;

    /**
     * @var TopCoaster[]
     *
     * @ORM\OneToMany(targetEntity="TopCoaster", mappedBy="top", cascade={"persist", "remove"})
     * @ORM\OrderBy({"position" = "ASC"})
     * @CaptainConstraints\UniqueCoaster
     */
    private $topCoasters;

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
        $this->topCoasters = new ArrayCollection();
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
     * @return Top
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
     * @return Top
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
     * @return Top
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
     * Add topCoaster
     *
     * @param TopCoaster $topCoaster
     *
     * @return Top
     */
    public function addTopCoaster(TopCoaster $topCoaster)
    {
        $topCoaster->setTop($this);

        $this->topCoasters->add($topCoaster);

        return $this;
    }

    /**
     * Remove topCoaster
     *
     * @param TopCoaster $topCoaster
     */
    public function removeTopCoaster(TopCoaster $topCoaster)
    {
        $this->topCoasters->removeElement($topCoaster);
    }

    /**
     * @return TopCoaster[]|ArrayCollection
     */
    public function getTopCoasters()
    {
        return $this->topCoasters;
    }

    /**
     * @param bool $main
     * @return Top
     */
    public function setMain(bool $main): Top
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
     * @return Top
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
     * @return Top
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
