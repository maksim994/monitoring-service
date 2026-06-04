<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Entity\Check;
use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Service\Alert\AlertEngine;
use App\Service\Probe\ProbeRunner;
use Doctrine\ORM\EntityManagerInterface;

final class SiteRefreshService
{
    private const PROBE_ID = 'cabinet-manual';

    public function __construct(
        private readonly CheckRepository $checkRepository,
        private readonly ProbeRunner $probeRunner,
        private readonly AlertEngine $alertEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *   probeChecks: list<string>,
     *   moduleCheckTypes: list<string>,
     *   message: string
     * }
     */
    public function refresh(Site $site): array
    {
        $probeChecks = [];

        foreach ($this->checkRepository->findEnabledProbesForSite($site) as $check) {
            $result = $this->probeRunner->runCheck($check, self::PROBE_ID);
            $this->entityManager->persist($result);
            $probeChecks[] = $check->getType();
        }

        $this->alertEngine->evaluateSite($site);
        $this->entityManager->flush();

        $moduleCheckTypes = [
            Check::TYPE_DISK_LOW,
            Check::TYPE_BACKUP_STALE,
            Check::TYPE_AGENTS_LAG,
            Check::TYPE_MODULES_UPDATES,
            Check::TYPE_BITRIX_LICENSE_EXPIRY,
        ];

        $message = $probeChecks === []
            ? 'Внешние проверки (SSL, домен, uptime) выключены или недоступны.'
            : sprintf(
                'Обновлены проверки с сервера: %s.',
                implode(', ', array_map(static fn (string $type): string => $type, $probeChecks)),
            );

        if ($site->getLastHeartbeatAt() !== null) {
            $message .= ' Данные с Bitrix (лицензия, диск, бэкап…) приходят с модуля — на сайте выполните «Собрать и отправить все метрики» в настройках модуля или дождитесь агента (~5 мин).';
        } else {
            $message .= ' Модуль Bitrix ещё не на связи — сначала настройте и отправьте heartbeat с сайта.';
        }

        return [
            'probeChecks' => $probeChecks,
            'moduleCheckTypes' => $moduleCheckTypes,
            'message' => $message,
        ];
    }
}
