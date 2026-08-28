import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import SearchableSelect from '../../../Components/SearchableSelect';
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

type CalculationMode = TaxCode['calculation_mode'];
type RecoverabilityMode = TaxCode['recoverability_mode'];
type TaxCodeForm = {
  code: string;
  name: {
    en: string;
    ar: string;
  };
  calculation_mode: CalculationMode;
  recoverability_mode: RecoverabilityMode;
  is_active: boolean;
};

export default function TaxCodeEdit({ locale, taxCode }: EditProps) {
  const dict = getDictionary(locale);
  const taxDict = dict.app.taxes;

  const nameObj = typeof taxCode.name === 'object' ? taxCode.name : { en: taxCode.name, ar: taxCode.name };

  const { data, setData, put, processing, errors } = useForm<TaxCodeForm>({
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
    put(`/taxes/codes/${taxCode.id}`, { preserveScroll: true });
  }

  const calculationModeOptions: Array<{ value: CalculationMode; label: string }> = [
    { value: 'exclusive', label: taxDict.exclusive },
    { value: 'inclusive', label: taxDict.inclusive },
    { value: 'exempt', label: taxDict.exempt },
  ];
  const recoverabilityModeOptions: Array<{ value: RecoverabilityMode; label: string }> = [
    { value: 'full', label: taxDict.full },
    { value: 'none', label: taxDict.none },
  ];

  return (
    <AppLayout active="taxes.codes.index">
      <Head title={taxDict.editTaxCode} />

      <div className="space-y-6 max-w-3xl mx-auto">
        <PageHeader
          title={taxDict.editTaxCode}
          description={`${taxCode.code}`}
          actions={
            <Link
              href="/taxes/codes"
              className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
            >
              {taxDict.backToCodes}
            </Link>
          }
        />

        <Card className="p-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                {taxDict.code} *
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
                  {taxDict.nameEn} *
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
                  {taxDict.nameAr} *
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
              <SearchableSelect<CalculationMode>
                label={taxDict.calculationMode}
                options={calculationModeOptions}
                value={data.calculation_mode}
                onChange={(value) => setData('calculation_mode', value || 'exclusive')}
                isClearable={false}
              />

              <SearchableSelect<RecoverabilityMode>
                label={taxDict.recoverabilityMode}
                options={recoverabilityModeOptions}
                value={data.recoverability_mode}
                onChange={(value) => setData('recoverability_mode', value || 'full')}
                isClearable={false}
              />
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
                {taxDict.active}
              </label>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
              <Link
                href="/taxes/codes"
                className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
              >
                {taxDict.cancel}
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium disabled:opacity-50"
                title={taxDict.save}
                aria-label={taxDict.save}
              >
                {taxDict.save}
              </button>
            </div>
          </form>
        </Card>
      </div>
    </AppLayout>
  );
}
