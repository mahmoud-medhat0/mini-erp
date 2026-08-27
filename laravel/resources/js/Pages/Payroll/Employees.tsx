import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Component = { id: string; code: string; name: TranslatedName; type: 'earning' | 'deduction'; calculation_type: 'fixed' | 'percent_of_base' };
type Assignment = {
  id: string;
  payroll_component_id: string;
  amount_minor?: number | null;
  rate_bps?: number | null;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
  component?: Component | null;
};
type Employee = {
  id: string;
  code: string;
  name: TranslatedName;
  branch_id?: string | null;
  status: string;
  hire_date: string;
  termination_date?: string | null;
  currency: string;
  base_salary_minor: number;
  payment_method: string;
  notes?: string | null;
  lock_version: number;
  branch?: Branch | null;
  component_assignments?: Assignment[];
};
type PaginatedData<T> = { data: T[]; total: number; links: any[] };
type Props = SharedPageProps & {
  employees: PaginatedData<Employee>;
  branches: Branch[];
  currencies: CurrencyOption[];
  components: Component[];
  statuses: string[];
  paymentMethods: string[];
  filters: { search?: string; status?: string; branch_id?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function amountToMinor(value: string): number {
  return Math.round(Number(value || 0) * 100);
}

function minorToAmount(value?: number | null): string {
  return (Number(value || 0) / 100).toFixed(2);
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active') return 'ok';
  if (value === 'terminated') return 'danger';
  if (value === 'inactive') return 'warning';

  return 'muted';
}

export default function PayrollEmployeesIndex({
  locale,
  employees,
  branches = [],
  currencies = [],
  components = [],
  statuses = [],
  paymentMethods = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.payrollEmployees;
  const shared = dict.app.pages.payrollShared;
  const statusLabels = pageDict.statuses as Record<string, string>;
  const paymentMethodLabels = pageDict.paymentMethods as Record<string, string>;
  const componentTypeLabels = shared.componentTypes as Record<string, string>;
  const can = useCan();
  const defaultCurrency = currencies[0]?.code || '';
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Employee | null>(null);
  const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(employees.data[0] || null);
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');

  const branchOptions = useMemo(() => branches.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [branches, locale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [currencies, locale]);
  const componentOptions = useMemo(() => components.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: componentTypeLabels[item.type] || item.type })), [components, locale, componentTypeLabels]);
  const statusOptions = statuses.map((item) => ({ value: item, label: statusLabels[item] || item }));
  const paymentOptions = paymentMethods.map((item) => ({ value: item, label: paymentMethodLabels[item] || item }));
  const activeFilterCount = [search, status, branchId].filter(Boolean).length;

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    branch_id: '',
    status: 'active',
    hire_date: today(),
    termination_date: '',
    currency: defaultCurrency,
    base_salary_minor: 0,
    base_amount: '',
    payment_method: 'manual',
    notes: '',
    lock_version: 1,
  });

  const assignmentForm = useForm({
    payroll_component_id: components[0]?.id || '',
    amount_minor: null as number | null,
    amount: '',
    rate_bps: null as number | null,
    effective_from: today(),
    effective_to: '',
    is_active: true,
  });

  function applyFilters() {
    router.get('/payroll/employees', { search, status, branch_id: branchId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setBranchId('');
    router.get('/payroll/employees', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      branch_id: '',
      status: 'active',
      hire_date: today(),
      termination_date: '',
      currency: defaultCurrency,
      base_salary_minor: 0,
      base_amount: '',
      payment_method: 'manual',
      notes: '',
      lock_version: 1,
    });
    setShowForm(true);
  }

  function openEdit(employee: Employee) {
    setEditing(employee);
    form.setData({
      code: employee.code,
      name: {
        en: getLocalizedName(employee.name, 'en'),
        ar: getLocalizedName(employee.name, 'ar'),
      },
      branch_id: employee.branch_id || '',
      status: employee.status,
      hire_date: employee.hire_date,
      termination_date: employee.termination_date || '',
      currency: employee.currency,
      base_salary_minor: employee.base_salary_minor,
      base_amount: minorToAmount(employee.base_salary_minor),
      payment_method: employee.payment_method,
      notes: employee.notes || '',
      lock_version: employee.lock_version,
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      branch_id: form.data.branch_id || null,
      termination_date: form.data.termination_date || null,
      base_salary_minor: amountToMinor(form.data.base_amount),
    };

    if (editing) {
      router.put(`/payroll/employees/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/payroll/employees', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function submitAssignment(event: FormEvent) {
    event.preventDefault();
    if (!selectedEmployee) return;

    router.post(`/payroll/employees/${selectedEmployee.id}/components`, {
      ...assignmentForm.data,
      amount_minor: assignmentForm.data.amount ? amountToMinor(assignmentForm.data.amount) : null,
      effective_to: assignmentForm.data.effective_to || null,
    }, { preserveScroll: true });
  }

  function deleteAssignment(assignment: Assignment) {
    if (!selectedEmployee || !confirm(pageDict.confirmDeleteAssignment)) {
      return;
    }

    router.delete(`/payroll/employees/${selectedEmployee.id}/components/${assignment.id}`, { preserveScroll: true });
  }

  return (
    <AppLayout active="payroll.employees.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('payroll.create') && can('view_payroll') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={pageDict.branch} />
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
            <SearchableSelect options={currencyOptions} value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} />
            <SearchableSelect options={[{ value: '', label: pageDict.noBranch }, ...branchOptions]} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
            <SearchableSelect options={statusOptions} value={form.data.status} onChange={(value) => form.setData('status', value || 'active')} label={pageDict.status} />
            <SearchableSelect options={paymentOptions} value={form.data.payment_method} onChange={(value) => form.setData('payment_method', value || 'manual')} label={pageDict.paymentMethod} />
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.baseSalary}</span>
              <input className="input" type="number" min="0" step="0.01" value={form.data.base_amount} onChange={(event) => form.setData('base_amount', event.target.value)} />
            </label>
            <DatePicker value={form.data.hire_date} onChange={(value) => form.setData('hire_date', value || '')} label={pageDict.hireDate} />
            <DatePicker value={form.data.termination_date} onChange={(value) => form.setData('termination_date', value || '')} label={pageDict.terminationDate} />
            <label className="space-y-1 text-sm font-semibold lg:col-span-2">
              <span>{pageDict.notes}</span>
              <input className="input" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            </label>
            <div className="flex items-end gap-2 lg:col-span-4">
              <Button type="submit" disabled={form.processing}>{editing ? shared.update : shared.save}</Button>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{shared.cancel}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {employees.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.code}</th>
                  <th className={tableClasses.th}>{pageDict.name}</th>
                  <th className={tableClasses.th}>{pageDict.branch}</th>
                  <th className={tableClasses.th}>{pageDict.baseSalary}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={tableClasses.th}>{shared.actions}</th>
                </tr>
              </thead>
              <tbody>
                {employees.data.map((employee) => (
                  <tr key={employee.id} className={selectedEmployee?.id === employee.id ? 'bg-[var(--background)]' : ''}>
                    <td className={tableClasses.td}>{employee.code}</td>
                    <td className={tableClasses.td}>{getLocalizedName(employee.name, locale)}</td>
                    <td className={tableClasses.td}>{employee.branch ? `${employee.branch.code} - ${getLocalizedName(employee.branch.name, locale)}` : pageDict.noBranch}</td>
                    <td className={tableClasses.td}><AccountingAmount amountMinor={employee.base_salary_minor} currency={employee.currency} /></td>
                    <td className={tableClasses.td}><StatusBadge tone={statusTone(employee.status)}>{statusLabels[employee.status] || employee.status}</StatusBadge></td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setSelectedEmployee(employee)}>{pageDict.components}</Button>
                        {can('payroll.edit') && can('view_payroll') ? <Button variant="secondary" onClick={() => openEdit(employee)}>{shared.edit}</Button> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Card className="p-4">
            <h2 className="text-base font-bold">{pageDict.assignmentPanel}</h2>
            {selectedEmployee ? (
              <div className="mt-4 space-y-4">
                <div className="text-sm font-semibold">{selectedEmployee.code} - {getLocalizedName(selectedEmployee.name, locale)}</div>
                <form onSubmit={submitAssignment} className="grid gap-3">
                  <SearchableSelect options={componentOptions} value={assignmentForm.data.payroll_component_id || null} onChange={(value) => assignmentForm.setData('payroll_component_id', value || '')} label={pageDict.component} />
                  <label className="space-y-1 text-sm font-semibold">
                    <span>{pageDict.amount}</span>
                    <input className="input" type="number" min="0" step="0.01" value={assignmentForm.data.amount} onChange={(event) => assignmentForm.setData('amount', event.target.value)} />
                  </label>
                  <label className="space-y-1 text-sm font-semibold">
                    <span>{pageDict.rateBps}</span>
                    <input className="input" type="number" min="0" max="1000000" value={assignmentForm.data.rate_bps ?? ''} onChange={(event) => assignmentForm.setData('rate_bps', event.target.value === '' ? null : Number(event.target.value))} />
                  </label>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <DatePicker value={assignmentForm.data.effective_from} onChange={(value) => assignmentForm.setData('effective_from', value || '')} label={pageDict.effectiveFrom} />
                    <DatePicker value={assignmentForm.data.effective_to} onChange={(value) => assignmentForm.setData('effective_to', value || '')} label={pageDict.effectiveTo} />
                  </div>
                  {can('payroll.edit') && can('view_payroll') ? <Button type="submit">{pageDict.addComponent}</Button> : null}
                </form>
                <div className="space-y-2">
                  {(selectedEmployee.component_assignments || []).length === 0 ? <p className="text-sm text-[var(--text-secondary)]">{pageDict.noComponents}</p> : null}
                  {(selectedEmployee.component_assignments || []).map((assignment) => (
                    <div key={assignment.id} className="rounded-md border border-[var(--border)] p-3 text-sm">
                      <div className="font-semibold">{assignment.component ? `${assignment.component.code} - ${getLocalizedName(assignment.component.name, locale)}` : pageDict.component}</div>
                      <div className="mt-1 text-xs text-[var(--text-secondary)]">
                        {formatDate(assignment.effective_from)} {assignment.effective_to ? `- ${formatDate(assignment.effective_to)}` : ''}
                      </div>
                      <div className="mt-2 flex items-center justify-between gap-2">
                        <AccountingAmount amountMinor={assignment.amount_minor || 0} currency={selectedEmployee.currency} />
                        {can('payroll.edit') && can('view_payroll') ? <Button variant="danger" onClick={() => deleteAssignment(assignment)}>{shared.delete}</Button> : null}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="mt-3 text-sm text-[var(--text-secondary)]">{pageDict.selectEmployee}</p>
            )}
          </Card>
        </div>
      )}
    </AppLayout>
  );
}
