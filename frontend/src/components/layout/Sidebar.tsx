import { Activity, Globe2, LayoutDashboard, LogOut, Settings, Shield, ShieldAlert, Users } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { canManageUsers } from '../../lib/roles';

const navItems = [
  { to: '/', label: 'Обзор', icon: LayoutDashboard, end: true },
  { to: '/sites', label: 'Сайты', icon: Globe2 },
  { to: '/incidents', label: 'Инциденты', icon: ShieldAlert },
  { to: '/users', label: 'Пользователи', icon: Users, roles: ['owner', 'admin'] as const },
  { to: '/settings', label: 'Настройки', icon: Settings },
];

export function Sidebar() {
  const { user, organization, logout } = useAuth();

  return (
    <aside className="flex h-full w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar text-slate-300">
      <div className="border-b border-sidebar-border px-5 py-6">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-lg shadow-brand-600/30">
            <Activity className="h-5 w-5" />
          </div>
          <div>
            <div className="text-sm font-semibold text-white">Monitoring</div>
            <div className="text-xs text-slate-400">Bitrix SaaS</div>
          </div>
        </div>
      </div>

      <nav className="flex-1 space-y-1 px-3 py-4">
        {user?.isPlatformAdmin && (
          <NavLink
            to="/admin"
            className={({ isActive }) =>
              `mb-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                isActive
                  ? 'bg-amber-500/20 text-amber-200'
                  : 'text-amber-300 hover:bg-sidebar-hover hover:text-white'
              }`
            }
          >
            <Shield className="h-4 w-4" />
            Админка платформы
          </NavLink>
        )}
        {navItems
          .filter((item) => !('roles' in item) || canManageUsers(organization?.role))
          .map(({ to, label, icon: Icon, end }) => (
          <NavLink
            key={to}
            to={to}
            end={end}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                isActive
                  ? 'bg-sidebar-hover text-white'
                  : 'text-slate-300 hover:bg-sidebar-hover hover:text-white'
              }`
            }
          >
            <Icon className="h-4 w-4" />
            {label}
          </NavLink>
        ))}
      </nav>

      <div className="border-t border-sidebar-border p-4">
        <div className="rounded-xl bg-sidebar-hover p-3">
          <div className="truncate text-sm font-medium text-white">{user?.name}</div>
          <div className="truncate text-xs text-slate-400">{organization?.name}</div>
          <button
            type="button"
            onClick={logout}
            className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white"
          >
            <LogOut className="h-4 w-4" />
            Выйти
          </button>
        </div>
      </div>
    </aside>
  );
}
