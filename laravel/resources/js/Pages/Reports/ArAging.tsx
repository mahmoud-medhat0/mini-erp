import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';

type ArAgingProps = SharedPageProps & {
  report: {
    as_of_date: string;
    currency: string;
    customers: Array<{
      customer: { id: string; code: string; name: string };
      items: Array<{
        id: string;
        reference: string;
        entry_date: string;
        due_date: string | null;
        basis_used: string;
        age_days: number;
        original_amount_minor: number;
        allocated_minor: number;
        unapplied_minor: number;
        bucket: string;
      }>;
      totals: {
        current: number;
        b1_30: number;
        b31_60: number;
        b61_90: number;
        over_90: number;
        total: number;
      };
    }>;
    grand_totals: {
      current: number;
      b1_30: number;
      b31_60: number;
      b61_90: number;
      over_90: number;
      total: number;
    };
  };
  customers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; customer_id: string | null; currency: string };
};

export default function ArAging({ locale, report, customers, currencies, filters }: ArAgingProps) {
  const isAr = locale === 'ar';

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/ar-aging', {
      as_of_date: asOfDate,
      customer_id: customerId,
      currency,
    });
  };

  const handleExport = () => {
    const url = `/reports/ar-aging/export?as_of_date=${asOfDate}&customer_id=${customerId}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ar-aging">
      <Head title={isAr ? 'أعمار ديون العملاء - Mini ERP' : 'AR Aging Report - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تقرير أعمار ديون العملاء' : 'AR Aging Report'}
        description={isAr ? 'تحليل المستحقات المفتوحة للعملاء موزعة على فترات الاستحقاق (حتى تاريخ التقرير).' : 'Analysis of outstanding customer receivables grouped by aging buckets.'}
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
                {isAr ? 'حتى تاريخ (تاريخ الاستحقاق)' : 'As of Date'}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
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
                {isAr ? 'العملة' : 'Currency'}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || 'EGP')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {isAr ? 'عرض التقرير' : 'View Report'}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'حالي (غير مستحق)' : 'Current'}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.grand_totals.current, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">1 - 30 {isAr ? 'يوم' : 'Days'}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(report.grand_totals.b1_30, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">31 - 60 {isAr ? 'يوم' : 'Days'}</div>
            <div className="text-sm font-bold text-amber-600">
              {formatMoney(report.grand_totals.b31_60, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">61 - 90 {isAr ? 'يوم' : 'Days'}</div>
            <div className="text-sm font-bold text-orange-600">
              {formatMoney(report.grand_totals.b61_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">+90 {isAr ? 'يوم' : 'Days'}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.grand_totals.over_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي المتبقي' : 'Total Open'}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.grand_totals.total, report.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'العميل' : 'Customer'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'المرجع' : 'Reference'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'تاريخ الحركة' : 'Entry Date'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'أساس العمر' : 'Aging Basis'}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'حالي' : 'Current'}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">1-30</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">31-60</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">61-90</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">+90</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'الرصيد المتبقي' : 'Open Balance'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.customers.map((cGroup, idx) => (
                <tr key={idx} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-bold">
                    {cGroup.customer.code} - {cGroup.customer.name}
                  </td>
                  <td colSpan={3} className="p-3 text-[var(--text-secondary)]">
                    {cGroup.items.length} {isAr ? 'حركة مفتوحة' : 'open items'}
                  </td>
                  <td className="p-3 text-end font-mono">{formatMoney(cGroup.totals.current, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(cGroup.totals.b1_30, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(cGroup.totals.b31_60, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(cGroup.totals.b61_90, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(cGroup.totals.over_90, report.currency)}</td>
                  <td className="p-3 text-end font-mono font-bold">{formatMoney(cGroup.totals.total, report.currency)}</td>
                </tr>
              ))}
              {report.customers.length === 0 ? (
                <tr>
                  <td colSpan={10} className="p-8 text-center text-[var(--text-muted)]">
                    {isAr ? 'لا توجد مستحقات مفتوحة للعملاء.' : 'No open customer receivables found.'}
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
