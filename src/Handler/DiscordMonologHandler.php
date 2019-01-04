<?php

namespace App\Handler;

use App\Service\DiscordService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Monolog Handler to log into a discord channel
 */
class DiscordMonologHandler extends AbstractProcessingHandler
{
    /**
     * @var DiscordService
     */
    protected $discordService;

    /**
     * DiscordMonologHandler constructor.
     * @param DiscordService $discordService
     */
    public function __construct(DiscordService $discordService)
    {
        parent::__construct(Logger::CRITICAL, true);

        $this->discordService = $discordService;
    }

    /**
     * @param array $record
     * @throws \Exception
     */
    public function write(array $record)
    {
        $this->discordService->log(
            sprintf('%s: `%s`', Logger::getLevelName($record['level']), $record['message'])
        );
    }
}
