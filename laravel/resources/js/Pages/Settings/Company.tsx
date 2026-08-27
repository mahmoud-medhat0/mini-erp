import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import AttachmentPanel from '../../Components/AttachmentPanel';
import { Card, EmptyState, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { CompanyFormData, CompanyRow, CurrencyRow, SharedPageProps } from '../../Types';

type CompanyProps = SharedPageProps & {
  currencies: CurrencyRow[];
  companies: CompanyRow[];
};

function CompanyFormModal({
  company,
  currencies,
  dict,
  onClose,
}: {
  company?: CompanyRow;
  currencies: CurrencyRow[];
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const { data, setData, post, patch, processing, errors, reset } = useForm<CompanyFormData>({
    name_en: company?.nameEn ?? '',
    name_ar: company?.nameAr ?? '',
    base_currency: company?.baseCurrency ?? (currencies[0]?.code ?? ''),
    lock_version: company?.lockVersion,
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (company) {
      patch(`/settings/company/${company.id}`, {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
    } else {
      post('/settings/company', {
        preserveScroll: true,
        onSuccess: () => {
          reset();
          onClose();
        },
      });
    }
  }

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
          {company ? `${dict.app.actions.edit}: ${company.name}` : dict.app.actions.addCompany}
        </h3>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
        >
          <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.nameEn}
            </label>
            <input
              type="text"
              value={data.name_en}
              onChange={(event) => setData('name_en', event.target.value)}
              placeholder={dict.app.fields.companyNameEnPlaceholder}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.name_en ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name_en}</p> : null}
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.nameAr}
            </label>
            <input
              type="text"
              dir="rtl"
              value={data.name_ar}
              onChange={(event) => setData('name_ar', event.target.value)}
              placeholder={dict.app.fields.companyNameArPlaceholder}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.name_ar ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name_ar}</p> : null}
          </div>

          {/* Base Currency */}
          <SearchableSelect
            label={dict.app.fields.baseCurrency}
            options={currencies.map((c) => ({
              value: c.code,
              label: `${c.code} - ${c.name}`,
              badge: c.code,
            }))}
            value={data.base_currency}
            onChange={(val) => setData('base_currency', val ?? '')}
            isSearchable={true}
            isClearable={false}
          />
        </div>

        {/* Submit Actions */}
        <div className="flex items-center justify-end gap-3 pt-2 border-t border-[var(--border)]">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
          >
            {dict.app.actions.cancel}
          </button>
          <button
            type="submit"
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
          >
            {processing ? (
              <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            ) : null}
            <span>{company ? dict.app.actions.save : dict.app.actions.create}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

export default function CompanySettings({ companies, currencies, locale }: CompanyProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCompanyId, setEditingCompanyId] = useState<string | null>(null);
  const [selectedCompanyId, setSelectedCompanyId] = useState<string>(companies[0]?.id ?? '');

  return (
    <AppLayout active="settings.company">
      <Head title={dict.app.settings.sections.company.title} />

      <PageHeader
        title={dict.app.settings.sections.company.title}
        description={dict.app.settings.company.description}
        actions={
          <button
            type="button"
            onClick={() => {
              setEditingCompanyId(null);
              setShowAddForm(!showAddForm);
            }}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{dict.app.actions.addCompany}</span>
          </button>
        }
      />

      {/* Add Company Form Panel */}
      {showAddForm ? (
        <CompanyFormModal
          currencies={currencies}
          dict={dict}
          onClose={() => setShowAddForm(false)}
        />
      ) : null}

      {/* Main Companies Table Card */}
      {companies.length === 0 ? (
        <EmptyState title={dict.app.settings.company.empty} />
      ) : (
        <div className="space-y-4">
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.fields.company}</th>
                  <th className={tableClasses.th}>{dict.app.fields.baseCurrency}</th>
                  <th className={tableClasses.th}>{dict.app.fields.createdAt}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.actions.edit}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {companies.map((company) => {
                  const isEditing = editingCompanyId === company.id;
                  const isSelectedForAttachments = selectedCompanyId === company.id;

                  return (
                    <tr key={company.id} className="group hover:bg-[var(--background)]/50 transition-colors">
                      <td className={tableClasses.td}>
                        <div className="flex flex-col min-w-44">
                          <span className="font-bold text-[var(--text-primary)] text-sm">{company.name}</span>
                          <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[var(--text-muted)]">
                            <span>{dict.app.fields.nameEn}: {company.nameEn}</span>
                            <span>•</span>
                            <span>{dict.app.fields.nameAr}: {company.nameAr}</span>
                          </div>
                        </div>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 border border-blue-500/20">
                          {company.baseCurrency}
                        </span>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="text-xs font-semibold text-[var(--text-secondary)]">
                          {company.createdAt ? new Date(company.createdAt).toLocaleDateString(locale) : accDict.notAvailable}
                        </span>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex items-center justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => setSelectedCompanyId(company.id)}
                            className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                              isSelectedForAttachments
                                ? 'border-blue-500/50 bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:border-[var(--primary)]'
                            }`}
                          >
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            <span>{dict.app.pages.settingsCompany.attachments}</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setShowAddForm(false);
                              setEditingCompanyId(isEditing ? null : company.id);
                            }}
                            className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                              isEditing
                                ? 'border-[var(--primary)] bg-[var(--primary)] text-white'
                                : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)]'
                            }`}
                          >
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>{dict.app.actions.edit}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Inline Edit Form Panel when editing a specific company */}
          {editingCompanyId ? (
            <div className="pt-2">
              <CompanyFormModal
                company={companies.find((c) => c.id === editingCompanyId)}
                currencies={currencies}
                dict={dict}
                onClose={() => setEditingCompanyId(null)}
              />
            </div>
          ) : null}

          {/* Dynamic Company Attachments Section */}
          {companies.length > 0 && selectedCompanyId ? (
            <div className="mt-6 space-y-3">
              {companies.length > 1 ? (
                <div className="flex items-center justify-between bg-[var(--surface)] p-3.5 rounded-xl border border-[var(--border)]">
                  <label className="text-xs font-bold text-[var(--text-primary)]">
                    {dict.app.pages.settingsCompany.selectCompanyForAttachments}
                  </label>
                  <select
                    value={selectedCompanyId}
                    onChange={(e) => setSelectedCompanyId(e.target.value)}
                    className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs font-semibold text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden cursor-pointer"
                  >
                    {companies.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.baseCurrency})
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}
              <AttachmentPanel
                key={selectedCompanyId}
                entityType="company"
                entityId={selectedCompanyId}
                locale={locale === 'ar' ? 'ar' : 'en'}
              />
            </div>
          ) : null}
        </div>
      )}
    </AppLayout>
  );
}
