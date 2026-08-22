import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';

interface DeliveryNoteReportRow {
    id: string;
    delivery_number: string;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    delivery_date: string;
    status: string;
    delivered_quantity_e6: number;
    lines_count: number;
}

interface DeliveryNotesReportProps {
    reportData: {
        rows: DeliveryNoteReportRow[];
        summary: {
            total_notes_count: number;
            total_delivered_quantity_e6: number;
        };
    };
    filters: {
        date_from: string;
        date_to: string;
        status: string;
        customer_id: string;
        product_id: string;
        search: string;
    };
    customers: Array<{ id: string; code: string; name: string }>;
    products: Array<{ id: string; code: string; name: string }>;
}

export default function DeliveryNotesReport({ reportData, filters, customers, products }: DeliveryNotesReportProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [status, setStatus] = useState(filters.status || '');
    const [customerId, setCustomerId] = useState(filters.customer_id || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/delivery-notes', {
            date_from: dateFrom,
            date_to: dateTo,
            status,
            customer_id: customerId,
            product_id: productId,
            search,
        }, { preserveState: true });
    };

    const getStatusTone = (st: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
        if (st === 'confirmed') return 'ok';
        if (st === 'cancelled') return 'danger';
        return 'muted';
    };

    const formatQty = (e6: number) => (e6 / 1000000).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    return (
        <AppLayout active="reports.delivery-notes">
            <Head title="Delivery Notes Report / تقرير إذونات التسليم" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Delivery Notes Register / سجل إذونات التسليم"
                    description="Read-only operational register of all delivery notes"
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
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Customer / العميل</label>
                            <select
                                value={customerId}
                                onChange={(e) => setCustomerId(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">All Customers</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.code} - {c.name}</option>
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
                                placeholder="DN #, Order #, Customer..."
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
                        <div className="text-xs font-medium text-slate-500">Total Delivery Notes / إجمالي إذونات التسليم</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_notes_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Total Delivered Quantity / إجمالي الكمية المسلمة</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatQty(reportData.summary.total_delivered_quantity_e6)}</div>
                    </Card>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className={tableClasses.table}>
                            <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className={tableClasses.th}>Delivery # / رقم إذن التسليم</th>
                                    <th className={tableClasses.th}>Sales Order # / أمر البيع</th>
                                    <th className={tableClasses.th}>Customer / العميل</th>
                                    <th className={tableClasses.th}>Date / التاريخ</th>
                                    <th className={tableClasses.th}>Status / الحالة</th>
                                    <th className={tableClasses.th}>Delivered Qty / الكمية المسلمة</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {reportData.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-4 text-center">
                                            <EmptyState title="No delivery notes found / لا توجد إذونات تسليم" />
                                        </td>
                                    </tr>
                                ) : (
                                    reportData.rows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td className={`${tableClasses.td} font-medium`}>{row.delivery_number}</td>
                                            <td className={tableClasses.td}>{row.sales_order_number}</td>
                                            <td className={tableClasses.td}>{row.customer_code} - {row.customer_name}</td>
                                            <td className={tableClasses.td}>{row.delivery_date}</td>
                                            <td className={tableClasses.td}><StatusBadge tone={getStatusTone(row.status)}>{row.status}</StatusBadge></td>
                                            <td className={`${tableClasses.td} font-semibold`}>{formatQty(row.delivered_quantity_e6)}</td>
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
