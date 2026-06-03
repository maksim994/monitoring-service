import { useState } from 'react';
import {
  Archive,
  ChevronDown,
  Clock,
  Globe,
  HardDrive,
  Lock,
  Package,
  Radio,
  ScrollText,
  Settings2,
  Timer,
  type LucideIcon,
} from 'lucide-react';
import { CheckThresholdForm } from './CheckThresholdForm';
import { Badge } from './ui/Badge';
import { Switch } from './ui/Switch';
import { getCheckMeta, getCheckTypeLabel, getThresholdChips, type ThresholdChip } from '../lib/checks';
import {
  formatCheckSnapshot,
  snapshotStatusClass,
  type CheckSnapshot,
} from '../lib/checkSnapshot';

const CHECK_ICONS: Record<string, { icon: LucideIcon; tone: string }> = {
  uptime_http: { icon: Globe, tone: 'bg-sky-50 text-sky-600 ring-sky-100' },
  ssl_expiry: { icon: Lock, tone: 'bg-indigo-50 text-indigo-600 ring-indigo-100' },
  domain_expiry: { icon: Globe, tone: 'bg-cyan-50 text-cyan-600 ring-cyan-100' },
  disk_low: { icon: HardDrive, tone: 'bg-orange-50 text-orange-600 ring-orange-100' },
  backup_stale: { icon: Archive, tone: 'bg-violet-50 text-violet-600 ring-violet-100' },
  agents_lag: { icon: Timer, tone: 'bg-amber-50 text-amber-600 ring-amber-100' },
  modules_updates: { icon: Package, tone: 'bg-emerald-50 text-emerald-600 ring-emerald-100' },
  heartbeat_missing: { icon: Radio, tone: 'bg-brand-50 text-brand-600 ring-brand-100' },
  bitrix_license_expiry: { icon: ScrollText, tone: 'bg-rose-50 text-rose-600 ring-rose-100' },
};

const CHIP_STYLES: Record<ThresholdChip['level'], string> = {
  warning: 'bg-amber-50 text-amber-800 ring-amber-200/80',
  critical: 'bg-red-50 text-red-800 ring-red-200/80',
  info: 'bg-slate-100 text-slate-700 ring-slate-200',
};

type SiteCheck = {
  id: string;
  type: string;
  enabled: boolean;
  intervalSeconds: number;
  settings: Record<string, unknown>;
  snapshot?: CheckSnapshot | null;
};

type Props = {
  check: SiteCheck;
  canManage: boolean;
  saving: boolean;
  toggling?: boolean;
  onSave: (settings: Record<string, number>) => Promise<void>;
  onToggleEnabled: (enabled: boolean) => void;
};

export function SiteCheckCard({ check, canManage, saving, toggling, onSave, onToggleEnabled }: Props) {
  const [expanded, setExpanded] = useState(false);
  const meta = getCheckMeta(check.type);
  const iconConfig = CHECK_ICONS[check.type] ?? { icon: Settings2, tone: 'bg-slate-100 text-slate-600 ring-slate-200' };
  const Icon = iconConfig.icon;
  const chips = getThresholdChips(check.type, check.settings ?? {});
  const intervalMin = Math.round(check.intervalSeconds / 60);
  const snapshotLines = formatCheckSnapshot(check.type, check.snapshot, check.settings ?? {});
  const collectedAt = check.snapshot?.collectedAt;

  return (
    <article
      className={`overflow-hidden rounded-2xl border bg-white shadow-sm transition-shadow hover:shadow-md ${
        check.enabled ? 'border-slate-200/90' : 'border-slate-200 bg-slate-50/80 opacity-90'
      }`}
    >
      <div className="flex gap-4 p-5">
        <div
          className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ${iconConfig.tone}`}
        >
          <Icon className="h-5 w-5" strokeWidth={2} />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <h3 className="text-base font-semibold tracking-tight text-slate-900">{getCheckTypeLabel(check.type)}</h3>
              {!canManage && (
                <Badge
                  className={
                    check.enabled
                      ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                      : 'bg-slate-100 text-slate-500 ring-slate-200'
                  }
                >
                  {check.enabled ? 'Отслеживается' : 'Не отслеживается'}
                </Badge>
              )}
            </div>

            {canManage && (
              <div
                className={`flex shrink-0 items-center gap-2 rounded-lg border px-2.5 py-1.5 shadow-sm transition-colors ${
                  check.enabled ? 'border-brand-200/80 bg-brand-50/60' : 'border-slate-200 bg-white'
                } ${toggling ? 'opacity-60' : ''}`}
              >
                <span className="text-xs font-medium text-slate-600">{toggling ? 'Сохранение…' : 'Мониторинг'}</span>
                <Switch
                  checked={check.enabled}
                  disabled={toggling}
                  onChange={onToggleEnabled}
                  id={`check-enabled-${check.id}`}
                  aria-label={`Мониторинг: ${getCheckTypeLabel(check.type)}`}
                />
              </div>
            )}
          </div>

          {!check.enabled && (
            <p className="mt-2 text-xs text-slate-500">Инциденты по этой проверке не создаются. Открытые закроются автоматически.</p>
          )}

          {meta && <p className="mt-1 line-clamp-2 text-sm leading-relaxed text-slate-500">{meta.description}</p>}

          <div className="mt-3 rounded-xl border border-slate-100 bg-gradient-to-br from-slate-50 to-white px-3.5 py-3 ring-1 ring-slate-100/80">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Сейчас</p>
              {check.snapshot?.status && (
                <span className={`text-xs font-medium ${snapshotStatusClass(check.snapshot.status)}`}>
                  {check.snapshot.status === 'ok'
                    ? 'В норме'
                    : check.snapshot.status === 'warning'
                      ? 'Внимание'
                      : check.snapshot.status === 'critical'
                        ? 'Критично'
                        : 'Нет данных'}
                </span>
              )}
            </div>
            <ul className="mt-2 space-y-1">
              {snapshotLines.map((line) => (
                <li
                  key={line.text}
                  className={`text-sm leading-snug ${line.emphasis ? 'font-medium text-slate-900' : 'text-slate-600'}`}
                >
                  {line.text}
                </li>
              ))}
            </ul>
            {collectedAt && (
              <p className="mt-2 text-xs text-slate-400">
                Обновлено:{' '}
                {new Date(collectedAt).toLocaleString('ru-RU', {
                  day: 'numeric',
                  month: 'short',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </p>
            )}
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-2">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200/80">
              <Clock className="h-3.5 w-3.5 text-slate-400" />
              Опрос {intervalMin} мин
            </span>

            {chips.map((chip) => (
              <span
                key={chip.key}
                className={`inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${CHIP_STYLES[chip.level]}`}
                title={chip.title ?? `${chip.shortLabel}: ${chip.value}`}
              >
                <span className="shrink-0 opacity-80">{chip.shortLabel}</span>
                <span className="font-semibold">{chip.value}</span>
              </span>
            ))}

            {chips.length === 0 && (
              <span className="rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-400 ring-1 ring-slate-100">
                Пороги заданы системой
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
        {meta && (
          <button
            type="button"
            onClick={() => setExpanded((value) => !value)}
            className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-brand-600"
          >
            <ChevronDown className={`h-4 w-4 transition-transform ${expanded ? 'rotate-180' : ''}`} />
            {expanded ? 'Скрыть пояснение' : 'Зачем эта проверка'}
          </button>
        )}

        <div className="w-full sm:w-auto sm:min-w-[7rem]">
          <CheckThresholdForm
            checkType={check.type}
            settings={check.settings ?? {}}
            disabled={!canManage || !check.enabled}
            saving={saving}
            onSave={onSave}
            variant="compact"
          />
        </div>
      </div>

      {expanded && meta && (
        <div className="border-t border-slate-100 px-5 py-4">
          <p className="text-sm leading-relaxed text-slate-900">
            <span className="font-semibold">Если сработает инцидент: </span>
            {meta.whyItMatters}
          </p>
        </div>
      )}
    </article>
  );
}
