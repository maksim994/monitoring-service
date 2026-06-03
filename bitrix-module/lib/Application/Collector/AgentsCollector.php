<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class AgentsCollector
{
    private const TOP_STUCK_LIMIT = 3;
    private const INACTIVE_OVERDUE_SAMPLE_LIMIT = 5;
    private const COLLECTOR_TAG = 'agents_v3';
    private const MIN_GRACE_SECONDS = 120;
    private const GRACE_INTERVAL_MULTIPLIER = 2;

    /** Модули, чьи agents не участвуют в алерте (сам мониторинг не должен создавать инцидент о себе). */
    private const EXCLUDED_OVERDUE_MODULES = [
        'vendor.monitoring',
    ];

    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        $stats = $this->scanActiveAgents();

        return $this->buildMetrics($stats);
    }

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        $stats = $this->scanActiveAgents();

        return [
            'collector' => self::COLLECTOR_TAG,
            'activeCount' => $stats['activeCount'],
            'overdueCount' => $stats['overdueCount'],
            'maxLagSeconds' => $stats['maxLagSeconds'],
            'stuckAgents' => $stats['stuckAgents'],
            'selfMonitoringOverdue' => $stats['selfMonitoringOverdue'],
            'inactiveOverdueSample' => $this->scanInactiveOverdueSample(),
            'metrics' => $this->buildMetrics($stats),
        ];
    }

    /**
     * @return array{
     *   activeCount: int,
     *   overdueCount: int,
     *   maxLagSeconds: int,
     *   stuckAgents: list<array<string, mixed>>,
     *   selfMonitoringOverdue: list<array<string, mixed>>
     * }
     */
    private function scanActiveAgents(): array
    {
        $now = time();
        $activeCount = 0;
        $overdueCount = 0;
        $maxLagSeconds = 0;
        /** @var list<array<string, mixed>> */
        $stuckAgents = [];
        /** @var list<array<string, mixed>> */
        $selfMonitoringOverdue = [];

        foreach ($this->iterateActiveAgents() as $agent) {
            ++$activeCount;

            $nextExecAt = $this->resolveNextExecTimestamp($agent['NEXT_EXEC'] ?? null);
            if ($nextExecAt === null || $nextExecAt >= $now) {
                continue;
            }

            $lagSeconds = $now - $nextExecAt;
            $row = [
                'id' => (int) ($agent['ID'] ?? 0),
                'module' => (string) ($agent['MODULE_ID'] ?? 'unknown'),
                'function' => $this->sanitizeAgentFunction((string) ($agent['NAME'] ?? '')),
                'lagSeconds' => $lagSeconds,
                'active' => 'Y',
                'nextExec' => $this->formatNextExecForDisplay($agent['NEXT_EXEC'] ?? null),
                'agentInterval' => (int) ($agent['AGENT_INTERVAL'] ?? 0),
                'graceSeconds' => $this->overdueGraceSeconds($agent),
            ];

            if ($lagSeconds <= $row['graceSeconds']) {
                continue;
            }

            if ($this->isExcludedFromOverdueAlert($agent)) {
                $selfMonitoringOverdue[] = $row;

                continue;
            }

            ++$overdueCount;

            if ($lagSeconds > $maxLagSeconds) {
                $maxLagSeconds = $lagSeconds;
            }

            $stuckAgents[] = $row;
        }

        usort(
            $stuckAgents,
            static fn (array $left, array $right): int => $right['lagSeconds'] <=> $left['lagSeconds'],
        );
        $stuckAgents = array_slice($stuckAgents, 0, self::TOP_STUCK_LIMIT);

        usort(
            $selfMonitoringOverdue,
            static fn (array $left, array $right): int => $right['lagSeconds'] <=> $left['lagSeconds'],
        );

        return [
            'activeCount' => $activeCount,
            'overdueCount' => $overdueCount,
            'maxLagSeconds' => $maxLagSeconds,
            'stuckAgents' => $stuckAgents,
            'selfMonitoringOverdue' => array_slice($selfMonitoringOverdue, 0, self::TOP_STUCK_LIMIT),
        ];
    }

    /** @param array<string, mixed> $agent */
    private function isExcludedFromOverdueAlert(array $agent): bool
    {
        $module = strtolower(trim((string) ($agent['MODULE_ID'] ?? '')));

        return in_array($module, self::EXCLUDED_OVERDUE_MODULES, true);
    }

    /** @param array<string, mixed> $agent */
    private function overdueGraceSeconds(array $agent): int
    {
        $interval = (int) ($agent['AGENT_INTERVAL'] ?? 0);
        if ($interval < 60) {
            $interval = 60;
        }

        return max(self::MIN_GRACE_SECONDS, $interval * self::GRACE_INTERVAL_MULTIPLIER);
    }

    /**
     * Неактивные агенты с прошедшим NEXT_EXEC — для диагностики (в метрики облака не попадают).
     *
     * @return list<array<string, mixed>>
     */
    private function scanInactiveOverdueSample(): array
    {
        $now = time();
        /** @var list<array<string, mixed>> */
        $sample = [];

        foreach ($this->iterateAgentsByActiveFlag('N') as $agent) {
            $nextExecAt = $this->resolveNextExecTimestamp($agent['NEXT_EXEC'] ?? null);
            if ($nextExecAt === null || $nextExecAt >= $now) {
                continue;
            }

            $sample[] = [
                'id' => (int) ($agent['ID'] ?? 0),
                'module' => (string) ($agent['MODULE_ID'] ?? 'unknown'),
                'function' => $this->sanitizeAgentFunction((string) ($agent['NAME'] ?? '')),
                'lagSeconds' => $now - $nextExecAt,
                'active' => 'N',
                'nextExec' => $this->formatNextExecForDisplay($agent['NEXT_EXEC'] ?? null),
            ];
        }

        usort(
            $sample,
            static fn (array $left, array $right): int => $right['lagSeconds'] <=> $left['lagSeconds'],
        );

        return array_slice($sample, 0, self::INACTIVE_OVERDUE_SAMPLE_LIMIT);
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function iterateActiveAgents(): \Generator
    {
        yield from $this->iterateAgentsByActiveFlag('Y');
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function iterateAgentsByActiveFlag(string $activeFlag): \Generator
    {
        $activeFlag = strtoupper($activeFlag) === 'Y' ? 'Y' : 'N';

        if (class_exists(\Bitrix\Main\Agent\AgentTable::class)) {
            $result = \Bitrix\Main\Agent\AgentTable::getList([
                'filter' => ['=ACTIVE' => $activeFlag],
                'order' => ['NEXT_EXEC' => 'ASC'],
                'select' => ['ID', 'MODULE_ID', 'NAME', 'NEXT_EXEC', 'ACTIVE', 'AGENT_INTERVAL'],
            ]);

            while ($row = $result->fetch()) {
                if (!is_array($row) || !$this->isAgentActiveFlag($row, $activeFlag)) {
                    continue;
                }

                yield $row;
            }

            return;
        }

        if (!class_exists(\CAgent::class)) {
            return;
        }

        $result = \CAgent::GetList(['NEXT_EXEC' => 'ASC'], ['ACTIVE' => $activeFlag]);
        if (!is_object($result)) {
            return;
        }

        while ($agent = $result->Fetch()) {
            if (!is_array($agent) || !$this->isAgentActiveFlag($agent, $activeFlag)) {
                continue;
            }

            yield $agent;
        }
    }

    /** @param array<string, mixed> $agent */
    private function isAgentActiveFlag(array $agent, string $expectedActive): bool
    {
        return strtoupper(trim((string) ($agent['ACTIVE'] ?? 'N'))) === $expectedActive;
    }

    /**
     * @param array{activeCount: int, overdueCount: int, maxLagSeconds: int, stuckAgents: list<array<string, mixed>>} $stats
     *
     * @return list<array<string, mixed>>
     */
    private function buildMetrics(array $stats): array
    {
        $stuckAgents = $stats['stuckAgents'];

        return [
            [
                'key' => 'agents.active_count',
                'value' => $stats['activeCount'],
                'unit' => 'count',
                'tags' => ['status' => 'ok', 'collector' => self::COLLECTOR_TAG],
            ],
            [
                'key' => 'agents.overdue_count',
                'value' => $stats['overdueCount'],
                'unit' => 'count',
                'tags' => [
                    'status' => 'ok',
                    'collector' => self::COLLECTOR_TAG,
                    'stuckAgents' => $stuckAgents,
                ],
            ],
            [
                'key' => 'agents.max_lag_seconds',
                'value' => $stats['maxLagSeconds'],
                'unit' => 'seconds',
                'tags' => [
                    'status' => 'ok',
                    'collector' => self::COLLECTOR_TAG,
                    'stuckAgents' => $stuckAgents,
                ],
            ],
        ];
    }

    private function resolveNextExecTimestamp(mixed $nextExec): ?int
    {
        if ($nextExec instanceof \Bitrix\Main\Type\DateTime) {
            return $nextExec->getTimestamp();
        }

        if ($nextExec instanceof \DateTimeInterface) {
            return $nextExec->getTimestamp();
        }

        return $this->parseNextExec((string) $nextExec);
    }

    private function formatNextExecForDisplay(mixed $nextExec): string
    {
        if ($nextExec instanceof \Bitrix\Main\Type\DateTime) {
            return $nextExec->toString();
        }

        if ($nextExec instanceof \DateTimeInterface) {
            return $nextExec->format('d.m.Y H:i:s');
        }

        return trim((string) $nextExec);
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
