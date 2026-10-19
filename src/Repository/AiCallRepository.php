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

    /**
     * Calls and spend per task and model since a date, most expensive first.
     *
     * @return list<array{task: string, model: string, calls: int, failures: int, cost_usd: float, avg_cost_usd: float}>
     */
    public function costsSince(\DateTimeImmutable $since): array
    {
        /** @var list<array{task: string, model: string, calls: int|string, failures: int|string|null, cost_usd: float|string|null}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.task, c.model, COUNT(c.id) AS calls')
            ->addSelect('SUM(CASE WHEN c.success = false THEN 1 ELSE 0 END) AS failures')
            ->addSelect('SUM(c.costUsd) AS cost_usd')
            ->where('c.createdAt >= :since')
            ->setParameter('since', $since, Types::DATETIME_IMMUTABLE)
            ->groupBy('c.task, c.model')
            ->orderBy('cost_usd', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'task' => (string) $row['task'],
            'model' => (string) $row['model'],
            'calls' => (int) $row['calls'],
            'failures' => (int) $row['failures'],
            'cost_usd' => (float) $row['cost_usd'],
            'avg_cost_usd' => (int) $row['calls'] > 0 ? (float) $row['cost_usd'] / (int) $row['calls'] : 0.0,
        ], $rows);
    }
}
