import DataTable, {
  type DataTableSlots,
} from 'datatables.net-react';
import DataTablesCore from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
import type { Config } from 'datatables.net';
import { useMemo } from 'react';

import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

DataTable.use(DataTablesCore);

type QueryValue = string | number | boolean | null | undefined;

type ServerDataTableProps = {
  ajaxUrl: string;
  columns: NonNullable<Config['columns']>;
  filters?: Record<string, QueryValue>;
  initialSearch?: string;
  locale: string;
  pageLength?: number;
  order?: Config['order'];
  slots?: DataTableSlots;
  tableId?: string;
};

const language = (locale: string): Config['language'] => locale === 'ar'
  ? {
      emptyTable: 'لا توجد بيانات متاحة',
      info: 'عرض _START_ إلى _END_ من إجمالي _TOTAL_ سجل',
      infoEmpty: 'لا توجد سجلات للعرض',
      infoFiltered: '(تمت التصفية من إجمالي _MAX_ سجل)',
      lengthMenu: 'عرض _MENU_ سجل',
      loadingRecords: 'جارٍ التحميل…',
      processing: 'جارٍ تجهيز البيانات…',
      search: 'بحث سريع:',
      searchPlaceholder: 'ابحث في النتائج…',
      zeroRecords: 'لا توجد نتائج مطابقة',
      paginate: {
        first: 'الأولى',
        last: 'الأخيرة',
        next: 'التالي',
        previous: 'السابق',
      },
    }
  : {
      emptyTable: 'No data available',
      info: 'Showing _START_ to _END_ of _TOTAL_ records',
      infoEmpty: 'No records to show',
      infoFiltered: '(filtered from _MAX_ total records)',
      lengthMenu: 'Show _MENU_ records',
      loadingRecords: 'Loading…',
      processing: 'Processing…',
      search: 'Quick search:',
      searchPlaceholder: 'Search results…',
      zeroRecords: 'No matching records found',
      paginate: {
        first: 'First',
        last: 'Last',
        next: 'Next',
        previous: 'Previous',
      },
    };

export default function ServerDataTable({
  ajaxUrl,
  columns,
  filters = {},
  initialSearch = '',
  locale,
  pageLength = 25,
  order = [],
  slots,
  tableId,
}: ServerDataTableProps) {
  const tableStateKey = useMemo(() => JSON.stringify({
    ajaxUrl,
    filters: Object.entries(filters).sort(([left], [right]) => left.localeCompare(right)),
    initialSearch,
    locale,
    order,
    pageLength,
  }), [ajaxUrl, filters, initialSearch, locale, order, pageLength]);

  const ajax = useMemo<NonNullable<Config['ajax']>>(() => ({
    url: ajaxUrl,
    type: 'GET',
    data: (payload: Record<string, unknown>) => {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          payload[key] = value;
        }
      });
    },
  }), [ajaxUrl, filters]);

  const options = useMemo<Config>(() => ({
    autoWidth: false,
    deferRender: true,
    language: language(locale),
    layout: {
      topStart: 'pageLength',
      topEnd: 'search',
      bottomStart: 'info',
      bottomEnd: 'paging',
    },
    lengthMenu: [10, 25, 50, 100],
    order,
    pageLength,
    processing: true,
    responsive: true,
    search: initialSearch
      ? ({ search: initialSearch } as NonNullable<Config['search']> & { search: string })
      : undefined,
    searchDelay: 350,
    serverSide: true,
  }), [initialSearch, locale, order, pageLength]);

  return (
    <div className="server-data-table overflow-hidden" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <DataTable
        key={tableStateKey}
        ajax={ajax}
        columns={columns}
        id={tableId}
        options={options}
        slots={slots}
        className="display responsive nowrap w-full"
      />
    </div>
  );
}

export type { DataTableSlots };
