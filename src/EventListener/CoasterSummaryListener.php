<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\CoasterSummary;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Listener for CoasterSummary entity lifecycle events.
 *
 * Handles cascade deletion of translations when an English summary is deleted.
 * Ensures that when the source English summary is removed, all associated
 * translations in other languages are also removed to prevent orphaned records.
 */
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: CoasterSummary::class)]
class CoasterSummaryListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Before removing a summary, cascade delete all translations if it's an English summary.
     *
     * When an English summary is deleted, all translations (fr, es, de) for the same
     * coaster are automatically deleted to prevent orphaned translations.
     *
     * Note: Feedback records are already cascade-deleted via entity configuration.
     */
    public function preRemove(CoasterSummary $summary, PreRemoveEventArgs $event): void
    {
        // Only cascade delete translations if the deleted summary is English
        if ('en' !== $summary->getLanguage()) {
            $this->logger->info('Non-English summary deleted, no cascade deletion needed', [
                'coaster' => $summary->getCoaster()?->getName(),
                'language' => $summary->getLanguage(),
            ]);

            return;
        }

        $coaster = $summary->getCoaster();
        if (!$coaster) {
            $this->logger->warning('Cannot cascade delete translations: coaster not found');

            return;
        }

        // Find all translations for this coaster (excluding English)
        $translations = $this->entityManager->getRepository(CoasterSummary::class)
            ->createQueryBuilder('cs')
            ->where('cs.coaster = :coaster')
            ->andWhere('cs.language != :english')
            ->setParameter('coaster', $coaster)
            ->setParameter('english', 'en')
            ->getQuery()
            ->getResult();

        if (empty($translations)) {
            $this->logger->info('No translations found to cascade delete', [
                'coaster' => $coaster->getName(),
            ]);

            return;
        }

        // Delete all translations
        foreach ($translations as $translation) {
            $this->logger->info('Cascade deleting translation', [
                'coaster' => $coaster->getName(),
                'language' => $translation->getLanguage(),
            ]);

            $this->entityManager->remove($translation);
        }

        // Flush is not needed here - it will be handled by the main removal operation
        $this->logger->info('Cascade deletion completed', [
            'coaster' => $coaster->getName(),
            'translations_deleted' => \count($translations),
        ]);
    }
}
