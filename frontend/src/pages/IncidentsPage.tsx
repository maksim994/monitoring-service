import { useEffect, useMemo, useState } from 'react';
import { api, type IncidentSummary } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import {
  getCheckTypeLabel,
  getIncidentSeverityMeta,
  getIncidentStatusMeta,
  INCIDENT_SEVERITY_COLUMN_LABEL,
} from '../lib/incidents';
import { canManageIncidents } from '../lib/roles';

type StatusFilter = 'active' | 'all' | 'resolved';

export function IncidentsPage() {
  const { token, organization } = useAuth();
  const [incidents, setIncidents] = useState<IncidentSummary[]>([]);
  const [filter, setFilter] = useState<StatusFilter>('active');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionId, setActionId] = useState<string | null>(null);

  const canManage = canManageIncidents(organization?.role);

  useEffect(() => {
    if (!token) {
      return;
    }

    setLoading(true);
    setError(null);

    const status = filter === 'resolved' ? 'resolved' : undefined;

    api
      .listIncidents(token, status)
      .then((response) => {
        let items = response.items;
        if (filter === 'active') {
          items = items.filter((item) => item.status === 'open' || item.status === 'acknowledged');
        }
        setIncidents(items);
      })
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить инциденты'))
      .finally(() => setLoading(false));
  }, [token, filter]);

  const stats = useMemo(() => {
    const open = incidents.filter((item) => item.status === 'open').length;
    const acknowledged = incidents.filter((item) => item.status === 'acknowledged').length;
    const critical = incidents.filter((item) => item.severity === 'critical' && item.status !== 'resolved').length;

    return { open, acknowledged, critical };
  }, [incidents]);

  async function handleAcknowledge(incidentId: string) {
    if (!token) {
      return;
    }

    setActionId(incidentId);
    try {
      const updated = await api.acknowledgeIncident(token, incidentId);
      setIncidents((current) => current.map((item) => (item.id === incidentId ? updated : item)));
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось подтвердить инцидент');
    } finally {
      setActionId(null);
    }
  }

  async function handleResolve(incidentId: string) {
    if (!token) {
      return;
    }

    setActionId(incidentId);
    try {
      const updated = await api.resolveIncident(token, incidentId);
      setIncidents((current) =>
        filter === 'active' ? current.filter((item) => item.id !== incidentId) : current.map((item) => (item.id === incidentId ? updated : item)),
      );
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось закрыть инцидент');
    } finally {
      setActionId(null);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        {([
          ['active', 'Активные'],
          ['all', 'Все'],
          ['resolved', 'Закрытые'],
        ] as const).map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => setFilter(value)}
            className={`rounded-lg px-4 py-2 text-sm font-medium transition ${
              filter === value
                ? 'bg-brand-600 text-white shadow-sm'
                : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {filter === 'active' && !loading && incidents.length > 0 && (
        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-slate-500">Открытые</div>
            <div className="mt-1 text-2xl font-semibold text-slate-900">{stats.open}</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-slate-500">Подтверждённые</div>
            <div className="mt-1 text-2xl font-semibold text-slate-900">{stats.acknowledged}</div>
          </div>
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <div className="text-xs uppercase tracking-wide text-red-600">Критичные</div>
            <div className="mt-1 text-2xl font-semibold text-red-700">{stats.critical}</div>
          </div>
        </div>
      )}

      <Card title="Инциденты" description="Алерты по связи с модулем, uptime, диску, бэкапам и другим проверкам.">
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

        {!loading && incidents.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            {filter === 'active' ? 'Активных инцидентов нет.' : 'Инцидентов пока нет.'}
          </div>
        )}

        {!loading && incidents.length > 0 && (
          <div className="overflow-x-auto rounded-xl border border-slate-200">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Инцидент</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Сайт</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">{INCIDENT_SEVERITY_COLUMN_LABEL}</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Статус</th>
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Открыт</th>
                  <th className="px-4 py-3 text-right font-medium text-slate-500">Действия</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {incidents.map((incident) => {
                  const severity = getIncidentSeverityMeta(incident.severity);
                  const status = getIncidentStatusMeta(incident.status);
                  const SeverityIcon = severity.icon;
                  const isActive = incident.status === 'open' || incident.status === 'acknowledged';

                  return (
                    <tr key={incident.id} className="hover:bg-slate-50/80">
                      <td className="px-4 py-4">
                        <div className="font-medium text-slate-900">{incident.title}</div>
                        <div className="mt-1 text-xs text-slate-500">{getCheckTypeLabel(incident.checkType)}</div>
                      </td>
                      <td className="px-4 py-4">
                        <div className="font-medium text-slate-900">{incident.siteDomain}</div>
                        <div className="mt-1 font-mono text-xs text-slate-400">{incident.siteId}</div>
                      </td>
                      <td className="px-4 py-4">
                        <Badge className={severity.badgeClass}>
                          <SeverityIcon className="h-3.5 w-3.5" />
                          {severity.label}
                        </Badge>
                      </td>
                      <td className="px-4 py-4">
                        <Badge className={status.badgeClass}>{status.label}</Badge>
                      </td>
                      <td className="px-4 py-4 text-slate-600">
                        {new Date(incident.openedAt).toLocaleString('ru-RU')}
                      </td>
                      <td className="px-4 py-4">
                        {isActive && canManage && (
                          <div className="flex justify-end gap-2">
                            {incident.status === 'open' && (
                              <Button
                                type="button"
                                variant="secondary"
                                disabled={actionId === incident.id}
                                onClick={() => handleAcknowledge(incident.id)}
                              >
                                Подтвердить
                              </Button>
                            )}
                            <Button
                              type="button"
                              variant="secondary"
                              disabled={actionId === incident.id}
                              onClick={() => handleResolve(incident.id)}
                            >
                              Закрыть
                            </Button>
                          </div>
                        )}
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
