import { type FormEvent, useEffect, useState } from 'react';
import { Copy, Plus } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api, type SiteDetails, type SiteSummary } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Input } from '../components/ui/Input';
import { getStatusMeta } from '../lib/status';
import { canManageSites } from '../lib/roles';

export function SitesPage() {
  const { token, organization } = useAuth();
  const [sites, setSites] = useState<SiteSummary[]>([]);
  const [domain, setDomain] = useState('');
  const [siteUrl, setSiteUrl] = useState('');
  const [createdSite, setCreatedSite] = useState<{ siteId: string; apiSecret: string; site: SiteDetails } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showForm, setShowForm] = useState(false);

  useEffect(() => {
    if (!token) {
      return;
    }

    api.listSites(token)
      .then((response) => setSites(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить сайты'))
      .finally(() => setLoading(false));
  }, [token]);

  async function onCreateSite(event: FormEvent) {
    event.preventDefault();
    if (!token) {
      return;
    }

    setCreating(true);
    setError(null);

    try {
      const response = await api.createSite(token, { domain, siteUrl });
      setCreatedSite(response);
      setSites((current) => [response.site, ...current]);
      setDomain('');
      setSiteUrl('');
      setShowForm(false);
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось создать сайт');
    } finally {
      setCreating(false);
    }
  }

  async function copyText(value: string) {
    await navigator.clipboard.writeText(value);
  }

  const canCreate = canManageSites(organization?.role);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-sm text-slate-500">Управление подключёнными Bitrix-проектами</p>
        </div>
        {canCreate && (
          <Button type="button" onClick={() => setShowForm((value) => !value)}>
            <Plus className="h-4 w-4" />
            Добавить сайт
          </Button>
        )}
      </div>

      {showForm && canCreate && (
        <Card title="Новый сайт" description="После создания вы получите site ID и API secret для Bitrix-модуля.">
          <form onSubmit={onCreateSite} className="grid gap-4 md:grid-cols-2">
            <Input label="Домен" value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="example.ru" required />
            <Input
              label="URL сайта"
              value={siteUrl}
              onChange={(e) => setSiteUrl(e.target.value)}
              placeholder="https://example.ru"
              required
            />
            <div className="md:col-span-2 flex gap-3">
              <Button type="submit" disabled={creating}>
                {creating ? 'Создание...' : 'Создать сайт'}
              </Button>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                Отмена
              </Button>
            </div>
          </form>
        </Card>
      )}

      {createdSite && (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
          <div className="font-medium text-emerald-900">Сайт успешно создан</div>
          <p className="mt-1 text-sm text-emerald-700">Секрет показывается только один раз. Сохраните его для настройки Bitrix-модуля.</p>
          <div className="mt-4 grid gap-3 md:grid-cols-2">
            {[
              { label: 'Site ID', value: createdSite.siteId },
              { label: 'API Secret', value: createdSite.apiSecret },
            ].map(({ label, value }) => (
              <div key={label} className="rounded-xl border border-emerald-200 bg-white px-4 py-3">
                <div className="text-xs font-medium uppercase tracking-wide text-emerald-700">{label}</div>
                <div className="mt-2 flex items-start justify-between gap-3">
                  <code className="break-all text-sm text-slate-800">{value}</code>
                  <button
                    type="button"
                    onClick={() => copyText(value)}
                    className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    title="Копировать"
                  >
                    <Copy className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <Card title="Список сайтов" description="Статус подключения, heartbeat и идентификаторы.">
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {!loading && sites.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Сайтов пока нет. Нажмите «Добавить сайт», чтобы получить ключи подключения.
          </div>
        )}

        {!loading && sites.length > 0 && (
          <div className="overflow-x-auto rounded-xl border border-slate-200">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Домен</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Статус</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Heartbeat</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Инциденты</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {sites.map((site) => {
                  const status = getStatusMeta(site.status);
                  const StatusIcon = status.icon;

                  return (
                    <tr key={site.id} className="hover:bg-slate-50/80">
                      <td className="px-4 py-4">
                        <Link to={`/sites/${site.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                          {site.domain}
                        </Link>
                        <div className="mt-1 font-mono text-xs text-slate-400">{site.id}</div>
                      </td>
                      <td className="px-4 py-4">
                        <Badge className={status.badgeClass}>
                          <StatusIcon className="h-3.5 w-3.5" />
                          {status.label}
                        </Badge>
                      </td>
                      <td className="px-4 py-4 text-slate-600">
                        {site.lastHeartbeatAt
                          ? new Date(site.lastHeartbeatAt).toLocaleString('ru-RU')
                          : 'Нет heartbeat'}
                      </td>
                      <td className="px-4 py-4 text-slate-600">{site.openIncidents}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
