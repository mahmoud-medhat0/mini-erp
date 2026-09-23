import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, Modal, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type ToolCategory = { id: string; code: string; name: TranslatedName };
type Branch = { id: string; code: string; name: TranslatedName };
type Employee = { id: string; code: string; name: TranslatedName };
type Tool = {
  id: string;
  code: string;
  name: TranslatedName;
  description?: TranslatedName;
  tool_category_id: string;
  serial_number?: string | null;
  quantity: number;
  status: string;
  branch_id?: string | null;
  custodian_employee_id?: string | null;
  location_note?: string | null;
  notes?: string | null;
  is_active: boolean;
  lock_version: number;
  category?: ToolCategory | null;
  branch?: Branch | null;
  custodian?: Employee | null;
};

type Props = SharedPageProps & {
  categories: ToolCategory[];
  branches: Branch[];
  employees: Employee[];
  statuses: string[];
  filters: { search?: string; status?: string; tool_category_id?: string; branch_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'available') return 'ok';
  if (value === 'issued') return 'info';
  if (value === 'maintenance') return 'warning';
  if (value === 'damaged' || value === 'lost') return 'danger';
  return 'muted';
}

type CustodyActionType = 'issue' | 'return' | 'transfer' | 'status';

export default function Tools({ locale, categories = [], branches = [], employees = [], statuses = [], filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.tools;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [categoryId, setCategoryId] = useState(filters.tool_category_id || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Tool | null>(null);
  const [custodyAction, setCustodyAction] = useState<{ tool: Tool; type: CustodyActionType } | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);
  const [tableResetToken, setTableResetToken] = useState(0);

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    description: { en: '', ar: '' },
    tool_category_id: '',
    serial_number: '',
    quantity: 1,
    branch_id: '',
    location_note: '',
    notes: '',
    is_active: true,
    reason: '',
    lock_version: 1,
  });

  const custodyForm = useForm({
    custodian_employee_id: '',
    branch_id: '',
    status: 'available',
    reason: '',
  });

  const categoryOptions = useMemo(() => categories.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [categories, activeLocale]);

  const branchOptions = useMemo(() => branches.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [branches, activeLocale]);

  const employeeOptions = useMemo(() => employees.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [employees, activeLocale]);

  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const statusChangeOptions = statusOptions.filter((item) => item.value !== 'issued');
  const activeFilterCount = [search, status, categoryId, branchId].filter(Boolean).length;

  function clearFilters() {
    setSearch('');
    setStatus('');
    setCategoryId('');
    setBranchId('');
    setTableResetToken((value) => value + 1);
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      description: { en: '', ar: '' },
      tool_category_id: categories[0]?.id || '',
      serial_number: '',
      quantity: 1,
      branch_id: '',
      location_note: '',
      notes: '',
      is_active: true,
      reason: '',
      lock_version: 1,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(tool: Tool) {
    setEditing(tool);
    form.setData({
      code: tool.code,
      name: { en: namePart(tool.name, 'en'), ar: namePart(tool.name, 'ar') },
      description: { en: namePart(tool.description || null, 'en'), ar: namePart(tool.description || null, 'ar') },
      tool_category_id: tool.tool_category_id,
      serial_number: tool.serial_number || '',
      quantity: tool.quantity,
      branch_id: tool.branch_id || '',
      location_note: tool.location_note || '',
      notes: tool.notes || '',
      is_active: tool.is_active,
      reason: '',
      lock_version: tool.lock_version,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      serial_number: data.serial_number || null,
      branch_id: data.branch_id || null,
      location_note: data.location_note || null,
      notes: data.notes || null,
      reason: data.reason || null,
    }));

    const onSuccess = () => {
      setShowForm(false);
      setTableReloadToken((value) => value + 1);
    };

    if (editing) {
      form.put(`/equipment/tools/${editing.id}`, { preserveScroll: true, onSuccess });
      return;
    }

    form.post('/equipment/tools', { preserveScroll: true, onSuccess });
  }

  function deleteTool(tool: Tool) {
    if (!confirm(pageDict.confirmDelete)) return;
    router.delete(`/equipment/tools/${tool.id}`, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  function openCustodyAction(tool: Tool, type: CustodyActionType) {
    custodyForm.setData({
      custodian_employee_id: '',
      branch_id: tool.branch_id || '',
      status: 'available',
      reason: '',
    });
    custodyForm.clearErrors();
    setCustodyAction({ tool, type });
  }

  function submitCustodyAction(event: FormEvent) {
    event.preventDefault();
    if (!custodyAction) return;
    const { tool, type } = custodyAction;
    const onSuccess = () => {
      setCustodyAction(null);
      setTableReloadToken((value) => value + 1);
    };

    if (type === 'issue') {
      custodyForm.transform((data) => ({
        custodian_employee_id: data.custodian_employee_id,
        branch_id: data.branch_id || null,
        reason: data.reason || null,
      }));
      custodyForm.post(`/equipment/tools/${tool.id}/issue`, { preserveScroll: true, onSuccess });
      return;
    }

    if (type === 'return') {
      custodyForm.transform((data) => ({ reason: data.reason || null }));
      custodyForm.post(`/equipment/tools/${tool.id}/return`, { preserveScroll: true, onSuccess });
      return;
    }

    if (type === 'transfer') {
      custodyForm.transform((data) => ({
        branch_id: data.branch_id || null,
        custodian_employee_id: data.custodian_employee_id || null,
        reason: data.reason || null,
      }));
      custodyForm.post(`/equipment/tools/${tool.id}/transfer`, { preserveScroll: true, onSuccess });
      return;
    }

    custodyForm.transform((data) => ({ status: data.status, reason: data.reason || null }));
    custodyForm.post(`/equipment/tools/${tool.id}/status`, { preserveScroll: true, onSuccess });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'tool.code', title: pageDict.code },
    { data: 'name', name: 'tool.name', title: pageDict.item },
    { data: 'custodian', name: 'custodian', title: pageDict.custodian, orderable: false, searchable: false },
    { data: 'branch', name: 'branch', title: pageDict.branch, orderable: false, searchable: false },
    { data: 'status', name: 'tool.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    'tool.code': (value: string, _type: unknown, item: Tool) => (
      <div>
        <div className="font-mono text-sm font-bold">{value}</div>
        {item.serial_number ? <div className="mt-1 text-xs text-[var(--text-muted)]">{item.serial_number}</div> : null}
      </div>
    ),
    'tool.name': (_value: unknown, _type: unknown, item: Tool) => (
      <div>
        <div className="font-semibold">{namePart(item.name, activeLocale)}</div>
        <div className="mt-1 text-xs text-[var(--text-muted)]">{item.category ? `${item.category.code} - ${namePart(item.category.name, activeLocale)}` : ''}</div>
      </div>
    ),
    custodian: (_value: unknown, _type: unknown, item: Tool) => (
      <span className="text-sm">{item.custodian ? `${item.custodian.code} - ${namePart(item.custodian.name, activeLocale)}` : pageDict.noCustodian}</span>
    ),
    branch: (_value: unknown, _type: unknown, item: Tool) => (
      <div>
        <div className="text-sm">{item.branch ? `${item.branch.code} - ${namePart(item.branch.name, activeLocale)}` : ''}</div>
        {item.location_note ? <div className="mt-1 text-xs text-[var(--text-muted)]">{item.location_note}</div> : null}
      </div>
    ),
    'tool.status': (value: string) => (
      <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>
    ),
    actions: (_value: unknown, _type: unknown, item: Tool) => (
      <div className="flex flex-wrap justify-end gap-2">
        {can('equipment.edit') && item.status === 'available' ? <Button variant="secondary" onClick={() => openCustodyAction(item, 'issue')}>{pageDict.issue}</Button> : null}
        {can('equipment.edit') && item.status === 'issued' ? <Button variant="secondary" onClick={() => openCustodyAction(item, 'return')}>{pageDict.returnToStock}</Button> : null}
        {can('equipment.edit') && item.status !== 'retired' ? <Button variant="secondary" onClick={() => openCustodyAction(item, 'transfer')}>{pageDict.transfer}</Button> : null}
        {can('equipment.edit') && item.status !== 'retired' ? <Button variant="secondary" onClick={() => openCustodyAction(item, 'status')}>{pageDict.changeStatus}</Button> : null}
        {can('equipment.edit') ? <Button variant="secondary" onClick={() => openEdit(item)}>{pageDict.edit}</Button> : null}
        {can('equipment.delete') && item.status !== 'issued' ? <Button variant="danger" onClick={() => deleteTool(item)}>{pageDict.delete}</Button> : null}
      </div>
    ),
  }), [activeLocale, can, pageDict]);

  const tableFilters = useMemo(() => ({
    status,
    tool_category_id: categoryId,
    branch_id: branchId,
  }), [status, categoryId, branchId]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} /></div>
      <div className="w-52"><SearchableSelect options={[{ value: '', label: pageDict.allCategories }, ...categoryOptions]} value={categoryId || null} onChange={(value) => setCategoryId(value || '')} /></div>
      <div className="w-52"><SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} /></div>
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
    </div>
  );

  return (
    <AppLayout active="equipment.tools.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('equipment.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
                <p className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.formHint}</p>
              </div>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.code}
                <input className="input mt-1" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
                {form.errors.code ? <p className="mt-1 text-xs text-rose-600">{form.errors.code}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameEn}
                <input className="input mt-1" value={form.data.name.en} onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameAr}
                <input className="input mt-1" value={form.data.name.ar} onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })} />
              </label>
              <SearchableSelect options={categoryOptions} value={form.data.tool_category_id || null} onChange={(value) => form.setData('tool_category_id', value || '')} label={pageDict.category} required error={form.errors.tool_category_id} />
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.serialNumber}
                <input className="input mt-1" value={form.data.serial_number} onChange={(event) => form.setData('serial_number', event.target.value)} />
                {form.errors.serial_number ? <p className="mt-1 text-xs text-rose-600">{form.errors.serial_number}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.quantity}
                <input className="input mt-1" type="number" min={1} value={form.data.quantity} onChange={(event) => form.setData('quantity', Number(event.target.value) || 1)} />
              </label>
              <SearchableSelect options={branchOptions} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} error={form.errors.branch_id} />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.locationNote}
                <input className="input mt-1" value={form.data.location_note} onChange={(event) => form.setData('location_note', event.target.value)} />
              </label>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.descriptionEn}
                <textarea className="input mt-1 min-h-20" value={form.data.description.en} onChange={(event) => form.setData('description', { ...form.data.description, en: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.descriptionAr}
                <textarea className="input mt-1 min-h-20" value={form.data.description.ar} onChange={(event) => form.setData('description', { ...form.data.description, ar: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.notes}
                <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
              </label>
            </div>

            <label className="flex items-center gap-2 text-xs font-bold uppercase text-[var(--text-secondary)]">
              <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
              {pageDict.active}
            </label>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing}>{editing ? pageDict.update : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          key={tableResetToken}
          ajaxUrl="/equipment/tools/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={search}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="tools-data-table"
        />
      </Card>

      <Modal
        isOpen={Boolean(custodyAction)}
        onClose={() => setCustodyAction(null)}
        title={
          (custodyAction?.type === 'issue' ? pageDict.issueTitle : null) ||
          (custodyAction?.type === 'return' ? pageDict.returnTitle : null) ||
          (custodyAction?.type === 'transfer' ? pageDict.transferTitle : null) ||
          (custodyAction?.type === 'status' ? pageDict.statusTitle : null) ||
          ''
        }
        closeLabel={pageDict.cancel}
        size="md"
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setCustodyAction(null)}>{pageDict.cancel}</Button>
            <Button type="submit" form="tool-custody-form" disabled={custodyForm.processing}>{pageDict.confirm}</Button>
          </>
        }
      >
            <form id="tool-custody-form" onSubmit={submitCustodyAction} className="space-y-4">
              {custodyAction?.type === 'issue' ? (
                <SearchableSelect options={employeeOptions} value={custodyForm.data.custodian_employee_id || null} onChange={(value) => custodyForm.setData('custodian_employee_id', value || '')} label={pageDict.custodianEmployee} required error={custodyForm.errors.custodian_employee_id} />
              ) : null}

              {custodyAction?.type === 'issue' || custodyAction?.type === 'transfer' ? (
                <SearchableSelect options={branchOptions} value={custodyForm.data.branch_id || null} onChange={(value) => custodyForm.setData('branch_id', value || '')} label={pageDict.targetBranch} error={custodyForm.errors.branch_id} />
              ) : null}

              {custodyAction?.type === 'transfer' ? (
                <SearchableSelect options={employeeOptions} value={custodyForm.data.custodian_employee_id || null} onChange={(value) => custodyForm.setData('custodian_employee_id', value || '')} label={pageDict.targetCustodian} error={custodyForm.errors.custodian_employee_id} />
              ) : null}

              {custodyAction?.type === 'status' ? (
                <SearchableSelect options={statusChangeOptions} value={custodyForm.data.status} onChange={(value) => custodyForm.setData('status', value || 'available')} label={pageDict.newStatus} required error={custodyForm.errors.status} />
              ) : null}

              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {pageDict.reason}
                <textarea className="input mt-1 min-h-20" value={custodyForm.data.reason} onChange={(event) => custodyForm.setData('reason', event.target.value)} />
              </label>

            </form>
      </Modal>
    </AppLayout>
  );
}
