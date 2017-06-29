<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Coaster
 *
 * @ORM\Table(name="rating_coaster")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\RatingCoasterRepository")
 */
class RatingCoaster
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
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster", inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $coaster;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\User", inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var float
     *
     * @ORM\Column(name="rating", type="float", precision=2, scale=1, nullable=false)
     */
    private $value;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return RatingCoaster
     */
    public function setCoaster(Coaster $coaster)
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

    /**
     * Set user
     *
     * @param \BddBundle\Entity\User $user
     *
     * @return RatingCoaster
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \BddBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return RatingCoaster
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
