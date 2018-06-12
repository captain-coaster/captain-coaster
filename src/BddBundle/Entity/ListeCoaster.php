<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ListeCoaster
 *
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_coaster_liste", columns={"liste_id", "coaster_id"})})
 * @ORM\Entity(repositoryClass="BddBundle\Repository\ListeCoasterRepository")
 */
class ListeCoaster
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
     * @var Liste
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Liste", inversedBy="listeCoasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $liste;

    /**
     * @var Coaster
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster")
     * @ORM\JoinColumn(nullable=false)
     */
    private $coaster;


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
     * @return ListeCoaster
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
     * Set liste
     *
     * @param \BddBundle\Entity\Liste $liste
     *
     * @return ListeCoaster
     */
    public function setListe(\BddBundle\Entity\Liste $liste)
    {
        $this->liste = $liste;

        return $this;
    }

    /**
     * Get liste
     *
     * @return \BddBundle\Entity\Liste
     */
    public function getListe()
    {
        return $this->liste;
    }

    /**
     * Set coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return ListeCoaster
     */
    public function setCoaster(\BddBundle\Entity\Coaster $coaster)
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     *
     * @return \BddBundle\Entity\Coaster
     */
    public function getCoaster()
    {
        return $this->coaster;
    }
}
