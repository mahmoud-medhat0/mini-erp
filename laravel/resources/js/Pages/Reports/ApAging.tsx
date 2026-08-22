import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

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
  const dict = getDictionary(locale);

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
      <Head title={dict.app.pages.reportsApAging.apAgingReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsApAging.apAgingReport}
        description={dict.app.pages.reportsApAging.analysisOfOutstandingSupplierPayablesGrouped}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {dict.app.pages.reportsApAging.exportCsv}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApAging.asOfDate}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApAging.supplier}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsApAging.allSuppliers },
                  ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` })),
                ]}
                value={supplierId}
                onChange={(val) => setSupplierId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApAging.currency}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || 'EGP')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsApAging.viewReport}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApAging.current}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.grand_totals.current, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">1 - 30 {dict.app.pages.reportsApAging.days}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(report.grand_totals.b1_30, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">31 - 60 {dict.app.pages.reportsApAging.days_2}</div>
            <div className="text-sm font-bold text-amber-600">
              {formatMoney(report.grand_totals.b31_60, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">61 - 90 {dict.app.pages.reportsApAging.days_3}</div>
            <div className="text-sm font-bold text-orange-600">
              {formatMoney(report.grand_totals.b61_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">+90 {dict.app.pages.reportsApAging.days_4}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.grand_totals.over_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApAging.totalOpen}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.grand_totals.total, report.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.supplier_2}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.reference}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.entryDate}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.agingBasis}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.current_2}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">1-30</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">31-60</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">61-90</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">+90</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsApAging.openBalance}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.suppliers.map((sGroup, idx) => (
                <tr key={idx} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-bold">
                    {sGroup.supplier.code} - {sGroup.supplier.name}
                  </td>
                  <td colSpan={3} className="p-3 text-[var(--text-secondary)]">
                    {sGroup.items.length} {dict.app.pages.reportsApAging.openItems}
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
                    {dict.app.pages.reportsApAging.noOpenSupplierPayablesFound}
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
