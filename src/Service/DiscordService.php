<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends notifications through a Discord bot.
 */
class DiscordService
{
    final public const BASE_URL = 'https://discordapp.com/api/webhooks';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly array $channels,
    ) {
    }

    public function log(string $message): void
    {
        $this->sendMessage($message, $this->channels['log']);
    }

    public function notify(string $message): void
    {
        $this->sendMessage($message, $this->channels['notify']);
    }

    private function sendMessage(string $message, string $channel): void
    {
        try {
            $request = $this->client->request('POST', sprintf('%s/%s', self::BASE_URL, $channel), [
                'json' => ['content' => $message],
            ]);
        } catch (TransportExceptionInterface) {
        }
    }
}
