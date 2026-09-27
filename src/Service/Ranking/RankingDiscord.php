<?php

declare(strict_types=1);

namespace App\Service\Ranking;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Posts the ranking runs to the team's Discord log channel.
 */
class RankingDiscord
{
    private const int MAX_LENGTH = 2000;
    // Coasters listed by name in a summary line, the others counted
    private const int LISTED = 15;

    public function __construct(private readonly ChatterInterface $chatter)
    {
    }

    /** @param list<string> $lines split into as many messages as Discord's length limit needs */
    public function send(array $lines): void
    {
        $message = '';
        foreach ($lines as $line) {
            if ('' !== $message && \strlen($message) + \strlen($line) + 1 > self::MAX_LENGTH) {
                $this->post($message);
                $message = '';
                // Discord's rate limit
                sleep(2);
            }
            $message .= $line."\n";
        }

        if ('' !== $message) {
            $this->post($message);
        }
    }

    /**
     * The report's summary lines.
     *
     * @param array<int, string> $names coaster id => display name
     *
     * @return list<string>
     */
    public static function summary(RankingReport $report, array $names): array
    {
        $name = static fn (int $id): string => $names[$id] ?? '#'.$id;
        $lines = [$report->top10Changed() ? '**Top 10** ⚡ changed' : '**Top 10** unchanged'];
        foreach ($report->top10 as [$coaster, $rank, $previous]) {
            $lines[] = \sprintf('%d. %s %s', $rank, $name($coaster), match (true) {
                null === $previous => '🆕',
                $previous > $rank => '↑'.($previous - $rank),
                $previous < $rank => '↓'.($rank - $previous),
                default => '=',
            });
        }
        $lines[] = '';
        $lines[] = \sprintf('Ranked coasters: %d → %d', $report->rankedBefore, $report->rankedNow);

        $list = static function (array $ranks, string $format) use ($name): string {
            $items = [];
            foreach (\array_slice($ranks, 0, self::LISTED, true) as $coaster => $rank) {
                $items[] = \sprintf($format, $rank, $name($coaster));
            }

            return implode(', ', $items).(\count($ranks) > self::LISTED ? \sprintf(' (+%d)', \count($ranks) - self::LISTED) : '');
        };

        if ($report->entered) {
            $lines[] = 'New: '.$list($report->entered, '#%d %s');
        }
        if ($report->left) {
            $lines[] = 'Left: '.$list($report->left, '%2$s (was #%1$d)');
        }
        foreach (\array_slice($report->bigMoves, 0, 10) as [$coaster, $previous, $rank]) {
            $lines[] = \sprintf('Big move: %s #%d → #%d', $name($coaster), $previous, $rank);
        }
        foreach ($report->anomalies as $anomaly) {
            $lines[] = '⚠️ '.$anomaly;
        }

        return $lines;
    }

    /** One message that never throws: a Discord outage must not fail a run, nor hide the error being reported. */
    public function alert(string $message): void
    {
        try {
            $this->post($message);
        } catch (\Throwable) {
        }
    }

    private function post(string $message): void
    {
        $this->chatter->send(new ChatMessage($message)->transport('discord_log'));
    }
}
