import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader } from '../../../Components/Primitives';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type TaxCode = {
  id: string;
  code: string;
  name: Record<string, string> | string;
};

type TaxRate = {
  id: string;
  tax_code_id: string;
  rate_bps: number;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
  taxCode?: TaxCode | null;
};

type PaginatedRates = {
  data: TaxRate[];
  current_page: number;
  last_page: number;
  total: number;
};

type RatesProps = SharedPageProps & {
  taxRates: PaginatedRates;
  taxCodes: TaxCode[];
  filters: { tax_code_id?: string };
};

export default function TaxRatesIndex({ locale, taxRates, taxCodes, filters }: RatesProps) {
  const dict = getDictionary(locale);
  const taxDict = dict.app.taxes;

  const [selectedTaxCode, setSelectedTaxCode] = useState(filters.tax_code_id || '');
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    tax_code_id: taxCodes[0]?.id || '',
    rate_bps: 1400,
    effective_from: new Date().toISOString().split('T')[0],
    effective_to: '',
    is_active: true,
  });

  function getTransName(nameObj?: Record<string, string> | string | null): string {
    if (!nameObj) return taxDict.notAvailable;
    if (typeof nameObj === 'string') return nameObj;
    return nameObj[locale] || nameObj.en || taxDict.notAvailable;
  }

  function handleFilter(codeId: string) {
    setSelectedTaxCode(codeId);
    router.get('/taxes/rates', { tax_code_id: codeId }, { preserveState: true, replace: true });
  }

  function handleCreateRate(e: FormEvent) {
    e.preventDefault();
    post('/taxes/rates', {
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  }

  function handleDeleteRate(id: string) {
    if (confirm(taxDict.confirmDeleteRate)) {
      router.delete(`/taxes/rates/${id}`);
    }
  }

  return (
    <AppLayout active="taxes.rates.index">
      <Head title={taxDict.taxRates} />

      <div className="space-y-6">
        <PageHeader
          title={taxDict.taxRates}
          description={taxDict.subtitle}
          actions={
            <div className="flex items-center gap-3">
              <Link
                href="/taxes/codes"
                className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition font-medium text-sm"
              >
                {taxDict.backToCodes}
              </Link>
              <button
                onClick={() => setShowModal(true)}
                className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium text-sm"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                <span>{taxDict.newTaxRate}</span>
              </button>
            </div>
          }
        />

        {/* Filter Card */}
        <Card className="p-4">
          <div className="flex items-center gap-4">
            <div className="w-64">
              <select
                value={selectedTaxCode}
                onChange={(e) => handleFilter(e.target.value)}
                className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
              >
                <option value="">{taxDict.allTaxCodes}</option>
                {taxCodes.map((code) => (
                  <option key={code.id} value={code.id}>
                    {code.code} - {getTransName(code.name)}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </Card>

        {/* Rates Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left rtl:text-right">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 uppercase text-xs">
                <tr>
                  <th className="px-4 py-3">{taxDict.code}</th>
                  <th className="px-4 py-3 text-right">{taxDict.rateBps}</th>
                  <th className="px-4 py-3 text-right">{taxDict.percentage}</th>
                  <th className="px-4 py-3">{taxDict.effectiveFrom}</th>
                  <th className="px-4 py-3">{taxDict.effectiveTo}</th>
                  <th className="px-4 py-3">{taxDict.status}</th>
                  <th className="px-4 py-3 text-right rtl:text-left">{taxDict.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {taxRates.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                      {taxDict.emptyRates}
                    </td>
                  </tr>
                ) : (
                  taxRates.data.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                        {item.taxCode?.code} ({getTransName(item.taxCode?.name)})
                      </td>
                      <td className="px-4 py-3 text-right font-mono font-semibold">
                        {item.rate_bps} {taxDict.basisPointsSuffix}
                      </td>
                      <td className="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {(item.rate_bps / 100).toFixed(2)}%
                      </td>
                      <td className="px-4 py-3">{item.effective_from}</td>
                      <td className="px-4 py-3 text-slate-500">{item.effective_to || taxDict.notAvailable}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                          item.is_active
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200'
                        }`}>
                          {item.is_active ? taxDict.active : taxDict.inactive}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right rtl:text-left">
                        <button
                          onClick={() => handleDeleteRate(item.id)}
                          className="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline"
                        >
                          {taxDict.delete}
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      {/* Modal for Creating New Tax Rate */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <Card className="w-full max-w-md p-6 space-y-4">
            <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100">
              {taxDict.newTaxRate}
            </h3>
            <form onSubmit={handleCreateRate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.code} *
                </label>
                <select
                  value={data.tax_code_id}
                  onChange={(e) => setData('tax_code_id', e.target.value)}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                  required
                >
                  {taxCodes.map((code) => (
                    <option key={code.id} value={code.id}>
                      {code.code} - {getTransName(code.name)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.rateBpsInput} *
                </label>
                <input
                  type="number"
                  value={data.rate_bps}
                  onChange={(e) => setData('rate_bps', parseInt(e.target.value) || 0)}
                  min={0}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm font-mono"
                  required
                />
                <p className="text-xs text-slate-500 mt-1">
                  {(data.rate_bps / 100).toFixed(2)}%
                </p>
                {errors.rate_bps && <p className="text-xs text-rose-600 mt-1">{errors.rate_bps}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {taxDict.effectiveFrom} *
                  </label>
                  <input
                    type="date"
                    value={data.effective_from}
                    onChange={(e) => setData('effective_from', e.target.value)}
                    className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {taxDict.effectiveTo}
                  </label>
                  <input
                    type="date"
                    value={data.effective_to}
                    onChange={(e) => setData('effective_to', e.target.value)}
                    className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
                >
                  {taxDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium disabled:opacity-50"
                >
                  {taxDict.save}
                </button>
              </div>
            </form>
          </Card>
        </div>
      )}
    </AppLayout>
  );
}
