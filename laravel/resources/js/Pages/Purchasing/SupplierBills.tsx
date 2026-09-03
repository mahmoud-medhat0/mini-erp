import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string;
};

type ProductOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  type: 'service' | 'non_stock' | 'stock';
  unit_of_measure_id: string;
  unitOfMeasure?: {
    id: string;
    code: string;
    name: string;
  } | null;
};

type TaxCodeOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  calculation_mode: string;
  rates?: Array<{ rate_bps: number; effective_from: string }>;
};

type BillLineForm = {
  product_id: string;
  unit_of_measure_id: string;
  purchase_order_line_id?: string | null;
  goods_receipt_line_id?: string | null;
  description: string;
  quantity: number; // Decimal UI input
  unit_cost: number; // Decimal UI input
  tax_code_id?: string | null;
};

type SupplierBillRow = {
  id: string;
  number?: string | null;
  supplier_id: string;
  purchase_order_id?: string | null;
  goods_receipt_id?: string | null;
  supplier?: { id: string; name: string } | null;
  bill_date: string;
  due_date?: string | null;
  supplier_reference?: string | null;
  reference?: string | null;
  description?: string | null;
  currency: string;
  subtotal_minor: number;
  tax_amount_minor?: number;
  total_minor: number;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  lock_version: number;
  lines?: Array<{
    id: string;
    product_id: string;
    unit_of_measure_id: string;
    purchase_order_line_id?: string | null;
    goods_receipt_line_id?: string | null;
    description: string;
    quantity_e6: number;
    unit_cost_minor: number;
    line_total_minor: number;
    tax_code_id?: string | null;
    tax_rate_bps?: number;
    tax_amount_minor?: number;
    gross_amount_minor?: number;
    product?: ProductOption | null;
    unitOfMeasure?: { id: string; code: string; name: string } | null;
  }>;
};

type PendingSensitiveAction = {
  url: string;
  confirmCode: string;
  message: string;
};

type SupplierBillSourceLine = {
  id: string;
  product_id: string;
  unit_of_measure_id: string;
  description?: string | null;
  quantity_e6: number;
  unit_price_minor?: number;
  product?: ProductOption | null;
  purchaseOrderLine?: { unit_price_minor?: number | null } | null;
  purchase_order_line?: { unit_price_minor?: number | null } | null;
};

type ConfirmedPurchaseOrder = {
  id: string;
  number?: string | null;
  supplier_id?: string | null;
  currency?: string | null;
  supplier?: { id?: string; name?: string | null } | null;
  lines?: SupplierBillSourceLine[];
};

type ConfirmedGoodsReceipt = {
  id: string;
  number?: string | null;
  supplier_id?: string | null;
  currency?: string | null;
  supplier?: { id?: string; name?: string | null } | null;
  purchaseOrder?: ConfirmedPurchaseOrder | null;
  purchase_order?: ConfirmedPurchaseOrder | null;
  lines?: SupplierBillSourceLine[];
};

type SupplierBillsProps = SharedPageProps & {
  supplierBills: {
    data: SupplierBillRow[];
    links: PaginationLink[];
  };
  activeSuppliers: SupplierOption[];
  eligibleProducts: ProductOption[];
  confirmedPurchaseOrders: ConfirmedPurchaseOrder[];
  confirmedGoodsReceipts: ConfirmedGoodsReceipt[];
  taxCodes?: TaxCodeOption[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function SupplierBillsIndex({
  locale,
  supplierBills,
  activeSuppliers,
  eligibleProducts,
  confirmedPurchaseOrders,
  confirmedGoodsReceipts,
  taxCodes = [],
  filters,
}: SupplierBillsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.purchasingSupplierBills;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingBill, setEditingBill] = useState<SupplierBillRow | null>(null);
  const [sourceMode, setSourceMode] = useState<'manual' | 'purchase_order' | 'goods_receipt'>('manual');
  const [pendingSensitiveAction, setPendingSensitiveAction] = useState<PendingSensitiveAction | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const { data, setData, post, put, processing, errors, reset } = useForm<{
    supplier_id: string;
    purchase_order_id: string;
    goods_receipt_id: string;
    bill_date: string;
    due_date: string;
    currency: string;
    fx_rate_e6: number;
    supplier_reference: string;
    reference: string;
    description: string;
    lock_version?: number;
    lines: Array<{
      product_id: string;
      unit_of_measure_id: string;
      purchase_order_line_id?: string | null;
      goods_receipt_line_id?: string | null;
      description?: string;
      quantity_e6: number;
      unit_cost_minor: number;
    }>;
  }>({
    supplier_id: '',
    purchase_order_id: '',
    goods_receipt_id: '',
    bill_date: todayStr,
    due_date: todayStr,
    currency: '',
    fx_rate_e6: 1000000,
    supplier_reference: '',
    reference: '',
    description: '',
    lines: [],
  });

  const [lineItems, setLineItems] = useState<BillLineForm[]>([]);
  const [searchFilter, setSearchFilter] = useState(filters.search || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');

  const getProductName = (prod?: ProductOption | null): string => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    if (typeof prod.name === 'object' && prod.name !== null) {
      return isAr ? prod.name.ar || prod.name.en || '' : prod.name.en || prod.name.ar || '';
    }
    return '';
  };

  const getGoodsReceiptPurchaseOrder = (goodsReceipt: ConfirmedGoodsReceipt): ConfirmedPurchaseOrder | null => {
    return goodsReceipt.purchaseOrder ?? goodsReceipt.purchase_order ?? null;
  };

  const getSourceLineUnitCostMinor = (line: SupplierBillSourceLine): number => {
    return line.purchaseOrderLine?.unit_price_minor ?? line.purchase_order_line?.unit_price_minor ?? line.unit_price_minor ?? 0;
  };

  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'submitted', label: pageDict.submitted },
    { value: 'approved', label: pageDict.approved },
    { value: 'posted', label: pageDict.posted },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.submitted, pageDict.approved, pageDict.posted, pageDict.cancelled]);

  const supplierOptions = useMemo(() => activeSuppliers.map((supplier) => ({
    value: supplier.id,
    label: supplier.name,
    sublabel: supplier.code,
  })), [activeSuppliers]);

  const purchaseOrderOptions = useMemo(() => confirmedPurchaseOrders.map((purchaseOrder) => ({
    value: purchaseOrder.id,
    label: purchaseOrder.number || purchaseOrder.id,
    sublabel: `${getLocalizedName(purchaseOrder.supplier?.name, locale) || accDict.notAvailable} - ${purchaseOrder.currency || accDict.notAvailable}`,
  })), [confirmedPurchaseOrders, accDict.notAvailable]);

  const goodsReceiptOptions = useMemo(() => confirmedGoodsReceipts.map((goodsReceipt) => {
    const purchaseOrder = getGoodsReceiptPurchaseOrder(goodsReceipt);

    return {
      value: goodsReceipt.id,
      label: goodsReceipt.number || goodsReceipt.id,
      sublabel: `${getLocalizedName(goodsReceipt.supplier?.name, locale) || purchaseOrder?.supplier?.name || accDict.notAvailable} - ${goodsReceipt.currency || purchaseOrder?.currency || accDict.notAvailable}`,
    };
  }), [confirmedGoodsReceipts, accDict.notAvailable]);

  const productOptions = useMemo(() => eligibleProducts.map((product) => ({
    value: product.id,
    label: `${product.code} - ${getProductName(product)}`,
    sublabel: product.type,
  })), [eligibleProducts, isAr]);
  const canEditSupplierBills = can('purchasing.edit');
  const canSubmitSupplierBills = can('purchasing.submit');
  const canApproveSupplierBills = can('purchasing.approve');
  const canPostSupplierBills = can('purchasing.post') && can('view_financials');
  const canCancelSupplierBills = can('purchasing.cancel');

  const openCreateModal = () => {
    setEditingBill(null);
    setSourceMode('manual');
    reset();
    setData({
      supplier_id: activeSuppliers[0]?.id || '',
      purchase_order_id: '',
      goods_receipt_id: '',
      bill_date: todayStr,
      due_date: todayStr,
      currency: '',
      fx_rate_e6: 1000000,
      supplier_reference: '',
      reference: '',
      description: '',
      lines: [],
    });

    const defaultProduct = eligibleProducts[0];
    if (defaultProduct) {
      setLineItems([
        {
          product_id: defaultProduct.id,
          unit_of_measure_id: defaultProduct.unit_of_measure_id,
          description: getProductName(defaultProduct),
          quantity: 1,
          unit_cost: 10,
        },
      ]);
    } else {
      setLineItems([]);
    }
    setShowModal(true);
  };

  const openEditModal = (bill: SupplierBillRow) => {
    setEditingBill(bill);
    setSourceMode(bill.purchase_order_id ? 'purchase_order' : bill.goods_receipt_id ? 'goods_receipt' : 'manual');
    setData({
      supplier_id: bill.supplier_id,
      purchase_order_id: bill.purchase_order_id || '',
      goods_receipt_id: bill.goods_receipt_id || '',
      bill_date: bill.bill_date,
      due_date: bill.due_date || bill.bill_date,
      currency: bill.currency,
      fx_rate_e6: 1000000,
      supplier_reference: bill.supplier_reference || '',
      reference: bill.reference || '',
      description: bill.description || '',
      lock_version: bill.lock_version,
      lines: [],
    });

    if (bill.lines) {
      setLineItems(
        bill.lines.map((l) => ({
          product_id: l.product_id,
          unit_of_measure_id: l.unitOfMeasure?.id || '',
          purchase_order_line_id: l.purchase_order_line_id,
          goods_receipt_line_id: l.goods_receipt_line_id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_cost: l.unit_cost_minor / 100,
        }))
      );
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingBill(null);
    reset();
  };

  const handleSourceModeChange = (mode: 'manual' | 'purchase_order' | 'goods_receipt') => {
    setSourceMode(mode);
    if (mode === 'manual') {
      setData((d) => ({ ...d, purchase_order_id: '', goods_receipt_id: '' }));
      if (eligibleProducts[0]) {
        const p = eligibleProducts[0];
        setLineItems([
          {
            product_id: p.id,
            unit_of_measure_id: p.unit_of_measure_id,
            description: getProductName(p),
            quantity: 1,
            unit_cost: 10,
          },
        ]);
      }
    }
  };

  const handlePurchaseOrderSelect = (poId: string) => {
    const po = confirmedPurchaseOrders.find((p) => p.id === poId);
    if (!po) return;

    setData((d) => ({
      ...d,
      supplier_id: po.supplier_id || '',
      purchase_order_id: po.id,
      goods_receipt_id: '',
      currency: po.currency || '',
    }));

    if (po.lines) {
      const items: BillLineForm[] = po.lines
        .filter((line) => line.product?.type !== 'stock')
        .map((line) => ({
          product_id: line.product_id,
          unit_of_measure_id: line.unit_of_measure_id,
          purchase_order_line_id: line.id,
          description: line.description || getProductName(line.product),
          quantity: line.quantity_e6 / 1000000,
          unit_cost: (line.unit_price_minor ?? 0) / 100,
        }));
      setLineItems(items);
    }
  };

  const handleGoodsReceiptSelect = (grId: string) => {
    const gr = confirmedGoodsReceipts.find((g) => g.id === grId);
    if (!gr) return;

    setData((d) => ({
      ...d,
      supplier_id: gr.supplier_id || '',
      goods_receipt_id: gr.id,
      purchase_order_id: '',
      currency: gr.currency || getGoodsReceiptPurchaseOrder(gr)?.currency || '',
    }));

    if (gr.lines) {
      const items: BillLineForm[] = gr.lines
        .filter((line) => line.product?.type !== 'stock')
        .map((line) => ({
          product_id: line.product_id,
          unit_of_measure_id: line.unit_of_measure_id,
          goods_receipt_line_id: line.id,
          description: line.description || getProductName(line.product),
          quantity: line.quantity_e6 / 1000000,
          unit_cost: getSourceLineUnitCostMinor(line) / 100,
        }));
      setLineItems(items);
    }
  };

  const addLine = () => {
    const p = eligibleProducts[0];
    if (!p) return;
    setLineItems([
      ...lineItems,
      {
        product_id: p.id,
        unit_of_measure_id: p.unit_of_measure_id,
        description: getProductName(p),
        quantity: 1,
        unit_cost: 10,
      },
    ]);
  };

  const removeLine = (index: number) => {
    setLineItems(lineItems.filter((_, i) => i !== index));
  };

  const updateLineItem = <K extends keyof BillLineForm>(index: number, field: K, value: BillLineForm[K]) => {
    const next = [...lineItems];
    const item = { ...next[index], [field]: value };

    if (field === 'product_id') {
      const prod = eligibleProducts.find((p) => p.id === String(value ?? ''));
      if (prod) {
        item.unit_of_measure_id = prod.unit_of_measure_id;
        item.description = getProductName(prod);
      }
    }

    next[index] = item;
    setLineItems(next);
  };

  const handleSubmitForm = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      product_id: item.product_id,
      unit_of_measure_id: item.unit_of_measure_id,
      purchase_order_line_id: item.purchase_order_line_id || null,
      goods_receipt_line_id: item.goods_receipt_line_id || null,
      description: item.description,
      quantity_e6: Math.round(item.quantity * 1000000),
      unit_cost_minor: Math.round(item.unit_cost * 100),
    }));

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingBill) {
      router.put(`/purchasing/bills/${editingBill.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/bills', payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (billId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.purchasingSupplierBills.submitThisBill;
    if (action === 'approve') confirmMsg = dict.app.pages.purchasingSupplierBills.approveThisBill;
    if (action === 'post') confirmMsg = dict.app.pages.purchasingSupplierBills.postThisBillToApGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingSupplierBills.cancelThisBill;

    if (action === 'post') {
      setPendingSensitiveAction({
        url: `/purchasing/bills/${billId}/post`,
        confirmCode: 'POST_SUPPLIER_BILL',
        message: confirmMsg,
      });
      return;
    }

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/bills/${billId}/${action}`, {}, { preserveScroll: true });
    }
  };

  const getStatusTone = (status: string): 'muted' | 'info' | 'warning' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'submitted':
        return 'info';
      case 'approved':
        return 'warning';
      case 'posted':
        return 'ok';
      case 'cancelled':
        return 'danger';
      default:
        return 'muted';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'draft':
        return dict.app.pages.purchasingSupplierBills.draft;
      case 'submitted':
        return dict.app.pages.purchasingSupplierBills.submitted;
      case 'approved':
        return dict.app.pages.purchasingSupplierBills.approved;
      case 'posted':
        return dict.app.pages.purchasingSupplierBills.posted;
      case 'cancelled':
        return dict.app.pages.purchasingSupplierBills.cancelled;
      default:
        return status;
    }
  };

  const isSupplierBillActionable = (bill: SupplierBillRow) => ['draft', 'submitted', 'approved'].includes(bill.status);

  const hasAvailableSupplierBillAction = (bill: SupplierBillRow) => (
    bill.status === 'draft'
      ? canEditSupplierBills || canSubmitSupplierBills || canCancelSupplierBills
      : bill.status === 'submitted'
        ? canApproveSupplierBills || canCancelSupplierBills
        : bill.status === 'approved'
          ? canPostSupplierBills || canCancelSupplierBills
          : false
  );

  const getSupplierBillActionState = (bill: SupplierBillRow) => {
    if (hasAvailableSupplierBillAction(bill)) return null;

    return isSupplierBillActionable(bill) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  const handleSearchFilter = (e: FormEvent) => {
    e.preventDefault();
    router.get(
      '/purchasing/bills',
      { search: searchFilter, status: statusFilter },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  };

  const previewTotalMinor = lineItems.reduce((sum, item) => {
    const qtyE6 = Math.round(item.quantity * 1000000);
    const costMinor = Math.round(item.unit_cost * 100);
    const lineTotal = Math.floor((qtyE6 * costMinor) / 1000000);
    return sum + lineTotal;
  }, 0);
  const supplierBillSubmitLabel = editingBill ? pageDict.saveChanges : pageDict.createBill;

  return (
    <AppLayout active="supplier-bills.index">
      <Head title={dict.app.pages.purchasingSupplierBills.supplierBills} />

      <div className="space-y-6">
        <PageHeader
          title={dict.app.pages.purchasingSupplierBills.supplierBills_2}
          description={dict.app.pages.purchasingSupplierBills.managePurchasingSupplierBillsAndAp}
          actions={
            can('purchasing.create') ? (
              <button
                type="button"
                onClick={openCreateModal}
                title={dict.app.pages.purchasingSupplierBills.createSupplierBill}
                aria-label={dict.app.pages.purchasingSupplierBills.createSupplierBill}
                className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>{dict.app.pages.purchasingSupplierBills.createSupplierBill}</span>
              </button>
            ) : null
          }
        />

        {/* Filters */}
        <Card className="p-4">
          <form onSubmit={handleSearchFilter} className="flex flex-wrap items-center gap-4">
            <div className="flex-1 min-w-[200px]">
              <input
                type="text"
                placeholder={dict.app.pages.purchasingSupplierBills.searchByBillNumberSupplierOr}
                value={searchFilter}
                onChange={(e) => setSearchFilter(e.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
              />
            </div>
            <div className="w-40">
              <SearchableSelect
                options={statusFilterOptions}
                value={statusFilter || null}
                onChange={(value) => setStatusFilter(value || '')}
                label={dict.app.pages.purchasingSupplierBills.status}
              />
            </div>
            <button
              type="submit"
              title={dict.app.pages.purchasingSupplierBills.filter}
              aria-label={dict.app.pages.purchasingSupplierBills.filter}
              className="rounded-md border border-[var(--border)] px-4 py-1.5 text-sm font-medium hover:bg-[var(--background)]"
            >
              {dict.app.pages.purchasingSupplierBills.filter}
            </button>
          </form>
        </Card>

        {/* Supplier Bills Table */}
        <Card className="overflow-hidden">
          {supplierBills.data.length === 0 ? (
            <EmptyState
              title={dict.app.pages.purchasingSupplierBills.noSupplierBillsFound}
              description={dict.app.pages.purchasingSupplierBills.createANewSupplierBillTo}
            />
          ) : (
            <div className={tableClasses.wrap}>
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierBills.billNumber}</th>
                    <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierBills.supplier}</th>
                    <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierBills.billDate}</th>
                    <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierBills.total}</th>
                    <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierBills.status}</th>
                    <th className={`${tableClasses.th} text-end`}>{dict.app.pages.purchasingSupplierBills.actions}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border)]">
                  {supplierBills.data.map((bill) => {
                    const actionState = getSupplierBillActionState(bill);

                    return (
                      <tr key={bill.id}>
                        <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                          {bill.number || <span className="text-[var(--text-muted)]">{dict.app.pages.purchasingSupplierBills.draft_2}</span>}
                        </td>
                        <td className={`${tableClasses.td} font-medium`}>{getLocalizedName(bill.supplier?.name, locale) || accDict.notAvailable}</td>
                        <td className={tableClasses.td}>{bill.bill_date}</td>
                        <td className={`${tableClasses.td} font-mono font-semibold`}>
                          {formatMoney(bill.total_minor, bill.currency)}
                        </td>
                        <td className={tableClasses.td}>
                          <StatusBadge tone={getStatusTone(bill.status)}>
                            {getStatusLabel(bill.status)}
                          </StatusBadge>
                        </td>
                        <td className={`${tableClasses.td} text-end`}>
                          <div className="flex flex-wrap items-center justify-end gap-2">
                            {bill.status === 'draft' && canEditSupplierBills ? (
                              <button
                                type="button"
                                onClick={() => openEditModal(bill)}
                                title={dict.app.pages.purchasingSupplierBills.edit}
                                aria-label={dict.app.pages.purchasingSupplierBills.edit}
                                className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                              >
                                {dict.app.pages.purchasingSupplierBills.edit}
                              </button>
                            ) : null}

                            {bill.status === 'draft' && canSubmitSupplierBills ? (
                              <button
                                type="button"
                                onClick={() => handleAction(bill.id, 'submit')}
                                title={dict.app.pages.purchasingSupplierBills.submit}
                                aria-label={dict.app.pages.purchasingSupplierBills.submit}
                                className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40"
                              >
                                {dict.app.pages.purchasingSupplierBills.submit}
                              </button>
                            ) : null}

                            {bill.status === 'submitted' && canApproveSupplierBills ? (
                              <button
                                type="button"
                                onClick={() => handleAction(bill.id, 'approve')}
                                title={dict.app.pages.purchasingSupplierBills.approve}
                                aria-label={dict.app.pages.purchasingSupplierBills.approve}
                                className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                              >
                                {dict.app.pages.purchasingSupplierBills.approve}
                              </button>
                            ) : null}

                            {bill.status === 'approved' && canPostSupplierBills ? (
                              <button
                                type="button"
                                onClick={() => handleAction(bill.id, 'post')}
                                title={dict.app.pages.purchasingSupplierBills.post}
                                aria-label={dict.app.pages.purchasingSupplierBills.post}
                                className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                              >
                                {dict.app.pages.purchasingSupplierBills.post}
                              </button>
                            ) : null}

                            {isSupplierBillActionable(bill) && canCancelSupplierBills ? (
                              <button
                                type="button"
                                onClick={() => handleAction(bill.id, 'cancel')}
                                title={dict.app.pages.purchasingSupplierBills.cancel}
                                aria-label={dict.app.pages.purchasingSupplierBills.cancel}
                                className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                              >
                                {dict.app.pages.purchasingSupplierBills.cancel}
                              </button>
                            ) : null}

                            {actionState ? (
                              <StatusBadge tone="muted">{actionState}</StatusBadge>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>

        {/* Modal Form */}
        {showModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-lg bg-[var(--background)] p-6 shadow-xl border border-[var(--border)]">
              <h2 className="text-lg font-bold mb-4">
                {editingBill
                  ? dict.app.pages.purchasingSupplierBills.editSupplierBill
                  : dict.app.pages.purchasingSupplierBills.createSupplierBill_2}
              </h2>

              <form onSubmit={handleSubmitForm} className="space-y-4">
                {/* Source Selection Mode */}
                {!editingBill && (
                  <div className="space-y-2">
                    <label className="block text-sm font-semibold">{dict.app.pages.purchasingSupplierBills.billSource}</label>
                    <div className="flex gap-4">
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'manual'}
                          onChange={() => handleSourceModeChange('manual')}
                        />
                        {dict.app.pages.purchasingSupplierBills.manualServiceNonStock}
                      </label>
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'purchase_order'}
                          onChange={() => handleSourceModeChange('purchase_order')}
                        />
                        {dict.app.pages.purchasingSupplierBills.fromPurchaseOrder}
                      </label>
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'goods_receipt'}
                          onChange={() => handleSourceModeChange('goods_receipt')}
                        />
                        {dict.app.pages.purchasingSupplierBills.fromGoodsReceipt}
                      </label>
                    </div>
                  </div>
                )}

                {sourceMode === 'purchase_order' && !editingBill && (
                  <div>
                    <label className="block text-sm font-medium mb-1">{dict.app.pages.purchasingSupplierBills.selectPurchaseOrder}</label>
                    <SearchableSelect
                      options={purchaseOrderOptions}
                      value={data.purchase_order_id || null}
                      onChange={(value) => handlePurchaseOrderSelect(value || '')}
                      placeholder={dict.app.pages.purchasingSupplierBills.selectPo}
                      isClearable={false}
                    />
                  </div>
                )}

                {sourceMode === 'goods_receipt' && !editingBill && (
                  <div>
                    <label className="block text-sm font-medium mb-1">{dict.app.pages.purchasingSupplierBills.selectGoodsReceipt}</label>
                    <SearchableSelect
                      options={goodsReceiptOptions}
                      value={data.goods_receipt_id || null}
                      onChange={(value) => handleGoodsReceiptSelect(value || '')}
                      placeholder={dict.app.pages.purchasingSupplierBills.selectGr}
                      isClearable={false}
                    />
                  </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <SearchableSelect
                    label={dict.app.pages.purchasingSupplierBills.supplier_2}
                    disabled={!!editingBill || sourceMode !== 'manual'}
                    value={data.supplier_id || null}
                    onChange={(value) => setData('supplier_id', value || '')}
                    options={supplierOptions}
                    isClearable={false}
                    required
                    error={errors.supplier_id}
                  />

                  <DatePicker
                    label={dict.app.pages.purchasingSupplierBills.billDate_2}
                    value={data.bill_date}
                    onChange={(value) => setData('bill_date', value || '')}
                    error={errors.bill_date}
                  />

                  <DatePicker
                    label={dict.app.pages.purchasingSupplierBills.dueDate}
                    value={data.due_date}
                    onChange={(value) => setData('due_date', value || '')}
                    error={errors.due_date}
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">{dict.app.pages.purchasingSupplierBills.supplierRef}</label>
                    <input
                      type="text"
                      value={data.supplier_reference}
                      onChange={(e) => setData('supplier_reference', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{dict.app.pages.purchasingSupplierBills.currency}</label>
                    <input
                      type="text"
                      disabled
                      value={data.currency}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm text-[var(--text-muted)]"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{dict.app.pages.purchasingSupplierBills.reference}</label>
                    <input
                      type="text"
                      value={data.reference}
                      onChange={(e) => setData('reference', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    />
                  </div>
                </div>

                {/* Line Items Table */}
                <div className="space-y-2 pt-4">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-bold">{dict.app.pages.purchasingSupplierBills.billLineItems}</h3>
                    {sourceMode === 'manual' && (
                      <button
                        type="button"
                        onClick={addLine}
                        title={dict.app.pages.purchasingSupplierBills.addLine}
                        aria-label={dict.app.pages.purchasingSupplierBills.addLine}
                        className="text-xs font-semibold text-blue-600 hover:underline"
                      >
                        {dict.app.pages.purchasingSupplierBills.addLine}
                      </button>
                    )}
                  </div>

                  <div className="overflow-x-auto border border-[var(--border)] rounded-md">
                    <table className="w-full text-xs">
                      <thead className="bg-[var(--background)] border-b border-[var(--border)]">
                        <tr>
                          <th className="p-2 text-start">{dict.app.pages.purchasingSupplierBills.productService}</th>
                          <th className="p-2 text-start">{dict.app.pages.purchasingSupplierBills.description}</th>
                          <th className="p-2 text-start w-24">{dict.app.pages.purchasingSupplierBills.qty}</th>
                          <th className="p-2 text-start w-28">{dict.app.pages.purchasingSupplierBills.unitCost}</th>
                          <th className="p-2 text-end w-32">{dict.app.pages.purchasingSupplierBills.total_2}</th>
                          {sourceMode === 'manual' && <th className="p-2 w-10"></th>}
                        </tr>
                      </thead>
                      <tbody>
                        {lineItems.map((item, idx) => {
                          const lineTotal = Math.floor(
                            (Math.round(item.quantity * 1000000) * Math.round(item.unit_cost * 100)) / 1000000
                          );

                          return (
                            <tr key={idx} className="border-b border-[var(--border)] last:border-0">
                              <td className="p-2">
                                <SearchableSelect
                                  disabled={sourceMode !== 'manual'}
                                  value={item.product_id || null}
                                  onChange={(value) => updateLineItem(idx, 'product_id', value || '')}
                                  options={productOptions}
                                  isClearable={false}
                                  required
                                />
                              </td>
                              <td className="p-2">
                                <input
                                  type="text"
                                  value={item.description}
                                  onChange={(e) => updateLineItem(idx, 'description', e.target.value)}
                                  className="w-full rounded border border-[var(--border)] bg-[var(--background)] p-1 text-xs"
                                />
                              </td>
                              <td className="p-2">
                                <input
                                  type="number"
                                  step="0.000001"
                                  min="0.000001"
                                  value={item.quantity}
                                  onChange={(e) => updateLineItem(idx, 'quantity', parseFloat(e.target.value) || 0)}
                                  className="w-full rounded border border-[var(--border)] bg-[var(--background)] p-1 text-xs"
                                />
                              </td>
                              <td className="p-2">
                                <input
                                  type="number"
                                  step="0.01"
                                  min="0"
                                  disabled={sourceMode !== 'manual'}
                                  value={item.unit_cost}
                                  onChange={(e) => updateLineItem(idx, 'unit_cost', parseFloat(e.target.value) || 0)}
                                  className="w-full rounded border border-[var(--border)] bg-[var(--background)] p-1 text-xs"
                                />
                              </td>
                              <td className="p-2 text-end font-mono font-semibold">
                                {formatMoney(lineTotal, data.currency)}
                              </td>
                              {sourceMode === 'manual' && (
                                <td className="p-2 text-center">
                                  <button
                                    type="button"
                                    onClick={() => removeLine(idx)}
                                    title={`${dict.app.pages.purchasingSupplierBills.removeLine} ${idx + 1}`}
                                    aria-label={`${dict.app.pages.purchasingSupplierBills.removeLine} ${idx + 1}`}
                                    className="text-rose-600 hover:underline font-bold"
                                  >
                                    ×
                                  </button>
                                </td>
                              )}
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="flex items-center justify-between pt-4 border-t border-[var(--border)]">
                  <div className="text-base font-bold">
                    {dict.app.pages.purchasingSupplierBills.grandTotal}
                    <span className="font-mono">{formatMoney(previewTotalMinor, data.currency)}</span>
                  </div>

                  <div className="space-x-3 rtl:space-x-reverse">
                    <button
                      type="button"
                      onClick={closeModal}
                      title={dict.app.pages.purchasingSupplierBills.cancel_2}
                      aria-label={dict.app.pages.purchasingSupplierBills.cancel_2}
                      className="rounded-md border border-[var(--border)] px-4 py-2 text-sm font-medium hover:bg-[var(--background)]"
                    >
                      {dict.app.pages.purchasingSupplierBills.cancel_2}
                    </button>
                    <button
                      type="submit"
                      disabled={processing}
                      title={supplierBillSubmitLabel}
                      aria-label={supplierBillSubmitLabel}
                      className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 disabled:opacity-50"
                    >
                      {editingBill
                        ? dict.app.pages.purchasingSupplierBills.saveChanges
                        : dict.app.pages.purchasingSupplierBills.createBill}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>

      <SensitiveActionModal
        isOpen={pendingSensitiveAction !== null}
        onClose={() => setPendingSensitiveAction(null)}
        onConfirm={(payload) => {
          if (!pendingSensitiveAction) return;
          router.post(pendingSensitiveAction.url, payload, {
            preserveScroll: true,
            onSuccess: () => setPendingSensitiveAction(null),
          });
        }}
        confirmCode={pendingSensitiveAction?.confirmCode ?? 'POST_SUPPLIER_BILL'}
        message={pendingSensitiveAction?.message}
        locale={locale}
      />
    </AppLayout>
  );
}
