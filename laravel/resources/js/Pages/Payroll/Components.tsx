import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
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
type Props = SharedPageProps & {
  components?: Component[];
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
  const [type, setType] = useState(filters.type || '');
  const [reloadToken, setReloadToken] = useState(0);

  const expenseOptions = useMemo(() => expenseAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency_code || undefined })), [expenseAccounts, locale]);
  const liabilityOptions = useMemo(() => liabilityAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency_code || undefined })), [liabilityAccounts, locale]);
  const typeOptions = types.map((item) => ({ value: item, label: componentTypeLabels[item] || item }));
  const typeFilterOptions = [{ value: '', label: pageDict.allTypes }, ...typeOptions];
  const calculationOptions = calculationTypes.map((item) => ({ value: item, label: calculationTypeLabels[item] || item }));
  const activeFilterCount = [filters.search, type].filter(Boolean).length;

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

  function clearFilters() {
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
      router.put(`/payroll/components/${editing.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => {
          setShowForm(false);
          setReloadToken((value) => value + 1);
        },
      });
      return;
    }

    router.post('/payroll/components', payload, {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setReloadToken((value) => value + 1);
      },
    });
  }

  function deleteComponent(component: Component) {
    if (!confirm(pageDict.confirmDeleteComponent)) {
      return;
    }

    router.delete(`/payroll/components/${component.id}`, {
      preserveScroll: true,
      onSuccess: () => setReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'sort_order', name: 'sort_order', title: pageDict.sortOrder, visible: false, searchable: false },
    { data: 'code', name: 'code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.name, orderable: false },
    { data: 'type', name: 'type', title: pageDict.type },
    { data: 'calculation_type', name: 'calculation_type', title: pageDict.calculationType },
    { data: 'default_amount_minor', name: 'default_amount_minor', title: pageDict.defaultAmount, searchable: false },
    { data: 'employee_assignments_count', name: 'employee_assignments_count', title: pageDict.assignments, searchable: false },
    { data: 'is_active', name: 'is_active', title: pageDict.active, searchable: false },
    { data: 'actions', name: 'actions', title: shared.actions, orderable: false, searchable: false },
  ], [pageDict, shared.actions]);

  const slots = useMemo<DataTableSlots>(() => ({
    name_text: (_value: any, _type: any, component: Component) => getLocalizedName(component.name, locale),
    type: (value: any) => componentTypeLabels[value] || value,
    calculation_type: (value: any) => calculationTypeLabels[value] || value,
    default_amount_minor: (value: any) => formatAmount(Number(value || 0)),
    employee_assignments_count: (value: any) => Number(value || 0),
    is_active: (_value: any, _type: any, component: Component) => (
      <StatusBadge tone={component.is_active ? 'ok' : 'muted'}>{component.is_active ? pageDict.active : pageDict.inactive}</StatusBadge>
    ),
    actions: (_value: any, _type: any, component: Component) => (
      <div className="flex flex-wrap gap-2">
        {can('payroll.edit') && can('view_payroll') ? <Button variant="secondary" onClick={() => openEdit(component)}>{shared.edit}</Button> : null}
        {can('payroll.delete') && can('view_payroll') && !component.is_system ? <Button variant="danger" onClick={() => deleteComponent(component)}>{shared.delete}</Button> : null}
      </div>
    ),
  }), [calculationTypeLabels, can, componentTypeLabels, locale, pageDict, shared.delete, shared.edit]);

  const tableFilters = useMemo(() => ({ type }), [type]);
  const toolbar = (
    <div className="flex flex-wrap items-center gap-3">
      <SearchableSelect options={typeFilterOptions} value={type || null} onChange={(value) => setType(value || '')} label={pageDict.type} />
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{shared.clearFilter}</Button>
    </div>
  );

  return (
    <AppLayout active="payroll.components.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('payroll.create') && can('view_payroll') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

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

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/payroll/components/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[0, 'asc'], [1, 'asc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="payroll-components-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
