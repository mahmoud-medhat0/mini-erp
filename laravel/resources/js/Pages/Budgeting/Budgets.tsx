import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, Modal, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, formatMoney, formatPeriodLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { BudgetLineRow, BudgetRow, BudgetStatus, PaginationLink, SharedPageProps } from '../../Types';

type PaginatedData<T> = {
  data: T[];
  total: number;
  links: PaginationLink[];
};

type FiscalYearOption = {
  id: string;
  year: number;
  start_date?: string | null;
  end_date?: string | null;
  status?: string;
  periods?: {
    id: string;
    month: number;
    start_date?: string | null;
    end_date?: string | null;
  }[];
};

type FinancialPeriodOption = {
  id: string;
  fiscal_year_id: string;
  month: number;
  start_date?: string | null;
  end_date?: string | null;
  fiscal_year?: { year: number } | null;
};

type AccountOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  type?: string;
  nature?: string;
  currency?: string;
  is_active?: boolean;
};

type ProjectOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  status?: string;
  is_active?: boolean;
};

type CostCenterOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  category?: string | null;
  is_active?: boolean;
};

type CurrencyOption = {
  code: string;
  name: Record<string, string> | string;
  symbol: string;
};

type Props = SharedPageProps & {
  budgets: PaginatedData<BudgetRow>;
  fiscalYears: FiscalYearOption[];
  financialPeriods: FinancialPeriodOption[];
  accounts: AccountOption[];
  projects: ProjectOption[];
  costCenters: CostCenterOption[];
  currencies: CurrencyOption[];
  statuses: BudgetStatus[];
  filters: {
    search?: string;
    fiscal_year_id?: string;
    status?: string;
  };
};

type BudgetLineDraft = {
  id?: string;
  financial_period_id: string;
  account_id: string;
  project_id: string;
  cost_center_id: string;
  currency: string;
  amount_minor: number;
  notes: string;
};

export default function BudgetsIndex({
  locale,
  budgets,
  fiscalYears = [],
  financialPeriods = [],
  accounts = [],
  projects = [],
  costCenters = [],
  currencies = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.budgets;
  const can = useCan();

  const canViewFinancials = can('view_financials');
  const canCreate = can('budgeting.create') && canViewFinancials;
  const canEdit = can('budgeting.edit') && canViewFinancials;
  const canDelete = can('budgeting.delete') && canViewFinancials;
  const canApprove = can('budgeting.approve') && canViewFinancials;

  const [showModal, setShowModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [editingBudget, setEditingBudget] = useState<BudgetRow | null>(null);
  const [viewingBudget, setViewingBudget] = useState<BudgetRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');

  const defaultYearId = fiscalYears[0]?.id || '';
  const defaultCurrencyCode = currencies[0]?.code || '';

  const form = useForm({
    fiscal_year_id: defaultYearId,
    code: '',
    version_code: 'V1',
    name: { en: '', ar: '' },
    description: '',
    default_currency: defaultCurrencyCode,
    lock_version: 1,
    lines: [] as BudgetLineDraft[],
  });

  const statusOptions = useMemo(
    () => [
      { value: 'draft', label: pageDict.statusDraft },
      { value: 'submitted', label: pageDict.statusSubmitted },
      { value: 'approved', label: pageDict.statusApproved },
      { value: 'active', label: pageDict.statusActive },
      { value: 'archived', label: pageDict.statusArchived },
      { value: 'cancelled', label: pageDict.statusCancelled },
    ],
    [
      pageDict.statusActive,
      pageDict.statusApproved,
      pageDict.statusArchived,
      pageDict.statusCancelled,
      pageDict.statusDraft,
      pageDict.statusSubmitted,
    ],
  );

  const statusFilterOptions = useMemo(
    () => [{ value: '', label: pageDict.allStatuses }, ...statusOptions],
    [pageDict.allStatuses, statusOptions],
  );

  const fiscalYearFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allFiscalYears },
      ...fiscalYears.map((fy) => ({
        value: fy.id,
        label: `${fy.year} (${formatDate(fy.start_date)} - ${formatDate(fy.end_date)})`,
      })),
    ],
    [fiscalYears, pageDict.allFiscalYears],
  );

  const activeFilterCount = [search, filters.fiscal_year_id, filters.status].filter(Boolean).length;

  function applyFilters(overrides: Partial<typeof filters> = {}) {
    const current = {
      search,
      fiscal_year_id: filters.fiscal_year_id || '',
      status: filters.status || '',
      ...overrides,
    };
    router.get('/budgeting/budgets', current, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    router.get('/budgeting/budgets', {}, { preserveScroll: true, preserveState: true });
  }

  const activePeriodsForForm = useMemo(() => {
    const targetYearId = form.data.fiscal_year_id;
    if (!targetYearId) return [];
    return financialPeriods.filter((p) => p.fiscal_year_id === targetYearId);
  }, [financialPeriods, form.data.fiscal_year_id]);

  function openCreateModal() {
    setEditingBudget(null);
    const initialYearId = fiscalYears[0]?.id || '';
    const initialPeriods = financialPeriods.filter((p) => p.fiscal_year_id === initialYearId);
    const firstPeriodId = initialPeriods[0]?.id || financialPeriods[0]?.id || '';
    const firstAccountId = accounts[0]?.id || '';

    form.setData({
      fiscal_year_id: initialYearId,
      code: '',
      version_code: 'V1',
      name: { en: '', ar: '' },
      description: '',
      default_currency: defaultCurrencyCode,
      lock_version: 1,
      lines: [
        {
          financial_period_id: firstPeriodId,
          account_id: firstAccountId,
          project_id: '',
          cost_center_id: '',
          currency: defaultCurrencyCode,
          amount_minor: 0,
          notes: '',
        },
      ],
    });
    form.clearErrors();
    setShowModal(true);
  }

  function openEditModal(budget: BudgetRow) {
    setEditingBudget(budget);
    const localizedName = typeof budget.name === 'object' && budget.name !== null ? budget.name : { en: String(budget.name || ''), ar: '' };

    form.setData({
      fiscal_year_id: budget.fiscal_year_id,
      code: budget.code,
      version_code: budget.version_code,
      name: {
        en: localizedName.en || '',
        ar: localizedName.ar || '',
      },
      description: budget.description || '',
      default_currency: budget.default_currency || defaultCurrencyCode,
      lock_version: budget.lock_version,
      lines: (budget.lines || []).map((l) => ({
        id: l.id,
        financial_period_id: l.financial_period_id,
        account_id: l.account_id,
        project_id: l.project_id || '',
        cost_center_id: l.cost_center_id || '',
        currency: l.currency,
        amount_minor: l.amount_minor,
        notes: l.notes || '',
      })),
    });
    form.clearErrors();
    setShowModal(true);
  }

  function openViewModal(budget: BudgetRow) {
    setViewingBudget(budget);
    setShowDetailModal(true);
  }

  function addLine() {
    const firstPeriodId = activePeriodsForForm[0]?.id || financialPeriods[0]?.id || '';
    const firstAccountId = accounts[0]?.id || '';
    form.setData('lines', [
      ...form.data.lines,
      {
        financial_period_id: firstPeriodId,
        account_id: firstAccountId,
        project_id: '',
        cost_center_id: '',
        currency: form.data.default_currency || defaultCurrencyCode,
        amount_minor: 0,
        notes: '',
      },
    ]);
  }

  function removeLine(index: number) {
    form.setData(
      'lines',
      form.data.lines.filter((_, i) => i !== index),
    );
  }

  function updateLine<K extends keyof BudgetLineDraft>(index: number, field: K, value: BudgetLineDraft[K]) {
    const updated = [...form.data.lines];
    updated[index] = { ...updated[index], [field]: value };
    form.setData('lines', updated);
  }

  const duplicateLineIndices = useMemo(() => {
    const seen: Record<string, number> = {};
    const duplicates = new Set<number>();

    form.data.lines.forEach((line, index) => {
      const key = `${line.financial_period_id}|${line.account_id}|${line.project_id || 'null'}|${line.cost_center_id || 'null'}|${line.currency}`;
      if (seen[key] !== undefined) {
        duplicates.add(seen[key]);
        duplicates.add(index);
      } else {
        seen[key] = index;
      }
    });

    return duplicates;
  }, [form.data.lines]);

  const formSummaryByCurrency = useMemo(() => {
    const totals: Record<string, number> = {};
    form.data.lines.forEach((l) => {
      const curr = l.currency || form.data.default_currency || '';
      if (curr) {
        totals[curr] = (totals[curr] || 0) + (Number(l.amount_minor) || 0);
      }
    });
    return totals;
  }, [form.data.default_currency, form.data.lines]);

  function handleSave(e: FormEvent) {
    e.preventDefault();
    if (editingBudget) {
      form.patch(`/budgeting/budgets/${editingBudget.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          setEditingBudget(null);
        },
      });
    } else {
      form.post('/budgeting/budgets', {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
        },
      });
    }
  }

  function handleSubmit(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmSubmitBudget.replace('{code}', budget.code))) {
      router.post(
        `/budgeting/budgets/${budget.id}/submit`,
        { lock_version: budget.lock_version },
        { preserveScroll: true },
      );
    }
  }

  function handleApprove(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmApproveBudget.replace('{code}', budget.code))) {
      router.post(
        `/budgeting/budgets/${budget.id}/approve`,
        { lock_version: budget.lock_version },
        { preserveScroll: true },
      );
    }
  }

  function handleActivate(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmActivateBudget.replace('{code}', budget.code))) {
      router.post(
        `/budgeting/budgets/${budget.id}/activate`,
        { lock_version: budget.lock_version },
        { preserveScroll: true },
      );
    }
  }

  function handleArchive(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmArchiveBudget.replace('{code}', budget.code))) {
      router.post(
        `/budgeting/budgets/${budget.id}/archive`,
        { lock_version: budget.lock_version },
        { preserveScroll: true },
      );
    }
  }

  function handleCancel(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmCancelBudget.replace('{code}', budget.code))) {
      router.post(
        `/budgeting/budgets/${budget.id}/cancel`,
        { lock_version: budget.lock_version },
        { preserveScroll: true },
      );
    }
  }

  function handleDelete(budget: BudgetRow) {
    if (window.confirm(pageDict.confirmDeleteBudget.replace('{code}', budget.code))) {
      router.delete(`/budgeting/budgets/${budget.id}`, { preserveScroll: true });
    }
  }

  function getStatusTone(status: BudgetStatus): 'ok' | 'warning' | 'info' | 'muted' | 'danger' {
    switch (status) {
      case 'active':
        return 'ok';
      case 'approved':
        return 'info';
      case 'submitted':
        return 'warning';
      case 'draft':
        return 'muted';
      case 'archived':
        return 'muted';
      case 'cancelled':
        return 'danger';
      default:
        return 'muted';
    }
  }

  function getStatusLabel(status: BudgetStatus): string {
    switch (status) {
      case 'draft':
        return pageDict.statusDraft;
      case 'submitted':
        return pageDict.statusSubmitted;
      case 'approved':
        return pageDict.statusApproved;
      case 'active':
        return pageDict.statusActive;
      case 'archived':
        return pageDict.statusArchived;
      case 'cancelled':
        return pageDict.statusCancelled;
      default:
        return status;
    }
  }

  function computeRowTotals(lines?: BudgetLineRow[]): Record<string, number> {
    const map: Record<string, number> = {};
    if (!lines) return map;
    lines.forEach((l) => {
      const c = l.currency || '';
      if (c) {
        map[c] = (map[c] || 0) + (Number(l.amount_minor) || 0);
      }
    });
    return map;
  }

  function paginationLabel(label: string): string {
    if (label.includes('&laquo;')) return pageDict.previousPage;
    if (label.includes('&raquo;')) return pageDict.nextPage;

    return label.replace(/<[^>]*>/g, '');
  }

  return (
    <AppLayout active="budgeting.budgets">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canCreate ? (
            <Button onClick={openCreateModal} title={pageDict.createBudget} aria-label={pageDict.createBudget}>
              {pageDict.createBudget}
            </Button>
          ) : null
        }
      />

      {/* Filter Bar */}
      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={pageDict.search}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                applyFilters({ search });
              }
            }}
            className="w-64 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect
            options={fiscalYearFilterOptions}
            value={filters.fiscal_year_id || ''}
            onChange={(value) => applyFilters({ fiscal_year_id: value || '' })}
            className="w-52"
            isSearchable={false}
          />
          <SearchableSelect
            options={statusFilterOptions}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-40"
            isSearchable={false}
          />
          <Button
            variant="secondary"
            onClick={clearFilters}
            disabled={activeFilterCount === 0}
            title={pageDict.clearFilter}
            aria-label={pageDict.clearFilter}
          >
            {pageDict.clearFilter}
          </Button>
        </div>
      </Card>

      {/* Main Budgets Table */}
      {budgets.data.length === 0 ? (
        <EmptyState title={pageDict.noBudgets} description={pageDict.noBudgetsDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead className="border-b border-[var(--border)] bg-[var(--surface-hover)]">
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.fiscalYear}</th>
                <th className={tableClasses.th}>{pageDict.versionCode}</th>
                <th className={tableClasses.th}>{pageDict.nameEn}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.linesCount}</th>
                <th className={tableClasses.th}>{pageDict.totalAmount}</th>
                <th className={`${tableClasses.th} text-end`}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {budgets.data.map((b) => {
                const rowTotals = computeRowTotals(b.lines);
                const isDraft = b.status === 'draft';
                const isSubmitted = b.status === 'submitted';
                const isApproved = b.status === 'approved';
                const isActive = b.status === 'active';
                const totalLines = b.lines ? b.lines.length : 0;
                const totalAmountSum = Object.values(rowTotals).reduce((sum, v) => sum + v, 0);

                return (
                  <tr key={b.id} className="hover:bg-[var(--surface-hover)] transition-colors">
                    <td className={`${tableClasses.td} font-medium`}>
                      <button
                        type="button"
                        onClick={() => openViewModal(b)}
                        title={b.code}
                        aria-label={b.code}
                        className="text-[var(--primary)] hover:underline font-semibold text-start cursor-pointer"
                      >
                        {b.code}
                      </button>
                    </td>
                    <td className={tableClasses.td}>
                      {b.fiscal_year?.year || '-'}
                    </td>
                    <td className={tableClasses.td}>
                      <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-blue-500/10 text-blue-500">
                        {b.version_code}
                      </span>
                    </td>
                    <td className={tableClasses.td}>{getLocalizedName(b.name, locale)}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(b.status)}>{getStatusLabel(b.status)}</StatusBadge>
                    </td>
                    <td className={tableClasses.td}>{totalLines}</td>
                    <td className={tableClasses.td}>
                      {Object.keys(rowTotals).length === 0 ? (
                        <span className="text-[var(--text-muted)]">-</span>
                      ) : (
                        <div className="space-y-0.5">
                          {Object.entries(rowTotals).map(([curr, amt]) => (
                            <div key={curr} className="font-mono text-xs">
                              {formatMoney(amt, curr)}
                            </div>
                          ))}
                        </div>
                      )}
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex items-center justify-end gap-1.5 flex-wrap">
                        <Button
                          variant="secondary"
                          className="px-2.5 py-1 text-xs"
                          onClick={() => openViewModal(b)}
                          title={pageDict.viewDetails}
                          aria-label={pageDict.viewDetails}
                        >
                          {pageDict.viewDetails}
                        </Button>

                        {/* Lifecycle buttons based on status & permissions */}
                        {isDraft && canEdit ? (
                          <Button
                            variant="secondary"
                            className="px-2.5 py-1 text-xs"
                            onClick={() => openEditModal(b)}
                            title={pageDict.edit}
                            aria-label={pageDict.edit}
                          >
                            {pageDict.edit}
                          </Button>
                        ) : null}

                        {isDraft && canEdit && totalLines > 0 && totalAmountSum > 0 ? (
                          <Button
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleSubmit(b)}
                            title={pageDict.submit}
                            aria-label={pageDict.submit}
                          >
                            {pageDict.submit}
                          </Button>
                        ) : null}

                        {isSubmitted && canApprove ? (
                          <Button
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleApprove(b)}
                            title={pageDict.approve}
                            aria-label={pageDict.approve}
                          >
                            {pageDict.approve}
                          </Button>
                        ) : null}

                        {isApproved && canApprove ? (
                          <Button
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleActivate(b)}
                            title={pageDict.activate}
                            aria-label={pageDict.activate}
                          >
                            {pageDict.activate}
                          </Button>
                        ) : null}

                        {(isApproved || isActive) && canApprove ? (
                          <Button
                            variant="secondary"
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleArchive(b)}
                            title={pageDict.archive}
                            aria-label={pageDict.archive}
                          >
                            {pageDict.archive}
                          </Button>
                        ) : null}

                        {(isDraft || isSubmitted) && canEdit ? (
                          <Button
                            variant="danger"
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleCancel(b)}
                            title={pageDict.cancelBudget}
                            aria-label={pageDict.cancelBudget}
                          >
                            {pageDict.cancelBudget}
                          </Button>
                        ) : null}

                        {isDraft && canDelete ? (
                          <Button
                            variant="danger"
                            className="px-2.5 py-1 text-xs"
                            onClick={() => handleDelete(b)}
                            title={pageDict.delete}
                            aria-label={pageDict.delete}
                          >
                            {pageDict.delete}
                          </Button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination Links */}
      {budgets.links && budgets.links.length > 3 ? (
        <div className="flex justify-center items-center gap-1 mt-6 flex-wrap">
          {budgets.links.map((link, idx) => (
            <Link
              key={idx}
              href={link.url || '#'}
              preserveScroll
              preserveState
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                link.active
                  ? 'bg-[var(--primary)] text-white font-bold'
                  : link.url
                    ? 'bg-[var(--surface)] text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] border border-[var(--border)]'
                    : 'text-[var(--text-muted)] cursor-not-allowed border border-[var(--border)] opacity-50'
              }`}
            >
              {paginationLabel(link.label)}
            </Link>
          ))}
        </div>
      ) : null}

      {/* Create / Edit Modal */}
      {showModal ? (
        <Modal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          title={editingBudget ? pageDict.editBudget : pageDict.createBudget}
        >
          <form onSubmit={handleSave} className="space-y-6">
            {/* Header section */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.fiscalYear} <span className="text-red-500">*</span>
                </label>
                <SearchableSelect
                  options={fiscalYears.map((fy) => ({
                    value: fy.id,
                    label: `${fy.year} (${formatDate(fy.start_date)} - ${formatDate(fy.end_date)})`,
                  }))}
                  value={form.data.fiscal_year_id}
                  onChange={(val) => {
                    const yearId = val || '';
                    form.setData('fiscal_year_id', yearId);
                    const periodsForNewYear = financialPeriods.filter((p) => p.fiscal_year_id === yearId);
                    const firstPeriodId = periodsForNewYear[0]?.id || '';
                    if (firstPeriodId) {
                      const updatedLines = form.data.lines.map((l) => ({
                        ...l,
                        financial_period_id: firstPeriodId,
                      }));
                      form.setData((prev) => ({
                        ...prev,
                        fiscal_year_id: yearId,
                        lines: updatedLines,
                      }));
                    }
                  }}
                  disabled={!!editingBudget}
                  placeholder={pageDict.selectFiscalYear}
                />
                {form.errors.fiscal_year_id ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors.fiscal_year_id}</p>
                ) : null}
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.code} <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={form.data.code}
                  onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                  placeholder={pageDict.codePlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)] font-mono"
                />
                {form.errors.code ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors.code}</p>
                ) : null}
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.versionCode} <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={form.data.version_code}
                  onChange={(e) => form.setData('version_code', e.target.value.toUpperCase())}
                  placeholder={pageDict.versionCodePlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)] font-mono"
                />
                {form.errors.version_code ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors.version_code}</p>
                ) : null}
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.nameEn} <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={form.data.name.en}
                  onChange={(e) => form.setData('name', { ...form.data.name, en: e.target.value })}
                  placeholder={pageDict.nameEnPlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
                {form.errors['name.en'] ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors['name.en']}</p>
                ) : null}
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.nameAr}
                </label>
                <input
                  type="text"
                  value={form.data.name.ar}
                  onChange={(e) => form.setData('name', { ...form.data.name, ar: e.target.value })}
                  placeholder={pageDict.nameArPlaceholder}
                  dir="rtl"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
                {form.errors['name.ar'] ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors['name.ar']}</p>
                ) : null}
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.defaultCurrency} <span className="text-red-500">*</span>
                </label>
                <SearchableSelect
                  options={currencies.map((c) => ({
                    value: c.code,
                    label: `${c.code} - ${getLocalizedName(c.name, locale)} (${c.symbol})`,
                  }))}
                  value={form.data.default_currency}
                  onChange={(val) => form.setData('default_currency', val || defaultCurrencyCode)}
                  placeholder={pageDict.selectCurrency}
                />
                {form.errors.default_currency ? (
                  <p className="text-red-500 text-xs mt-1">{form.errors.default_currency}</p>
                ) : null}
              </div>

              <div className="md:col-span-3">
                <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                  {pageDict.descriptionLabel}
                </label>
                <textarea
                  rows={2}
                  value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)}
                  placeholder={pageDict.descriptionPlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
              </div>
            </div>

            {/* Monthly Budget Line Editor */}
            <div className="border-t border-[var(--border)] pt-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <h4 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.budgetLines}</h4>
                  <p className="text-xs text-[var(--text-muted)]">
                    {pageDict.lineCount}: {form.data.lines.length}
                  </p>
                </div>
                <Button type="button" variant="secondary" className="px-2.5 py-1 text-xs" onClick={addLine} title={pageDict.addLine} aria-label={pageDict.addLine}>
                  + {pageDict.addLine}
                </Button>
              </div>

              {form.errors.lines ? (
                <p className="text-red-500 text-xs mb-2">{form.errors.lines}</p>
              ) : null}

              {duplicateLineIndices.size > 0 ? (
                <div className="p-2.5 mb-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs flex items-center gap-2">
                  <svg className="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <span>{pageDict.duplicateLineDetected}</span>
                </div>
              ) : null}

              {form.data.lines.length === 0 ? (
                <div className="p-6 text-center rounded-xl border border-dashed border-[var(--border)] text-[var(--text-muted)] text-xs">
                  {pageDict.noLines}
                </div>
              ) : (
                <div className="overflow-x-auto max-h-96 rounded-xl border border-[var(--border)]">
                  <table className="w-full text-xs text-start">
                    <thead className="bg-[var(--surface-hover)] sticky top-0 z-10 border-b border-[var(--border)]">
                      <tr>
                        <th className="p-2 text-start font-semibold">{pageDict.financialPeriod}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.account}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.project}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.costCenter}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.currency}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.amount}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.notes}</th>
                        <th className="p-2 text-center font-semibold w-12">{pageDict.actions}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border)]">
                      {form.data.lines.map((line, index) => {
                        const isDup = duplicateLineIndices.has(index);

                        return (
                          <tr key={index} className={`hover:bg-[var(--surface-hover)] transition-colors ${isDup ? 'bg-amber-500/5' : ''}`}>
                            <td className="p-2 min-w-36">
                              <SearchableSelect
                                options={activePeriodsForForm.map((p) => ({
                                  value: p.id,
                                  label: formatPeriodLabel(p, locale),
                                }))}
                                value={line.financial_period_id}
                                onChange={(val) => updateLine(index, 'financial_period_id', val || '')}
                                placeholder={pageDict.selectPeriod}
                                className="w-full"
                              />
                            </td>

                            <td className="p-2 min-w-48">
                              <SearchableSelect
                                options={accounts.map((a) => ({
                                  value: a.id,
                                  label: `${a.code} - ${getLocalizedName(a.name, locale)}`,
                                }))}
                                value={line.account_id}
                                onChange={(val) => updateLine(index, 'account_id', val || '')}
                                placeholder={pageDict.selectAccount}
                                className="w-full"
                              />
                            </td>

                            <td className="p-2 min-w-36">
                              <SearchableSelect
                                options={[
                                  { value: '', label: `(${pageDict.none})` },
                                  ...projects.map((pr) => ({
                                    value: pr.id,
                                    label: `${pr.code} - ${getLocalizedName(pr.name, locale)}`,
                                  })),
                                ]}
                                value={line.project_id || ''}
                                onChange={(val) => updateLine(index, 'project_id', val || '')}
                                placeholder={pageDict.selectProject}
                                className="w-full"
                              />
                            </td>

                            <td className="p-2 min-w-36">
                              <SearchableSelect
                                options={[
                                  { value: '', label: `(${pageDict.none})` },
                                  ...costCenters.map((cc) => ({
                                    value: cc.id,
                                    label: `${cc.code} - ${getLocalizedName(cc.name, locale)}`,
                                  })),
                                ]}
                                value={line.cost_center_id || ''}
                                onChange={(val) => updateLine(index, 'cost_center_id', val || '')}
                                placeholder={pageDict.selectCostCenter}
                                className="w-full"
                              />
                            </td>

                            <td className="p-2 min-w-24">
                              <SearchableSelect
                                options={currencies.map((c) => ({
                                  value: c.code,
                                  label: c.code,
                                }))}
                                value={line.currency}
                                onChange={(val) => updateLine(index, 'currency', val || defaultCurrencyCode)}
                                placeholder={pageDict.selectCurrency}
                                className="w-full"
                              />
                            </td>

                            <td className="p-2 min-w-28">
                              <input
                                type="number"
                                min="0"
                                step="1"
                                required
                                value={line.amount_minor}
                                onChange={(e) => updateLine(index, 'amount_minor', Math.max(0, parseInt(e.target.value, 10) || 0))}
                                className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1.5 text-xs text-[var(--text-primary)] font-mono text-end outline-hidden focus:border-[var(--primary)]"
                              />
                            </td>

                            <td className="p-2 min-w-32">
                              <input
                                type="text"
                                value={line.notes}
                                onChange={(e) => updateLine(index, 'notes', e.target.value)}
                                placeholder={pageDict.notesPlaceholder}
                                className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1.5 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                              />
                            </td>

                            <td className="p-2 text-center">
                              <button
                                type="button"
                                onClick={() => removeLine(index)}
                                title={pageDict.removeLine}
                                aria-label={pageDict.removeLine}
                                className="p-1 rounded-md text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer"
                              >
                                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                              </button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Total summary by currency */}
              {Object.keys(formSummaryByCurrency).length > 0 ? (
                <div className="mt-3 p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)] flex flex-wrap items-center justify-between gap-2">
                  <span className="text-xs font-semibold text-[var(--text-secondary)]">{pageDict.summaryByCurrency}:</span>
                  <div className="flex flex-wrap gap-3">
                    {Object.entries(formSummaryByCurrency).map(([curr, total]) => (
                      <span key={curr} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--primary)]">
                        <span>{curr}:</span>
                        <span>{formatMoney(total, curr)}</span>
                      </span>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>

            {/* Actions footer */}
            <div className="flex justify-end items-center gap-3 pt-4 border-t border-[var(--border)]">
              <Button
                type="button"
                variant="secondary"
                onClick={() => setShowModal(false)}
                title={pageDict.cancel}
                aria-label={pageDict.cancel}
              >
                {pageDict.cancel}
              </Button>
              <Button
                type="submit"
                disabled={form.processing || duplicateLineIndices.size > 0}
                title={pageDict.save}
                aria-label={pageDict.save}
              >
                {pageDict.save}
              </Button>
            </div>
          </form>
        </Modal>
      ) : null}

      {/* Detail / Lifecycle Modal */}
      {showDetailModal && viewingBudget ? (
        <Modal
          isOpen={showDetailModal}
          onClose={() => setShowDetailModal(false)}
          title={`${viewingBudget.code} - ${getLocalizedName(viewingBudget.name, locale)}`}
        >
          <div className="space-y-6">
            {/* Header info cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)]">
                <div className="text-xs text-[var(--text-muted)]">{pageDict.status}</div>
                <div className="mt-1">
                  <StatusBadge tone={getStatusTone(viewingBudget.status)}>{getStatusLabel(viewingBudget.status)}</StatusBadge>
                </div>
              </div>

              <div className="p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)]">
                <div className="text-xs text-[var(--text-muted)]">{pageDict.fiscalYear}</div>
                <div className="mt-1 text-xs font-semibold">{viewingBudget.fiscal_year?.year || '-'}</div>
              </div>

              <div className="p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)]">
                <div className="text-xs text-[var(--text-muted)]">{pageDict.versionCode}</div>
                <div className="mt-1 text-xs font-mono font-bold text-blue-500">{viewingBudget.version_code}</div>
              </div>

              <div className="p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)]">
                <div className="text-xs text-[var(--text-muted)]">{pageDict.defaultCurrency}</div>
                <div className="mt-1 text-xs font-mono font-semibold">{viewingBudget.default_currency}</div>
              </div>
            </div>

            {viewingBudget.description ? (
              <div className="p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)]">
                <div className="text-xs text-[var(--text-muted)] mb-1">{pageDict.descriptionLabel}</div>
                <p className="text-xs text-[var(--text-primary)]">{viewingBudget.description}</p>
              </div>
            ) : null}

            {/* Audit & Lifecycle Stamps */}
            <div className="p-3.5 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)] space-y-2">
              <h5 className="text-xs font-bold text-[var(--text-primary)]">{pageDict.auditInfo}</h5>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-[var(--text-secondary)]">
                <div>
                  <span className="text-[var(--text-muted)]">{pageDict.createdAt}: </span>
                  <span>{formatDate(viewingBudget.created_at)}</span>
                  {viewingBudget.creator?.name ? <span> ({viewingBudget.creator.name})</span> : null}
                </div>
                {viewingBudget.submitted_at ? (
                  <div>
                    <span className="text-[var(--text-muted)]">{pageDict.submittedAt}: </span>
                    <span>{formatDate(viewingBudget.submitted_at)}</span>
                    {viewingBudget.submitter?.name ? <span> ({viewingBudget.submitter.name})</span> : null}
                  </div>
                ) : null}
                {viewingBudget.approved_at ? (
                  <div>
                    <span className="text-[var(--text-muted)]">{pageDict.approvedAt}: </span>
                    <span>{formatDate(viewingBudget.approved_at)}</span>
                    {viewingBudget.approver?.name ? <span> ({viewingBudget.approver.name})</span> : null}
                  </div>
                ) : null}
                {viewingBudget.activated_at ? (
                  <div>
                    <span className="text-[var(--text-muted)]">{pageDict.activatedAt}: </span>
                    <span>{formatDate(viewingBudget.activated_at)}</span>
                    {viewingBudget.activator?.name ? <span> ({viewingBudget.activator.name})</span> : null}
                  </div>
                ) : null}
                {viewingBudget.archived_at ? (
                  <div>
                    <span className="text-[var(--text-muted)]">{pageDict.archivedAt}: </span>
                    <span>{formatDate(viewingBudget.archived_at)}</span>
                    {viewingBudget.archiver?.name ? <span> ({viewingBudget.archiver.name})</span> : null}
                  </div>
                ) : null}
                {viewingBudget.cancelled_at ? (
                  <div>
                    <span className="text-[var(--text-muted)]">{pageDict.cancelledAt}: </span>
                    <span>{formatDate(viewingBudget.cancelled_at)}</span>
                    {viewingBudget.canceller?.name ? <span> ({viewingBudget.canceller.name})</span> : null}
                  </div>
                ) : null}
              </div>
            </div>

            {/* Lines Breakdown */}
            <div>
              <h5 className="text-xs font-bold text-[var(--text-primary)] mb-2">
                {pageDict.budgetLines} ({viewingBudget.lines ? viewingBudget.lines.length : 0})
              </h5>

              {!viewingBudget.lines || viewingBudget.lines.length === 0 ? (
                <div className="p-4 text-center rounded-xl border border-[var(--border)] text-[var(--text-muted)] text-xs">
                  {pageDict.noLines}
                </div>
              ) : (
                <div className="overflow-x-auto max-h-72 rounded-xl border border-[var(--border)]">
                  <table className="w-full text-xs text-start">
                    <thead className="bg-[var(--surface-hover)] sticky top-0 border-b border-[var(--border)]">
                      <tr>
                        <th className="p-2 text-start font-semibold">{pageDict.financialPeriod}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.account}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.project}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.costCenter}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.currency}</th>
                        <th className="p-2 text-end font-semibold">{pageDict.amount}</th>
                        <th className="p-2 text-start font-semibold">{pageDict.notes}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border)]">
                      {viewingBudget.lines.map((line) => (
                        <tr key={line.id} className="hover:bg-[var(--surface-hover)] transition-colors">
                          <td className="p-2">
                            {line.financial_period ? formatPeriodLabel(line.financial_period, locale) : '-'}
                          </td>
                          <td className="p-2 font-medium">
                            {line.account ? `${line.account.code} - ${getLocalizedName(line.account.name, locale)}` : '-'}
                          </td>
                          <td className="p-2">
                            {line.project ? `${line.project.code} - ${getLocalizedName(line.project.name, locale)}` : <span className="text-[var(--text-muted)]">-</span>}
                          </td>
                          <td className="p-2">
                            {line.cost_center ? `${line.cost_center.code} - ${getLocalizedName(line.cost_center.name, locale)}` : <span className="text-[var(--text-muted)]">-</span>}
                          </td>
                          <td className="p-2 font-mono">{line.currency}</td>
                          <td className="p-2 text-end font-mono font-bold">
                            {formatMoney(line.amount_minor, line.currency)}
                          </td>
                          <td className="p-2 text-[var(--text-muted)]">{line.notes || '-'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Total summary by currency in detail modal */}
              {viewingBudget.lines && viewingBudget.lines.length > 0 ? (
                <div className="mt-3 p-3 rounded-xl bg-[var(--surface-hover)] border border-[var(--border)] flex flex-wrap items-center justify-between gap-2">
                  <span className="text-xs font-semibold text-[var(--text-secondary)]">{pageDict.summaryByCurrency}:</span>
                  <div className="flex flex-wrap gap-3">
                    {Object.entries(computeRowTotals(viewingBudget.lines)).map(([curr, total]) => (
                      <span key={curr} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--primary)]">
                        <span>{curr}:</span>
                        <span>{formatMoney(total, curr)}</span>
                      </span>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>

            {/* Modal actions */}
            <div className="flex justify-end items-center gap-2 pt-4 border-t border-[var(--border)]">
              <Button
                type="button"
                variant="secondary"
                onClick={() => setShowDetailModal(false)}
                title={pageDict.close}
                aria-label={pageDict.close}
              >
                {pageDict.close}
              </Button>
            </div>
          </div>
        </Modal>
      ) : null}
    </AppLayout>
  );
}
