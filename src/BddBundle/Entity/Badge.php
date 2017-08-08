<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Badge
 *
 * @ORM\Table(name="badge")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\BadgeRepository")
 */
class Badge
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
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $filenameFr;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $filenameEn;

    /**
     * @var User
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\User", mappedBy="badges")
     * @ORM\JoinColumn(nullable=true)
     */
    private $users;

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
     * Set type
     *
     * @param string $type
     *
     * @return Badge
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
     * Set filenameFr
     *
     * @param string $filenameFr
     *
     * @return Badge
     */
    public function setFilenameFr($filenameFr)
    {
        $this->filenameFr = $filenameFr;

        return $this;
    }

    /**
     * Get filenameFr
     *
     * @return string
     */
    public function getFilenameFr()
    {
        return $this->filenameFr;
    }

    /**
     * Set filenameEn
     *
     * @param string $filenameEn
     *
     * @return Badge
     */
    public function setFilenameEn($filenameEn)
    {
        $this->filenameEn = $filenameEn;

        return $this;
    }

    /**
     * Get filenameEn
     *
     * @return string
     */
    public function getFilenameEn()
    {
        return $this->filenameEn;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add user
     *
     * @param \BddBundle\Entity\User $user
     *
     * @return Badge
     */
    public function addUser(\BddBundle\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \BddBundle\Entity\User $user
     */
    public function removeUser(\BddBundle\Entity\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Badge
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

    public function getFilename($locale = 'en')
    {
        $method = sprintf('getFilename%s', ucfirst($locale));

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->getFilenameEn();
    }
}
