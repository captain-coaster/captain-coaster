<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ImageReport;
use App\Service\PictureUrlSigner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordMediaEmbedObject;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Discord notification for a GenAI-flagged image -- replaces the old unconditional
 * per-upload post in ImageListener::postPersist(). Fires only for images that were
 * actually flagged, turning the channel back into a live worklist.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ImageReport::class)]
class ImageReportListener
{
    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly PictureUrlSigner $pictureUrlSigner,
    ) {
    }

    /** After persist: send Discord notification */
    public function postPersist(ImageReport $imageReport, PostPersistEventArgs $event): void
    {
        $image = $imageReport->getImage();

        $embed = new DiscordEmbed()
            ->title('Photo flagged for review')
            ->addField(
                new DiscordFieldEmbedObject()
                    ->name('Coaster')
                    ->value($imageReport->getCoasterName() ?? '(unknown)')
                    ->inline(false)
            )
            ->addField(
                new DiscordFieldEmbedObject()
                    ->name('Categories')
                    ->value(implode(', ', $imageReport->getCategories()))
                    ->inline(false)
            )
            ->addField(
                new DiscordFieldEmbedObject()
                    ->name('AI explanation')
                    ->value($imageReport->getAiExplanation() ?? '(none)')
                    ->inline(false)
            );

        if (null !== $image) {
            $imageUrl = $this->pictureUrlSigner->sign($image->getFilename(), 1440, 1440, 'jpg');
            $embed->url($imageUrl)->thumbnail(new DiscordMediaEmbedObject()->url($imageUrl));
        }

        $this->chatter->send(
            new ChatMessage('')->transport('discord_picture')->options(new DiscordOptions()->addEmbed($embed))
        );
    }
}
