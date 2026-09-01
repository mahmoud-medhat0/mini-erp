import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, Modal, PageHeader, PaginationControls, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, ProjectRow, ProjectStatus, SharedPageProps } from '../../Types';

type PaginatedData<T> = {
  data: T[];
  total: number;
  links: PaginationLink[];
};

type Props = SharedPageProps & {
  projects: PaginatedData<ProjectRow>;
  filters: {
    search?: string;
    status?: string;
    is_billable?: string;
  };
};

export default function ProjectsIndex({ locale, projects, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.projects;
  const accDict = dict.app.accounting;
  const auditDict = dict.app.audit;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingProject, setEditingProject] = useState<ProjectRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');

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
        onSuccess: () => setShowModal(false),
      });
      return;
    }

    form.post('/projects', {
      preserveScroll: true,
      onSuccess: () => setShowModal(false),
    });
  }

  function handleDelete(project: ProjectRow) {
    const projectName = getLocalizedName(project.name, locale) || project.code;
    if (window.confirm(pageDict.confirmDeleteProject.replace('{name}', projectName))) {
      router.delete(`/projects/${project.id}`, { preserveScroll: true });
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

      {projects.data.length === 0 ? (
        <EmptyState
          title={pageDict.noProjects}
          description={pageDict.noProjectsDescription}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.nameEn}</th>
                <th className={tableClasses.th}>{pageDict.descriptionLabel}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.startDate}</th>
                <th className={tableClasses.th}>{pageDict.endDate}</th>
                <th className={tableClasses.th}>{pageDict.billable}</th>
                <th className={tableClasses.th}>{pageDict.active}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {projects.data.map((project) => (
                <tr key={project.id} className="hover:bg-[var(--background)]/60 transition-colors">
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{project.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{getLocalizedName(project.name, locale)}</td>
                  <td className={`${tableClasses.td} text-xs text-[var(--text-secondary)] max-w-xs truncate`}>
                    {project.description || accDict.notAvailable}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={getStatusTone(project.status)}>
                      {getStatusLabel(project.status)}
                    </StatusBadge>
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {project.start_date || accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {project.end_date || accDict.notAvailable}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={project.is_billable ? 'ok' : 'muted'}>
                      {project.is_billable ? pageDict.billableYes : pageDict.billableNo}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={project.is_active ? 'ok' : 'muted'}>
                      {project.is_active ? pageDict.active : pageDict.inactive}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-3">
                      {can('projects.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(project)}
                          title={pageDict.edit}
                          aria-label={pageDict.edit}
                          className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                        >
                          {pageDict.edit}
                        </button>
                      ) : null}
                      {can('projects.delete') ? (
                        <button
                          type="button"
                          onClick={() => handleDelete(project)}
                          title={pageDict.delete}
                          aria-label={pageDict.delete}
                          className="text-xs font-bold text-red-500 hover:underline cursor-pointer"
                        >
                          {pageDict.delete}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination Controls */}
      <PaginationControls
        links={projects.links}
        total={projects.total}
        totalLabel={auditDict.totalRecords}
      />

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
