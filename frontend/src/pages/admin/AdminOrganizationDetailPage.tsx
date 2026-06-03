import { type FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { adminApi, type AdminOrganizationDetails, type AdminPlan } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { Input } from '../../components/ui/Input';
import { getStatusMeta } from '../../lib/status';

export function AdminOrganizationDetailPage() {
  const { organizationId } = useParams();
  const { token } = useAuth();
  const [org, setOrg] = useState<AdminOrganizationDetails | null>(null);
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [name, setName] = useState('');
  const [planCode, setPlanCode] = useState('');
  const [status, setStatus] = useState('active');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!token || !organizationId) {
      return;
    }

    Promise.all([
      adminApi.getOrganization(token, organizationId),
      adminApi.listPlans(token),
    ])
      .then(([organization, plansResponse]) => {
        setOrg(organization);
        setName(organization.name);
        setPlanCode(organization.planCode);
        setStatus(organization.status);
        setPlans(plansResponse.items.filter((plan) => plan.active));
      })
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить проект'))
      .finally(() => setLoading(false));
  }, [token, organizationId]);

  async function onSave(event: FormEvent) {
    event.preventDefault();
    if (!token || !organizationId) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      const updated = await adminApi.updateOrganization(token, organizationId, { name, planCode, status });
      setOrg((current) => (current ? { ...current, ...updated } : current));
      setSuccess('Настройки проекта сохранены');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сохранить');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return <p className="text-sm text-slate-500">Загрузка...</p>;
  }

  if (error && !org) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>;
  }

  if (!org) {
    return null;
  }

  return (
    <div className="space-y-6">
      <Link to="/admin/organizations" className="text-sm text-brand-600 hover:text-brand-700">
        ← К списку проектов
      </Link>

      {success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <Card title="Настройки проекта" description="Имя, тариф и статус организации.">
        <form onSubmit={onSave} className="grid max-w-2xl gap-4">
          <Input label="Название" value={name} onChange={(e) => setName(e.target.value)} required />
          <label className="block text-sm">
            <span className="mb-1.5 block font-medium text-slate-700">Тариф</span>
            <select
              value={planCode}
              onChange={(e) => setPlanCode(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              {plans.map((plan) => (
                <option key={plan.code} value={plan.code}>
                  {plan.label} — до {plan.maxSites} сайтов
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1.5 block font-medium text-slate-700">Статус</span>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="active">active</option>
              <option value="suspended">suspended</option>
            </select>
          </label>
          <div>
            <Button type="submit" disabled={saving}>{saving ? 'Сохранение...' : 'Сохранить'}</Button>
          </div>
        </form>
      </Card>

      <Card title="Использование лимитов">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="truncate text-xs uppercase text-slate-500">Сайты</div>
            <div className="mt-1 text-xl font-semibold">{org.usage.sites.used} / {org.usage.sites.limit}</div>
          </div>
          <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="truncate text-xs uppercase text-slate-500">Пользователи</div>
            <div className="mt-1 text-xl font-semibold">{org.usage.users.used} / {org.usage.users.limit}</div>
          </div>
          <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="truncate text-xs uppercase text-slate-500">Uptime interval</div>
            <div className="mt-1 text-xl font-semibold">{Math.round(org.usage.uptimeIntervalSeconds / 60)} мин</div>
          </div>
        </div>
      </Card>

      <Card title="Сайты проекта">
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <th className="px-3 py-2 font-medium">Домен</th>
                <th className="px-3 py-2 font-medium">Связь</th>
                <th className="px-3 py-2 font-medium">Инциденты</th>
                <th className="px-3 py-2 font-medium">Статус</th>
              </tr>
            </thead>
            <tbody>
              {org.sites.map((site) => {
                const meta = getStatusMeta(site.status);
                return (
                  <tr key={site.id} className="border-b border-slate-100">
                    <td className="px-3 py-3">
                      <div className="font-medium text-slate-900">{site.domain}</div>
                      <div className="text-xs text-slate-500">{site.siteUrl}</div>
                    </td>
                    <td className="px-3 py-3 text-slate-600">
                      {site.lastHeartbeatAt ? new Date(site.lastHeartbeatAt).toLocaleString('ru-RU') : '—'}
                    </td>
                    <td className="px-3 py-3">{site.openIncidents}</td>
                    <td className="px-3 py-3">{meta.label}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
