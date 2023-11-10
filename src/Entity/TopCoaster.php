<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TopCoaster - ranked coaster in a top
 */
#[ORM\Table(name: 'liste_coaster')]
#[ORM\Entity(repositoryClass: \App\Repository\TopCoasterRepository::class)]
class TopCoaster implements \Stringable
{
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'position', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $position = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Top::class, inversedBy: 'topCoasters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\Top $top = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Coaster::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\Coaster $coaster = null;

    public function __toString(): string
    {
        return (string)$this->coaster . ' (' . $this->position . ')';
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
