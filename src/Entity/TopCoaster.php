<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TopCoaster - ranked coaster in a top
 *
 * @ORM\Table(name="liste_coaster")
 * @ORM\Entity(repositoryClass="App\Repository\TopCoasterRepository")
 */
class TopCoaster
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
     * @var int
     *
     * @ORM\Column(name="position", type="integer", nullable=true)
     */
    private $position;

    /**
     * @var Top
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Top", inversedBy="topCoasters")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $top;

    /**
     * @var Coaster
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Coaster", fetch="EAGER")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $coaster;

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->coaster . ' (' . $this->position . ')';
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
     * Set position
     *
     * @param integer $position
     *
     * @return TopCoaster
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set top
     *
     * @param Top $top
     *
     * @return TopCoaster
     */
    public function setTop(Top $top)
    {
        $this->top = $top;

        return $this;
    }

    /**
     * Get top
     *
     * @return Top
     */
    public function getTop()
    {
        return $this->top;
    }

    /**
     * Set coaster
     *
     * @param Coaster $coaster
     *
     * @return TopCoaster
     */
    public function setCoaster(Coaster $coaster)
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     *
     * @return Coaster
     */
    public function getCoaster()
    {
        return $this->coaster;
    }
}
