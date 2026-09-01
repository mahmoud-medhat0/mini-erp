import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { CustomerInvoicesDataTable } from '../../Components/OperationalReportDataTables';
import ReportFilterPanel from '../../Components/ReportFilterPanel';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

interface CustomerInvoiceReportRow {
    id: string;
    invoice_number: string;
    customer_name: string;
    customer_code: string;
    invoice_date: string;
    due_date: string;
    status: string;
    currency: string;
    total_minor: number;
    journal_entry_id: string | null;
    journal_entry_number: string | null;
    receivable_entry_id: string | null;
    lines_count: number;
}

interface CustomerInvoicesReportProps extends SharedPageProps {
    reportData: {
        rows: CustomerInvoiceReportRow[];
        summary: {
            total_invoices_count: number;
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

export default function CustomerInvoicesReport({ locale, reportData, filters, customers, products, currencies }: CustomerInvoicesReportProps) {
    const dict = getDictionary(locale);
    const pageDict = dict.app.pages.reports;
    const accDict = dict.app.accounting;
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
    const statusOptions = [
        { value: '', label: pageDict.allStatuses },
        { value: 'draft', label: pageDict.draft },
        { value: 'submitted', label: pageDict.submitted },
        { value: 'approved', label: pageDict.approved },
        { value: 'posted', label: pageDict.posted },
        { value: 'cancelled', label: pageDict.cancelled },
    ];
    const customerOptions = [
        { value: '', label: pageDict.allCustomers },
        ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` })),
    ];
    const productOptions = [
        { value: '', label: pageDict.allProducts },
        ...products.map((p) => ({ value: p.id, label: `${p.code} - ${p.name}` })),
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/customer-invoices', {
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
        router.get('/reports/customer-invoices', {}, { preserveState: true });
    };

    return (
        <AppLayout active="reports.customer-invoices">
            <Head title={pageDict.customerInvoicesHeadTitle} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title={pageDict.customerInvoicesTitle}
                    description={pageDict.customerInvoicesDescription}
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
                            label={pageDict.customer}
                            options={customerOptions}
                            value={customerId}
                            onChange={(value) => setCustomerId(value || '')}
                            placeholder={pageDict.allCustomers}
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
                                placeholder={pageDict.customerInvoicesSearchPlaceholder}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                    </ReportFilterPanel>
                </form>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalCustomerInvoices}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_invoices_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalInvoicedAmount}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">
                            {filters.currency ? formatMoney(reportData.summary.total_amount_minor, filters.currency) : pageDict.mixedCurrencyAmount}
                        </div>
                    </Card>
                </div>

                <Card>
                    <CustomerInvoicesDataTable
                        filters={filters}
                        labels={pageDict}
                        locale={locale}
                        notAvailable={accDict.notAvailable}
                    />
                </Card>
            </div>
        </AppLayout>
    );
}
