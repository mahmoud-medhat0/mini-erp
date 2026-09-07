import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, Modal, PageHeader, SearchableSelect, StatusBadge, ToggleSwitch } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { ProjectRow, ProjectStatus, SharedPageProps } from '../../Types';

type Props = SharedPageProps & {
  filters: {
    search?: string;
    status?: string;
    is_billable?: string;
  };
};

export default function ProjectsIndex({ locale, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.projects;
  const accDict = dict.app.accounting;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingProject, setEditingProject] = useState<ProjectRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');
  const [reloadToken, setReloadToken] = useState(0);

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    description: '',
    status: 'active' as ProjectStatus,
    start_date: '' as string | null,
    end_date: '' as string | null,
    is_billable: false,
    is_active: true,
    lock_version: 1,
  });

  const statusOptions = useMemo(
    () => [
      { value: 'active' as const, label: pageDict.statusActive },
      { value: 'on_hold' as const, label: pageDict.statusOnHold },
      { value: 'completed' as const, label: pageDict.statusCompleted },
      { value: 'cancelled' as const, label: pageDict.statusCancelled },
    ],
    [pageDict.statusActive, pageDict.statusCancelled, pageDict.statusCompleted, pageDict.statusOnHold],
  );

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allStatuses },
      ...statusOptions,
    ],
    [pageDict.allStatuses, statusOptions],
  );

  const billableFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allBillable },
      { value: 'true', label: pageDict.billableYes },
      { value: 'false', label: pageDict.billableNo },
    ],
    [pageDict.allBillable, pageDict.billableNo, pageDict.billableYes],
  );

  const activeFilterCount = [search, filters.status, filters.is_billable].filter(Boolean).length;

  function applyFilters(overrides: Partial<typeof filters> = {}) {
    const current = {
      search,
      status: filters.status || '',
      is_billable: filters.is_billable || '',
      ...overrides,
    };
    router.get('/projects', current, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    router.get('/projects', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreateModal() {
    setEditingProject(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      description: '',
      status: 'active',
      start_date: null,
      end_date: null,
      is_billable: false,
      is_active: true,
      lock_version: 1,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function openEditModal(project: ProjectRow) {
    setEditingProject(project);
    form.setData({
      code: project.code,
      name: {
        en: typeof project.name === 'object' && project.name ? project.name.en || '' : String(project.name || ''),
        ar: typeof project.name === 'object' && project.name ? project.name.ar || '' : String(project.name || ''),
      },
      description: project.description || '',
      status: project.status,
      start_date: project.start_date || null,
      end_date: project.end_date || null,
      is_billable: project.is_billable,
      is_active: project.is_active,
      lock_version: project.lock_version,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();

    if (editingProject) {
      form.patch(`/projects/${editingProject.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          setReloadToken((value) => value + 1);
        },
      });
      return;
    }

    form.post('/projects', {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        setReloadToken((value) => value + 1);
      },
    });
  }

  function handleDelete(project: ProjectRow) {
    const projectName = getLocalizedName(project.name, locale) || project.code;
    if (window.confirm(pageDict.confirmDeleteProject.replace('{name}', projectName))) {
      router.delete(`/projects/${project.id}`, {
        preserveScroll: true,
        onSuccess: () => setReloadToken((value) => value + 1),
      });
    }
  }

  function getStatusTone(status: ProjectStatus): 'ok' | 'warning' | 'info' | 'muted' {
    switch (status) {
      case 'active':
        return 'ok';
      case 'on_hold':
        return 'warning';
      case 'completed':
        return 'info';
      case 'cancelled':
      default:
        return 'muted';
    }
  }

  function getStatusLabel(status: ProjectStatus): string {
    switch (status) {
      case 'active':
        return pageDict.statusActive;
      case 'on_hold':
        return pageDict.statusOnHold;
      case 'completed':
        return pageDict.statusCompleted;
      case 'cancelled':
        return pageDict.statusCancelled;
      default:
        return status;
    }
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: pageDict.code },
    { data: 'name', name: 'name', title: pageDict.nameEn },
    { data: 'description', name: 'description', title: pageDict.descriptionLabel },
    { data: 'status', name: 'status', title: pageDict.status },
    { data: 'start_date', name: 'start_date', title: pageDict.startDate },
    { data: 'end_date', name: 'end_date', title: pageDict.endDate },
    { data: 'is_billable', name: 'is_billable', title: pageDict.billable },
    { data: 'is_active', name: 'is_active', title: pageDict.active },
    { data: 'id', name: 'id', title: pageDict.actions, orderable: false, searchable: false },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    code: (data: string): ReactElement => <span className="font-mono text-xs font-bold">{data}</span>,
    name: (data: ProjectRow['name']): ReactElement => <span className="font-semibold">{getLocalizedName(data, locale)}</span>,
    description: (data: string | null): ReactElement => (
      <span className="block max-w-xs truncate text-xs text-[var(--text-secondary)]">{data || accDict.notAvailable}</span>
    ),
    status: (data: ProjectStatus): ReactElement => (
      <StatusBadge tone={getStatusTone(data)}>{getStatusLabel(data)}</StatusBadge>
    ),
    start_date: (data: string | null): ReactElement => <span className="font-mono text-xs">{data || accDict.notAvailable}</span>,
    end_date: (data: string | null): ReactElement => <span className="font-mono text-xs">{data || accDict.notAvailable}</span>,
    is_billable: (data: boolean): ReactElement => (
      <StatusBadge tone={data ? 'ok' : 'muted'}>{data ? pageDict.billableYes : pageDict.billableNo}</StatusBadge>
    ),
    is_active: (data: boolean): ReactElement => (
      <StatusBadge tone={data ? 'ok' : 'muted'}>{data ? pageDict.active : pageDict.inactive}</StatusBadge>
    ),
    id: (_data: string, _type: unknown, row: ProjectRow): ReactElement => (
      <div className="flex flex-wrap items-center gap-3">
        {can('projects.edit') ? (
          <button type="button" onClick={() => openEditModal(row)} title={pageDict.edit} aria-label={pageDict.edit} className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer">
            {pageDict.edit}
          </button>
        ) : null}
        {can('projects.delete') ? (
          <button type="button" onClick={() => handleDelete(row)} title={pageDict.delete} aria-label={pageDict.delete} className="text-xs font-bold text-red-500 hover:underline cursor-pointer">
            {pageDict.delete}
          </button>
        ) : null}
      </div>
    ),
  } as unknown as DataTableSlots), [accDict.notAvailable, can, locale, pageDict]);

  return (
    <AppLayout active="projects.index" pagination="manual">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          can('projects.create') ? (
            <Button
              onClick={openCreateModal}
              title={pageDict.createProject}
              aria-label={pageDict.createProject}
            >
              {pageDict.createProject}
            </Button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={pageDict.search}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                applyFilters({ search });
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect
            options={statusFilterOptions}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-44"
            isSearchable={false}
          />
          <SearchableSelect
            options={billableFilterOptions}
            value={filters.is_billable || ''}
            onChange={(value) => applyFilters({ is_billable: value || '' })}
            className="w-44"
            isSearchable={false}
          />
          <Button
            variant="secondary"
            onClick={clearFilters}
            disabled={activeFilterCount === 0}
            title={pageDict.clearFilter}
            aria-label={pageDict.clearFilter}
          >
            {pageDict.clearFilter}
          </Button>
        </div>
      </Card>

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/projects/data"
          columns={columns}
          filters={{
            project_search: filters.search || '',
            status: filters.status || '',
            is_billable: filters.is_billable || '',
          }}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="projects-table"
        />
      </Card>

      {/* Create / Edit Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editingProject ? pageDict.editProject : pageDict.createProject}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.code} *
              </label>
              <input
                type="text"
                value={form.data.code}
                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                required
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono uppercase text-[var(--text-primary)]"
              />
              {form.errors.code ? <p className="text-xs text-red-500 mt-1">{form.errors.code}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.status} *
              </label>
              <SearchableSelect<ProjectStatus>
                options={statusOptions}
                value={form.data.status}
                onChange={(val) => form.setData('status', (val || 'active') as ProjectStatus)}
                isClearable={false}
                isSearchable={false}
                required
              />
              {form.errors.status ? <p className="text-xs text-red-500 mt-1">{form.errors.status}</p> : null}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.nameEn} *
              </label>
              <input
                type="text"
                value={form.data.name.en}
                onChange={(e) => form.setData('name', { ...form.data.name, en: e.target.value })}
                required
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold"
              />
              {form.errors['name.en'] ? <p className="text-xs text-red-500 mt-1">{form.errors['name.en']}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.nameAr}
              </label>
              <input
                type="text"
                value={form.data.name.ar}
                onChange={(e) => form.setData('name', { ...form.data.name, ar: e.target.value })}
                dir="rtl"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
              />
              {form.errors['name.ar'] ? <p className="text-xs text-red-500 mt-1">{form.errors['name.ar']}</p> : null}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <DatePicker
                label={pageDict.startDate}
                value={form.data.start_date}
                onChange={(date) => form.setData('start_date', date)}
                error={form.errors.start_date}
              />
            </div>

            <div>
              <DatePicker
                label={pageDict.endDate}
                value={form.data.end_date}
                onChange={(date) => form.setData('end_date', date)}
                minDate={form.data.start_date || undefined}
                error={form.errors.end_date}
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {pageDict.descriptionLabel}
            </label>
            <textarea
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              placeholder={pageDict.descriptionPlaceholder}
              rows={3}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)]"
            />
            {form.errors.description ? <p className="text-xs text-red-500 mt-1">{form.errors.description}</p> : null}
          </div>

          <div className="flex flex-wrap items-center justify-between gap-4 pt-2">
            <ToggleSwitch
              checked={form.data.is_billable}
              onChange={(val) => form.setData('is_billable', val)}
              label={pageDict.isBillable}
              description={pageDict.isBillableDescription}
            />

            <ToggleSwitch
              checked={form.data.is_active}
              onChange={(val) => form.setData('is_active', val)}
              label={pageDict.active}
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowModal(false)}
              title={pageDict.cancel}
              aria-label={pageDict.cancel}
            >
              {pageDict.cancel}
            </Button>
            <Button
              type="submit"
              disabled={form.processing}
              title={pageDict.save}
              aria-label={pageDict.save}
            >
              {pageDict.save}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
