<?php

namespace BddBundle\Service;

use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Sends notifications through a Discord bot
 */
class DiscordMonologHandler extends AbstractProcessingHandler
{
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
    protected $url;

    /**
     * DiscordMonologHandler constructor.
     * @param HttpClient $client
     * @param MessageFactory $factory
     * @param string $url
     */
    public function __construct(HttpClient $client, MessageFactory $factory, string $url)
    {
        parent::__construct(Logger::CRITICAL, true);

        $this->client = $client;
        $this->factory = $factory;
        $this->url = $url;
    }

    /**
     * @param array $record
     * @throws \Exception
     * @throws \Http\Client\Exception
     */
    public function write(array $record)
    {
        $body = json_encode(
            [
                'content' => sprintf(
                    '%s: `%s`',
                    Logger::getLevelName($record['level']),
                    $record['message']
                ),
            ]
        );

        $request = $this->factory->createRequest(
            'POST',
            $this->url,
            ['Content-Type' => 'application/json'],
            $body
        );

        $this->client->sendRequest($request);
    }
}
