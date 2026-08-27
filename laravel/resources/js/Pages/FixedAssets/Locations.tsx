import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
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
  locations: LocationRow[];
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

export default function FixedAssetLocationsIndex({ locale, locations = [], branches = [], filters, can }: LocationsProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;

  const [search, setSearch] = useState(filters.search || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [status, setStatus] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [editingLocation, setEditingLocation] = useState<LocationRow | null>(null);

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
    { value: 'active', label: appDict.active },
    { value: 'inactive', label: appDict.inactive },
  ];
  const activeFilterCount = [search, branchId, status].filter(Boolean).length;
  const formErrors = form.errors as Record<string, string | undefined>;

  function applyFilters() {
    router.get('/fixed-asset-locations', { search, branch_id: branchId, status }, { preserveState: true, preserveScroll: true });
  }

  function clearFilters() {
    setSearch('');
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
        onSuccess: () => setShowForm(false),
      });
      return;
    }

    form.post('/fixed-asset-locations', {
      preserveScroll: true,
      onSuccess: () => setShowForm(false),
    });
  }

  function deleteLocation(location: LocationRow) {
    if (!confirm(appDict.confirmDeleteAssetLocation)) return;

    router.delete(`/fixed-asset-locations/${location.id}`, { preserveScroll: true });
  }

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
                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
              >
                {appDict.createAssetLocation}
              </button>
            ) : null
          }
        />

        <Card>
          <div className="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-4 items-center">
            <input
              type="text"
              placeholder={appDict.searchAssetLocationsPlaceholder}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
            />
            <SearchableSelect options={[{ value: '', label: appDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={appDict.branch} />
            <SearchableSelect options={[{ value: '', label: appDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={appDict.status} />
            <Button onClick={applyFilters}>{appDict.filter}</Button>
            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{appDict.clearFilters}</Button>
          </div>

          {locations.length === 0 ? (
            <EmptyState title={appDict.noAssetLocations} description={appDict.noAssetLocationsDescription} />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{appDict.code}</th>
                    <th className={tableClasses.th}>{appDict.name}</th>
                    <th className={tableClasses.th}>{appDict.branch}</th>
                    <th className={tableClasses.th}>{appDict.assetCount}</th>
                    <th className={tableClasses.th}>{appDict.status}</th>
                    <th className={tableClasses.th}>{appDict.actions}</th>
                  </tr>
                </thead>
                <tbody>
                  {locations.map((location) => (
                    <tr key={location.id}>
                      <td className={`${tableClasses.td} font-mono font-semibold`}>{location.code}</td>
                      <td className={tableClasses.td}>{getLocalizedName(location.name, locale)}</td>
                      <td className={tableClasses.td}>
                        {location.branch ? `${location.branch.code} - ${getLocalizedName(location.branch.name, locale)}` : appDict.notAssigned}
                      </td>
                      <td className={tableClasses.td}>{location.assets_count || 0}</td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={location.is_active ? 'ok' : 'muted'}>
                          {location.is_active ? appDict.active : appDict.inactive}
                        </StatusBadge>
                      </td>
                      <td className={tableClasses.td}>
                        <div className="flex items-center gap-3">
                          {can.edit && (
                            <button
                              type="button"
                              onClick={() => openEditForm(location)}
                              className="text-xs font-medium text-indigo-600 hover:text-indigo-900"
                            >
                              {appDict.edit}
                            </button>
                          )}
                          {can.delete && (location.assets_count || 0) === 0 && (
                            <button
                              type="button"
                              onClick={() => deleteLocation(location)}
                              className="text-xs font-medium text-rose-600 hover:text-rose-900"
                            >
                              {appDict.delete}
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
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
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={form.processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {appDict.save}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
