import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Customer = { id: string; code: string; name: TranslatedName };
type Branch = { id: string; code: string; name: TranslatedName };
type RentableItem = { id: string; code: string; name: TranslatedName; status: string };
type SourceInvoiceRef = { status: string };
type PriorInvoiceLine = {
  line_type: string;
  line_total_minor: number;
  invoice?: SourceInvoiceRef | null;
};
type ContractLine = {
  id: string;
  line_no: number;
  rentable_item_id: string;
  description?: TranslatedName;
  rate_minor: number;
  deposit_minor: number;
  rentable_item?: RentableItem | null;
  invoice_lines?: PriorInvoiceLine[];
  invoiceLines?: PriorInvoiceLine[];
};
type ReturnLine = {
  id: string;
  rental_contract_line_id: string;
  estimated_damage_charge_minor: number;
  condition_in: string;
  outcome: string;
  rentable_item?: RentableItem | null;
  invoice_lines?: PriorInvoiceLine[];
  invoiceLines?: PriorInvoiceLine[];
};
type RentalReturn = {
  id: string;
  number?: string | null;
  status: string;
  lines?: ReturnLine[];
};
type Contract = {
  id: string;
  number?: string | null;
  customer_id: string;
  branch_id?: string | null;
  status: string;
  start_date: string;
  expected_end_date: string;
  currency: string;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: ContractLine[];
  returns?: RentalReturn[];
};
type TaxCode = {
  id: string;
  code: string;
  name: TranslatedName;
  calculation_mode: string;
  rates?: Array<{ rate_bps: number; effective_from: string }>;
};
type InvoiceLine = {
  id: string;
  line_no: number;
  line_type: string;
  rental_contract_line_id?: string | null;
  rental_return_line_id?: string | null;
  description?: string | null;
  quantity_e6: number;
  unit_amount_minor: number;
  line_total_minor: number;
  tax_code_id?: string | null;
  tax_rate_bps: number;
  tax_amount_minor: number;
  gross_amount_minor: number;
  contract_line?: ContractLine | null;
  rental_return_line?: ReturnLine | null;
  tax_code?: TaxCode | null;
};
type RentalInvoice = {
  id: string;
  number?: string | null;
  rental_contract_id: string;
  customer_id: string;
  branch_id?: string | null;
  invoice_type: string;
  status: string;
  invoice_date: string;
  due_date?: string | null;
  billing_period_start?: string | null;
  billing_period_end?: string | null;
  currency: string;
  subtotal_minor: number;
  tax_amount_minor: number;
  total_minor: number;
  reference?: string | null;
  notes?: string | null;
  lock_version: number;
  contract?: Contract | null;
  customer?: Customer | null;
  branch?: Branch | null;
  journal_entry_id?: string | null;
  receivable_entry_id?: string | null;
  lines?: InvoiceLine[];
};
type EditableLine = {
  line_type: string;
  rental_contract_line_id: string;
  rental_return_line_id: string;
  description: string;
  quantity: string;
  unit_amount: string;
  tax_code_id: string;
  notes: string;
};
type Props = SharedPageProps & {
  invoices: { data: RentalInvoice[]; total: number; links?: PaginationLink[] };
  contracts: Contract[];
  currencies: CurrencyOption[];
  taxCodes: TaxCode[];
  statuses: string[];
  invoiceTypes: string[];
  lineTypes: string[];
  filters: { search?: string; status?: string; invoice_type?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function amountToMinor(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (normalized === '') return 0;
  const match = normalized.match(/^(\d+)(?:\.(\d{0,2}))?$/);
  if (!match) return 0;

  return Number(`${match[1]}${(match[2] || '').padEnd(2, '0').slice(0, 2)}`);
}

function minorToAmount(value?: number | null): string {
  const amount = Math.abs(Number(value || 0));
  const sign = Number(value || 0) < 0 ? '-' : '';

  return `${sign}${Math.trunc(amount / 100)}.${String(amount % 100).padStart(2, '0')}`;
}

function parseQuantityToE6(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (!/^\d+(\.\d{0,6})?$/.test(normalized)) return 0;
  const [wholeRaw, fractionRaw = ''] = normalized.split('.');
  const whole = Number(wholeRaw || '0');
  const fraction = Number(fractionRaw.padEnd(6, '0').slice(0, 6) || '0');

  if (!Number.isSafeInteger(whole) || !Number.isSafeInteger(fraction)) return 0;

  return whole * 1000000 + fraction;
}

function quantityFromE6(value?: number | null): string {
  const absolute = Math.max(0, Math.trunc(Number(value || 0)));
  const whole = Math.floor(absolute / 1000000);
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${whole}${fraction ? `.${fraction}` : ''}`;
}

function calculateLineTotalMinor(quantity: string, unitAmount: string): number {
  const quantityE6 = BigInt(parseQuantityToE6(quantity));
  const unitMinor = BigInt(amountToMinor(unitAmount));
  if (quantityE6 <= 0n || unitMinor < 0n) return 0;

  return Number((quantityE6 * unitMinor) / 1000000n);
}

function activePriorLines(line: ContractLine | ReturnLine, lineType: string): PriorInvoiceLine[] {
  const priorLines = line.invoice_lines || line.invoiceLines || [];

  return priorLines.filter((priorLine) => priorLine.line_type === lineType && priorLine.invoice?.status !== 'cancelled');
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  if (value === 'approved') return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  return getLocalizedName(name, locale);
}

export default function RentalInvoicesIndex({
  locale,
  invoices,
  contracts = [],
  currencies = [],
  taxCodes = [],
  statuses = [],
  invoiceTypes = [],
  lineTypes = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const pageDict = dict.app.pages.rentalInvoices;
  const can = useCan();
  const canCreateRentalInvoices = can('rentals.invoice');
  const canSubmitRentalInvoices = can('rentals.submit');
  const canApproveRentalInvoices = can('rentals.approve');
  const canPostRentalInvoices = can('rentals.post') && can('view_financials');
  const canCancelRentalInvoices = can('rentals.cancel');
  const defaultCurrency = contracts[0]?.currency || currencies[0]?.code || '';
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [invoiceType, setInvoiceType] = useState(filters.invoice_type || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<RentalInvoice | null>(null);
  const [lineItems, setLineItems] = useState<EditableLine[]>([]);

  const form = useForm({
    rental_contract_id: contracts[0]?.id || '',
    invoice_type: 'periodic_rent',
    invoice_date: today(),
    due_date: '',
    billing_period_start: contracts[0]?.start_date || today(),
    billing_period_end: contracts[0]?.expected_end_date || today(),
    currency: defaultCurrency,
    fx_rate_e6: 1000000,
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const selectedContract = contracts.find((contract) => contract.id === form.data.rental_contract_id);
  const selectedCurrency = selectedContract?.currency || form.data.currency;
  const selectedContractLines = selectedContract?.lines || [];
  const returnChargeLines = (selectedContract?.returns || [])
    .flatMap((rentalReturn) => (rentalReturn.lines || []).map((line) => ({ ...line, returnNumber: rentalReturn.number || pageDict.notNumbered })))
    .filter((line) => Number(line.estimated_damage_charge_minor || 0) > activePriorLines(line, 'damage_charge').reduce((sum, priorLine) => sum + Number(priorLine.line_total_minor || 0), 0));

  const contractOptions = useMemo(() => contracts.map((contract) => ({
    value: contract.id,
    label: `${contract.number || pageDict.notNumbered} - ${contract.customer ? `${contract.customer.code} - ${namePart(contract.customer.name, activeLocale)}` : pageDict.customer}`,
    sublabel: contract.branch ? `${contract.branch.code} - ${namePart(contract.branch.name, activeLocale)} / ${contract.currency}` : `${pageDict.noBranch} / ${contract.currency}`,
  })), [contracts, activeLocale, pageDict.notNumbered, pageDict.customer, pageDict.noBranch]);

  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const invoiceTypeOptions = invoiceTypes.map((item) => ({ value: item, label: pageDict.invoiceTypes[item as keyof typeof pageDict.invoiceTypes] || item }));
  const lineTypeOptions = lineTypes.map((item) => ({ value: item, label: pageDict.lineTypes[item as keyof typeof pageDict.lineTypes] || item }));
  const taxOptions = taxCodes.map((taxCode) => ({
    value: taxCode.id,
    label: `${taxCode.code} - ${namePart(taxCode.name, activeLocale)}`,
    sublabel: taxCode.calculation_mode,
  }));
  const sourceOptions = selectedContractLines.map((line) => ({
    value: line.id,
    label: `${line.line_no} - ${line.rentable_item ? `${line.rentable_item.code} - ${namePart(line.rentable_item.name, activeLocale)}` : pageDict.sourceLine}`,
    sublabel: `${pageDict.lineTotal}: ${minorToAmount(line.rate_minor)} / ${pageDict.deposit}: ${minorToAmount(line.deposit_minor)}`,
  }));
  const damageOptions = returnChargeLines.map((line) => {
    const billed = activePriorLines(line, 'damage_charge').reduce((sum, priorLine) => sum + Number(priorLine.line_total_minor || 0), 0);
    const remaining = Math.max(0, Number(line.estimated_damage_charge_minor || 0) - billed);

    return {
      value: line.id,
      label: `${line.returnNumber} - ${line.rentable_item ? `${line.rentable_item.code} - ${namePart(line.rentable_item.name, activeLocale)}` : pageDict.sourceLine}`,
      sublabel: `${pageDict.remainingDamage}: ${minorToAmount(remaining)}`,
    };
  });

  const preview = lineItems.reduce((totals, line) => {
    const baseMinor = calculateLineTotalMinor(line.quantity, line.unit_amount);
    const taxCode = taxCodes.find((item) => item.id === line.tax_code_id);
    const rateBps = taxCode?.calculation_mode === 'exempt' ? 0 : Number(taxCode?.rates?.[0]?.rate_bps || 0);
    const taxMinor = taxCode ? Number((BigInt(baseMinor) * BigInt(rateBps) + 5000n) / 10000n) : 0;

    return {
      subtotal: totals.subtotal + baseMinor,
      tax: totals.tax + taxMinor,
      total: totals.total + baseMinor + taxMinor,
    };
  }, { subtotal: 0, tax: 0, total: 0 });

  const visibleCurrencies = Array.from(new Set(invoices.data.map((row) => row.currency).filter(Boolean)));
  const visibleTotal = visibleCurrencies.length <= 1 ? invoices.data.reduce((sum, row) => sum + Number(row.total_minor || 0), 0) : null;
  const openInvoices = invoices.data.filter((row) => ['draft', 'submitted', 'approved'].includes(row.status)).length;
  const postedInvoices = invoices.data.filter((row) => row.status === 'posted').length;

  function resetForContract(contractId = contracts[0]?.id || '') {
    const contract = contracts.find((item) => item.id === contractId);
    form.setData({
      rental_contract_id: contractId,
      invoice_type: 'periodic_rent',
      invoice_date: today(),
      due_date: '',
      billing_period_start: contract?.start_date || today(),
      billing_period_end: contract?.expected_end_date || today(),
      currency: contract?.currency || currencies[0]?.code || '',
      fx_rate_e6: 1000000,
      reference: '',
      notes: '',
      lock_version: 1,
    });
    setLineItems([]);
    form.clearErrors();
  }

  function openCreate() {
    setEditing(null);
    resetForContract();
    setShowForm(true);
  }

  function openEdit(invoice: RentalInvoice) {
    setEditing(invoice);
    form.setData({
      rental_contract_id: invoice.rental_contract_id,
      invoice_type: invoice.invoice_type,
      invoice_date: invoice.invoice_date,
      due_date: invoice.due_date || '',
      billing_period_start: invoice.billing_period_start || '',
      billing_period_end: invoice.billing_period_end || '',
      currency: invoice.currency,
      fx_rate_e6: 1000000,
      reference: invoice.reference || '',
      notes: invoice.notes || '',
      lock_version: invoice.lock_version,
    });
    setLineItems((invoice.lines || []).map((line) => ({
      line_type: line.line_type,
      rental_contract_line_id: line.rental_contract_line_id || '',
      rental_return_line_id: line.rental_return_line_id || '',
      description: line.description || '',
      quantity: quantityFromE6(line.quantity_e6),
      unit_amount: minorToAmount(line.unit_amount_minor),
      tax_code_id: line.tax_code_id || '',
      notes: '',
    })));
    form.clearErrors();
    setShowForm(true);
  }

  function changeContract(value: string | null) {
    resetForContract(value || '');
  }

  function addContractLine(lineType: 'rent' | 'deposit') {
    const sourceLine = selectedContractLines.find((line) => {
      if (lineType === 'deposit') {
        const billed = activePriorLines(line, 'deposit').reduce((sum, priorLine) => sum + Number(priorLine.line_total_minor || 0), 0);
        return Number(line.deposit_minor || 0) > billed;
      }

      return true;
    });
    if (!sourceLine) return;

    const billedDeposit = activePriorLines(sourceLine, 'deposit').reduce((sum, priorLine) => sum + Number(priorLine.line_total_minor || 0), 0);
    const remainingDeposit = Math.max(0, Number(sourceLine.deposit_minor || 0) - billedDeposit);

    setLineItems((current) => [
      ...current,
      {
        line_type: lineType,
        rental_contract_line_id: sourceLine.id,
        rental_return_line_id: '',
        description: namePart(sourceLine.description || null, activeLocale),
        quantity: '1',
        unit_amount: minorToAmount(lineType === 'deposit' ? remainingDeposit : sourceLine.rate_minor),
        tax_code_id: '',
        notes: '',
      },
    ]);
  }

  function addDamageLine() {
    const sourceLine = returnChargeLines[0];
    if (!sourceLine) return;

    const billed = activePriorLines(sourceLine, 'damage_charge').reduce((sum, priorLine) => sum + Number(priorLine.line_total_minor || 0), 0);
    const remaining = Math.max(0, Number(sourceLine.estimated_damage_charge_minor || 0) - billed);

    setLineItems((current) => [
      ...current,
      {
        line_type: 'damage_charge',
        rental_contract_line_id: sourceLine.rental_contract_line_id || '',
        rental_return_line_id: sourceLine.id,
        description: sourceLine.rentable_item ? `${sourceLine.rentable_item.code} - ${namePart(sourceLine.rentable_item.name, activeLocale)}` : '',
        quantity: '1',
        unit_amount: minorToAmount(remaining),
        tax_code_id: '',
        notes: '',
      },
    ]);
  }

  function addManualLine(lineType: 'late_fee' | 'other_charge') {
    setLineItems((current) => [
      ...current,
      {
        line_type: lineType,
        rental_contract_line_id: '',
        rental_return_line_id: '',
        description: '',
        quantity: '1',
        unit_amount: '0.00',
        tax_code_id: '',
        notes: '',
      },
    ]);
  }

  function updateLine(index: number, patch: Partial<EditableLine>) {
    setLineItems((current) => current.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function removeLine(index: number) {
    setLineItems((current) => current.filter((_, lineIndex) => lineIndex !== index));
  }

  function applyFilters() {
    router.get('/rentals/invoices', { search, status, invoice_type: invoiceType }, { preserveScroll: true, preserveState: true });
  }

  const activeFilterCount = [search, status, invoiceType].filter(Boolean).length;

  function clearFilters() {
    setSearch('');
    setStatus('');
    setInvoiceType('');
    router.get('/rentals/invoices', {}, { preserveScroll: true, preserveState: true });
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    const payload = {
      ...form.data,
      lines: lineItems.map((line) => ({
        line_type: line.line_type,
        rental_contract_line_id: line.rental_contract_line_id || null,
        rental_return_line_id: line.rental_return_line_id || null,
        description: line.description,
        quantity_e6: parseQuantityToE6(line.quantity),
        unit_amount_minor: amountToMinor(line.unit_amount),
        tax_code_id: line.tax_code_id || null,
        notes: line.notes,
      })),
    };

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setEditing(null);
        setLineItems([]);
      },
    };

    if (editing) {
      router.put(`/rentals/invoices/${editing.id}`, payload, options);
      return;
    }

    router.post('/rentals/invoices', payload, options);
  }

  function action(invoice: RentalInvoice, actionName: 'submit' | 'approve' | 'post' | 'cancel') {
    const confirmText = {
      submit: pageDict.confirmSubmit,
      approve: pageDict.confirmApprove,
      post: pageDict.confirmPost,
      cancel: pageDict.confirmCancel,
    }[actionName];

    if (confirm(confirmText)) {
      const payload = actionName === 'post' ? { confirm_action: 'POST_RENTAL_INVOICE' } : {};
      router.post(`/rentals/invoices/${invoice.id}/${actionName}`, payload, { preserveScroll: true });
    }
  }

  const isRentalInvoiceActionable = (invoice: RentalInvoice) => ['draft', 'submitted', 'approved'].includes(invoice.status);

  const hasAvailableRentalInvoiceAction = (invoice: RentalInvoice) => (
    invoice.status === 'draft'
      ? canCreateRentalInvoices || canSubmitRentalInvoices || canApproveRentalInvoices || canCancelRentalInvoices
      : invoice.status === 'submitted'
        ? canApproveRentalInvoices || canCancelRentalInvoices
        : invoice.status === 'approved'
          ? canPostRentalInvoices || canCancelRentalInvoices
          : false
  );

  const getRentalInvoiceActionState = (invoice: RentalInvoice) => {
    if (hasAvailableRentalInvoiceAction(invoice)) return null;

    return isRentalInvoiceActionable(invoice) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="rentals.invoices.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canCreateRentalInvoices ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <div className="mb-4 grid gap-3 md:grid-cols-3">
        <MetricCard label={pageDict.total} value={visibleTotal === null ? invoices.data.length : <AccountingAmount amountMinor={visibleTotal} currency={visibleCurrencies[0] || pageDict.noCurrency} />} tone="blue" />
        <MetricCard label={pageDict.postedNumber} value={postedInvoices} tone="emerald" />
        <MetricCard label={pageDict.status} value={openInvoices} tone="amber" />
      </div>

      <Card className="mb-4 p-4">
        <div className="grid gap-3 md:grid-cols-[1fr_220px_220px_auto_auto]">
          <input className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allTypes }, ...invoiceTypeOptions]} value={invoiceType || null} onChange={(value) => setInvoiceType(value || '')} label={pageDict.invoiceType} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-4 p-4">
          <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="m-0 text-lg font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
                <p className="mt-1 max-w-3xl text-sm text-[var(--text-secondary)]">{pageDict.formHint}</p>
              </div>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>

            <div className="grid gap-3 lg:grid-cols-3">
              <SearchableSelect label={pageDict.contract} value={form.data.rental_contract_id} onChange={changeContract} options={contractOptions} disabled={Boolean(editing)} required />
              <SearchableSelect label={pageDict.invoiceType} value={form.data.invoice_type} onChange={(value) => form.setData('invoice_type', value || 'periodic_rent')} options={invoiceTypeOptions} required />
              <label className="block">
                <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.currency}</span>
                <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm font-mono text-[var(--text-primary)]" value={selectedCurrency} disabled />
              </label>
              <DatePicker label={pageDict.invoiceDate} value={form.data.invoice_date} onChange={(value) => form.setData('invoice_date', value || today())} required />
              <DatePicker label={pageDict.dueDate} value={form.data.due_date} onChange={(value) => form.setData('due_date', value || '')} />
              <label className="block">
                <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.reference}</span>
                <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
              </label>
              <DatePicker label={pageDict.billingPeriodStart} value={form.data.billing_period_start} onChange={(value) => form.setData('billing_period_start', value || '')} />
              <DatePicker label={pageDict.billingPeriodEnd} value={form.data.billing_period_end} onChange={(value) => form.setData('billing_period_end', value || '')} />
              <label className="block lg:col-span-1">
                <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.notes}</span>
                <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
              </label>
            </div>

            <div className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h3>
                <div className="flex flex-wrap gap-2">
                  <Button variant="secondary" onClick={() => addContractLine('rent')} disabled={!selectedContract}>{pageDict.addRentLine}</Button>
                  <Button variant="secondary" onClick={() => addContractLine('deposit')} disabled={!selectedContract}>{pageDict.addDepositLine}</Button>
                  <Button variant="secondary" onClick={addDamageLine} disabled={!selectedContract || returnChargeLines.length === 0}>{pageDict.addDamageLine}</Button>
                  <Button variant="secondary" onClick={() => addManualLine('late_fee')} disabled={!selectedContract}>{pageDict.addLateFeeLine}</Button>
                  <Button variant="secondary" onClick={() => addManualLine('other_charge')} disabled={!selectedContract}>{pageDict.addOtherChargeLine}</Button>
                </div>
              </div>

              {!selectedContract ? (
                <p className="text-sm text-[var(--text-secondary)]">{pageDict.selectContractFirst}</p>
              ) : lineItems.length === 0 ? (
                <p className="text-sm text-[var(--text-secondary)]">{pageDict.noLines}</p>
              ) : (
                <div className="grid gap-3">
                  {lineItems.map((line, index) => {
                    const lineTotal = calculateLineTotalMinor(line.quantity, line.unit_amount);
                    return (
                      <div key={`${line.line_type}-${index}`} className="rounded-md border border-[var(--border)] bg-[var(--surface)] p-3">
                        <div className="grid gap-3 lg:grid-cols-[160px_1fr_1fr_120px_140px_150px_auto]">
                          <SearchableSelect label={pageDict.lineType} value={line.line_type} onChange={(value) => updateLine(index, { line_type: value || 'rent', rental_return_line_id: value === 'damage_charge' ? line.rental_return_line_id : '' })} options={lineTypeOptions} isClearable={false} />
                          {line.line_type === 'damage_charge' ? (
                            <SearchableSelect label={pageDict.sourceLine} value={line.rental_return_line_id} onChange={(value) => updateLine(index, { rental_return_line_id: value || '' })} options={damageOptions} />
                          ) : line.line_type === 'rent' || line.line_type === 'deposit' ? (
                            <SearchableSelect label={pageDict.sourceLine} value={line.rental_contract_line_id} onChange={(value) => updateLine(index, { rental_contract_line_id: value || '' })} options={sourceOptions} />
                          ) : (
                            <label className="block">
                              <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.sourceLine}</span>
                              <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-muted)]" value={pageDict.manualSource} disabled />
                            </label>
                          )}
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.descriptionLabel}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.description} onChange={(e) => updateLine(index, { description: e.target.value })} />
                          </label>
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.quantity}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.quantity} onChange={(e) => updateLine(index, { quantity: e.target.value })} inputMode="decimal" />
                          </label>
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.unitAmount}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.unit_amount} onChange={(e) => updateLine(index, { unit_amount: e.target.value })} inputMode="decimal" />
                          </label>
                          <SearchableSelect label={pageDict.taxCode} value={line.tax_code_id} onChange={(value) => updateLine(index, { tax_code_id: value || '' })} options={taxOptions} placeholder={pageDict.noTax} />
                          <Button variant="danger" onClick={() => removeLine(index)}>{pageDict.removeLine}</Button>
                        </div>
                        <div className="mt-2 text-end text-xs text-[var(--text-secondary)]">
                          {pageDict.lineTotal}: <AccountingAmount amountMinor={lineTotal} currency={selectedCurrency} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--border)] pt-4">
              <div className="grid gap-2 text-sm text-[var(--text-secondary)] sm:grid-cols-3">
                <span>{pageDict.subtotal}: <AccountingAmount amountMinor={preview.subtotal} currency={selectedCurrency} /></span>
                <span>{pageDict.tax}: <AccountingAmount amountMinor={preview.tax} currency={selectedCurrency} /></span>
                <span>{pageDict.gross}: <AccountingAmount amountMinor={preview.total} currency={selectedCurrency} /></span>
              </div>
              <Button type="submit" disabled={lineItems.length === 0}>{editing ? pageDict.update : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {contracts.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.noContracts} />
      ) : invoices.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.contract}</th>
                <th className={tableClasses.th}>{pageDict.customer}</th>
                <th className={tableClasses.th}>{pageDict.invoiceDate}</th>
                <th className={tableClasses.th}>{pageDict.invoiceType}</th>
                <th className={tableClasses.th}>{pageDict.total}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={`${tableClasses.th} text-end`}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {invoices.data.map((invoice) => {
                const actionState = getRentalInvoiceActionState(invoice);

                return (
                  <tr key={invoice.id}>
                    <td className={`${tableClasses.td} font-mono font-bold`}>{invoice.number || pageDict.notNumbered}</td>
                    <td className={tableClasses.td}>{invoice.contract?.number || pageDict.notNumbered}</td>
                    <td className={tableClasses.td}>
                      {invoice.customer ? `${invoice.customer.code} - ${namePart(invoice.customer.name, activeLocale)}` : pageDict.customer}
                    </td>
                    <td className={tableClasses.td}>{formatDate(invoice.invoice_date)}</td>
                    <td className={tableClasses.td}>{pageDict.invoiceTypes[invoice.invoice_type as keyof typeof pageDict.invoiceTypes] || invoice.invoice_type}</td>
                    <td className={tableClasses.td}><AccountingAmount amountMinor={invoice.total_minor} currency={invoice.currency} /></td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(invoice.status)}>
                        {pageDict.statuses[invoice.status as keyof typeof pageDict.statuses] || invoice.status}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        {invoice.status === 'draft' && canCreateRentalInvoices ? (
                          <button type="button" onClick={() => openEdit(invoice)} title={pageDict.edit} aria-label={pageDict.edit} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.edit}</button>
                        ) : null}
                        {invoice.status === 'draft' && canSubmitRentalInvoices ? (
                          <button type="button" onClick={() => action(invoice, 'submit')} title={pageDict.submit} aria-label={pageDict.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{pageDict.submit}</button>
                        ) : null}
                        {['draft', 'submitted'].includes(invoice.status) && canApproveRentalInvoices ? (
                          <button type="button" onClick={() => action(invoice, 'approve')} title={pageDict.approve} aria-label={pageDict.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{pageDict.approve}</button>
                        ) : null}
                        {invoice.status === 'approved' && canPostRentalInvoices ? (
                          <button type="button" onClick={() => action(invoice, 'post')} title={pageDict.postToArGl} aria-label={pageDict.postToArGl} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.postToArGl}</button>
                        ) : null}
                        {isRentalInvoiceActionable(invoice) && canCancelRentalInvoices ? (
                          <button type="button" onClick={() => action(invoice, 'cancel')} title={pageDict.cancel} aria-label={pageDict.cancel} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancel}</button>
                        ) : null}
                        {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
