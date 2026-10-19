<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiCall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiCall>
 */
class AiCallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiCall::class);
    }

    /**
     * A direct insert, outside the unit of work: the caller's own flush stays its own, and a
     * call is recorded even when the caller later fails.
     *
     * @param array{task: string, model: string, subject_id: ?int, language: ?string, success: bool, error_code: ?string, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, cost_usd: float, latency_ms: ?int} $call
     */
    public function record(array $call): void
    {
        $this->getEntityManager()->getConnection()->insert('ai_call', [
            'task' => $call['task'],
            'model' => $call['model'],
            'subject_id' => $call['subject_id'],
            'language' => $call['language'],
            'success' => $call['success'],
            'error_code' => $call['error_code'],
            'input_tokens' => $call['input_tokens'],
            'output_tokens' => $call['output_tokens'],
            'cache_read_tokens' => $call['cache_read_tokens'],
            'cache_write_tokens' => $call['cache_write_tokens'],
            'cost_usd' => \sprintf('%.6F', $call['cost_usd']),
            'latency_ms' => $call['latency_ms'],
            'created_at' => new \DateTimeImmutable(),
        ], [
            'success' => Types::BOOLEAN,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }
}
