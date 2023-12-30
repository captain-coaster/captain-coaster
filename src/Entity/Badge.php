<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'badges')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType($type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setFilenameFr($filenameFr): static
    {
        $this->filenameFr = $filenameFr;

        return $this;
    }

    public function getFilenameFr(): ?string
    {
        return $this->filenameFr;
    }

    public function setFilenameEn($filenameEn): static
    {
        $this->filenameEn = $filenameEn;

        return $this;
    }

    public function getFilenameEn(): ?string
    {
        return $this->filenameEn;
    }

    public function addUser(User $user): static
    {
        $this->users[] = $user;

        return $this;
    }

    public function removeUser(User $user): void
    {
        $this->users->removeElement($user);
    }

    public function getUsers(): ArrayCollection|Collection
    {
        return $this->users;
    }

    public function setName($name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getFilename(string $locale = 'en'): string
    {
        $method = sprintf('getFilename%s', ucfirst((string) $locale));

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->getFilenameEn();
    }
}
