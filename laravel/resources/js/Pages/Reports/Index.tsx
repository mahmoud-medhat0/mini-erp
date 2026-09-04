import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { PageHeader } from '../../Components/Primitives';
import { getDictionary, interpolate } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types/page';

type ReportItem = {
  id: string;
  name: string;
  desc: string;
  href: string;
  categoryKey: string;
};

type ReportGroup = {
  key: string;
  title: string;
  icon: ReactNode;
  reports: ReportItem[];
};

export default function ReportsIndex({ locale }: SharedPageProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const taxDict = dict.app.taxes;
  const pageDict = dict.app.pages.reports;
  const can = useCan();

  const [search, setSearch] = useState('');
  const [activeTab, setActiveTab] = useState('all');

  const canViewFinancials = can('view_financials');
  const canViewFixedAssetReports = can('reports.view') && canViewFinancials;
  const canViewBudgetVarianceReport = can('budgeting.view') && can('reports.view') && canViewFinancials;

  const reportGroups: ReportGroup[] = useMemo(() => [
    ...(canViewFinancials
      ? [
          {
            key: 'financials',
            title: accDict.financialStatements,
            icon: (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            ),
            reports: [
              {
                id: 'balance-sheet',
                name: accDict.balanceSheet,
                desc: accDict.balanceSheetDesc,
                href: '/reports/balance-sheet',
                categoryKey: 'financials',
              },
              {
                id: 'income-statement',
                name: accDict.incomeStatement,
                desc: accDict.incomeStatementDesc,
                href: '/reports/income-statement',
                categoryKey: 'financials',
              },
              {
                id: 'cash-flow',
                name: accDict.cashFlowStatement,
                desc: accDict.cashFlowStatementDesc,
                href: '/reports/cash-flow',
                categoryKey: 'financials',
              },
              {
                id: 'financial-ratios',
                name: accDict.financialRatios,
                desc: accDict.financialRatiosDesc,
                href: '/reports/financial-ratios',
                categoryKey: 'financials',
              },
            ],
          },
        ]
      : []),
    {
      key: 'ar',
      title: pageDict.arCustomerReports,
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      ),
      reports: [
        {
          id: 'customer-statement',
          name: pageDict.customerStatement,
          desc: pageDict.detailedAccountStatementWithOpeningAnd,
          href: '/reports/customer-statement',
          categoryKey: 'ar',
        },
        {
          id: 'ar-aging',
          name: pageDict.arAgingReport,
          desc: pageDict.agingAnalysisOfOpenCustomerReceivables,
          href: '/reports/ar-aging',
          categoryKey: 'ar',
        },
        {
          id: 'ar-gl-reconciliation',
          name: pageDict.arToGlReconciliation,
          desc: pageDict.compareCustomerSubledgerBalancesAgainstThe,
          href: '/reports/ar-gl-reconciliation',
          categoryKey: 'ar',
        },
      ],
    },
    {
      key: 'ap',
      title: pageDict.apSupplierReports,
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0v-4m0 4h4" />
        </svg>
      ),
      reports: [
        {
          id: 'supplier-statement',
          name: pageDict.supplierStatement,
          desc: pageDict.detailedAccountStatementWithOpeningAnd_2,
          href: '/reports/supplier-statement',
          categoryKey: 'ap',
        },
        {
          id: 'ap-aging',
          name: pageDict.apAgingReport,
          desc: pageDict.agingAnalysisOfOpenSupplierPayables,
          href: '/reports/ap-aging',
          categoryKey: 'ap',
        },
        {
          id: 'ap-gl-reconciliation',
          name: pageDict.apToGlReconciliation,
          desc: pageDict.compareSupplierSubledgerBalancesAgainstThe,
          href: '/reports/ap-gl-reconciliation',
          categoryKey: 'ap',
        },
      ],
    },
    {
      key: 'treasury',
      title: pageDict.cashBankChequeReports,
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      ),
      reports: [
        {
          id: 'cash-book',
          name: pageDict.cashBookReport,
          desc: pageDict.ledgerBackedDetailedCashMovementAnd,
          href: '/reports/cash-book',
          categoryKey: 'treasury',
        },
        {
          id: 'bank-book',
          name: pageDict.bankBookReport,
          desc: pageDict.ledgerBackedDetailedBankMovementAnd,
          href: '/reports/bank-book',
          categoryKey: 'treasury',
        },
        {
          id: 'cheque-register',
          name: pageDict.chequeRegister,
          desc: pageDict.statusAndTrackingReportForIncoming,
          href: '/reports/cheque-register',
          categoryKey: 'treasury',
        },
        {
          id: 'bank-reconciliations',
          name: pageDict.bankReconciliationStatus,
          desc: pageDict.summaryReportOfBankReconciliationStatements,
          href: '/reports/bank-reconciliations',
          categoryKey: 'treasury',
        },
      ],
    },
    {
      key: 'operations',
      title: pageDict.salesPurchasingInventoryReports,
      icon: (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      ),
      reports: [
        {
          id: 'sales-orders',
          name: pageDict.salesOrdersRegister,
          desc: pageDict.readOnlyOperationalRegisterOfSales,
          href: '/reports/sales-orders',
          categoryKey: 'operations',
        },
        {
          id: 'purchase-orders',
          name: pageDict.purchaseOrdersRegister,
          desc: pageDict.readOnlyOperationalRegisterOfPurchase,
          href: '/reports/purchase-orders',
          categoryKey: 'operations',
        },
        {
          id: 'delivery-notes',
          name: pageDict.deliveryNotesRegister,
          desc: pageDict.readOnlyRegisterOfGoodsDelivery,
          href: '/reports/delivery-notes',
          categoryKey: 'operations',
        },
        {
          id: 'goods-receipts',
          name: pageDict.goodsReceiptsRegister,
          desc: pageDict.readOnlyRegisterOfGoodsReceipts,
          href: '/reports/goods-receipts',
          categoryKey: 'operations',
        },
        {
          id: 'customer-invoices',
          name: pageDict.customerInvoicesRegister,
          desc: pageDict.readOnlyRegisterOfCustomerInvoices,
          href: '/reports/customer-invoices',
          categoryKey: 'operations',
        },
        {
          id: 'supplier-bills',
          name: pageDict.supplierBillsRegister,
          desc: pageDict.readOnlyRegisterOfSupplierBills,
          href: '/reports/supplier-bills',
          categoryKey: 'operations',
        },
        {
          id: 'stock-movements',
          name: pageDict.stockMovementsRegister,
          desc: pageDict.immutableAuditLedgerOfStockMovements,
          href: '/reports/stock-movements',
          categoryKey: 'operations',
        },
        {
          id: 'product-statement',
          name: pageDict.productStatementReport,
          desc: pageDict.detailedAccountStatementOfQuantityAndValue,
          href: '/reports/product-statement',
          categoryKey: 'operations',
        },
        {
          id: 'warehouse-statement',
          name: pageDict.warehouseStatementReport,
          desc: pageDict.detailedAccountStatementOfStockValue,
          href: '/reports/warehouse-statement',
          categoryKey: 'operations',
        },
        ...(canViewFinancials
          ? [
              {
                id: 'rentals',
                name: pageDict.rentalOperationsRegister,
                desc: pageDict.rentalOperationsRegisterDescription,
                href: '/reports/rentals',
                categoryKey: 'operations',
              },
              {
                id: 'branch-operations',
                name: dict.app.pages.branchOperationsReport.title,
                desc: dict.app.pages.branchOperationsReport.description,
                href: '/reports/branch-operations',
                categoryKey: 'operations',
              },
              {
                id: 'branch-profitability',
                name: dict.app.pages.branchProfitabilityReport.title,
                desc: dict.app.pages.branchProfitabilityReport.description,
                href: '/reports/branch-profitability',
                categoryKey: 'operations',
              },
              {
                id: 'project-profitability',
                name: dict.app.pages.projectProfitabilityReport.title,
                desc: dict.app.pages.projectProfitabilityReport.description,
                href: '/reports/project-profitability',
                categoryKey: 'operations',
              },
              {
                id: 'cost-center-actuals',
                name: dict.app.pages.costCenterActualsReport.title,
                desc: dict.app.pages.costCenterActualsReport.description,
                href: '/reports/cost-center-actuals',
                categoryKey: 'operations',
              },
              ...(canViewBudgetVarianceReport
                ? [
                    {
                      id: 'budget-variance',
                      name: dict.app.pages.budgetVarianceReport.title,
                      desc: dict.app.pages.budgetVarianceReport.description,
                      href: '/budgeting/variance',
                      categoryKey: 'operations',
                    },
                  ]
                : []),
            ]
          : []),
      ],
    },
    ...(canViewFixedAssetReports
      ? [
          {
            key: 'fixed_assets',
            title: pageDict.fixedAssetReports,
            icon: (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            ),
            reports: [
              {
                id: 'fixed-asset-register',
                name: pageDict.fixedAssetRegisterReport,
                desc: pageDict.fixedAssetRegisterReportDescription,
                href: '/reports/fixed-asset-register',
                categoryKey: 'fixed_assets',
              },
              {
                id: 'fixed-asset-net-book-values',
                name: pageDict.fixedAssetNetBookValueReport,
                desc: pageDict.fixedAssetNetBookValueReportDescription,
                href: '/reports/fixed-asset-net-book-values',
                categoryKey: 'fixed_assets',
              },
              {
                id: 'fixed-asset-depreciation',
                name: pageDict.fixedAssetDepreciationScheduleReport,
                desc: pageDict.fixedAssetDepreciationScheduleDescription,
                href: '/reports/fixed-asset-depreciation',
                categoryKey: 'fixed_assets',
              },
              {
                id: 'fixed-asset-depreciation-runs',
                name: pageDict.fixedAssetDepreciationRunHistoryReport,
                desc: pageDict.fixedAssetDepreciationRunHistoryDescription,
                href: '/reports/fixed-asset-depreciation-runs',
                categoryKey: 'fixed_assets',
              },
              {
                id: 'fixed-asset-disposals',
                name: pageDict.fixedAssetDisposalHistoryReport,
                desc: pageDict.fixedAssetDisposalHistoryDescription,
                href: '/reports/fixed-asset-disposals',
                categoryKey: 'fixed_assets',
              },
            ],
          },
        ]
      : []),
    ...(canViewFinancials
      ? [
          {
            key: 'tax',
            title: taxDict.title,
            icon: (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 14l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            ),
            reports: [
              {
                id: 'vat-register',
                name: taxDict.vatRegister.title,
                desc: taxDict.vatRegister.subtitle,
                href: '/reports/vat-register',
                categoryKey: 'tax',
              },
              {
                id: 'vat-summary',
                name: taxDict.vatSummary.title,
                desc: taxDict.vatSummary.subtitle,
                href: '/reports/vat-summary',
                categoryKey: 'tax',
              },
              {
                id: 'vat-gl-reconciliation',
                name: taxDict.vatGlReconciliation.title,
                desc: taxDict.vatGlReconciliation.subtitle,
                href: '/reports/vat-gl-reconciliation',
                categoryKey: 'tax',
              },
            ],
          },
        ]
      : []),
  ], [canViewFinancials, canViewFixedAssetReports, canViewBudgetVarianceReport, accDict, taxDict, pageDict, dict]);

  const allReportsCount = useMemo(() => {
    return reportGroups.reduce((acc, g) => acc + g.reports.length, 0);
  }, [reportGroups]);

  const filteredGroups = useMemo(() => {
    const query = search.trim().toLowerCase();

    return reportGroups
      .map((group) => {
        if (activeTab !== 'all' && group.key !== activeTab) {
          return null;
        }

        const filteredReports = group.reports.filter((r) => {
          if (!query) return true;
          return r.name.toLowerCase().includes(query) || r.desc.toLowerCase().includes(query);
        });

        if (filteredReports.length === 0) return null;

        return {
          ...group,
          reports: filteredReports,
        };
      })
      .filter((g): g is ReportGroup => g !== null);
  }, [reportGroups, activeTab, search]);

  const totalFilteredReportsCount = useMemo(() => {
    return filteredGroups.reduce((acc, g) => acc + g.reports.length, 0);
  }, [filteredGroups]);

  return (
    <AppLayout active="reports.index">
      <Head title={pageDict.reportsHubMiniErp} />

      <div className="space-y-6">
        <PageHeader
          title={pageDict.reportsHub}
          description={pageDict.accessAndExportOperationalSubledgerReports}
        />

        {/* Search & Filter Bar */}
        <div className="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
          {/* Search Input */}
          <div className="relative flex-1">
            <div className="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 pl-3 rtl:pr-3 flex items-center pointer-events-none text-slate-400">
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={pageDict.searchReportsPlaceholder}
              className="w-full pl-9 rtl:pl-3 rtl:pr-9 pr-8 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                title={pageDict.clearSearch}
                aria-label={pageDict.clearSearch}
                className="absolute inset-y-0 right-0 rtl:right-auto rtl:left-0 pr-3 rtl:pl-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>

          {/* Report Count summary */}
          <div className="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0 self-center">
            {interpolate(pageDict.showingReportsCount, { shown: totalFilteredReportsCount, total: allReportsCount })}
          </div>
        </div>

        {/* Category Tabs */}
        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none border-b border-slate-200 dark:border-slate-700">
          <button
            type="button"
            onClick={() => setActiveTab('all')}
            title={pageDict.allReportsTab}
            aria-label={pageDict.allReportsTab}
            className={`px-3.5 py-2 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1.5 ${
              activeTab === 'all'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
            }`}
          >
            <span>{pageDict.allReportsTab}</span>
            <span
              className={`text-[10px] px-1.5 py-0.5 rounded-full ${
                activeTab === 'all'
                  ? 'bg-indigo-700 text-white'
                  : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
              }`}
            >
              {allReportsCount}
            </span>
          </button>

          {reportGroups.map((group) => {
            const isSelected = activeTab === group.key;
            return (
              <button
                key={group.key}
                type="button"
                onClick={() => setActiveTab(group.key)}
                title={group.title}
                aria-label={group.title}
                className={`px-3.5 py-2 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1.5 ${
                  isSelected
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                }`}
              >
                <span>{group.title}</span>
                <span
                  className={`text-[10px] px-1.5 py-0.5 rounded-full ${
                    isSelected
                      ? 'bg-indigo-700 text-white'
                      : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                  }`}
                >
                  {group.reports.length}
                </span>
              </button>
            );
          })}
        </div>

        {/* Reports Content Grid */}
        {filteredGroups.length === 0 ? (
          <div className="bg-white dark:bg-slate-800 rounded-xl p-12 text-center border border-slate-200 dark:border-slate-700 space-y-3">
            <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-900 text-slate-400 flex items-center justify-center mx-auto">
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
              {pageDict.noReportsFound}
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
              {pageDict.noReportsFoundDesc}
            </p>
            <button
              type="button"
              onClick={() => {
                setSearch('');
                setActiveTab('all');
              }}
              title={pageDict.resetSearchAndFilters}
              aria-label={pageDict.resetSearchAndFilters}
              className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 pt-2"
            >
              <span>{pageDict.resetSearchAndFilters}</span>
            </button>
          </div>
        ) : (
          <div className="space-y-8">
            {filteredGroups.map((group) => (
              <div key={group.key} className="space-y-3">
                <div className="flex items-center gap-2 text-slate-800 dark:text-slate-200">
                  <div className="p-1.5 rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                    {group.icon}
                  </div>
                  <h2 className="text-sm font-bold uppercase tracking-wider">
                    {group.title}
                  </h2>
                  <span className="text-xs font-medium text-slate-400">({group.reports.length})</span>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                  {group.reports.map((report) => (
                    <Link
                      key={report.id}
                      href={report.href}
                      className="bg-white dark:bg-slate-800/90 rounded-xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm hover:shadow-md hover:border-indigo-500/50 dark:hover:border-indigo-500/50 transition-all duration-200 group flex flex-col justify-between"
                    >
                      <div>
                        <div className="flex items-center justify-between gap-2 mb-3">
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700/70 dark:text-slate-300">
                            {group.title}
                          </span>
                          <span className="text-indigo-600 dark:text-indigo-400 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 transition-transform">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                          </span>
                        </div>
                        <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                          {report.name}
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mt-1.5 mb-4">
                          {report.desc}
                        </p>
                      </div>

                      <div className="pt-3 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between text-xs font-semibold text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-300">
                        <span>{pageDict.viewReport}</span>
                        <svg className="w-3.5 h-3.5 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7-7 7" />
                        </svg>
                      </div>
                    </Link>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
