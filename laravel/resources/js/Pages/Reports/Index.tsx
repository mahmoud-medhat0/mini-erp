import { Head } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

export default function ReportsIndex({ locale }: SharedPageProps) {
  const dict = getDictionary(locale);

  const reportGroups = [
    {
      title: dict.app.pages.reports.arCustomerReports,
      reports: [
        {
          name: dict.app.pages.reports.customerStatement,
          desc: dict.app.pages.reports.detailedAccountStatementWithOpeningAnd,
          href: '/reports/customer-statement',
        },
        {
          name: dict.app.pages.reports.arAgingReport,
          desc: dict.app.pages.reports.agingAnalysisOfOpenCustomerReceivables,
          href: '/reports/ar-aging',
        },
        {
          name: dict.app.pages.reports.arToGlReconciliation,
          desc: dict.app.pages.reports.compareCustomerSubledgerBalancesAgainstThe,
          href: '/reports/ar-gl-reconciliation',
        },
      ],
    },
    {
      title: dict.app.pages.reports.apSupplierReports,
      reports: [
        {
          name: dict.app.pages.reports.supplierStatement,
          desc: dict.app.pages.reports.detailedAccountStatementWithOpeningAnd_2,
          href: '/reports/supplier-statement',
        },
        {
          name: dict.app.pages.reports.apAgingReport,
          desc: dict.app.pages.reports.agingAnalysisOfOpenSupplierPayables,
          href: '/reports/ap-aging',
        },
        {
          name: dict.app.pages.reports.apToGlReconciliation,
          desc: dict.app.pages.reports.compareSupplierSubledgerBalancesAgainstThe,
          href: '/reports/ap-gl-reconciliation',
        },
      ],
    },
    {
      title: dict.app.pages.reports.cashBankChequeReports,
      reports: [
        {
          name: dict.app.pages.reports.cashBookReport,
          desc: dict.app.pages.reports.ledgerBackedDetailedCashMovementAnd,
          href: '/reports/cash-book',
        },
        {
          name: dict.app.pages.reports.bankBookReport,
          desc: dict.app.pages.reports.ledgerBackedDetailedBankMovementAnd,
          href: '/reports/bank-book',
        },
        {
          name: dict.app.pages.reports.chequeRegister,
          desc: dict.app.pages.reports.statusAndTrackingReportForIncoming,
          href: '/reports/cheque-register',
        },
        {
          name: dict.app.pages.reports.bankReconciliationStatus,
          desc: dict.app.pages.reports.summaryReportOfBankReconciliationStatements,
          href: '/reports/bank-reconciliations',
        },
      ],
    },
    {
      title: dict.app.pages.reports.salesPurchasingInventoryReports,
      reports: [
        {
          name: dict.app.pages.reports.salesOrdersRegister,
          desc: dict.app.pages.reports.readOnlyOperationalRegisterOfSales,
          href: '/reports/sales-orders',
        },
        {
          name: dict.app.pages.reports.purchaseOrdersRegister,
          desc: dict.app.pages.reports.readOnlyOperationalRegisterOfPurchase,
          href: '/reports/purchase-orders',
        },
        {
          name: dict.app.pages.reports.deliveryNotesRegister,
          desc: dict.app.pages.reports.readOnlyRegisterOfGoodsDelivery,
          href: '/reports/delivery-notes',
        },
        {
          name: dict.app.pages.reports.goodsReceiptsRegister,
          desc: dict.app.pages.reports.readOnlyRegisterOfGoodsReceipts,
          href: '/reports/goods-receipts',
        },
        {
          name: dict.app.pages.reports.customerInvoicesRegister,
          desc: dict.app.pages.reports.readOnlyRegisterOfCustomerInvoices,
          href: '/reports/customer-invoices',
        },
        {
          name: dict.app.pages.reports.supplierBillsRegister,
          desc: dict.app.pages.reports.readOnlyRegisterOfSupplierBills,
          href: '/reports/supplier-bills',
        },
        {
          name: dict.app.pages.reports.stockMovementsRegister,
          desc: dict.app.pages.reports.immutableAuditLedgerOfStockMovements,
          href: '/reports/stock-movements',
        },
      ],
    },
  ];

  return (
    <AppLayout active="reports.index">
      <Head title={dict.app.pages.reports.reportsHubMiniErp} />

      <PageHeader
        title={dict.app.pages.reports.reportsHub}
        description={dict.app.pages.reports.accessAndExportOperationalSubledgerReports}
      />

      <div className="space-y-6">
        {reportGroups.map((group, idx) => (
          <div key={idx} className="space-y-3">
            <h2 className="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider">
              {group.title}
            </h2>
            <div className="grid gap-4 md:grid-cols-3">
              {group.reports.map((report, rIdx) => (
                <Card key={rIdx} className="p-5 hover:border-[var(--primary)] transition-all">
                  <h3 className="text-sm font-bold text-[var(--text-primary)] mb-1">
                    {report.name}
                  </h3>
                  <p className="text-xs text-[var(--text-secondary)] mb-4 leading-relaxed">
                    {report.desc}
                  </p>
                  <a
                    href={report.href}
                    className="inline-flex items-center text-xs font-bold text-[var(--primary)] hover:underline"
                  >
                    {dict.app.pages.reports.viewReport}
                  </a>
                </Card>
              ))}
            </div>
          </div>
        ))}
      </div>
    </AppLayout>
  );
}
