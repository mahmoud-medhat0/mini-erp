import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { DeliveryNotesDataTable } from '../../Components/OperationalReportDataTables';
import ReportFilterPanel from '../../Components/ReportFilterPanel';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

interface DeliveryNoteReportRow {
    id: string;
    delivery_number: string;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    warehouse_id: string | null;
    warehouse_code: string;
    warehouse_name: Record<string, string> | string | null;
    delivery_date: string;
    status: string;
    delivered_quantity_e6: number;
    lines_count: number;
}

interface DeliveryNotesReportProps extends SharedPageProps {
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
        warehouse_id: string;
        search: string;
    };
    customers: Array<{ id: string; code: string; name: string }>;
    products: Array<{ id: string; code: string; name: string }>;
    warehouses: Array<{ id: string; code: string; name: Record<string, string> | string; is_default?: boolean }>;
}

export default function DeliveryNotesReport({ locale, reportData, filters, customers, products, warehouses }: DeliveryNotesReportProps) {
    const dict = getDictionary(locale);
    const pageDict = dict.app.pages.reports;
    const notAvailable = dict.app.accounting.notAvailable;
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [status, setStatus] = useState(filters.status || '');
    const [customerId, setCustomerId] = useState(filters.customer_id || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
    const [search, setSearch] = useState(filters.search || '');
    const activeFilterCount = [dateFrom, dateTo, status, customerId, productId, warehouseId, search].filter(Boolean).length;
    const statusOptions = [
        { value: '', label: pageDict.allStatuses },
        { value: 'draft', label: pageDict.draft },
        { value: 'confirmed', label: pageDict.confirmed },
        { value: 'cancelled', label: pageDict.cancelled },
    ];
    const customerOptions = [
        { value: '', label: pageDict.allCustomers },
        ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${getLocalizedName(c.name, locale)}` })),
    ];
    const productOptions = [
        { value: '', label: pageDict.allProducts },
        ...products.map((p) => ({ value: p.id, label: `${p.code} - ${getLocalizedName(p.name, locale)}` })),
    ];
    const warehouseOptions = [
        { value: '', label: pageDict.allWarehouses },
        ...warehouses.map((warehouse) => ({
            value: warehouse.id,
            label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
        })),
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/delivery-notes', {
            date_from: dateFrom,
            date_to: dateTo,
            status,
            customer_id: customerId,
            product_id: productId,
            warehouse_id: warehouseId,
            search,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        setStatus('');
        setCustomerId('');
        setProductId('');
        setWarehouseId('');
        setSearch('');
        router.get('/reports/delivery-notes', {}, { preserveState: true });
    };

    const numberLocale = locale === 'ar' ? 'ar-EG' : 'en-US';
    const formatQty = (e6: number) => (e6 / 1000000).toLocaleString(numberLocale, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    return (
        <AppLayout active="reports.delivery-notes">
            <Head title={pageDict.deliveryNotesHeadTitle} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title={pageDict.deliveryNotesTitle}
                    description={pageDict.deliveryNotesDescription}
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
                        <SearchableSelect label={pageDict.status} options={statusOptions} value={status} onChange={(value) => setStatus(value || '')} placeholder={pageDict.allStatuses} />
                        <SearchableSelect label={pageDict.customer} options={customerOptions} value={customerId} onChange={(value) => setCustomerId(value || '')} placeholder={pageDict.allCustomers} />
                        <SearchableSelect label={pageDict.product} options={productOptions} value={productId} onChange={(value) => setProductId(value || '')} placeholder={pageDict.allProducts} />
                        <SearchableSelect label={pageDict.warehouse} options={warehouseOptions} value={warehouseId} onChange={(value) => setWarehouseId(value || '')} placeholder={pageDict.allWarehouses} />
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{pageDict.search}</label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={pageDict.deliveryNotesSearchPlaceholder}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                    </ReportFilterPanel>
                </form>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalDeliveryNotes}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_notes_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalDeliveredQuantity}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{formatQty(reportData.summary.total_delivered_quantity_e6)}</div>
                    </Card>
                </div>

                <Card>
                    <DeliveryNotesDataTable
                        filters={filters}
                        labels={pageDict}
                        locale={locale}
                        notAvailable={notAvailable}
                    />
                </Card>
            </div>
        </AppLayout>
    );
}
