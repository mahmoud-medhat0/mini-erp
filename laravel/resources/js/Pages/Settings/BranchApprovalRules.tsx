import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type BranchOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type BranchApprovalRuleRow = {
  id: string;
  document_type: string;
  branch_match: string;
  branch_id: string | null;
  required_permission: string;
  is_active: boolean;
  notes: string | null;
  branch: BranchOption | null;
};

type BranchApprovalRulesProps = SharedPageProps & {
  rules: BranchApprovalRuleRow[];
  branches: BranchOption[];
  documentTypes: string[];
  branchMatches: string[];
  permissionOptions: string[];
};

export default function BranchApprovalRules({ locale, rules, branches, documentTypes, branchMatches, permissionOptions }: BranchApprovalRulesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.settings.branchApprovalRules;
  const permissionLabels = dict.app.permissionsList as Record<string, string>;
  const [editingId, setEditingId] = useState<string | null>(null);

  const form = useForm({
    document_type: documentTypes[0] || '',
    branch_match: 'document',
    branch_id: '',
    required_permission: permissionOptions[0] || 'approvals.override',
    is_active: true,
    notes: '',
  });

  const documentTypeLabels = pageDict.documentTypes as Record<string, string>;
  const branchMatchLabels = pageDict.branchMatches as Record<string, string>;

  const documentTypeOptions = useMemo(
    () => documentTypes.map((documentType) => ({
      value: documentType,
      label: documentTypeLabels[documentType] || documentType,
      sublabel: documentType,
    })),
    [documentTypeLabels, documentTypes],
  );

  const branchMatchOptions = useMemo(
    () => branchMatches.map((branchMatch) => ({
      value: branchMatch,
      label: branchMatchLabels[branchMatch] || branchMatch,
      sublabel: branchMatch,
    })),
    [branchMatchLabels, branchMatches],
  );

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? dict.app.status.active : dict.app.status.inactive,
    })),
    [branches, dict.app.status.active, dict.app.status.inactive, locale],
  );

  const permissionSelectOptions = useMemo(
    () => permissionOptions.map((permission) => ({
      value: permission,
      label: permissionLabels[permission] || permission,
      sublabel: permission,
    })),
    [permissionLabels, permissionOptions],
  );

  function resetForm() {
    setEditingId(null);
    form.setData({
      document_type: documentTypes[0] || '',
      branch_match: 'document',
      branch_id: '',
      required_permission: permissionOptions[0] || 'approvals.override',
      is_active: true,
      notes: '',
    });
    form.clearErrors();
  }

  function editRule(rule: BranchApprovalRuleRow) {
    setEditingId(rule.id);
    form.setData({
      document_type: rule.document_type,
      branch_match: rule.branch_match,
      branch_id: rule.branch_id || '',
      required_permission: rule.required_permission,
      is_active: rule.is_active,
      notes: rule.notes || '',
    });
    form.clearErrors();
  }

  function submit(e: FormEvent) {
    e.preventDefault();

    const options = {
      preserveScroll: true,
      onSuccess: resetForm,
    };

    if (editingId) {
      form.patch(`/settings/branch-approval-rules/${editingId}`, options);
    } else {
      form.post('/settings/branch-approval-rules', options);
    }
  }

  function deleteRule(rule: BranchApprovalRuleRow) {
    if (!confirm(pageDict.deleteConfirm)) return;

    router.delete(`/settings/branch-approval-rules/${rule.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        if (editingId === rule.id) resetForm();
      },
    });
  }

  const activeRules = rules.filter((rule) => rule.is_active).length;
  const branchScopedRules = rules.filter((rule) => rule.branch_id).length;

  return (
    <AppLayout active="settings.branch_approval_rules">
      <Head title={pageDict.headTitle} />

      <PageHeader title={pageDict.title} description={pageDict.description} />

      <div className="mb-4 grid gap-3 md:grid-cols-3">
        <Card className="border-s-4 border-s-blue-500 p-4">
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.totalRules}</span>
          <span className="mt-2 block text-2xl font-extrabold text-[var(--text-primary)]">{rules.length.toLocaleString()}</span>
        </Card>
        <Card className="border-s-4 border-s-emerald-500 p-4">
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.activeRules}</span>
          <span className="mt-2 block text-2xl font-extrabold text-[var(--text-primary)]">{activeRules.toLocaleString()}</span>
        </Card>
        <Card className="border-s-4 border-s-purple-500 p-4">
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.branchScopedRules}</span>
          <span className="mt-2 block text-2xl font-extrabold text-[var(--text-primary)]">{branchScopedRules.toLocaleString()}</span>
        </Card>
      </div>

      <div className="grid gap-4 xl:grid-cols-[minmax(320px,420px)_1fr]">
        <Card className="p-4">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.documentType}</label>
              <SearchableSelect value={form.data.document_type} onChange={(value) => form.setData('document_type', value || '')} options={documentTypeOptions} placeholder={pageDict.selectDocumentType} error={form.errors.document_type} />
            </div>

            <div>
              <label className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.branchMatch}</label>
              <SearchableSelect value={form.data.branch_match} onChange={(value) => form.setData('branch_match', value || 'document')} options={branchMatchOptions} placeholder={pageDict.selectBranchMatch} error={form.errors.branch_match} />
            </div>

            <div>
              <label className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.branchScope}</label>
              <SearchableSelect value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value || '')} options={branchOptions} placeholder={pageDict.allBranches} error={form.errors.branch_id} />
            </div>

            <div>
              <label className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.requiredPermission}</label>
              <SearchableSelect value={form.data.required_permission} onChange={(value) => form.setData('required_permission', value || '')} options={permissionSelectOptions} placeholder={pageDict.selectPermission} error={form.errors.required_permission} />
            </div>

            <div>
              <label className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.notes}</label>
              <textarea
                value={form.data.notes}
                onChange={(event) => form.setData('notes', event.target.value)}
                placeholder={pageDict.notesPlaceholder}
                className="min-h-24 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
              />
              {form.errors.notes ? <p className="mt-1 text-xs text-red-500">{form.errors.notes}</p> : null}
            </div>

            <ToggleSwitch label={pageDict.active} description={pageDict.activeDescription} checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />

            <div className="flex flex-wrap justify-end gap-2 border-t border-[var(--border)] pt-4">
              {editingId ? (
                <Button type="button" variant="secondary" onClick={resetForm}>
                  {pageDict.cancelEdit}
                </Button>
              ) : null}
              <Button type="submit" disabled={form.processing || !form.data.document_type || !form.data.branch_match || !form.data.required_permission}>
                {editingId ? pageDict.updateRule : pageDict.createRule}
              </Button>
            </div>
          </form>
        </Card>

        {rules.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.documentType}</th>
                  <th className={tableClasses.th}>{pageDict.branchMatch}</th>
                  <th className={tableClasses.th}>{pageDict.branchScope}</th>
                  <th className={tableClasses.th}>{pageDict.requiredPermission}</th>
                  <th className={tableClasses.th}>{dict.app.fields.status}</th>
                  <th className={tableClasses.th}>{pageDict.actions}</th>
                </tr>
              </thead>
              <tbody>
                {rules.map((rule) => (
                  <tr key={rule.id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <span className="font-bold">{documentTypeLabels[rule.document_type] || rule.document_type}</span>
                      <span className="mt-1 block font-mono text-xs text-[var(--text-muted)]">{rule.document_type}</span>
                    </td>
                    <td className={tableClasses.td}>{branchMatchLabels[rule.branch_match] || rule.branch_match}</td>
                    <td className={tableClasses.td}>
                      {rule.branch ? (
                        <div className="flex min-w-48 flex-col gap-1">
                          <span className="font-semibold">{rule.branch.code}</span>
                          <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(rule.branch.name, locale)}</span>
                        </div>
                      ) : (
                        <StatusBadge tone="muted">{pageDict.allBranches}</StatusBadge>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-mono text-xs">{rule.required_permission}</span>
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={rule.is_active ? 'ok' : 'muted'}>{rule.is_active ? dict.app.status.active : dict.app.status.inactive}</StatusBadge>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="secondary" onClick={() => editRule(rule)}>{pageDict.edit}</Button>
                        <Button type="button" variant="danger" onClick={() => deleteRule(rule)}>{pageDict.delete}</Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
