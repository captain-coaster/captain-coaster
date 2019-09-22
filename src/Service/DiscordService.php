<?php

namespace App\Service;

use Http\Client\HttpClient;
use Http\Message\MessageFactory;

/**
 * Sends notifications through a Discord bot
 *
 * Class DiscordService
 * @package App\Service
 */
class DiscordService
{
    const BASE_URL = 'https://discordapp.com/api/webhooks';

    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $factory;

    /**
     * @var string
     */
    protected $channels;

    /**
     * DiscordService constructor.
     * @param HttpClient $client
     * @param MessageFactory $factory
     * @param array $channels
     */
    public function __construct(HttpClient $client, MessageFactory $factory, array $channels)
    {
        $this->client = $client;
        $this->factory = $factory;
        $this->channels = $channels;
    }

    public function log(string $message)
    {
        $this->sendMessage($message, $this->channels['log']);
    }

    public function notify(string $message)
    {
        $this->sendMessage($message, $this->channels['notify']);
    }

    /**
     * @param string $message
     * @param string $channel
     */
    private function sendMessage(string $message, string $channel)
    {
        try {
            $request = $this->factory->createRequest(
                'POST',
                sprintf('%s/%s', self::BASE_URL, $channel),
                ['Content-Type' => 'application/json'],
                json_encode(['content' => $message])
            );

            $this->client->sendRequest($request);
        } catch (\Http\Client\Exception | \Exception $e) {
            // do nothing
        }
    }
}
