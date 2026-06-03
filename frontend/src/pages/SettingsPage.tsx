import { type FormEvent, useEffect, useState } from 'react';
import { BellRing, Mail, MessageCircle, Plus, ScrollText, Send, Webhook } from 'lucide-react';
import { api, type AuditLogEntry, type NotificationChannel, type NotificationDeliveryEntry, type PlanUsage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Input } from '../components/ui/Input';
import { canManageChannels, canManagePlan, canViewAudit, roleLabel } from '../lib/roles';

type ChannelType = 'email' | 'telegram' | 'webhook';

const CHANNEL_META: Record<ChannelType, { label: string; icon: typeof Mail }> = {
  email: { label: 'Email', icon: Mail },
  telegram: { label: 'Telegram', icon: MessageCircle },
  webhook: { label: 'Webhook', icon: Webhook },
};

const ADD_CHANNEL_BUTTON_LABEL: Record<ChannelType, string> = {
  email: 'Добавить email',
  telegram: 'Добавить Telegram',
  webhook: 'Добавить webhook',
};

export function SettingsPage() {
  const { token, organization } = useAuth();
  const [channels, setChannels] = useState<NotificationChannel[]>([]);
  const [plan, setPlan] = useState<PlanUsage | null>(null);
  const [channelType, setChannelType] = useState<ChannelType>('email');
  const [name, setName] = useState('Email alerts');
  const [email, setEmail] = useState('');
  const [chatId, setChatId] = useState('');
  const [botToken, setBotToken] = useState('');
  const [webhookUrl, setWebhookUrl] = useState('');
  const [webhookSecret, setWebhookSecret] = useState('');
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [testingId, setTestingId] = useState<string | null>(null);
  const [auditLogs, setAuditLogs] = useState<AuditLogEntry[]>([]);
  const [deliveries, setDeliveries] = useState<NotificationDeliveryEntry[]>([]);
  const [changingPlan, setChangingPlan] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const canManage = canManageChannels(organization?.role);
  const canAudit = canViewAudit(organization?.role);
  const canChangePlan = canManagePlan(organization?.role);

  useEffect(() => {
    if (!token) {
      return;
    }

    const requests: Promise<void>[] = [
      api.listNotificationChannels(token).then((response) => setChannels(response.items)),
      api.getPlanUsage(token).then((response) => setPlan(response)),
    ];

    if (canAudit) {
      requests.push(
        api.listAuditLogs(token).then((response) => setAuditLogs(response.items)),
        api.listNotificationDeliveries(token).then((response) => setDeliveries(response.items)),
      );
    }

    Promise.all(requests)
      .catch((caught) => setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось загрузить настройки'))
      .finally(() => setLoading(false));
  }, [token, canAudit]);

  function onTypeChange(type: ChannelType) {
    setChannelType(type);
    setName(type === 'email' ? 'Email alerts' : type === 'telegram' ? 'Telegram alerts' : 'Webhook alerts');
  }

  async function onCreateChannel(event: FormEvent) {
    event.preventDefault();
    if (!token || !canManage) {
      return;
    }

    setCreating(true);
    setError(null);
    setSuccess(null);

    const settings: Record<string, string> =
      channelType === 'email'
        ? { email }
        : channelType === 'telegram'
          ? { chatId, ...(botToken ? { botToken } : {}) }
          : { url: webhookUrl, ...(webhookSecret ? { secret: webhookSecret } : {}) };

    try {
      const channel = await api.createNotificationChannel(token, { type: channelType, name, settings });
      setChannels((current) => [...current, channel]);
      setSuccess('Канал уведомлений добавлен');
      setEmail('');
      setChatId('');
      setBotToken('');
      setWebhookUrl('');
      setWebhookSecret('');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось создать канал');
    } finally {
      setCreating(false);
    }
  }

  async function onTestChannel(channelId: string) {
    if (!token) {
      return;
    }

    setTestingId(channelId);
    setError(null);
    setSuccess(null);

    try {
      await api.testNotificationChannel(token, channelId);
      setSuccess('Тестовое уведомление отправлено');
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось отправить тест');
    } finally {
      setTestingId(null);
    }
  }

  async function onChangePlan(planCode: string) {
    if (!token || !canChangePlan || planCode === plan?.planCode) {
      return;
    }

    setChangingPlan(planCode);
    setError(null);
    setSuccess(null);

    try {
      const updated = await api.changePlan(token, planCode);
      setPlan(updated);
      setSuccess(`Тариф изменён на ${updated.planLabel}`);
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сменить тариф');
    } finally {
      setChangingPlan(null);
    }
  }

  return (
    <div className="space-y-6">
      {plan && (
        <Card title="Тариф и лимиты" description={`Текущий план: ${plan.planLabel}. Роль: ${roleLabel(organization?.role)}.`}>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="truncate text-xs uppercase tracking-wide text-slate-500">Сайты</div>
              <div className="mt-1 text-2xl font-semibold text-slate-900">
                {plan.sites.used} / {plan.sites.limit}
              </div>
            </div>
            <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="truncate text-xs uppercase tracking-wide text-slate-500">Пользователи</div>
              <div className="mt-1 text-2xl font-semibold text-slate-900">
                {plan.users.used} / {plan.users.limit}
              </div>
            </div>
            <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="truncate text-xs uppercase tracking-wide text-slate-500">Uptime interval</div>
              <div className="mt-1 text-2xl font-semibold text-slate-900">{Math.round(plan.uptimeIntervalSeconds / 60)} мин</div>
            </div>
            <div className="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="truncate text-xs uppercase tracking-wide text-slate-500">Webhooks</div>
              <div className="mt-1 text-2xl font-semibold text-slate-900">
                {plan.webhooksEnabled ? `Да (${plan.webhooks.used})` : 'Недоступны'}
              </div>
            </div>
          </div>

          {canChangePlan && plan.availablePlans && plan.availablePlans.length > 0 && (
            <div className="mt-6 border-t border-slate-200 pt-5">
              <div className="mb-3 text-sm font-medium text-slate-900">Сменить тариф</div>
              <p className="mb-4 text-sm text-slate-500">
                Оплата в MVP не подключена. Смена тарифа сразу меняет лимиты организации.
              </p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {plan.availablePlans.map((option) => {
                  const isCurrent = option.code === plan.planCode;
                  return (
                    <div
                      key={option.code}
                      className={`min-w-0 rounded-xl border px-4 py-3 ${
                        isCurrent ? 'border-brand-300 bg-brand-50' : 'border-slate-200 bg-white'
                      }`}
                    >
                      <div className="font-semibold text-slate-900">{option.label}</div>
                      <div className="mt-2 space-y-1 text-xs text-slate-600">
                        <div>Сайты: {option.maxSites}</div>
                        <div>Пользователи: {option.maxUsers}</div>
                        <div>Uptime: {Math.round(option.uptimeIntervalSeconds / 60)} мин</div>
                        <div>Webhooks: {option.webhooksEnabled ? 'Да' : 'Нет'}</div>
                      </div>
                      <Button
                        type="button"
                        variant={isCurrent ? 'secondary' : 'primary'}
                        disabled={isCurrent || changingPlan === option.code}
                        className="mt-3 w-full"
                        onClick={() => onChangePlan(option.code)}
                      >
                        {isCurrent ? 'Текущий' : changingPlan === option.code ? '...' : 'Выбрать'}
                      </Button>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </Card>
      )}

      {canManage ? (
        <Card
          title="Добавить канал"
          description="Email, Telegram и webhook. SMTP и прокси — в env сервера. Открытые critical в Telegram повторяются каждый час (CRITICAL_TELEGRAM_REMINDER_SECONDS). В dev email в Mailhog: http://localhost:18025"
        >
          <div className="mb-4 flex flex-wrap gap-2">
            {(Object.keys(CHANNEL_META) as ChannelType[]).map((type) => {
              const disabled = type === 'webhook' && !!plan && !plan.webhooksEnabled;
              return (
                <button
                  key={type}
                  type="button"
                  disabled={disabled}
                  onClick={() => onTypeChange(type)}
                  className={`rounded-lg px-4 py-2 text-sm font-medium transition ${
                    channelType === type
                      ? 'bg-brand-600 text-white'
                      : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50'
                  }`}
                >
                  {CHANNEL_META[type].label}
                </button>
              );
            })}
          </div>

          <form onSubmit={onCreateChannel} className="grid gap-4 md:grid-cols-2">
            <Input label="Название канала" value={name} onChange={(e) => setName(e.target.value)} required />
            {channelType === 'email' && (
              <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="alerts@example.ru" required />
            )}
            {channelType === 'telegram' && (
              <>
                <Input
                  label="Bot Token"
                  type="password"
                  value={botToken}
                  onChange={(e) => setBotToken(e.target.value)}
                  placeholder="123456:ABC-DEF..."
                  required={false}
                />
                <Input label="Chat ID" value={chatId} onChange={(e) => setChatId(e.target.value)} placeholder="-1001234567890" required />
                <p className="md:col-span-2 text-sm text-slate-500">
                  Создайте бота через @BotFather, добавьте его в чат/группу и укажите chat id. Если токен задан глобально на сервере (TELEGRAM_BOT_TOKEN), поле можно оставить пустым.
                </p>
              </>
            )}
            {channelType === 'email' && (
              <p className="md:col-span-2 text-sm text-slate-500">
                Укажите email получателя. Отправка через SMTP (MAILER_DSN). Отправитель — MAILER_FROM или логин из DSN (должен совпадать с ящиком Mail.ru).
              </p>
            )}
            {channelType === 'webhook' && (
              <>
                <Input label="Webhook URL" value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} placeholder="https://hooks.example.com/alerts" required />
                <Input label="Secret (optional)" value={webhookSecret} onChange={(e) => setWebhookSecret(e.target.value)} placeholder="hmac-secret" />
              </>
            )}
            <div className="md:col-span-2">
              <Button type="submit" disabled={creating}>
                <Plus className="h-4 w-4" />
                {creating ? 'Сохранение...' : ADD_CHANNEL_BUTTON_LABEL[channelType]}
              </Button>
            </div>
          </form>
        </Card>
      ) : (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
          У вашей роли нет прав на управление каналами уведомлений.
        </div>
      )}

      {success && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

      <Card title="Каналы" description="Активные способы доставки алертов.">
        {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
        {!loading && channels.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Каналов пока нет. Добавьте email, Telegram или webhook.
          </div>
        )}
        {!loading && channels.length > 0 && (
          <div className="space-y-3">
            {channels.map((channel) => {
              const meta = CHANNEL_META[channel.type as ChannelType] ?? { label: channel.type, icon: BellRing };
              const Icon = meta.icon;
              const telegramDetail = channel.settings.chatId
                ? `chat ${channel.settings.chatId}${channel.settings.botTokenConfigured ? ', bot настроен' : ''}`
                : null;
              const detail =
                channel.settings.email ?? telegramDetail ?? channel.settings.url ?? channel.type;

              return (
                <div key={channel.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                      <Icon className="h-4 w-4" />
                    </div>
                    <div>
                      <div className="font-medium text-slate-900">{channel.name}</div>
                      <div className="text-sm text-slate-500">{meta.label}: {detail}</div>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">
                      {channel.enabled ? 'Активен' : 'Отключён'}
                    </span>
                    {canManage && (
                      <Button type="button" variant="secondary" disabled={testingId === channel.id} onClick={() => onTestChannel(channel.id)}>
                        <Send className="h-4 w-4" />
                        {testingId === channel.id ? '...' : 'Тест'}
                      </Button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </Card>

      {canAudit && (
        <>
          <Card title="Журнал аудита" description="Последние действия пользователей в организации.">
            {auditLogs.length === 0 ? (
              <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-8 text-center text-sm text-slate-500">
                Записей пока нет.
              </div>
            ) : (
              <div className="space-y-2">
                {auditLogs.map((entry) => (
                  <div key={entry.id} className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <div className="flex items-start gap-3">
                      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                        <ScrollText className="h-4 w-4" />
                      </div>
                      <div>
                        <div className="font-medium text-slate-900">{entry.message}</div>
                        <div className="text-xs text-slate-500">{entry.action}</div>
                      </div>
                    </div>
                    <div className="text-xs text-slate-500">{new Date(entry.createdAt).toLocaleString('ru-RU')}</div>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title="Доставка уведомлений" description="История отправок по каналам email, Telegram и webhook.">
            {deliveries.length === 0 ? (
              <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-8 text-center text-sm text-slate-500">
                Отправок пока нет.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                      <th className="px-3 py-2 font-medium">Канал</th>
                      <th className="px-3 py-2 font-medium">Статус</th>
                      <th className="px-3 py-2 font-medium">Время</th>
                    </tr>
                  </thead>
                  <tbody>
                    {deliveries.map((delivery) => (
                      <tr key={delivery.id} className="border-b border-slate-100">
                        <td className="px-3 py-3">
                          <div className="font-medium text-slate-900">{delivery.channelName}</div>
                          <div className="text-xs text-slate-500">{delivery.channelType}</div>
                          {delivery.error && <div className="mt-1 text-xs text-red-600">{delivery.error}</div>}
                        </td>
                        <td className="px-3 py-3">
                          <span
                            className={`rounded-md px-2 py-1 text-xs font-medium ${
                              delivery.status === 'sent'
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-red-50 text-red-700'
                            }`}
                          >
                            {delivery.status}
                          </span>
                        </td>
                        <td className="px-3 py-3 text-slate-600">
                          {new Date(delivery.sentAt ?? delivery.createdAt).toLocaleString('ru-RU')}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </>
      )}
    </div>
  );
}
