import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type PartnerRow = {
  id: string;
  code: string;
  name: TranslatedName;
  share_bps: number;
  share_percent: number;
  status: string;
  notes: string | null;
  lock_version: number;
};

type Props = SharedPageProps & {
  can: { create: boolean; edit: boolean; delete: boolean };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

export default function PartnersIndex({ locale, can }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.partners;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const canUse = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<PartnerRow | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [reloadToken, setReloadToken] = useState(0);

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    share_percent: '',
    status: 'active',
    notes: '',
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
    form.setData({ code: '', name: { en: '', ar: '' }, share_percent: '', status: 'active', notes: '', lock_version: 1 });
    form.clearErrors();
    setShowModal(true);
  }

  function openEdit(partner: PartnerRow) {
    setEditing(partner);
    form.setData({
      code: partner.code,
      name: { en: namePart(partner.name, 'en'), ar: namePart(partner.name, 'ar') },
      share_percent: String(partner.share_percent),
      status: partner.status,
      notes: partner.notes || '',
      lock_version: partner.lock_version,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    const payload = {
      code: form.data.code,
      name: form.data.name,
      share_bps: Math.round(Number(form.data.share_percent || 0) * 100),
      status: form.data.status,
      notes: form.data.notes || null,
      lock_version: form.data.lock_version,
    };

    if (editing) {
      router.put(`/partners/${editing.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          setReloadToken((value) => value + 1);
        },
      });
      return;
    }

    router.post('/partners', payload, {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        setReloadToken((value) => value + 1);
      },
    });
  }

  function handleDelete(partner: PartnerRow) {
    if (!confirm(pageDict.confirmDelete)) return;
    router.delete(`/partners/${partner.id}`, {
      preserveScroll: true,
      onSuccess: () => setReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'partner.code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.nameEn },
    { data: 'share_percent', name: 'share_percent', title: pageDict.sharePercent, orderable: false, className: 'text-end' },
    { data: 'status', name: 'partner.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    name_text: (_value: unknown, _type: unknown, row: PartnerRow) => namePart(row.name, activeLocale),
    share_percent: (value: number) => <span>{value}%</span>,
    'partner.status': (value: string) => (
      <StatusBadge tone={value === 'active' ? 'ok' : 'muted'}>{value === 'active' ? pageDict.active : pageDict.inactive}</StatusBadge>
    ),
    actions: (_value: unknown, _type: unknown, row: PartnerRow) => (
      <div className="flex flex-wrap justify-end gap-2">
        {canUse('partners.edit') ? <Button variant="secondary" onClick={() => openEdit(row)}>{pageDict.edit}</Button> : null}
        {canUse('partners.delete') ? <Button variant="danger" onClick={() => handleDelete(row)}>{pageDict.delete}</Button> : null}
      </div>
    ),
  }), [activeLocale, canUse, pageDict]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={statusOptions} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} /></div>
    </div>
  );

  return (
    <AppLayout active="partners.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canUse('partners.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      {showModal ? (
        <Card className="mb-5 p-5">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
              <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.back}</Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-4">
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
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.sharePercent}
                <input className="input mt-1" type="number" step="0.01" min="0" max="100" value={form.data.share_percent} onChange={(event) => form.setData('share_percent', event.target.value)} />
              </label>
            </div>

            <label className="flex items-center gap-2 text-xs font-bold uppercase text-[var(--text-secondary)]">
              <input type="checkbox" checked={form.data.status === 'active'} onChange={(event) => form.setData('status', event.target.checked ? 'active' : 'inactive')} />
              {pageDict.active}
            </label>

            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.notes}
              <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
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
          ajaxUrl="/partners/data"
          columns={columns}
          filters={extraFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={reloadToken}
          slots={slots}
          tableId="partners-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
