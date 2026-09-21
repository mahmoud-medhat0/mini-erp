import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type EmployeeOption = { id: string; code: string; name: TranslatedName };
type RunOption = { id: string; number: string | null; payroll_date: string };

type PayrollLineRow = {
  id: string;
  currency: string;
  base_salary_minor: number;
  earnings_minor: number;
  deductions_minor: number;
  gross_minor: number;
  net_minor: number;
  employee?: EmployeeOption | null;
  run?: RunOption | null;
};

type Props = SharedPageProps & {
  employees: EmployeeOption[];
  runs: RunOption[];
  filters: { employee_id?: string; payroll_run_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

export default function PayrollReport({ locale, employees, runs, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.payrollReport;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';

  const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
  const [runFilter, setRunFilter] = useState(filters.payroll_run_id || '');

  const employeeOptions = useMemo(() => employees.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [employees, activeLocale]);
  const runOptions = useMemo(() => runs.map((item) => ({ value: item.id, label: `${item.number || pageDict.noNumber} - ${item.payroll_date}` })), [runs, pageDict.noNumber]);

  const columns = useMemo(() => [
    { data: 'employee', name: 'employee', title: pageDict.employee, orderable: false, searchable: false },
    { data: 'run', name: 'run', title: pageDict.payrollRun, orderable: false, searchable: false },
    { data: 'base_salary_minor', name: 'payroll_run_line.base_salary_minor', title: pageDict.baseSalary, className: 'text-end' },
    { data: 'earnings_minor', name: 'payroll_run_line.earnings_minor', title: pageDict.earnings, className: 'text-end' },
    { data: 'deductions_minor', name: 'payroll_run_line.deductions_minor', title: pageDict.deductions, className: 'text-end' },
    { data: 'net_minor', name: 'payroll_run_line.net_minor', title: pageDict.net, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    employee: (_v: unknown, _t: unknown, row: PayrollLineRow) => <span className="font-medium">{row.employee ? `${row.employee.code} - ${namePart(row.employee.name, activeLocale)}` : ''}</span>,
    run: (_v: unknown, _t: unknown, row: PayrollLineRow) => <span className="font-mono text-sm">{row.run ? (row.run.number || pageDict.noNumber) : ''}</span>,
    'payroll_run_line.base_salary_minor': (value: number, _t: unknown, row: PayrollLineRow) => formatMoney(value, row.currency),
    'payroll_run_line.earnings_minor': (value: number, _t: unknown, row: PayrollLineRow) => formatMoney(value, row.currency),
    'payroll_run_line.deductions_minor': (value: number, _t: unknown, row: PayrollLineRow) => formatMoney(value, row.currency),
    'payroll_run_line.net_minor': (value: number, _t: unknown, row: PayrollLineRow) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
  }), [activeLocale, pageDict.noNumber]);

  const tableFilters = useMemo(() => ({ employee_id: employeeFilter, payroll_run_id: runFilter }), [employeeFilter, runFilter]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-56"><SearchableSelect options={[{ value: '', label: pageDict.allEmployees }, ...employeeOptions]} value={employeeFilter || null} onChange={(value) => setEmployeeFilter(value || '')} /></div>
      <div className="w-56"><SearchableSelect options={[{ value: '', label: pageDict.allRuns }, ...runOptions]} value={runFilter || null} onChange={(value) => setRunFilter(value || '')} /></div>
    </div>
  );

  return (
    <AppLayout active="reports.payroll">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/reports/payroll/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[1, 'desc']]}
          slots={slots}
          tableId="payroll-report-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
