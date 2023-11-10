<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Coaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CoasterToIdTransformer implements DataTransformerInterface
{
    public $repository;

    public function __construct(private readonly EntityManagerInterface $em, private readonly \App\Repository\CoasterRepository $coasterRepository)
    {
    }

    /**
     * Transforms an object (Coaster) to a string (id).
     *
     * @param Coaster|null $coaster
     *
     * @return string
     */
    public function transform($coaster)
    {
        if (!$coaster instanceof \App\Entity\Coaster) {
            return '';
        }

        return $coaster->getId();
    }

    /**
     * Transforms a string (id) to an object (Coaster).
     *
     * @param string $coasterId
     *
     * @return Coaster|null
     *
     * @throws TransformationFailedException if object (issue) is not found
     */
    public function reverseTransform($coasterId)
    {
        // no issue number? It's optional, so that's ok
        if ('' === $coasterId || '0' === $coasterId) {
            return;
        }

        $issue = $this->repository
            ->find($coasterId);

        if (null === $issue) {
            throw new TransformationFailedException(sprintf('A coaster with id "%s" does not exist!', $coasterId));
        }

        return $issue;
    }
}
