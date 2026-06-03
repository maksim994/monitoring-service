type SwitchProps = {
  checked: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
  id?: string;
  'aria-label'?: string;
};

export function Switch({ checked, disabled, onChange, id, 'aria-label': ariaLabel }: SwitchProps) {
  return (
    <label className={`relative inline-flex shrink-0 ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}>
      <input
        id={id}
        type="checkbox"
        role="switch"
        aria-checked={checked}
        aria-label={ariaLabel}
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
        className="peer sr-only"
      />
      <span
        aria-hidden
        className="block h-5 w-9 rounded-full bg-slate-200 ring-1 ring-slate-200 transition-colors duration-200 peer-checked:bg-brand-600 peer-checked:ring-brand-500/40 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2"
      />
      <span
        aria-hidden
        className="pointer-events-none absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm ring-1 ring-slate-200/60 transition-transform duration-200 peer-checked:translate-x-4"
      />
    </label>
  );
}
