<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LikeReport
 *
 * @ORM\Table(name="like_report")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\LikeReportRepository")
 */
class LikeReport
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
     * @var Report
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Report", inversedBy="likes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $report;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\User", inversedBy="reportLikes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Report $report
     * @return LikeReport
     */
    public function setReport(Report $report): LikeReport
    {
        $this->report = $report;

        return $this;
    }

    /**
     * @return Report
     */
    public function getReport(): Report
    {
        return $this->report;
    }

    /**
     * @param User $user
     * @return LikeReport
     */
    public function setUser(User $user): LikeReport
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }
}
