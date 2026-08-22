import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import AttachmentPanel from '../../Components/AttachmentPanel';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { BranchFormData, BranchRow, SharedPageProps } from '../../Types';

type BranchesProps = SharedPageProps & {
  branches: BranchRow[];
};

function BranchFormModal({
  branch,
  dict,
  onClose,
}: {
  branch?: BranchRow;
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const { data, setData, post, patch, processing, errors, reset } = useForm<BranchFormData>({
    code: branch?.code ?? '',
    name_en: branch?.nameEn ?? '',
    name_ar: branch?.nameAr ?? '',
    is_active: branch?.isActive ?? true,
    lock_version: branch?.lockVersion ?? 0,
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (branch) {
      patch(`/settings/branches/${branch.id}`, {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
    } else {
      post('/settings/branches', {
        preserveScroll: true,
        onSuccess: () => {
          reset();
          onClose();
        },
      });
    }
  }

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
          {branch ? `${dict.app.actions.edit}: ${branch.name}` : dict.app.actions.addBranch}
        </h3>
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

      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          {/* Branch Code */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.code} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.code}
              onChange={(event) => setData('code', event.target.value)}
              placeholder="e.g. CAI-01"
              className="w-full font-mono rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.code ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.code}</p> : null}
          </div>

          {/* Name English */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.branch} (English) <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.name_en}
              onChange={(event) => setData('name_en', event.target.value)}
              placeholder="e.g. Cairo Main Branch"
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.name_en ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name_en}</p> : null}
          </div>

          {/* Name Arabic */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.branch} (العربية) <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              dir="rtl"
              value={data.name_ar}
              onChange={(event) => setData('name_ar', event.target.value)}
              placeholder="مثال: فرع القاهرة الرئيسي"
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.name_ar ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name_ar}</p> : null}
          </div>
        </div>

        {/* Active Status Toggle */}
        <div className="pt-2">
          <ToggleSwitch
            label={dict.app.status.active}
            description={dict.app.settings.sections.branches.title}
            checked={data.is_active}
            onChange={(val) => setData('is_active', val)}
          />
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
            <span>{branch ? dict.app.actions.save : dict.app.actions.create}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

export default function Branches({ branches, locale }: BranchesProps) {
  const dict = getDictionary(locale);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingBranchId, setEditingBranchId] = useState<string | null>(null);
  const [selectedBranchId, setSelectedBranchId] = useState<string>(branches[0]?.id ?? '');

  return (
    <AppLayout active="settings.branches">
      <Head title={dict.app.settings.sections.branches.title} />

      <PageHeader
        title={dict.app.settings.sections.branches.title}
        description={dict.app.settings.branches.description}
        actions={
          <button
            type="button"
            onClick={() => {
              setEditingBranchId(null);
              setShowAddForm(!showAddForm);
            }}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{dict.app.actions.addBranch}</span>
          </button>
        }
      />

      {/* Add Branch Form Panel */}
      {showAddForm ? (
        <BranchFormModal
          dict={dict}
          onClose={() => setShowAddForm(false)}
        />
      ) : null}

      {/* Main Branches Table Card */}
      {branches.length === 0 ? (
        <EmptyState title={dict.app.settings.branches.empty} />
      ) : (
        <div className="space-y-4">
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.fields.code}</th>
                  <th className={tableClasses.th}>{dict.app.fields.branch}</th>
                  <th className={tableClasses.th}>{dict.app.fields.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.actions.edit}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {branches.map((branch) => {
                  const isEditing = editingBranchId === branch.id;
                  const isSelectedForAttachments = selectedBranchId === branch.id;

                  return (
                    <tr key={branch.id} className="group hover:bg-[var(--background)]/50 transition-colors">
                      <td className={tableClasses.td}>
                        <span className="font-mono text-xs font-bold text-[var(--primary)] bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-md">
                          {branch.code}
                        </span>
                      </td>
                      <td className={tableClasses.td}>
                        <div className="flex flex-col min-w-44">
                          <span className="font-bold text-[var(--text-primary)] text-sm">{branch.name}</span>
                          <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[var(--text-muted)]">
                            <span>EN: {branch.nameEn}</span>
                            <span>•</span>
                            <span>AR: {branch.nameAr}</span>
                          </div>
                        </div>
                      </td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={branch.isActive ? 'ok' : 'muted'}>
                          {branch.isActive ? dict.app.status.active : dict.app.status.inactive}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex items-center justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => setSelectedBranchId(branch.id)}
                            className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                              isSelectedForAttachments
                                ? 'border-blue-500/50 bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:border-[var(--primary)]'
                            }`}
                          >
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            <span>{dict.app.pages.settingsBranches.attachments}</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setShowAddForm(false);
                              setEditingBranchId(isEditing ? null : branch.id);
                            }}
                            className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
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
                          <button
                            type="button"
                            onClick={() => {
                              if (confirm(dict.app.actions.confirmDelete || 'Are you sure you want to delete this branch?')) {
                                router.delete(`/settings/branches/${branch.id}`);
                              }
                            }}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-all cursor-pointer"
                          >
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>{dict.app.actions.delete}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Inline Edit Form Panel when editing a specific branch */}
          {editingBranchId ? (
            <div className="pt-2">
              <BranchFormModal
                branch={branches.find((b) => b.id === editingBranchId)}
                dict={dict}
                onClose={() => setEditingBranchId(null)}
              />
            </div>
          ) : null}

          {/* Dynamic Branch Attachments Section */}
          {branches.length > 0 && selectedBranchId ? (
            <div className="mt-6 space-y-3">
              {branches.length > 1 ? (
                <div className="flex items-center justify-between bg-[var(--surface)] p-3.5 rounded-xl border border-[var(--border)]">
                  <label className="text-xs font-bold text-[var(--text-primary)]">
                    {dict.app.pages.settingsBranches.selectBranchForAttachments}
                  </label>
                  <select
                    value={selectedBranchId}
                    onChange={(e) => setSelectedBranchId(e.target.value)}
                    className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs font-semibold text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden cursor-pointer"
                  >
                    {branches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.name} ({b.code})
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}
              <AttachmentPanel
                key={selectedBranchId}
                entityType="branch"
                entityId={selectedBranchId}
                locale={locale === 'ar' ? 'ar' : 'en'}
              />
            </div>
          ) : null}
        </div>
      )}
    </AppLayout>
  );
}
