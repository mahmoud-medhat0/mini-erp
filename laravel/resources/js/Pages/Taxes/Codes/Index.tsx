import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader } from '../../../Components/Primitives';
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
  const taxDict = (dict.app as any).taxes || {};

  const [search, setSearch] = useState(filters.search || '');

  function getTransName(nameObj?: Record<string, string> | string | null): string {
    if (!nameObj) return '-';
    if (typeof nameObj === 'string') return nameObj;
    return nameObj[locale] || nameObj.en || '-';
  }

  function handleFilter() {
    router.get('/taxes/codes', { search }, { preserveState: true, replace: true });
  }

  function handleDelete(id: string) {
    if (confirm(taxDict.confirmDeleteCode || 'Are you sure you want to delete this tax code?')) {
      router.delete(`/taxes/codes/${id}`);
    }
  }

  return (
    <AppLayout active="taxes.codes.index">
      <Head title={taxDict.title || 'Tax Codes & Rates'} />

      <div className="space-y-6">
        <PageHeader
          title={taxDict.title || 'Tax Codes & Rates'}
          description={taxDict.subtitle || 'Manage master-data tax codes, rates, effective date ranges, and modes.'}
          actions={
            <div className="flex items-center gap-3">
              <Link
                href="/taxes/rates"
                className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition font-medium text-sm"
              >
                {taxDict.taxRates || 'Tax Rates'}
              </Link>
              <Link
                href="/taxes/codes/create"
                className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium text-sm"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                <span>{taxDict.newTaxCode || 'New Tax Code'}</span>
              </Link>
            </div>
          }
        />

        {/* Filter */}
        <Card className="p-4">
          <div className="flex items-center gap-4">
            <div className="flex-1">
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                placeholder={taxDict.code || 'Search tax code or name...'}
                className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
              />
            </div>
            <button
              onClick={handleFilter}
              className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-md font-medium text-sm hover:bg-slate-200 transition"
            >
              {locale === 'ar' ? 'بحث' : 'Search'}
            </button>
          </div>
        </Card>

        {/* Tax Codes Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left rtl:text-right">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 uppercase text-xs">
                <tr>
                  <th className="px-4 py-3">{taxDict.code || 'Code'}</th>
                  <th className="px-4 py-3">{taxDict.nameEn || 'Name'}</th>
                  <th className="px-4 py-3">{taxDict.calculationMode || 'Mode'}</th>
                  <th className="px-4 py-3">{taxDict.recoverabilityMode || 'Recoverability'}</th>
                  <th className="px-4 py-3 text-center">{taxDict.ratesCount || 'Rates'}</th>
                  <th className="px-4 py-3">{taxDict.status || 'Status'}</th>
                  <th className="px-4 py-3 text-right rtl:text-left">{taxDict.actions || 'Actions'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {taxCodes.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                      {taxDict.emptyCodes || 'No tax codes configured.'}
                    </td>
                  </tr>
                ) : (
                  taxCodes.data.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                        {item.code}
                      </td>
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                        {getTransName(item.name)}
                      </td>
                      <td className="px-4 py-3 capitalize text-slate-600 dark:text-slate-400">
                        {item.calculation_mode}
                      </td>
                      <td className="px-4 py-3 capitalize text-slate-600 dark:text-slate-400">
                        {item.recoverability_mode}
                      </td>
                      <td className="px-4 py-3 text-center font-mono font-medium">
                        {item.rates_count ?? 0}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                          item.is_active
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200'
                        }`}>
                          {item.is_active ? (taxDict.active || 'Active') : (taxDict.inactive || 'Inactive')}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right rtl:text-left space-x-2 rtl:space-x-reverse">
                        <Link
                          href={`/taxes/codes/${item.id}/edit`}
                          className="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                          {taxDict.editTaxCode || 'Edit'}
                        </Link>
                        {!item.is_system && (
                          <button
                            onClick={() => handleDelete(item.id)}
                            className="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline"
                          >
                            {taxDict.delete || 'Delete'}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
