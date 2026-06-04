export type CheckSnapshot = {
  status: string;
  value: Record<string, unknown>;
  collectedAt: string;
};

export type CheckSnapshotLine = {
  text: string;
  emphasis?: boolean;
};

function formatDateTimeRu(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }

  return date.toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDateRu(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }

  return date.toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function formatBytes(bytes: unknown): string | null {
  if (typeof bytes !== 'number' || !Number.isFinite(bytes)) {
    return null;
  }

  if (bytes >= 1024 ** 3) {
    return `${(bytes / 1024 ** 3).toFixed(1)} ГБ`;
  }

  if (bytes >= 1024 ** 2) {
    return `${(bytes / 1024 ** 2).toFixed(0)} МБ`;
  }

  return `${Math.round(bytes / 1024)} КБ`;
}

function formatDuration(seconds: number): string {
  if (seconds < 60) {
    return `${seconds} сек`;
  }

  if (seconds < 3600) {
    return `${Math.round(seconds / 60)} мин`;
  }

  if (seconds < 86400) {
    return `${(seconds / 3600).toFixed(1)} ч`;
  }

  return `${(seconds / 86400).toFixed(1)} дн`;
}

function formatBackupAge(hours: number): string {
  if (hours >= 24) {
    const days = Math.round(hours / 24);

    return `${days} ${days === 1 ? 'сутки' : days < 5 ? 'суток' : 'суток'}`;
  }

  return `${Math.round(hours)} ч`;
}

export function formatCheckSnapshot(
  checkType: string,
  snapshot: CheckSnapshot | null | undefined,
  settings?: Record<string, unknown>,
): CheckSnapshotLine[] {
  if (!snapshot) {
    return [{ text: 'Данных пока нет — дождитесь первого опроса.' }];
  }

  const value = snapshot.value;
  const lines: CheckSnapshotLine[] = [];

  switch (checkType) {
    case 'ssl_expiry': {
      if (typeof value.error === 'string') {
        lines.push({ text: `Не удалось проверить SSL: ${value.error}` });
        break;
      }
      if (typeof value.validTo === 'string') {
        lines.push({ text: `Действует до: ${formatDateRu(value.validTo)}`, emphasis: true });
      }
      if (typeof value.daysLeft === 'number') {
        lines.push({ text: `Осталось: ${value.daysLeft} дн.` });
      }
      break;
    }

    case 'domain_expiry': {
      if (typeof value.error === 'string') {
        lines.push({ text: 'Срок домена не определён (RDAP недоступен).' });
        break;
      }
      if (typeof value.expiresAt === 'string') {
        lines.push({ text: `Регистрация до: ${formatDateRu(value.expiresAt)}`, emphasis: true });
      }
      if (typeof value.daysLeft === 'number') {
        lines.push({ text: `Осталось: ${value.daysLeft} дн.` });
      }
      break;
    }

    case 'bitrix_license_expiry': {
      if (value.unlimited === true) {
        lines.push({ text: 'Лицензия без ограничения по сроку', emphasis: true });
        if (typeof value.edition === 'string' && value.edition !== '') {
          lines.push({ text: `Редакция: ${value.edition}` });
        }
        break;
      }
      if (typeof value.productExpireDate === 'string') {
        lines.push({ text: `Лицензия до: ${formatDateRu(value.productExpireDate)}`, emphasis: true });
      }
      if (typeof value.supportExpireDate === 'string') {
        lines.push({ text: `Техподдержка до: ${formatDateRu(value.supportExpireDate)}`, emphasis: true });
      }
      if (typeof value.daysLeft === 'number') {
        const source =
          value.source === 'support' ? ' (ближайший срок — техподдержка)' : value.source === 'product' ? '' : '';
        lines.push({ text: `До ближайшего срока: ${value.daysLeft} дн.${source}` });
      }
      if (
        typeof value.productDaysLeft === 'number' &&
        typeof value.supportDaysLeft === 'number' &&
        value.productDaysLeft !== value.supportDaysLeft
      ) {
        lines.push({
          text: `Отдельно: лицензия ${value.productDaysLeft} дн., ТП ${value.supportDaysLeft} дн.`,
        });
      }
      if (typeof value.edition === 'string' && value.edition !== '') {
        lines.push({ text: `Редакция: ${value.edition}` });
      }
      break;
    }

    case 'disk_low': {
      const warning =
        typeof settings?.warningPercent === 'number'
          ? settings.warningPercent
          : typeof settings?.warningPercent === 'string'
            ? Number(settings.warningPercent)
            : null;
      const critical =
        typeof settings?.criticalPercent === 'number'
          ? settings.criticalPercent
          : typeof settings?.criticalPercent === 'string'
            ? Number(settings.criticalPercent)
            : null;

      if (typeof value.freePercent === 'number') {
        lines.push({ text: `Свободно: ${value.freePercent}%`, emphasis: true });
        if (warning !== null && Number.isFinite(warning)) {
          const belowMin = value.freePercent < warning;
          lines.push({
            text: belowMin
              ? `Ниже вашего минимума (${warning}%)`
              : `В пределах минимума (≥ ${warning}%)`,
          });
        }
      }
      const free = formatBytes(value.freeBytes);
      const total = formatBytes(value.totalBytes);
      if (free && total) {
        lines.push({ text: `${free} из ${total}` });
      }
      if (critical !== null && Number.isFinite(critical)) {
        lines.push({ text: `Критично при свободном < ${critical}%` });
      }
      break;
    }

    case 'backup_stale': {
      if (value.backupStatus === 'missing') {
        lines.push({ text: 'Резервная копия не найдена', emphasis: true });
        break;
      }
      if (typeof value.lastBackupAt === 'string') {
        lines.push({ text: `Последний бэкап: ${formatDateTimeRu(value.lastBackupAt)}`, emphasis: true });
      }
      if (typeof value.ageHours === 'number') {
        lines.push({ text: `Возраст архива: ${formatBackupAge(value.ageHours)}` });
      }
      break;
    }

    case 'agents_lag': {
      if (typeof value.overdueCount === 'number') {
        lines.push({ text: `Просроченных агентов: ${value.overdueCount}` });
      }
      if (typeof value.maxLagSeconds === 'number') {
        lines.push({ text: `Макс. задержка: ${formatDuration(value.maxLagSeconds)}`, emphasis: true });
      }
      if (typeof value.activeCount === 'number') {
        lines.push({ text: `Активных агентов: ${value.activeCount}` });
      }
      if (Array.isArray(value.stuckAgents)) {
        for (const agent of value.stuckAgents.slice(0, 3)) {
          if (!agent || typeof agent !== 'object') {
            continue;
          }
          const row = agent as Record<string, unknown>;
          const id = typeof row.id === 'number' ? `#${row.id} ` : '';
          const module = typeof row.module === 'string' ? row.module : '?';
          const fn = typeof row.function === 'string' ? row.function : '?';
          const lag =
            typeof row.lagSeconds === 'number' ? formatDuration(row.lagSeconds) : '';
          lines.push({ text: `${id}[${module}] ${fn}${lag ? ` — ${lag}` : ''}` });
        }
      }
      break;
    }

    case 'modules_updates': {
      if (typeof value.updatesAvailableCount === 'number') {
        lines.push({
          text:
            value.updatesAvailableCount === 0
              ? 'Доступных обновлений нет'
              : `Доступно обновлений: ${value.updatesAvailableCount}`,
          emphasis: true,
        });
      }
      break;
    }

    case 'uptime_http': {
      if (typeof value.error === 'string') {
        lines.push({ text: `Ошибка: ${value.error}` });
      }
      if (typeof value.httpStatus === 'number') {
        lines.push({ text: `HTTP ${value.httpStatus}`, emphasis: true });
      }
      if (typeof value.responseTimeMs === 'number') {
        lines.push({ text: `Ответ: ${value.responseTimeMs} мс` });
      }
      break;
    }

    case 'heartbeat_missing': {
      if (typeof value.lastHeartbeatAt === 'string') {
        lines.push({ text: `Последний сигнал: ${formatDateTimeRu(value.lastHeartbeatAt)}`, emphasis: true });
        if (typeof value.secondsSinceLastHeartbeat === 'number') {
          lines.push({ text: `Назад: ${formatDuration(value.secondsSinceLastHeartbeat)}` });
        }
      } else {
        lines.push({ text: 'Связь с модулем ещё не зафиксирована' });
      }
      break;
    }

    default:
      lines.push({ text: 'Состояние получено' });
  }

  if (lines.length === 0) {
    lines.push({ text: 'Данные получены, но не удалось сформировать сводку.' });
  }

  return lines;
}

export type SnapshotMetricTone = 'brand' | 'default' | 'success' | 'warning' | 'danger';

export type SnapshotMetricDisplay = {
  primary: string;
  secondary?: string;
  tone: SnapshotMetricTone;
};

function toneFromSnapshotStatus(status?: string): SnapshotMetricTone {
  switch (status) {
    case 'ok':
      return 'success';
    case 'warning':
      return 'warning';
    case 'critical':
      return 'danger';
    default:
      return 'default';
  }
}

function shortenError(message: string, fallback: string): string {
  const trimmed = message.trim();
  if (trimmed === '') {
    return fallback;
  }

  return trimmed.length > 48 ? `${trimmed.slice(0, 48)}…` : trimmed;
}

const NO_DATA: SnapshotMetricDisplay = {
  primary: 'Нет данных',
  secondary: 'Ожидаем первый опрос',
  tone: 'default',
};

export function getSnapshotMetricDisplay(
  checkType: 'ssl_expiry' | 'domain_expiry' | 'bitrix_license_expiry',
  snapshot?: CheckSnapshot | null,
): SnapshotMetricDisplay {
  if (!snapshot) {
    return NO_DATA;
  }

  const value = snapshot.value;
  const tone = toneFromSnapshotStatus(snapshot.status);

  if (checkType === 'ssl_expiry') {
    if (typeof value.error === 'string') {
      return {
        primary: 'Не проверено',
        secondary: shortenError(value.error, 'Ошибка SSL'),
        tone: 'warning',
      };
    }
    const primary =
      typeof value.validTo === 'string' ? formatDateRu(value.validTo) : typeof value.daysLeft === 'number' ? `${value.daysLeft} дн.` : '—';
    const secondary =
      typeof value.daysLeft === 'number'
        ? `осталось ${value.daysLeft} дн.`
        : typeof value.sslVerifySkipped === 'boolean' && value.sslVerifySkipped
          ? 'срок без проверки цепочки'
          : undefined;

    return { primary, secondary, tone };
  }

  if (checkType === 'domain_expiry') {
    if (typeof value.error === 'string') {
      return {
        primary: 'Не определён',
        secondary: shortenError(value.error, 'Срок не определён'),
        tone: 'warning',
      };
    }
    const primary =
      typeof value.expiresAt === 'string'
        ? formatDateRu(value.expiresAt)
        : typeof value.daysLeft === 'number'
          ? `${value.daysLeft} дн.`
          : '—';
    const secondary =
      typeof value.daysLeft === 'number'
        ? `осталось ${value.daysLeft} дн.${value.source === 'whois' ? ' (WHOIS)' : ''}`
        : undefined;

    return { primary, secondary, tone };
  }

  if (value.unlimited === true) {
    const edition = typeof value.edition === 'string' && value.edition !== '' ? value.edition : undefined;

    return { primary: 'Бессрочно', secondary: edition, tone: 'success' };
  }

  let primary = '—';
  let secondary: string | undefined;

  if (value.source === 'support' && typeof value.supportExpireDate === 'string') {
    primary = formatDateRu(value.supportExpireDate);
    if (typeof value.productExpireDate === 'string') {
      secondary = `Лицензия до ${formatDateRu(value.productExpireDate)}`;
    }
  } else if (typeof value.productExpireDate === 'string') {
    primary = formatDateRu(value.productExpireDate);
    if (typeof value.supportExpireDate === 'string') {
      secondary = `ТП до ${formatDateRu(value.supportExpireDate)}`;
    }
  } else if (typeof value.supportExpireDate === 'string') {
    primary = formatDateRu(value.supportExpireDate);
  }

  if (primary === '—' && typeof value.daysLeft === 'number') {
    primary = `через ${value.daysLeft} дн.`;
  }

  if (!secondary && typeof value.daysLeft === 'number') {
    secondary = `осталось ${value.daysLeft} дн.`;
  }

  return { primary, secondary, tone };
}

export function snapshotStatusClass(status: string): string {
  switch (status) {
    case 'ok':
      return 'text-emerald-700';
    case 'warning':
      return 'text-amber-700';
    case 'critical':
      return 'text-red-700';
    default:
      return 'text-slate-500';
  }
}
