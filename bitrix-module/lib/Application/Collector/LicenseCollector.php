<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

final class LicenseCollector
{
    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        if (!class_exists(Application::class)) {
            return [];
        }

        try {
            $license = Application::getInstance()->getLicense();
        } catch (\Throwable) {
            return [
                [
                    'key' => 'license.days_left',
                    'value' => null,
                    'unit' => 'days',
                    'tags' => ['status' => 'unknown'],
                ],
            ];
        }

        $productExpire = $license->getExpireDate();
        $supportExpire = $license->getSupportExpireDate();
        $productDays = $this->computeDaysLeft($productExpire);
        $supportDays = $this->computeDaysLeft($supportExpire);

        $tags = [
            'isDemo' => $license->isDemo(),
            'isTimeBound' => $license->isTimeBound(),
            'edition' => (string) $license->getName(),
        ];

        if ($productExpire instanceof DateTime) {
            $tags['productExpireDate'] = $productExpire->format('c');
        }

        if ($supportExpire instanceof DateTime) {
            $tags['supportExpireDate'] = $supportExpire->format('c');
        }

        if (!$license->isTimeBound() && $productDays === null && $supportDays === null) {
            return [
                [
                    'key' => 'license.days_left',
                    'value' => null,
                    'unit' => 'days',
                    'tags' => array_merge($tags, ['status' => 'unlimited']),
                ],
            ];
        }

        $effective = $this->pickEffectiveDays($productDays, $supportDays);
        if ($effective === null) {
            return [
                [
                    'key' => 'license.days_left',
                    'value' => null,
                    'unit' => 'days',
                    'tags' => array_merge($tags, ['status' => 'unknown']),
                ],
            ];
        }

        $tags['status'] = 'ok';
        $tags['source'] = $effective['source'];
        $tags['daysLeft'] = $effective['days'];

        if ($productDays !== null) {
            $tags['productDaysLeft'] = $productDays;
        }

        if ($supportDays !== null) {
            $tags['supportDaysLeft'] = $supportDays;
        }

        return [
            [
                'key' => 'license.days_left',
                'value' => $effective['days'],
                'unit' => 'days',
                'tags' => $tags,
            ],
        ];
    }

    private function computeDaysLeft(?DateTime $date): ?int
    {
        if ($date === null) {
            return null;
        }

        $delta = $date->getTimestamp() - time();

        return $delta < 0 ? 0 : (int) ceil($delta / 86400);
    }

    /**
     * @return array{days: int, source: string}|null
     */
    private function pickEffectiveDays(?int $productDays, ?int $supportDays): ?array
    {
        if ($productDays === null && $supportDays === null) {
            return null;
        }

        if ($productDays !== null && $supportDays !== null) {
            if ($supportDays < $productDays) {
                return ['days' => $supportDays, 'source' => 'support'];
            }

            return ['days' => $productDays, 'source' => 'product'];
        }

        if ($productDays !== null) {
            return ['days' => $productDays, 'source' => 'product'];
        }

        return ['days' => $supportDays, 'source' => 'support'];
    }
}
