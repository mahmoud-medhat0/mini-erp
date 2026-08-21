import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type AuditLogRow = {
  id: string;
  actor_id: number | string | null;
  actor_name?: string | null;
  actor_email?: string | null;
  action: string;
  entity_type: string;
  entity_id: string;
  before_json?: string | null;
  after_json?: string | null;
  reason?: string | null;
  request_id?: string | null;
  ip?: string | null;
  device?: string | null;
  at: string;
};

type UserOption = {
  id: number;
  name: string;
  email: string;
};

type PaginatedAuditLogs = {
  data: AuditLogRow[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type AuditLogProps = SharedPageProps & {
  logs: PaginatedAuditLogs;
  filters: {
    actor_id?: string;
    action?: string;
    entity_type?: string;
    entity_id?: string;
    request_id?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
  };
  actions: string[];
  entityTypes: string[];
  usersList: UserOption[];
};

export default function AuditLogIndex({
  locale,
  logs,
  filters,
  actions,
  entityTypes,
  usersList,
}: AuditLogProps) {
  const dict = getDictionary(locale);
  const isAr = locale === 'ar';

  const [searchFilter, setSearchFilter] = useState(filters.search ?? '');
  const [actorFilter, setActorFilter] = useState(filters.actor_id ?? '');
  const [actionFilter, setActionFilter] = useState(filters.action ?? '');
  const [entityTypeFilter, setEntityTypeFilter] = useState(filters.entity_type ?? '');
  const [requestIdFilter, setRequestIdFilter] = useState(filters.request_id ?? '');
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
  const [dateTo, setDateTo] = useState(filters.date_to ?? '');
  const [selectedPayload, setSelectedPayload] = useState<AuditLogRow | null>(null);

  function handleFilterSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    router.get(
      '/audit-log',
      {
        search: searchFilter || undefined,
        actor_id: actorFilter || undefined,
        action: actionFilter || undefined,
        entity_type: entityTypeFilter || undefined,
        request_id: requestIdFilter || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      },
      { preserveState: true }
    );
  }

  function handleReset() {
    setSearchFilter('');
    setActorFilter('');
    setActionFilter('');
    setEntityTypeFilter('');
    setRequestIdFilter('');
    setDateFrom('');
    setDateTo('');
    router.get('/audit-log', {}, { preserveState: true });
  }

  function parseJsonPayload(raw?: string | null) {
    if (!raw) return null;
    try {
      return JSON.stringify(JSON.parse(raw), null, 2);
    } catch {
      return raw;
    }
  }

  return (
    <AppLayout active="audit.view">
      <Head title={isAr ? 'سجل التدقيق' : 'Audit Log'} />

      <PageHeader
        title={isAr ? 'سجل التدقيق والعمليات' : 'System Audit Log'}
        description={
          isAr
            ? 'سجل التغييرات والأحداث غير القابل للتعديل للنظام (Append-only audit trail)'
            : 'Immutable append-only audit trail recording all system transactions and security actions'
        }
      />

      {/* Filter Bar */}
      <Card className="p-4 mb-6 border-[var(--border)] bg-[var(--surface)]">
        <form onSubmit={handleFilterSubmit} className="space-y-3">
          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-3">
            {/* Search Input */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'بحث عام' : 'Search'}
              </label>
              <input
                type="text"
                value={searchFilter}
                onChange={(e) => setSearchFilter(e.target.value)}
                placeholder={isAr ? 'بحث عن إجراء أو كائن...' : 'Search action or entity...'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden"
              />
            </div>

            {/* Actor Filter */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'المستخدم' : 'Actor / User'}
              </label>
              <select
                value={actorFilter}
                onChange={(e) => setActorFilter(e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden cursor-pointer"
              >
                <option value="">{isAr ? 'جميع المستخدمين' : 'All Users'}</option>
                {usersList.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} ({u.email})
                  </option>
                ))}
              </select>
            </div>

            {/* Action Filter */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'الإجراء' : 'Action'}
              </label>
              <select
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden cursor-pointer"
              >
                <option value="">{isAr ? 'جميع الإجراءات' : 'All Actions'}</option>
                {actions.map((act) => (
                  <option key={act} value={act}>
                    {act}
                  </option>
                ))}
              </select>
            </div>

            {/* Entity Type Filter */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'نوع الكائن' : 'Entity Type'}
              </label>
              <select
                value={entityTypeFilter}
                onChange={(e) => setEntityTypeFilter(e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden cursor-pointer"
              >
                <option value="">{isAr ? 'جميع الكائنات' : 'All Entities'}</option>
                {entityTypes.map((ent) => (
                  <option key={ent} value={ent}>
                    {ent}
                  </option>
                ))}
              </select>
            </div>

            {/* Request ID Filter */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'معرف الطلب' : 'Request ID'}
              </label>
              <input
                type="text"
                value={requestIdFilter}
                onChange={(e) => setRequestIdFilter(e.target.value)}
                placeholder={isAr ? 'req-...' : 'req-...'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden"
              />
            </div>

            {/* Date From */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'من تاريخ' : 'Date From'}
              </label>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden"
              />
            </div>

            {/* Date To */}
            <div>
              <label className="block text-[11px] font-bold text-[var(--text-secondary)] mb-1">
                {isAr ? 'إلى تاريخ' : 'Date To'}
              </label>
              <input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] outline-hidden"
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border)]">
            <button
              type="button"
              onClick={handleReset}
              className="px-3 py-1.5 text-xs font-bold rounded-xl border border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-all cursor-pointer"
            >
              {isAr ? 'إعادة ضبط' : 'Reset'}
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 text-xs font-bold rounded-xl bg-[var(--primary)] text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {isAr ? 'تصفية النتائج' : 'Filter Logs'}
            </button>
          </div>
        </form>
      </Card>

      {/* Main Audit Table */}
      {logs.data.length === 0 ? (
        <EmptyState title={isAr ? 'لا توجد سجلات تدقيق مطابقة' : 'No audit log records found'} />
      ) : (
        <Card className="overflow-hidden border-[var(--border)]">
          <div className="overflow-x-auto">
            <table className={tableClasses.table}>
              <thead>
                <tr className="border-b border-[var(--border)] bg-[var(--background)]/50">
                  <th className={tableClasses.th}>{isAr ? 'التاريخ والوقت' : 'Timestamp'}</th>
                  <th className={tableClasses.th}>{isAr ? 'المستخدم' : 'Actor'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الإجراء' : 'Action'}</th>
                  <th className={tableClasses.th}>{isAr ? 'نوع الكائن' : 'Entity Type'}</th>
                  <th className={tableClasses.th}>{isAr ? 'معرف الكائن' : 'Entity ID'}</th>
                  <th className={tableClasses.th}>{isAr ? 'معرف الطلب' : 'Request ID'}</th>
                  <th className={`${tableClasses.th} text-end`}>{isAr ? 'التفاصيل' : 'Details'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {logs.data.map((log) => (
                  <tr key={log.id} className="hover:bg-[var(--background)]/50 transition-colors">
                    <td className={tableClasses.td}>
                      <span className="text-xs font-semibold text-[var(--text-secondary)] font-mono">
                        {new Date(log.at).toLocaleString(locale)}
                      </span>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-col">
                        <span className="text-xs font-bold text-[var(--text-primary)]">
                          {log.actor_name || (log.actor_id ? `User #${log.actor_id}` : (isAr ? 'النظام' : 'System'))}
                        </span>
                        {log.actor_email ? (
                          <span className="text-[10px] text-[var(--text-muted)] font-mono">{log.actor_email}</span>
                        ) : null}
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400 border border-blue-500/20 font-mono">
                        {log.action}
                      </span>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="text-xs font-semibold text-[var(--text-primary)]">{log.entity_type}</span>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-mono text-xs text-[var(--text-muted)] truncate max-w-[120px] block" title={log.entity_id}>
                        {log.entity_id}
                      </span>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-mono text-[10px] text-[var(--text-muted)]">
                        {log.request_id ? log.request_id.substring(0, 8) : '-'}
                      </span>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      {log.before_json || log.after_json ? (
                        <button
                          type="button"
                          onClick={() => setSelectedPayload(log)}
                          className="inline-flex items-center gap-1 rounded-lg border border-blue-500/20 bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-all cursor-pointer"
                        >
                          <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          <span>{isAr ? 'عرض الحمولة' : 'View Payload'}</span>
                        </button>
                      ) : (
                        <span className="text-xs text-[var(--text-muted)]">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination Controls */}
          <div className="flex items-center justify-between p-4 border-t border-[var(--border)] bg-[var(--surface)]">
            <span className="text-xs text-[var(--text-muted)]">
              {isAr ? `إجمالي السجلات: ${logs.total}` : `Total records: ${logs.total}`}
            </span>
            <div className="flex items-center gap-2">
              {logs.prev_page_url ? (
                <button
                  type="button"
                  onClick={() => router.get(logs.prev_page_url!)}
                  className="px-3 py-1 text-xs font-bold rounded-lg border border-[var(--border)] bg-[var(--surface)] text-[var(--text-primary)] hover:border-[var(--primary)] transition-all cursor-pointer"
                >
                  {isAr ? 'السابق' : 'Previous'}
                </button>
              ) : null}
              <span className="text-xs font-bold text-[var(--text-primary)] px-2">
                {logs.current_page} / {logs.last_page}
              </span>
              {logs.next_page_url ? (
                <button
                  type="button"
                  onClick={() => router.get(logs.next_page_url!)}
                  className="px-3 py-1 text-xs font-bold rounded-lg border border-[var(--border)] bg-[var(--surface)] text-[var(--text-primary)] hover:border-[var(--primary)] transition-all cursor-pointer"
                >
                  {isAr ? 'التالي' : 'Next'}
                </button>
              ) : null}
            </div>
          </div>
        </Card>
      )}

      {/* Expandable JSON Payload Drawer / Modal */}
      {selectedPayload ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <Card className="w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col border-[var(--border)] bg-[var(--surface)] shadow-2xl">
            <div className="flex items-center justify-between border-b border-[var(--border)] p-4">
              <div>
                <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {isAr ? 'حمولة التغيير (JSON Payload)' : 'Audit Record JSON Payload'}
                </h3>
                <span className="text-xs text-[var(--text-muted)] font-mono">
                  {selectedPayload.action} • {selectedPayload.entity_type} #{selectedPayload.entity_id}
                </span>
              </div>
              <button
                type="button"
                onClick={() => setSelectedPayload(null)}
                className="p-1 text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="p-4 overflow-y-auto space-y-4 font-mono text-xs">
              {selectedPayload.before_json ? (
                <div>
                  <h5 className="m-0 mb-1 font-bold text-amber-600 dark:text-amber-400">
                    {isAr ? 'الحالة القبلية (BEFORE):' : 'State Before (BEFORE):'}
                  </h5>
                  <pre className="p-3 rounded-xl bg-[var(--background)] border border-[var(--border)] text-[var(--text-primary)] overflow-x-auto">
                    {parseJsonPayload(selectedPayload.before_json)}
                  </pre>
                </div>
              ) : null}

              {selectedPayload.after_json ? (
                <div>
                  <h5 className="m-0 mb-1 font-bold text-emerald-600 dark:text-emerald-400">
                    {isAr ? 'الحالة البعدية (AFTER):' : 'State After (AFTER):'}
                  </h5>
                  <pre className="p-3 rounded-xl bg-[var(--background)] border border-[var(--border)] text-[var(--text-primary)] overflow-x-auto">
                    {parseJsonPayload(selectedPayload.after_json)}
                  </pre>
                </div>
              ) : null}
            </div>

            <div className="border-t border-[var(--border)] p-3 text-end bg-[var(--background)]/50">
              <button
                type="button"
                onClick={() => setSelectedPayload(null)}
                className="px-4 py-1.5 text-xs font-bold rounded-xl bg-[var(--primary)] text-white hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
              >
                {isAr ? 'إغلاق' : 'Close'}
              </button>
            </div>
          </Card>
        </div>
      ) : null}
    </AppLayout>
  );
}
