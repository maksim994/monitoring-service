import { type FormEvent, useEffect, useState } from 'react';
import { Copy, KeyRound, Power, PowerOff, Wrench } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { api, type MaintenanceWindow, type SiteDetails } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Input } from '../components/ui/Input';
import { getCheckTypeLabel } from '../lib/incidents';
import { canManageSites } from '../lib/roles';
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
];

export function SiteDetailPage() {
  const { siteId } = useParams();
  const { token, organization } = useAuth();
  const [site, setSite] = useState<(SiteDetails & { checks?: Array<{ id: string; type: string; enabled: boolean; intervalSeconds: number }> }) | null>(null);
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
        <Card title="Heartbeat">
          <p className="text-sm text-slate-600">
            {site.lastHeartbeatAt ? new Date(site.lastHeartbeatAt).toLocaleString('ru-RU') : 'Нет heartbeat'}
          </p>
        </Card>
        <Card title="Открытые инциденты">
          <p className="text-2xl font-semibold text-slate-900">{site.openIncidents}</p>
        </Card>
        <Card title="Bitrix">
          <p className="text-sm text-slate-600">{site.bitrixVersion ?? '—'}</p>
        </Card>
        <Card title="PHP">
          <p className="text-sm text-slate-600">{site.phpVersion ?? '—'}</p>
        </Card>
      </div>

      <Card
        title="Окно обслуживания"
        description="Во время окна новые инциденты не создаются и не отправляются напоминания в Telegram. Уже открытые инциденты остаются; при восстановлении закрываются как обычно."
      >
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

      <Card title="Проверки" description="Активные правила мониторинга для сайта.">
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Тип</th>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Интервал</th>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Статус</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {(site.checks ?? []).map((check) => (
                <tr key={check.id}>
                  <td className="px-4 py-4 font-medium text-slate-900">{getCheckTypeLabel(check.type)}</td>
                  <td className="px-4 py-4 text-slate-600">{Math.round(check.intervalSeconds / 60)} мин</td>
                  <td className="px-4 py-4">
                    <Badge className={check.enabled ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'}>
                      {check.enabled ? 'Включена' : 'Отключена'}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
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
