import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Button, Card, PageHeader, StatusBadge } from '../../../Components/Primitives';
import { getLocalizedName } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type TaxCode = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  tax_type: string;
  calculation_mode: 'exclusive' | 'inclusive' | 'exempt';
  recoverability_mode: 'full' | 'none';
  is_system: boolean;
  is_active: boolean;
  rates_count?: number;
};

type PaginatedCodes = {
  data: TaxCode[];
  current_page: number;
  last_page: number;
  total: number;
};

type IndexProps = SharedPageProps & {
  taxCodes: PaginatedCodes;
  filters: { search?: string };
};

export default function TaxCodesIndex({ locale, taxCodes, filters }: IndexProps) {
  const dict = getDictionary(locale);
  const taxDict = dict.app.taxes;

  const [search, setSearch] = useState(filters.search || '');

  function getCalcModeLabel(mode: string) {
    if (mode === 'exclusive') return taxDict.exclusive;
    if (mode === 'inclusive') return taxDict.inclusive;
    if (mode === 'exempt') return taxDict.exempt;
    return mode;
  }

  function getRecModeLabel(mode: string) {
    if (mode === 'full') return taxDict.full;
    if (mode === 'none') return taxDict.none;
    return mode;
  }

  function handleFilter() {
    router.get('/taxes/codes', { search }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function handleReset() {
    setSearch('');
    router.get('/taxes/codes', {}, { preserveState: true, preserveScroll: true, replace: true });
  }

  function handleDelete(id: string) {
    if (confirm(taxDict.confirmDeleteCode)) {
      router.delete(`/taxes/codes/${id}`, { preserveScroll: true });
    }
  }

  return (
    <AppLayout active="taxes.codes.index">
      <Head title={taxDict.title} />

      <div className="space-y-6">
        <PageHeader
          title={taxDict.title}
          description={taxDict.subtitle}
          actions={
            <div className="flex items-center gap-3">
              <Link
                href="/taxes/rates"
                title={taxDict.taxRates}
                aria-label={taxDict.taxRates}
                className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] shadow-xs hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                <svg className="size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>{taxDict.taxRates}</span>
              </Link>
              <Link
                href="/taxes/codes/create"
                title={taxDict.newTaxCode}
                aria-label={taxDict.newTaxCode}
                className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>{taxDict.newTaxCode}</span>
              </Link>
            </div>
          }
        />

        {/* Filter Toolbar */}
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="relative flex-1 max-w-md">
              <div className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-[var(--text-muted)]">
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                placeholder={taxDict.searchTaxCode}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] ps-9 pe-9 py-2 text-xs font-semibold text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-all"
              />
              {search && (
                <button
                  type="button"
                  onClick={handleReset}
                  title={dict.app.actions.reset}
                  aria-label={dict.app.actions.reset}
                  className="absolute inset-y-0 end-0 flex items-center pe-3 text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              )}
            </div>
            <Button onClick={handleFilter} title={taxDict.search} aria-label={taxDict.search} className="px-4 py-2 text-xs">
              {taxDict.search}
            </Button>
            {search && (
              <Button variant="secondary" onClick={handleReset} className="px-4 py-2 text-xs">
                {dict.app.actions.reset}
              </Button>
            )}
          </div>
        </Card>

        {/* Tax Codes Table */}
        <Card className="overflow-hidden p-0 shadow-sm border-[var(--border)]">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-[var(--border)] text-xs">
              <thead className="bg-[var(--background)]">
                <tr>
                  <th className="px-4 py-3.5 text-start font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.code}</th>
                  <th className="px-4 py-3.5 text-start font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.name}</th>
                  <th className="px-4 py-3.5 text-start font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.calculationMode}</th>
                  <th className="px-4 py-3.5 text-start font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.recoverabilityMode}</th>
                  <th className="px-4 py-3.5 text-center font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.ratesCount}</th>
                  <th className="px-4 py-3.5 text-start font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.status}</th>
                  <th className="px-4 py-3.5 text-end font-extrabold uppercase text-[var(--text-muted)] tracking-wider">{taxDict.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)] bg-[var(--surface)]">
                {taxCodes.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-12 text-center text-[var(--text-muted)]">
                      <div className="flex flex-col items-center justify-center gap-2">
                        <svg className="size-8 text-[var(--text-muted)] opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                        <span className="font-semibold">{taxDict.emptyCodes}</span>
                      </div>
                    </td>
                  </tr>
                ) : (
                  taxCodes.data.map((item) => (
                    <tr key={item.id} className="hover:bg-[var(--background)]/60 transition-colors">
                      <td className="px-4 py-3.5">
                        <div className="flex items-center gap-2">
                          <span className="inline-block rounded-md bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1 font-mono font-bold text-xs text-indigo-600 dark:text-indigo-400">
                            {item.code}
                          </span>
                          {item.is_system && (
                            <span className="rounded bg-[var(--background)] border border-[var(--border)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--text-muted)] uppercase">
                              {taxDict.isSystem}
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3.5 font-bold text-[var(--text-primary)]">
                        {getLocalizedName(item.name, locale)}
                      </td>
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center rounded-lg bg-[var(--background)] border border-[var(--border)] px-2.5 py-1 text-xs font-semibold text-[var(--text-secondary)]">
                          {getCalcModeLabel(item.calculation_mode)}
                        </span>
                      </td>
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center rounded-lg bg-[var(--background)] border border-[var(--border)] px-2.5 py-1 text-xs font-semibold text-[var(--text-secondary)]">
                          {getRecModeLabel(item.recoverability_mode)}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 text-center">
                        <span className="inline-flex items-center justify-center rounded-md bg-blue-500/10 border border-blue-500/20 px-2.5 py-0.5 font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                          {item.rates_count ?? 0}
                        </span>
                      </td>
                      <td className="px-4 py-3.5">
                        <StatusBadge tone={item.is_active ? 'ok' : 'muted'}>
                          {item.is_active ? taxDict.active : taxDict.inactive}
                        </StatusBadge>
                      </td>
                      <td className="px-4 py-3.5 text-end">
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            href={`/taxes/codes/${item.id}/edit`}
                            title={taxDict.editTaxCode}
                            aria-label={taxDict.editTaxCode}
                            className="inline-flex items-center gap-1 rounded-lg border border-blue-500/20 bg-blue-500/10 px-2.5 py-1 font-bold text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-colors"
                          >
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>{taxDict.editTaxCode}</span>
                          </Link>
                          {!item.is_system && (
                            <button
                              type="button"
                              onClick={() => handleDelete(item.id)}
                              title={taxDict.delete}
                              aria-label={taxDict.delete}
                              className="inline-flex items-center gap-1 rounded-lg border border-rose-500/20 bg-rose-500/10 px-2.5 py-1 font-bold text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-500/20 transition-colors cursor-pointer"
                            >
                              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                              <span>{taxDict.delete}</span>
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {taxCodes.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-[var(--border)] bg-[var(--background)] px-4 py-3 text-xs">
              <span className="text-[var(--text-muted)] font-medium">
                {locale === 'ar' ? `صفحة ${taxCodes.current_page} من ${taxCodes.last_page}` : `Page ${taxCodes.current_page} of ${taxCodes.last_page}`}
              </span>
              <div className="flex items-center gap-2">
                <Button
                  variant="secondary"
                  disabled={taxCodes.current_page <= 1}
                  onClick={() => router.get('/taxes/codes', { ...filters, page: taxCodes.current_page - 1 }, { preserveScroll: true })}
                  className="px-3 py-1 text-xs"
                >
                  {locale === 'ar' ? 'السابق' : 'Previous'}
                </Button>
                <Button
                  variant="secondary"
                  disabled={taxCodes.current_page >= taxCodes.last_page}
                  onClick={() => router.get('/taxes/codes', { ...filters, page: taxCodes.current_page + 1 }, { preserveScroll: true })}
                  className="px-3 py-1 text-xs"
                >
                  {locale === 'ar' ? 'التالي' : 'Next'}
                </Button>
              </div>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
