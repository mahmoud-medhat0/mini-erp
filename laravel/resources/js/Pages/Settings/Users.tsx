import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import ToggleSwitch from '../../Components/ToggleSwitch';
import { getDictionary } from '../../lib/i18n';
import type { RoleRow, SharedPageProps, UserRow } from '../../Types';

type UsersProps = SharedPageProps & {
  users: UserRow[];
  roles: RoleRow[];
  allPermissions?: string[];
};

function CategoryIcon({ categoryKey, className = 'size-4' }: { categoryKey: string; className?: string }) {
  const iconPaths: Record<string, string> = {
    accounting: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    audit: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    banks: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
    budgeting: 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z',
    cash: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    cheques: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    close_period: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    costCenters: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    customers: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    dashboard: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    equipment: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    expenses: 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    fixedAssets: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1 M14 7h1m-1 4h1m-1 4h1',
    inventory: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    partners: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    payroll: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    projects: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    purchasing: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z',
    recurring: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    rentals: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 0121 9z',
    reports: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    sales: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
    settings: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
    suppliers: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
    taxes: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z',
    users: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    general: 'M13 10V3L4 14h7v7l9-11h-7z',
  };

  const path = iconPaths[categoryKey] || iconPaths.general;

  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
  );
}

function formatPermission(perm: string, dict: ReturnType<typeof getDictionary>): string {
  const exact = (dict.app.permissionsList as Record<string, string>)?.[perm];
  if (exact) return exact;

  const parts = perm.split('.');
  if (parts.length === 2) {
    const categoryLabels = dict.app.permissionCategories as Record<string, string>;
    const actionLabels = dict.app.permissionActions as Record<string, string>;
    const domain = categoryLabels[parts[0]] || categoryLabels.general || parts[0];
    const action = actionLabels[parts[1]] || parts[1].replace(/_/g, ' ');
    return `${domain}: ${action}`;
  }

  return (dict.app.permissionActions as Record<string, string>)[perm] || perm.replace(/_/g, ' ');
}

function getCategoryTitle(key: string, dict: ReturnType<typeof getDictionary>): string {
  const labels = dict.app.permissionCategories as Record<string, string>;

  return labels[key] || labels.general || key;
}

function groupPermissionsByCategory(permissions: string[]) {
  const groups: Record<string, string[]> = {};

  for (const perm of permissions) {
    const parts = perm.split('.');
    const categoryKey = parts.length > 1 ? parts[0] : 'general';
    if (!groups[categoryKey]) {
      groups[categoryKey] = [];
    }
    groups[categoryKey].push(perm);
  }

  return groups;
}

function UserFormModal({
  user,
  roles,
  dict,
  onClose,
}: {
  user?: UserRow;
  roles: RoleRow[];
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const roleOptions = roles.map((r) => ({ value: String(r.id), label: r.name }));
  const languageOptions = [
    { value: 'en', label: dict.common.languageName.en },
    { value: 'ar', label: dict.common.languageName.ar },
  ];

  const initialRoleId = user?.roles[0]?.id ? String(user.roles[0].id) : (roleOptions[0]?.value ?? '');

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    password: '',
    locale: user?.locale ?? 'en',
    role_id: initialRoleId,
    is_active: user?.isActive ?? true,
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (user) {
      patch(`/settings/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
    } else {
      post('/settings/users', {
        preserveScroll: true,
        onSuccess: () => {
          reset();
          onClose();
        },
      });
    }
  }

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
          {user ? `${dict.app.actions.editUser}: ${user.name}` : dict.app.actions.addUser}
        </h3>
        <button
          type="button"
          title={dict.app.actions.close}
          aria-label={dict.app.actions.close}
          onClick={onClose}
          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
        >
          <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          {/* Name */}
          <div className="space-y-1">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.fullName} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder={dict.app.fields.fullNamePlaceholder}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.name ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name}</p> : null}
          </div>

          {/* Email */}
          <div className="space-y-1">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.email} <span className="text-[var(--danger)]">*</span>
            </label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              placeholder={dict.app.fields.emailPlaceholder}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required
            />
            {errors.email ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.email}</p> : null}
          </div>

          {/* Password */}
          <div className="space-y-1">
            <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {dict.app.fields.password} {user ? dict.app.fields.passwordKeep : <span className="text-[var(--danger)]">*</span>}
            </label>
            <input
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              placeholder={user ? dict.app.fields.passwordMaskedPlaceholder : dict.app.fields.passwordPlaceholder}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              required={!user}
              minLength={8}
            />
            {errors.password ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.password}</p> : null}
          </div>

          {/* Language / Locale */}
          <SearchableSelect
            label={dict.app.fields.language}
            options={languageOptions}
            value={data.locale}
            onChange={(val) => setData('locale', val ?? 'en')}
            isSearchable={false}
            isClearable={false}
          />

          {!user ? (
            <SearchableSelect
              label={dict.app.fields.role}
              options={roleOptions}
              value={data.role_id}
              onChange={(val) => setData('role_id', val ?? '')}
              isSearchable={true}
              isClearable={true}
            />
          ) : null}

          {/* Active Status Toggle */}
          <div className="pt-5 sm:col-span-2">
            <ToggleSwitch
              checked={data.is_active}
              onChange={(val) => setData('is_active', val)}
              label={dict.app.fields.activeAccount}
              description={dict.app.fields.activeAccountDesc}
            />
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border)]">
          <button
            type="button"
            title={dict.app.actions.cancel}
            aria-label={dict.app.actions.cancel}
            onClick={onClose}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
          >
            {dict.app.actions.cancel}
          </button>
          <button
            type="submit"
            title={user ? dict.app.actions.save : dict.app.actions.create}
            aria-label={user ? dict.app.actions.save : dict.app.actions.create}
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
          >
            {processing ? (
              <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            ) : null}
            <span>{user ? dict.app.actions.save : dict.app.actions.create}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

function AssignRoleFormModal({
  users,
  roles,
  dict,
  onClose,
}: {
  users: UserRow[];
  roles: RoleRow[];
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const userOptions = users.map((user) => ({
    value: String(user.id),
    label: `${user.name} (${user.email})`,
  }));

  const roleOptions = roles.map((role) => ({
    value: String(role.id),
    label: role.name,
  }));

  const { data, setData, post, processing, reset } = useForm({
    user_id: userOptions[0]?.value ?? '',
    role_id: roleOptions[0]?.value ?? '',
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/settings/users/roles', {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  }

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
          {dict.app.actions.assign} {dict.app.fields.roles}
        </h3>
        <button
          type="button"
          title={dict.app.actions.close}
          aria-label={dict.app.actions.close}
          onClick={onClose}
          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
        >
          <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <SearchableSelect
            label={dict.app.fields.user}
            options={userOptions}
            value={data.user_id}
            onChange={(val) => setData('user_id', val ?? '')}
            isSearchable={true}
            isClearable={false}
          />
          <SearchableSelect
            label={dict.app.fields.roles}
            options={roleOptions}
            value={data.role_id}
            onChange={(val) => setData('role_id', val ?? '')}
            isSearchable={true}
            isClearable={false}
          />
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border)]">
          <button
            type="button"
            title={dict.app.actions.cancel}
            aria-label={dict.app.actions.cancel}
            onClick={onClose}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
          >
            {dict.app.actions.cancel}
          </button>
          <button
            type="submit"
            title={dict.app.actions.assign}
            aria-label={dict.app.actions.assign}
            disabled={processing || users.length === 0 || roles.length === 0}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
          >
            {processing ? (
              <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            ) : null}
            <span>{dict.app.actions.assign}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

function RoleFormModal({
  role,
  allPermissions,
  dict,
  onClose,
}: {
  role?: RoleRow;
  allPermissions: string[];
  dict: ReturnType<typeof getDictionary>;
  onClose: () => void;
}) {
  const [searchTerm, setSearchTerm] = useState('');

  const { data, setData, post, patch, processing, errors, reset } = useForm<{
    name: string;
    permissions: string[];
  }>({
    name: role?.name ?? '',
    permissions: role?.permissions ?? [],
  });

  // Group permissions by category domain
  const groupedPermissions = groupPermissionsByCategory(allPermissions);
  const categoriesList = Object.keys(groupedPermissions);
  const [activeCategory, setActiveCategory] = useState<string>(categoriesList[0] || 'accounting');

  function togglePermission(perm: string) {
    if (data.permissions.includes(perm)) {
      setData('permissions', data.permissions.filter((p) => p !== perm));
    } else {
      setData('permissions', [...data.permissions, perm]);
    }
  }

  function toggleCategoryAll(catPerms: string[]) {
    const allSelected = catPerms.every((p) => data.permissions.includes(p));
    if (allSelected) {
      setData('permissions', data.permissions.filter((p) => !catPerms.includes(p)));
    } else {
      const newPerms = new Set([...data.permissions, ...catPerms]);
      setData('permissions', Array.from(newPerms));
    }
  }

  function toggleSelectAll() {
    if (data.permissions.length === allPermissions.length) {
      setData('permissions', []);
    } else {
      setData('permissions', [...allPermissions]);
    }
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (role) {
      patch(`/settings/roles/${role.id}`, {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
    } else {
      post('/settings/roles', {
        preserveScroll: true,
        onSuccess: () => {
          reset();
          onClose();
        },
      });
    }
  }

  // Calculate current active perms or searched perms
  const activeCatPerms = groupedPermissions[activeCategory] || [];
  const searchedPerms = searchTerm.trim()
    ? allPermissions.filter(
        (p) =>
          p.toLowerCase().includes(searchTerm.toLowerCase()) ||
          formatPermission(p, dict).toLowerCase().includes(searchTerm.toLowerCase())
      )
    : activeCatPerms;

  const activeCatSelectedCount = activeCatPerms.filter((p) => data.permissions.includes(p)).length;
  const isCurrentGroupAllSelected = activeCatPerms.length > 0 && activeCatSelectedCount === activeCatPerms.length;

  return (
    <Card className="mb-6 border-blue-500/20 bg-[var(--surface)] p-6 shadow-xl">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-5">
        <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
          {role ? `${dict.app.actions.editRole}: ${role.name}` : dict.app.actions.addRole}
        </h3>
        <button
          type="button"
          title={dict.app.actions.close}
          aria-label={dict.app.actions.close}
          onClick={onClose}
          className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
        >
          <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form onSubmit={submit} className="space-y-6">
        {/* Role Name Input */}
        <div className="space-y-1.5 max-w-md">
          <label className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
            {dict.app.fields.roleName} <span className="text-[var(--danger)]">*</span>
          </label>
          <input
            type="text"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            placeholder={dict.app.fields.roleNamePlaceholder}
            className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
            required
          />
          {errors.name ? <p className="m-0 text-xs font-semibold text-[var(--danger)]">{errors.name}</p> : null}
        </div>

        {/* Permissions Matrix Header */}
        <div className="border-t border-[var(--border)] pt-5 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h4 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                {dict.app.fields.permissions} ({data.permissions.length} / {allPermissions.length} {dict.app.fields.selected})
              </h4>
              <p className="m-0 text-xs text-[var(--text-muted)]">{dict.app.fields.configureAccessScopes}</p>
            </div>

            <div className="flex items-center gap-3">
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder={dict.app.fields.searchPermissionsPlaceholder}
                className="w-56 sm:w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              />
              <button
                type="button"
                title={data.permissions.length === allPermissions.length ? dict.app.actions.clearAll : dict.app.actions.selectAll}
                aria-label={data.permissions.length === allPermissions.length ? dict.app.actions.clearAll : dict.app.actions.selectAll}
                onClick={toggleSelectAll}
                className="rounded-xl border border-blue-500/20 bg-blue-500/10 px-3.5 py-2 text-xs font-extrabold text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-colors"
              >
                {data.permissions.length === allPermissions.length ? dict.app.actions.clearAll : dict.app.actions.selectAll}
              </button>
            </div>
          </div>

          {/* Two-Column Category Sidebar & Permissions Grid */}
          <div className="grid gap-4 lg:grid-cols-[260px_1fr] rounded-2xl border border-[var(--border)] bg-[var(--background)] p-3">
            {/* Left Category Sidebar with SVG Icons */}
            <div className="space-y-1 max-h-96 overflow-y-auto border-b pb-3 lg:border-b-0 lg:border-e lg:pb-0 lg:pe-3 border-[var(--border)]">
              <span className="block px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {dict.app.fields.modulesAndDomains}
              </span>

              {categoriesList.map((catKey) => {
                const catTitle = getCategoryTitle(catKey, dict);
                const catPerms = groupedPermissions[catKey] || [];
                const selectedCount = catPerms.filter((p) => data.permissions.includes(p)).length;
                const isSelected = activeCategory === catKey && !searchTerm.trim();

                return (
                  <button
                    key={catKey}
                    type="button"
                    title={catTitle}
                    aria-label={catTitle}
                    onClick={() => {
                      setSearchTerm('');
                      setActiveCategory(catKey);
                    }}
                    className={`w-full flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all ${
                      isSelected
                        ? 'bg-[var(--primary)] text-white shadow-xs'
                        : selectedCount > 0
                          ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20'
                          : 'text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <div className="flex items-center gap-2.5 min-w-0">
                      <CategoryIcon
                        categoryKey={catKey}
                        className={`size-4 shrink-0 transition-colors ${
                          isSelected ? 'text-white' : selectedCount > 0 ? 'text-blue-500' : 'text-[var(--text-muted)]'
                        }`}
                      />
                      <span className="truncate">{catTitle}</span>
                    </div>

                    <span
                      className={`rounded-md px-1.5 py-0.5 text-[10px] font-mono shrink-0 ${
                        isSelected
                          ? 'bg-white/20 text-white'
                          : selectedCount > 0
                            ? 'bg-blue-500/20 text-blue-600 dark:text-blue-400'
                            : 'bg-[var(--surface)] text-[var(--text-muted)]'
                      }`}
                    >
                      {selectedCount}/{catPerms.length}
                    </span>
                  </button>
                );
              })}
            </div>

            {/* Right Permissions Grid */}
            <div className="space-y-3 p-1">
              {!searchTerm.trim() ? (
                <div className="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                  <div className="flex items-center gap-2.5">
                    <CategoryIcon categoryKey={activeCategory} className="size-5 text-blue-500" />
                    <span className="font-bold text-sm text-[var(--text-primary)]">
                      {getCategoryTitle(activeCategory, dict)}
                    </span>
                    <span className="rounded-full bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                      {activeCatSelectedCount} / {activeCatPerms.length} {dict.app.fields.selected}
                    </span>
                  </div>

                  <button
                    type="button"
                    title={isCurrentGroupAllSelected ? dict.app.actions.deselectModule : dict.app.actions.selectAllInModule}
                    aria-label={isCurrentGroupAllSelected ? dict.app.actions.deselectModule : dict.app.actions.selectAllInModule}
                    onClick={() => toggleCategoryAll(activeCatPerms)}
                    className="text-xs font-extrabold text-blue-600 dark:text-blue-400 hover:underline"
                  >
                    {isCurrentGroupAllSelected ? dict.app.actions.deselectModule : dict.app.actions.selectAllInModule}
                  </button>
                </div>
              ) : (
                <div className="border-b border-[var(--border)] pb-2 text-xs font-bold text-[var(--text-secondary)]">
                  {dict.app.fields.permissionSearchResults.replace('{count}', String(searchedPerms.length))}
                </div>
              )}

              <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 max-h-80 overflow-y-auto pe-1">
                {searchedPerms.map((perm) => {
                  const isChecked = data.permissions.includes(perm);
                  const permLabel = formatPermission(perm, dict);

                  return (
                    <label
                      key={perm}
                      className={`flex items-center gap-2.5 p-2.5 rounded-xl border text-xs font-semibold cursor-pointer transition-all ${
                        isChecked
                          ? 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold shadow-xs'
                          : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] hover:border-[var(--primary)]'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={isChecked}
                        onChange={() => togglePermission(perm)}
                        className="size-4 shrink-0 rounded-md border-[var(--border)] text-blue-600 focus:ring-blue-500"
                      />
                      <div className="flex flex-col min-w-0">
                        <span className="font-bold text-[var(--text-primary)] text-xs truncate">{permLabel}</span>
                        <span className="font-mono text-[10px] text-[var(--text-muted)] truncate">{perm}</span>
                      </div>
                    </label>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Submit Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border)]">
          <button
            type="button"
            title={dict.app.actions.cancel}
            aria-label={dict.app.actions.cancel}
            onClick={onClose}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
          >
            {dict.app.actions.cancel}
          </button>
          <button
            type="submit"
            title={role ? dict.app.actions.save : dict.app.actions.create}
            aria-label={role ? dict.app.actions.save : dict.app.actions.create}
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
          >
            {processing ? (
              <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            ) : null}
            <span>{role ? dict.app.actions.save : dict.app.actions.create}</span>
          </button>
        </div>
      </form>
    </Card>
  );
}

function RoleCard({
  role,
  dict,
  onEdit,
}: {
  role: RoleRow;
  dict: ReturnType<typeof getDictionary>;
  onEdit: () => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const maxInitial = 10;
  const visiblePermissions = expanded ? role.permissions : role.permissions.slice(0, maxInitial);
  const remainingCount = role.permissions.length - maxInitial;

  return (
    <Card className="p-5 flex flex-col justify-between space-y-4 hover:border-blue-500/30 transition-all">
      <div className="space-y-3">
        <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
          <div className="flex items-center gap-2">
            <span className="font-bold text-base text-[var(--text-primary)]">{role.name}</span>
            {role.isTemplate ? <StatusBadge tone="ok">{dict.app.status.template}</StatusBadge> : null}
          </div>

          {/* Action buttons (Edit & Delete) */}
          <div className="flex items-center gap-1.5">
            <button
              type="button"
              onClick={onEdit}
              className="rounded-lg border border-[var(--border)] bg-[var(--background)] p-1.5 text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)] transition-colors"
              title={dict.app.actions.editRole}
              aria-label={dict.app.actions.editRole}
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>

            {!role.isTemplate ? <DeleteRoleButton roleId={role.id} roleName={role.name} dict={dict} /> : null}
          </div>
        </div>

        <div className="flex items-center justify-between text-xs text-[var(--text-muted)] font-semibold">
          <span>{role.permissions.length} {dict.app.fields.permissions}</span>
        </div>

        <div className="flex flex-wrap gap-1.5">
          {visiblePermissions.map((perm) => (
            <span
              key={perm}
              title={perm}
              className="inline-flex items-center gap-1.5 text-[11px] font-bold text-[var(--text-secondary)] bg-[var(--background)] border border-[var(--border)] px-2.5 py-1 rounded-lg"
            >
              <span className="size-1.5 rounded-full bg-blue-500/60" />
              <span>{formatPermission(perm, dict)}</span>
            </span>
          ))}

          {role.permissions.length > maxInitial ? (
            <button
              type="button"
              title={expanded ? dict.app.actions.showLess : `+ ${remainingCount} ${dict.app.actions.showMore}`}
              aria-label={expanded ? dict.app.actions.showLess : `+ ${remainingCount} ${dict.app.actions.showMore}`}
              onClick={() => setExpanded(!expanded)}
              className="inline-flex items-center gap-1 text-[11px] font-extrabold text-blue-600 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg hover:bg-blue-500/20 transition-colors"
            >
              <span>{expanded ? dict.app.actions.showLess : `+ ${remainingCount} ${dict.app.actions.showMore}`}</span>
            </button>
          ) : null}
        </div>
      </div>
    </Card>
  );
}

function RevokeRoleButton({
  userId,
  roleId,
  roleName,
  dict,
}: {
  userId: number | string;
  roleId: number | string;
  roleName: string;
  dict: ReturnType<typeof getDictionary>;
}) {
  const { delete: destroy, processing } = useForm({ user_id: userId, role_id: roleId });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    destroy('/settings/users/roles', { preserveScroll: true });
  }

  return (
    <form onSubmit={submit} className="inline-flex">
      <span className="inline-flex items-center gap-1.5 rounded-lg border border-blue-500/20 bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400">
        <span>{roleName}</span>
        <button
          type="submit"
          disabled={processing}
          title={dict.app.actions.revoke}
          aria-label={dict.app.actions.revoke}
          className="rounded p-0.5 text-blue-500 hover:bg-blue-500/20 hover:text-red-500 transition-colors disabled:opacity-50"
        >
          <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </span>
    </form>
  );
}

function DeleteRoleButton({ roleId, roleName, dict }: { roleId: number | string; roleName: string; dict: ReturnType<typeof getDictionary> }) {
  const { delete: destroy, processing } = useForm({});

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const msg = dict.app.messages.confirmDeleteRole.replace('{name}', roleName);
    if (confirm(msg)) {
      destroy(`/settings/roles/${roleId}`, { preserveScroll: true });
    }
  }

  return (
    <form onSubmit={submit} className="inline-flex">
      <button
        type="submit"
        disabled={processing}
        title={dict.app.actions.deleteRole}
        aria-label={dict.app.actions.deleteRole}
        className="inline-flex items-center gap-1 rounded-lg border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-bold text-red-600 hover:bg-red-500/20 transition-colors disabled:opacity-50"
      >
        <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </button>
    </form>
  );
}

function DeleteUserButton({
  userId,
  userName,
  currentUserId,
  dict,
}: {
  userId: number | string;
  userName: string;
  currentUserId?: number | string;
  dict: ReturnType<typeof getDictionary>;
}) {
  const { delete: destroy, processing } = useForm({});
  const isSelf = String(userId) === String(currentUserId);
  const [selfDeleteMessage, setSelfDeleteMessage] = useState<string | null>(isSelf ? dict.app.messages.cannotDeleteSelf : null);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (isSelf) {
      setSelfDeleteMessage(dict.app.messages.cannotDeleteSelf);
      return;
    }
    setSelfDeleteMessage(null);
    const msg = dict.app.messages.confirmDeleteUser.replace('{name}', userName);
    if (confirm(msg)) {
      destroy(`/settings/users/${userId}`, { preserveScroll: true });
    }
  }

  return (
    <div className="inline-flex flex-col items-end gap-1">
      <form onSubmit={submit} className="inline-flex">
        <button
          type="submit"
          disabled={processing || isSelf}
          title={isSelf ? dict.app.messages.cannotDeleteSelf : dict.app.actions.deleteUser}
          aria-label={isSelf ? dict.app.messages.cannotDeleteSelf : dict.app.actions.deleteUser}
          className="inline-flex items-center gap-1 rounded-lg border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-bold text-red-600 hover:bg-red-500/20 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </form>
      {selfDeleteMessage ? (
        <span className="max-w-36 text-end text-[10px] font-semibold text-amber-600 dark:text-amber-300" role="status">
          {selfDeleteMessage}
        </span>
      ) : null}
    </div>
  );
}

export default function Users({ users, roles, allPermissions = [], auth, locale }: UsersProps) {
  const dict = getDictionary(locale);
  const [activeTab, setActiveTab] = useState<'users' | 'roles'>('users');
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [showAddUserForm, setShowAddUserForm] = useState(false);
  const [editingUser, setEditingUser] = useState<UserRow | null>(null);
  const [showAddRoleForm, setShowAddRoleForm] = useState(false);
  const [editingRole, setEditingRole] = useState<RoleRow | null>(null);

  // Default permissions fallback if empty from DB
  const defaultPermissions = allPermissions.length > 0 ? allPermissions : [
    'settings.configure',
    'users.configure',
    'diagnostics.view',
    'company.manage',
    'branch.manage',
    'numbering.manage',
    'reports.view',
  ];

  return (
    <AppLayout active="settings.users">
      <Head title={dict.app.settings.sections.users.title} />

      <PageHeader
        title={dict.app.settings.sections.users.title}
        description={dict.app.settings.users.description}
        actions={
          activeTab === 'users' ? (
            <div className="flex items-center gap-3">
              <button
                type="button"
                title={dict.app.actions.addUser}
                aria-label={dict.app.actions.addUser}
                onClick={() => {
                  setEditingUser(null);
                  setShowAssignForm(false);
                  setShowAddUserForm(!showAddUserForm);
                }}
                className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>{dict.app.actions.addUser}</span>
              </button>

              <button
                type="button"
                title={`${dict.app.actions.assign} ${dict.app.fields.roles}`}
                aria-label={`${dict.app.actions.assign} ${dict.app.fields.roles}`}
                onClick={() => {
                  setShowAddUserForm(false);
                  setEditingUser(null);
                  setShowAssignForm(!showAssignForm);
                }}
                className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors"
              >
                <svg className="size-4 text-[var(--primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>{dict.app.actions.assign} {dict.app.fields.roles}</span>
              </button>
            </div>
          ) : (
            <button
              type="button"
              title={dict.app.actions.addRole}
              aria-label={dict.app.actions.addRole}
              onClick={() => {
                setEditingRole(null);
                setShowAddRoleForm(!showAddRoleForm);
              }}
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.actions.addRole}</span>
            </button>
          )
        }
      />

      {/* Tabs Navigation Bar */}
      <div className="flex border-b border-[var(--border)] mb-6 gap-2">
        <button
          type="button"
          title={dict.app.fields.user}
          aria-label={dict.app.fields.user}
          onClick={() => setActiveTab('users')}
          className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-all ${
            activeTab === 'users'
              ? 'border-[var(--primary)] text-[var(--primary)]'
              : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <span>{dict.app.fields.user} ({users.length})</span>
        </button>

        <button
          type="button"
          title={dict.app.fields.roles}
          aria-label={dict.app.fields.roles}
          onClick={() => setActiveTab('roles')}
          className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-all ${
            activeTab === 'roles'
              ? 'border-[var(--primary)] text-[var(--primary)]'
              : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          <span>{dict.app.fields.roles} ({roles.length})</span>
        </button>
      </div>

      {/* TAB 1: USERS (CRUD) */}
      {activeTab === 'users' ? (
        <div className="space-y-6">
          {/* Add / Edit User Modal */}
          {showAddUserForm || editingUser ? (
            <UserFormModal
              user={editingUser ?? undefined}
              roles={roles}
              dict={dict}
              onClose={() => {
                setShowAddUserForm(false);
                setEditingUser(null);
              }}
            />
          ) : null}

          {/* Assign Role Modal */}
          {showAssignForm ? (
            <AssignRoleFormModal
              users={users}
              roles={roles}
              dict={dict}
              onClose={() => setShowAssignForm(false)}
            />
          ) : null}

          {users.length === 0 ? (
            <EmptyState title={dict.app.settings.users.emptyUsers} />
          ) : (
            <div className={tableClasses.wrap}>
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{dict.app.fields.user}</th>
                    <th className={tableClasses.th}>{dict.app.fields.roles}</th>
                    <th className={tableClasses.th}>{dict.app.fields.status}</th>
                    <th className={tableClasses.th} />
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border)]">
                  {users.map((user) => {
                    const initials = user.name
                      ? user.name
                          .split(' ')
                          .map((n) => n[0])
                          .slice(0, 2)
                          .join('')
                          .toUpperCase()
                      : 'U';

                    return (
                      <tr key={user.id} className="group hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 font-extrabold text-white text-xs shadow-md shadow-blue-500/20">
                              {initials}
                            </div>
                            <div className="flex flex-col min-w-40">
                              <span className="font-bold text-[var(--text-primary)] text-sm">{user.name}</span>
                              <span className="font-mono text-xs text-[var(--text-muted)] mt-0.5">{user.email}</span>
                            </div>
                          </div>
                        </td>
                        <td className={tableClasses.td}>
                          {user.roles.length === 0 ? (
                            <span className="text-xs text-[var(--text-muted)] italic">{dict.app.state.none}</span>
                          ) : (
                            <div className="flex flex-wrap gap-2">
                              {user.roles.map((role) => (
                                <RevokeRoleButton key={role.id} userId={user.id} roleId={role.id} roleName={role.name} dict={dict} />
                              ))}
                            </div>
                          )}
                        </td>
                        <td className={tableClasses.td}>
                          <StatusBadge tone={user.isActive ? 'ok' : 'danger'}>
                            {user.isActive ? dict.app.status.active : dict.app.status.inactive}
                          </StatusBadge>
                        </td>
                        <td className={tableClasses.td}>
                          <div className="flex items-center justify-end gap-1.5">
                            <button
                              type="button"
                              aria-label={dict.app.actions.editUser}
                              onClick={() => {
                                setShowAssignForm(false);
                                setShowAddUserForm(false);
                                setEditingUser(user);
                              }}
                              className="rounded-lg border border-[var(--border)] bg-[var(--background)] p-1.5 text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)] transition-colors"
                              title={dict.app.actions.editUser}
                            >
                              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                            </button>

                            <DeleteUserButton userId={user.id} userName={user.name} currentUserId={auth?.user?.id} dict={dict} />
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ) : null}

      {/* TAB 2: ROLES & PERMISSIONS (CRUD) */}
      {activeTab === 'roles' ? (
        <div className="space-y-6">
          {/* Add / Edit Role Modal */}
          {showAddRoleForm || editingRole ? (
            <RoleFormModal
              role={editingRole ?? undefined}
              allPermissions={defaultPermissions}
              dict={dict}
              onClose={() => {
                setShowAddRoleForm(false);
                setEditingRole(null);
              }}
            />
          ) : null}

          {roles.length === 0 ? (
            <EmptyState title={dict.app.settings.users.emptyRoles} />
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {roles.map((role) => (
                <RoleCard
                  key={role.id}
                  role={role}
                  dict={dict}
                  onEdit={() => {
                    setShowAddRoleForm(false);
                    setEditingRole(role);
                  }}
                />
              ))}
            </div>
          )}
        </div>
      ) : null}
    </AppLayout>
  );
}
