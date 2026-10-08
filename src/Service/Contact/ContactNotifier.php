<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Controller\Admin\CoasterCrudController;
use App\Controller\Admin\ParkCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends a contact message to the team: an email they can reply to, and a Discord embed.
 */
class ContactNotifier
{
    /** Discord caps an embed description at 4096 characters; the email carries the full text. */
    private const int DISCORD_MESSAGE_LENGTH = 1500;
    private const int DISCORD_FIELD_LENGTH = 1024;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ChatterInterface $chatter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        #[Autowire(param: 'app_contact_mail_to')]
        private readonly string $mailTo,
    ) {
    }

    public function send(ContactMessage $message): void
    {
        $links = $this->links($message);

        $email = new TemplatedEmail()
            ->to($this->mailTo)
            ->subject('['.$message->topic->teamLabel().'] '.($message->about() ?? $message->name))
            ->htmlTemplate('Contact/email.html.twig')
            ->context(['contact' => $message, 'links' => $links]);

        if (null !== $message->email) {
            $email->replyTo($message->email);
        }

        $this->mailer->send($email);

        $this->chatter->send(
            new ChatMessage('')->transport('discord_notif')->options(new DiscordOptions()->addEmbed($this->embed($message, $links)))
        );
    }

    /** @return array{page?: string, admin?: string, member?: string} */
    private function links(ContactMessage $message): array
    {
        $links = [];
        $locale = ['_locale' => $message->locale];

        if (null !== $message->coaster) {
            $links['page'] = $this->urlGenerator->generate('show_coaster', $locale + ['id' => $message->coaster->getId(), 'slug' => $message->coaster->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
            $links['admin'] = $this->adminUrl(CoasterCrudController::class, $message->coaster->getId());
        } elseif (null !== $message->park) {
            $links['page'] = $this->urlGenerator->generate('park_show', $locale + ['id' => $message->park->getId(), 'slug' => $message->park->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
            $links['admin'] = $this->adminUrl(ParkCrudController::class, $message->park->getId());
        }

        if (null !== $message->user) {
            $links['member'] = $this->urlGenerator->generate('user_show', $locale + ['slug' => $message->user->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $links;
    }

    /** @param class-string $controller */
    private function adminUrl(string $controller, ?int $id): string
    {
        return $this->adminUrlGenerator->setController($controller)->setAction(Action::EDIT)->setEntityId($id)->generateUrl();
    }

    /** @param array{page?: string, admin?: string, member?: string} $links */
    private function embed(ContactMessage $message, array $links): DiscordEmbed
    {
        // A name is free text: escaped, it can't draw a link or formatting in the team's channel.
        $name = preg_replace('/[\\\\`*_~|>\[\]()]/', '\\\\$0', $message->name) ?? '';

        $embed = new DiscordEmbed()
            ->title(mb_substr($message->title(), 0, 256))
            ->description(mb_strimwidth($message->message, 0, self::DISCORD_MESSAGE_LENGTH, '…'))
            ->color($message->topic->color())
            ->addField($this->field('From', isset($links['member']) ? \sprintf('[%s](%s)', $name, $links['member']) : $name.' (guest)'))
            ->addField($this->field('Reply to', $message->email ?? 'No email'));

        if (isset($links['page'], $links['admin'])) {
            $embed->addField($this->field('Page', \sprintf('[Open](%s) · [Admin](%s)', $links['page'], $links['admin'])));
        }
        if (null !== $message->searchQuery) {
            $embed->addField($this->field('Search', $message->searchQuery));
        }

        return $embed->addField($this->field('Language', $message->locale));
    }

    private function field(string $name, string $value): DiscordFieldEmbedObject
    {
        return new DiscordFieldEmbedObject()->name($name)->value(mb_substr($value, 0, self::DISCORD_FIELD_LENGTH))->inline(true);
    }
}
