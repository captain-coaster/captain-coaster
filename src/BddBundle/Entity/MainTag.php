<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MainTag
 *
 * @ORM\Table
 * @ORM\Entity(repositoryClass="BddBundle\Repository\MainTagRepository")
 */
class MainTag
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
     * @var Coaster
     * @ORM\ManyToOne(targetEntity="Coaster")
     */
    private $coaster;

    /**
     * @var Tag
     * @ORM\ManyToOne(targetEntity="Tag")
     */
    private $tag;

    /**
     * @var int
     *
     * @ORM\Column(name="rank", type="integer", nullable=false)
     */
    private $rank;

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return MainTag
     */
    public function setCoaster(Coaster $coaster): MainTag
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     *
     * @return \BddBundle\Entity\Coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * Set tag
     *
     * @param \BddBundle\Entity\Tag $tag
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
     * @return \BddBundle\Entity\Tag
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
