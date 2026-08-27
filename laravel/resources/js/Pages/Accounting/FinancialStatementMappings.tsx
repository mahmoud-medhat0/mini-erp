import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type AccountItem = {
  id: string;
  code: string;
  name: string | { en?: string; ar?: string };
  type: string;
  nature: string;
  financial_statement_line_id?: string | null;
  cash_flow_activity?: string | null;
  accountType?: { id: string; name: string | { en?: string; ar?: string }; statement_type?: string } | null;
  group?: { id: string; name: string | { en?: string; ar?: string } } | null;
};

type StatementLineRow = {
  id: string;
  code: string;
  statement_type: 'balance_sheet' | 'income_statement';
  section_code: string;
  name: string | { en?: string; ar?: string };
  normal_balance: 'debit' | 'credit';
  cash_flow_activity?: string | null;
  sort_order: number;
  is_system: boolean;
  is_active: boolean;
  accounts: AccountItem[];
};

type StatementType = StatementLineRow['statement_type'];
type NormalBalance = StatementLineRow['normal_balance'];

type OptionItem = {
  value: string;
};

type StatementLineForm = {
  code: string;
  statement_type: StatementType;
  section_code: string;
  name_en: string;
  name_ar: string;
  normal_balance: NormalBalance;
  cash_flow_activity: string;
  sort_order: number;
  is_active: boolean;
};

function toStatementType(value: string): StatementType {
  return value === 'income_statement' ? 'income_statement' : 'balance_sheet';
}

function toNormalBalance(value: string): NormalBalance {
  return value === 'credit' ? 'credit' : 'debit';
}

type MappingsProps = SharedPageProps & {
  lines: StatementLineRow[];
  unmappedAccounts: AccountItem[];
  statementTypes: OptionItem[];
  sectionOptions: OptionItem[];
  normalBalances: OptionItem[];
  cashFlowActivities: OptionItem[];
};

export default function FinancialStatementMappings({
  locale,
  lines = [],
  unmappedAccounts = [],
  statementTypes = [],
  sectionOptions = [],
  normalBalances = [],
  cashFlowActivities = [],
}: MappingsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const can = useCan();
  const canManage = can('accounting.mappings');

  const statementTypeLabel = (value: string) => (value === 'balance_sheet' ? accDict.balanceSheet : accDict.incomeStatement);
  const statementTypeShortLabel = (value: string) => (value === 'balance_sheet' ? accDict.balanceSheetShort : accDict.incomeStatementShort);
  const normalBalanceLabel = (value: string) => (value === 'debit' ? accDict.debitLabel : accDict.creditLabel);
  const normalBalanceBadge = (value: string) => (value === 'debit' ? accDict.debitBadge : accDict.creditBadge);
  const cashFlowActivityLabel = (value?: string | null) => {
    const labels: Record<string, string> = {
      operating: accDict.operatingOption,
      investing: accDict.investingOption,
      financing: accDict.financingOption,
    };

    return value ? (labels[value] ?? value) : accDict.cashFlowActivityUnclassified;
  };
  const accountTypeLabel = (value: string) => {
    const labels: Record<string, string> = {
      asset: accDict.assetOption,
      liability: accDict.liabilityOption,
      equity: accDict.equityOption,
      revenue: accDict.revenueOption,
      expense: accDict.expenseOption,
      contra_asset: accDict.contraAssetOption,
      contra_liability: accDict.contraLiabilityOption,
      contra_revenue: accDict.contraRevenueOption,
    };

    return labels[value] ?? value;
  };
  const sectionLabel = (value: string) => {
    const labels: Record<string, string> = {
      current_assets: accDict.currentAssets,
      non_current_assets: accDict.nonCurrentAssets,
      current_liabilities: accDict.currentLiabilities,
      non_current_liabilities: accDict.nonCurrentLiabilities,
      equity: accDict.equitySection,
      revenue: accDict.revenueSection,
      contra_revenue: accDict.contraRevenueSection,
      cogs: accDict.cogsSection,
      operating_expenses: accDict.operatingExpenses,
      other_income: accDict.otherIncome,
      other_expenses: accDict.otherExpenses,
    };

    return labels[value] ?? value;
  };

  const [activeTab, setActiveTab] = useState<'all' | 'balance_sheet' | 'income_statement'>('all');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingLine, setEditingLine] = useState<StatementLineRow | null>(null);

  const [selectedUnmappedAccount, setSelectedUnmappedAccount] = useState<string>('');
  const [targetLineForAccount, setTargetLineForAccount] = useState<string>('');

  const lineForm = useForm<StatementLineForm>({
    code: '',
    statement_type: 'balance_sheet',
    section_code: 'current_assets',
    name_en: '',
    name_ar: '',
    normal_balance: 'debit',
    cash_flow_activity: '',
    sort_order: 0,
    is_active: true,
  });

  const filteredLines = lines.filter((line) => {
    if (activeTab === 'all') return true;
    return line.statement_type === activeTab;
  });

  function openCreateModal() {
    lineForm.reset();
    lineForm.clearErrors();
    setEditingLine(null);
    setShowAddModal(true);
  }

  function openEditModal(line: StatementLineRow) {
    const enName = typeof line.name === 'object' ? line.name?.en || '' : line.name || '';
    const arName = typeof line.name === 'object' ? line.name?.ar || '' : line.name || '';

    lineForm.setData({
      code: line.code,
      statement_type: line.statement_type,
      section_code: line.section_code,
      name_en: enName,
      name_ar: arName,
      normal_balance: line.normal_balance,
      cash_flow_activity: line.cash_flow_activity ?? '',
      sort_order: line.sort_order,
      is_active: line.is_active,
    });
    lineForm.clearErrors();
    setEditingLine(line);
    setShowAddModal(true);
  }

  function submitLineForm(e: FormEvent) {
    e.preventDefault();
    if (editingLine) {
      lineForm.put(`/accounting/statement-mappings/lines/${editingLine.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowAddModal(false);
          setEditingLine(null);
        },
      });
    } else {
      lineForm.post('/accounting/statement-mappings/lines', {
        preserveScroll: true,
        onSuccess: () => {
          setShowAddModal(false);
          lineForm.reset();
        },
      });
    }
  }

  function statementLineDeleteMessage(line: StatementLineRow) {
    return accDict.confirmDeleteStatementLine
      .replace('{code}', line.code)
      .replace('{name}', getLocalizedName(line.name, locale));
  }

  function handleDeleteLine(line: StatementLineRow) {
    if (line.is_system) {
      alert(accDict.cannotDeleteSystemLine);
      return;
    }
    if (line.accounts && line.accounts.length > 0) {
      alert(accDict.cannotDeleteInUseLine);
      return;
    }
    if (!confirm(statementLineDeleteMessage(line))) {
      return;
    }
    router.delete(`/accounting/statement-mappings/lines/${line.id}`, {
      preserveScroll: true,
    });
  }

  function handleAssignAccount(accountId: string, statementLineId: string | null) {
    router.post(
      '/accounting/statement-mappings/assign',
      {
        account_id: accountId,
        financial_statement_line_id: statementLineId,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          if (accountId === selectedUnmappedAccount) {
            setSelectedUnmappedAccount('');
            setTargetLineForAccount('');
          }
        },
      }
    );
  }

  function handleAccountCashFlowActivity(accountId: string, activity: string) {
    router.post(
      '/accounting/statement-mappings/account-cash-flow',
      {
        account_id: accountId,
        cash_flow_activity: activity || null,
      },
      { preserveScroll: true }
    );
  }

  function handleUnmappedAssignSubmit(e: FormEvent) {
    e.preventDefault();
    if (!selectedUnmappedAccount) return;
    handleAssignAccount(selectedUnmappedAccount, targetLineForAccount || null);
  }

  return (
    <AppLayout active="accounting.statement_mappings">
      <Head title={accDict.statementMappingsMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.statementMappings}
          description={accDict.statementMappingsDesc}
          actions={
            canManage ? (
              <button
                type="button"
                onClick={openCreateModal}
                className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-md shadow-blue-500/20 transition-all hover:bg-blue-600"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {accDict.addStatementLine}
              </button>
            ) : null
          }
        />

        {/* Tab Filters */}
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setActiveTab('all')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                activeTab === 'all'
                  ? 'bg-[var(--primary)] text-white'
                  : 'bg-[var(--surface-subtle)] text-[var(--text-secondary)] hover:bg-[var(--background)]'
              }`}
            >
              {accDict.allStatementTypes} ({lines.length})
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('balance_sheet')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                activeTab === 'balance_sheet'
                  ? 'bg-[var(--primary)] text-white'
                  : 'bg-[var(--surface-subtle)] text-[var(--text-secondary)] hover:bg-[var(--background)]'
              }`}
            >
              {accDict.balanceSheet} ({lines.filter((l) => l.statement_type === 'balance_sheet').length})
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('income_statement')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                activeTab === 'income_statement'
                  ? 'bg-[var(--primary)] text-white'
                  : 'bg-[var(--surface-subtle)] text-[var(--text-secondary)] hover:bg-[var(--background)]'
              }`}
            >
              {accDict.incomeStatement} ({lines.filter((l) => l.statement_type === 'income_statement').length})
            </button>
          </div>

          <div className="text-xs text-[var(--text-muted)] font-medium">
            {accDict.unmappedAccounts}:{' '}
            <span className={`font-bold ${unmappedAccounts.length > 0 ? 'text-amber-500' : 'text-emerald-500'}`}>
              {unmappedAccounts.length}
            </span>
          </div>
        </div>

        {unmappedAccounts.length > 0 ? (
          <Card className="border-amber-500/30 bg-amber-500/5 p-4 dark:bg-amber-500/10">
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                  {accDict.unmappedAccounts} ({unmappedAccounts.length})
                </h3>
                <span className="text-[11px] text-[var(--text-muted)]">
                  {accDict.assignToLineDesc}
                </span>
              </div>

              {canManage ? (
                <form onSubmit={handleUnmappedAssignSubmit} className="flex flex-wrap items-center gap-3">
                  <div className="min-w-[220px] flex-1">
                    <SearchableSelect
                      options={unmappedAccounts.map((acc) => ({
                        value: acc.id,
                        label: `${acc.code} - ${getLocalizedName(acc.name, locale)} (${accountTypeLabel(acc.type)})`,
                      }))}
                      value={selectedUnmappedAccount}
                      onChange={(val) => setSelectedUnmappedAccount(val || '')}
                      placeholder={accDict.selectAccount}
                    />
                  </div>

                  <div className="min-w-[220px] flex-1">
                    <SearchableSelect
                      options={lines.map((l) => ({
                        value: l.id,
                        label: `${l.code} - ${getLocalizedName(l.name, locale)} (${statementTypeShortLabel(l.statement_type)})`,
                      }))}
                      value={targetLineForAccount}
                      onChange={(val) => setTargetLineForAccount(val || '')}
                      placeholder={accDict.selectStatementLine}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!selectedUnmappedAccount || !targetLineForAccount}
                    className="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-amber-700 disabled:opacity-50"
                  >
                    {accDict.assignAccount}
                  </button>
                </form>
              ) : null}

              <div className="flex flex-wrap gap-2 pt-2">
                {unmappedAccounts.map((acc) => (
                  <span
                    key={acc.id}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--surface)] px-2.5 py-1 text-[11px] font-medium border border-[var(--border)] text-[var(--text-primary)]"
                  >
                    <span className="font-mono font-bold text-amber-600 dark:text-amber-400">{acc.code}</span>
                    <span>{getLocalizedName(acc.name, locale)}</span>
                  </span>
                ))}
              </div>
            </div>
          </Card>
        ) : null}

        {filteredLines.length === 0 ? (
          <EmptyState
            title={accDict.noStatementLines}
            description={accDict.noStatementLinesDesc}
          />
        ) : (
          <div className="space-y-4">
            {filteredLines.map((line) => (
              <Card key={line.id} className="overflow-hidden border border-[var(--border)]">
                <div className="flex flex-wrap items-center justify-between gap-3 bg-[var(--surface-subtle)] px-4 py-3 border-b border-[var(--border)]">
                  <div className="flex flex-wrap items-center gap-3">
                    <span className="font-mono text-xs font-extrabold text-[var(--primary)]">{line.code}</span>
                    <h3 className="text-xs font-bold text-[var(--text-primary)]">
                      {getLocalizedName(line.name, locale)}
                    </h3>
                    <StatusBadge tone={line.statement_type === 'balance_sheet' ? 'ok' : 'info'}>
                      {statementTypeLabel(line.statement_type)}
                    </StatusBadge>
                    <StatusBadge tone={line.normal_balance === 'debit' ? 'muted' : 'warning'}>
                      {normalBalanceBadge(line.normal_balance)}
                    </StatusBadge>
                    <StatusBadge tone={line.cash_flow_activity ? 'info' : 'muted'}>
                      {cashFlowActivityLabel(line.cash_flow_activity)}
                    </StatusBadge>
                    {line.is_system ? (
                      <span className="rounded bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                        {accDict.systemBadge}
                      </span>
                    ) : (
                      <span className="rounded bg-gray-500/10 px-2 py-0.5 text-[10px] font-bold text-gray-500">
                        {accDict.customBadge}
                      </span>
                    )}
                    <span className="text-[11px] text-[var(--text-muted)]">
                      {accDict.section}: <code className="font-mono">{sectionLabel(line.section_code)}</code>
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <span className="text-[11px] text-[var(--text-muted)] font-medium">
                      {accDict.mappedAccounts}: {line.accounts?.length || 0}
                    </span>
                    {canManage ? (
                      <>
                        <button
                          type="button"
                          onClick={() => openEditModal(line)}
                          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]"
                          title={actionsDict.edit}
                        >
                          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 012.828 0L19.586 7.586a2 2 0 010 2.828L11.828 18.172l-3.536.707.707-3.536L16.586 3.586z" />
                          </svg>
                        </button>
                        {!line.is_system && (!line.accounts || line.accounts.length === 0) ? (
                          <button
                            type="button"
                            onClick={() => handleDeleteLine(line)}
                            className="rounded-lg p-1 text-red-500 hover:bg-red-500/10"
                            title={actionsDict.delete}
                          >
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        ) : null}
                      </>
                    ) : null}
                  </div>
                </div>

                <div className="overflow-x-auto">
                  {line.accounts && line.accounts.length > 0 ? (
                    <table className={tableClasses.table}>
                      <thead>
                        <tr className="border-b border-[var(--border)] bg-[var(--surface-subtle)] text-start text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
                          <th className={tableClasses.th}>{accDict.accountCode}</th>
                          <th className={tableClasses.th}>{accDict.accountName}</th>
                          <th className={tableClasses.th}>{accDict.accountType}</th>
                          <th className={tableClasses.th}>{accDict.accountGroup}</th>
                          <th className={tableClasses.th}>{accDict.cashFlowActivity}</th>
                          {canManage ? <th className={`${tableClasses.th} text-end`}>{actionsDict.actionsTitle}</th> : null}
                        </tr>
                      </thead>
                      <tbody>
                        {line.accounts.map((acc) => (
                          <tr key={acc.id} className="border-b border-[var(--border)] hover:bg-[var(--surface-subtle)] transition-colors">
                            <td className={`${tableClasses.td} font-mono font-bold text-[var(--primary)]`}>
                              {acc.code}
                            </td>
                            <td className={`${tableClasses.td} font-semibold`}>
                              {getLocalizedName(acc.name, locale)}
                            </td>
                            <td className={tableClasses.td}>
                              {acc.accountType ? getLocalizedName(acc.accountType.name, locale) : accountTypeLabel(acc.type)}
                            </td>
                            <td className={tableClasses.td}>
                              {acc.group ? getLocalizedName(acc.group.name, locale) : accDict.notAssigned}
                            </td>
                            <td className={tableClasses.td}>
                              {canManage ? (
                                <select
                                  value={acc.cash_flow_activity ?? ''}
                                  onChange={(e) => handleAccountCashFlowActivity(acc.id, e.target.value)}
                                  className="min-w-[150px] rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1 text-[11px] text-[var(--text-primary)]"
                                >
                                  <option value="">{accDict.cashFlowActivityInherited}</option>
                                  {cashFlowActivities.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                      {cashFlowActivityLabel(opt.value)}
                                    </option>
                                  ))}
                                </select>
                              ) : (
                                <span>{cashFlowActivityLabel(acc.cash_flow_activity ?? line.cash_flow_activity)}</span>
                              )}
                            </td>
                            {canManage ? (
                              <td className={`${tableClasses.td} text-end`}>
                                <button
                                  type="button"
                                  onClick={() => handleAssignAccount(acc.id, null)}
                                  className="text-xs font-semibold text-red-500 hover:underline cursor-pointer"
                                >
                                  {accDict.unassign}
                                </button>
                              </td>
                            ) : null}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  ) : (
                    <div className="p-4 text-center text-xs text-[var(--text-muted)] italic">
                      {accDict.noMappedAccounts}
                    </div>
                  )}
                </div>
              </Card>
            ))}
          </div>
        )}

        {showAddModal ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="w-full max-w-lg space-y-4 p-6 bg-[var(--surface)] border border-[var(--border)]">
              <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
                <h3 className="text-sm font-bold text-[var(--text-primary)]">
                  {editingLine
                    ? accDict.editStatementLine
                    : accDict.addStatementLine}
                </h3>
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="text-[var(--text-muted)] hover:text-[var(--text-primary)]"
                >
                  <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <form onSubmit={submitLineForm} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.lineCode} *
                    </label>
                    <input
                      type="text"
                      required
                      disabled={editingLine?.is_system}
                      value={lineForm.data.code}
                      onChange={(e) => lineForm.setData('code', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)] disabled:opacity-50"
                    />
                    {lineForm.errors.code ? (
                      <p className="mt-1 text-[11px] text-red-500">{lineForm.errors.code}</p>
                    ) : null}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.statementType} *
                    </label>
                    <select
                      disabled={editingLine?.is_system}
                      value={lineForm.data.statement_type}
                      onChange={(e) => lineForm.setData('statement_type', toStatementType(e.target.value))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] disabled:opacity-50"
                    >
                      {statementTypes.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {statementTypeLabel(opt.value)}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.sectionCode} *
                    </label>
                    <select
                      value={lineForm.data.section_code}
                      onChange={(e) => lineForm.setData('section_code', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    >
                      {sectionOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {sectionLabel(opt.value)}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.normalBalance} *
                    </label>
                    <select
                      value={lineForm.data.normal_balance}
                      onChange={(e) => lineForm.setData('normal_balance', toNormalBalance(e.target.value))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    >
                      {normalBalances.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {normalBalanceLabel(opt.value)}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {accDict.cashFlowActivity}
                  </label>
                  <select
                    value={lineForm.data.cash_flow_activity}
                    onChange={(e) => lineForm.setData('cash_flow_activity', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  >
                    <option value="">{accDict.cashFlowActivityUnclassified}</option>
                    {cashFlowActivities.map((opt) => (
                      <option key={opt.value} value={opt.value}>
                        {cashFlowActivityLabel(opt.value)}
                      </option>
                    ))}
                  </select>
                  <p className="mt-1 text-[11px] text-[var(--text-muted)]">
                    {accDict.cashFlowActivityLineHelp}
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.nameEn} *
                    </label>
                    <input
                      type="text"
                      required
                      value={lineForm.data.name_en}
                      onChange={(e) => lineForm.setData('name_en', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                      {accDict.nameAr}
                    </label>
                    <input
                      type="text"
                      value={lineForm.data.name_ar}
                      onChange={(e) => lineForm.setData('name_ar', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {accDict.sortOrder}
                  </label>
                  <input
                    type="number"
                    value={lineForm.data.sort_order}
                    onChange={(e) => lineForm.setData('sort_order', parseInt(e.target.value, 10) || 0)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  />
                </div>

                <div className="flex justify-end gap-3 pt-4 border-t border-[var(--border)]">
                  <button
                    type="button"
                    onClick={() => setShowAddModal(false)}
                    className="rounded-xl bg-[var(--surface-subtle)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                  >
                    {actionsDict.cancel}
                  </button>
                  <button
                    type="submit"
                    disabled={lineForm.processing}
                    className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-600 disabled:opacity-50"
                  >
                    {actionsDict.save}
                  </button>
                </div>
              </form>
            </Card>
          </div>
        ) : null}
      </div>
    </AppLayout>
  );
}
