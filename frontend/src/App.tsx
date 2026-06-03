import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth/AuthContext';
import { AdminLayout } from './components/layout/AdminLayout';
import { AppLayout } from './components/layout/AppLayout';
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage';
import { AdminOrganizationDetailPage } from './pages/admin/AdminOrganizationDetailPage';
import { AdminOrganizationsPage } from './pages/admin/AdminOrganizationsPage';
import { AdminPlansPage } from './pages/admin/AdminPlansPage';
import { AdminSitesPage } from './pages/admin/AdminSitesPage';
import { IncidentsPage } from './pages/IncidentsPage';
import { LoginPage } from './pages/LoginPage';
import { OverviewPage } from './pages/OverviewPage';
import { SiteDetailPage } from './pages/SiteDetailPage';
import { SettingsPage } from './pages/SettingsPage';
import { SitesPage } from './pages/SitesPage';
import { UsersPage } from './pages/UsersPage';

const PAGE_META: Record<string, { title: string; description: string }> = {
  '/': {
    title: 'Обзор',
    description: 'Сводка по сайтам, связь с модулем и текущие риски.',
  },
  '/sites': {
    title: 'Сайты',
    description: 'Подключение Bitrix-проектов и управление ключами API.',
  },
  '/incidents': {
    title: 'Инциденты',
    description: 'Активные алерты и история проблем по сайтам.',
  },
  '/settings': {
    title: 'Настройки',
    description: 'Каналы уведомлений и параметры организации.',
  },
  '/users': {
    title: 'Пользователи',
    description: 'Участники организации и роли доступа.',
  },
};

const ADMIN_META: Record<string, { title: string; description: string }> = {
  '/admin': {
    title: 'Обзор платформы',
    description: 'Сводная статистика по всем проектам и сайтам.',
  },
  '/admin/organizations': {
    title: 'Проекты',
    description: 'Все организации-клиенты Monitoring Service.',
  },
  '/admin/sites': {
    title: 'Сайты',
    description: 'Все подключённые Bitrix-сайты на платформе.',
  },
  '/admin/plans': {
    title: 'Тарифы',
    description: 'Управление лимитами и тарифными планами.',
  },
};

function ProtectedApp() {
  const { token, user, initializing } = useAuth();
  const location = useLocation();
  const isAdminRoute = location.pathname.startsWith('/admin');
  const meta = isAdminRoute
    ? ADMIN_META[location.pathname] ?? ADMIN_META['/admin']
    : PAGE_META[location.pathname] ?? PAGE_META['/'];

  if (initializing) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <div className="rounded-2xl border border-slate-200 bg-white px-8 py-6 shadow-sm">
          <p className="text-sm text-slate-500">Проверка сессии...</p>
        </div>
      </div>
    );
  }

  if (!token || !user) {
    return <LoginPage />;
  }

  return (
    <div className="h-full">
      <Routes>
      <Route element={<AdminLayout title={meta.title} description={meta.description} />}>
        <Route path="admin" element={<AdminDashboardPage />} />
        <Route path="admin/organizations" element={<AdminOrganizationsPage />} />
        <Route path="admin/organizations/:organizationId" element={<AdminOrganizationDetailPage />} />
        <Route path="admin/sites" element={<AdminSitesPage />} />
        <Route path="admin/plans" element={<AdminPlansPage />} />
      </Route>
      <Route element={<AppLayout title={meta.title} description={meta.description} />}>
        <Route index element={<OverviewPage />} />
        <Route path="sites" element={<SitesPage />} />
        <Route path="sites/:siteId" element={<SiteDetailPage />} />
        <Route path="incidents" element={<IncidentsPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
    </div>
  );
}

function App() {
  return (
    <AuthProvider>
      <ProtectedApp />
    </AuthProvider>
  );
}

export default App;
