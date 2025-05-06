<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ReviewReport;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ReviewReport::class)]
class ReviewReportListener
{
    public function __construct(
        private readonly ChatterInterface $chatter,
    ) {
    }

    /** After persist: send Discord notification */
    public function postPersist(ReviewReport $reviewReport, PostPersistEventArgs $event): void
    {
        $discordOptions = (new DiscordOptions())
            ->addEmbed(
                (new DiscordEmbed())
                    ->title('Nouveau signalement de note ou d\'avis')
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Coaster')
                            ->value($reviewReport->getReview()->getCoaster()->getName())
                            ->inline(false)
                    )
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Reason')
                            ->value(ucfirst($reviewReport->getReason()))
                            ->inline(false)
                    )
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Reported content')
                            ->value($reviewReport->getReview()->getReview() ? mb_substr($reviewReport->getReview()->getReview(), 0, 1000, 'UTF-8') : '')
                            ->inline(false)
                    )
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Rating')
                            ->value((string) $reviewReport->getReview()->getValue())
                            ->inline(false)
                    )
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Reported by')
                            ->value($reviewReport->getUser()->getDisplayName())
                            ->inline(false)
                    )
            );

        $this->chatter->send(
            (new ChatMessage(''))->transport('discord_report')->options($discordOptions)
        );
    }
}
