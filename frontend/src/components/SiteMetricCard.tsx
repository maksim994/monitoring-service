import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { HelpHint } from './ui/HelpHint';

type Tone = 'brand' | 'default' | 'success' | 'warning' | 'danger';

const toneClasses: Record<Tone, string> = {
  brand: 'bg-brand-50 text-brand-600 ring-brand-100',
  default: 'bg-slate-50 text-slate-600 ring-slate-100',
  success: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
  warning: 'bg-amber-50 text-amber-600 ring-amber-100',
  danger: 'bg-red-50 text-red-600 ring-red-100',
};

type SiteMetricCardProps = {
  title: string;
  value: ReactNode;
  icon: LucideIcon;
  tone?: Tone;
  helpContent?: string;
  helpLabel?: string;
  valueClassName?: string;
  className?: string;
};

export function SiteMetricCard({
  title,
  value,
  icon: Icon,
  tone = 'default',
  helpContent,
  helpLabel,
  valueClassName = 'mt-2 text-sm font-semibold text-slate-900',
  className = '',
}: SiteMetricCardProps) {
  return (
    <div
      className={`overflow-visible rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <p className="inline-flex flex-wrap items-center gap-1.5 text-sm font-medium text-slate-500">
            {title}
            {helpContent && <HelpHint content={helpContent} label={helpLabel ?? `Пояснение: ${title}`} />}
          </p>
          <div className={valueClassName}>{value}</div>
        </div>
        <div
          className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ${toneClasses[tone]}`}
        >
          <Icon className="h-5 w-5" strokeWidth={2} />
        </div>
      </div>
    </div>
  );
}
