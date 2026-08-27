import { Head, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type OptionRow = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active?: boolean;
};

type RentalReportRow = {
  contract_id: string;
  contract_number: string;
  customer_code: string;
  customer_name: TranslatedName;
  branch_id: string | null;
  branch_code: string;
  branch_name: TranslatedName;
  status: string;
  due_state: string;
  start_date: string | null;
  expected_end_date: string | null;
  actual_end_date: string | null;
  currency: string;
  billing_cycle: string;
  line_count: number;
  confirmed_handover_count: number;
  returned_line_count: number;
  open_item_count: number;
  invoice_count: number;
  posted_invoice_count: number;
  open_invoice_count: number;
  estimated_rent_minor: number;
  deposit_minor: number;
  rent_billed_minor: number;
  deposit_billed_minor: number;
  charge_billed_minor: number;
  tax_billed_minor: number;
  total_billed_minor: number;
  open_invoice_total_minor: number;
  unbilled_line_count: number;
  pending_damage_minor: number;
  has_unposted_invoice: boolean;
  latest_journal_number: string;
};

type Props = SharedPageProps & {
  reportData: {
    as_of_date: string;
    ending_soon_date: string;
    base_currency: string;
    currency_codes: string[];
    single_currency: boolean;
    display_currency: string;
    rows: RentalReportRow[];
    summary: {
      contract_count: number;
      active_contract_count: number;
      overdue_contract_count: number;
      ending_soon_contract_count: number;
      open_item_count: number;
      unbilled_line_count: number;
      open_invoice_count: number;
      posted_invoice_count: number;
      rent_billed_minor: number;
      deposit_billed_minor: number;
      charge_billed_minor: number;
      tax_billed_minor: number;
      total_billed_minor: number;
      open_invoice_total_minor: number;
      pending_damage_minor: number;
    };
    readiness: {
      has_mixed_currency: boolean;
      has_overdue_contracts: boolean;
      has_unbilled_lines: boolean;
      has_pending_damage: boolean;
      has_unposted_invoices: boolean;
    };
  };
  filters: {
    as_of_date: string;
    date_from: string;
    date_to: string;
    branch_id: string;
    customer_id: string;
    status: string;
    currency: string;
    search: string;
  };
  branches: OptionRow[];
  customers: OptionRow[];
  currencies: CurrencyOption[];
  statuses: string[];
};

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active' || value === 'posted' || value === 'completed') return 'ok';
  if (value === 'cancelled' || value === 'overdue') return 'danger';
  if (value === 'approved' || value === 'submitted' || value === 'ending_soon') return 'warning';
  return 'muted';
}

export default function RentalOperationsReport({ locale, reportData, filters, branches, customers, currencies, statuses }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.rentalOperationsReport;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');
  const activeLocale = locale === 'ar' ? 'ar' : 'en';

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date || reportData.as_of_date);
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [status, setStatus] = useState(filters.status || '');
  const [currency, setCurrency] = useState(filters.currency || '');
  const [search, setSearch] = useState(filters.search || '');

  const branchOptions = useMemo(() => branches.map((branch) => ({
    value: branch.id,
    label: `${branch.code} - ${getLocalizedName(branch.name, activeLocale)}`,
    sublabel: branch.is_active ? pageDict.active : pageDict.inactive,
  })), [branches, activeLocale, pageDict.active, pageDict.inactive]);

  const customerOptions = useMemo(() => customers.map((customer) => ({
    value: customer.id,
    label: `${customer.code} - ${getLocalizedName(customer.name, activeLocale)}`,
  })), [customers, activeLocale]);

  const statusOptions = statuses.map((item) => ({
    value: item,
    label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item,
  }));

  const currencyOptions = currencies.map((item) => ({
    value: item.code,
    label: `${item.code} - ${getLocalizedName(item.name, activeLocale)}`,
    sublabel: item.symbol,
  }));

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    router.get('/reports/rentals', {
      as_of_date: asOfDate,
      date_from: dateFrom,
      date_to: dateTo,
      branch_id: branchId,
      customer_id: customerId,
      status,
      currency,
      search,
    }, { preserveState: true, replace: true });
  }

  function exportHref(): string {
    const params = new URLSearchParams();
    if (asOfDate) params.set('as_of_date', asOfDate);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    if (branchId) params.set('branch_id', branchId);
    if (customerId) params.set('customer_id', customerId);
    if (status) params.set('status', status);
    if (currency) params.set('currency', currency);
    if (search) params.set('search', search);

    const query = params.toString();
    return `/reports/rentals/export${query ? `?${query}` : ''}`;
  }

  function amountValue(amountMinor: number, tone?: 'debit' | 'credit' | 'net' | 'muted' | 'danger' | 'success'): ReactNode {
    if (!reportData.single_currency) {
      return pageDict.mixedCurrencyAmount;
    }

    return <AccountingAmount amountMinor={amountMinor} currency={reportData.display_currency} tone={tone} />;
  }

  const readinessItems = [
    { active: reportData.readiness.has_overdue_contracts, text: pageDict.readiness.overdueContracts },
    { active: reportData.readiness.has_unbilled_lines, text: pageDict.readiness.unbilledLines },
    { active: reportData.readiness.has_pending_damage, text: pageDict.readiness.pendingDamage },
    { active: reportData.readiness.has_unposted_invoices, text: pageDict.readiness.unpostedInvoices },
    { active: reportData.readiness.has_mixed_currency, text: pageDict.readiness.mixedCurrency },
  ];

  return (
    <AppLayout active="reports.rentals">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canExport || canPrint ? (
            <>
              {canExport ? (
                <a href={exportHref()} className="inline-flex items-center justify-center rounded-xl border border-transparent bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-2xs transition-all hover:opacity-90">
                  {actionsDict.exportCsv}
                </a>
              ) : null}
              {canPrint ? (
                <Button type="button" variant="secondary" onClick={() => window.print()}>
                  {actionsDict.printReport}
                </Button>
              ) : null}
            </>
          ) : null
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <form onSubmit={applyFilters} className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-8 xl:items-end">
            <DatePicker label={pageDict.asOfDate} value={asOfDate} onChange={(value) => setAsOfDate(value || '')} />
            <DatePicker label={pageDict.dateFrom} value={dateFrom} onChange={(value) => setDateFrom(value || '')} />
            <DatePicker label={pageDict.dateTo} value={dateTo} onChange={(value) => setDateTo(value || '')} />
            <SearchableSelect label={pageDict.branch} options={branchOptions} value={branchId} onChange={(value) => setBranchId(value || '')} placeholder={pageDict.allBranches} />
            <SearchableSelect label={pageDict.customer} options={customerOptions} value={customerId} onChange={(value) => setCustomerId(value || '')} placeholder={pageDict.allCustomers} />
            <SearchableSelect label={pageDict.status} options={statusOptions} value={status} onChange={(value) => setStatus(value || '')} placeholder={pageDict.allStatuses} />
            <SearchableSelect label={pageDict.currency} options={currencyOptions} value={currency} onChange={(value) => setCurrency(value || '')} placeholder={pageDict.allCurrencies} />
            <div className="space-y-1">
              <label className="block text-xs font-bold text-[var(--text-secondary)]">{pageDict.search}</label>
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={pageDict.searchPlaceholder}
                className="h-[42px] w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm text-[var(--text-primary)] outline-none transition focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20"
              />
            </div>
            <div className="md:col-span-2 xl:col-span-8 flex flex-wrap justify-end gap-2">
              <Button type="submit">{pageDict.filter}</Button>
              <Button type="button" variant="secondary" onClick={() => {
                setAsOfDate(reportData.as_of_date);
                setDateFrom('');
                setDateTo('');
                setBranchId('');
                setCustomerId('');
                setStatus('');
                setCurrency('');
                setSearch('');
                router.get('/reports/rentals', {}, { preserveState: true, replace: true });
              }}>
                {pageDict.clear}
              </Button>
            </div>
          </form>
        </Card>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <MetricCard label={pageDict.activeContracts} value={reportData.summary.active_contract_count.toLocaleString()} tone="blue" />
          <MetricCard label={pageDict.overdueContracts} value={reportData.summary.overdue_contract_count.toLocaleString()} tone={reportData.summary.overdue_contract_count > 0 ? 'danger' : 'emerald'} />
          <MetricCard label={pageDict.unbilledLines} value={reportData.summary.unbilled_line_count.toLocaleString()} tone={reportData.summary.unbilled_line_count > 0 ? 'amber' : 'emerald'} />
          <MetricCard label={pageDict.totalBilled} value={amountValue(reportData.summary.total_billed_minor, 'success')} tone="emerald" />
          <MetricCard label={pageDict.openPipeline} value={amountValue(reportData.summary.open_invoice_total_minor, 'muted')} tone="purple" />
        </div>

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
          <Card className="p-4">
            <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.readinessTitle}</h2>
            <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.readinessDescription}</p>
            <div className="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
              {readinessItems.map((item) => (
                <div key={item.text} className="flex items-center justify-between gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                  <span className="text-xs font-semibold text-[var(--text-secondary)]">{item.text}</span>
                  <StatusBadge tone={item.active ? 'warning' : 'ok'}>
                    {item.active ? pageDict.reviewNeeded : pageDict.clearStatus}
                  </StatusBadge>
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4">
            <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.amountsTitle}</h2>
            <div className="mt-4 grid grid-cols-2 gap-3">
              {[
                { label: pageDict.rentBilled, value: amountValue(reportData.summary.rent_billed_minor, 'credit') },
                { label: pageDict.depositsBilled, value: amountValue(reportData.summary.deposit_billed_minor, 'credit') },
                { label: pageDict.chargesBilled, value: amountValue(reportData.summary.charge_billed_minor, 'credit') },
                { label: pageDict.taxBilled, value: amountValue(reportData.summary.tax_billed_minor, 'credit') },
              ].map((item) => (
                <div key={item.label} className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                  <div className="text-xs font-bold uppercase text-[var(--text-secondary)]">{item.label}</div>
                  <div className="mt-2 text-sm font-extrabold text-[var(--text-primary)]">{item.value}</div>
                </div>
              ))}
            </div>
            {!reportData.single_currency ? (
              <p className="mt-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-5 text-amber-700 dark:text-amber-300">
                {pageDict.mixedCurrencyWarning}
              </p>
            ) : null}
          </Card>
        </div>

        {reportData.rows.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.contract}</th>
                  <th className={tableClasses.th}>{pageDict.customer}</th>
                  <th className={tableClasses.th}>{pageDict.branch}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={tableClasses.th}>{pageDict.dates}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.items}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.invoices}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.totalBilled}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.pendingDamage}</th>
                  <th className={tableClasses.th}>{pageDict.latestJournal}</th>
                </tr>
              </thead>
              <tbody>
                {reportData.rows.map((row) => (
                  <tr key={row.contract_id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-44 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">{row.contract_number || pageDict.notNumbered}</span>
                        <span className="text-xs text-[var(--text-secondary)]">{pageDict.billingCycles[row.billing_cycle as keyof typeof pageDict.billingCycles] || row.billing_cycle}</span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex min-w-48 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">{row.customer_code}</span>
                        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(row.customer_name, activeLocale)}</span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex min-w-40 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">{row.branch_code || pageDict.noBranch}</span>
                        <span className="text-xs text-[var(--text-secondary)]">{row.branch_id ? getLocalizedName(row.branch_name, activeLocale) : pageDict.operationalReference}</span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-col gap-2">
                        <StatusBadge tone={statusTone(row.status)}>
                          {pageDict.statuses[row.status as keyof typeof pageDict.statuses] || row.status}
                        </StatusBadge>
                        <StatusBadge tone={statusTone(row.due_state)}>
                          {pageDict.dueStates[row.due_state as keyof typeof pageDict.dueStates] || row.due_state}
                        </StatusBadge>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex min-w-40 flex-col gap-1 text-xs">
                        <span>{pageDict.from}: {formatDate(row.start_date) || pageDict.notAvailable}</span>
                        <span>{pageDict.to}: {formatDate(row.expected_end_date) || pageDict.notAvailable}</span>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="space-y-1 font-mono text-xs">
                        <div>{row.open_item_count.toLocaleString()} / {row.line_count.toLocaleString()}</div>
                        <div className="text-[var(--text-muted)]">{pageDict.unbilled}: {row.unbilled_line_count.toLocaleString()}</div>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="space-y-1 font-mono text-xs">
                        <div>{pageDict.posted}: {row.posted_invoice_count.toLocaleString()}</div>
                        <div className="text-[var(--text-muted)]">{pageDict.open}: {row.open_invoice_count.toLocaleString()}</div>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <AccountingAmount amountMinor={row.total_billed_minor} currency={row.currency} tone="success" />
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <AccountingAmount amountMinor={row.pending_damage_minor} currency={row.currency} tone={row.pending_damage_minor > 0 ? 'danger' : 'muted'} />
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">{row.latest_journal_number || pageDict.notAvailable}</span>
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
