import { Head } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types';

export default function ReportsIndex({ locale }: SharedPageProps) {
  const isAr = locale === 'ar';

  const reportGroups = [
    {
      title: isAr ? 'تقارير العملاء والذمم المدينة' : 'AR & Customer Reports',
      reports: [
        {
          name: isAr ? 'كشف حساب عميل' : 'Customer Statement',
          desc: isAr ? 'كشف حساب تفصيلي بحركات وحساب العميل مع رصيد البداية والنهاية.' : 'Detailed account statement with opening and closing balances.',
          href: '/reports/customer-statement',
        },
        {
          name: isAr ? 'تقرير أعمار ديون العملاء' : 'AR Aging Report',
          desc: isAr ? 'تحليل أعمار المستحقات المفتوحة للعملاء (30، 60، 90، +90 يوماً).' : 'Aging analysis of open customer receivables by age buckets.',
          href: '/reports/ar-aging',
        },
        {
          name: isAr ? 'مطابقة الذمم المدينة مع الأستاذ العام' : 'AR to GL Reconciliation',
          desc: isAr ? 'مقارنة إجمالي رصيد ميزان الذمم بالعملاء بحساب الأستاذ العام.' : 'Compare customer subledger balances against the GL control account.',
          href: '/reports/ar-gl-reconciliation',
        },
      ],
    },
    {
      title: isAr ? 'تقارير الموردين والذمم الدائنة' : 'AP & Supplier Reports',
      reports: [
        {
          name: isAr ? 'كشف حساب مورد' : 'Supplier Statement',
          desc: isAr ? 'كشف حساب تفصيلي بحركات وحساب المورد مع رصيد البداية والنهاية.' : 'Detailed account statement with opening and closing balances.',
          href: '/reports/supplier-statement',
        },
        {
          name: isAr ? 'تقرير أعمار ديون الموردين' : 'AP Aging Report',
          desc: isAr ? 'تحليل أعمار المستحقات المفتوحة للموردين (30، 60، 90، +90 يوماً).' : 'Aging analysis of open supplier payables by age buckets.',
          href: '/reports/ap-aging',
        },
        {
          name: isAr ? 'مطابقة الذمم الدائنة مع الأستاذ العام' : 'AP to GL Reconciliation',
          desc: isAr ? 'مقارنة إجمالي رصيد ميزان الذمم بالموردين بحساب الأستاذ العام.' : 'Compare supplier subledger balances against the GL control account.',
          href: '/reports/ap-gl-reconciliation',
        },
      ],
    },
    {
      title: isAr ? 'تقارير النقدية والبنوك والشيكات' : 'Cash, Bank & Cheque Reports',
      reports: [
        {
          name: isAr ? 'دفتر حركة الخزينة' : 'Cash Book Report',
          desc: isAr ? 'سجل تفصيلي لجميع المقبوضات والمدفوعات النقدية بالأستاذ.' : 'Ledger-backed detailed cash movement and daily running balance.',
          href: '/reports/cash-book',
        },
        {
          name: isAr ? 'دفتر حركة البنك' : 'Bank Book Report',
          desc: isAr ? 'سجل تفصيلي لجميع الإيداعات والمسحوبات البنكية بالأستاذ.' : 'Ledger-backed detailed bank movement and daily running balance.',
          href: '/reports/bank-book',
        },
        {
          name: isAr ? 'سجل ومتابعة الشيكات' : 'Cheque Register',
          desc: isAr ? 'تقرير ومتابعة الشيكات الواردة والصادرة وحالات التحصيل/الصرف.' : 'Status and tracking report for incoming and outgoing cheques.',
          href: '/reports/cheque-register',
        },
        {
          name: isAr ? 'تقرير تسويات البنك' : 'Bank Reconciliation Status',
          desc: isAr ? 'ملخص ومطابقة كشوف الحسابات البنكية مع الأستاذ العام.' : 'Summary report of bank reconciliation statements and matching state.',
          href: '/reports/bank-reconciliations',
        },
      ],
    },
  ];

  return (
    <AppLayout active="reports.index">
      <Head title={isAr ? 'مركز التقارير والذمم - Mini ERP' : 'Reports Hub - Mini ERP'} />

      <PageHeader
        title={isAr ? 'مركز التقارير والتقارير الفرعية' : 'Reports Hub'}
        description={isAr ? 'عرض وتنزل جميع التقارير الفرعية للعملاء والموردين والنقدية والبنوك والشيكات.' : 'Access and export operational subledger reports and reconciliation tools.'}
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
                    {isAr ? 'عرض التقرير ←' : 'View Report →'}
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
