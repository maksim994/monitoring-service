<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Admin;

use Bitrix\Main\Localization\Loc;

final class AdminLang
{
    public static function loadForSettingsPage(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $candidates = [];

        if (defined('LANGUAGE_ID') && is_string(LANGUAGE_ID) && LANGUAGE_ID !== '') {
            $candidates[] = LANGUAGE_ID;
        }

        $candidates[] = 'ru';
        $candidates[] = 'en';

        foreach (array_unique($candidates) as $langId) {
            $path = $moduleRoot.'/lang/'.$langId.'/admin/vendor_monitoring_settings.php';
            if (!is_file($path)) {
                continue;
            }

            Loc::loadMessages($path);

            return;
        }
    }

    public static function message(string $code, ?array $replace = null): string
    {
        $message = Loc::getMessage($code, $replace);

        return is_string($message) && $message !== '' ? $message : $code;
    }
}
