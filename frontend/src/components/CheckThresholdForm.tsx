import { type FormEvent, useEffect, useState } from 'react';
import { Pencil } from 'lucide-react';
import { getCheckMeta, getCheckTypeLabel, getThresholdChips, type CheckThresholdField } from '../lib/checks';
import { Button } from './ui/Button';
import { Input } from './ui/Input';
import { Modal } from './ui/Modal';

type Props = {
  checkType: string;
  settings: Record<string, unknown>;
  disabled?: boolean;
  saving?: boolean;
  variant?: 'default' | 'compact';
  onSave: (settings: Record<string, number>) => Promise<void>;
};

function buildDraft(fields: CheckThresholdField[], settings: Record<string, unknown>): Record<string, string> {
  const draft: Record<string, string> = {};
  for (const field of fields) {
    const raw = settings[field.key];
    if (typeof raw === 'number') {
      draft[field.key] = String(field.fromApi ? field.fromApi(raw) : raw);
    } else if (typeof raw === 'string' && raw !== '') {
      draft[field.key] = raw;
    } else {
      draft[field.key] = '';
    }
  }
  return draft;
}

function draftToApi(fields: CheckThresholdField[], draft: Record<string, string>): Record<string, number> {
  const result: Record<string, number> = {};
  for (const field of fields) {
    const num = Number(draft[field.key]);
    if (!Number.isFinite(num)) {
      continue;
    }
    result[field.key] = field.toApi ? field.toApi(num) : num;
  }
  return result;
}

export function CheckThresholdForm({
  checkType,
  settings,
  disabled,
  saving,
  variant = 'default',
  onSave,
}: Props) {
  const meta = getCheckMeta(checkType);
  const fields = meta?.thresholdFields ?? [];
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<Record<string, string>>(() => buildDraft(fields, settings));
  const [error, setError] = useState<string | null>(null);

  const chips = getThresholdChips(checkType, settings);

  useEffect(() => {
    if (!open) {
      const currentFields = getCheckMeta(checkType)?.thresholdFields ?? [];
      setDraft(buildDraft(currentFields, settings));
    }
  }, [open, settings, checkType]);

  if (fields.length === 0) {
    return variant === 'compact' ? null : <span className="text-sm text-slate-400">Пороги не настраиваются</span>;
  }

  function closeModal() {
    setOpen(false);
    setError(null);
    setDraft(buildDraft(fields, settings));
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await onSave(draftToApi(fields, draft));
      setOpen(false);
    } catch (caught) {
      setError((caught as { error?: { message?: string } })?.error?.message ?? 'Не удалось сохранить');
    }
  }

  const triggerButton =
    variant === 'compact' ? (
      <button
        type="button"
        onClick={() => {
          setDraft(buildDraft(fields, settings));
          setOpen(true);
          setError(null);
        }}
        className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:border-brand-300 hover:text-brand-700 sm:w-auto"
      >
        <Pencil className="h-3.5 w-3.5" />
        {meta?.thresholdButtonLabel ?? 'Пороги'}
      </button>
    ) : (
      <Button
        type="button"
        variant="secondary"
        onClick={() => {
          setDraft(buildDraft(fields, settings));
          setOpen(true);
          setError(null);
        }}
      >
        Изменить пороги
      </Button>
    );

  return (
    <>
      {variant === 'compact' ? (disabled ? null : triggerButton) : (
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-slate-600">
            {chips.map((c) => `${c.shortLabel}: ${c.value}`).join(' · ') || '—'}
          </span>
          {!disabled && triggerButton}
        </div>
      )}

      <Modal
        open={open}
        onClose={closeModal}
        title={
          checkType === 'disk_low'
            ? 'Минимум свободного места на диске'
            : `Пороги: ${getCheckTypeLabel(checkType)}`
        }
        description={meta?.thresholdsModalDescription}
      >
        <form onSubmit={handleSubmit} className="space-y-5">
          {fields.map((field) => (
            <Input
              key={field.key}
              label={`${field.label} (${field.unit})`}
              hint={field.hint}
              type="number"
              min={field.min}
              max={field.max}
              step={field.step ?? 1}
              value={draft[field.key] ?? ''}
              onChange={(e) => setDraft((current) => ({ ...current, [field.key]: e.target.value }))}
            />
          ))}
          {error && <p className="text-sm text-red-600">{error}</p>}
          <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <Button type="submit" disabled={saving}>
              {saving ? 'Сохранение...' : 'Сохранить'}
            </Button>
            <Button type="button" variant="secondary" disabled={saving} onClick={closeModal}>
              Отмена
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
