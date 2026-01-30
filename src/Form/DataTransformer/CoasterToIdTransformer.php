<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Coaster;
use App\Repository\CoasterRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<Coaster|null, int|null>
 */
class CoasterToIdTransformer implements DataTransformerInterface
{
    public function __construct(private readonly CoasterRepository $coasterRepository)
    {
    }

    /** Transforms an object (Coaster) to an int (id). */
    public function transform($coaster): ?int
    {
        if (!$coaster instanceof Coaster) {
            return null;
        }

        return $coaster->getId();
    }

    /** Transforms an int (id) to an object (Coaster). */
    public function reverseTransform($coasterId): ?Coaster
    {
        if (!$coasterId) {
            return null;
        }

        $coaster = $this->coasterRepository->find($coasterId);

        if (null === $coaster) {
            throw new TransformationFailedException(\sprintf('A coaster with id "%d" does not exist!', $coasterId));
        }

        return $coaster;
    }
}
