import { Head, useForm, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary, interpolate } from '../../lib/i18n';
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

const UNMAPPED_COLLAPSE_KEY = 'statement_mappings_unmapped_collapsed';

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
  const fieldsDict = dict.app.fields;

  const can = useCan();
  const canManage = can('accounting.mappings');

  const [viewMode, setViewMode] = useState<'datatable' | 'cards'>('datatable');
  const [activeTab, setActiveTab] = useState<'all' | 'balance_sheet' | 'income_statement'>('all');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingLine, setEditingLine] = useState<StatementLineRow | null>(null);
  const [pageError, setPageError] = useState<string | null>(null);

  const [selectedUnmappedAccount, setSelectedUnmappedAccount] = useState<string>('');
  const [targetLineForAccount, setTargetLineForAccount] = useState<string>('');

  // ServerDataTable Filter States
  const [dtStatementType, setDtStatementType] = useState<string>('');
  const [dtMappingStatus, setDtMappingStatus] = useState<string>('');
  const [dtCashFlow, setDtCashFlow] = useState<string>('');
  const [dtSection, setDtSection] = useState<string>('');
  const [unmappedSearch, setUnmappedSearch] = useState('');
  const [unmappedExpanded, setUnmappedExpanded] = useState(false);
  // The panel can list hundreds of chips, so it starts collapsed and the
  // choice is remembered — same treatment as the sidebar collapse.
  const [unmappedCollapsed, setUnmappedCollapsed] = useState(true);


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

  const statementTypeOptions = useMemo(
    () => statementTypes.map((opt) => ({
      value: toStatementType(opt.value),
      label: statementTypeLabel(opt.value),
      sublabel: opt.value,
    })),
    [statementTypes, statementTypeLabel],
  );

  const sectionSelectOptions = useMemo(
    () => sectionOptions.map((opt) => ({
      value: opt.value,
      label: sectionLabel(opt.value),
      sublabel: opt.value,
    })),
    [sectionOptions, sectionLabel],
  );

  const normalBalanceOptions = useMemo(
    () => normalBalances.map((opt) => ({
      value: toNormalBalance(opt.value),
      label: normalBalanceLabel(opt.value),
      sublabel: opt.value,
    })),
    [normalBalances, normalBalanceLabel],
  );

  const cashFlowActivityOptions = useMemo(
    () => [
      { value: '', label: accDict.cashFlowActivityUnclassified },
      ...cashFlowActivities.map((opt) => ({
        value: opt.value,
        label: cashFlowActivityLabel(opt.value),
        sublabel: opt.value,
      })),
    ],
    [accDict.cashFlowActivityUnclassified, cashFlowActivities, cashFlowActivityLabel],
  );

  const accountCashFlowActivityOptions = useMemo(
    () => [
      { value: '', label: accDict.cashFlowActivityInherited },
      ...cashFlowActivities.map((opt) => ({
        value: opt.value,
        label: cashFlowActivityLabel(opt.value),
        sublabel: opt.value,
      })),
    ],
    [accDict.cashFlowActivityInherited, cashFlowActivities, cashFlowActivityLabel],
  );

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

  // Calculate statistics
  const totalMappedAccountsCount = lines.reduce((acc, curr) => acc + (curr.accounts?.length || 0), 0);
  const bsLinesCount = lines.filter((l) => l.statement_type === 'balance_sheet').length;
  const isLinesCount = lines.filter((l) => l.statement_type === 'income_statement').length;

  useEffect(() => {
    const saved = localStorage.getItem(UNMAPPED_COLLAPSE_KEY);
    if (saved !== null) {
      setUnmappedCollapsed(saved === 'true');
    }
  }, []);

  function toggleUnmappedCollapse() {
    setUnmappedCollapsed((prev) => {
      const next = !prev;
      localStorage.setItem(UNMAPPED_COLLAPSE_KEY, String(next));
      return next;
    });
  }

  const UNMAPPED_DISPLAY_LIMIT = 40;
  const filteredUnmapped = useMemo(() => {
    if (!unmappedSearch.trim()) return unmappedAccounts;
    const q = unmappedSearch.toLowerCase();
    return unmappedAccounts.filter((acc) =>
      acc.code.toLowerCase().includes(q) ||
      getLocalizedName(acc.name, locale).toLowerCase().includes(q),
    );
  }, [unmappedAccounts, unmappedSearch, locale]);


  function openCreateModal() {
    lineForm.reset();
    lineForm.clearErrors();
    setPageError(null);
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
    setPageError(null);
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
      setPageError(accDict.cannotDeleteSystemLine);
      return;
    }
    if (line.accounts && line.accounts.length > 0) {
      setPageError(accDict.cannotDeleteInUseLine);
      return;
    }
    if (!confirm(statementLineDeleteMessage(line))) {
      return;
    }
    setPageError(null);
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
        preserveState: true,
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
      {
        preserveScroll: true,
        preserveState: true,
      }
    );
  }

  function handleUnmappedAssignSubmit(e: FormEvent) {
    e.preventDefault();
    if (!selectedUnmappedAccount) return;
    handleAssignAccount(selectedUnmappedAccount, targetLineForAccount || null);
  }

  const linesRef = useRef(lines);
  linesRef.current = lines;

  const cfOptionsRef = useRef(accountCashFlowActivityOptions);
  cfOptionsRef.current = accountCashFlowActivityOptions;

  // ── DataTables columns & slots ──────────────────────────────────────────────
  const dtColumns = useMemo(() => [
    { data: 'code', name: 'code', title: dict.app.fields.code, className: 'font-mono font-bold text-xs', width: '120px' },
    { data: 'name', name: 'name', title: accDict.accountName },
    { data: 'account_type_name', name: 'account_type_name', title: accDict.accountType },
    { data: 'line_code', name: 'line_code', title: accDict.code, width: '120px', className: 'font-mono text-xs font-bold' },
    { data: 'line_name', name: 'line_name', title: accDict.statementLineName },
    { data: 'statement_type', name: 'statement_type', title: accDict.statementType, searchable: false, width: '130px' },
    { data: 'account_cash_flow_activity', name: 'account_cash_flow_activity', title: accDict.cashFlowActivity, searchable: false, width: '180px' },
    ...(canManage ? [{ data: 'actions', name: 'actions', title: accDict.assignAccount, searchable: false, orderable: false, width: '220px' }] : []),
  ], [dict, accDict, canManage]);

  const dtSlots = useMemo<DataTableSlots>(() => ({
    code: (d: any) => (
      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
        {String(d || '')}
      </span>
    ),
    name: (d: any) => (
      <span className="font-bold text-xs text-[var(--text-primary)]">
        {getLocalizedName(d, locale)}
      </span>
    ),
    account_type_name: (d: any, _t: any, row: any) => (
      <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
        {d ? getLocalizedName(d, locale) : accountTypeLabel(row?.type)}
      </span>
    ),
    line_code: (d: any) => (
      d ? (
        <span className="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{String(d)}</span>
      ) : (
        <span className="text-xs font-bold text-amber-500 px-2 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/20">
          {accDict.unmapped}
        </span>
      )
    ),
    line_name: (d: any) => (
      d ? (
        <span className="text-xs font-bold text-[var(--text-primary)]">{getLocalizedName(d, locale)}</span>
      ) : (
        <span className="text-xs text-[var(--text-muted)] italic">{accDict.notAssigned}</span>
      )
    ),
    statement_type: (d: any) => (
      d ? (
        <StatusBadge tone={d === 'balance_sheet' ? 'ok' : 'info'}>
          {statementTypeLabel(d)}
        </StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      )
    ),
    account_cash_flow_activity: (d: any, _t: any, row: any) => (
      canManage ? (
        <SearchableSelect
          value={d ?? ''}
          options={cfOptionsRef.current}
          onChange={(val) => handleAccountCashFlowActivity(row.id, val ?? '')}
          placeholder={accDict.cashFlowActivityInherited}
          isSearchable={true}
          locale={locale}
          className="min-w-[160px]"
        />
      ) : (
        <span className="text-xs font-medium text-[var(--text-secondary)]">
          {cashFlowActivityLabel(d ?? row.line_cash_flow_activity)}
        </span>
      )
    ),
    actions: (_d: any, _t: any, row: any) => (
      canManage ? (
        <SearchableSelect
          value={row.financial_statement_line_id ?? ''}
          options={[
            { value: '', label: accDict.unassignOption },
            ...linesRef.current.map((l) => ({
              value: l.id,
              label: `${l.code} - ${getLocalizedName(l.name, locale)} (${statementTypeShortLabel(l.statement_type)})`,
            })),
          ]}
          onChange={(lineId) => handleAssignAccount(row.id, lineId || null)}
          placeholder={accDict.selectStatementLine}
          isSearchable={true}
          locale={locale}
          className="min-w-[200px]"
        />
      ) : null
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [dict, accDict, locale, canManage]);

  const dtFilters = useMemo(() => ({
    statement_type: dtStatementType,
    mapping_status: dtMappingStatus,
    cash_flow_activity: dtCashFlow,
    section_code: dtSection,
  }), [dtStatementType, dtMappingStatus, dtCashFlow, dtSection]);

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const dtToolbar = useMemo(() => (
    <div className="flex flex-col md:flex-row md:items-center gap-2.5 w-full">
      <div className="flex items-center gap-1.5 px-2.5 py-2 rounded-xl bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] text-xs font-bold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] whitespace-nowrap shrink-0 self-start md:self-auto">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        <span>{actionsDict.filter}</span>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 flex-1 w-full">
        <SearchableSelect
          options={[
            { value: '', label: accDict.allStatementTypes },
            { value: 'balance_sheet', label: accDict.balanceSheet },
            { value: 'income_statement', label: accDict.incomeStatement },
          ]}
          value={dtStatementType}
          onChange={(v) => setDtStatementType(v || '')}
          placeholder={accDict.statementType}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={[
            { value: '', label: accDict.allMappingStatuses },
            { value: 'mapped', label: accDict.mappedAccounts },
            { value: 'unmapped', label: accDict.unmappedAccounts },
          ]}
          value={dtMappingStatus}
          onChange={(v) => setDtMappingStatus(v || '')}
          placeholder={accDict.mappingStatus}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={[
            { value: '', label: accDict.allCashFlowActivities },
            ...cashFlowActivities.map((opt) => ({
              value: opt.value,
              label: cashFlowActivityLabel(opt.value),
            })),
          ]}
          value={dtCashFlow}
          onChange={(v) => setDtCashFlow(v || '')}
          placeholder={accDict.cashFlowActivity}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={[
            { value: '', label: accDict.allSections },
            ...sectionOptions.map((opt) => ({
              value: opt.value,
              label: sectionLabel(opt.value),
            })),
          ]}
          value={dtSection}
          onChange={(v) => setDtSection(v || '')}
          placeholder={accDict.section}
          isSearchable={true}
          className="w-full"
        />
      </div>

      {(dtStatementType || dtMappingStatus || dtCashFlow || dtSection) ? (
        <button
          type="button"
          onClick={() => {
            setDtStatementType('');
            setDtMappingStatus('');
            setDtCashFlow('');
            setDtSection('');
          }}
          title={actionsDict.reset}
          aria-label={actionsDict.reset}
          className="inline-flex items-center justify-center gap-1 text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-700 px-3 py-2 rounded-xl border border-red-500/20 bg-red-500/10 transition-all cursor-pointer whitespace-nowrap shrink-0 self-start md:self-auto"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{actionsDict.reset}</span>
        </button>
      ) : null}
    </div>
  ), [locale, dtStatementType, dtMappingStatus, dtCashFlow, dtSection]);



  return (
    <AppLayout active="accounting.statement_mappings">
      <Head title={accDict.statementMappingsMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.statementMappings}
          description={accDict.statementMappingsDesc}
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-1 p-1 rounded-xl bg-[var(--surface-subtle)] border border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setViewMode('datatable')}
                  title={accDict.detailsTable}
                  aria-label={accDict.detailsTable}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                    viewMode === 'datatable'
                      ? 'bg-[var(--primary)] text-white shadow-sm'
                      : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7-8v8m14-8v8M3 6h18" />
                  </svg>
                  <span>{accDict.detailsTable}</span>
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('cards')}
                  title={accDict.statementLines}
                  aria-label={accDict.statementLines}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                    viewMode === 'cards'
                      ? 'bg-[var(--primary)] text-white shadow-sm'
                      : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                  </svg>
                  <span>{accDict.statementLines}</span>
                </button>
              </div>

              {canManage ? (
                <button
                  type="button"
                  onClick={openCreateModal}
                  title={accDict.addStatementLine}
                  aria-label={accDict.addStatementLine}
                  className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-md shadow-blue-500/20 transition-all hover:bg-blue-600"
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                  </svg>
                  {accDict.addStatementLine}
                </button>
              ) : null}
            </div>
          }
        />

        {/* Dashboard KPI Stats Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <Card className="p-4 border border-[var(--border)] bg-gradient-to-br from-blue-500/5 to-transparent">
            <div className="text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
              {accDict.totalLines}
            </div>
            <div className="text-xl font-black text-[var(--text-primary)] mt-1">{lines.length}</div>
          </Card>
          <Card className="p-4 border border-[var(--border)] bg-gradient-to-br from-indigo-500/5 to-transparent">
            <div className="text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
              {accDict.balanceSheet}
            </div>
            <div className="text-xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{bsLinesCount}</div>
          </Card>
          <Card className="p-4 border border-[var(--border)] bg-gradient-to-br from-purple-500/5 to-transparent">
            <div className="text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
              {accDict.incomeStatement}
            </div>
            <div className="text-xl font-black text-purple-600 dark:text-purple-400 mt-1">{isLinesCount}</div>
          </Card>
          <Card className="p-4 border border-[var(--border)] bg-gradient-to-br from-emerald-500/5 to-transparent">
            <div className="text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
              {accDict.mappedAccounts}
            </div>
            <div className="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{totalMappedAccountsCount}</div>
          </Card>
          <Card className={`p-4 border ${unmappedAccounts.length > 0 ? 'border-amber-500/40 bg-amber-500/10' : 'border-[var(--border)] bg-gradient-to-br from-gray-500/5 to-transparent'}`}>
            <div className="text-[11px] font-bold text-[var(--text-muted)] uppercase tracking-wider">
              {accDict.unmappedAccounts}
            </div>
            <div className={`text-xl font-black mt-1 ${unmappedAccounts.length > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-500'}`}>
              {unmappedAccounts.length}
            </div>
          </Card>
        </div>

        {pageError ? (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-xs font-semibold text-red-600 dark:text-red-400">
            {pageError}
          </div>
        ) : null}

        {/* Unmapped Accounts Quick Assign Panel */}
        {unmappedAccounts.length > 0 ? (
          <Card className="border-amber-500/30 bg-amber-500/5 p-4 dark:bg-amber-500/10">
            <div className="space-y-3">
              <button
                type="button"
                onClick={toggleUnmappedCollapse}
                aria-expanded={!unmappedCollapsed}
                aria-controls="unmapped-accounts-panel"
                title={unmappedCollapsed ? actionsDict.expand : actionsDict.collapse}
                aria-label={unmappedCollapsed ? actionsDict.expand : actionsDict.collapse}
                className="flex w-full items-center justify-between gap-3 text-start cursor-pointer"
              >
                <h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                  <svg
                    className={`size-3.5 shrink-0 transition-transform duration-200 ${unmappedCollapsed ? '' : 'rotate-180'}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                  {accDict.unmappedAccounts} ({unmappedAccounts.length})
                </h3>
                <span className="text-[11px] text-[var(--text-muted)]">
                  {accDict.assignToLineDesc}
                </span>
              </button>

              <div id="unmapped-accounts-panel" className="space-y-3" hidden={unmappedCollapsed}>
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
                      isSearchable={true}
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
                      isSearchable={true}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!selectedUnmappedAccount || !targetLineForAccount}
                    title={accDict.assignAccount}
                    aria-label={accDict.assignAccount}
                    className="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-amber-700 disabled:opacity-50"
                  >
                    {accDict.assignAccount}
                  </button>
                </form>
              ) : null}

              {/* Search bar for unmapped accounts */}
              <div className="flex items-center gap-2 pt-1">
                <div className="relative flex-1 max-w-xs">
                  <svg className="absolute start-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--text-muted)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                  </svg>
                  <input
                    type="search"
                    value={unmappedSearch}
                    onChange={(e) => setUnmappedSearch(e.target.value)}
                    placeholder={accDict.searchAccountsPlaceholder}
                    className="h-8 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] ps-9 pe-3 text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all"
                  />
                </div>
                <span className="text-[11px] text-[var(--text-muted)] shrink-0">
                  {filteredUnmapped.length}/{unmappedAccounts.length}
                </span>
              </div>

              <div className="flex flex-wrap gap-2 pt-1">
                {(unmappedExpanded || unmappedSearch ? filteredUnmapped : filteredUnmapped.slice(0, UNMAPPED_DISPLAY_LIMIT)).map((acc) => (
                  <span
                    key={acc.id}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--surface)] px-2.5 py-1 text-[11px] font-medium border border-[var(--border)] text-[var(--text-primary)] hover:border-amber-400/60 transition-colors cursor-default"
                  >
                    <span className="font-mono font-bold text-amber-600 dark:text-amber-400">{acc.code}</span>
                    <span>{getLocalizedName(acc.name, locale)}</span>
                  </span>
                ))}
                {filteredUnmapped.length === 0 && (
                  <span className="text-xs text-[var(--text-muted)] italic py-2">
                    {accDict.noResults}
                  </span>
                )}
              </div>

              {!unmappedSearch && filteredUnmapped.length > UNMAPPED_DISPLAY_LIMIT && (
                <button
                  type="button"
                  onClick={() => setUnmappedExpanded((v) => !v)}
                  title={unmappedExpanded ? actionsDict.showLess : actionsDict.showMore}
                  aria-label={unmappedExpanded ? actionsDict.showLess : actionsDict.showMore}
                  className="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer mt-1"
                >
                  {unmappedExpanded
                    ? actionsDict.showLess
                    : interpolate(accDict.showMoreCount, {
                        count: filteredUnmapped.length - UNMAPPED_DISPLAY_LIMIT,
                      })}
                </button>
              )}
              </div>

            </div>
          </Card>
        ) : null}

        {/* View Mode 1: ServerDataTable */}
        {viewMode === 'datatable' ? (
          <Card className="p-4 border border-[var(--border)] space-y-4">
            <ServerDataTable
              ajaxUrl="/accounting/statement-mappings/data"
              columns={dtColumns}
              slots={dtSlots}
              toolbar={dtToolbar}
              filters={dtFilters}
              locale={locale}
              tableId="statement-mappings-table"
            />
          </Card>
        ) : (
          /* View Mode 2: Cards / Statement Lines Tree */
          <div className="space-y-6">
            {/* Tab Filters */}
            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setActiveTab('all')}
                  title={accDict.allStatementTypes}
                  aria-label={accDict.allStatementTypes}
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
                  title={accDict.balanceSheet}
                  aria-label={accDict.balanceSheet}
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
                  title={accDict.incomeStatement}
                  aria-label={accDict.incomeStatement}
                  className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                    activeTab === 'income_statement'
                      ? 'bg-[var(--primary)] text-white'
                      : 'bg-[var(--surface-subtle)] text-[var(--text-secondary)] hover:bg-[var(--background)]'
                  }`}
                >
                  {accDict.incomeStatement} ({lines.filter((l) => l.statement_type === 'income_statement').length})
                </button>
              </div>
            </div>

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
                              title={`${actionsDict.edit} ${line.code}`}
                              aria-label={`${actionsDict.edit} ${line.code}`}
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
                                title={`${actionsDict.delete} ${line.code}`}
                                aria-label={`${actionsDict.delete} ${line.code}`}
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
                                    <SearchableSelect
                                      value={acc.cash_flow_activity ?? ''}
                                      options={accountCashFlowActivityOptions}
                                      onChange={(value) => handleAccountCashFlowActivity(acc.id, value ?? '')}
                                      placeholder={accDict.cashFlowActivityInherited}
                                      isSearchable={true}
                                      className="min-w-[180px]"
                                    />
                                  ) : (
                                    <span>{cashFlowActivityLabel(acc.cash_flow_activity ?? line.cash_flow_activity)}</span>
                                  )}
                                </td>
                                {canManage ? (
                                  <td className={`${tableClasses.td} text-end`}>
                                    <button
                                      type="button"
                                      onClick={() => handleAssignAccount(acc.id, null)}
                                      title={`${accDict.unassign} ${acc.code}`}
                                      aria-label={`${accDict.unassign} ${acc.code}`}
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
                  title={actionsDict.close}
                  aria-label={actionsDict.close}
                  className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--surface-subtle)] hover:text-[var(--text-primary)]"
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <form onSubmit={submitLineForm} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.code}
                    </label>
                    <input
                      type="text"
                      value={lineForm.data.code}
                      onChange={(e) => lineForm.setData('code', e.target.value)}
                      placeholder="BS-ASSET-CURR"
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)] uppercase focus:border-[var(--primary)] focus:outline-none"
                    />
                    {lineForm.errors.code ? (
                      <p className="mt-1 text-[11px] font-semibold text-red-500">{lineForm.errors.code}</p>
                    ) : null}
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.statementType}
                    </label>
                    <SearchableSelect<StatementType>
                      options={statementTypeOptions}
                      value={lineForm.data.statement_type}
                      onChange={(val) => lineForm.setData('statement_type', toStatementType(val || 'balance_sheet'))}
                      placeholder={accDict.statementType}
                      isSearchable={true}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {fieldsDict.nameEn}
                    </label>
                    <input
                      type="text"
                      value={lineForm.data.name_en}
                      onChange={(e) => lineForm.setData('name_en', e.target.value)}
                      placeholder={accDict.statementLineNameEnExample}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    />
                    {lineForm.errors.name_en ? (
                      <p className="mt-1 text-[11px] font-semibold text-red-500">{lineForm.errors.name_en}</p>
                    ) : null}
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {fieldsDict.nameAr}
                    </label>
                    <input
                      type="text"
                      value={lineForm.data.name_ar}
                      onChange={(e) => lineForm.setData('name_ar', e.target.value)}
                      placeholder={accDict.statementLineNameArExample}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.section}
                    </label>
                    <SearchableSelect
                      options={sectionSelectOptions}
                      value={lineForm.data.section_code}
                      onChange={(val) => lineForm.setData('section_code', val || 'current_assets')}
                      placeholder={accDict.section}
                      isSearchable={true}
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.normalBalance}
                    </label>
                    <SearchableSelect<NormalBalance>
                      options={normalBalanceOptions}
                      value={lineForm.data.normal_balance}
                      onChange={(val) => lineForm.setData('normal_balance', toNormalBalance(val || 'debit'))}
                      placeholder={accDict.normalBalance}
                      isSearchable={true}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.cashFlowActivity}
                    </label>
                    <SearchableSelect
                      options={cashFlowActivityOptions}
                      value={lineForm.data.cash_flow_activity}
                      onChange={(val) => lineForm.setData('cash_flow_activity', val || '')}
                      placeholder={accDict.cashFlowActivity}
                      isSearchable={true}
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1">
                      {accDict.sortOrder}
                    </label>
                    <input
                      type="number"
                      value={lineForm.data.sort_order}
                      onChange={(e) => lineForm.setData('sort_order', parseInt(e.target.value) || 0)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    />
                  </div>
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-[var(--border)] pt-4">
                  <button
                    type="button"
                    onClick={() => setShowAddModal(false)}
                    title={actionsDict.cancel}
                    aria-label={actionsDict.cancel}
                    className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--surface-subtle)]"
                  >
                    {actionsDict.cancel}
                  </button>
                  <button
                    type="submit"
                    disabled={lineForm.processing}
                    title={actionsDict.save}
                    aria-label={actionsDict.save}
                    className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow hover:bg-blue-600 disabled:opacity-50"
                  >
                    {editingLine ? actionsDict.save : actionsDict.add}
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
