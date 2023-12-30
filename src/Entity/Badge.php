<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Badge.
 */
#[ORM\Table(name: 'badge')]
#[ORM\Entity(repositoryClass: BadgeRepository::class)]
class Badge
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private ?string $filenameFr = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private ?string $filenameEn = null;

    /** @var Collection<\App\Entity\User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'badges')]
    #[ORM\JoinColumn]
    private \Doctrine\Common\Collections\Collection $users;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type.
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
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set filenameFr.
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
     * Get filenameFr.
     *
     * @return string
     */
    public function getFilenameFr()
    {
        return $this->filenameFr;
    }

    /**
     * Set filenameEn.
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
     * Get filenameEn.
     *
     * @return string
     */
    public function getFilenameEn()
    {
        return $this->filenameEn;
    }

    /** Constructor */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add user.
     *
     * @return Badge
     */
    public function addUser(User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /** Remove user */
    public function removeUser(User $user): void
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Set name.
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
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getFilename($locale = 'en')
    {
        $method = sprintf('getFilename%s', ucfirst((string) $locale));

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->getFilenameEn();
    }
}
