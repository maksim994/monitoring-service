import { type FormEvent, useEffect, useState } from 'react';
import { AlertTriangle, Box, Code2, Copy, Globe, KeyRound, Lock, Power, PowerOff, Radio, RefreshCw, ScrollText, Wrench } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { api, type MaintenanceWindow, type SiteDetails } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { SiteMetricCard } from '../components/SiteMetricCard';
import { Input } from '../components/ui/Input';
import { SiteCheckCard } from '../components/SiteCheckCard';
import {
  CONNECTION_DESCRIPTION,
  CONNECTION_LABEL,
  CONNECTION_MISSING_LABEL,
  getCheckTypeLabel,
  MAINTENANCE_WINDOW_HELP,
} from '../lib/checks';
import { canManageSites } from '../lib/roles';
import { getSnapshotMetricDisplay, type CheckSnapshot } from '../lib/checkSnapshot';
import { getStatusMeta } from '../lib/status';

const MAINTENANCE_CHECK_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: 'Все проверки' },
  { value: 'uptime_http', label: getCheckTypeLabel('uptime_http') },
  { value: 'ssl_expiry', label: getCheckTypeLabel('ssl_expiry') },
  { value: 'domain_expiry', label: getCheckTypeLabel('domain_expiry') },
  { value: 'disk_low', label: getCheckTypeLabel('disk_low') },
  { value: 'backup_stale', label: getCheckTypeLabel('backup_stale') },
  { value: 'agents_lag', label: getCheckTypeLabel('agents_lag') },
  { value: 'modules_updates', label: getCheckTypeLabel('modules_updates') },
  { value: 'heartbeat_missing', label: getCheckTypeLabel('heartbeat_missing') },
  { value: 'bitrix_license_expiry', label: getCheckTypeLabel('bitrix_license_expiry') },
];

export function SiteDetailPage() {
  const { siteId } = useParams();
  const { token, organization } = useAuth();
  const [site, setSite] = useState<
    (SiteDetails & {
      checks?: Array<{
        id: string;
        type: string;
        enabled: boolean;
        intervalSeconds: number;
        settings: Record<string, unknown>;
        snapshot?: {
          status: string;
          value: Record<string, unknown>;
          collectedAt: string;
        } | null;
      }>;
    }) | null
  >(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [rotatedSecret, setRotatedSecret] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [maintenanceWindows, setMaintenanceWindows] = useState<MaintenanceWindow[]>([]);
  const [maintenanceTitle, setMaintenanceTitle] = useState('Плановые работы');
  const [maintenanceHours, setMaintenanceHours] = useState('2');
  const [maintenanceCheckType, setMaintenanceCheckType] = useState('');

  const canManage = canManageSites(organization?.role);
  const isDisabled = site?.status === 'disabled';

  useEffect(() => {
    if (!token || !siteId) {
      return;
    }

    Promise.all([
      api.getSite(token, siteId),
      api.listMaintenanceWindows(token, siteId).catch(() => ({ items: [] as MaintenanceWindow[] })),
    ])
      .then(([siteData, maintenanceData]) => {
        setSite(siteData);
        setMaintenanceWindows(maintenanceData.items);
      })
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить сайт'))
      .finally(() => setLoading(false));
  }, [token, siteId]);

  async function onRefreshSite() {
    if (!token || !siteId || isDisabled) {
      return;
    }

    setActionLoading('refresh');
    setError(null);
    setSuccess(null);

    try {
      const data = await api.refreshSite(token, siteId);
      setSite(data);
      setSuccess(data.refresh?.message ?? 'Данные обновлены.');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось обновить данные');
    } finally {
      setActionLoading(null);
    }
  }

  async function reloadMaintenance() {
    if (!token || !siteId) {
      return;
    }

    const response = await api.listMaintenanceWindows(token, siteId);
    setMaintenanceWindows(response.items);
  }

  async function onCreateMaintenance(event: FormEvent) {
    event.preventDefault();
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading('maintenance-create');
    setError(null);
    setSuccess(null);

    try {
      await api.createMaintenanceWindow(token, siteId, {
        title: maintenanceTitle,
        durationHours: Number(maintenanceHours) || 2,
        ...(maintenanceCheckType ? { checkType: maintenanceCheckType } : {}),
      });
      await reloadMaintenance();
      setSuccess('Окно обслуживания создано. Новые инциденты не будут открываться до окончания.');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось создать окно');
    } finally {
      setActionLoading(null);
    }
  }

  async function onCancelMaintenance(windowId: string) {
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading(`maintenance-cancel-${windowId}`);
    setError(null);

    try {
      await api.cancelMaintenanceWindow(token, siteId, windowId);
      await reloadMaintenance();
      setSuccess('Окно обслуживания отменено');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось отменить окно');
    } finally {
      setActionLoading(null);
    }
  }

  async function copyText(value: string) {
    await navigator.clipboard.writeText(value);
    setSuccess('Скопировано в буфер обмена');
  }

  async function onRotateKey() {
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading('rotate');
    setError(null);
    setSuccess(null);

    try {
      const response = await api.rotateSiteKey(token, siteId);
      setRotatedSecret(response.apiSecret);
      setSuccess('Новый API secret создан. Обновите его в Bitrix-модуле.');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сменить ключ');
    } finally {
      setActionLoading(null);
    }
  }

  function mergeCheckUpdate(
    checkId: string,
    patch: Partial<{
      settings: Record<string, unknown>;
      enabled: boolean;
      snapshot?: {
        status: string;
        value: Record<string, unknown>;
        collectedAt: string;
      } | null;
    }>,
  ) {
    setSite((current) => {
      if (!current?.checks) {
        return current;
      }
      return {
        ...current,
        checks: current.checks.map((check) => (check.id === checkId ? { ...check, ...patch } : check)),
      };
    });
  }

  async function onSaveCheckSettings(checkId: string, settings: Record<string, number>) {
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading(`check-${checkId}`);
    setError(null);

    try {
      const updated = await api.updateCheck(token, siteId, checkId, { settings });
      mergeCheckUpdate(checkId, {
        settings: updated.settings,
        enabled: updated.enabled,
        snapshot: updated.snapshot,
      });
      setSuccess('Пороги проверки сохранены');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сохранить пороги');
      throw caught;
    } finally {
      setActionLoading(null);
    }
  }

  async function onToggleCheckEnabled(checkId: string, enabled: boolean) {
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading(`check-toggle-${checkId}`);
    setError(null);

    try {
      const updated = await api.updateCheck(token, siteId, checkId, { enabled });
      mergeCheckUpdate(checkId, {
        enabled: updated.enabled,
        settings: updated.settings,
        snapshot: updated.snapshot,
      });
      setSuccess(enabled ? 'Проверка включена' : 'Проверка отключена — новые инциденты не создаются');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось изменить проверку');
    } finally {
      setActionLoading(null);
    }
  }

  async function onToggleSite() {
    if (!token || !siteId || !canManage) {
      return;
    }

    setActionLoading(isDisabled ? 'enable' : 'disable');
    setError(null);
    setSuccess(null);

    try {
      const updated = isDisabled
        ? await api.enableSite(token, siteId)
        : await api.disableSite(token, siteId);
      setSite((current) => (current ? { ...current, ...updated } : updated));
      setSuccess(isDisabled ? 'Сайт снова включён в мониторинг' : 'Сайт отключён от мониторинга');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось изменить статус сайта');
    } finally {
      setActionLoading(null);
    }
  }

  if (loading) {
    return <p className="text-sm text-slate-500">Загрузка...</p>;
  }

  if (error && !site) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>;
  }

  if (!site) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Сайт не найден</div>;
  }

  const status = getStatusMeta(site.status);
  const StatusIcon = status.icon;
  const hasActiveMaintenance = maintenanceWindows.some((window) => window.active);

  const checkSnapshot = (type: string): CheckSnapshot | null | undefined =>
    site.checks?.find((check) => check.type === type)?.snapshot;

  const sslMetric = getSnapshotMetricDisplay('ssl_expiry', checkSnapshot('ssl_expiry'));
  const domainMetric = getSnapshotMetricDisplay('domain_expiry', checkSnapshot('domain_expiry'));
  const licenseMetric = getSnapshotMetricDisplay('bitrix_license_expiry', checkSnapshot('bitrix_license_expiry'));

  function metricValue(display: ReturnType<typeof getSnapshotMetricDisplay>) {
    return (
      <>
        <span>{display.primary}</span>
        {display.secondary && <span className="mt-1 block text-xs font-normal text-slate-500">{display.secondary}</span>}
      </>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <Link to="/sites" className="text-sm text-brand-600 hover:text-brand-700">
            ← К списку сайтов
          </Link>
          <h2 className="mt-2 text-2xl font-semibold text-slate-900">{site.domain}</h2>
          <p className="mt-1 text-sm text-slate-500">{site.siteUrl}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            disabled={isDisabled || actionLoading === 'refresh'}
            onClick={onRefreshSite}
          >
            <RefreshCw className={`h-4 w-4 ${actionLoading === 'refresh' ? 'animate-spin' : ''}`} />
            {actionLoading === 'refresh' ? 'Обновление...' : 'Обновить данные'}
          </Button>
          {hasActiveMaintenance && (
            <Badge className="bg-violet-50 text-violet-700 ring-violet-200">
              <Wrench className="h-3.5 w-3.5" />
              Обслуживание
            </Badge>
          )}
          <Badge className={status.badgeClass}>
            <StatusIcon className="h-3.5 w-3.5" />
            {status.label}
          </Badge>
        </div>
      </div>

      {success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <SiteMetricCard
          title={CONNECTION_LABEL}
          icon={Radio}
          tone="brand"
          helpContent={CONNECTION_DESCRIPTION}
          helpLabel="Что такое связь с модулем"
          value={
            site.lastHeartbeatAt
              ? new Date(site.lastHeartbeatAt).toLocaleString('ru-RU')
              : CONNECTION_MISSING_LABEL
          }
        />
        <SiteMetricCard
          title="Открытые инциденты"
          icon={AlertTriangle}
          tone={site.openIncidents > 0 ? 'danger' : 'default'}
          value={site.openIncidents}
          valueClassName="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
        />
        <SiteMetricCard
          title="Версия Bitrix"
          icon={Box}
          tone="default"
          value={site.bitrixVersion ?? '—'}
          valueClassName="mt-2 text-sm font-medium text-slate-700"
        />
        <SiteMetricCard
          title="PHP"
          icon={Code2}
          tone="default"
          value={site.phpVersion ?? '—'}
          valueClassName="mt-2 text-sm font-medium text-slate-700"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <SiteMetricCard
          title="SSL-сертификат"
          icon={Lock}
          tone={sslMetric.tone}
          value={metricValue(sslMetric)}
          valueClassName="mt-2 text-sm font-semibold leading-snug text-slate-900"
        />
        <SiteMetricCard
          title="Домен"
          icon={Globe}
          tone={domainMetric.tone}
          value={metricValue(domainMetric)}
          valueClassName="mt-2 text-sm font-semibold leading-snug text-slate-900"
        />
        <SiteMetricCard
          title="Лицензия 1С-Битрикс"
          icon={ScrollText}
          tone={licenseMetric.tone}
          value={metricValue(licenseMetric)}
          valueClassName="mt-2 text-sm font-semibold leading-snug text-slate-900"
        />
      </div>

      <Card title="Окно обслуживания" description={MAINTENANCE_WINDOW_HELP.intro}>
        <details className="group rounded-xl border border-violet-200/80 bg-gradient-to-br from-violet-50 to-white">
          <summary className="cursor-pointer list-none px-4 py-3 text-sm font-medium text-violet-900 marker:content-none [&::-webkit-details-marker]:hidden">
            <span className="inline-flex items-center gap-2">
              <Wrench className="h-4 w-4 text-violet-600" />
              {MAINTENANCE_WINDOW_HELP.title}
              <span className="text-violet-600/70 group-open:hidden">— нажмите, чтобы развернуть</span>
            </span>
          </summary>
          <ul className="space-y-2 border-t border-violet-100 px-4 py-3 text-sm leading-relaxed text-violet-800/95">
            {MAINTENANCE_WINDOW_HELP.bullets.map((item) => (
              <li key={item} className="flex gap-2">
                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                {item}
              </li>
            ))}
          </ul>
        </details>
        {maintenanceWindows.length === 0 && (
          <p className="text-sm text-slate-500">Нет запланированных или активных окон.</p>
        )}
        {maintenanceWindows.length > 0 && (
          <div className="space-y-3">
            {maintenanceWindows.map((window) => (
              <div key={window.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                <div>
                  <div className="font-medium text-slate-900">{window.title}</div>
                  <div className="mt-1 text-sm text-slate-500">
                    {window.checkType ? getCheckTypeLabel(window.checkType) : 'Все проверки'} · до{' '}
                    {new Date(window.endsAt).toLocaleString('ru-RU')}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Badge
                    className={
                      window.active
                        ? 'bg-violet-50 text-violet-700 ring-violet-200'
                        : 'bg-slate-100 text-slate-600 ring-slate-200'
                    }
                  >
                    {window.active ? 'Активно' : 'Запланировано'}
                  </Badge>
                  {canManage && (
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={actionLoading === `maintenance-cancel-${window.id}`}
                      onClick={() => onCancelMaintenance(window.id)}
                    >
                      {actionLoading === `maintenance-cancel-${window.id}` ? '...' : 'Отменить'}
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

        {canManage && (
          <form onSubmit={onCreateMaintenance} className="mt-5 grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-2">
            <Input label="Название" value={maintenanceTitle} onChange={(e) => setMaintenanceTitle(e.target.value)} />
            <Input
              label="Длительность (часов)"
              type="number"
              min={1}
              max={168}
              value={maintenanceHours}
              onChange={(e) => setMaintenanceHours(e.target.value)}
            />
            <label className="block md:col-span-2">
              <span className="mb-1 block text-sm font-medium text-slate-700">Проверки</span>
              <select
                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                value={maintenanceCheckType}
                onChange={(e) => setMaintenanceCheckType(e.target.value)}
              >
                {MAINTENANCE_CHECK_OPTIONS.map((option) => (
                  <option key={option.value || 'all'} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <div className="md:col-span-2">
              <Button type="submit" disabled={actionLoading === 'maintenance-create'}>
                <Wrench className="h-4 w-4" />
                {actionLoading === 'maintenance-create' ? 'Создание...' : 'Включить обслуживание'}
              </Button>
            </div>
          </form>
        )}
      </Card>

      <Card
        title="Проверки"
        description="Включайте только нужные проверки и настройте пороги. Кнопка «Обновить данные» сверху запускает SSL/домен/uptime сразу; лицензия, диск и бэкап — с сайта через модуль Bitrix."
      >
        <div className="space-y-3">
          {(site.checks ?? []).map((check) => (
            <SiteCheckCard
              key={check.id}
              check={check}
              canManage={canManage}
              saving={actionLoading === `check-${check.id}`}
              toggling={actionLoading === `check-toggle-${check.id}`}
              onSave={(settings) => onSaveCheckSettings(check.id, settings)}
              onToggleEnabled={(enabled) => onToggleCheckEnabled(check.id, enabled)}
            />
          ))}
        </div>
        <p className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500 ring-1 ring-slate-100">
          Связь с модулем и доступность сайта (uptime) используют отдельные правила сервиса — см. блок «Связь с модулем»
          выше и карточку HTTP.
        </p>
      </Card>

      <Card title="Подключение модуля" description="Site ID и API secret для Bitrix-модуля. После ротации ключа обновите secret на сайте.">
        <p className="text-sm text-slate-600">Site ID:</p>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <code className="block flex-1 break-all rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-800">{site.id}</code>
          <Button type="button" variant="secondary" onClick={() => copyText(site.id)}>
            <Copy className="h-4 w-4" />
            Копировать
          </Button>
        </div>

        {rotatedSecret && (
          <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p className="text-sm font-medium text-amber-900">Новый API secret (показывается один раз):</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <code className="block flex-1 break-all rounded-lg bg-white px-3 py-2 text-xs text-slate-800">{rotatedSecret}</code>
              <Button type="button" variant="secondary" onClick={() => copyText(rotatedSecret)}>
                <Copy className="h-4 w-4" />
                Копировать
              </Button>
            </div>
          </div>
        )}

        {canManage && (
          <div className="mt-4 flex flex-wrap gap-2">
            <Button type="button" disabled={isDisabled || actionLoading === 'rotate'} onClick={onRotateKey}>
              <KeyRound className="h-4 w-4" />
              {actionLoading === 'rotate' ? 'Ротация...' : 'Сменить API secret'}
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={actionLoading === 'disable' || actionLoading === 'enable'}
              onClick={onToggleSite}
            >
              {isDisabled ? <Power className="h-4 w-4" /> : <PowerOff className="h-4 w-4" />}
              {actionLoading === 'disable' || actionLoading === 'enable'
                ? '...'
                : isDisabled
                  ? 'Включить мониторинг'
                  : 'Отключить мониторинг'}
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
