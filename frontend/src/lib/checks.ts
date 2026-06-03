export type CheckThresholdField = {
  key: string;
  /** Заголовок поля в модальном окне */
  label: string;
  unit: string;
  min: number;
  max: number;
  step?: number;
  /** Подпись к числу в бейдже, например «бэкап старше 72 ч» */
  chipText?: string;
  /** Пояснение под полем ввода */
  hint?: string;
  /** Короткая подпись в бейдже на карточке проверки */
  chipShortLabel?: string;
  toApi?: (value: number) => number;
  fromApi?: (value: number) => number;
};

export type CheckMeta = {
  label: string;
  description: string;
  whyItMatters: string;
  /** Текст в модальном окне порогов */
  thresholdsModalDescription?: string;
  /** Подпись кнопки открытия порогов (по умолчанию «Пороги») */
  thresholdButtonLabel?: string;
  thresholdFields?: CheckThresholdField[];
};

function chip(template: string, value: number): string {
  return template.replace('{value}', String(value));
}

export const CHECK_META: Record<string, CheckMeta> = {
  uptime_http: {
    label: 'Доступность сайта',
    description: 'Внешняя проверка: открывается ли главная страница или указанный URL по HTTPS/HTTP.',
    whyItMatters:
      'Если сайт не отвечает, клиенты и поисковики видят ошибку. Порог срабатывания — после нескольких неудачных попыток подряд (debounce), настраивается на уровне сервиса.',
  },
  ssl_expiry: {
    label: 'SSL-сертификат',
    description: 'Проверка срока действия TLS-сертификата и возможности handshake.',
    whyItMatters:
      'Просроченный SSL — браузер показывает предупреждение, падают интеграции и доверие к сайту.',
    thresholdsModalDescription: 'Инцидент создаётся, когда до окончания срока действия сертификата остаётся меньше указанного числа дней.',
    thresholdFields: [
      {
        key: 'warningDays',
        label: 'Предупреждение',
        unit: 'дней',
        chipText: 'SSL истекает через {value} дн',
        hint: 'Жёлтый инцидент: до конца сертификата осталось меньше этого числа дней. Рекомендуется продлить заранее.',
        min: 1,
        max: 365,
        step: 1,
      },
      {
        key: 'criticalDays',
        label: 'Критично',
        unit: 'дней',
        chipText: 'SSL истекает через {value} дн',
        hint: 'Красный инцидент: срок заканчивается очень скоро (обычно меньше порога предупреждения).',
        min: 1,
        max: 90,
        step: 1,
      },
    ],
  },
  domain_expiry: {
    label: 'Срок регистрации домена',
    description: 'Оценка даты истечения домена через RDAP (если реестр отвечает).',
    whyItMatters: 'Просроченный домен — сайт и почта перестают работать, домен могут занять.',
    thresholdsModalDescription: 'Считаем, сколько дней осталось до истечения регистрации домена.',
    thresholdFields: [
      {
        key: 'warningDays',
        label: 'Предупреждение',
        unit: 'дней',
        chipText: 'домен истекает через {value} дн',
        hint: 'Предупреждение: до продления домена осталось меньше указанного числа дней.',
        min: 1,
        max: 365,
        step: 1,
      },
      {
        key: 'criticalDays',
        label: 'Критично',
        unit: 'дней',
        chipText: 'домен истекает через {value} дн',
        hint: 'Критично: домен скоро перестанет принадлежать вам — срочно продлите у регистратора.',
        min: 1,
        max: 90,
        step: 1,
      },
    ],
  },
  disk_low: {
    label: 'Место на диске',
    description: 'Модуль Bitrix сообщает, сколько свободно места на диске с document root.',
    whyItMatters:
      'При нехватке места падают загрузки, кеш, бэкапы и обновления; сайт может перестать сохранять данные.',
    thresholdButtonLabel: 'Минимум места',
    thresholdsModalDescription:
      'Укажите минимальную долю свободного места (в %). Пока на диске не меньше этого значения — проверка в норме. Ниже — предупреждение; ещё ниже критического порога — критичный инцидент. Пример: при 8% свободно и минимуме 15% будет «Внимание»; если для вашего сервера достаточно 5%, задайте минимум 5%.',
    thresholdFields: [
      {
        key: 'warningPercent',
        label: 'Минимум свободного места',
        unit: '%',
        chipShortLabel: 'Минимум',
        chipText: 'норма от {value}%',
        hint: 'Пока свободно ≥ этого % — без инцидента. По умолчанию 15%. Уменьшите, если на сервере обычно мало запаса (например, 5–8%).',
        min: 1,
        max: 90,
        step: 1,
      },
      {
        key: 'criticalPercent',
        label: 'Критично, если ниже',
        unit: '%',
        chipShortLabel: 'Критично',
        chipText: 'ниже {value}%',
        hint: 'Критичный инцидент при ещё меньшем остатке. Должно быть меньше минимума свободного места (по умолчанию 5%).',
        min: 1,
        max: 50,
        step: 1,
      },
    ],
  },
  backup_stale: {
    label: 'Резервное копирование',
    description: 'Проверка свежести архивов в каталоге `/bitrix/backup/` на сервере.',
    whyItMatters:
      'Без свежих копий при сбое или взломе нельзя быстро восстановить сайт и базу. Отсутствие бэкапов — отдельный инцидент.',
    thresholdsModalDescription:
      'Укажите максимальный возраст последнего архива в часах. Например, 72 часа = последний бэкап сделан более 3 суток назад.',
    thresholdFields: [
      {
        key: 'warningHours',
        label: 'Предупреждение',
        unit: 'часов',
        chipText: 'бэкап старше {value} ч',
        hint: 'Предупреждение: последний файл в /bitrix/backup/ лежит дольше этого срока (в часах). 72 ч ≈ 3 суток без свежей копии.',
        min: 1,
        max: 2160,
        step: 1,
      },
      {
        key: 'criticalHours',
        label: 'Критично',
        unit: 'часов',
        chipText: 'бэкап старше {value} ч',
        hint: 'Критично: архив ещё старше (например 168 ч = 7 суток). Должно быть больше порога предупреждения.',
        min: 1,
        max: 8760,
        step: 1,
      },
    ],
  },
  agents_lag: {
    label: 'Агенты Bitrix (cron)',
    description:
      'Агенты — фоновые задачи Bitrix (рассылки, индексация, наш модуль). Сверяется время NEXT_EXEC с текущим.',
    whyItMatters:
      'Просроченные агенты — не уходят письма, не обновляется поиск, не выполняются регламентные задачи. Часто причина — cron не настроен, только запуск «по хитам».',
    thresholdsModalDescription: 'Пороги — на сколько минут просрочено выполнение агента относительно NEXT_EXEC.',
    thresholdFields: [
      {
        key: 'warningLagSeconds',
        label: 'Предупреждение',
        unit: 'мин',
        chipText: 'задержка от {value} мин',
        hint: 'Предупреждение: агент не отработал дольше этого времени (в минутах).',
        min: 1,
        max: 43200,
        step: 1,
        toApi: (m) => Math.round(m * 60),
        fromApi: (s) => Math.round(s / 60),
      },
      {
        key: 'criticalLagSeconds',
        label: 'Критично',
        unit: 'мин',
        chipText: 'задержка от {value} мин',
        hint: 'Критично: просрочка ещё больше (например 120 мин = 2 часа). Должно быть больше порога предупреждения.',
        min: 1,
        max: 525600,
        step: 1,
        toApi: (m) => Math.round(m * 60),
        fromApi: (s) => Math.round(s / 60),
      },
    ],
  },
  bitrix_license_expiry: {
    label: 'Лицензия 1С-Битрикс',
    description:
      'Модуль читает сроки через API Bitrix (`License::getExpireDate`, `getSupportExpireDate`) — без ручного ввода и без передачи ключей.',
    whyItMatters:
      'Просроченная лицензия или техподдержка — нельзя ставить обновления безопасности, на сайте появляются предупреждения в админке, возможны ограничения работы.',
    thresholdsModalDescription:
      'Инцидент, когда до ближайшего срока (лицензия продукта или техподдержка) остаётся меньше указанного числа дней.',
    thresholdFields: [
      {
        key: 'warningDays',
        label: 'Предупреждение',
        unit: 'дней',
        chipText: 'лицензия через {value} дн',
        hint: 'Предупреждение: до окончания лицензии или техподдержки (что наступит раньше) осталось меньше этого числа дней.',
        min: 1,
        max: 365,
        step: 1,
      },
      {
        key: 'criticalDays',
        label: 'Критично',
        unit: 'дней',
        chipText: 'лицензия через {value} дн',
        hint: 'Критично: срок очень близко. Значение должно быть меньше порога предупреждения.',
        min: 1,
        max: 90,
        step: 1,
      },
    ],
  },
  modules_updates: {
    label: 'Обновления модулей',
    description: 'Сколько обновлений доступно для установленных модулей Bitrix.',
    whyItMatters:
      'Долго без обновлений — накапливаются уязвимости и несовместимости; обновления лучше планировать осознанно.',
    thresholdsModalDescription: 'Инцидент создаётся, когда доступно не меньше указанного числа обновлений модулей.',
    thresholdFields: [
      {
        key: 'warningUpdatesCount',
        label: 'Порог',
        unit: 'шт',
        chipText: 'от {value} обновлений',
        hint: 'Например, 1 — инцидент при любой доступной обновлении установленных модулей.',
        min: 1,
        max: 100,
        step: 1,
      },
    ],
  },
  heartbeat_missing: {
    label: 'Связь с модулем Bitrix',
    description:
      'Периодический «пульс»: модуль на сайте сообщает, что установлен и видит облако мониторинга.',
    whyItMatters:
      'Если пульса нет — мы не получаем метрики с сервера (диск, бэкап, агенты). Частые причины: неверный API secret, модуль выключен, блокировка исходящих запросов.',
  },
};

export function getCheckTypeLabel(checkType: string): string {
  return CHECK_META[checkType]?.label ?? checkType;
}

export function getCheckMeta(checkType: string): CheckMeta | null {
  return CHECK_META[checkType] ?? null;
}

export type ThresholdChip = {
  key: string;
  level: 'warning' | 'critical' | 'info';
  shortLabel: string;
  value: string;
  title?: string;
};

export function getThresholdChips(checkType: string, settings: Record<string, unknown>): ThresholdChip[] {
  const meta = getCheckMeta(checkType);
  if (!meta?.thresholdFields?.length) {
    return [];
  }

  const chips: ThresholdChip[] = [];
  for (const field of meta.thresholdFields) {
    const raw = settings[field.key];
    if (typeof raw !== 'number' && typeof raw !== 'string') {
      continue;
    }
    const num = Number(raw);
    const display = field.fromApi ? field.fromApi(num) : num;
    const isCritical = field.key.includes('critical');
    const isWarning = field.key.includes('warning') || field.key === 'warningUpdatesCount';

    const valueText = field.chipText ? chip(field.chipText, display) : `${display} ${field.unit}`;

    const defaultShortLabel = isCritical && !isWarning ? 'Критично' : isWarning ? 'Предупреждение' : 'Порог';

    chips.push({
      key: field.key,
      level: isCritical && !isWarning ? 'critical' : isWarning ? 'warning' : 'info',
      shortLabel: field.chipShortLabel ?? defaultShortLabel,
      value: valueText,
      title: field.hint,
    });
  }

  return chips;
}

export function formatThresholdSummary(checkType: string, settings: Record<string, unknown>): string | null {
  const chips = getThresholdChips(checkType, settings);
  if (chips.length === 0) {
    return null;
  }

  return chips.map((c) => `${c.shortLabel}: ${c.value}`).join(' · ');
}

export const CONNECTION_LABEL = 'Связь с модулем';
export const CONNECTION_MISSING_LABEL = 'Нет связи';
export const CONNECTION_DESCRIPTION =
  'Модуль на сайте периодически отправляет сигнал «на связи». Это не публичный uptime, а контроль, что Bitrix-модуль установлен и достучался до сервиса мониторинга.';

export const MAINTENANCE_WINDOW_HELP = {
  title: 'Зачем нужно окно обслуживания',
  intro:
    'Используйте при плановых работах: обновление Bitrix, перенос на другой хостинг, правки на сервере — когда временные сбои ожидаемы.',
  bullets: [
    'Пока окно активно — новые инциденты по выбранным проверкам (или по всем) не создаются.',
    'Повторные Telegram-напоминания по critical тоже не уходят.',
    'Уже открытые инциденты остаются в списке; если проблема ушла — закроются автоматически.',
    'Проверки продолжают выполняться, данные сохраняются — меняется только создание новых алертов.',
    'После окончания времени окно само перестаёт действовать; можно отменить досрочно.',
  ],
};
