import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, Globe2, HeartPulse } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api, type SiteSummary } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { StatCard } from '../components/ui/StatCard';
import { getStatusMeta } from '../lib/status';

export function OverviewPage() {
  const { token } = useAuth();
  const [sites, setSites] = useState<SiteSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      return;
    }

    api.listSites(token)
      .then((response) => setSites(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить сайты'))
      .finally(() => setLoading(false));
  }, [token]);

  const stats = useMemo(() => {
    const ok = sites.filter((site) => site.status === 'ok').length;
    const warning = sites.filter((site) => site.status === 'warning').length;
    const critical = sites.filter((site) => site.status === 'critical' || site.status === 'pending').length;
    const openIncidents = sites.reduce((sum, site) => sum + site.openIncidents, 0);

    return { total: sites.length, ok, warning, critical, openIncidents };
  }, [sites]);

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard title="Всего сайтов" value={stats.total} icon={Globe2} />
        <StatCard title="В норме" value={stats.ok} hint="Статус OK" icon={CheckCircle2} tone="success" />
        <StatCard title="Есть риски" value={stats.warning} hint="Warning-инциденты" icon={AlertTriangle} tone="warning" />
        <StatCard title="Требуют внимания" value={stats.critical} hint="Critical или без heartbeat" icon={HeartPulse} tone="danger" />
      </div>

      {stats.openIncidents > 0 && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
          Открытых инцидентов: <strong>{stats.openIncidents}</strong>.{' '}
          <Link to="/incidents" className="font-medium text-brand-700 hover:text-brand-600">
            Перейти к инцидентам
          </Link>
        </div>
      )}

      <Card
        title="Последние сайты"
        description="Быстрый обзор подключённых проектов"
        action={
          <Link to="/sites">
            <Button variant="secondary">Все сайты</Button>
          </Link>
        }
      >
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {!loading && sites.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center">
            <p className="text-sm text-slate-500">Сайтов пока нет. Добавьте первый проект на странице «Сайты».</p>
            <Link to="/sites" className="mt-4 inline-block">
              <Button>Добавить сайт</Button>
            </Link>
          </div>
        )}

        {!loading && sites.length > 0 && (
          <div className="overflow-hidden rounded-xl border border-slate-200">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Сайт</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Статус</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Heartbeat</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {sites.slice(0, 5).map((site) => {
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
