import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';

interface StockMovementReportRow {
    id: string;
    movement_date: string;
    movement_type: 'receipt' | 'issue' | 'reversal';
    source_type: string;
    source_id: string;
    source_line_id: string | null;
    product_id: string;
    product_name: string;
    product_code: string;
    uom_code: string;
    currency: string;
    quantity_delta_e6: number;
    value_delta_minor: number;
    unit_cost_e6: number;
    balance_quantity_e6: number;
    balance_valuation_amount_minor: number;
    journal_entry_id: string | null;
    journal_entry_number: string | null;
}

interface StockMovementsReportProps {
    reportData: {
        rows: StockMovementReportRow[];
        summary: {
            total_movements_count: number;
            total_quantity_delta_e6: number;
            total_value_delta_minor: number;
        };
    };
    filters: {
        date_from: string;
        date_to: string;
        movement_type: string;
        product_id: string;
        currency: string;
        search: string;
    };
    products: Array<{ id: string; code: string; name: string }>;
}

export default function StockMovementsReport({ reportData, filters, products }: StockMovementsReportProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [movementType, setMovementType] = useState(filters.movement_type || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [currency, setCurrency] = useState(filters.currency || '');
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/stock-movements', {
            date_from: dateFrom,
            date_to: dateTo,
            movement_type: movementType,
            product_id: productId,
            currency,
            search,
        }, { preserveState: true });
    };

    const getMovementTone = (mt: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
        if (mt === 'receipt') return 'ok';
        if (mt === 'issue') return 'warning';
        return 'muted';
    };

    const formatQty = (e6: number) => (e6 / 1000000).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    return (
        <AppLayout active="reports.stock-movements">
            <Head title="Stock Movements Report / تقرير حركة المخزون" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title="Stock Movement Ledger / سجل حركات المخزون"
                    description="Immutable read-only audit register of all stock receipts and issues"
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
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Movement Type / نوع الحركة</label>
                            <select
                                value={movementType}
                                onChange={(e) => setMovementType(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">All Types</option>
                                <option value="receipt">Receipt / إستلام</option>
                                <option value="issue">Issue / صرف</option>
                                <option value="reversal">Reversal / إسترجاع</option>
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
                                placeholder="Source, Product..."
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                        <div className="md:col-span-3 flex justify-end gap-2">
                            <Button type="submit" variant="primary">Filter / تصفية</Button>
                        </div>
                    </form>
                </Card>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Total Movement Records / إجمالي الحركات</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_movements_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Net Quantity Delta / صافي حركة الكمية</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatQty(reportData.summary.total_quantity_delta_e6)}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">Net Value Delta / صافي حركة التقييم</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatMoney(reportData.summary.total_value_delta_minor, filters.currency || 'EGP')}</div>
                    </Card>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className={tableClasses.table}>
                            <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className={tableClasses.th}>Date / التاريخ</th>
                                    <th className={tableClasses.th}>Type / النوع</th>
                                    <th className={tableClasses.th}>Source / المصدر</th>
                                    <th className={tableClasses.th}>Product / المنتج</th>
                                    <th className={tableClasses.th}>Qty Delta / تغير الكمية</th>
                                    <th className={tableClasses.th}>Value Delta / تغير القيمة</th>
                                    <th className={tableClasses.th}>Post Balance / الرصيد بعد الحركة</th>
                                    <th className={tableClasses.th}>Journal / القيد</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {reportData.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="p-4 text-center">
                                            <EmptyState title="No stock movement records found / لا توجد حركات مخزون" />
                                        </td>
                                    </tr>
                                ) : (
                                    reportData.rows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td className={tableClasses.td}>{row.movement_date}</td>
                                            <td className={tableClasses.td}><StatusBadge tone={getMovementTone(row.movement_type)}>{row.movement_type}</StatusBadge></td>
                                            <td className={tableClasses.td}>
                                                <span className="font-mono text-xs text-slate-700 dark:text-slate-300">{row.source_type}</span>
                                            </td>
                                            <td className={tableClasses.td}>{row.product_code} - {row.product_name}</td>
                                            <td className={`${tableClasses.td} font-medium ${row.quantity_delta_e6 >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
                                                {row.quantity_delta_e6 >= 0 ? '+' : ''}{formatQty(row.quantity_delta_e6)} {row.uom_code}
                                            </td>
                                            <td className={`${tableClasses.td} font-medium ${row.value_delta_minor >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
                                                {row.value_delta_minor >= 0 ? '+' : ''}{formatMoney(row.value_delta_minor, row.currency)}
                                            </td>
                                            <td className={tableClasses.td}>
                                                {formatQty(row.balance_quantity_e6)} {row.uom_code} ({formatMoney(row.balance_valuation_amount_minor, row.currency)})
                                            </td>
                                            <td className={tableClasses.td}>
                                                {row.journal_entry_number ? (
                                                    <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{row.journal_entry_number}</span>
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
