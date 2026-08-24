import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { formatMoney } from '../../lib/accountingHelpers';

type Customer = {
  id: string;
  code: string;
  name: string;
};

type ReceivableEntry = {
  id: string;
  customer_id: string;
  source_type: string;
  source_id: string;
  entry_date: string;
  due_date?: string;
  description?: string;
  currency: string;
  debit_minor: number;
  credit_minor: number;
  remaining_minor: number;
  customer?: Customer;
};

type Settlement = {
  id: string;
  customer_id: string;
  source_receivable_entry_id: string;
  target_receivable_entry_id: string;
  currency: string;
  amount_minor: number;
  status: 'active' | 'reversed';
  settled_at: string;
  reversed_at?: string;
  reason?: string;
  reversed_reason?: string;
  customer?: Customer;
  source_receivable_entry?: ReceivableEntry;
  target_receivable_entry?: ReceivableEntry;
  creator?: { id: number; name: string };
  reverser?: { id: number; name: string };
};

type Props = {
  creditEntries: ReceivableEntry[];
  selectedSourceEntry: ReceivableEntry | null;
  openTargetDebits: ReceivableEntry[];
  existingSettlements: {
    data: Settlement[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  customers: Customer[];
  filters: {
    customer_id?: string;
    source_entry_id?: string;
  };
};

export default function ReceivableSettlements({
  creditEntries,
  selectedSourceEntry,
  openTargetDebits,
  existingSettlements,
  customers,
  filters,
}: Props) {
  const [selectedCustomerId, setSelectedCustomerId] = useState(filters.customer_id || '');
  const [selectedSourceId, setSelectedSourceId] = useState(filters.source_entry_id || '');
  const [reversingId, setReversingId] = useState<string | null>(null);
  const [reverseReason, setReverseReason] = useState('');

  const settleForm = useForm({
    source_receivable_entry_id: selectedSourceEntry?.id || '',
    lines: openTargetDebits.map((item) => ({
      target_receivable_entry_id: item.id,
      amount_minor: 0,
      reason: '',
    })),
  });

  const reverseForm = useForm({
    reason: '',
  });

  function handleFilterChange(custId: string, sourceId: string) {
    setSelectedCustomerId(custId);
    setSelectedSourceId(sourceId);
    const params = new URLSearchParams();
    if (custId) params.set('customer_id', custId);
    if (sourceId) params.set('source_entry_id', sourceId);
    window.location.href = `/sales/receivable-settlements?${params.toString()}`;
  }

  function handleSettleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!selectedSourceEntry) return;

    const validLines = settleForm.data.lines.filter((l) => l.amount_minor > 0);
    if (validLines.length === 0) {
      alert('Please enter a positive settlement amount for at least one target entry.');
      return;
    }

    router.post(
      '/sales/receivable-settlements',
      {
        source_receivable_entry_id: selectedSourceEntry.id,
        lines: validLines,
      },
      { preserveScroll: true }
    );
  }

  function handleReverseSubmit(e: FormEvent) {
    e.preventDefault();
    if (!reversingId || !reverseReason.trim()) return;

    router.post(
      `/sales/receivable-settlements/${reversingId}/reverse`,
      { reason: reverseReason },
      {
        preserveScroll: true,
        onSuccess: () => {
          setReversingId(null);
          setReverseReason('');
        },
      }
    );
  }

  const fmtMoney = (amount: number, curr: string) => formatMoney(amount, curr);

  return (
    <AppLayout active="customer-credit-notes.index">
      <Head title="AR Settlement of Credit Notes" />

      <div className="mx-auto max-w-7xl px-4 py-6 space-y-6">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-[var(--text-primary)]">
              Manual AR Credit Settlement
            </h1>
            <p className="text-sm text-[var(--text-muted)]">
              Settle posted Customer Credit Notes (credit entries) against open Customer Invoices (debit entries).
            </p>
          </div>
          <Link
            href="/sales/credit-notes"
            className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
          >
            ← Back to Credit Notes
          </Link>
        </div>

        {/* Filter Card */}
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-xs space-y-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-[var(--text-muted)]">
            Step 1: Select Open AR Credit Entry
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                Filter Customer
              </label>
              <select
                value={selectedCustomerId}
                onChange={(e) => handleFilterChange(e.target.value, '')}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden focus:ring-2 focus:ring-blue-500"
              >
                <option value="">All Customers</option>
                {customers.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.code} - {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                Select Credit Entry (Credit Note)
              </label>
              <select
                value={selectedSourceId}
                onChange={(e) => handleFilterChange(selectedCustomerId, e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select open credit entry...</option>
                {creditEntries.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.customer?.name} | Date: {e.entry_date} | Source: {e.source_type} | Remaining Credit: {fmtMoney(e.remaining_minor, e.currency)}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Settlement Action Form */}
        {selectedSourceEntry ? (
          <div className="rounded-2xl border border-blue-500/30 bg-blue-500/5 p-5 space-y-4">
            <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-blue-500/20 pb-3 gap-2">
              <div>
                <h3 className="text-base font-bold text-[var(--text-primary)]">
                  Settling Credit Entry for {selectedSourceEntry.customer?.name}
                </h3>
                <p className="text-xs text-[var(--text-muted)]">
                  Source ID: {selectedSourceEntry.id} | Entry Date: {selectedSourceEntry.entry_date} | Currency: {selectedSourceEntry.currency}
                </p>
              </div>
              <div className="rounded-xl bg-blue-600 px-4 py-2 text-white font-extrabold text-sm">
                Available Credit: {fmtMoney(selectedSourceEntry.remaining_minor, selectedSourceEntry.currency)}
              </div>
            </div>

            <form onSubmit={handleSettleSubmit} className="space-y-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
                Step 2: Allocate Credit to Open Target Invoices
              </h4>

              {openTargetDebits.length === 0 ? (
                <p className="text-xs text-[var(--text-muted)] py-4 text-center">
                  No open target debit entries (invoices) found for this customer and currency.
                </p>
              ) : (
                <div className="overflow-x-auto rounded-xl border border-[var(--border)] bg-[var(--surface)]">
                  <table className="w-full text-start text-xs">
                    <thead className="bg-[var(--background)] text-[var(--text-muted)] font-bold">
                      <tr>
                        <th className="p-3 text-start">Entry Date</th>
                        <th className="p-3 text-start">Source / Ref</th>
                        <th className="p-3 text-start">Description</th>
                        <th className="p-3 text-end">Original Debit</th>
                        <th className="p-3 text-end">Remaining Open Debit</th>
                        <th className="p-3 text-end w-48">Settlement Amount</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border)]">
                      {openTargetDebits.map((target, idx) => {
                        const line = settleForm.data.lines.find((l) => l.target_receivable_entry_id === target.id);
                        return (
                          <tr key={target.id} className="hover:bg-[var(--background)]/50">
                            <td className="p-3">{target.entry_date}</td>
                            <td className="p-3 font-mono">{target.source_type} ({target.id.substring(0, 8)})</td>
                            <td className="p-3">{target.description || 'Invoice debit'}</td>
                            <td className="p-3 text-end font-semibold">{fmtMoney(target.debit_minor, target.currency)}</td>
                            <td className="p-3 text-end font-bold text-emerald-600">{fmtMoney(target.remaining_minor, target.currency)}</td>
                            <td className="p-3 text-end">
                              <input
                                type="number"
                                min="0"
                                max={Math.min(target.remaining_minor, selectedSourceEntry.remaining_minor)}
                                value={line?.amount_minor || ''}
                                onChange={(e) => {
                                  const val = parseInt(e.target.value, 10) || 0;
                                  const newLines = [...settleForm.data.lines];
                                  const targetLineIdx = newLines.findIndex((l) => l.target_receivable_entry_id === target.id);
                                  if (targetLineIdx >= 0) {
                                    newLines[targetLineIdx].amount_minor = val;
                                  }
                                  settleForm.setData('lines', newLines);
                                }}
                                placeholder="0"
                                className="w-full text-end rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 text-xs text-[var(--text-primary)] font-mono font-bold focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                              />
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}

              {openTargetDebits.length > 0 ? (
                <div className="flex justify-end gap-3 pt-2">
                  <button
                    type="submit"
                    disabled={settleForm.processing}
                    className="rounded-xl bg-blue-600 px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-blue-700 disabled:opacity-50 transition-all cursor-pointer"
                  >
                    {settleForm.processing ? 'Processing Settlement...' : 'Confirm Settlement'}
                  </button>
                </div>
              ) : null}
            </form>
          </div>
        ) : null}

        {/* Existing Settlements History */}
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-xs space-y-4">
          <h2 className="text-base font-bold text-[var(--text-primary)]">
            Settlement Audit Log & Reversals
          </h2>

          <div className="overflow-x-auto rounded-xl border border-[var(--border)]">
            <table className="w-full text-start text-xs">
              <thead className="bg-[var(--background)] text-[var(--text-muted)] font-bold">
                <tr>
                  <th className="p-3 text-start">Settled At</th>
                  <th className="p-3 text-start">Customer</th>
                  <th className="p-3 text-start">Source Entry</th>
                  <th className="p-3 text-start">Target Entry</th>
                  <th className="p-3 text-end">Amount</th>
                  <th className="p-3 text-center">Status</th>
                  <th className="p-3 text-end">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {existingSettlements.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-4 text-center text-[var(--text-muted)]">
                      No settlements recorded yet.
                    </td>
                  </tr>
                ) : (
                  existingSettlements.data.map((s) => (
                    <tr key={s.id} className="hover:bg-[var(--background)]/50">
                      <td className="p-3 font-mono">{new Date(s.settled_at).toLocaleString()}</td>
                      <td className="p-3 font-semibold">{s.customer?.name}</td>
                      <td className="p-3 font-mono text-[10px]">{s.source_receivable_entry_id.substring(0, 8)}</td>
                      <td className="p-3 font-mono text-[10px]">{s.target_receivable_entry_id.substring(0, 8)}</td>
                      <td className="p-3 text-end font-extrabold">{fmtMoney(s.amount_minor, s.currency)}</td>
                      <td className="p-3 text-center">
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase ${
                            s.status === 'active'
                              ? 'bg-emerald-500/15 text-emerald-600'
                              : 'bg-red-500/15 text-red-600'
                          }`}
                        >
                          {s.status}
                        </span>
                      </td>
                      <td className="p-3 text-end">
                        {s.status === 'active' ? (
                          <button
                            type="button"
                            onClick={() => setReversingId(s.id)}
                            className="rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-[10px] font-bold text-red-600 hover:bg-red-500/20 transition-all cursor-pointer"
                          >
                            Reverse
                          </button>
                        ) : (
                          <span className="text-[10px] text-[var(--text-muted)]">Reversed</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Reversal Modal */}
        {reversingId ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div className="w-full max-w-md rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl space-y-4">
              <h3 className="text-base font-bold text-[var(--text-primary)]">
                Reverse Settlement
              </h3>
              <p className="text-xs text-[var(--text-muted)]">
                Reversing this settlement will restore the open credit on the source credit note and open debit on the target invoice.
              </p>
              <form onSubmit={handleReverseSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    Reversal Reason <span className="text-red-500">*</span>
                  </label>
                  <textarea
                    required
                    value={reverseReason}
                    onChange={(e) => setReverseReason(e.target.value)}
                    placeholder="Enter reason for reversal..."
                    rows={3}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)] focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div className="flex justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => setReversingId(null)}
                    className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={reverseForm.processing}
                    className="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white shadow-md hover:bg-red-700 disabled:opacity-50"
                  >
                    {reverseForm.processing ? 'Reversing...' : 'Confirm Reversal'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        ) : null}
      </div>
    </AppLayout>
  );
}
