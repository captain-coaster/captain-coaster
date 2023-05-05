<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tag
 */
#[ORM\Entity(repositoryClass: \App\Repository\TagRepository::class)]
class Tag implements \Stringable
{
    final public const PRO = 'pro';
    final public const CON = 'con';

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $name = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'type', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $type = null;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name): Tag
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type): Tag
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     */
    public function getType(): string
    {
        return $this->type;
    }
}
