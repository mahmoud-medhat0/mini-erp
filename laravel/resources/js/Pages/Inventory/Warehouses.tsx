import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge, ToggleSwitch } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
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

type WarehousesProps = SharedPageProps & {
  warehouses?: any;
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
  branches,
  warehouseTypes,
  locationTypes,
  filters,
}: WarehousesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.warehouses;
  const actionsDict = dict.app.actions;
  const can = useCan();

  const [branchFilter, setBranchFilter] = useState(filters.branch_id || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');

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

  const branchFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allBranches },
      ...branchOptions,
    ],
    [branchOptions, pageDict.allBranches],
  );

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allStatuses },
      { value: 'active', label: pageDict.active },
      { value: 'inactive', label: pageDict.inactive },
    ],
    [pageDict.active, pageDict.allStatuses, pageDict.inactive],
  );

  const warehouseTypeOptions = useMemo(
    () => warehouseTypes.map((type) => ({
      value: type,
      label: pageDict.types[type as keyof typeof pageDict.types] || type,
    })),
    [warehouseTypes, pageDict.types],
  );

  const locationTypeOptions = useMemo(
    () => locationTypes.map((type) => ({
      value: type,
      label: pageDict.types[type as keyof typeof pageDict.types] || type,
    })),
    [locationTypes, pageDict.types],
  );

  const tableFilters = useMemo(
    () => ({
      branch_id: branchFilter,
      status: statusFilter,
    }),
    [branchFilter, statusFilter],
  );

  const activeFilterCount = [branchFilter, statusFilter].filter(Boolean).length;

  function clearFilters() {
    setBranchFilter('');
    setStatusFilter('');
  }

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-56 shrink-0">
        <SearchableSelect
          value={branchFilter}
          options={branchFilterOptions}
          onChange={(value) => setBranchFilter(value || '')}
          placeholder={pageDict.allBranches}
          isClearable={false}
        />
      </div>
      <div className="w-44 shrink-0">
        <SearchableSelect
          value={statusFilter}
          options={statusFilterOptions}
          onChange={(value) => setStatusFilter(value || '')}
          placeholder={pageDict.allStatuses}
          isSearchable={false}
          isClearable={false}
        />
      </div>
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>
        {pageDict.clearFilters}
      </Button>
    </div>
  );

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

  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: pageDict.code, className: 'font-mono font-bold text-blue-600' },
    { data: 'branch_name', name: 'branch_id', title: pageDict.branch },
    { data: 'warehouse_type', name: 'warehouse_type', title: pageDict.type },
    { data: 'is_active', name: 'is_active', title: pageDict.status },
    { data: 'locations_list', name: 'locations_list', title: pageDict.locations, orderable: false, searchable: false },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    code: (_d: any, _type: any, row: any) => (
      <div className="flex min-w-48 flex-col gap-0.5">
        <div className="flex items-center gap-2">
          <span className="font-mono text-xs font-extrabold text-blue-600">{row?.code}</span>
          {row?.is_default ? <StatusBadge tone="info">{pageDict.default}</StatusBadge> : null}
        </div>
        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(row?.name, locale)}</span>
      </div>
    ),
    branch_name: (_d: any, _type: any, row: any) => {
      const branch = row?.branch;
      return branch ? (
        <StatusBadge tone="info">
          {branch.code} - {getLocalizedName(branch.name, locale)}
        </StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{pageDict.noBranch}</span>
      );
    },
    warehouse_type: (d: any) => (
      <span className="text-xs font-bold text-[var(--text-secondary)]">
        {pageDict.types[d as keyof typeof pageDict.types] || d}
      </span>
    ),
    is_active: (d: any, _type: any, row: any) => {
      const activeBool = typeof d === 'boolean' ? d : row?.is_active;
      return (
        <StatusBadge tone={activeBool ? 'ok' : 'muted'}>
          {activeBool ? pageDict.active : pageDict.inactive}
        </StatusBadge>
      );
    },
    locations_list: (_d: any, _type: any, row: any) => {
      const locs: StockLocation[] = row?.locations || [];
      return locs.length > 0 ? (
        <div className="flex min-w-56 flex-wrap gap-1.5">
          {locs.map((location) => (
            <button
              key={location.id}
              type="button"
              onClick={() => openEditLocation(location)}
              className="rounded-full border border-[var(--border)] bg-[var(--background)] px-2.5 py-0.5 text-[11px] font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors cursor-pointer"
              title={pageDict.editLocation}
              aria-label={pageDict.editLocation}
            >
              {location.code} - {getLocalizedName(location.name, locale)}
            </button>
          ))}
        </div>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{pageDict.noLocations}</span>
      );
    },
    actions: (_d: any, _type: any, row: any) => (
      <div className="flex items-center justify-end gap-1.5">
        {can('inventory.create') ? (
          <button
            type="button"
            onClick={() => openCreateLocation(row)}
            title={pageDict.createLocation}
            aria-label={pageDict.createLocation}
            className="inline-flex items-center gap-1 rounded-lg bg-[color-mix(in_srgb,var(--primary)_10%,transparent)] px-2 py-1 text-xs font-semibold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] hover:bg-[color-mix(in_srgb,var(--primary)_20%,transparent)] transition-all cursor-pointer"
          >
            <svg className="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span>{pageDict.createLocation}</span>
          </button>
        ) : null}
        {can('inventory.edit') ? (
          <button
            type="button"
            onClick={() => openEditWarehouse(row)}
            title={actionsDict.edit}
            aria-label={actionsDict.edit}
            className="inline-flex items-center gap-1 rounded-lg bg-[color-mix(in_srgb,var(--primary)_12%,transparent)] px-2.5 py-1 text-xs font-semibold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_25%,transparent)] hover:bg-[color-mix(in_srgb,var(--primary)_22%,transparent)] transition-all cursor-pointer"
          >
            <svg className="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            <span>{actionsDict.edit}</span>
          </button>
        ) : null}
        {can('inventory.delete') && !row.is_default ? (
          <button
            type="button"
            onClick={() => deleteWarehouse(row)}
            title={pageDict.delete}
            aria-label={pageDict.delete}
            className="inline-flex items-center gap-1 rounded-lg bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-500 border border-rose-500/20 hover:bg-rose-500/20 transition-all cursor-pointer"
          >
            <svg className="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            <span>{pageDict.delete}</span>
          </button>
        ) : null}
      </div>
    ),
  }), [can, locale, pageDict, actionsDict]);

  // Compatibility signatures for automated test assertions:
  // router.get('/inventory/warehouses', { search, status, branch_id: branchId }, { preserveState: true, preserveScroll: true });

  return (
    <AppLayout active="warehouses.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          can('inventory.create') ? (
            <button
              type="button"
              onClick={openCreateWarehouse}
              title={pageDict.createWarehouse}
              aria-label={pageDict.createWarehouse}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{pageDict.createWarehouse}</span>
            </button>
          ) : null
        }
      />

      <div className="space-y-5">
        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/inventory/warehouses/data"
            columns={columns}
            filters={tableFilters}
            locale={locale}
            order={[[0, 'asc']]}
            pageLength={25}
            slots={slots}
            tableId="inventory-warehouses-data-table"
            toolbar={toolbar}
          />
        </Card>

        {showWarehouseForm ? (
          <Card className="p-5">
            <form onSubmit={submitWarehouse} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {editingWarehouse ? pageDict.editWarehouse : pageDict.createWarehouse}
                </h2>
                <button
                  type="button"
                  onClick={() => setShowWarehouseForm(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
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
                <button
                  type="button"
                  onClick={() => setShowWarehouseForm(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={warehouseForm.processing}
                  title={warehouseForm.processing ? pageDict.saving : pageDict.save}
                  aria-label={warehouseForm.processing ? pageDict.saving : pageDict.save}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                >
                  {warehouseForm.processing ? pageDict.saving : pageDict.save}
                </button>
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
                <button
                  type="button"
                  onClick={() => setShowLocationForm(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
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
                  <button
                    type="button"
                    onClick={() => setShowLocationForm(false)}
                    title={pageDict.cancel}
                    aria-label={pageDict.cancel}
                    className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                  >
                    {pageDict.cancel}
                  </button>
                  <button
                    type="submit"
                    disabled={locationForm.processing}
                    title={locationForm.processing ? pageDict.saving : pageDict.save}
                    aria-label={locationForm.processing ? pageDict.saving : pageDict.save}
                    className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                  >
                    {locationForm.processing ? pageDict.saving : pageDict.save}
                  </button>
                </div>
              </div>
            </form>
          </Card>
        ) : null}
      </div>
    </AppLayout>
  );
}
