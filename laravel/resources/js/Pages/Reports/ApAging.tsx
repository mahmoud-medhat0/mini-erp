import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';

type ApAgingProps = SharedPageProps & {
  report: {
    as_of_date: string;
    currency: string;
    suppliers: Array<{
      supplier: { id: string; code: string; name: string };
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
  suppliers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; supplier_id: string | null; currency: string };
};

export default function ApAging({ locale, report, suppliers, currencies, filters }: ApAgingProps) {
  const isAr = locale === 'ar';

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/ap-aging', {
      as_of_date: asOfDate,
      supplier_id: supplierId,
      currency,
    });
  };

  const handleExport = () => {
    const url = `/reports/ap-aging/export?as_of_date=${asOfDate}&supplier_id=${supplierId}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ap-aging">
      <Head title={isAr ? 'أعمار ديون الموردين - Mini ERP' : 'AP Aging Report - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تقرير أعمار ديون الموردين' : 'AP Aging Report'}
        description={isAr ? 'تحليل المستحقات المفتوحة للموردين موزعة على فترات الاستحقاق (حتى تاريخ التقرير).' : 'Analysis of outstanding supplier payables grouped by aging buckets.'}
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
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'المورد' : 'Supplier'}</th>
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
              {report.suppliers.map((sGroup, idx) => (
                <tr key={idx} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-bold">
                    {sGroup.supplier.code} - {sGroup.supplier.name}
                  </td>
                  <td colSpan={3} className="p-3 text-[var(--text-secondary)]">
                    {sGroup.items.length} {isAr ? 'حركة مفتوحة' : 'open items'}
                  </td>
                  <td className="p-3 text-end font-mono">{formatMoney(sGroup.totals.current, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(sGroup.totals.b1_30, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(sGroup.totals.b31_60, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(sGroup.totals.b61_90, report.currency)}</td>
                  <td className="p-3 text-end font-mono">{formatMoney(sGroup.totals.over_90, report.currency)}</td>
                  <td className="p-3 text-end font-mono font-bold">{formatMoney(sGroup.totals.total, report.currency)}</td>
                </tr>
              ))}
              {report.suppliers.length === 0 ? (
                <tr>
                  <td colSpan={10} className="p-8 text-center text-[var(--text-muted)]">
                    {isAr ? 'لا توجد مستحقات مفتوحة للموردين.' : 'No open supplier payables found.'}
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
