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

interface SupplierBillsReportProps extends SharedPageProps {
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
    currencies: Array<{ code: string }>;
}

export default function SupplierBillsReport({ locale, reportData, filters, suppliers, products, currencies }: SupplierBillsReportProps) {
    const dict = getDictionary(locale);
    const pageDict = dict.app.pages.reports;
    const accDict = dict.app.accounting;
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
        { value: 'approved', label: pageDict.approved },
        { value: 'posted', label: pageDict.posted },
        { value: 'cancelled', label: pageDict.cancelled },
    ];
    const supplierOptions = [
        { value: '', label: pageDict.allSuppliers },
        ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` })),
    ];
    const productOptions = [
        { value: '', label: pageDict.allProducts },
        ...products.map((p) => ({ value: p.id, label: `${p.code} - ${p.name}` })),
    ];

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

    const handleReset = () => {
        setDateFrom('');
        setDateTo('');
        setStatus('');
        setSupplierId('');
        setProductId('');
        setCurrency('');
        setSearch('');
        router.get('/reports/supplier-bills', {}, { preserveState: true });
    };

    const getStatusTone = (st: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
        if (st === 'posted') return 'ok';
        if (st === 'cancelled') return 'danger';
        if (st === 'approved' || st === 'submitted') return 'warning';
        return 'muted';
    };

    const getStatusLabel = (st: string) => {
        if (st === 'draft') return pageDict.draft;
        if (st === 'submitted') return pageDict.submitted;
        if (st === 'approved') return pageDict.approved;
        if (st === 'posted') return pageDict.posted;
        if (st === 'cancelled') return pageDict.cancelled;

        return st;
    };

    return (
        <AppLayout active="reports.supplier-bills">
            <Head title={pageDict.supplierBillsHeadTitle} />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <PageHeader
                    title={pageDict.supplierBillsTitle}
                    description={pageDict.supplierBillsDescription}
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
                                placeholder={pageDict.supplierBillsSearchPlaceholder}
                                className="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                            />
                        </div>
                    </ReportFilterPanel>
                </form>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalSupplierBills}</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{reportData.summary.total_bills_count}</div>
                    </Card>
                    <Card className="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="text-xs font-medium text-slate-500">{pageDict.totalBilledAmount}</div>
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
                                    <th className={tableClasses.th}>{pageDict.billNumber}</th>
                                    <th className={tableClasses.th}>{pageDict.supplier}</th>
                                    <th className={tableClasses.th}>{pageDict.date}</th>
                                    <th className={tableClasses.th}>{pageDict.dueDate}</th>
                                    <th className={tableClasses.th}>{pageDict.status}</th>
                                    <th className={tableClasses.th}>{pageDict.total}</th>
                                    <th className={tableClasses.th}>{pageDict.journal}</th>
                                    <th className={tableClasses.th}>{pageDict.apEntry}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {reportData.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="p-4 text-center">
                                            <EmptyState title={pageDict.emptySupplierBills} />
                                        </td>
                                    </tr>
                                ) : (
                                    reportData.rows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td className={`${tableClasses.td} font-medium`}>{row.bill_number}</td>
                                            <td className={tableClasses.td}>{row.supplier_code} - {row.supplier_name}</td>
                                            <td className={tableClasses.td}>{row.bill_date}</td>
                                            <td className={tableClasses.td}>{row.due_date}</td>
                                            <td className={tableClasses.td}><StatusBadge tone={getStatusTone(row.status)}>{getStatusLabel(row.status)}</StatusBadge></td>
                                            <td className={`${tableClasses.td} font-semibold`}>{formatMoney(row.total_minor, row.currency)}</td>
                                            <td className={tableClasses.td}>
                                                {row.journal_entry_number ? (
                                                    <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{row.journal_entry_number}</span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">{accDict.notAvailable}</span>
                                                )}
                                            </td>
                                            <td className={tableClasses.td}>
                                                {row.payable_entry_id ? (
                                                    <span className="text-xs font-mono text-purple-600 dark:text-purple-400 font-semibold">AP-{row.payable_entry_id.substring(0, 8)}</span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">{accDict.notAvailable}</span>
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
