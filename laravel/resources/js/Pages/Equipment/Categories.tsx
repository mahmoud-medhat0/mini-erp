import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type CategoryRow = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
  lock_version: number;
  tools_count?: number;
};

type Props = SharedPageProps & {
  categories?: CategoryRow[];
  can: { create: boolean; edit: boolean; delete: boolean };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

export default function ToolCategories({ locale, can }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.toolCategories;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const canUse = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<CategoryRow | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [reloadToken, setReloadToken] = useState(0);

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    is_active: true,
    lock_version: 1,
  });

  const statusOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'active', label: pageDict.active },
    { value: 'inactive', label: pageDict.inactive },
  ], [pageDict]);

  const extraFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  function openCreate() {
    setEditing(null);
    form.setData({ code: '', name: { en: '', ar: '' }, is_active: true, lock_version: 1 });
    form.clearErrors();
    setShowModal(true);
  }

  function openEdit(category: CategoryRow) {
    setEditing(category);
    form.setData({
      code: category.code,
      name: { en: namePart(category.name, 'en'), ar: namePart(category.name, 'ar') },
      is_active: category.is_active,
      lock_version: category.lock_version,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    const onSuccess = () => {
      setShowModal(false);
      setReloadToken((value) => value + 1);
    };

    if (editing) {
      form.put(`/equipment/categories/${editing.id}`, { preserveScroll: true, onSuccess });
      return;
    }

    form.post('/equipment/categories', { preserveScroll: true, onSuccess });
  }

  function handleDelete(category: CategoryRow) {
    if (!confirm(pageDict.confirmDelete)) return;
    router.delete(`/equipment/categories/${category.id}`, {
      preserveScroll: true,
      onSuccess: () => setReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'tool_category.code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.nameEn },
    { data: 'tools_count', name: 'tools_count', title: pageDict.toolsCount, orderable: false, searchable: false },
    { data: 'is_active', name: 'tool_category.is_active', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    name_text: (_value: unknown, _type: unknown, row: CategoryRow) => namePart(row.name, activeLocale),
    is_active: (value: boolean) => (
      <StatusBadge tone={value ? 'ok' : 'muted'}>{value ? pageDict.active : pageDict.inactive}</StatusBadge>
    ),
    actions: (_value: unknown, _type: unknown, row: CategoryRow) => (
      <div className="flex flex-wrap justify-end gap-2">
        {canUse('equipment.edit') ? <Button variant="secondary" onClick={() => openEdit(row)}>{pageDict.edit}</Button> : null}
        {canUse('equipment.delete') && (row.tools_count ?? 0) === 0 ? <Button variant="danger" onClick={() => handleDelete(row)}>{pageDict.delete}</Button> : null}
      </div>
    ),
  }), [activeLocale, canUse, pageDict]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={statusOptions} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} /></div>
    </div>
  );

  return (
    <AppLayout active="equipment.categories.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canUse('equipment.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      {showModal ? (
        <Card className="mb-5 p-5">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
              <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.back}</Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.code}
                <input className="input mt-1" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
                {form.errors.code ? <p className="mt-1 text-xs text-rose-600">{form.errors.code}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameEn}
                <input className="input mt-1" value={form.data.name.en} onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })} />
                {form.errors['name.en'] ? <p className="mt-1 text-xs text-rose-600">{form.errors['name.en']}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameAr}
                <input className="input mt-1" value={form.data.name.ar} onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })} />
              </label>
            </div>

            <label className="flex items-center gap-2 text-xs font-bold uppercase text-[var(--text-secondary)]">
              <input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
              {pageDict.active}
            </label>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing}>{form.processing ? pageDict.saving : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/equipment/categories/data"
          columns={columns}
          filters={extraFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={reloadToken}
          slots={slots}
          tableId="tool-categories-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
