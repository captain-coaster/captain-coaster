<?php

namespace BddBundle\Form\DataTransformer;

use BddBundle\Entity\Coaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CoasterToIdTransformer implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Transforms an object (Coaster) to a string (id).
     *
     * @param  Coaster|null $coaster
     * @return string
     */
    public function transform($coaster)
    {
        if (null === $coaster) {
            return '';
        }

        return $coaster->getId();
    }

    /**
     * Transforms a string (id) to an object (Coaster).
     *
     * @param  string $coasterId
     * @return Coaster|null
     * @throws TransformationFailedException if object (issue) is not found.
     */
    public function reverseTransform($coasterId)
    {
        // no issue number? It's optional, so that's ok
        if (!$coasterId) {
            return;
        }

        $issue = $this->em
            ->getRepository(Coaster::class)
            ->find($coasterId);

        if (null === $issue) {
            throw new TransformationFailedException(
                sprintf(
                    'A coaster with id "%s" does not exist!',
                    $coasterId
                )
            );
        }

        return $issue;
    }
}