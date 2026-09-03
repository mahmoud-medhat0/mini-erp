import DataTable from 'datatables.net-react';
import DT from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
import type { Config } from 'datatables.net-dt';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

import SearchableSelect from './SearchableSelect';
import { getDictionary, interpolate } from '../lib/i18n';

DataTable.use(DT);

type DataTableSlots = Record<string, (data: any, type: any, row: any) => ReactNode>;

interface ServerDataTableProps {
  ajaxUrl: string;
  columns: any[];
  filters?: Record<string, any>;
  initialSearch?: string;
  locale: string;
  pageLength?: number;
  order?: any[];
  slots?: DataTableSlots;
  tableId?: string;
  toolbar?: ReactNode;
}

const language = (locale: string): Config['language'] => {
  const dict = getDictionary(locale);
  const dt = dict.common.datatable;
  return {
    emptyTable: dt.emptyTable,
    info: dt.info,
    infoEmpty: dt.infoEmpty,
    infoFiltered: dt.infoFiltered,
    lengthMenu: `${dt.show} _MENU_ ${dt.entries}`,
    loadingRecords: dt.loadingRecords,
    processing: dt.processing,
    search: dt.quickSearch,
    searchPlaceholder: dt.searchPlaceholder,
    zeroRecords: dt.zeroRecords,
    paginate: {
      first: dt.paginate.first,
      last: dt.paginate.last,
      next: dt.paginate.next,
      previous: dt.paginate.previous,
    },
  };
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
  toolbar,
}: ServerDataTableProps) {
  const dict = getDictionary(locale);
  const dtDict = dict.common.datatable;

  const [currentPageLength, setCurrentPageLength] = useState<number>(pageLength);
  const dtRef = useRef<any>(null);
  const isFirstRender = useRef(true);
  const filtersRef = useRef(filters);
  filtersRef.current = filters;

  const lengthOptions = useMemo(() => {
    const defaults = [10, 25, 50, 100];
    if (!defaults.includes(currentPageLength)) {
      return [...defaults, currentPageLength].sort((a, b) => a - b);
    }
    return defaults;
  }, [currentPageLength]);

  const filterStateKey = useMemo(
    () => JSON.stringify(filters),
    [filters],
  );

  const ajax = useMemo<NonNullable<Config['ajax']>>(() => ({
    url: ajaxUrl,
    type: 'GET',
    data: (payload: Record<string, unknown>) => {
      if (filtersRef.current) {
        Object.entries(filtersRef.current).forEach(([key, value]) => {
          if (value !== null && value !== undefined && value !== '') {
            payload[key] = value;
          }
        });
      }
    },
  }), [ajaxUrl]);

  const options = useMemo<Config>(() => ({
    autoWidth: false,
    deferRender: true,
    language: {
      ...language(locale),
      processing: `
        <div class="sdt-spinner"></div>
        <span class="sdt-spinner-label">${dtDict.processing}</span>
      `,
    },
    initComplete: function () {
      try {
        const tableNode = (this.api() as any).table().node();
        if (tableNode && tableNode.parentElement && !tableNode.parentElement.classList.contains('sdt-table-scroll')) {
          const wrapper = document.createElement('div');
          wrapper.className = 'sdt-table-scroll w-full overflow-x-auto';
          tableNode.parentNode.insertBefore(wrapper, tableNode);
          wrapper.appendChild(tableNode);
        }
      } catch (err) {
        // Fallback handled by useEffect
      }
    },
    layout: {
      topStart: null,
      topEnd: null,
      bottomStart: 'info',
      bottomEnd: 'paging',
    },
    order,
    pageLength: currentPageLength,
    processing: true,
    responsive: false,
    scrollX: false,
    search: initialSearch
      ? ({ search: initialSearch } as NonNullable<Config['search']> & { search: string })
      : undefined,
    searchDelay: 350,
    serverSide: true,
  }), [currentPageLength, dtDict.processing, initialSearch, locale, order]);

  const [searchValue, setSearchValue] = useState(initialSearch);

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setSearchValue(val);
    if (dtRef.current) {
      const dt = dtRef.current.dt ? dtRef.current.dt() : dtRef.current;
      if (dt && typeof dt.search === 'function') {
        dt.search(val).draw();
      }
    }
  };

  useEffect(() => {
    const wrapTable = () => {
      if (dtRef.current) {
        const dt = dtRef.current.dt ? dtRef.current.dt() : dtRef.current;
        if (dt && typeof dt.table === 'function') {
          const tableNode = dt.table().node();
          if (tableNode && tableNode.parentElement && !tableNode.parentElement.classList.contains('sdt-table-scroll')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'sdt-table-scroll w-full overflow-x-auto';
            tableNode.parentNode.insertBefore(wrapper, tableNode);
            wrapper.appendChild(tableNode);
          }
        }
      }
    };

    wrapTable();
    const timer = setTimeout(wrapTable, 100);
    return () => clearTimeout(timer);
  }, [tableId]);

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    if (dtRef.current) {
      const dt = dtRef.current.dt ? dtRef.current.dt() : dtRef.current;
      if (dt && typeof dt.ajax?.reload === 'function') {
        dt.ajax.reload(null, true);
      }
    }
  }, [filterStateKey]);

  return (
    <div className="server-data-table overflow-hidden" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <div className="sdt-top-bar px-5 py-3 border-b border-[var(--border)] bg-[color-mix(in_srgb,var(--background)_40%,var(--surface))] space-y-2.5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          {/* Quick Search */}
          <div className="relative flex items-center shrink-0">
            <svg className="absolute start-3 w-4 h-4 text-[var(--text-muted)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input
              type="search"
              value={searchValue}
              onChange={handleSearch}
              placeholder={dtDict.searchPlaceholder}
              className="h-9 w-64 rounded-xl border border-[var(--border)] bg-[var(--surface)] ps-9 pe-4 text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] outline-none focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary-glow)] transition-all"
            />
          </div>

          {/* Page Length Dropdown */}
          <div className="flex items-center gap-2 text-xs font-semibold text-[var(--text-secondary)] shrink-0">
            <span>{dtDict.show}</span>
            <SearchableSelect<number>
              options={lengthOptions.map((n) => ({ value: n, label: String(n) }))}
              value={currentPageLength}
              onChange={(val) => {
                if (val && !isNaN(Number(val))) {
                  setCurrentPageLength(Number(val));
                }
              }}
              isSearchable={true}
              isCreatable={true}
              isClearable={false}
              searchPlaceholder={dtDict.searchOrEnterNumber}
              createOptionLabel={(q) => interpolate(dtDict.createOption, { query: q })}
              className="w-24 min-w-[5.5rem]"
            />
            <span>{dtDict.entries}</span>
          </div>
        </div>

        {/* Custom Page Filters Toolbar */}
        {toolbar ? (
          <div className="sdt-toolbar-container pt-2.5 border-t border-[var(--border)]/40 w-full">
            {toolbar}
          </div>
        ) : null}
      </div>

      <DataTable
        ref={dtRef}
        key={tableId || 'sdt-table'}
        ajax={ajax}
        columns={columns}
        id={tableId}
        options={options}
        slots={slots}
        className="display nowrap w-full"
      />
    </div>
  );
}

export type { DataTableSlots, ServerDataTableProps };
