/local/modules/vendor.monitoring/
├── install/
│   ├── index.php
│   └── version.php
├── lib/
│   ├── Application/
│   │   └── Service/
│   │       └── ModuleSender.php
│   └── Cli/
│       └── Agent/
│           └── CollectorAgent.php
├── admin/
│   └── vendor_monitoring_settings.php
├── options.php
├── default_option.php
├── lang/
│   ├── ru/admin/vendor_monitoring_settings.php
│   └── en/admin/vendor_monitoring_settings.php
└── include.php
```

## Установка на сайт

1. Скопируйте каталог в `local/modules/vendor.monitoring` (или `bitrix/modules/vendor.monitoring`).
2. В админке Bitrix: **Marketplace → Установленные решения** → установите модуль **vendor.monitoring** (важно: создаётся файл `/bitrix/admin/vendor_monitoring_settings.php`).
3. Откройте **Настройки → Monitoring Service** (или `/bitrix/admin/vendor_monitoring_settings.php`).
4. Укажите URL API, Site ID и API secret из кабинета monitoring-service.

Если страница настроек без подписей и стилей — переустановите модуль или обновите stub:

```php
<?php require '/полный/путь/local/modules/vendor.monitoring/admin/vendor_monitoring_settings.php';
```

в файле `bitrix/admin/vendor_monitoring_settings.php`.
