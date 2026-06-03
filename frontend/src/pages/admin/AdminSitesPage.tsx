import { useEffect, useState } from 'react';
import { adminApi, type AdminSiteSummary } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { getStatusMeta } from '../../lib/status';

export function AdminSitesPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<AdminSiteSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [updatingId, setUpdatingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      return;
    }

    adminApi
      .listSites(token)
      .then((response) => setItems(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить сайты'))
      .finally(() => setLoading(false));
  }, [token]);

  async function toggleSite(site: AdminSiteSummary) {
    if (!token) {
      return;
    }

    const nextStatus = site.status === 'disabled' ? 'ok' : 'disabled';
    setUpdatingId(site.id);
    setError(null);

    try {
      const updated = await adminApi.updateSiteStatus(token, site.id, nextStatus);
      setItems((current) => current.map((item) => (item.id === site.id ? updated : item)));
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось изменить статус');
    } finally {
      setUpdatingId(null);
    }
  }

  return (
    <Card title="Все сайты" description="Мониторинг всех подключённых Bitrix-проектов.">
      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
      {!loading && (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <th className="px-3 py-2 font-medium">Сайт</th>
                <th className="px-3 py-2 font-medium">Проект</th>
                <th className="px-3 py-2 font-medium">Связь</th>
                <th className="px-3 py-2 font-medium">Статус</th>
                <th className="px-3 py-2 font-medium">Действия</th>
              </tr>
            </thead>
            <tbody>
              {items.map((site) => {
                const meta = getStatusMeta(site.status);
                return (
                  <tr key={site.id} className="border-b border-slate-100">
                    <td className="px-3 py-3">
                      <div className="font-medium text-slate-900">{site.domain}</div>
                      <div className="text-xs text-slate-500">{site.siteUrl}</div>
                    </td>
                    <td className="px-3 py-3">{site.organizationName}</td>
                    <td className="px-3 py-3 text-slate-600">
                      {site.lastHeartbeatAt ? new Date(site.lastHeartbeatAt).toLocaleString('ru-RU') : '—'}
                    </td>
                    <td className="px-3 py-3">{meta.label}</td>
                    <td className="px-3 py-3">
                      <Button
                        type="button"
                        variant="secondary"
                        disabled={updatingId === site.id}
                        onClick={() => toggleSite(site)}
                      >
                        {updatingId === site.id ? '...' : site.status === 'disabled' ? 'Включить' : 'Отключить'}
                      </Button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
