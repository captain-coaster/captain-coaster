<?php

namespace App\Handler;

use App\Service\DiscordService;
use Exception;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Monolog Handler to log into a discord channel
 */
class DiscordMonologHandler extends AbstractProcessingHandler
{
    /**
     * @var DiscordService
     */
    protected DiscordService $discordService;

    /**
     * DiscordMonologHandler constructor.
     * @param DiscordService $discordService
     */
    public function __construct(DiscordService $discordService)
    {
        parent::__construct(Level::Critical, true);

        $this->discordService = $discordService;
    }

    /**
     * @throws Exception
     */
    public function write(LogRecord $record): void
    {
        $this->discordService->log(
            sprintf('%s: `%s`', Logger::toMonologLevel($record->level), $record->message)
        );
    }
}
