<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MainTag
 */
#[ORM\Entity(repositoryClass: \App\Repository\MainTagRepository::class)]
class MainTag
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'Coaster', inversedBy: 'mainTags')]
    private ?\App\Entity\Coaster $coaster = null;

    /**
     * @var Tag
     */
    #[ORM\ManyToOne(targetEntity: 'Tag')]
    private $tag;

    /**
     * @var int
     */
    #[ORM\Column(name: 'rank', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $rank = null;

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set coaster
     *
     *
     */
    public function setCoaster(Coaster $coaster): MainTag
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * Set tag
     *
     * @param \App\Entity\Tag $tag
     *
     * @return MainTag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Get tag
     *
     * @return \App\Entity\Tag
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Set rank
     *
     * @param integer $rank
     *
     * @return MainTag
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank
     *
     * @return integer
     */
    public function getRank()
    {
        return $this->rank;
    }
}
