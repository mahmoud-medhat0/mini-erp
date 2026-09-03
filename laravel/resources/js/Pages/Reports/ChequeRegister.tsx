import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import ChequeRegisterDataTable from '../../Components/ChequeRegisterDataTable';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type ChequeRegisterProps = SharedPageProps & {
  report: {
    direction: string;
    filters: {
      status: string | null;
      customer_id: string | null;
      supplier_id: string | null;
      bank_account_id: string | null;
      date_from: string | null;
      date_to: string | null;
      currency: string;
    };
    total_amount_minor: number;
    incoming_total_minor: number;
    outgoing_total_minor: number;
    total_count: number;
  };
  customers: Array<{ id: string; code: string; name: string }>;
  suppliers: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: {
    direction: string;
    status: string | null;
    customer_id: string | null;
    supplier_id: string | null;
    bank_account_id: string | null;
    date_from: string | null;
    date_to: string | null;
    currency: string;
  };
};

export default function ChequeRegister({ locale, report, customers, suppliers, bankAccounts, currencies, filters }: ChequeRegisterProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [direction, setDirection] = useState(filters.direction || 'all');
  const [status, setStatus] = useState(filters.status || '');
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');
  const [currency, setCurrency] = useState(filters.currency);

  const hasActiveFilters = Boolean(
    (direction && direction !== 'all') ||
    status ||
    customerId ||
    supplierId ||
    bankAccountId ||
    dateFrom ||
    dateTo
  );

  const handleFilter = () => {
    router.get('/reports/cheque-register', {
      direction,
      status: status || undefined,
      customer_id: customerId || undefined,
      supplier_id: supplierId || undefined,
      bank_account_id: bankAccountId || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setDirection('all');
    setStatus('');
    setCustomerId('');
    setSupplierId('');
    setBankAccountId('');
    setDateFrom('');
    setDateTo('');
    router.get('/reports/cheque-register', { currency }, { preserveScroll: true });
  };

  const handleExport = () => {
    const url = `/reports/cheque-register/export?direction=${direction}&status=${status}&customer_id=${customerId}&supplier_id=${supplierId}&bank_account_id=${bankAccountId}&date_from=${dateFrom}&date_to=${dateTo}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.cheque-register">
      <Head title={dict.app.pages.reportsChequeRegister.chequeRegisterReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsChequeRegister.chequeRegisterReport}
        description={dict.app.pages.reportsChequeRegister.readOnlyTrackingRegisterForIncoming}
        actions={
          <div className="flex items-center gap-2">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {actionsDict.printReport}
              </Button>
            ) : null}
            {canExport ? (
              <Button variant="secondary" onClick={handleExport}>
                {dict.app.pages.reportsChequeRegister.exportCsv}
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsChequeRegister.chequeDirection}
              </label>
              <SearchableSelect
                options={[
                  { value: 'all', label: dict.app.pages.reportsChequeRegister.allIncomingOutgoing },
                  { value: 'incoming', label: dict.app.pages.reportsChequeRegister.incomingOnly },
                  { value: 'outgoing', label: dict.app.pages.reportsChequeRegister.outgoingOnly },
                ]}
                value={direction}
                onChange={(val) => setDirection(val || 'all')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsChequeRegister.customer}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsChequeRegister.allCustomers },
                  ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${getLocalizedName(c.name, locale)}` })),
                ]}
                value={customerId}
                onChange={(val) => setCustomerId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsChequeRegister.supplier}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsChequeRegister.allSuppliers },
                  ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${getLocalizedName(s.name, locale)}` })),
                ]}
                value={supplierId}
                onChange={(val) => setSupplierId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsChequeRegister.bankAccount}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsChequeRegister.allBankAccounts },
                  ...bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${getLocalizedName(b.name, locale)}` })),
                ]}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsChequeRegister.status}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsChequeRegister.allStatuses },
                  { value: 'draft', label: accDict.statusDraft },
                  { value: 'received', label: accDict.statusReceived },
                  { value: 'deposited', label: accDict.statusDeposited },
                  { value: 'issued', label: accDict.statusIssued },
                  { value: 'cleared', label: accDict.statusCleared },
                  { value: 'bounced', label: accDict.statusBounced },
                  { value: 'returned', label: accDict.statusReturned },
                  { value: 'cancelled', label: accDict.statusCancelled },
                ]}
                value={status}
                onChange={(val) => setStatus(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {accDict.entryDate} ({dict.app.pages.reportsCustomerStatement.fromDate})
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {accDict.entryDate} ({dict.app.pages.reportsCustomerStatement.toDate})
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsChequeRegister.applyFilters}
              </Button>
              <Button
                variant="secondary"
                onClick={handleReset}
                disabled={!hasActiveFilters}
                title={actionsDict.reset}
                aria-label={actionsDict.reset}
              >
                {actionsDict.reset}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsChequeRegister.chequeCount}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">{report.total_count}</div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsChequeRegister.totalIncoming}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.incoming_total_minor, report.filters.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsChequeRegister.totalOutgoing}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.outgoing_total_minor, report.filters.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsChequeRegister.grandTotal}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.total_amount_minor, report.filters.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <ChequeRegisterDataTable
            key={`${filters.direction}-${filters.status || 'all'}-${filters.customer_id || 'all'}-${filters.supplier_id || 'all'}-${filters.bank_account_id || 'all'}-${filters.date_from || 'start'}-${filters.date_to || 'end'}-${filters.currency}`}
            filters={filters}
            labels={{
              directionParty: dict.app.pages.reportsChequeRegister.directionParty,
              chequeNumber: dict.app.pages.reportsChequeRegister.chequeNo,
              dueDate: dict.app.pages.reportsChequeRegister.dueDate,
              bankAccount: dict.app.pages.reportsChequeRegister.bankAccount_3,
              status: dict.app.pages.reportsChequeRegister.status_2,
              amount: dict.app.pages.reportsChequeRegister.amount,
              statuses: {
                draft: accDict.statusDraft,
                received: accDict.statusReceived,
                deposited: accDict.statusDeposited,
                issued: accDict.statusIssued,
                cleared: accDict.statusCleared,
                bounced: accDict.statusBounced,
                returned: accDict.statusReturned,
                cancelled: accDict.statusCancelled,
              },
            }}
            locale={locale}
          />
        </Card>
      </div>
    </AppLayout>
  );
}
