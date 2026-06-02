import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { adminApi, type AdminOrganizationSummary } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';

export function AdminOrganizationsPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<AdminOrganizationSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      return;
    }

    adminApi
      .listOrganizations(token)
      .then((response) => setItems(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить проекты'))
      .finally(() => setLoading(false));
  }, [token]);

  return (
    <Card title="Все проекты" description="Организации клиентов Monitoring Service.">
      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
      {!loading && items.length === 0 && (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
          Проектов пока нет.
        </div>
      )}
      {!loading && items.length > 0 && (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <th className="px-3 py-2 font-medium">Проект</th>
                <th className="px-3 py-2 font-medium">Тариф</th>
                <th className="px-3 py-2 font-medium">Сайты</th>
                <th className="px-3 py-2 font-medium">Пользователи</th>
                <th className="px-3 py-2 font-medium">Инциденты</th>
                <th className="px-3 py-2 font-medium">Статус</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-b border-slate-100 hover:bg-slate-50">
                  <td className="px-3 py-3">
                    <Link to={`/admin/organizations/${item.id}`} className="font-medium text-brand-600 hover:text-brand-700">
                      {item.name}
                    </Link>
                    <div className="text-xs text-slate-500">{new Date(item.createdAt).toLocaleDateString('ru-RU')}</div>
                  </td>
                  <td className="px-3 py-3">{item.planLabel}</td>
                  <td className="px-3 py-3">{item.activeSitesCount} / {item.sitesCount}</td>
                  <td className="px-3 py-3">{item.usersCount}</td>
                  <td className="px-3 py-3">{item.openIncidents}</td>
                  <td className="px-3 py-3">
                    <Badge className={item.status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-red-50 text-red-700 ring-red-200'}>
                      {item.status}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
