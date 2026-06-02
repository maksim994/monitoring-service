import { useEffect, useState } from 'react';
import { adminApi, type AdminDashboard } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { Card } from '../../components/ui/Card';

export function AdminDashboardPage() {
  const { token } = useAuth();
  const [stats, setStats] = useState<AdminDashboard | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      return;
    }

    adminApi
      .dashboard(token)
      .then(setStats)
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить статистику'));
  }, [token]);

  if (error) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>;
  }

  if (!stats) {
    return <p className="text-sm text-slate-500">Загрузка...</p>;
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card title="Проекты (организации)">
        <p className="text-3xl font-semibold text-slate-900">{stats.organizations}</p>
      </Card>
      <Card title="Сайты всего">
        <p className="text-3xl font-semibold text-slate-900">{stats.sites}</p>
      </Card>
      <Card title="Активные сайты">
        <p className="text-3xl font-semibold text-slate-900">{stats.activeSites}</p>
      </Card>
      <Card title="Пользователи">
        <p className="text-3xl font-semibold text-slate-900">{stats.users}</p>
      </Card>
    </div>
  );
}
