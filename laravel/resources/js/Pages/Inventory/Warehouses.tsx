import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type Branch = {
  id: string;
  code: string;
  name: TranslatedName;
};

type StockLocation = {
  id: string;
  warehouse_id: string;
  code: string;
  name: TranslatedName;
  location_type: string;
  is_active: boolean;
  lock_version: number;
};

type Warehouse = {
  id: string;
  code: string;
  name: TranslatedName;
  branch_id?: string | null;
  branch?: Branch | null;
  warehouse_type: string;
  is_default: boolean;
  is_active: boolean;
  lock_version: number;
  locations?: StockLocation[];
};

type PaginatedData<T> = {
  data: T[];
  total: number;
};

type WarehousesProps = SharedPageProps & {
  warehouses: PaginatedData<Warehouse>;
  branches: Branch[];
  warehouseTypes: string[];
  locationTypes: string[];
  filters: {
    search?: string;
    status?: string;
    branch_id?: string;
  };
};

type WarehouseForm = {
  code: string;
  name: {
    en: string;
    ar: string;
  };
  branch_id: string;
  warehouse_type: string;
  is_default: boolean;
  is_active: boolean;
  lock_version: number;
};

type LocationForm = {
  warehouse_id: string;
  code: string;
  name: {
    en: string;
    ar: string;
  };
  location_type: string;
  is_active: boolean;
  lock_version: number;
};

function fieldError(errors: Partial<Record<string, string>>, key: string): string | undefined {
  return errors[key];
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (!name) return '';
  if (typeof name === 'string') return name;

  return name[locale] || name.en || name.ar || '';
}

export default function WarehousesIndex({
  locale,
  warehouses,
  branches,
  warehouseTypes,
  locationTypes,
  filters,
}: WarehousesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.warehouses;
  const actionsDict = dict.app.actions;
  const can = useCan();

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [showWarehouseForm, setShowWarehouseForm] = useState(false);
  const [editingWarehouse, setEditingWarehouse] = useState<Warehouse | null>(null);
  const [showLocationForm, setShowLocationForm] = useState(false);
  const [editingLocation, setEditingLocation] = useState<StockLocation | null>(null);

  const warehouseForm = useForm<WarehouseForm>({
    code: '',
    name: { en: '', ar: '' },
    branch_id: '',
    warehouse_type: warehouseTypes[0] || 'standard',
    is_default: false,
    is_active: true,
    lock_version: 1,
  });

  const locationForm = useForm<LocationForm>({
    warehouse_id: '',
    code: '',
    name: { en: '', ar: '' },
    location_type: locationTypes[0] || 'standard',
    is_active: true,
    lock_version: 1,
  });

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
    })),
    [branches, locale],
  );

  const warehouseTypeOptions = warehouseTypes.map((type) => ({
    value: type,
    label: pageDict.types[type as keyof typeof pageDict.types] || type,
  }));

  const locationTypeOptions = locationTypes.map((type) => ({
    value: type,
    label: pageDict.types[type as keyof typeof pageDict.types] || type,
  }));
  const activeFilterCount = [search, status, branchId].filter(Boolean).length;

  function applyFilters() {
    router.get('/inventory/warehouses', { search, status, branch_id: branchId }, { preserveState: true, preserveScroll: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setBranchId('');
    router.get('/inventory/warehouses', {}, { preserveState: true, preserveScroll: true });
  }

  function openCreateWarehouse() {
    setEditingWarehouse(null);
    warehouseForm.setData({
      code: '',
      name: { en: '', ar: '' },
      branch_id: '',
      warehouse_type: warehouseTypes[0] || 'standard',
      is_default: false,
      is_active: true,
      lock_version: 1,
    });
    warehouseForm.clearErrors();
    setShowWarehouseForm(true);
  }

  function openEditWarehouse(warehouse: Warehouse) {
    setEditingWarehouse(warehouse);
    warehouseForm.setData({
      code: warehouse.code,
      name: {
        en: namePart(warehouse.name, 'en'),
        ar: namePart(warehouse.name, 'ar'),
      },
      branch_id: warehouse.branch_id || '',
      warehouse_type: warehouse.warehouse_type,
      is_default: warehouse.is_default,
      is_active: warehouse.is_active,
      lock_version: warehouse.lock_version,
    });
    warehouseForm.clearErrors();
    setShowWarehouseForm(true);
  }

  function submitWarehouse(event: React.FormEvent) {
    event.preventDefault();
    if (editingWarehouse) {
      warehouseForm.put(`/inventory/warehouses/${editingWarehouse.id}`, {
        preserveScroll: true,
        onSuccess: () => setShowWarehouseForm(false),
      });
      return;
    }

    warehouseForm.post('/inventory/warehouses', {
      preserveScroll: true,
      onSuccess: () => setShowWarehouseForm(false),
    });
  }

  function deleteWarehouse(warehouse: Warehouse) {
    if (!confirm(pageDict.confirmDelete)) return;

    router.delete(`/inventory/warehouses/${warehouse.id}`, { preserveScroll: true });
  }

  function openCreateLocation(warehouse: Warehouse) {
    setEditingLocation(null);
    locationForm.setData({
      warehouse_id: warehouse.id,
      code: '',
      name: { en: '', ar: '' },
      location_type: locationTypes[0] || 'standard',
      is_active: true,
      lock_version: 1,
    });
    locationForm.clearErrors();
    setShowLocationForm(true);
  }

  function openEditLocation(location: StockLocation) {
    setEditingLocation(location);
    locationForm.setData({
      warehouse_id: location.warehouse_id,
      code: location.code,
      name: {
        en: namePart(location.name, 'en'),
        ar: namePart(location.name, 'ar'),
      },
      location_type: location.location_type,
      is_active: location.is_active,
      lock_version: location.lock_version,
    });
    locationForm.clearErrors();
    setShowLocationForm(true);
  }

  function submitLocation(event: React.FormEvent) {
    event.preventDefault();
    if (editingLocation) {
      locationForm.put(`/inventory/locations/${editingLocation.id}`, {
        preserveScroll: true,
        onSuccess: () => setShowLocationForm(false),
      });
      return;
    }

    locationForm.post('/inventory/locations', {
      preserveScroll: true,
      onSuccess: () => setShowLocationForm(false),
    });
  }

  return (
    <AppLayout active="warehouses.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          can('inventory.create') ? (
            <Button onClick={openCreateWarehouse}>
              <svg className="me-2 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              {pageDict.createWarehouse}
            </Button>
          ) : null
        }
      />

      <div className="space-y-5">
        <Card className="p-4">
          <div className="grid gap-3 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto] lg:items-end">
            <div>
              <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.search}</label>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="h-[42px] w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
              />
            </div>
            <SearchableSelect
              label={pageDict.branch}
              options={branchOptions}
              value={branchId}
              onChange={(value) => setBranchId(value || '')}
              placeholder={pageDict.allBranches}
            />
            <SearchableSelect
              label={pageDict.status}
              options={[
                { value: 'active', label: pageDict.active },
                { value: 'inactive', label: pageDict.inactive },
              ]}
              value={status}
              onChange={(value) => setStatus(value || '')}
              placeholder={pageDict.allStatuses}
              isSearchable={false}
            />
            <Button onClick={applyFilters}>
              <svg className="me-2 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18M6 10h12M10 16h4" />
              </svg>
              {pageDict.filter}
            </Button>
            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilters}</Button>
          </div>
        </Card>

        {showWarehouseForm ? (
          <Card className="p-5">
            <form onSubmit={submitWarehouse} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {editingWarehouse ? pageDict.editWarehouse : pageDict.createWarehouse}
                </h2>
                <Button variant="secondary" onClick={() => setShowWarehouseForm(false)}>{pageDict.cancel}</Button>
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.code}</label>
                  <input
                    value={warehouseForm.data.code}
                    onChange={(event) => warehouseForm.setData('code', event.target.value.toUpperCase())}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm font-mono text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    required
                  />
                  {warehouseForm.errors.code ? <span className="mt-1 block text-xs text-[var(--danger)]">{warehouseForm.errors.code}</span> : null}
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.nameEn}</label>
                  <input
                    value={warehouseForm.data.name.en}
                    onChange={(event) => warehouseForm.setData('name', { ...warehouseForm.data.name, en: event.target.value })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    required
                  />
                  {fieldError(warehouseForm.errors, 'name.en') ? <span className="mt-1 block text-xs text-[var(--danger)]">{fieldError(warehouseForm.errors, 'name.en')}</span> : null}
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.nameAr}</label>
                  <input
                    value={warehouseForm.data.name.ar}
                    onChange={(event) => warehouseForm.setData('name', { ...warehouseForm.data.name, ar: event.target.value })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                  />
                </div>
                <SearchableSelect
                  label={pageDict.branch}
                  options={branchOptions}
                  value={warehouseForm.data.branch_id}
                  onChange={(value) => warehouseForm.setData('branch_id', value || '')}
                  placeholder={pageDict.noBranch}
                  error={warehouseForm.errors.branch_id}
                />
                <SearchableSelect
                  label={pageDict.type}
                  options={warehouseTypeOptions}
                  value={warehouseForm.data.warehouse_type}
                  onChange={(value) => warehouseForm.setData('warehouse_type', value || 'standard')}
                  isClearable={false}
                  error={warehouseForm.errors.warehouse_type}
                />
                <div className="flex items-center rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                  <ToggleSwitch
                    checked={warehouseForm.data.is_active}
                    onChange={(checked) => warehouseForm.setData('is_active', checked)}
                    label={pageDict.active}
                  />
                </div>
                <div className="flex items-center rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                  <ToggleSwitch
                    checked={warehouseForm.data.is_default}
                    onChange={(checked) => warehouseForm.setData('is_default', checked)}
                    label={pageDict.default}
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setShowWarehouseForm(false)}>{pageDict.cancel}</Button>
                <Button type="submit" disabled={warehouseForm.processing}>
                  {warehouseForm.processing ? pageDict.saving : pageDict.save}
                </Button>
              </div>
            </form>
          </Card>
        ) : null}

        {showLocationForm ? (
          <Card className="p-5">
            <form onSubmit={submitLocation} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {editingLocation ? pageDict.editLocation : pageDict.createLocation}
                </h2>
                <Button variant="secondary" onClick={() => setShowLocationForm(false)}>{pageDict.cancel}</Button>
              </div>

              <div className="grid gap-4 md:grid-cols-4">
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.code}</label>
                  <input
                    value={locationForm.data.code}
                    onChange={(event) => locationForm.setData('code', event.target.value.toUpperCase())}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm font-mono text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    required
                  />
                  {locationForm.errors.code ? <span className="mt-1 block text-xs text-[var(--danger)]">{locationForm.errors.code}</span> : null}
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.locationNameEn}</label>
                  <input
                    value={locationForm.data.name.en}
                    onChange={(event) => locationForm.setData('name', { ...locationForm.data.name, en: event.target.value })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                    required
                  />
                  {fieldError(locationForm.errors, 'name.en') ? <span className="mt-1 block text-xs text-[var(--danger)]">{fieldError(locationForm.errors, 'name.en')}</span> : null}
                </div>
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.locationNameAr}</label>
                  <input
                    value={locationForm.data.name.ar}
                    onChange={(event) => locationForm.setData('name', { ...locationForm.data.name, ar: event.target.value })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                  />
                </div>
                <SearchableSelect
                  label={pageDict.locationType}
                  options={locationTypeOptions}
                  value={locationForm.data.location_type}
                  onChange={(value) => locationForm.setData('location_type', value || 'standard')}
                  isClearable={false}
                  error={locationForm.errors.location_type}
                />
              </div>

              <div className="flex items-center justify-between gap-3">
                <ToggleSwitch
                  checked={locationForm.data.is_active}
                  onChange={(checked) => locationForm.setData('is_active', checked)}
                  label={pageDict.active}
                />
                <div className="flex justify-end gap-2">
                  <Button variant="secondary" onClick={() => setShowLocationForm(false)}>{pageDict.cancel}</Button>
                  <Button type="submit" disabled={locationForm.processing}>
                    {locationForm.processing ? pageDict.saving : pageDict.save}
                  </Button>
                </div>
              </div>
            </form>
          </Card>
        ) : null}

        {warehouses.data.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.code}</th>
                  <th className={tableClasses.th}>{pageDict.branch}</th>
                  <th className={tableClasses.th}>{pageDict.type}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={tableClasses.th}>{pageDict.locations}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.actions}</th>
                </tr>
              </thead>
              <tbody>
                {warehouses.data.map((warehouse) => (
                  <tr key={warehouse.id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-52 flex-col gap-1">
                        <div className="flex items-center gap-2">
                          <span className="font-mono text-xs font-extrabold">{warehouse.code}</span>
                          {warehouse.is_default ? <StatusBadge tone="info">{pageDict.default}</StatusBadge> : null}
                        </div>
                        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(warehouse.name, locale)}</span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      {warehouse.branch ? (
                        <StatusBadge tone="info">
                          {warehouse.branch.code} - {getLocalizedName(warehouse.branch.name, locale)}
                        </StatusBadge>
                      ) : (
                        <span className="text-xs text-[var(--text-muted)]">{pageDict.noBranch}</span>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      <span className="text-xs font-bold text-[var(--text-secondary)]">
                        {pageDict.types[warehouse.warehouse_type as keyof typeof pageDict.types] || warehouse.warehouse_type}
                      </span>
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={warehouse.is_active ? 'ok' : 'muted'}>
                        {warehouse.is_active ? pageDict.active : pageDict.inactive}
                      </StatusBadge>
                    </td>
                    <td className={tableClasses.td}>
                      {warehouse.locations && warehouse.locations.length > 0 ? (
                        <div className="flex min-w-64 flex-wrap gap-2">
                          {warehouse.locations.map((location) => (
                            <button
                              key={location.id}
                              type="button"
                              onClick={() => openEditLocation(location)}
                              className="rounded-full border border-[var(--border)] bg-[var(--background)] px-3 py-1 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)]"
                              title={pageDict.editLocation}
                              aria-label={pageDict.editLocation}
                            >
                              {location.code} - {getLocalizedName(location.name, locale)}
                            </button>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xs text-[var(--text-muted)]">{pageDict.noLocations}</span>
                      )}
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex flex-wrap justify-end gap-2">
                        {can('inventory.create') ? (
                          <Button variant="secondary" onClick={() => openCreateLocation(warehouse)}>{pageDict.createLocation}</Button>
                        ) : null}
                        {can('inventory.edit') ? (
                          <Button variant="secondary" onClick={() => openEditWarehouse(warehouse)}>{actionsDict.edit}</Button>
                        ) : null}
                        {can('inventory.delete') && !warehouse.is_default ? (
                          <Button variant="danger" onClick={() => deleteWarehouse(warehouse)}>{pageDict.delete}</Button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
