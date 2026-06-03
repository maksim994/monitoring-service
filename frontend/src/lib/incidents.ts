import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, Info, XCircle } from 'lucide-react';

export type IncidentSeverity = 'info' | 'warning' | 'critical';
export type IncidentStatus = 'open' | 'acknowledged' | 'resolved' | 'muted';

export const INCIDENT_SEVERITY_META: Record<
  IncidentSeverity,
  { label: string; badgeClass: string; icon: LucideIcon }
> = {
  info: {
    label: 'Info',
    badgeClass: 'bg-slate-100 text-slate-700 ring-slate-200',
    icon: Info,
  },
  warning: {
    label: 'Warning',
    badgeClass: 'bg-amber-50 text-amber-700 ring-amber-200',
    icon: AlertTriangle,
  },
  critical: {
    label: 'Critical',
    badgeClass: 'bg-red-50 text-red-700 ring-red-200',
    icon: XCircle,
  },
};

export const INCIDENT_STATUS_META: Record<IncidentStatus, { label: string; badgeClass: string }> = {
  open: {
    label: 'Открыт',
    badgeClass: 'bg-red-50 text-red-700 ring-red-200',
  },
  acknowledged: {
    label: 'Подтверждён',
    badgeClass: 'bg-amber-50 text-amber-700 ring-amber-200',
  },
  resolved: {
    label: 'Закрыт',
    badgeClass: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  },
  muted: {
    label: 'Заглушён',
    badgeClass: 'bg-slate-100 text-slate-600 ring-slate-200',
  },
};

export function getIncidentSeverityMeta(severity: string) {
  return INCIDENT_SEVERITY_META[severity as IncidentSeverity] ?? INCIDENT_SEVERITY_META.info;
}

export function getIncidentStatusMeta(status: string) {
  return INCIDENT_STATUS_META[status as IncidentStatus] ?? INCIDENT_STATUS_META.open;
}

export function getCheckTypeLabel(checkType: string) {
  const labels: Record<string, string> = {
    heartbeat_missing: 'Нет heartbeat',
    uptime_http: 'Uptime HTTP',
    ssl_expiry: 'SSL сертификат',
    domain_expiry: 'Срок домена',
    disk_low: 'Мало места на диске',
    backup_stale: 'Устаревший бэкап',
    agents_lag: 'Просроченные agents',
    modules_updates: 'Обновления модулей',
  };

  return labels[checkType] ?? checkType;
}
