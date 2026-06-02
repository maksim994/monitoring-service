import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { AdminSidebar } from './AdminSidebar';

type AdminLayoutProps = {
  title: string;
  description?: string;
};

export function AdminLayout({ title, description }: AdminLayoutProps) {
  const { user } = useAuth();

  if (!user?.isPlatformAdmin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="flex h-screen overflow-hidden bg-slate-100">
      <AdminSidebar />
      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <header className="shrink-0 border-b border-slate-200 bg-white px-8 py-6">
          <div className="text-xs font-semibold uppercase tracking-wide text-amber-600">Platform Admin</div>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </header>
        <main className="min-h-0 flex-1 overflow-y-auto px-8 py-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
