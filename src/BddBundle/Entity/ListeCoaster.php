<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ListeCoaster
 *
 * @ORM\Table()
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
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster", fetch="EAGER")
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
     * @param Liste $liste
     *
     * @return ListeCoaster
     */
    public function setListe(Liste $liste)
    {
        $this->liste = $liste;

        return $this;
    }

    /**
     * Get liste
     *
     * @return Liste
     */
    public function getListe()
    {
        return $this->liste;
    }

    /**
     * Set coaster
     *
     * @param Coaster $coaster
     *
     * @return ListeCoaster
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
