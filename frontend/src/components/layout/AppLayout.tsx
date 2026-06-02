import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';

type AppLayoutProps = {
  title: string;
  description?: string;
};

export function AppLayout({ title, description }: AppLayoutProps) {
  return (
    <div className="flex h-screen overflow-hidden bg-slate-50">
      <Sidebar />
      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <header className="shrink-0 border-b border-slate-200 bg-white/80 px-8 py-6 backdrop-blur">
          <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </header>
        <main className="min-h-0 flex-1 overflow-y-auto px-8 py-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
