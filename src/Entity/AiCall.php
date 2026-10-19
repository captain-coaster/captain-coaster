<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiCallRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One Bedrock call and what it cost, failed calls included. Rows are written by
 * AiCallRepository::record() only.
 *
 * subjectId is what the call was about, read according to task: the coaster for a summary
 * (with language), the RiddenCoaster for a review moderation, the Image for an image
 * moderation. Null for summary previews and ad-hoc calibration texts.
 */
#[ORM\Table(name: 'ai_call')]
#[ORM\Index(name: 'idx_ai_call_task_created_at', columns: ['task', 'created_at'])]
#[ORM\Index(name: 'idx_ai_call_task_subject', columns: ['task', 'subject_id'])]
#[ORM\Entity(repositoryClass: AiCallRepository::class, readOnly: true)]
class AiCall
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $task;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $model;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $subjectId = null;

    #[ORM\Column(type: Types::STRING, length: 2, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $success;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $inputTokens = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $outputTokens = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $cacheReadTokens = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $cacheWriteTokens = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $costUsd = '0';

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $latencyMs = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;
}
