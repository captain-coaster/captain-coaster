<?php

declare(strict_types=1);

namespace App\Entity;

use App\Tooling\Translation\Model\TranslatableInterface;
use App\Tooling\Translation\Model\TranslatableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Continent.
 */
#[ORM\Table(name: 'continent')]
#[ORM\Entity]
class Continent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    private ?string $slug = null;

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->getTranslation()->getName() ?? $this->name ?? 'Undefined translation';
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
