import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';

type Supplier = {
  id: string;
  code: string;
  name: string;
};

type PayableEntry = {
  id: string;
  supplier_id: string;
  source_type: string;
  source_id: string;
  entry_date: string;
  due_date?: string;
  description?: string;
  currency: string;
  debit_minor: number;
  credit_minor: number;
  remaining_minor: number;
  supplier?: Supplier;
};

type Settlement = {
  id: string;
  supplier_id: string;
  source_payable_entry_id: string;
  target_payable_entry_id: string;
  currency: string;
  amount_minor: number;
  status: 'active' | 'reversed';
  settled_at: string;
  reversed_at?: string;
  reason?: string;
  reversed_reason?: string;
  supplier?: Supplier;
  source_payable_entry?: PayableEntry;
  target_payable_entry?: PayableEntry;
  creator?: { id: number; name: string };
  reverser?: { id: number; name: string };
};

type Props = {
  debitEntries: PayableEntry[];
  selectedSourceEntry: PayableEntry | null;
  openTargetCredits: PayableEntry[];
  existingSettlements: {
    data: Settlement[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  suppliers: Supplier[];
  filters: {
    supplier_id?: string;
    source_entry_id?: string;
  };
};

export default function PayableSettlements({
  debitEntries,
  selectedSourceEntry,
  openTargetCredits,
  existingSettlements,
  suppliers,
  filters,
}: Props) {
  const [selectedSupplierId, setSelectedSupplierId] = useState(filters.supplier_id || '');
  const [selectedSourceId, setSelectedSourceId] = useState(filters.source_entry_id || '');
  const [reversingId, setReversingId] = useState<string | null>(null);
  const [reverseReason, setReverseReason] = useState('');

  const settleForm = useForm({
    source_payable_entry_id: selectedSourceEntry?.id || '',
    lines: openTargetCredits.map((item) => ({
      target_payable_entry_id: item.id,
      amount_minor: 0,
      reason: '',
    })),
  });

  const reverseForm = useForm({
    reason: '',
  });

  function handleFilterChange(suppId: string, sourceId: string) {
    setSelectedSupplierId(suppId);
    setSelectedSourceId(sourceId);
    const params = new URLSearchParams();
    if (suppId) params.set('supplier_id', suppId);
    if (sourceId) params.set('source_entry_id', sourceId);
    window.location.href = `/purchasing/payable-settlements?${params.toString()}`;
  }

  function handleSettleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!selectedSourceEntry) return;

    const validLines = settleForm.data.lines.filter((l) => l.amount_minor > 0);
    if (validLines.length === 0) {
      alert('Please enter a positive settlement amount for at least one target bill entry.');
      return;
    }

    router.post(
      '/purchasing/payable-settlements',
      {
        source_payable_entry_id: selectedSourceEntry.id,
        lines: validLines,
      },
      { preserveScroll: true }
    );
  }

  function handleReverseSubmit(e: FormEvent) {
    e.preventDefault();
    if (!reversingId || !reverseReason.trim()) return;

    router.post(
      `/purchasing/payable-settlements/${reversingId}/reverse`,
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

  const fmtMoney = (amount: number, curr: string) =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount / 100) +
    ' ' +
    curr;

  return (
    <AppLayout active="supplier-adjustment-notes.index">
      <Head title="AP Settlement of Adjustment Notes" />

      <div className="mx-auto max-w-7xl px-4 py-6 space-y-6">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-[var(--text-primary)]">
              Manual AP Debit Settlement
            </h1>
            <p className="text-sm text-[var(--text-muted)]">
              Settle posted Supplier Adjustment Notes (decrease-payable debit entries) against open Supplier Bills (credit entries).
            </p>
          </div>
          <Link
            href="/purchasing/adjustment-notes"
            className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
          >
            ← Back to Adjustment Notes
          </Link>
        </div>

        {/* Filter Card */}
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-xs space-y-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-[var(--text-muted)]">
            Step 1: Select Open AP Debit Entry
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                Filter Supplier
              </label>
              <select
                value={selectedSupplierId}
                onChange={(e) => handleFilterChange(e.target.value, '')}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden focus:ring-2 focus:ring-blue-500"
              >
                <option value="">All Suppliers</option>
                {suppliers.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.code} - {s.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                Select Debit Entry (Adjustment Note)
              </label>
              <select
                value={selectedSourceId}
                onChange={(e) => handleFilterChange(selectedSupplierId, e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select open debit entry...</option>
                {debitEntries.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.supplier?.name} | Date: {e.entry_date} | Source: {e.source_type} | Remaining Debit: {fmtMoney(e.remaining_minor, e.currency)}
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
                  Settling Adjustment Debit Entry for {selectedSourceEntry.supplier?.name}
                </h3>
                <p className="text-xs text-[var(--text-muted)]">
                  Source ID: {selectedSourceEntry.id} | Entry Date: {selectedSourceEntry.entry_date} | Currency: {selectedSourceEntry.currency}
                </p>
              </div>
              <div className="rounded-xl bg-blue-600 px-4 py-2 text-white font-extrabold text-sm">
                Available Debit: {fmtMoney(selectedSourceEntry.remaining_minor, selectedSourceEntry.currency)}
              </div>
            </div>

            <form onSubmit={handleSettleSubmit} className="space-y-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
                Step 2: Allocate Debit to Open Target Supplier Bills
              </h4>

              {openTargetCredits.length === 0 ? (
                <p className="text-xs text-[var(--text-muted)] py-4 text-center">
                  No open target credit entries (bills) found for this supplier and currency.
                </p>
              ) : (
                <div className="overflow-x-auto rounded-xl border border-[var(--border)] bg-[var(--surface)]">
                  <table className="w-full text-start text-xs">
                    <thead className="bg-[var(--background)] text-[var(--text-muted)] font-bold">
                      <tr>
                        <th className="p-3 text-start">Entry Date</th>
                        <th className="p-3 text-start">Source / Ref</th>
                        <th className="p-3 text-start">Description</th>
                        <th className="p-3 text-end">Original Credit</th>
                        <th className="p-3 text-end">Remaining Open Credit</th>
                        <th className="p-3 text-end w-48">Settlement Amount</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border)]">
                      {openTargetCredits.map((target, idx) => {
                        const line = settleForm.data.lines.find((l) => l.target_payable_entry_id === target.id);
                        return (
                          <tr key={target.id} className="hover:bg-[var(--background)]/50">
                            <td className="p-3">{target.entry_date}</td>
                            <td className="p-3 font-mono">{target.source_type} ({target.id.substring(0, 8)})</td>
                            <td className="p-3">{target.description || 'Supplier bill credit'}</td>
                            <td className="p-3 text-end font-semibold">{fmtMoney(target.credit_minor, target.currency)}</td>
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
                                  const targetLineIdx = newLines.findIndex((l) => l.target_payable_entry_id === target.id);
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

              {openTargetCredits.length > 0 ? (
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
                  <th className="p-3 text-start">Supplier</th>
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
                      <td className="p-3 font-semibold">{s.supplier?.name}</td>
                      <td className="p-3 font-mono text-[10px]">{s.source_payable_entry_id.substring(0, 8)}</td>
                      <td className="p-3 font-mono text-[10px]">{s.target_payable_entry_id.substring(0, 8)}</td>
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
                Reversing this settlement will restore the open debit on the source adjustment note and open credit on the target bill.
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
