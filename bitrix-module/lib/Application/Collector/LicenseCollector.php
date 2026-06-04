<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

use Bitrix\Main\Application;
use Bitrix\Main\Type\Date;
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

        $productExpireIso = $this->formatExpireForTag($productExpire);
        if ($productExpireIso !== null) {
            $tags['productExpireDate'] = $productExpireIso;
        }

        $supportExpireIso = $this->formatExpireForTag($supportExpire);
        if ($supportExpireIso !== null) {
            $tags['supportExpireDate'] = $supportExpireIso;
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

    private function computeDaysLeft(Date|DateTime|null $date): ?int
    {
        if ($date === null) {
            return null;
        }

        $timestamp = $this->resolveExpireTimestamp($date);
        if ($timestamp === null) {
            return null;
        }

        $delta = $timestamp - time();

        return $delta < 0 ? 0 : (int) ceil($delta / 86400);
    }

    private function formatExpireForTag(Date|DateTime|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof DateTime) {
            return $date->format('c');
        }

        return $date->format('Y-m-d');
    }

    private function resolveExpireTimestamp(Date|DateTime $date): ?int
    {
        try {
            return $date->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
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
