import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';

interface SupplierBillReportRow {
    id: string;
    bill_number: string;
    supplier_name: string;
    supplier_code: string;
    bill_date: string;
    due_date: string;
    status: string;
    currency: string;
    total_minor: number;
    journal_entry_id: string | null;
    journal_entry_number: string | null;
    payable_entry_id: string | null;
    lines_count: number;
}

interface SupplierBillsReportProps {
    reportData: {
        rows: SupplierBillReportRow[];
        summary: {
            total_bills_count: number;
            total_amount_minor: number;
        };
    };
    filters: {
        date_from: string;
        date_to: string;
        status: string;
        supplier_id: string;
        product_id: string;
        currency: string;
        search: string;
    };
    suppliers: Array<{ id: string; code: string; name: string }>;
    products: Array<{ id: string; code: string; name: string }>;
}

export default function SupplierBillsReport({ reportData, filters, suppliers, products }: SupplierBillsReportProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [status, setStatus] = useState(filters.status || '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [currency, setCurrency] = useState(filters.currency || '');
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/supplier-bills', {
            date_from: dateFrom,
            date_to: dateTo,
            status,
            supplier_id: supplierId,
            product_id: productId,
            currency,
            search,
        }, { preserveState: true });
    };

    const getStatusTone = (st: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
        if (st === 'posted') return 'ok';
        if (st === 'cancelled') return 'danger';
        if (st === 'approved' || st === 'submitted') return 'warning';
        return 'muted';
    };

    return (
        <AppLayout active="reports.supplier-bills">
            <Head title="Supplier Bills Report / تقرير فواتير الموردين" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Supplier Bills Register / سجل فواتير الموردين"
                    description="Read-only operational register of all supplier bills with accounting links"
                />

                <Card>
                    <form onSubmit={handleFilter} className="p-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Date From / من تاريخ</label>
                            <DatePicker value={dateFrom} onChange={(v) => setDateFrom(v || '')} />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Date To / إلى تاريخ</label>
                            <DatePicker value={dateTo} onChange={(v) => setDateTo(v || '')} />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Status / الحالة</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="approved">Approved</option>
                                <option value="posted">Posted</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Supplier / المورد</label>
                            <select
                                value={supplierId}
                                onChange={(e) => setSupplierId(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">All Suppliers</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Product / المنتج</label>
                            <select
                                value={productId}
                                onChange={(e) => setProductId(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">All Products</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>{p.code} - {p.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Search / بحث</label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Bill #, Supplier..."
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                        <div className="md:col-span-2 flex justify-end gap-2">
                            <Button type="submit" variant="primary">Filter / تصفية</Button>
                        </div>
                    </form>
                </Card>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Total Supplier Bills / إجمالي فواتير الموردين</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_bills_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Total Billed Amount / إجمالي المبلغ المفوتر</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatMoney(reportData.summary.total_amount_minor, filters.currency || 'EGP')}</div>
                    </Card>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className={tableClasses.table}>
                            <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className={tableClasses.th}>Bill # / رقم الفاتورة</th>
                                    <th className={tableClasses.th}>Supplier / المورد</th>
                                    <th className={tableClasses.th}>Date / التاريخ</th>
                                    <th className={tableClasses.th}>Due Date / الاستحقاق</th>
                                    <th className={tableClasses.th}>Status / الحالة</th>
                                    <th className={tableClasses.th}>Total / المبلغ</th>
                                    <th className={tableClasses.th}>Journal / القيد</th>
                                    <th className={tableClasses.th}>AP Entry / قيد الذمم</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {reportData.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="p-4 text-center">
                                            <EmptyState title="No supplier bills found / لا توجد فواتير موردين" />
                                        </td>
                                    </tr>
                                ) : (
                                    reportData.rows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td className={`${tableClasses.td} font-medium`}>{row.bill_number}</td>
                                            <td className={tableClasses.td}>{row.supplier_code} - {row.supplier_name}</td>
                                            <td className={tableClasses.td}>{row.bill_date}</td>
                                            <td className={tableClasses.td}>{row.due_date}</td>
                                            <td className={tableClasses.td}><StatusBadge tone={getStatusTone(row.status)}>{row.status}</StatusBadge></td>
                                            <td className={`${tableClasses.td} font-semibold`}>{formatMoney(row.total_minor, row.currency)}</td>
                                            <td className={tableClasses.td}>
                                                {row.journal_entry_number ? (
                                                    <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{row.journal_entry_number}</span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className={tableClasses.td}>
                                                {row.payable_entry_id ? (
                                                    <span className="text-xs font-mono text-purple-600 dark:text-purple-400 font-semibold">AP-{row.payable_entry_id.substring(0, 8)}</span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
