import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type AccountOption = {
  id: string;
  code: string;
  name: TranslatedName;
  type: string;
  nature: string;
  currency: string;
  is_control: boolean;
  allow_manual_posting: boolean;
};

type BranchOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type MappingRow = {
  id: string;
  key: string;
  branch_id: string | null;
  account_id: string;
  description: string | null;
  is_system: boolean;
  account: AccountOption;
  branch: BranchOption | null;
};

type AccountMappingsProps = SharedPageProps & {
  mappingKeys: string[];
  mappings: MappingRow[];
  accounts: AccountOption[];
  branches: BranchOption[];
};

type MappingScope = 'global' | 'branch';

function toMappingScope(value: string | null): MappingScope {
  return value === 'branch' ? 'branch' : 'global';
}

export default function AccountMappings({ locale, mappingKeys, mappings, accounts, branches }: AccountMappingsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canManageMappings = can('accounting.mappings') || can('settings.configure');
  const mappingKeyLabels = accDict.mappingKeys as Record<string, string>;
  const [scope, setScope] = useState<MappingScope>('global');

  const form = useForm({
    key: mappingKeys[0] || '',
    branch_id: '',
    account_id: '',
    description: '',
  });

  const mappingKeyOptions = useMemo(
    () => mappingKeys.map((key) => ({
      value: key,
      label: mappingKeyLabels[key] || key,
      sublabel: key,
    })),
    [mappingKeyLabels, mappingKeys],
  );

  const scopeOptions = useMemo(
    () => [
      { value: 'global' as const, label: accDict.globalScope },
      { value: 'branch' as const, label: accDict.branchScope },
    ],
    [accDict.branchScope, accDict.globalScope],
  );

  const accountOptions = useMemo(
    () => accounts.map((account) => ({
      value: account.id,
      label: `${account.code} - ${getLocalizedName(account.name, locale)}`,
      sublabel: `${account.type} / ${account.nature} / ${account.currency}`,
    })),
    [accounts, locale],
  );

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? accDict.active : accDict.inactive,
    })),
    [accDict.active, accDict.inactive, branches, locale],
  );

  function submit(e: FormEvent) {
    e.preventDefault();
    form.post('/accounting/account-mappings', {
      preserveScroll: true,
      onSuccess: () => {
        form.setData('account_id', '');
        form.setData('description', '');
      },
    });
  }

  function changeScope(nextScope: MappingScope) {
    setScope(nextScope);
    if (nextScope === 'global') {
      form.setData('branch_id', '');
    }
  }

  function mappingDeleteMessage(mapping: MappingRow) {
    const keyLabel = mappingKeyLabels[mapping.key] || mapping.key;
    const branchLabel = mapping.branch
      ? `${mapping.branch.code} - ${getLocalizedName(mapping.branch.name, locale)}`
      : accDict.globalScope;
    const accountLabel = `${mapping.account.code} - ${getLocalizedName(mapping.account.name, locale)}`;

    return accDict.accountMappingDeleteConfirm
      .replace('{key}', keyLabel)
      .replace('{branch}', branchLabel)
      .replace('{account}', accountLabel);
  }

  function deleteMapping(mapping: MappingRow) {
    if (!mapping.branch_id) return;
    if (!confirm(mappingDeleteMessage(mapping))) return;
    router.delete(`/accounting/account-mappings/${mapping.id}`, { preserveScroll: true });
  }

  const globalCount = mappings.filter((mapping) => !mapping.branch_id).length;
  const branchCount = mappings.filter((mapping) => mapping.branch_id).length;

  return (
    <AppLayout active="accounting.account_mappings">
      <Head title={accDict.accountMappingsHeadTitle} />

      <PageHeader title={accDict.accountMappings} description={accDict.accountMappingsDesc} />

      <div className="space-y-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <Card className="p-4">
            <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.globalMappings}</span>
            <span className="mt-2 block font-mono text-2xl font-extrabold text-[var(--text-primary)]">{globalCount.toLocaleString()}</span>
          </Card>
          <Card className="p-4">
            <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.branchOverrides}</span>
            <span className="mt-2 block font-mono text-2xl font-extrabold text-[var(--text-primary)]">{branchCount.toLocaleString()}</span>
          </Card>
          <Card className="p-4">
            <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.mappingKeysCount}</span>
            <span className="mt-2 block font-mono text-2xl font-extrabold text-[var(--text-primary)]">{mappingKeys.length.toLocaleString()}</span>
          </Card>
        </div>

        <Card className="p-4">
          <form onSubmit={submit} className="grid grid-cols-1 gap-4 xl:grid-cols-[1.3fr_0.8fr_1.2fr_1.5fr_auto] xl:items-end">
            <SearchableSelect
              label={accDict.mappingKey}
              options={mappingKeyOptions}
              value={form.data.key}
              onChange={(value) => form.setData('key', value || '')}
              placeholder={accDict.selectMappingKey}
              error={form.errors.key}
            />

            <SearchableSelect<MappingScope>
              label={accDict.mappingScope}
              options={scopeOptions}
              value={scope}
              onChange={(value) => changeScope(toMappingScope(value))}
              placeholder={accDict.mappingScope}
              isClearable={false}
              isSearchable={false}
            />

            <SearchableSelect
              label={accDict.branch}
              options={branchOptions}
              value={form.data.branch_id}
              onChange={(value) => form.setData('branch_id', value || '')}
              placeholder={accDict.selectBranch}
              disabled={scope === 'global'}
              error={form.errors.branch_id}
            />

            <SearchableSelect
              label={accDict.account}
              options={accountOptions}
              value={form.data.account_id}
              onChange={(value) => form.setData('account_id', value || '')}
              placeholder={accDict.selectAccount}
              error={form.errors.account_id}
            />

            <Button type="submit" disabled={!canManageMappings || form.processing || !form.data.key || !form.data.account_id || (scope === 'branch' && !form.data.branch_id)}>
              {form.processing ? accDict.saving : accDict.saveMapping}
            </Button>

            <label className="space-y-1 text-sm font-medium text-[var(--text-secondary)] xl:col-span-5">
              <span>{accDict.description}</span>
              <input
                value={form.data.description}
                onChange={(event) => form.setData('description', event.target.value)}
                placeholder={accDict.mappingDescriptionPlaceholder}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
              />
            </label>
          </form>
        </Card>

        {mappings.length === 0 ? (
          <EmptyState title={accDict.noAccountMappings} description={accDict.noAccountMappingsDesc} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{accDict.mappingKey}</th>
                  <th className={tableClasses.th}>{accDict.mappingScope}</th>
                  <th className={tableClasses.th}>{accDict.account}</th>
                  <th className={tableClasses.th}>{accDict.description}</th>
                  <th className={tableClasses.th}>{accDict.status}</th>
                  <th className={tableClasses.th}>{accDict.actions}</th>
                </tr>
              </thead>
              <tbody>
                {mappings.map((mapping) => (
                  <tr key={mapping.id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-56 flex-col gap-1">
                        <span className="text-sm font-bold text-[var(--text-primary)]">{mappingKeyLabels[mapping.key] || mapping.key}</span>
                        <span className="font-mono text-xs text-[var(--text-muted)]">{mapping.key}</span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      {mapping.branch ? (
                        <div className="flex min-w-48 flex-col gap-1">
                          <StatusBadge tone="ok">{accDict.branchScope}</StatusBadge>
                          <span className="text-xs text-[var(--text-secondary)]">
                            {mapping.branch.code} - {getLocalizedName(mapping.branch.name, locale)}
                          </span>
                        </div>
                      ) : (
                        <StatusBadge tone="muted">{accDict.globalScope}</StatusBadge>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex min-w-56 flex-col gap-1">
                        <span className="font-mono text-xs font-bold text-[var(--text-primary)]">{mapping.account.code}</span>
                        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(mapping.account.name, locale)}</span>
                        <span className="text-[10px] font-semibold uppercase text-[var(--text-muted)]">
                          {mapping.account.type} / {mapping.account.nature} / {mapping.account.currency}
                        </span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>{mapping.description || accDict.notAssigned}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={mapping.branch_id ? 'ok' : 'muted'}>
                        {mapping.branch_id ? accDict.overrideBadge : accDict.globalBadge}
                      </StatusBadge>
                    </td>
                    <td className={tableClasses.td}>
                      {mapping.branch_id ? (
                        canManageMappings ? (
                          <Button type="button" variant="danger" onClick={() => deleteMapping(mapping)}>
                            {accDict.delete}
                          </Button>
                        ) : (
                          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
                        )
                      ) : (
                        <span className="text-xs font-semibold text-[var(--text-muted)]">{accDict.protectedGlobalMapping}</span>
                      )}
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
