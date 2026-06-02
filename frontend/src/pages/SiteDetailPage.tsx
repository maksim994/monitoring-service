import { useEffect, useState } from 'react';
import { Copy, KeyRound, Power, PowerOff } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { api, type SiteDetails } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { getCheckTypeLabel } from '../lib/incidents';
import { canManageSites } from '../lib/roles';
import { getStatusMeta } from '../lib/status';

export function SiteDetailPage() {
  const { siteId } = useParams();
  const { token, organization } = useAuth();
  const [site, setSite] = useState<(SiteDetails & { checks?: Array<{ id: string; type: string; enabled: boolean; intervalSeconds: number }> }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [rotatedSecret, setRotatedSecret] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const canManage = canManageSites(organization?.role);
  const isDisabled = site?.status === 'disabled';

  useEffect(() => {
    if (!token || !siteId) {
      return;
    }

    api
      .getSite(token, siteId)
      .then(setSite)
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить сайт'))
      .finally(() => setLoading(false));
  }, [token, siteId]);

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
        <Badge className={status.badgeClass}>
          <StatusIcon className="h-3.5 w-3.5" />
          {status.label}
        </Badge>
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
