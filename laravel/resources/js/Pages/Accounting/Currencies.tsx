import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, tableClasses } from '../../Components/Primitives';
import { getAccountTypeLabel, getLocalizedName, getAccountNatureLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { AccountItem, CurrencyItem, FxRateItem, SharedPageProps } from '../../Types';

type CurrenciesProps = SharedPageProps & {
  currencies: CurrencyItem[];
};

export default function Currencies({ locale, currencies = [] }: CurrenciesProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};

  const [search, setSearch] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingCurrency, setEditingCurrency] = useState<CurrencyItem | null>(null);
  const [deletingCurrency, setDeletingCurrency] = useState<CurrencyItem | null>(null);
  const [selectedAccountsCurrency, setSelectedAccountsCurrency] = useState<CurrencyItem | null>(null);
  const [selectedFxRatesCurrency, setSelectedFxRatesCurrency] = useState<CurrencyItem | null>(null);

  const getName = (nameObj?: Record<string, string> | string | null) => getLocalizedName(nameObj, locale);

  const getNameEn = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return nameObj.en || '';
  };

  const getNameAr = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return nameObj.ar || '';
  };

  const createForm = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    symbol: '',
    exponent: 2,
  });

  const editForm = useForm({
    name_en: '',
    name_ar: '',
    symbol: '',
    exponent: 2,
  });

  function handleCreateSubmit(e: FormEvent) {
    e.preventDefault();
    createForm.post('/accounting/currencies', {
      onSuccess: () => {
        setShowCreateModal(false);
        createForm.reset();
      },
    });
  }

  function handleEditSubmit(e: FormEvent) {
    e.preventDefault();
    if (!editingCurrency) return;
    editForm.patch(`/accounting/currencies/${editingCurrency.code}`, {
      onSuccess: () => {
        setEditingCurrency(null);
        editForm.reset();
      },
    });
  }

  function handleDeleteConfirm() {
    if (!deletingCurrency) return;
    router.delete(`/accounting/currencies/${deletingCurrency.code}`, {
      onSuccess: () => setDeletingCurrency(null),
    });
  }

  function startEdit(curr: CurrencyItem) {
    setEditingCurrency(curr);
    editForm.setData({
      name_en: getNameEn(curr.name),
      name_ar: getNameAr(curr.name),
      symbol: curr.symbol,
      exponent: curr.exponent,
    });
  }

  const filteredCurrencies = currencies.filter((c) => {
    const q = search.toLowerCase();
    return (
      c.code.toLowerCase().includes(q) ||
      getName(c.name).toLowerCase().includes(q) ||
      c.symbol.toLowerCase().includes(q)
    );
  });

  return (
    <AppLayout active="accounting.currencies">
      <Head title={accDict.currencies || 'Currencies'} />

      <PageHeader
        title={accDict.currencies || 'Currencies'}
        description={accDict.currenciesDesc || 'Configure system ISO currency codes, symbols, decimal precision, and entity associations.'}
        actions={
          <button
            type="button"
            onClick={() => setShowCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.addCurrency || 'Add Currency'}</span>
          </button>
        }
      />

      {/* Summary Cards */}
      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <Card className="p-5 relative overflow-hidden group border border-[var(--border)] hover:border-blue-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.totalCurrencies || 'Total Currencies'}
              </span>
              <p className="mt-1 text-3xl font-black font-mono text-[var(--text-primary)]">{currencies.length}</p>
            </div>
            <div className="p-3 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </Card>

        <Card className="p-5 relative overflow-hidden group border border-[var(--border)] hover:border-indigo-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.linkedAccounts || 'Linked Accounts'}
              </span>
              <p className="mt-1 text-3xl font-black font-mono text-[var(--primary)]">
                {currencies.reduce((sum, c) => sum + (c.accounts_count || 0), 0)}
              </p>
            </div>
            <div className="p-3 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9 6 9-6M3 7l9-6 9 6" />
              </svg>
            </div>
          </div>
        </Card>

        <Card className="p-5 relative overflow-hidden group border border-[var(--border)] hover:border-emerald-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.fxRatesConfigured || 'FX Rates Configured'}
              </span>
              <p className="mt-1 text-3xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                {currencies.reduce((sum, c) => sum + (c.exchange_rates_count || 0), 0)}
              </p>
            </div>
            <div className="p-3 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
          </div>
        </Card>
      </div>

      {/* Create Currency Modal */}
      {showCreateModal ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-5">
            <div className="flex items-center gap-2">
              <div className="size-2 rounded-full bg-[var(--primary)] animate-pulse" />
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.addCurrency || 'Add Currency'}</h3>
            </div>
            <button
              type="button"
              onClick={() => setShowCreateModal(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
            </button>
          </div>

          <form onSubmit={handleCreateSubmit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.isoCode || 'ISO Code'}
              </label>
              <input
                type="text"
                maxLength={3}
                placeholder="USD"
                value={createForm.data.code}
                onChange={(e) => createForm.setData('code', e.target.value.toUpperCase())}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono uppercase focus:ring-2 focus:ring-blue-500/20"
                required
              />
              {createForm.errors.code ? <p className="text-xs text-red-500 mt-1">{createForm.errors.code}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.nameEn || 'English Name'}
              </label>
              <input
                type="text"
                placeholder="US Dollar"
                value={createForm.data.name_en}
                onChange={(e) => createForm.setData('name_en', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {createForm.errors.name_en ? <p className="text-xs text-red-500 mt-1">{createForm.errors.name_en}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.nameAr || 'Arabic Name'}
              </label>
              <input
                type="text"
                placeholder="الدولار الأمريكي"
                value={createForm.data.name_ar}
                onChange={(e) => createForm.setData('name_ar', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {createForm.errors.name_ar ? <p className="text-xs text-red-500 mt-1">{createForm.errors.name_ar}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.symbol || 'Symbol'}
              </label>
              <input
                type="text"
                placeholder="$"
                value={createForm.data.symbol}
                onChange={(e) => createForm.setData('symbol', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono"
                required
              />
              {createForm.errors.symbol ? <p className="text-xs text-red-500 mt-1">{createForm.errors.symbol}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.minorExponent || 'Minor Exponent'}
              </label>
              <input
                type="number"
                min="0"
                max="4"
                value={createForm.data.exponent}
                onChange={(e) => createForm.setData('exponent', Number(e.target.value))}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono"
                required
              />
              {createForm.errors.exponent ? <p className="text-xs text-red-500 mt-1">{createForm.errors.exponent}</p> : null}
            </div>

            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 mt-2">
              <button
                type="button"
                onClick={() => setShowCreateModal(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={createForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save || 'Save'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Edit Currency Modal */}
      {editingCurrency ? (
        <Card className="p-6 mb-6 border-2 border-indigo-500/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-5">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {accDict.editCurrency || 'Edit Currency'}: <span className="font-mono px-2 py-0.5 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">{editingCurrency.code}</span>
            </h3>
            <button
              type="button"
              onClick={() => setEditingCurrency(null)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
            </button>
          </div>

          <form onSubmit={handleEditSubmit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.nameEn || 'English Name'}
              </label>
              <input
                type="text"
                value={editForm.data.name_en}
                onChange={(e) => editForm.setData('name_en', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {editForm.errors.name_en ? <p className="text-xs text-red-500 mt-1">{editForm.errors.name_en}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.nameAr || 'Arabic Name'}
              </label>
              <input
                type="text"
                value={editForm.data.name_ar}
                onChange={(e) => editForm.setData('name_ar', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {editForm.errors.name_ar ? <p className="text-xs text-red-500 mt-1">{editForm.errors.name_ar}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.symbol || 'Symbol'}
              </label>
              <input
                type="text"
                value={editForm.data.symbol}
                onChange={(e) => editForm.setData('symbol', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono"
                required
              />
              {editForm.errors.symbol ? <p className="text-xs text-red-500 mt-1">{editForm.errors.symbol}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.minorExponent || 'Minor Exponent'}
              </label>
              <input
                type="number"
                min="0"
                max="4"
                value={editForm.data.exponent}
                onChange={(e) => editForm.setData('exponent', Number(e.target.value))}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono"
                required
              />
              {editForm.errors.exponent ? <p className="text-xs text-red-500 mt-1">{editForm.errors.exponent}</p> : null}
            </div>

            <div className="sm:col-span-2 lg:col-span-4 flex justify-end gap-3 mt-2">
              <button
                type="button"
                onClick={() => setEditingCurrency(null)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={editForm.processing}
                className="rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-indigo-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save || 'Save'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Delete Currency Confirmation Modal */}
      {deletingCurrency ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <Card className="max-w-md w-full p-6 border-2 border-red-500/40 shadow-2xl">
            <div className="flex items-center gap-3 text-red-600 dark:text-red-400 mb-3">
              <div className="p-2.5 rounded-xl bg-red-500/10">
                <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">Delete Currency</h3>
            </div>
            <p className="text-xs text-[var(--text-secondary)] leading-relaxed">
              Are you sure you want to delete currency <span className="font-mono font-bold text-red-500">{deletingCurrency.code}</span> ({getName(deletingCurrency.name)})?
            </p>
            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                onClick={() => setDeletingCurrency(null)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="button"
                onClick={handleDeleteConfirm}
                className="rounded-xl bg-red-600 px-4.5 py-2 text-xs font-bold text-white hover:bg-red-700 shadow-md shadow-red-500/20 transition-all cursor-pointer"
              >
                {actionsDict.delete || 'Delete'}
              </button>
            </div>
          </Card>
        </div>
      ) : null}

      {/* Accounts Detail Modal */}
      {selectedAccountsCurrency ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <Card className="max-w-2xl w-full p-6 border border-[var(--border)] shadow-2xl bg-[var(--surface)]">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
              <div className="flex items-center gap-2">
                <div className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9 6 9-6M3 7l9-6 9 6" />
                  </svg>
                </div>
                <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {accDict.linkedAccountsFor || 'Linked Accounts for'}{' '}
                  <span className="font-mono text-[var(--primary)]">{selectedAccountsCurrency.code}</span> ({getName(selectedAccountsCurrency.name)})
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setSelectedAccountsCurrency(null)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
              >
                <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{actionsDict.cancel || 'Close'}</span>
              </button>
            </div>

            {selectedAccountsCurrency.accounts && selectedAccountsCurrency.accounts.length > 0 ? (
              <div className="max-h-80 overflow-y-auto rounded-xl border border-[var(--border)]">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{accDict.accountCode || 'Account Code'}</th>
                      <th className={tableClasses.th}>{accDict.accountName || 'Account Name'}</th>
                      <th className={tableClasses.th}>{accDict.accountType || 'Type'}</th>
                      <th className={tableClasses.th}>{accDict.accountNature || 'Nature'}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {selectedAccountsCurrency.accounts.map((acc) => (
                      <tr key={acc.id} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <Link
                            href={`/accounting/ledger?account_id=${acc.id}`}
                            className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400 hover:underline hover:text-blue-700 dark:hover:text-blue-300 transition-colors inline-flex items-center gap-1 group"
                            title={accDict.viewLedgerTitle || 'View General Ledger for this account'}
                          >
                            <span>{acc.code}</span>
                            <svg className="size-3 text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                          </Link>
                        </td>
                        <td className={tableClasses.td}>
                          <Link
                            href={`/accounting/ledger?account_id=${acc.id}`}
                            className="text-xs font-medium text-[var(--text-primary)] hover:text-blue-600 dark:hover:text-blue-400 hover:underline transition-colors"
                            title={accDict.viewLedgerTitle || 'View General Ledger for this account'}
                          >
                            {getName(acc.name)}
                          </Link>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                            {getAccountTypeLabel(acc.type, locale)}
                          </span>
                        </td>
                        <td className={tableClasses.td}>
                          {acc.nature.toLowerCase() === 'debit' ? (
                            <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                              {getAccountNatureLabel(acc.nature, locale)}
                            </span>
                          ) : (
                            <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                              {getAccountNatureLabel(acc.nature)}
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-xs text-[var(--text-muted)] py-4 text-center">
                {accDict.noAccountsLinked || 'No accounts linked to this currency.'}
              </p>
            )}
          </Card>
        </div>
      ) : null}

      {/* FX Rates Detail Modal */}
      {selectedFxRatesCurrency ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <Card className="max-w-2xl w-full p-6 border border-[var(--border)] shadow-2xl bg-[var(--surface)]">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
              <div className="flex items-center gap-2">
                <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                  </svg>
                </div>
                <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {accDict.recordedFxRatesFor || 'Recorded FX Rates for'}{' '}
                  <span className="font-mono text-[var(--primary)]">{selectedFxRatesCurrency.code}</span> ({getName(selectedFxRatesCurrency.name)})
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setSelectedFxRatesCurrency(null)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
              >
                <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{actionsDict.cancel || 'Close'}</span>
              </button>
            </div>

            {selectedFxRatesCurrency.exchange_rates && selectedFxRatesCurrency.exchange_rates.length > 0 ? (
              <div className="max-h-80 overflow-y-auto rounded-xl border border-[var(--border)]">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{accDict.effectiveDate || 'Effective Date'}</th>
                      <th className={tableClasses.th}>{accDict.rateDecimal || 'Rate (Decimal)'}</th>
                      <th className={tableClasses.th}>{accDict.rateE6 || 'Rate E6 (Scaled Integer)'}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {selectedFxRatesCurrency.exchange_rates.map((fx) => (
                      <tr key={fx.id} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <span className="font-mono text-xs text-[var(--text-primary)]">{fx.date.split('T')[0]}</span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-mono font-bold text-xs text-[var(--primary)]">
                            {(fx.rate_e6 / 1000000).toFixed(4)}
                          </span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-mono text-xs text-[var(--text-muted)]">{fx.rate_e6}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-xs text-[var(--text-muted)] py-4 text-center">
                {accDict.noRatesRecorded || 'No exchange rates recorded for this currency.'}
              </p>
            )}
          </Card>
        </div>
      ) : null}

      {/* Filter / Search Bar */}
      <Card className="p-4 mb-6">
        <div className="flex items-center gap-3">
          <div className="relative flex-1">
            <div className="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-[var(--text-muted)]">
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              placeholder={accDict.searchCurrencyPlaceholder || 'Search currency by code, name, or symbol...'}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] ps-10 pe-3.5 py-2.5 text-xs text-[var(--text-primary)] focus:ring-2 focus:ring-blue-500/20"
            />
          </div>
        </div>
      </Card>

      {/* Table List */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>{accDict.isoCode || 'Code'}</th>
              <th className={tableClasses.th}>{accDict.name || 'Name'}</th>
              <th className={tableClasses.th}>{accDict.symbol || 'Symbol'}</th>
              <th className={tableClasses.th}>{accDict.minorExponent || 'Minor Exponent'}</th>
              <th className={tableClasses.th}>{accDict.accounts || 'Accounts'}</th>
              <th className={tableClasses.th}>{accDict.fxRates || 'FX Rates'}</th>
              <th className={tableClasses.th} />
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {filteredCurrencies.map((c) => {
              const hasLinkedRecords = (c.accounts_count || 0) > 0 || (c.exchange_rates_count || 0) > 0;
              return (
                <tr key={c.code} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{c.code}</span>
                      <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-mono">
                        ISO 4217
                      </span>
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs font-medium text-[var(--text-primary)]">{getName(c.name)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="inline-flex items-center justify-center size-7 rounded-lg bg-[var(--background)] font-mono font-bold text-xs text-[var(--text-primary)] border border-[var(--border)]">
                      {c.symbol}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-muted)]">{c.exponent}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => (c.accounts_count || 0) > 0 && setSelectedAccountsCurrency(c)}
                      disabled={(c.accounts_count || 0) === 0}
                      title={(c.accounts_count || 0) > 0 ? "Click to view linked accounts" : "No accounts linked"}
                      className="inline-flex items-center gap-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 px-2.5 py-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 font-mono disabled:opacity-40 transition-all cursor-pointer disabled:cursor-default"
                    >
                      <span>{c.accounts_count || 0}</span>
                      <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => (c.exchange_rates_count || 0) > 0 && setSelectedFxRatesCurrency(c)}
                      disabled={(c.exchange_rates_count || 0) === 0}
                      title={(c.exchange_rates_count || 0) > 0 ? "Click to view recorded FX rates" : "No rates recorded"}
                      className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 px-2.5 py-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 font-mono disabled:opacity-40 transition-all cursor-pointer disabled:cursor-default"
                    >
                      <span>{c.exchange_rates_count || 0}</span>
                      <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => startEdit(c)}
                        className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-1 text-[11px] font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors"
                      >
                        {actionsDict.edit || 'Edit'}
                      </button>

                      <button
                        type="button"
                        onClick={() => !hasLinkedRecords && setDeletingCurrency(c)}
                        disabled={hasLinkedRecords}
                        title={hasLinkedRecords ? "Cannot delete currency with linked accounts or FX rates" : "Delete currency"}
                        className="rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-1 text-[11px] font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                      >
                        {actionsDict.delete || 'Delete'}
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
