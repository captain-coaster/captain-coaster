<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MainTagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * MainTag.
 */
#[ORM\Entity(repositoryClass: MainTagRepository::class)]
class MainTag
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Coaster::class, inversedBy: 'mainTags')]
    private ?Coaster $coaster = null;

    /** @var Tag */
    #[ORM\ManyToOne(targetEntity: Tag::class)]
    private $tag;

    #[ORM\Column(name: 'rank', type: Types::INTEGER)]
    private ?int $rank = null;

    /** Get id */
    public function getId(): int
    {
        return $this->id;
    }

    /** Set coaster */
    public function setCoaster(Coaster $coaster): self
    {
        $this->coaster = $coaster;

        return $this;
    }

    /** Get coaster */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * Set tag.
     *
     * @param Tag $tag
     *
     * @return MainTag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Get tag.
     *
     * @return Tag
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Set rank.
     *
     * @param int $rank
     *
     * @return MainTag
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank.
     *
     * @return int
     */
    public function getRank()
    {
        return $this->rank;
    }
}
