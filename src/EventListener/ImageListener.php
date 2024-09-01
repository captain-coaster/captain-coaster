<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Image;
use App\Service\ImageManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordMediaEmbedObject;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Image::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Image::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Image::class)]
class ImageListener
{
    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ChatterInterface $chatter,
        private string $picturesHostname
    ) {
    }

    /** Before persist: upload file to storage (S3) */
    public function prePersist(Image $image, PrePersistEventArgs $event): void
    {
        // only upload new files
        if ($image->getFile() instanceof UploadedFile) {
            $fileName = $this->imageManager->upload($image);
            $image->setFilename($fileName);
        }
    }

    /** After persist: send Discord notification */
    public function postPersist(Image $image, PostPersistEventArgs $event): void
    {
        $discordOptions = (new DiscordOptions())
            ->addEmbed(
                (new DiscordEmbed())
                    ->title($image->getCoaster()->getName().' - '.$image->getCoaster()->getPark()->getName())
                    ->image((new DiscordMediaEmbedObject())
                        ->url($this->picturesHostname.'/1440x1440/'.$image->getFilename()))
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Uploader')
                            ->value($image->getUploader()->getDisplayName())
                            ->inline(true)
                    )
                    ->addField(
                        (new DiscordFieldEmbedObject())
                            ->name('Credit')
                            ->value($image->getCredit())
                            ->inline(true)
                    )
            );

        $this->chatter->send(
            (new ChatMessage(''))->transport('discord_picture')->options($discordOptions)
        );
    }

    /** Before remove: remove image file on storage (S3) */
    public function preRemove(Image $image, PreRemoveEventArgs $args): void
    {
        $this->imageManager->remove($image->getFilename());
    }

    /** After remove: update main images, remove cache */
    public function postRemove(Image $image, PostRemoveEventArgs $args): void
    {
        $this->imageManager->setMainImages();
        $this->imageManager->removeCache($image);
    }

    /** After update (enabled set to 1 is an update): update main images */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $this->imageManager->setMainImages();
    }
}
