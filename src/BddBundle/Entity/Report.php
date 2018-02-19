<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Report
 *
 * @ORM\Table(name="report")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\ReportRepository")
 */
class Report
{
    const STATUS_DRAFT = 'draft';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Park
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Park")
     * @ORM\JoinColumn(nullable=false)
     */
    private $park;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var string|null
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=true)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="language", type="string", length=255)
     */
    private $language;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="visitDate", type="datetime")
     */
    private $visitDate;

    /**
     * @var string|null
     *
     * @ORM\Column(name="content", type="text", nullable=true)
     */
    private $content;

    /**
     * @var int
     *
     * @ORM\Column(name="viewsNumber", type="integer")
     */
    private $viewsNumber = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    private $status = self::STATUS_DRAFT;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="creationDate", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $creationDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updateDate", type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $updateDate;


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
     * Set park
     *
     * @param \BddBundle\Entity\Park $park
     *
     * @return Report
     */
    public function setPark(Park $park)
    {
        $this->park = $park;

        return $this;
    }

    /**
     * Get park
     *
     * @return \BddBundle\Entity\Park
     */
    public function getPark()
    {
        return $this->park;
    }

    /**
     * Set user
     *
     * @param \BddBundle\Entity\User $user
     *
     * @return Report
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
     * Set title.
     *
     * @param string|null $title
     *
     * @return Report
     */
    public function setTitle($title = null)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set language.
     *
     * @param string $language
     *
     * @return Report
     */
    public function setLanguage($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set visitDate.
     *
     * @param \DateTime $visitDate
     *
     * @return Report
     */
    public function setVisitDate($visitDate)
    {
        $this->visitDate = $visitDate;

        return $this;
    }

    /**
     * Get visitDate.
     *
     * @return \DateTime
     */
    public function getVisitDate()
    {
        return $this->visitDate;
    }

    /**
     * Set content.
     *
     * @param string|null $content
     *
     * @return Report
     */
    public function setContent($content = null)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return string|null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set viewsNumber.
     *
     * @param int $viewsNumber
     *
     * @return Report
     */
    public function setViewsNumber($viewsNumber)
    {
        $this->viewsNumber = $viewsNumber;

        return $this;
    }

    /**
     * Get viewsNumber.
     *
     * @return int
     */
    public function getViewsNumber()
    {
        return $this->viewsNumber;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return Report
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set creationDate.
     *
     * @param \DateTime $creationDate
     *
     * @return Report
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    /**
     * Get creationDate.
     *
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Set updateDate.
     *
     * @param \DateTime $updateDate
     *
     * @return Report
     */
    public function setUpdateDate($updateDate)
    {
        $this->updateDate = $updateDate;

        return $this;
    }

    /**
     * Get updateDate.
     *
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->updateDate;
    }
}
