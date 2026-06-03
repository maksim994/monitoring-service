import type { ReactNode } from 'react';

type CardProps = {
  children: ReactNode;
  className?: string;
  title?: ReactNode;
  description?: string;
  action?: ReactNode;
};

export function Card({ children, className = '', title, description, action }: CardProps) {
  return (
    <section className={`rounded-2xl border border-slate-200 bg-white shadow-sm ${className}`}>
      {(title || description || action) && (
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
          <div>
            {title &&
              (typeof title === 'string' ? (
                <h2 className="text-base font-semibold text-slate-900">{title}</h2>
              ) : (
                <div className="text-base font-semibold text-slate-900">{title}</div>
              ))}
            {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
          </div>
          {action}
        </div>
      )}
      <div className="px-6 py-5">{children}</div>
    </section>
  );
}
