import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
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
    items: Array<{
      id: string;
      direction: 'incoming' | 'outgoing';
      cheque_number: string;
      party_name: string;
      party_code: string;
      bank_account_name: string;
      due_date: string;
      currency: string;
      amount_minor: number;
      status: string;
      notes: string | null;
    }>;
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

  const getChequeStatusLabel = (st: string) => {
    const s = st.toLowerCase();
    const map: Record<string, string> = {
      received: accDict.statusReceived,
      deposited: accDict.statusDeposited,
      issued: accDict.statusIssued,
      cleared: accDict.statusCleared,
      bounced: accDict.statusBounced,
      returned: accDict.statusReturned,
      cancelled: accDict.statusCancelled,
    };
    return map[s] || st.toUpperCase();
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
                  ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` })),
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
                  ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` })),
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
                  ...bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` })),
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
          <table className="w-full text-start text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.directionParty}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.chequeNo}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.dueDate}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.bankAccount_3}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.status_2}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsChequeRegister.amount}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.items.map((item) => (
                <tr key={`${item.direction}-${item.id}`} className="hover:bg-[var(--background)]/30">
                  <td className="p-3">
                    <span className={`inline-block px-1.5 py-0.5 text-[10px] font-bold rounded me-2 ${item.direction === 'incoming' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}`}>
                      {item.direction.toUpperCase()}
                    </span>
                    <span className="font-semibold">{item.party_code} - {item.party_name}</span>
                  </td>
                  <td className="p-3 font-mono font-bold">{item.cheque_number}</td>
                  <td className="p-3 font-mono">{item.due_date}</td>
                  <td className="p-3 text-[var(--text-secondary)]">{item.bank_account_name}</td>
                  <td className="p-3">
                    <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-300">
                      {getChequeStatusLabel(item.status)}
                    </span>
                  </td>
                  <td className="p-3 text-end font-mono font-bold">
                    {formatMoney(item.amount_minor, item.currency)}
                  </td>
                </tr>
              ))}
              {report.items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="p-8 text-center text-[var(--text-muted)]">
                    {dict.app.pages.reportsChequeRegister.noChequesFoundMatchingTheSpecified}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </Card>
      </div>
    </AppLayout>
  );
}
