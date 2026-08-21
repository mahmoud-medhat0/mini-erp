import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';

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
  const isAr = locale === 'ar';

  const [direction, setDirection] = useState(filters.direction || 'all');
  const [status, setStatus] = useState(filters.status || '');
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');
  const [currency, setCurrency] = useState(filters.currency);

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
    });
  };

  const handleExport = () => {
    const url = `/reports/cheque-register/export?direction=${direction}&status=${status}&customer_id=${customerId}&supplier_id=${supplierId}&bank_account_id=${bankAccountId}&date_from=${dateFrom}&date_to=${dateTo}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.cheque-register">
      <Head title={isAr ? 'سجل ومتابعة الشيكات - Mini ERP' : 'Cheque Register Report - Mini ERP'} />

      <PageHeader
        title={isAr ? 'سجل ومتابعة الشيكات' : 'Cheque Register Report'}
        description={isAr ? 'تقرير تجميعي وقراءات الشيكات الواردة والصادرة وحالات الاستحقاق والصرف.' : 'Read-only tracking register for incoming and outgoing cheques across all lifecycle states.'}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {isAr ? 'تصدير CSV' : 'Export CSV'}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'اتجاه الشيك' : 'Cheque Direction'}
              </label>
              <SearchableSelect
                options={[
                  { value: 'all', label: isAr ? 'الكل (وارد وصادر)' : 'All (Incoming & Outgoing)' },
                  { value: 'incoming', label: isAr ? 'شيكات واردة فقط' : 'Incoming Only' },
                  { value: 'outgoing', label: isAr ? 'شيكات صادرة فقط' : 'Outgoing Only' },
                ]}
                value={direction}
                onChange={(val) => setDirection(val || 'all')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'العميل' : 'Customer'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع العملاء' : 'All Customers' },
                  ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` })),
                ]}
                value={customerId}
                onChange={(val) => setCustomerId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'المورد' : 'Supplier'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع الموردين' : 'All Suppliers' },
                  ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` })),
                ]}
                value={supplierId}
                onChange={(val) => setSupplierId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'الحساب البنكي' : 'Bank Account'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع الحسابات' : 'All Bank Accounts' },
                  ...bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` })),
                ]}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'حالة الشيك' : 'Status'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع الحالات' : 'All Statuses' },
                  { value: 'received', label: 'Received' },
                  { value: 'deposited', label: 'Deposited' },
                  { value: 'issued', label: 'Issued' },
                  { value: 'cleared', label: 'Cleared' },
                  { value: 'bounced', label: 'Bounced' },
                  { value: 'returned', label: 'Returned' },
                  { value: 'cancelled', label: 'Cancelled' },
                ]}
                value={status}
                onChange={(val) => setStatus(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'حساب البنك' : 'Bank Account'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع البنوك' : 'All Banks' },
                  ...bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` })),
                ]}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {isAr ? 'تطبيق الفلتر' : 'Apply Filters'}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'عدد الشيكات' : 'Cheque Count'}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">{report.total_count}</div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي الوارد' : 'Total Incoming'}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.incoming_total_minor, report.filters.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي الصادر' : 'Total Outgoing'}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.outgoing_total_minor, report.filters.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي قيمة الشيكات' : 'Grand Total'}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.total_amount_minor, report.filters.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'الاطراف/الجهة' : 'Direction / Party'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'رقم الشيك' : 'Cheque No.'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'تاريخ الاستحقاق' : 'Due Date'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'حساب البنك' : 'Bank Account'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'الحالة' : 'Status'}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'المبلغ' : 'Amount'}</th>
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
                      {item.status.toUpperCase()}
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
                    {isAr ? 'لا توجد شيكات تطابق الفلتر المحدد.' : 'No cheques found matching the specified filters.'}
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
