import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { NumberingFormData, SequenceRow, SharedPageProps } from '../../Types';

type NumberingProps = SharedPageProps & {
  sequences: SequenceRow[];
};

function NumberingFormModal({
  sequence,
  dict,
  onClose,
}: {
  sequence?: SequenceRow;
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const { data, setData, post, patch, processing, errors, reset } = useForm<NumberingFormData>({
    key: sequence?.key ?? '',
    doc_type: sequence?.docType ?? '',
    prefix: sequence?.prefix ?? '',
    include_year: sequence?.includeYear ?? true,
    padding: sequence?.padding ?? 5,
    reset_policy: sequence?.resetPolicy ?? 'yearly',
    next_value: sequence?.nextValue ?? 1,
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (sequence) {
      patch(`/settings/numbering/${sequence.id}`, {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
    } else {
      post('/settings/numbering', {
        preserveScroll: true,
        onSuccess: () => {
          reset();
          onClose();
        },
      });
    }
  }

  // Calculate live format preview
  const currentYear = new Date().getFullYear();
  const paddedVal = String(data.next_value).padStart(Math.max(1, Math.min(12, data.padding)), '0');
  const livePreview = `${data.prefix}${data.include_year ? `${currentYear}-` : ''}${paddedVal}`;

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <div>
          <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
            {sequence ? `${dict.app.actions.edit}: ${sequence.docType}` : dict.app.actions.addSequence}
          </h3>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
        >
          <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form onSubmit={submit} className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {/* Key */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.key} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.key}
              onChange={(event) => setData('key', event.target.value)}
              placeholder="e.g. sales.invoice"
              className="w-full font-mono rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.key ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.key}</p> : null}
          </div>

          {/* Doc Type */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.docType} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.doc_type}
              onChange={(event) => setData('doc_type', event.target.value)}
              placeholder="e.g. SalesInvoice"
              className="w-full font-mono rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
          </div>

          {/* Prefix */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.prefix} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.prefix}
              onChange={(event) => setData('prefix', event.target.value)}
              placeholder="e.g. INV-"
              className="w-full font-mono rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
          </div>

          {/* Padding */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.padding} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="number"
              min={1}
              max={12}
              value={data.padding}
              onChange={(event) => setData('padding', Number(event.target.value))}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-3 items-end">
          {/* Reset Policy */}
          <SearchableSelect
            label={dict.app.fields.resetPolicy}
            options={[
              { value: 'never', label: 'Never' },
              { value: 'yearly', label: 'Yearly' },
              { value: 'monthly', label: 'Monthly' },
            ]}
            value={data.reset_policy}
            onChange={(val) => setData('reset_policy', val ?? 'yearly')}
            isSearchable={false}
            isClearable={false}
          />

          {/* Next Value */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.nextValue}
            </label>
            <input
              type="number"
              min={1}
              value={data.next_value}
              onChange={(event) => setData('next_value', Number(event.target.value))}
              className="w-full font-mono rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
          </div>

          {/* Include Year Toggle */}
          <div className="pb-1.5">
            <ToggleSwitch
              label={dict.app.fields.includeYear}
              description="Format: PREFIX-YEAR-NUMBER"
              checked={data.include_year}
              onChange={(val) => setData('include_year', val)}
            />
          </div>
        </div>

        {/* Live Formatted Number Preview */}
        <div className="flex items-center justify-between rounded-xl border border-blue-500/20 bg-blue-500/5 p-4">
          <div className="flex items-center gap-2">
            <svg className="size-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span className="text-xs font-bold text-[var(--text-primary)]">
              {dict.app.fields.preview}:
            </span>
          </div>
          <span className="font-mono text-sm font-extrabold text-blue-600 dark:text-blue-400 bg-blue-500/10 px-3 py-1 rounded-lg border border-blue-500/20 shadow-xs">
            {livePreview}
          </span>
        </div>

        {/* Submit Actions */}
        <div className="flex items-center justify-end gap-3 pt-2 border-t border-[var(--border)]">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
          >
            {dict.app.actions.cancel}
          </button>
          <button
            type="submit"
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
          >
            {processing ? (
              <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            ) : null}
            <span>{sequence ? dict.app.actions.save : dict.app.actions.create}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

export default function Numbering({ sequences, locale }: NumberingProps) {
  const dict = getDictionary(locale);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingSequenceId, setEditingSequenceId] = useState<string | null>(null);

  return (
    <AppLayout active="settings.numbering">
      <Head title={dict.app.settings.sections.numbering.title} />

      <PageHeader
        title={dict.app.settings.sections.numbering.title}
        description={dict.app.settings.numbering.description}
        actions={
          <button
            type="button"
            onClick={() => {
              setEditingSequenceId(null);
              setShowAddForm(!showAddForm);
            }}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{dict.app.actions.addSequence}</span>
          </button>
        }
      />

      {/* Add Sequence Form Panel */}
      {showAddForm ? (
        <NumberingFormModal
          dict={dict}
          onClose={() => setShowAddForm(false)}
        />
      ) : null}

      {/* Main Sequences Table Card */}
      {sequences.length === 0 ? (
        <EmptyState title={dict.app.settings.numbering.empty} />
      ) : (
        <div className="space-y-4">
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.fields.docType}</th>
                  <th className={tableClasses.th}>{dict.app.fields.preview}</th>
                  <th className={tableClasses.th}>{dict.app.fields.resetPolicy}</th>
                  <th className={tableClasses.th}>{dict.app.fields.nextValue}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.actions.edit}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {sequences.map((sequence) => {
                  const isEditing = editingSequenceId === sequence.id;

                  return (
                    <tr key={sequence.id} className="group hover:bg-[var(--background)]/50 transition-colors">
                      <td className={tableClasses.td}>
                        <div className="flex flex-col min-w-44">
                          <span className="font-bold text-[var(--text-primary)] text-sm">{sequence.docType}</span>
                          <span className="font-mono text-xs text-[var(--text-muted)] mt-0.5">{sequence.key}</span>
                        </div>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-blue-500/10 border border-blue-500/20 px-3 py-1 font-mono text-xs font-extrabold text-blue-600 dark:text-blue-400">
                          <svg className="size-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                          </svg>
                          <span>{sequence.preview}</span>
                        </span>
                      </td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone="info">
                          {sequence.resetPolicy}
                        </StatusBadge>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="font-mono text-xs font-bold text-[var(--text-primary)] bg-[var(--background)] border border-[var(--border)] px-2.5 py-1 rounded-md">
                          #{sequence.nextValue}
                        </span>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <button
                          type="button"
                          onClick={() => {
                            setShowAddForm(false);
                            setEditingSequenceId(isEditing ? null : sequence.id);
                          }}
                          className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all ${
                            isEditing
                              ? 'border-[var(--primary)] bg-[var(--primary)] text-white'
                              : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)]'
                          }`}
                        >
                          <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                          <span>{dict.app.actions.edit}</span>
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Inline Edit Form Panel when editing a specific sequence */}
          {editingSequenceId ? (
            <div className="pt-2">
              <NumberingFormModal
                sequence={sequences.find((s) => s.id === editingSequenceId)}
                dict={dict}
                onClose={() => setEditingSequenceId(null)}
              />
            </div>
          ) : null}
        </div>
      )}
    </AppLayout>
  );
}
