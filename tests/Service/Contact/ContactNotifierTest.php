<?php

declare(strict_types=1);

namespace App\Tests\Service\Contact;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Enum\ContactTopic;
use App\Service\Contact\ContactMessage;
use App\Service\Contact\ContactNotifier;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ContactNotifierTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private ChatterInterface&MockObject $chatter;
    private ContactNotifier $notifier;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->chatter = $this->createMock(ChatterInterface::class);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route): string => 'https://site.test/'.$route);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('https://site.test/team/edit');

        $this->notifier = new ContactNotifier($this->mailer, $this->chatter, $urlGenerator, $adminUrlGenerator, 'team@site.test');
    }

    public function testADataErrorNamesTheCoasterAndLinksToItsPages(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Taron');
        $coaster->setPark(new Park()->setName('Phantasialand'));

        $this->mailer->expects($this->once())->method('send')->with($this->callback(static function (TemplatedEmail $email): bool {
            self::assertSame('[Data error] Taron, Phantasialand', $email->getSubject());
            self::assertSame('team@site.test', $email->getTo()[0]->getAddress());
            self::assertSame('lucas@site.test', $email->getReplyTo()[0]->getAddress());
            self::assertSame(['page' => 'https://site.test/show_coaster', 'admin' => 'https://site.test/team/edit'], $email->getContext()['links']);

            return true;
        }));

        $this->chatter->expects($this->once())->method('send')->with($this->callback(static function (ChatMessage $chat): bool {
            $embed = $chat->getOptions()?->toArray()['embeds'][0] ?? [];
            self::assertSame('discord_notif', $chat->getTransport());
            self::assertSame('Data error: Taron, Phantasialand', $embed['title']);
            self::assertSame('The restraint is a lap bar.', $embed['description']);
            self::assertSame(ContactTopic::Data->color(), $embed['color']);
            self::assertContains('[Open](https://site.test/show_coaster) · [Admin](https://site.test/team/edit)', array_column($embed['fields'], 'value'));

            return true;
        }));

        $this->notifier->send(new ContactMessage(
            topic: ContactTopic::Data,
            message: 'The restraint is a lap bar.',
            name: 'Lucas',
            email: 'lucas@site.test',
            locale: 'fr',
            coaster: $coaster,
        ));
    }

    public function testAGuestWithoutEmailGetsNoReplyToAndTheirNameDrawsNoLink(): void
    {
        $this->mailer->expects($this->once())->method('send')->with($this->callback(static function (TemplatedEmail $email): bool {
            self::assertSame('[Other] Partnership', $email->getSubject());
            self::assertSame([], $email->getReplyTo());
            self::assertSame([], $email->getContext()['links']);

            return true;
        }));

        $this->chatter->expects($this->once())->method('send')->with($this->callback(static function (ChatMessage $chat): bool {
            $fields = $chat->getOptions()?->toArray()['embeds'][0]['fields'] ?? [];
            self::assertContains('\\[Ana\\]\\(https://evil.test\\) (guest)', array_column($fields, 'value'));
            self::assertContains('No email', array_column($fields, 'value'));

            return true;
        }));

        $this->notifier->send(new ContactMessage(
            topic: ContactTopic::Other,
            message: 'Hello',
            name: '[Ana](https://evil.test)',
            email: null,
            locale: 'en',
            subject: 'Partnership',
        ));
    }

    public function testAMissingCoasterReportCarriesTheSearch(): void
    {
        $this->mailer->expects($this->once())->method('send')->with($this->callback(static function (TemplatedEmail $email): bool {
            self::assertSame('[Missing coaster or park] "Voltron Nevera"', $email->getSubject());

            return true;
        }));

        $this->chatter->expects($this->once())->method('send')->with($this->callback(static function (ChatMessage $chat): bool {
            $fields = $chat->getOptions()?->toArray()['embeds'][0]['fields'] ?? [];
            self::assertContains('Voltron Nevera', array_column($fields, 'value'));

            return true;
        }));

        $this->notifier->send(new ContactMessage(
            topic: ContactTopic::Missing,
            message: 'Opened in 2024 at Europa-Park.',
            name: 'Ana',
            email: null,
            locale: 'de',
            searchQuery: 'Voltron Nevera',
        ));
    }
}
