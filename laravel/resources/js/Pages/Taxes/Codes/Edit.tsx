import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
};

type EditProps = SharedPageProps & {
  taxCode: TaxCode;
};

export default function TaxCodeEdit({ locale, taxCode }: EditProps) {
  const dict = getDictionary(locale);
  const taxDict = (dict.app as any).taxes || {};

  const nameObj = typeof taxCode.name === 'object' ? taxCode.name : { en: taxCode.name, ar: taxCode.name };

  const { data, setData, put, processing, errors } = useForm({
    code: taxCode.code,
    name: {
      en: nameObj.en || '',
      ar: nameObj.ar || '',
    },
    calculation_mode: taxCode.calculation_mode,
    recoverability_mode: taxCode.recoverability_mode,
    is_active: taxCode.is_active,
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    put(`/taxes/codes/${taxCode.id}`);
  }

  return (
    <AppLayout active="taxes.codes.index">
      <Head title={taxDict.editTaxCode || 'Edit Tax Code'} />

      <div className="space-y-6 max-w-3xl mx-auto">
        <PageHeader
          title={taxDict.editTaxCode || 'Edit Tax Code'}
          description={`${taxCode.code}`}
          actions={
            <Link
              href="/taxes/codes"
              className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
            >
              {taxDict.backToCodes || 'Back to Tax Codes'}
            </Link>
          }
        />

        <Card className="p-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                {taxDict.code || 'Code'} *
              </label>
              <input
                type="text"
                value={data.code}
                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                disabled={taxCode.is_system}
                className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm font-mono disabled:opacity-60"
                required
              />
              {errors.code && <p className="text-xs text-rose-600 mt-1">{errors.code}</p>}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.nameEn || 'English Name'} *
                </label>
                <input
                  type="text"
                  value={data.name.en}
                  onChange={(e) => setData('name', { ...data.name, en: e.target.value })}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.nameAr || 'Arabic Name'} *
                </label>
                <input
                  type="text"
                  value={data.name.ar}
                  onChange={(e) => setData('name', { ...data.name, ar: e.target.value })}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                  required
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.calculationMode || 'Calculation Mode'}
                </label>
                <select
                  value={data.calculation_mode}
                  onChange={(e) => setData('calculation_mode', e.target.value as any)}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                >
                  <option value="exclusive">{taxDict.exclusive || 'Tax-Exclusive (Net + Tax)'}</option>
                  <option value="inclusive">{taxDict.inclusive || 'Tax-Inclusive (Gross Includes Tax)'}</option>
                  <option value="exempt">{taxDict.exempt || 'Exempt / Out of Scope'}</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                  {taxDict.recoverabilityMode || 'Recoverability Mode'}
                </label>
                <select
                  value={data.recoverability_mode}
                  onChange={(e) => setData('recoverability_mode', e.target.value as any)}
                  className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
                >
                  <option value="full">{taxDict.full || '100% Recoverable Input VAT'}</option>
                  <option value="none">{taxDict.none || 'Non-Recoverable'}</option>
                </select>
              </div>
            </div>

            <div className="flex items-center gap-2 pt-2">
              <input
                type="checkbox"
                id="is_active"
                checked={data.is_active}
                onChange={(e) => setData('is_active', e.target.checked)}
                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <label htmlFor="is_active" className="text-sm font-medium text-slate-700 dark:text-slate-300">
                {taxDict.active || 'Active'}
              </label>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
              <Link
                href="/taxes/codes"
                className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
              >
                {taxDict.cancel || 'Cancel'}
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium disabled:opacity-50"
              >
                {taxDict.save || 'Save Changes'}
              </button>
            </div>
          </form>
        </Card>
      </div>
    </AppLayout>
  );
}
