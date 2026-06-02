import { type FormEvent, useState } from 'react';
import { Activity, BellRing, Gauge, ShieldCheck } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';

export function LoginPage() {
  const { login, register } = useAuth();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [email, setEmail] = useState('demo@monitoring.local');
  const [password, setPassword] = useState('Demo123456');
  const [name, setName] = useState('');
  const [organizationName, setOrganizationName] = useState('Моя организация');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      if (mode === 'login') {
        await login(email, password);
      } else {
        await register({ email, password, name, organizationName });
      }
    } catch (caught) {
      const message = (caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось выполнить запрос';
      setError(message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="grid min-h-full overflow-y-auto lg:grid-cols-2 lg:overflow-hidden">
      <section className="relative hidden overflow-hidden bg-sidebar px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div>
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-600 shadow-lg shadow-brand-600/30">
              <Activity className="h-5 w-5" />
            </div>
            <div>
              <div className="text-lg font-semibold">Monitoring Service</div>
              <div className="text-sm text-slate-400">Мониторинг сайтов 1С-Битрикс</div>
            </div>
          </div>

          <div className="mt-16 max-w-lg">
            <h1 className="text-4xl font-semibold leading-tight tracking-tight">
              Единый кабинет для uptime, SSL, диска, бэкапов и агентов
            </h1>
            <p className="mt-4 text-base leading-7 text-slate-300">
              Контролируйте техническое здоровье Bitrix-проектов, получайте инциденты без шума и подключайте сайты за 15 минут.
            </p>
          </div>

          <div className="mt-10 grid gap-4">
            {[
              { icon: Gauge, title: 'Статус сайтов в реальном времени', text: 'Heartbeat, uptime и ключевые метрики в одном месте.' },
              { icon: ShieldCheck, title: 'Bitrix-диагностика', text: 'Агенты, бэкапы, обновления и свободное место на диске.' },
              { icon: BellRing, title: 'Уведомления без лишнего шума', text: 'Telegram, email и webhook с подтверждением проблем.' },
            ].map(({ icon: Icon, title, text }) => (
              <div key={title} className="flex gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <div className="rounded-xl bg-brand-600/20 p-3 text-brand-100">
                  <Icon className="h-5 w-5" />
                </div>
                <div>
                  <div className="font-medium text-white">{title}</div>
                  <div className="mt-1 text-sm text-slate-400">{text}</div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <p className="text-sm text-slate-500">© Monitoring Service · MVP</p>
      </section>

      <section className="flex items-center justify-center px-6 py-10">
        <div className="w-full max-w-md">
          <div className="mb-8 lg:hidden">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white">
                <Activity className="h-5 w-5" />
              </div>
              <div>
                <div className="font-semibold text-slate-900">Monitoring Service</div>
                <div className="text-sm text-slate-500">Кабинет мониторинга Bitrix</div>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-2xl font-semibold tracking-tight text-slate-900">
              {mode === 'login' ? 'Вход в кабинет' : 'Регистрация'}
            </h2>
            <p className="mt-2 text-sm text-slate-500">
              {mode === 'login'
                ? 'Используйте демо-доступ для локальной разработки.'
                : 'Создайте организацию и подключите первый сайт.'}
            </p>

            <div className="mt-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">
              Демо: <span className="font-medium">demo@monitoring.local</span> / <span className="font-medium">Demo123456</span>
            </div>

            <form onSubmit={onSubmit} className="mt-6 space-y-4">
              {mode === 'register' && (
                <>
                  <Input label="Имя" value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" required />
                  <Input
                    label="Организация"
                    value={organizationName}
                    onChange={(e) => setOrganizationName(e.target.value)}
                    autoComplete="organization"
                    required
                  />
                </>
              )}
              <Input
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="username"
                required
              />
              <Input
                label="Пароль"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                required
              />

              {error && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  {error}
                </div>
              )}

              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? 'Подождите...' : mode === 'login' ? 'Войти' : 'Создать аккаунт'}
              </Button>
            </form>

            <button
              type="button"
              onClick={() => setMode(mode === 'login' ? 'register' : 'login')}
              className="mt-4 w-full text-sm font-medium text-brand-600 hover:text-brand-700"
            >
              {mode === 'login' ? 'Создать аккаунт' : 'Уже есть аккаунт? Войти'}
            </button>
          </div>
        </div>
      </section>
    </div>
  );
}
