<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class AgentsCollector
{
    private const TOP_STUCK_LIMIT = 3;

    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        if (!class_exists(\CAgent::class)) {
            return [];
        }

        $now = time();
        $activeCount = 0;
        $overdueCount = 0;
        $maxLagSeconds = 0;
        /** @var list<array{module: string, function: string, lagSeconds: int}> */
        $stuckAgents = [];

        $result = \CAgent::GetList(['NEXT_EXEC' => 'ASC'], ['ACTIVE' => 'Y']);
        if (!is_object($result)) {
            return [];
        }

        while ($agent = $result->Fetch()) {
            if (!is_array($agent)) {
                continue;
            }

            ++$activeCount;

            $nextExecAt = $this->parseNextExec((string) ($agent['NEXT_EXEC'] ?? ''));
            if ($nextExecAt === null || $nextExecAt >= $now) {
                continue;
            }

            $lagSeconds = $now - $nextExecAt;
            ++$overdueCount;

            if ($lagSeconds > $maxLagSeconds) {
                $maxLagSeconds = $lagSeconds;
            }

            $stuckAgents[] = [
                'module' => (string) ($agent['MODULE_ID'] ?? 'unknown'),
                'function' => $this->sanitizeAgentFunction((string) ($agent['NAME'] ?? '')),
                'lagSeconds' => $lagSeconds,
            ];
        }

        usort(
            $stuckAgents,
            static fn (array $left, array $right): int => $right['lagSeconds'] <=> $left['lagSeconds'],
        );
        $stuckAgents = array_slice($stuckAgents, 0, self::TOP_STUCK_LIMIT);

        return [
            [
                'key' => 'agents.active_count',
                'value' => $activeCount,
                'unit' => 'count',
                'tags' => ['status' => 'ok'],
            ],
            [
                'key' => 'agents.overdue_count',
                'value' => $overdueCount,
                'unit' => 'count',
                'tags' => [
                    'status' => 'ok',
                    'stuckAgents' => $stuckAgents,
                ],
            ],
            [
                'key' => 'agents.max_lag_seconds',
                'value' => $maxLagSeconds,
                'unit' => 'seconds',
                'tags' => [
                    'status' => 'ok',
                    'stuckAgents' => $stuckAgents,
                ],
            ],
        ];
    }

    private function parseNextExec(string $nextExec): ?int
    {
        $nextExec = trim($nextExec);
        if ($nextExec === '') {
            return null;
        }

        $timestamp = strtotime($nextExec);
        if ($timestamp !== false) {
            return $timestamp;
        }

        if (function_exists('MakeTimeStamp')) {
            $timestamp = (int) MakeTimeStamp($nextExec);
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        return null;
    }

    private function sanitizeAgentFunction(string $function): string
    {
        $function = trim($function);
        if ($function === '') {
            return 'unknown';
        }

        if (strlen($function) > 120) {
            return substr($function, 0, 117).'...';
        }

        return $function;
    }
}
