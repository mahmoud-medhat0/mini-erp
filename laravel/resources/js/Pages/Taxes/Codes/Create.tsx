import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import SearchableSelect from '../../../Components/SearchableSelect';
import { Card, PageHeader } from '../../../Components/Primitives';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type CalculationMode = 'exclusive' | 'inclusive' | 'exempt';
type RecoverabilityMode = 'full' | 'none';
type TaxCodeForm = {
  code: string;
  name: {
    en: string;
    ar: string;
  };
  tax_type: 'vat';
  calculation_mode: CalculationMode;
  recoverability_mode: RecoverabilityMode;
  is_active: boolean;
};

export default function TaxCodeCreate({ locale }: SharedPageProps) {
  const dict = getDictionary(locale);
  const taxDict = dict.app.taxes;

  const { data, setData, post, processing, errors } = useForm<TaxCodeForm>({
    code: '',
    name: {
      en: '',
      ar: '',
    },
    tax_type: 'vat',
    calculation_mode: 'exclusive',
    recoverability_mode: 'full',
    is_active: true,
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    post('/taxes/codes', { preserveScroll: true });
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
      <Head title={taxDict.createTaxCode} />

      <div className="space-y-6 max-w-3xl mx-auto">
        <PageHeader
          title={taxDict.createTaxCode}
          description={taxDict.createSubtitle}
          actions={
            <Link
              href="/taxes/codes"
              className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] shadow-xs hover:bg-[var(--background)] transition-colors"
            >
              <svg className="size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              <span>{taxDict.backToCodes}</span>
            </Link>
          }
        />

        <Card className="p-6 border-[var(--border)] shadow-sm">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {taxDict.code} *
              </label>
              <input
                type="text"
                value={data.code}
                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                placeholder={taxDict.codePlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-all"
                required
              />
              {errors.code && <p className="text-xs text-rose-500 mt-1 font-semibold">{errors.code}</p>}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {taxDict.nameEn} *
                </label>
                <input
                  type="text"
                  value={data.name.en}
                  onChange={(e) => setData('name', { ...data.name, en: e.target.value })}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-all"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {taxDict.nameAr} *
                </label>
                <input
                  type="text"
                  value={data.name.ar}
                  onChange={(e) => setData('name', { ...data.name, ar: e.target.value })}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-all"
                  required
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {taxDict.calculationMode}
                </label>
                <SearchableSelect<CalculationMode>
                  options={calculationModeOptions}
                  value={data.calculation_mode}
                  onChange={(value) => setData('calculation_mode', value || 'exclusive')}
                  isClearable={false}
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {taxDict.recoverabilityMode}
                </label>
                <SearchableSelect<RecoverabilityMode>
                  options={recoverabilityModeOptions}
                  value={data.recoverability_mode}
                  onChange={(value) => setData('recoverability_mode', value || 'full')}
                  isClearable={false}
                />
              </div>
            </div>

            <div className="pt-2">
              <label htmlFor="is_active" className="inline-flex items-center gap-3 p-3 rounded-xl bg-[var(--background)] border border-[var(--border)] cursor-pointer select-none">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="size-4 rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)]"
                />
                <span className="text-xs font-bold text-[var(--text-primary)]">
                  {taxDict.active}
                </span>
              </label>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-[var(--border)]">
              <Link
                href="/taxes/codes"
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors"
              >
                {taxDict.cancel}
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer disabled:opacity-50"
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
