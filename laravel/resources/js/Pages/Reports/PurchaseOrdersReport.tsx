import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { PurchaseOrdersDataTable } from '../../Components/OperationalReportDataTables';
import ReportFilterPanel from '../../Components/ReportFilterPanel';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

interface PurchaseOrderReportRow {
    id: string;
    order_number: string;
    supplier_name: string;
    supplier_code: string;
    order_date: string;
    status: string;
    currency: string;
    total_minor: number;
    ordered_quantity_e6: number;
    lines_count: number;
}

interface PurchaseOrdersReportProps extends SharedPageProps {
    reportData: {
        rows: PurchaseOrderReportRow[];
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
        supplier_id: string;
        product_id: string;
        currency: string;
        search: string;
    };
    suppliers: Array<{ id: string; code: string; name: string }>;
    products: Array<{ id: string; code: string; name: string }>;
    currencies: Array<{ code: string }>;
}

export default function PurchaseOrdersReport({ locale, reportData, filters, suppliers, products, currencies }: PurchaseOrdersReportProps) {
    const dict = getDictionary(locale);
    const pageDict = dict.app.pages.reports;
    const notAvailable = dict.app.accounting.notAvailable;
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [status, setStatus] = useState(filters.status || '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
    const [productId, setProductId] = useState(filters.product_id || '');
    const [currency, setCurrency] = useState(filters.currency || '');
    const [search, setSearch] = useState(filters.search || '');
    const activeFilterCount = [dateFrom, dateTo, status, supplierId, productId, currency, search].filter(Boolean).length;
    const currencyOptions = [
        { value: '', label: pageDict.allCurrencies },
        ...currencies.map((c) => ({ value: c.code, label: c.code })),
    ];
    const statusOptions = [
        { value: '', label: pageDict.allStatuses },
        { value: 'draft', label: pageDict.draft },
        { value: 'submitted', label: pageDict.submitted },
        { value: 'confirmed', label: pageDict.confirmed },
        { value: 'cancelled', label: pageDict.cancelled },
    ];
    const supplierOptions = [
        { value: '', label: pageDict.allSuppliers },
        ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${getLocalizedName(s.name, locale)}` })),
    ];
    const productOptions = [
        { value: '', label: pageDict.allProducts },
        ...products.map((p) => ({ value: p.id, label: `${p.code} - ${getLocalizedName(p.name, locale)}` })),
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/purchase-orders', {
            date_from: dateFrom,
            date_to: dateTo,
            status,
            supplier_id: supplierId,
            product_id: productId,
            currency,
            search,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        setStatus('');
        setSupplierId('');
        setProductId('');
        setCurrency('');
        setSearch('');
        router.get('/reports/purchase-orders', {}, { preserveState: true });
    };

    const numberLocale = locale === 'ar' ? 'ar-EG' : 'en-US';
    const formatQty = (e6: number) => (e6 / 1000000).toLocaleString(numberLocale, { minimumFractionDigits: 2, maximumFractionDigits: 6 });

    return (
        <AppLayout active="reports.purchase-orders">
            <Head title={pageDict.purchaseOrdersHeadTitle} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title={pageDict.purchaseOrdersTitle}
                    description={pageDict.purchaseOrdersDescription}
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
                        <SearchableSelect
                            label={pageDict.status}
                            options={statusOptions}
                            value={status}
                            onChange={(value) => setStatus(value || '')}
                            placeholder={pageDict.allStatuses}
                        />
                        <SearchableSelect
                            label={pageDict.supplier}
                            options={supplierOptions}
                            value={supplierId}
                            onChange={(value) => setSupplierId(value || '')}
                            placeholder={pageDict.allSuppliers}
                        />
                        <SearchableSelect
                            label={pageDict.product}
                            options={productOptions}
                            value={productId}
                            onChange={(value) => setProductId(value || '')}
                            placeholder={pageDict.allProducts}
                        />
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
                                placeholder={pageDict.purchaseOrdersSearchPlaceholder}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                    </ReportFilterPanel>
                </form>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalPurchaseOrders}</div>
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
                    <PurchaseOrdersDataTable
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
