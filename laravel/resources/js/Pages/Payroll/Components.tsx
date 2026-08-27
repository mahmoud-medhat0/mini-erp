import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatAccountingAmount, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Component = {
  id: string;
  code: string;
  name: TranslatedName;
  type: 'earning' | 'deduction';
  calculation_type: 'fixed' | 'percent_of_base';
  default_amount_minor?: number | null;
  rate_bps?: number | null;
  expense_account_id?: string | null;
  liability_account_id?: string | null;
  sort_order: number;
  is_system: boolean;
  is_active: boolean;
  lock_version: number;
  employee_assignments_count?: number;
  expense_account?: AccountOption | null;
  liability_account?: AccountOption | null;
};
type PaginatedData<T> = { data: T[]; total: number; links: any[] };
type Props = SharedPageProps & {
  components: PaginatedData<Component>;
  expenseAccounts: AccountOption[];
  liabilityAccounts: AccountOption[];
  types: string[];
  calculationTypes: string[];
  filters: { search?: string; type?: string };
};

function amountToMinor(value: string): number | null {
  if (value === '') return null;

  return Math.round(Number(value || 0) * 100);
}

function minorToAmount(value?: number | null): string {
  if (value === null || value === undefined) return '';

  return (Number(value || 0) / 100).toFixed(2);
}

export default function PayrollComponentsIndex({
  locale,
  components,
  expenseAccounts = [],
  liabilityAccounts = [],
  types = [],
  calculationTypes = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.payrollComponents;
  const shared = dict.app.pages.payrollShared;
  const componentTypeLabels = shared.componentTypes as Record<string, string>;
  const calculationTypeLabels = pageDict.calculationTypes as Record<string, string>;
  const formatAmount = (amountMinor?: number | null) => formatAccountingAmount(amountMinor || 0, '', { zeroAsDash: false, showCurrency: false });
  const can = useCan();
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Component | null>(null);
  const [search, setSearch] = useState(filters.search || '');
  const [type, setType] = useState(filters.type || '');

  const expenseOptions = useMemo(() => expenseAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency_code || undefined })), [expenseAccounts, locale]);
  const liabilityOptions = useMemo(() => liabilityAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency_code || undefined })), [liabilityAccounts, locale]);
  const typeOptions = types.map((item) => ({ value: item, label: componentTypeLabels[item] || item }));
  const calculationOptions = calculationTypes.map((item) => ({ value: item, label: calculationTypeLabels[item] || item }));
  const activeFilterCount = [search, type].filter(Boolean).length;

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    type: 'earning',
    calculation_type: 'fixed',
    default_amount_minor: null as number | null,
    amount: '',
    rate_bps: null as number | null,
    expense_account_id: '',
    liability_account_id: '',
    sort_order: 100,
    is_active: true,
    lock_version: 1,
  });

  function applyFilters() {
    router.get('/payroll/components', { search, type }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setType('');
    router.get('/payroll/components', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      type: 'earning',
      calculation_type: 'fixed',
      default_amount_minor: null,
      amount: '',
      rate_bps: null,
      expense_account_id: '',
      liability_account_id: '',
      sort_order: 100,
      is_active: true,
      lock_version: 1,
    });
    setShowForm(true);
  }

  function openEdit(component: Component) {
    setEditing(component);
    form.setData({
      code: component.code,
      name: {
        en: getLocalizedName(component.name, 'en'),
        ar: getLocalizedName(component.name, 'ar'),
      },
      type: component.type,
      calculation_type: component.calculation_type,
      default_amount_minor: component.default_amount_minor ?? null,
      amount: minorToAmount(component.default_amount_minor),
      rate_bps: component.rate_bps ?? null,
      expense_account_id: component.expense_account_id || '',
      liability_account_id: component.liability_account_id || '',
      sort_order: component.sort_order,
      is_active: component.is_active,
      lock_version: component.lock_version,
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      default_amount_minor: amountToMinor(form.data.amount),
      expense_account_id: form.data.expense_account_id || null,
      liability_account_id: form.data.liability_account_id || null,
    };

    if (editing) {
      router.put(`/payroll/components/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/payroll/components', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function deleteComponent(component: Component) {
    if (!confirm(pageDict.confirmDeleteComponent)) {
      return;
    }

    router.delete(`/payroll/components/${component.id}`, { preserveScroll: true });
  }

  return (
    <AppLayout active="payroll.components.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('payroll.create') && can('view_payroll') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allTypes }, ...typeOptions]} value={type || null} onChange={(value) => setType(value || '')} label={pageDict.type} />
          <Button onClick={applyFilters}>{shared.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{shared.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="grid gap-4 lg:grid-cols-4">
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.code}</span>
              <input className="input" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.nameEn}</span>
              <input className="input" value={form.data.name.en} onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.nameAr}</span>
              <input className="input" value={form.data.name.ar} onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })} />
            </label>
            <SearchableSelect options={typeOptions} value={form.data.type} onChange={(value) => form.setData('type', value || 'earning')} label={pageDict.type} />
            <SearchableSelect options={calculationOptions} value={form.data.calculation_type} onChange={(value) => form.setData('calculation_type', value || 'fixed')} label={pageDict.calculationType} />
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.defaultAmount}</span>
              <input className="input" type="number" min="0" step="0.01" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.rateBps}</span>
              <input className="input" type="number" min="0" max="1000000" value={form.data.rate_bps ?? ''} onChange={(event) => form.setData('rate_bps', event.target.value === '' ? null : Number(event.target.value))} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.sortOrder}</span>
              <input className="input" type="number" min="0" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} />
            </label>
            <SearchableSelect options={[{ value: '', label: pageDict.defaultMapping }, ...expenseOptions]} value={form.data.expense_account_id || null} onChange={(value) => form.setData('expense_account_id', value || '')} label={pageDict.expenseAccount} />
            <SearchableSelect options={[{ value: '', label: pageDict.defaultMapping }, ...liabilityOptions]} value={form.data.liability_account_id || null} onChange={(value) => form.setData('liability_account_id', value || '')} label={pageDict.liabilityAccount} />
            <label className="flex items-center gap-2 text-sm font-semibold">
              <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
              <span>{pageDict.active}</span>
            </label>
            <div className="flex items-end gap-2 lg:col-span-4">
              <Button type="submit" disabled={form.processing}>{editing ? shared.update : shared.save}</Button>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{shared.cancel}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {components.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.name}</th>
                <th className={tableClasses.th}>{pageDict.type}</th>
                <th className={tableClasses.th}>{pageDict.calculationType}</th>
                <th className={tableClasses.th}>{pageDict.defaultAmount}</th>
                <th className={tableClasses.th}>{pageDict.assignments}</th>
                <th className={tableClasses.th}>{pageDict.active}</th>
                <th className={tableClasses.th}>{shared.actions}</th>
              </tr>
            </thead>
            <tbody>
              {components.data.map((component) => (
                <tr key={component.id}>
                  <td className={tableClasses.td}>{component.code}</td>
                  <td className={tableClasses.td}>{getLocalizedName(component.name, locale)}</td>
                  <td className={tableClasses.td}>{componentTypeLabels[component.type] || component.type}</td>
                  <td className={tableClasses.td}>{calculationTypeLabels[component.calculation_type] || component.calculation_type}</td>
                  <td className={tableClasses.td}>{formatAmount(component.default_amount_minor)}</td>
                  <td className={tableClasses.td}>{component.employee_assignments_count || 0}</td>
                  <td className={tableClasses.td}><StatusBadge tone={component.is_active ? 'ok' : 'muted'}>{component.is_active ? pageDict.active : pageDict.inactive}</StatusBadge></td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap gap-2">
                      {can('payroll.edit') && can('view_payroll') ? <Button variant="secondary" onClick={() => openEdit(component)}>{shared.edit}</Button> : null}
                      {can('payroll.delete') && can('view_payroll') && !component.is_system ? <Button variant="danger" onClick={() => deleteComponent(component)}>{shared.delete}</Button> : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
