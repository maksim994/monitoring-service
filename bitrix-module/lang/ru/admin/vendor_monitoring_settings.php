<?php

$MESS['VENDOR_MONITORING_PAGE_TITLE'] = 'Monitoring Service';
$MESS['VENDOR_MONITORING_MODE'] = 'Режим';
$MESS['VENDOR_MONITORING_API_URL'] = 'URL API';
$MESS['VENDOR_MONITORING_SITE_ID'] = 'Site ID';
$MESS['VENDOR_MONITORING_API_SECRET'] = 'API Secret';
$MESS['VENDOR_MONITORING_SAVE'] = 'Сохранить';
$MESS['VENDOR_MONITORING_TEST_HEARTBEAT'] = 'Отправить тестовый heartbeat';
$MESS['VENDOR_MONITORING_TEST_BACKUP_METRICS'] = 'Отправить метрику бэкапа в облако';
$MESS['VENDOR_MONITORING_SAVED'] = 'Настройки сохранены';
$MESS['VENDOR_MONITORING_HEARTBEAT_OK'] = 'Heartbeat принят, HTTP #STATUS#';
$MESS['VENDOR_MONITORING_HEARTBEAT_FAIL'] = 'Heartbeat не принят, HTTP #STATUS#';
$MESS['VENDOR_MONITORING_BACKUP_METRICS_OK'] = 'Метрика бэкапа отправлена (HTTP #STATUS#). В облако ушло: «#NAME#», дата #DATE#, возраст #HOURS# ч';
$MESS['VENDOR_MONITORING_BACKUP_METRICS_OK_EMPTY'] = 'Метрика отправлена (HTTP #STATUS#), но архив на диске не найден — в облаке будет «бэкап отсутствует»';
$MESS['VENDOR_MONITORING_BACKUP_METRICS_FAIL'] = 'Метрика бэкапа не принята, HTTP #STATUS#';
$MESS['VENDOR_MONITORING_SECRET_HINT'] = 'Оставьте пустым, чтобы не менять текущий secret';

$MESS['VENDOR_MONITORING_BACKUP_DIAG_TITLE'] = 'Диагностика резервных копий (как видит модуль)';
$MESS['VENDOR_MONITORING_BACKUP_DIR'] = 'Каталог';
$MESS['VENDOR_MONITORING_BACKUP_COLLECTOR'] = 'Версия collector';
$MESS['VENDOR_MONITORING_BACKUP_SCANNED'] = 'Найдено файлов-частей архивов';
$MESS['VENDOR_MONITORING_BACKUP_DIR_MISSING'] = 'Каталог /bitrix/backup/ не существует';
$MESS['VENDOR_MONITORING_BACKUP_NOT_FOUND'] = 'В каталоге нет распознанных архивов (.tar.gz, .enc.gz и т.д.)';
$MESS['VENDOR_MONITORING_BACKUP_SELECTED'] = 'Выбран для мониторинга (самый свежий)';
$MESS['VENDOR_MONITORING_BACKUP_DATE'] = 'Дата по mtime файла';
$MESS['VENDOR_MONITORING_BACKUP_AGE'] = 'Возраст';
$MESS['VENDOR_MONITORING_BACKUP_AGE_UNIT'] = 'ч';
$MESS['VENDOR_MONITORING_BACKUP_METRIC_SENT'] = 'Метрика для облака';
$MESS['VENDOR_MONITORING_BACKUP_GROUPS'] = 'Все найденные архивы (группы)';
$MESS['VENDOR_MONITORING_BACKUP_GROUP_NAME'] = 'Базовое имя';
$MESS['VENDOR_MONITORING_BACKUP_GROUP_DATE'] = 'Свежайшая часть';
$MESS['VENDOR_MONITORING_BACKUP_GROUP_AGE'] = 'Возраст';
$MESS['VENDOR_MONITORING_BACKUP_GROUP_PARTS'] = 'Частей';
$MESS['VENDOR_MONITORING_BACKUP_GROUP_FILES'] = 'Файлы на диске';
$MESS['VENDOR_MONITORING_BACKUP_GROUPS_HINT'] = 'Зелёная строка — архив, который уйдёт в monitoring-service. Если свежий бэкап не подсвечен, проверьте расширение файла в /bitrix/backup/';
