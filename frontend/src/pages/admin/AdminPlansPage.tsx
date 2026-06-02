import { type FormEvent, useEffect, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { adminApi, type AdminPlan } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { Input } from '../../components/ui/Input';

export function AdminPlansPage() {
  const { token } = useAuth();
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingCode, setSavingCode] = useState<string | null>(null);
  const [deletingCode, setDeletingCode] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [newCode, setNewCode] = useState('');
  const [newLabel, setNewLabel] = useState('');
  const [newMaxSites, setNewMaxSites] = useState('10');
  const [newMaxUsers, setNewMaxUsers] = useState('5');
  const [newInterval, setNewInterval] = useState('300');
  const [newWebhooks, setNewWebhooks] = useState(true);

  function loadPlans() {
    if (!token) {
      return;
    }

    adminApi
      .listPlans(token)
      .then((response) => setPlans(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить тарифы'))
      .finally(() => setLoading(false));
  }

  useEffect(loadPlans, [token]);

  async function onCreatePlan(event: FormEvent) {
    event.preventDefault();
    if (!token) {
      return;
    }

    setCreating(true);
    setError(null);
    setSuccess(null);

    try {
      const plan = await adminApi.createPlan(token, {
        code: newCode.toLowerCase(),
        label: newLabel,
        maxSites: Number(newMaxSites),
        maxUsers: Number(newMaxUsers),
        uptimeIntervalSeconds: Number(newInterval),
        webhooksEnabled: newWebhooks,
      });
      setPlans((current) => [...current, plan]);
      setShowForm(false);
      setNewCode('');
      setNewLabel('');
      setSuccess('Тариф создан');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось создать тариф');
    } finally {
      setCreating(false);
    }
  }

  async function savePlan(plan: AdminPlan, patch: Partial<AdminPlan>) {
    if (!token) {
      return;
    }

    setSavingCode(plan.code);
    setError(null);
    setSuccess(null);

    try {
      const updated = await adminApi.updatePlan(token, plan.code, {
        label: patch.label ?? plan.label,
        maxSites: patch.maxSites ?? plan.maxSites,
        maxUsers: patch.maxUsers ?? plan.maxUsers,
        uptimeIntervalSeconds: patch.uptimeIntervalSeconds ?? plan.uptimeIntervalSeconds,
        webhooksEnabled: patch.webhooksEnabled ?? plan.webhooksEnabled,
        active: patch.active ?? plan.active,
      });
      setPlans((current) => current.map((item) => (item.code === plan.code ? updated : item)));
      setSuccess(`Тариф ${plan.label} обновлён`);
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сохранить тариф');
    } finally {
      setSavingCode(null);
    }
  }

  async function onDeletePlan(plan: AdminPlan) {
    if (!token) {
      return;
    }

    const confirmed = window.confirm(
      `Удалить тариф «${plan.label}» (${plan.code})?\n\nУдаление невозможно, если тариф назначен организациям.`,
    );
    if (!confirmed) {
      return;
    }

    setDeletingCode(plan.code);
    setError(null);
    setSuccess(null);

    try {
      await adminApi.deletePlan(token, plan.code);
      setPlans((current) => current.filter((item) => item.code !== plan.code));
      setSuccess(`Тариф ${plan.label} удалён`);
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось удалить тариф');
    } finally {
      setDeletingCode(null);
    }
  }

  return (
    <div className="space-y-6">
      {success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <div className="flex justify-end">
        <Button type="button" onClick={() => setShowForm((value) => !value)}>
          <Plus className="h-4 w-4" />
          {showForm ? 'Скрыть форму' : 'Новый тариф'}
        </Button>
      </div>

      {showForm && (
        <Card title="Создать тариф">
          <form onSubmit={onCreatePlan} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input label="Code (slug)" value={newCode} onChange={(e) => setNewCode(e.target.value)} placeholder="pro" required />
            <Input label="Название" value={newLabel} onChange={(e) => setNewLabel(e.target.value)} required />
            <Input label="Max sites" type="number" value={newMaxSites} onChange={(e) => setNewMaxSites(e.target.value)} required />
            <Input label="Max users" type="number" value={newMaxUsers} onChange={(e) => setNewMaxUsers(e.target.value)} required />
            <Input label="Uptime interval (sec)" type="number" value={newInterval} onChange={(e) => setNewInterval(e.target.value)} required />
            <label className="flex min-w-0 items-center gap-2 self-end pb-2.5 text-sm text-slate-700">
              <input type="checkbox" checked={newWebhooks} onChange={(e) => setNewWebhooks(e.target.checked)} />
              <span className="whitespace-nowrap">Webhooks enabled</span>
            </label>
            <div className="sm:col-span-2">
              <Button type="submit" disabled={creating}>{creating ? 'Создание...' : 'Создать'}</Button>
            </div>
          </form>
        </Card>
      )}

      <Card title="Тарифы" description="Лимиты применяются ко всем организациям на соответствующем тарифе.">
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {!loading && (
          <div className="space-y-4">
            {plans.map((plan) => (
              <div key={plan.code} className={`rounded-xl border p-4 ${plan.active ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 opacity-70'}`}>
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 text-xs text-slate-500">code: {plan.code}</div>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className={`rounded-md px-2 py-1 text-xs font-medium ${plan.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600'}`}>
                      {plan.active ? 'Активен' : 'Отключён'}
                    </span>
                    <Button
                      type="button"
                      variant="danger"
                      disabled={deletingCode === plan.code}
                      onClick={() => onDeletePlan(plan)}
                    >
                      <Trash2 className="h-4 w-4" />
                      {deletingCode === plan.code ? '...' : 'Удалить'}
                    </Button>
                  </div>
                </div>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <Input
                    key={`${plan.code}-label-${plan.label}`}
                    label="Название"
                    defaultValue={plan.label}
                    onBlur={(e) => {
                      const value = e.target.value.trim();
                      if (value !== '' && value !== plan.label) {
                        void savePlan(plan, { label: value });
                      }
                    }}
                  />
                  <Input
                    label="Max sites"
                    type="number"
                    defaultValue={plan.maxSites}
                    onBlur={(e) => {
                      const value = Number(e.target.value);
                      if (value !== plan.maxSites) {
                        void savePlan(plan, { maxSites: value });
                      }
                    }}
                  />
                  <Input
                    label="Max users"
                    type="number"
                    defaultValue={plan.maxUsers}
                    onBlur={(e) => {
                      const value = Number(e.target.value);
                      if (value !== plan.maxUsers) {
                        void savePlan(plan, { maxUsers: value });
                      }
                    }}
                  />
                  <Input
                    label="Interval (sec)"
                    type="number"
                    defaultValue={plan.uptimeIntervalSeconds}
                    onBlur={(e) => {
                      const value = Number(e.target.value);
                      if (value !== plan.uptimeIntervalSeconds) {
                        void savePlan(plan, { uptimeIntervalSeconds: value });
                      }
                    }}
                  />
                  <div className="min-w-0">
                    <span className="mb-2 block truncate text-sm font-medium text-slate-700">Webhooks</span>
                    <label className="flex h-[42px] items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5">
                      <input
                        type="checkbox"
                        defaultChecked={plan.webhooksEnabled}
                        onChange={(e) => void savePlan(plan, { webhooksEnabled: e.target.checked })}
                      />
                      <span className="text-sm text-slate-700">{plan.webhooksEnabled ? 'Включены' : 'Выключены'}</span>
                    </label>
                  </div>
                </div>
                {savingCode === plan.code && <p className="mt-2 text-xs text-slate-500">Сохранение...</p>}
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
