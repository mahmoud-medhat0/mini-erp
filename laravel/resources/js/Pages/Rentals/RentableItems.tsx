import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { AccountingAmount, Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Warehouse = { id: string; code: string; name: TranslatedName; branch_id?: string | null; warehouse_type?: string | null };
type Product = { id: string; code: string; name: TranslatedName; type?: string | null };
type FixedAsset = { id: string; asset_number: string; name: TranslatedName; status: string; branch_id?: string | null };
type RentableItem = {
  id: string;
  code: string;
  name: TranslatedName;
  description?: TranslatedName;
  item_source: string;
  product_id?: string | null;
  fixed_asset_id?: string | null;
  branch_id?: string | null;
  warehouse_id?: string | null;
  status: string;
  condition_status: string;
  currency: string;
  serial_number?: string | null;
  replacement_value_minor: number;
  daily_rate_minor?: number | null;
  monthly_rate_minor?: number | null;
  deposit_minor?: number | null;
  notes?: string | null;
  is_active: boolean;
  lock_version: number;
  product?: Product | null;
  fixed_asset?: FixedAsset | null;
  branch?: Branch | null;
  warehouse?: Warehouse | null;
};
type PaginatedData<T> = { data: T[]; total: number; links?: any[] };
type Props = SharedPageProps & {
  items: PaginatedData<RentableItem>;
  branches: Branch[];
  warehouses: Warehouse[];
  products: Product[];
  fixedAssets: FixedAsset[];
  currencies: CurrencyOption[];
  itemSources: string[];
  statuses: string[];
  conditionStatuses: string[];
  filters: { search?: string; status?: string; item_source?: string; branch_id?: string; warehouse_id?: string };
};

function amountToMinor(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (normalized === '') return 0;
  const match = normalized.match(/^(\d+)(?:\.(\d{0,2}))?$/);
  if (!match) return 0;
  const whole = match[1];
  const cents = (match[2] || '').padEnd(2, '0').slice(0, 2);
  return Number(`${whole}${cents}`);
}

function minorToAmount(value?: number | null): string {
  const amount = Math.abs(Number(value || 0));
  const sign = Number(value || 0) < 0 ? '-' : '';
  const whole = Math.trunc(amount / 100);
  const cents = String(amount % 100).padStart(2, '0');
  return `${sign}${whole}.${cents}`;
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  return getLocalizedName(name, locale);
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'available' || value === 'returned') return 'ok';
  if (value === 'rented' || value === 'allocated' || value === 'reserved') return 'info';
  if (value === 'return_pending' || value === 'maintenance') return 'warning';
  if (value === 'damaged' || value === 'lost') return 'danger';
  return 'muted';
}

function conditionTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'good') return 'ok';
  if (value === 'fair' || value === 'maintenance') return 'warning';
  if (value === 'damaged' || value === 'lost') return 'danger';
  return 'muted';
}

export default function RentableItemsIndex({
  locale,
  items,
  branches = [],
  warehouses = [],
  products = [],
  fixedAssets = [],
  currencies = [],
  itemSources = [],
  statuses = [],
  conditionStatuses = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const pageDict = dict.app.pages.rentableItems;
  const can = useCan();
  const defaultCurrency = currencies[0]?.code || '';
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [source, setSource] = useState(filters.item_source || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<RentableItem | null>(null);

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    description: { en: '', ar: '' },
    item_source: 'standalone',
    product_id: '',
    fixed_asset_id: '',
    branch_id: '',
    warehouse_id: '',
    status: 'available',
    condition_status: 'good',
    currency: defaultCurrency,
    serial_number: '',
    replacement_value_minor: 0,
    replacement_value_amount: '',
    daily_rate_minor: null as number | null,
    daily_rate_amount: '',
    monthly_rate_minor: null as number | null,
    monthly_rate_amount: '',
    deposit_minor: null as number | null,
    deposit_amount: '',
    notes: '',
    is_active: true,
    reason: '',
    lock_version: 1,
  });

  const branchOptions = useMemo(() => branches.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [branches, activeLocale]);

  const warehouseOptions = useMemo(() => warehouses.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
    sublabel: item.branch_id ? branches.find((branch) => branch.id === item.branch_id)?.code : undefined,
  })), [warehouses, branches, activeLocale]);

  const productOptions = useMemo(() => products.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
    sublabel: item.type || undefined,
  })), [products, activeLocale]);

  const fixedAssetOptions = useMemo(() => fixedAssets.map((item) => ({
    value: item.id,
    label: `${item.asset_number} - ${namePart(item.name, activeLocale)}`,
    sublabel: pageDict.fixedAssetStatuses[item.status as keyof typeof pageDict.fixedAssetStatuses] || item.status,
  })), [fixedAssets, activeLocale, pageDict.fixedAssetStatuses]);

  const currencyOptions = useMemo(() => currencies.map((item) => ({
    value: item.code,
    label: `${item.code} - ${getLocalizedName(item.name, activeLocale)}`,
  })), [currencies, activeLocale]);

  const sourceOptions = itemSources.map((item) => ({ value: item, label: pageDict.sources[item as keyof typeof pageDict.sources] || item }));
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const conditionOptions = conditionStatuses.map((item) => ({ value: item, label: pageDict.conditions[item as keyof typeof pageDict.conditions] || item }));
  const activeFilterCount = [search, status, source, branchId, warehouseId].filter(Boolean).length;

  function applyFilters() {
    router.get('/rentals/items', { search, status, item_source: source, branch_id: branchId, warehouse_id: warehouseId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setSource('');
    setBranchId('');
    setWarehouseId('');
    router.get('/rentals/items', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      description: { en: '', ar: '' },
      item_source: 'standalone',
      product_id: '',
      fixed_asset_id: '',
      branch_id: '',
      warehouse_id: '',
      status: 'available',
      condition_status: 'good',
      currency: defaultCurrency,
      serial_number: '',
      replacement_value_minor: 0,
      replacement_value_amount: '',
      daily_rate_minor: null,
      daily_rate_amount: '',
      monthly_rate_minor: null,
      monthly_rate_amount: '',
      deposit_minor: null,
      deposit_amount: '',
      notes: '',
      is_active: true,
      reason: '',
      lock_version: 1,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(item: RentableItem) {
    setEditing(item);
    form.setData({
      code: item.code,
      name: { en: namePart(item.name, 'en'), ar: namePart(item.name, 'ar') },
      description: { en: namePart(item.description || null, 'en'), ar: namePart(item.description || null, 'ar') },
      item_source: item.item_source,
      product_id: item.product_id || '',
      fixed_asset_id: item.fixed_asset_id || '',
      branch_id: item.branch_id || '',
      warehouse_id: item.warehouse_id || '',
      status: item.status,
      condition_status: item.condition_status,
      currency: item.currency,
      serial_number: item.serial_number || '',
      replacement_value_minor: item.replacement_value_minor,
      replacement_value_amount: minorToAmount(item.replacement_value_minor),
      daily_rate_minor: item.daily_rate_minor ?? null,
      daily_rate_amount: item.daily_rate_minor === null || item.daily_rate_minor === undefined ? '' : minorToAmount(item.daily_rate_minor),
      monthly_rate_minor: item.monthly_rate_minor ?? null,
      monthly_rate_amount: item.monthly_rate_minor === null || item.monthly_rate_minor === undefined ? '' : minorToAmount(item.monthly_rate_minor),
      deposit_minor: item.deposit_minor ?? null,
      deposit_amount: item.deposit_minor === null || item.deposit_minor === undefined ? '' : minorToAmount(item.deposit_minor),
      notes: item.notes || '',
      is_active: item.is_active,
      reason: '',
      lock_version: item.lock_version,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function sourceLabel(item: RentableItem): string {
    if (item.item_source === 'product' && item.product) return `${item.product.code} - ${namePart(item.product.name, activeLocale)}`;
    if (item.item_source === 'fixed_asset' && item.fixed_asset) return `${item.fixed_asset.asset_number} - ${namePart(item.fixed_asset.name, activeLocale)}`;
    return pageDict.sources.standalone;
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      code: form.data.code,
      name: form.data.name,
      description: form.data.description,
      item_source: form.data.item_source,
      product_id: form.data.item_source === 'product' ? form.data.product_id || null : null,
      fixed_asset_id: form.data.item_source === 'fixed_asset' ? form.data.fixed_asset_id || null : null,
      branch_id: form.data.branch_id || null,
      warehouse_id: form.data.warehouse_id || null,
      status: form.data.status,
      condition_status: form.data.condition_status,
      currency: form.data.currency,
      serial_number: form.data.serial_number || null,
      replacement_value_minor: amountToMinor(form.data.replacement_value_amount),
      daily_rate_minor: form.data.daily_rate_amount === '' ? null : amountToMinor(form.data.daily_rate_amount),
      monthly_rate_minor: form.data.monthly_rate_amount === '' ? null : amountToMinor(form.data.monthly_rate_amount),
      deposit_minor: form.data.deposit_amount === '' ? null : amountToMinor(form.data.deposit_amount),
      notes: form.data.notes || null,
      is_active: form.data.is_active,
      reason: form.data.reason || null,
      lock_version: form.data.lock_version,
    };

    if (editing) {
      router.put(`/rentals/items/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/rentals/items', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function deleteItem(item: RentableItem) {
    if (!confirm(pageDict.confirmDelete)) return;
    router.delete(`/rentals/items/${item.id}`, { preserveScroll: true });
  }

  return (
    <AppLayout active="rentals.items.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('rentals.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 xl:grid-cols-[1fr_180px_180px_220px_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allSources }, ...sourceOptions]} value={source || null} onChange={(value) => setSource(value || '')} label={pageDict.itemSource} />
          <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={pageDict.branch} />
          <SearchableSelect options={[{ value: '', label: pageDict.allWarehouses }, ...warehouseOptions]} value={warehouseId || null} onChange={(value) => setWarehouseId(value || '')} label={pageDict.warehouse} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

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
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameEn}
                <input className="input mt-1" value={form.data.name.en} onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameAr}
                <input className="input mt-1" value={form.data.name.ar} onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })} />
              </label>
              <SearchableSelect options={currencyOptions} value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} />
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <SearchableSelect
                options={sourceOptions}
                value={form.data.item_source}
                onChange={(value) => form.setData({
                  ...form.data,
                  item_source: value || 'standalone',
                  product_id: '',
                  fixed_asset_id: '',
                })}
                label={pageDict.itemSource}
              />
              {form.data.item_source === 'product' ? (
                <SearchableSelect options={productOptions} value={form.data.product_id || null} onChange={(value) => form.setData('product_id', value || '')} label={pageDict.product} />
              ) : null}
              {form.data.item_source === 'fixed_asset' ? (
                <SearchableSelect options={fixedAssetOptions} value={form.data.fixed_asset_id || null} onChange={(value) => form.setData('fixed_asset_id', value || '')} label={pageDict.fixedAsset} />
              ) : null}
              <SearchableSelect options={branchOptions} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
              <SearchableSelect options={warehouseOptions} value={form.data.warehouse_id || null} onChange={(value) => form.setData('warehouse_id', value || '')} label={pageDict.warehouse} />
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <SearchableSelect options={statusOptions} value={form.data.status} onChange={(value) => form.setData('status', value || 'available')} label={pageDict.status} />
              <SearchableSelect options={conditionOptions} value={form.data.condition_status} onChange={(value) => form.setData('condition_status', value || 'good')} label={pageDict.condition} />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.serialNumber}
                <input className="input mt-1" value={form.data.serial_number} onChange={(event) => form.setData('serial_number', event.target.value)} />
              </label>
              <div className="flex items-end pb-1">
                <ToggleSwitch checked={form.data.is_active} onChange={(value) => form.setData('is_active', value)} label={pageDict.active} />
              </div>
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.replacementValue}
                <input className="input mt-1" inputMode="decimal" value={form.data.replacement_value_amount} onChange={(event) => form.setData('replacement_value_amount', event.target.value)} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.dailyRate}
                <input className="input mt-1" inputMode="decimal" value={form.data.daily_rate_amount} onChange={(event) => form.setData('daily_rate_amount', event.target.value)} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.monthlyRate}
                <input className="input mt-1" inputMode="decimal" value={form.data.monthly_rate_amount} onChange={(event) => form.setData('monthly_rate_amount', event.target.value)} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.deposit}
                <input className="input mt-1" inputMode="decimal" value={form.data.deposit_amount} onChange={(event) => form.setData('deposit_amount', event.target.value)} />
              </label>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.descriptionEn}
                <textarea className="input mt-1 min-h-24" value={form.data.description.en} onChange={(event) => form.setData('description', { ...form.data.description, en: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.descriptionAr}
                <textarea className="input mt-1 min-h-24" value={form.data.description.ar} onChange={(event) => form.setData('description', { ...form.data.description, ar: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.reason}
                <textarea className="input mt-1 min-h-24" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
              </label>
            </div>

            <div className="grid gap-4 xl:grid-cols-1">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.notes}
                <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
              </label>
            </div>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing}>{editing ? pageDict.update : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {items.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.item}</th>
                <th className={tableClasses.th}>{pageDict.itemSource}</th>
                <th className={tableClasses.th}>{pageDict.location}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.condition}</th>
                <th className={tableClasses.th}>{pageDict.rates}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {items.data.map((item) => (
                <tr key={item.id}>
                  <td className={tableClasses.td}>
                    <div className="font-mono text-sm font-bold">{item.code}</div>
                    {item.serial_number ? <div className="mt-1 text-xs text-[var(--text-muted)]">{item.serial_number}</div> : null}
                  </td>
                  <td className={tableClasses.td}>
                    <div className="font-semibold">{namePart(item.name, activeLocale)}</div>
                    <div className="mt-1 text-xs text-[var(--text-muted)]">{item.is_active ? pageDict.active : pageDict.inactive}</div>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="text-sm font-semibold">{pageDict.sources[item.item_source as keyof typeof pageDict.sources] || item.item_source}</div>
                    <div className="mt-1 text-xs text-[var(--text-muted)]">{sourceLabel(item)}</div>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="text-sm font-semibold">{item.branch ? `${item.branch.code} - ${namePart(item.branch.name, activeLocale)}` : pageDict.noBranch}</div>
                    <div className="mt-1 text-xs text-[var(--text-muted)]">{item.warehouse ? `${item.warehouse.code} - ${namePart(item.warehouse.name, activeLocale)}` : pageDict.noWarehouse}</div>
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={statusTone(item.status)}>{pageDict.statuses[item.status as keyof typeof pageDict.statuses] || item.status}</StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={conditionTone(item.condition_status)}>{pageDict.conditions[item.condition_status as keyof typeof pageDict.conditions] || item.condition_status}</StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="grid gap-1 text-xs">
                      <span>{pageDict.dailyRate}: <AccountingAmount amountMinor={item.daily_rate_minor || 0} currency={item.currency} /></span>
                      <span>{pageDict.monthlyRate}: <AccountingAmount amountMinor={item.monthly_rate_minor || 0} currency={item.currency} /></span>
                      <span>{pageDict.deposit}: <AccountingAmount amountMinor={item.deposit_minor || 0} currency={item.currency} /></span>
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap gap-2">
                      {can('rentals.edit') ? <Button variant="secondary" onClick={() => openEdit(item)}>{pageDict.edit}</Button> : null}
                      {can('rentals.delete') ? <Button variant="danger" onClick={() => deleteItem(item)}>{pageDict.delete}</Button> : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
