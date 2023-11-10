<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tag
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag implements \Stringable
{
    final public const PRO = 'pro';
    final public const CON = 'con';

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 255)]
    private ?string $type = null;

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type): self
    {
        $this->type = $type;

        return $this;
    }
}
