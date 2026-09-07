import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type BranchOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
};

type LocationRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  branch_id?: string | null;
  branch?: BranchOption | null;
  is_active: boolean;
  lock_version: number;
  assets_count?: number;
};

type LocationsProps = SharedPageProps & {
  locations?: LocationRow[];
  branches: BranchOption[];
  filters: {
    search?: string;
    branch_id?: string;
    status?: string;
  };
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
  };
};

type LocationForm = {
  code: string;
  name: {
    en: string;
    ar: string;
  };
  branch_id: string;
  is_active: boolean;
  lock_version: number;
};

function namePart(name: Record<string, string> | string | null | undefined, locale: 'en' | 'ar'): string {
  if (!name) return '';
  if (typeof name === 'string') return name;

  return name[locale] || name.en || name.ar || '';
}

export default function FixedAssetLocationsIndex({ locale, branches = [], filters, can }: LocationsProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;

  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [status, setStatus] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [editingLocation, setEditingLocation] = useState<LocationRow | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const form = useForm<LocationForm>({
    code: '',
    name: { en: '', ar: '' },
    branch_id: '',
    is_active: true,
    lock_version: 1,
  });

  const branchOptions = branches.map((branch) => ({
    value: branch.id,
    label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
  }));
  const statusOptions = [
    { value: '', label: appDict.allStatuses },
    { value: 'active', label: appDict.active },
    { value: 'inactive', label: appDict.inactive },
  ];
  const activeFilterCount = [filters.search, branchId, status].filter(Boolean).length;
  const formErrors = form.errors as Record<string, string | undefined>;
  const locationSubmitLabel = form.processing ? appDict.saving : appDict.save;

  function clearFilters() {
    setBranchId('');
    setStatus('');
    router.get('/fixed-asset-locations', {}, { preserveState: true, preserveScroll: true });
  }

  function openCreateForm() {
    setEditingLocation(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      branch_id: '',
      is_active: true,
      lock_version: 1,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEditForm(location: LocationRow) {
    setEditingLocation(location);
    form.setData({
      code: location.code,
      name: {
        en: namePart(location.name, 'en'),
        ar: namePart(location.name, 'ar'),
      },
      branch_id: location.branch_id || '',
      is_active: location.is_active,
      lock_version: location.lock_version,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    if (editingLocation) {
      form.put(`/fixed-asset-locations/${editingLocation.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowForm(false);
          setReloadToken((value) => value + 1);
        },
      });
      return;
    }

    form.post('/fixed-asset-locations', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setReloadToken((value) => value + 1);
      },
    });
  }

  function deleteLocation(location: LocationRow) {
    if (!confirm(appDict.confirmDeleteAssetLocation)) return;

    const stopListening = router.on('finish', () => {
      stopListening();
      setReloadToken((value) => value + 1);
    });
    router.delete(`/fixed-asset-locations/${location.id}`, { preserveScroll: true });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: appDict.code },
    { data: 'name_text', name: 'name_text', title: appDict.name, orderable: false },
    { data: 'branch_label', name: 'branch_label', title: appDict.branch, orderable: false, searchable: false },
    { data: 'assets_count', name: 'assets_count', title: appDict.assetCount, searchable: false },
    { data: 'is_active', name: 'is_active', title: appDict.status, searchable: false },
    { data: 'actions', name: 'actions', title: appDict.actions, orderable: false, searchable: false },
  ], [appDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    code: (value: any) => <span className="font-mono font-semibold">{value}</span>,
    name_text: (_value: any, _type: any, location: LocationRow) => getLocalizedName(location.name, locale),
    branch_label: (_value: any, _type: any, location: LocationRow) => (
      <span>{location.branch ? `${location.branch.code} - ${getLocalizedName(location.branch.name, locale)}` : appDict.notAssigned}</span>
    ),
    assets_count: (value: any) => Number(value || 0),
    is_active: (_value: any, _type: any, location: LocationRow) => (
      <StatusBadge tone={location.is_active ? 'ok' : 'muted'}>
        {location.is_active ? appDict.active : appDict.inactive}
      </StatusBadge>
    ),
    actions: (_value: any, _type: any, location: LocationRow) => (
      <div className="flex items-center gap-3">
        {can.edit && (
          <button
            type="button"
            onClick={() => openEditForm(location)}
            title={appDict.edit}
            aria-label={appDict.edit}
            className="text-xs font-medium text-indigo-600 hover:text-indigo-900"
          >
            {appDict.edit}
          </button>
        )}
        {can.delete && (location.assets_count || 0) === 0 && (
          <button
            type="button"
            onClick={() => deleteLocation(location)}
            title={appDict.delete}
            aria-label={appDict.delete}
            className="text-xs font-medium text-rose-600 hover:text-rose-900"
          >
            {appDict.delete}
          </button>
        )}
      </div>
    ),
  }), [appDict, can, locale]);

  const tableFilters = useMemo(() => ({ branch_id: branchId, status }), [branchId, status]);
  const toolbar = (
    <div className="flex flex-wrap items-center gap-3">
      <SearchableSelect options={[{ value: '', label: appDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={appDict.branch} />
      <SearchableSelect options={statusOptions} value={status || null} onChange={(value) => setStatus(value || '')} label={appDict.status} />
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{appDict.clearFilters}</Button>
    </div>
  );

  return (
    <AppLayout active="fixed-asset-locations.index">
      <Head title={`${appDict.fixedAssetLocations} - ${appDict.appName}`} />

      <div className="space-y-6">
        <PageHeader
          title={appDict.fixedAssetLocations}
          description={appDict.fixedAssetLocationsDescription}
          actions={
            can.create ? (
              <button
                type="button"
                onClick={openCreateForm}
                title={appDict.createAssetLocation}
                aria-label={appDict.createAssetLocation}
                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
              >
                {appDict.createAssetLocation}
              </button>
            ) : null
          }
        />

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/fixed-asset-locations/data"
            columns={columns}
            filters={tableFilters}
            initialSearch={filters.search || ''}
            locale={locale}
            order={[[0, 'asc']]}
            pageLength={25}
            reloadToken={reloadToken}
            slots={slots}
            tableId="fixed-asset-locations-data-table"
            toolbar={toolbar}
          />
        </Card>
      </div>

      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
          <div className="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {editingLocation ? appDict.editAssetLocation : appDict.createAssetLocation}
            </h3>

            <form onSubmit={submit} className="mt-4 space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">{appDict.code}</label>
                  <input
                    type="text"
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value)}
                    className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm font-mono"
                    required
                  />
                  {form.errors.code && <p className="mt-1 text-xs text-rose-600">{form.errors.code}</p>}
                </div>

                <SearchableSelect
                  label={appDict.branch}
                  options={branchOptions}
                  value={form.data.branch_id || null}
                  onChange={(value) => form.setData('branch_id', value || '')}
                  placeholder={appDict.notAssigned}
                  error={form.errors.branch_id}
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">{appDict.englishName}</label>
                  <input
                    type="text"
                    value={form.data.name.en}
                    onChange={(e) => form.setData('name', { ...form.data.name, en: e.target.value })}
                    className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                    required
                  />
                  {form.errors['name.en'] && <p className="mt-1 text-xs text-rose-600">{form.errors['name.en']}</p>}
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">{appDict.arabicName}</label>
                  <input
                    type="text"
                    value={form.data.name.ar}
                    onChange={(e) => form.setData('name', { ...form.data.name, ar: e.target.value })}
                    className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                    required
                  />
                  {form.errors['name.ar'] && <p className="mt-1 text-xs text-rose-600">{form.errors['name.ar']}</p>}
                </div>
              </div>

              <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                  type="checkbox"
                  checked={form.data.is_active}
                  onChange={(e) => form.setData('is_active', e.target.checked)}
                  className="rounded border-slate-300 text-indigo-600"
                />
                {appDict.active}
              </label>

              {formErrors.location && <p className="text-xs text-rose-600">{formErrors.location}</p>}

              <div className="flex justify-end space-x-2 rtl:space-x-reverse pt-2">
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  title={appDict.cancel}
                  aria-label={appDict.cancel}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  title={locationSubmitLabel}
                  aria-label={locationSubmitLabel}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {locationSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
