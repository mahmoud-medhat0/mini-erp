import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';

import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
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

/** Slim rows behind the summary cards; the grid loads its own from the feed. */
type MappingSummaryRow = {
  id: string;
  key: string;
  branch_id: string | null;
};

type AccountMappingsProps = SharedPageProps & {
  mappingKeys: string[];
  mappings: MappingSummaryRow[];
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
  const actionsDict = dict.app.actions;
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
        setGridReloadToken((token) => token + 1);
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
    router.delete(`/accounting/account-mappings/${mapping.id}`, {
      preserveScroll: true,
      onSuccess: () => setGridReloadToken((token) => token + 1),
    });
  }

  // ── server-side grid ──────────────────────────────────────────────────────
  const [scopeFilter, setScopeFilter] = useState('');
  const [keyFilter, setKeyFilter] = useState('');
  // The grid fetches its own rows, so mutations have to ask it to refetch.
  const [gridReloadToken, setGridReloadToken] = useState(0);

  const dtColumns = useMemo(() => [
    { data: 'key', name: 'key', title: accDict.mappingKey },
    { data: 'scope', name: 'scope', title: accDict.mappingScope, searchable: false, width: '190px' },
    { data: 'account.code', name: 'account', title: accDict.account, searchable: false, defaultContent: '' },
    { data: 'description', name: 'description', title: accDict.description, searchable: false },
    { data: 'is_system', name: 'is_system', title: accDict.status, searchable: false, orderable: false, width: '110px' },
    { data: 'id', name: 'id', title: accDict.actions, searchable: false, orderable: false, width: '150px' },
  ], [accDict]);

  const dtSlots = useMemo<DataTableSlots>(() => ({
    key: (_d: unknown, _t: unknown, row: MappingRow): ReactElement => (
      <div className="flex min-w-56 flex-col gap-1">
        <span className="text-sm font-bold text-[var(--text-primary)]">{mappingKeyLabels[row.key] || row.key}</span>
        <span className="font-mono text-xs text-[var(--text-muted)]">{row.key}</span>
      </div>
    ),
    scope: (_d: unknown, _t: unknown, row: MappingRow): ReactElement => (
      row.branch ? (
        <div className="flex min-w-48 flex-col gap-1">
          <StatusBadge tone="ok">{accDict.branchScope}</StatusBadge>
          <span className="text-xs text-[var(--text-secondary)]">
            {row.branch.code} - {getLocalizedName(row.branch.name, locale)}
          </span>
        </div>
      ) : (
        <StatusBadge tone="muted">{accDict.globalScope}</StatusBadge>
      )
    ),
    account: (_d: unknown, _t: unknown, row: MappingRow): ReactElement => (
      <div className="flex min-w-56 flex-col gap-1">
        <span className="font-mono text-xs font-bold text-[var(--text-primary)]">{row.account?.code}</span>
        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(row.account?.name ?? null, locale)}</span>
        <span className="text-[10px] font-semibold uppercase text-[var(--text-muted)]">
          {row.account?.type} / {row.account?.nature} / {row.account?.currency}
        </span>
      </div>
    ),
    description: (data: string | null): ReactElement => (
      <span className="text-xs text-[var(--text-secondary)]">{data || accDict.notAssigned}</span>
    ),
    is_system: (_d: unknown, _t: unknown, row: MappingRow): ReactElement => (
      <StatusBadge tone={row.branch_id ? 'ok' : 'muted'}>
        {row.branch_id ? accDict.overrideBadge : accDict.globalBadge}
      </StatusBadge>
    ),
    id: (_d: unknown, _t: unknown, row: MappingRow): ReactElement => (
      row.branch_id ? (
        canManageMappings ? (
          <Button type="button" variant="danger" onClick={() => deleteMapping(row)}>
            {accDict.delete}
          </Button>
        ) : (
          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        )
      ) : (
        <span className="text-xs font-semibold text-[var(--text-muted)]">{accDict.protectedGlobalMapping}</span>
      )
    ),
  } as unknown as DataTableSlots), [accDict, dict, locale, mappingKeyLabels, canManageMappings]);

  const dtFilters = useMemo(() => ({ scope: scopeFilter, key: keyFilter }), [scopeFilter, keyFilter]);

  const hasGridFilters = scopeFilter !== '' || keyFilter !== '';

  // The two controls stay on one line and move as a unit when the bar runs out
  // of room; splitting them across lines reads as a broken layout. Neither is
  // clearable — their "(All)" option already is the cleared state, so a clear
  // button would just duplicate it.
  const dtToolbar = (
    <div className="flex flex-col gap-2 sm:flex-row sm:flex-nowrap sm:items-center">
      <span className="flex items-center gap-1.5 rounded-xl border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] px-2.5 py-1.5 text-xs font-bold whitespace-nowrap text-[var(--primary)] shrink-0">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        {actionsDict.filter}
      </span>

      <SearchableSelect
        options={[
          { value: '', label: accDict.allScopes },
          { value: 'global', label: accDict.globalScope },
          { value: 'branch', label: accDict.branchScope },
        ]}
        value={scopeFilter}
        onChange={(value) => setScopeFilter(value || '')}
        placeholder={accDict.mappingScope}
        isSearchable={false}
        isClearable={false}
        locale={locale}
        className="w-full sm:w-40 shrink-0"
      />

      <SearchableSelect
        options={[{ value: '', label: accDict.allMappingKeys }, ...mappingKeyOptions]}
        value={keyFilter}
        onChange={(value) => setKeyFilter(value || '')}
        placeholder={accDict.mappingKey}
        isClearable={false}
        locale={locale}
        className="w-full sm:w-56 shrink-0"
      />

      {hasGridFilters ? (
        <button
          type="button"
          onClick={() => {
            setScopeFilter('');
            setKeyFilter('');
          }}
          title={actionsDict.reset}
          aria-label={actionsDict.reset}
          className="inline-flex items-center justify-center gap-1 rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-xs font-bold whitespace-nowrap text-red-600 shrink-0 transition-all hover:text-red-700 cursor-pointer dark:text-red-400"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          {actionsDict.reset}
        </button>
      ) : null}
    </div>
  );

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

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/accounting/account-mappings/data"
            columns={dtColumns}
            filters={dtFilters}
            locale={locale}
            order={[]}
            pageLength={25}
            slots={dtSlots}
            tableId="account-mappings-table"
            toolbar={dtToolbar}
            reloadToken={gridReloadToken}
          />
        </Card>
      </div>
    </AppLayout>
  );
}
