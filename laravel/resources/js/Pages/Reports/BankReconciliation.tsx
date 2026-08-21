import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';

type BankReconciliationReportProps = SharedPageProps & {
  report: {
    filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
    reconciliations: Array<{
      id: string;
      bank_account: { id: string; code: string; name: string; currency: string };
      statement_reference: string;
      date_from: string;
      date_to: string;
      statement_opening_balance_minor: number;
      statement_closing_balance_minor: number;
      status: string;
      finalized_at: string | null;
      summary: {
        statement_movement_minor: number;
        system_movement_minor: number;
        matched_movement_minor: number;
        difference_minor: number;
        unmatched_statement_lines_count: number;
        matched_statement_lines_count: number;
        total_statement_lines_count: number;
      };
    }>;
  };
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
};

export default function BankReconciliationReport({ locale, report, bankAccounts, filters }: BankReconciliationReportProps) {
  const isAr = locale === 'ar';

  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [status, setStatus] = useState(filters.status || '');

  const handleFilter = () => {
    router.get('/reports/bank-reconciliations', {
      bank_account_id: bankAccountId || undefined,
      status: status || undefined,
    });
  };

  return (
    <AppLayout active="reports.bank-reconciliations">
      <Head title={isAr ? 'تقرير تسويات البنك - Mini ERP' : 'Bank Reconciliation Report - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تقرير ومتابعة تسويات البنك' : 'Bank Reconciliation Report'}
        description={isAr ? 'عرض تقارير ومطابقات كشوف الحسابات البنكية وحالات اكتمال التسوية.' : 'Read-only overview and audit reports for bank reconciliation statements.'}
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'حساب البنك' : 'Bank Account'}
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
                {isAr ? 'حالة المطابقة' : 'Status'}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: isAr ? 'جميع الحالات' : 'All Statuses' },
                  { value: 'draft', label: isAr ? 'مسودة' : 'Draft' },
                  { value: 'reconciled', label: isAr ? 'معتمدة / مطابقة' : 'Reconciled' },
                ]}
                value={status}
                onChange={(val) => setStatus(val || '')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {isAr ? 'عرض التسويات' : 'View Reconciliations'}
              </Button>
            </div>
          </div>
        </Card>

        <Card className="overflow-hidden p-0">
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'حساب البنك' : 'Bank Account'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'مرجع الكشف' : 'Statement Ref'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'فترة الكشف' : 'Period'}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'الحالة' : 'Status'}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'المطابق / الإجمالي' : 'Matched / Total'}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'الفارق' : 'Difference'}</th>
                <th className="p-3 font-semibold text-center text-[var(--text-secondary)]">{isAr ? 'التفاصيل' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.reconciliations.map((recon) => (
                <tr key={recon.id} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-bold">{recon.bank_account.code} - {recon.bank_account.name}</td>
                  <td className="p-3 font-mono">{recon.statement_reference}</td>
                  <td className="p-3 text-[var(--text-secondary)]">{recon.date_from} → {recon.date_to}</td>
                  <td className="p-3">
                    <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full ${
                      recon.status === 'reconciled'
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-amber-100 text-amber-800 border border-amber-300'
                    }`}>
                      {recon.status.toUpperCase()}
                    </span>
                  </td>
                  <td className="p-3 text-end font-mono">
                    {recon.summary.matched_statement_lines_count} / {recon.summary.total_statement_lines_count}
                  </td>
                  <td className={`p-3 text-end font-mono font-bold ${recon.summary.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                    {formatMoney(recon.summary.difference_minor, recon.bank_account.currency)}
                  </td>
                  <td className="p-3 text-center">
                    <Link
                      href={`/reports/bank-reconciliations/${recon.id}`}
                      className="text-xs font-bold text-[var(--primary)] hover:underline"
                    >
                      {isAr ? 'عرض الكشف ←' : 'View Detail →'}
                    </Link>
                  </td>
                </tr>
              ))}
              {report.reconciliations.length === 0 ? (
                <tr>
                  <td colSpan={7} className="p-8 text-center text-[var(--text-muted)]">
                    {isAr ? 'لا توجد تسويات بنكية مطابقة للبحث.' : 'No bank reconciliations found.'}
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
