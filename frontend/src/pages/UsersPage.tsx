import { type FormEvent, useEffect, useState } from 'react';
import { Trash2, UserPlus } from 'lucide-react';
import { api, type OrganizationMember } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Input } from '../components/ui/Input';
import { canManageUsers, roleLabel } from '../lib/roles';

const ASSIGNABLE_ROLES = ['admin', 'integrator', 'operator', 'viewer'];

export function UsersPage() {
  const { token, organization, user } = useAuth();
  const [members, setMembers] = useState<OrganizationMember[]>([]);
  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState('viewer');
  const [loading, setLoading] = useState(true);
  const [inviting, setInviting] = useState(false);
  const [updatingId, setUpdatingId] = useState<string | null>(null);
  const [removingId, setRemovingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const canManage = canManageUsers(organization?.role);

  useEffect(() => {
    if (!token) {
      return;
    }

    api
      .listOrganizationUsers(token)
      .then((response) => setMembers(response.items))
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить пользователей'))
      .finally(() => setLoading(false));
  }, [token]);

  async function onInvite(event: FormEvent) {
    event.preventDefault();
    if (!token || !canManage) {
      return;
    }

    setInviting(true);
    setError(null);
    setSuccess(null);

    try {
      const member = await api.inviteOrganizationUser(token, {
        email,
        name,
        role,
        ...(password ? { password } : {}),
      });
      setMembers((current) => [...current, member]);
      setEmail('');
      setName('');
      setPassword('');
      setRole('viewer');
      setSuccess('Пользователь добавлен в организацию');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось пригласить пользователя');
    } finally {
      setInviting(false);
    }
  }

  async function onRoleChange(userId: string, newRole: string) {
    if (!token || !canManage) {
      return;
    }

    setUpdatingId(userId);
    setError(null);
    setSuccess(null);

    try {
      const updated = await api.updateOrganizationUserRole(token, userId, newRole);
      setMembers((current) => current.map((member) => (member.userId === userId ? updated : member)));
      setSuccess('Роль обновлена');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось обновить роль');
    } finally {
      setUpdatingId(null);
    }
  }

  async function onRemove(userId: string) {
    if (!token || !canManage) {
      return;
    }

    setRemovingId(userId);
    setError(null);
    setSuccess(null);

    try {
      await api.removeOrganizationUser(token, userId);
      setMembers((current) => current.filter((member) => member.userId !== userId));
      setSuccess('Пользователь удалён из организации');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось удалить пользователя');
    } finally {
      setRemovingId(null);
    }
  }

  return (
    <div className="space-y-6">
      {canManage ? (
        <Card title="Пригласить пользователя" description="Для нового email нужен пароль. Существующий пользователь будет добавлен без смены пароля.">
          <form onSubmit={onInvite} className="grid gap-4 md:grid-cols-2">
            <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            <Input label="Имя" value={name} onChange={(e) => setName(e.target.value)} required />
            <Input label="Пароль (для нового пользователя)" type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
            <label className="block text-sm">
              <span className="mb-1.5 block font-medium text-slate-700">Роль</span>
              <select
                value={role}
                onChange={(e) => setRole(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
              >
                {ASSIGNABLE_ROLES.map((option) => (
                  <option key={option} value={option}>
                    {roleLabel(option)}
                  </option>
                ))}
              </select>
            </label>
            <div className="md:col-span-2">
              <Button type="submit" disabled={inviting}>
                <UserPlus className="h-4 w-4" />
                {inviting ? 'Добавление...' : 'Добавить пользователя'}
              </Button>
            </div>
          </form>
        </Card>
      ) : (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
          У вашей роли нет прав на управление пользователями.
        </div>
      )}

      {success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <Card title="Участники организации" description="Роли определяют доступ к сайтам, инцидентам и настройкам.">
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {!loading && members.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Пользователей пока нет.
          </div>
        )}
        {!loading && members.length > 0 && (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2 font-medium">Пользователь</th>
                  <th className="px-3 py-2 font-medium">Роль</th>
                  <th className="px-3 py-2 font-medium">Добавлен</th>
                  {canManage && <th className="px-3 py-2 font-medium">Действия</th>}
                </tr>
              </thead>
              <tbody>
                {members.map((member) => {
                  const isOwner = member.role === 'owner';
                  const isSelf = member.userId === user?.id;

                  return (
                    <tr key={member.userId} className="border-b border-slate-100">
                      <td className="px-3 py-3">
                        <div className="font-medium text-slate-900">{member.name}</div>
                        <div className="text-slate-500">{member.email}</div>
                      </td>
                      <td className="px-3 py-3">
                        {canManage && !isOwner ? (
                          <select
                            value={member.role}
                            disabled={updatingId === member.userId}
                            onChange={(e) => onRoleChange(member.userId, e.target.value)}
                            className="rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-900"
                          >
                            {ASSIGNABLE_ROLES.map((option) => (
                              <option key={option} value={option}>
                                {roleLabel(option)}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                            {roleLabel(member.role)}
                          </span>
                        )}
                      </td>
                      <td className="px-3 py-3 text-slate-600">
                        {new Date(member.joinedAt).toLocaleString('ru-RU')}
                      </td>
                      {canManage && (
                        <td className="px-3 py-3">
                          {!isOwner && !isSelf && (
                            <Button
                              type="button"
                              variant="secondary"
                              disabled={removingId === member.userId}
                              onClick={() => onRemove(member.userId)}
                            >
                              <Trash2 className="h-4 w-4" />
                              {removingId === member.userId ? '...' : 'Удалить'}
                            </Button>
                          )}
                        </td>
                      )}
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
