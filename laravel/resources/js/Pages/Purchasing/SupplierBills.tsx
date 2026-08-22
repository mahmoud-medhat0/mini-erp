import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

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

type BillLineForm = {
  product_id: string;
  unit_of_measure_id: string;
  purchase_order_line_id?: string | null;
  goods_receipt_line_id?: string | null;
  description: string;
  quantity: number; // Decimal UI input
  unit_cost: number; // Decimal UI input
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
    product?: ProductOption | null;
    unitOfMeasure?: { id: string; code: string; name: string } | null;
  }>;
};

type SupplierBillsProps = SharedPageProps & {
  supplierBills: {
    data: SupplierBillRow[];
    links: any[];
  };
  activeSuppliers: SupplierOption[];
  eligibleProducts: ProductOption[];
  confirmedPurchaseOrders: any[];
  confirmedGoodsReceipts: any[];
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
  filters,
}: SupplierBillsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);
  const [editingBill, setEditingBill] = useState<SupplierBillRow | null>(null);
  const [sourceMode, setSourceMode] = useState<'manual' | 'purchase_order' | 'goods_receipt'>('manual');

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
    currency: 'USD',
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
      currency: 'USD',
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
      supplier_id: po.supplier_id,
      purchase_order_id: po.id,
      goods_receipt_id: '',
      currency: po.currency,
    }));

    if (po.lines) {
      const items: BillLineForm[] = po.lines
        .filter((l: any) => l.product?.type !== 'stock')
        .map((l: any) => ({
          product_id: l.product_id,
          unit_of_measure_id: l.unit_of_measure_id,
          purchase_order_line_id: l.id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_cost: l.unit_price_minor / 100,
        }));
      setLineItems(items);
    }
  };

  const handleGoodsReceiptSelect = (grId: string) => {
    const gr = confirmedGoodsReceipts.find((g) => g.id === grId);
    if (!gr) return;

    setData((d) => ({
      ...d,
      supplier_id: gr.supplier_id,
      goods_receipt_id: gr.id,
      purchase_order_id: '',
      currency: gr.currency || gr.purchaseOrder?.currency || 'USD',
    }));

    if (gr.lines) {
      const items: BillLineForm[] = gr.lines
        .filter((l: any) => l.product?.type !== 'stock')
        .map((l: any) => ({
          product_id: l.product_id,
          unit_of_measure_id: l.unit_of_measure_id,
          goods_receipt_line_id: l.id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_cost: (l.purchaseOrderLine?.unit_price_minor || 0) / 100,
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

  const updateLineItem = (index: number, field: keyof BillLineForm, value: any) => {
    const next = [...lineItems];
    const item = { ...next[index], [field]: value };

    if (field === 'product_id') {
      const prod = eligibleProducts.find((p) => p.id === value);
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
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/bills', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (billId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = isAr ? 'هل أنت تأكد من تقديم الفاتورة؟' : 'Submit this bill?';
    if (action === 'approve') confirmMsg = isAr ? 'هل أنت تأكد من اعتماد الفاتورة؟' : 'Approve this bill?';
    if (action === 'post') confirmMsg = isAr ? 'هل أنت تأكد من ترحيل الفاتورة إلى القيود وحسابات الموردين؟' : 'Post this bill to AP/GL?';
    if (action === 'cancel') confirmMsg = isAr ? 'هل أنت تأكد من إلغاء الفاتورة؟' : 'Cancel this bill?';

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/bills/${billId}/${action}`);
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
        return isAr ? 'مسودة' : 'Draft';
      case 'submitted':
        return isAr ? 'مقدمة' : 'Submitted';
      case 'approved':
        return isAr ? 'معتمدة' : 'Approved';
      case 'posted':
        return isAr ? 'رحلت' : 'Posted';
      case 'cancelled':
        return isAr ? 'ملغاة' : 'Cancelled';
      default:
        return status;
    }
  };

  const handleSearchFilter = (e: FormEvent) => {
    e.preventDefault();
    router.get(
      '/purchasing/bills',
      { search: searchFilter, status: statusFilter },
      { preserveState: true, replace: true }
    );
  };

  const previewTotalMinor = lineItems.reduce((sum, item) => {
    const qtyE6 = Math.round(item.quantity * 1000000);
    const costMinor = Math.round(item.unit_cost * 100);
    const lineTotal = Math.floor((qtyE6 * costMinor) / 1000000);
    return sum + lineTotal;
  }, 0);

  return (
    <AppLayout active="supplier-bills.index">
      <Head title={isAr ? 'فواتير الموردين' : 'Supplier Bills'} />

      <div className="space-y-6">
        <PageHeader
          title={isAr ? 'فواتير الموردين' : 'Supplier Bills'}
          description={isAr ? 'إدارة فواتير المشتريات وترحيلها لحسابات الموردين والشراء' : 'Manage purchasing supplier bills and AP/GL posting.'}
          actions={
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{isAr ? 'إنشاء فاتورة توريد' : 'Create Supplier Bill'}</span>
            </button>
          }
        />

        {/* Filters */}
        <Card className="p-4">
          <form onSubmit={handleSearchFilter} className="flex flex-wrap items-center gap-4">
            <div className="flex-1 min-w-[200px]">
              <input
                type="text"
                placeholder={isAr ? 'بحث برقم الفاتورة أو المورد...' : 'Search by bill number, supplier, or ref...'}
                value={searchFilter}
                onChange={(e) => setSearchFilter(e.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
              />
            </div>
            <div className="w-40">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
              >
                <option value="">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
                <option value="draft">{isAr ? 'مسودة' : 'Draft'}</option>
                <option value="submitted">{isAr ? 'مقدمة' : 'Submitted'}</option>
                <option value="approved">{isAr ? 'معتمدة' : 'Approved'}</option>
                <option value="posted">{isAr ? 'رحلت' : 'Posted'}</option>
                <option value="cancelled">{isAr ? 'ملغاة' : 'Cancelled'}</option>
              </select>
            </div>
            <button
              type="submit"
              className="rounded-md border border-[var(--border)] px-4 py-1.5 text-sm font-medium hover:bg-[var(--background)]"
            >
              {isAr ? 'تصفية' : 'Filter'}
            </button>
          </form>
        </Card>

        {/* Supplier Bills Table */}
        <Card className="overflow-hidden">
          {supplierBills.data.length === 0 ? (
            <EmptyState
              title={isAr ? 'لا توجد فواتير توريد' : 'No supplier bills found'}
              description={isAr ? 'قم بإنشاء فاتورة توريد جديدة للبدء.' : 'Create a new supplier bill to get started.'}
            />
          ) : (
            <div className={tableClasses.wrap}>
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{isAr ? 'رقم الفاتورة' : 'Bill Number'}</th>
                    <th className={tableClasses.th}>{isAr ? 'المورد' : 'Supplier'}</th>
                    <th className={tableClasses.th}>{isAr ? 'تاريخ الفاتورة' : 'Bill Date'}</th>
                    <th className={tableClasses.th}>{isAr ? 'الإجمالي' : 'Total'}</th>
                    <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                    <th className={`${tableClasses.th} text-end`}>{isAr ? 'الإجراءات' : 'Actions'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border)]">
                  {supplierBills.data.map((bill) => (
                    <tr key={bill.id}>
                      <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                        {bill.number || <span className="text-[var(--text-muted)]">{isAr ? 'مسودة' : 'Draft'}</span>}
                      </td>
                      <td className={`${tableClasses.td} font-medium`}>{bill.supplier?.name || '-'}</td>
                      <td className={tableClasses.td}>{bill.bill_date}</td>
                      <td className={`${tableClasses.td} font-mono font-semibold`}>
                        {formatMoney(bill.total_minor, bill.currency)}
                      </td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={getStatusTone(bill.status)}>
                          {getStatusLabel(bill.status)}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                        {bill.status === 'draft' ? (
                          <>
                            <button
                              type="button"
                              onClick={() => openEditModal(bill)}
                              className="text-xs font-semibold text-blue-600 hover:underline"
                            >
                              {isAr ? 'تعديل' : 'Edit'}
                            </button>
                            <button
                              type="button"
                              onClick={() => handleAction(bill.id, 'submit')}
                              className="text-xs font-semibold text-indigo-600 hover:underline"
                            >
                              {isAr ? 'تقديم' : 'Submit'}
                            </button>
                          </>
                        ) : null}

                        {bill.status === 'submitted' ? (
                          <button
                            type="button"
                            onClick={() => handleAction(bill.id, 'approve')}
                            className="text-xs font-semibold text-amber-600 hover:underline"
                          >
                            {isAr ? 'اعتماد' : 'Approve'}
                          </button>
                        ) : null}

                        {bill.status === 'approved' ? (
                          <button
                            type="button"
                            onClick={() => handleAction(bill.id, 'post')}
                            className="text-xs font-semibold text-emerald-600 hover:underline"
                          >
                            {isAr ? 'ترحيل' : 'Post'}
                          </button>
                        ) : null}

                        {bill.status !== 'posted' && bill.status !== 'cancelled' ? (
                          <button
                            type="button"
                            onClick={() => handleAction(bill.id, 'cancel')}
                            className="text-xs font-semibold text-rose-600 hover:underline"
                          >
                            {isAr ? 'إلغاء' : 'Cancel'}
                          </button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
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
                  ? isAr
                    ? 'تعديل فاتورة التوريد'
                    : 'Edit Supplier Bill'
                  : isAr
                  ? 'إنشاء فاتورة توريد جديدة'
                  : 'Create Supplier Bill'}
              </h2>

              <form onSubmit={handleSubmitForm} className="space-y-4">
                {/* Source Selection Mode */}
                {!editingBill && (
                  <div className="space-y-2">
                    <label className="block text-sm font-semibold">{isAr ? 'مصدر الفاتورة' : 'Bill Source'}</label>
                    <div className="flex gap-4">
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'manual'}
                          onChange={() => handleSourceModeChange('manual')}
                        />
                        {isAr ? 'يدوي (خدمات ومواد غير مخزنية)' : 'Manual (Service/Non-stock)'}
                      </label>
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'purchase_order'}
                          onChange={() => handleSourceModeChange('purchase_order')}
                        />
                        {isAr ? 'من أمر شراء مؤكد' : 'From Purchase Order'}
                      </label>
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                          type="radio"
                          name="sourceMode"
                          checked={sourceMode === 'goods_receipt'}
                          onChange={() => handleSourceModeChange('goods_receipt')}
                        />
                        {isAr ? 'من سند استلام مؤكد' : 'From Goods Receipt'}
                      </label>
                    </div>
                  </div>
                )}

                {sourceMode === 'purchase_order' && !editingBill && (
                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'اختر أمر الشراء' : 'Select Purchase Order'}</label>
                    <select
                      value={data.purchase_order_id}
                      onChange={(e) => handlePurchaseOrderSelect(e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    >
                      <option value="">{isAr ? '-- اختر امر شراء --' : '-- Select PO --'}</option>
                      {confirmedPurchaseOrders.map((po) => (
                        <option key={po.id} value={po.id}>
                          {po.number} ({po.supplier?.name}) - {po.currency}
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                {sourceMode === 'goods_receipt' && !editingBill && (
                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'اختر سند الاستلام' : 'Select Goods Receipt'}</label>
                    <select
                      value={data.goods_receipt_id}
                      onChange={(e) => handleGoodsReceiptSelect(e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    >
                      <option value="">{isAr ? '-- اختر سند استلام --' : '-- Select GR --'}</option>
                      {confirmedGoodsReceipts.map((gr) => (
                        <option key={gr.id} value={gr.id}>
                          {gr.number} ({gr.supplier?.name})
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'المورد' : 'Supplier'}</label>
                    <select
                      disabled={!!editingBill || sourceMode !== 'manual'}
                      value={data.supplier_id}
                      onChange={(e) => setData('supplier_id', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    >
                      {activeSuppliers.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name} ({s.code})
                        </option>
                      ))}
                    </select>
                    {errors.supplier_id && <p className="text-xs text-rose-600 mt-1">{errors.supplier_id}</p>}
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'تاريخ الفاتورة' : 'Bill Date'}</label>
                    <input
                      type="date"
                      value={data.bill_date}
                      onChange={(e) => setData('bill_date', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    />
                    {errors.bill_date && <p className="text-xs text-rose-600 mt-1">{errors.bill_date}</p>}
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'تاريخ الاستحقاق' : 'Due Date'}</label>
                    <input
                      type="date"
                      value={data.due_date}
                      onChange={(e) => setData('due_date', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    />
                    {errors.due_date && <p className="text-xs text-rose-600 mt-1">{errors.due_date}</p>}
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'مرجع المورد' : 'Supplier Ref'}</label>
                    <input
                      type="text"
                      value={data.supplier_reference}
                      onChange={(e) => setData('supplier_reference', e.target.value)}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'العملة' : 'Currency'}</label>
                    <input
                      type="text"
                      disabled
                      value={data.currency}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-sm text-[var(--text-muted)]"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-1">{isAr ? 'المرجع' : 'Reference'}</label>
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
                    <h3 className="text-sm font-bold">{isAr ? 'بنود الفاتورة' : 'Bill Line Items'}</h3>
                    {sourceMode === 'manual' && (
                      <button
                        type="button"
                        onClick={addLine}
                        className="text-xs font-semibold text-blue-600 hover:underline"
                      >
                        {isAr ? '+ إضافة بند' : '+ Add Line'}
                      </button>
                    )}
                  </div>

                  <div className="overflow-x-auto border border-[var(--border)] rounded-md">
                    <table className="w-full text-xs">
                      <thead className="bg-[var(--background)] border-b border-[var(--border)]">
                        <tr>
                          <th className="p-2 text-start">{isAr ? 'المنتج / الخدمة' : 'Product/Service'}</th>
                          <th className="p-2 text-start">{isAr ? 'الوصف' : 'Description'}</th>
                          <th className="p-2 text-start w-24">{isAr ? 'الكمية' : 'Qty'}</th>
                          <th className="p-2 text-start w-28">{isAr ? 'تكلفة الوحدة' : 'Unit Cost'}</th>
                          <th className="p-2 text-end w-32">{isAr ? 'الإجمالي' : 'Total'}</th>
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
                                <select
                                  disabled={sourceMode !== 'manual'}
                                  value={item.product_id}
                                  onChange={(e) => updateLineItem(idx, 'product_id', e.target.value)}
                                  className="w-full rounded border border-[var(--border)] bg-[var(--background)] p-1 text-xs"
                                >
                                  {eligibleProducts.map((p) => (
                                    <option key={p.id} value={p.id}>
                                      {p.code} - {getProductName(p)}
                                    </option>
                                  ))}
                                </select>
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
                    {isAr ? 'الإجمالي العام: ' : 'Grand Total: '}
                    <span className="font-mono">{formatMoney(previewTotalMinor, data.currency)}</span>
                  </div>

                  <div className="space-x-3 rtl:space-x-reverse">
                    <button
                      type="button"
                      onClick={closeModal}
                      className="rounded-md border border-[var(--border)] px-4 py-2 text-sm font-medium hover:bg-[var(--background)]"
                    >
                      {isAr ? 'إلغاء' : 'Cancel'}
                    </button>
                    <button
                      type="submit"
                      disabled={processing}
                      className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 disabled:opacity-50"
                    >
                      {editingBill
                        ? isAr
                          ? 'حفظ التعديلات'
                          : 'Save Changes'
                        : isAr
                        ? 'إنشاء الفاتورة'
                        : 'Create Bill'}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
