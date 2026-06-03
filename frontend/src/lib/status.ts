import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, CheckCircle2, CircleDashed, XCircle } from 'lucide-react';

export type SiteStatus = 'pending' | 'ok' | 'warning' | 'critical' | 'disabled';

export const STATUS_META: Record<
  SiteStatus,
  { label: string; badgeClass: string; icon: LucideIcon }
> = {
  pending: {
    label: 'Ожидает',
    badgeClass: 'bg-slate-100 text-slate-700 ring-slate-200',
    icon: CircleDashed,
  },
  ok: {
    label: 'В норме',
    badgeClass: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    icon: CheckCircle2,
  },
  warning: {
    label: 'Предупреждение',
    badgeClass: 'bg-amber-50 text-amber-700 ring-amber-200',
    icon: AlertTriangle,
  },
  critical: {
    label: 'Критично',
    badgeClass: 'bg-red-50 text-red-700 ring-red-200',
    icon: XCircle,
  },
  disabled: {
    label: 'Отключён',
    badgeClass: 'bg-slate-100 text-slate-500 ring-slate-200',
    icon: CircleDashed,
  },
};

export function getStatusMeta(status: string) {
  return STATUS_META[status as SiteStatus] ?? STATUS_META.pending;
}
