import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import ReportFilterPanel from '../../Components/ReportFilterPanel';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

interface SalesOrderReportRow {
    id: string;
    order_number: string;
    customer_name: string;
    customer_code: string;
    order_date: string;
    status: string;
    currency: string;
    total_minor: number;
    ordered_quantity_e6: number;
    lines_count: number;
}

interface SalesOrdersReportProps extends SharedPageProps {
    reportData: {
        rows: SalesOrderReportRow[];
        summary: {
            total_orders_count: number;
            total_quantity_e6: number;
            total_amount_minor: number;
        };
    };
    filters: {
        date_from: string;
        date_to: string;
        status: string;
        customer_id: string;
        product_id: string;
        currency: string;
        search: string;
    };
    customers: Array<{ id: string; code: string; name: string }>;
    products: Array<{ id: string; code: string; name: string }>;
    currencies: Array<{ code: string }>;
}

export default function SalesOrdersReport({ locale, reportData, filters, customers, products, currencies }: SalesOrdersReportProps) {
    const pageDict = getDictionary(locale).app.pages.reports;
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [status, setStatus] = useState(filters.status || '');
    const [customerId, setCustomerId] = useState(filters.customer_id || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [currency, setCurrency] = useState(filters.currency || '');
    const [search, setSearch] = useState(filters.search || '');
    const activeFilterCount = [dateFrom, dateTo, status, customerId, productId, currency, search].filter(Boolean).length;
    const currencyOptions = [
        { value: '', label: pageDict.allCurrencies },
        ...currencies.map((c) => ({ value: c.code, label: c.code })),
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/sales-orders', {
            date_from: dateFrom,
            date_to: dateTo,
            status,
            customer_id: customerId,
            product_id: productId,
            currency,
            search,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        setStatus('');
        setCustomerId('');
        setProductId('');
        setCurrency('');
        setSearch('');
        router.get('/reports/sales-orders', {}, { preserveState: true });
    };

    const getStatusTone = (st: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
        if (st === 'confirmed') return 'ok';
        if (st === 'cancelled') return 'danger';
        if (st === 'submitted') return 'warning';
        return 'muted';
    };

    const getStatusLabel = (st: string) => {
        if (st === 'draft') return pageDict.draft;
        if (st === 'submitted') return pageDict.submitted;
        if (st === 'confirmed') return pageDict.confirmed;
        if (st === 'cancelled') return pageDict.cancelled;

        return st;
    };

    const numberLocale = locale === 'ar' ? 'ar-EG' : 'en-US';
    const formatQty = (e6: number) => (e6 / 1000000).toLocaleString(numberLocale, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    return (
        <AppLayout active="reports.sales-orders">
            <Head title={pageDict.salesOrdersHeadTitle} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title={pageDict.salesOrdersTitle}
                    description={pageDict.salesOrdersDescription}
                />

                <form onSubmit={handleFilter}>
                    <ReportFilterPanel
                        activeFilterCount={activeFilterCount}
                        activeFilterLabel={pageDict.activeFilters}
                        actions={(
                            <>
                                <Button type="button" variant="secondary" onClick={handleReset} disabled={activeFilterCount === 0}>{pageDict.clearFilters}</Button>
                                <Button type="submit" variant="primary">{pageDict.filter}</Button>
                            </>
                        )}
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.dateFrom}</label>
                            <DatePicker value={dateFrom} onChange={(v) => setDateFrom(v || '')} />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.dateTo}</label>
                            <DatePicker value={dateTo} onChange={(v) => setDateTo(v || '')} />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.status}</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">{pageDict.allStatuses}</option>
                                <option value="draft">{pageDict.draft}</option>
                                <option value="submitted">{pageDict.submitted}</option>
                                <option value="confirmed">{pageDict.confirmed}</option>
                                <option value="cancelled">{pageDict.cancelled}</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.customer}</label>
                            <select
                                value={customerId}
                                onChange={(e) => setCustomerId(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">{pageDict.allCustomers}</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.code} - {c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.product}</label>
                            <select
                                value={productId}
                                onChange={(e) => setProductId(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            >
                                <option value="">{pageDict.allProducts}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>{p.code} - {p.name}</option>
                                ))}
                            </select>
                        </div>
                        <SearchableSelect
                            label={pageDict.currency}
                            options={currencyOptions}
                            value={currency}
                            onChange={(value) => setCurrency(value || '')}
                            placeholder={pageDict.allCurrencies}
                        />
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.search}</label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={pageDict.salesOrdersSearchPlaceholder}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                    </ReportFilterPanel>
                </form>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalSalesOrders}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_orders_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalOrderedQuantity}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatQty(reportData.summary.total_quantity_e6)}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalValue}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">
                            {filters.currency ? formatMoney(reportData.summary.total_amount_minor, filters.currency) : pageDict.mixedCurrencyAmount}
                        </div>
                    </Card>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className={tableClasses.table}>
                            <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className={tableClasses.th}>{pageDict.orderNumber}</th>
                                    <th className={tableClasses.th}>{pageDict.customer}</th>
                                    <th className={tableClasses.th}>{pageDict.date}</th>
                                    <th className={tableClasses.th}>{pageDict.status}</th>
                                    <th className={tableClasses.th}>{pageDict.currency}</th>
                                    <th className={tableClasses.th}>{pageDict.qty}</th>
                                    <th className={tableClasses.th}>{pageDict.totalAmount}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {reportData.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="p-4 text-center">
                                            <EmptyState title={pageDict.emptySalesOrders} />
                                        </td>
                                    </tr>
                                ) : (
                                    reportData.rows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td className={`${tableClasses.td} font-medium`}>{row.order_number}</td>
                                            <td className={tableClasses.td}>{row.customer_code} - {row.customer_name}</td>
                                            <td className={tableClasses.td}>{row.order_date}</td>
                                            <td className={tableClasses.td}><StatusBadge tone={getStatusTone(row.status)}>{getStatusLabel(row.status)}</StatusBadge></td>
                                            <td className={tableClasses.td}>{row.currency}</td>
                                            <td className={tableClasses.td}>{formatQty(row.ordered_quantity_e6)}</td>
                                            <td className={`${tableClasses.td} font-semibold`}>{formatMoney(row.total_minor, row.currency)}</td>
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
