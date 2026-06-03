import { useId, useState } from 'react';
import { CircleHelp } from 'lucide-react';

type HelpHintProps = {
  content: string;
  /** Для screen readers */
  label?: string;
};

export function HelpHint({ content, label = 'Показать пояснение' }: HelpHintProps) {
  const [pinned, setPinned] = useState(false);
  const tooltipId = useId();

  return (
    <span className="group/hint relative inline-flex align-middle">
      <button
        type="button"
        className="rounded-full p-0.5 text-slate-400 transition-colors hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1"
        aria-label={label}
        aria-describedby={pinned ? tooltipId : undefined}
        aria-expanded={pinned}
        onClick={() => setPinned((value) => !value)}
        onBlur={(event) => {
          if (!event.currentTarget.parentElement?.contains(event.relatedTarget as Node | null)) {
            setPinned(false);
          }
        }}
      >
        <CircleHelp className="h-4 w-4" strokeWidth={2} />
      </button>

      <span
        id={tooltipId}
        role="tooltip"
        className={`pointer-events-none absolute left-0 top-full z-30 mt-2 w-[min(17rem,calc(100vw-3rem))] rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-xs leading-relaxed text-slate-700 shadow-lg ring-1 ring-slate-900/5 transition-opacity ${
          pinned ? 'visible opacity-100' : 'invisible opacity-0 group-hover/hint:visible group-hover/hint:opacity-100'
        }`}
      >
        {content}
      </span>
    </span>
  );
}
