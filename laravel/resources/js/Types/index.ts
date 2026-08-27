export * from './page';

// --- Attachment & Notification Types ---
export type AttachmentItem = {
  id: string;
  entity_type: string;
  entity_id: string;
  file_ref: string;
  name: string;
  mime: string;
  size: number;
  uploaded_by?: number | string | null;
  at: string;
};

export type NotificationRow = {
  id: string;
  type: string;
  target_ref?: string;
  targetRef?: string;
  read: boolean;
  at: string;
};

export type PaginationLink = {
  url: string | null;
  label: string;
  active: boolean;
};

// --- User & Role Management Types ---
export type UserRow = {
  id: number | string;
  name: string;
  email: string;
  locale?: string;
  theme?: string;
  is_active?: boolean;
  isActive?: boolean;
  roles: { id: number | string; name: string }[];
  created_at?: string;
  createdAt?: string;
};

export type UserOption = {
  id: number | string;
  name: string;
  email: string;
};

export type RoleRow = {
  id: number | string;
  name: string;
  is_template?: boolean;
  isTemplate?: boolean;
  permissions_count?: number;
  permissionsCount?: number;
  users_count?: number;
  usersCount?: number;
  permissions: string[];
};

// --- Settings & Foundation Models ---
export type CompanyRow = {
  id: string;
  name?: string;
  name_en?: string;
  name_ar?: string;
  nameEn?: string;
  nameAr?: string;
  base_currency?: string;
  baseCurrency?: string;
  lock_version?: number;
  lockVersion?: number;
  created_at?: string | null;
  createdAt?: string | null;
};

export type CompanyFormData = {
  name_en: string;
  name_ar: string;
  base_currency: string;
  lock_version?: number;
};

export type BranchRow = {
  id: string;
  code: string;
  name?: string;
  name_en?: string;
  name_ar?: string;
  nameEn?: string;
  nameAr?: string;
  is_active?: boolean;
  isActive?: boolean;
  lock_version?: number;
  lockVersion?: number;
};

export type BranchFormData = {
  code: string;
  name_en: string;
  name_ar: string;
  is_active: boolean;
  lock_version: number;
};

export type SequenceRow = {
  id: string;
  key: string;
  docType: string;
  doc_type?: string;
  prefix: string;
  next_value?: number;
  nextValue?: number;
  padding: number;
  include_year?: boolean;
  includeYear?: boolean;
  resetPolicy?: string;
  reset_policy?: string;
  preview: string;
  version?: number;
};

export type NumberingFormData = {
  key: string;
  doc_type?: string;
  prefix: string;
  next_value?: number;
  padding: number;
  include_year: boolean;
  reset_policy?: string;
  version?: number;
};

// --- Currency & FX Models ---
export type CurrencyRow = {
  code: string;
  name_en?: string;
  name_ar?: string;
  name?: Record<string, string> | string;
  symbol: string;
  decimal_places?: number;
  is_active?: boolean;
  is_base?: boolean;
};

export type CurrencyOption = {
  code: string;
  symbol: string;
  name: Record<string, string> | string;
};

export type FxRateItem = {
  id?: string;
  currency_code?: string;
  currency: string;
  rate?: number;
  rate_e6: number;
  effective_date?: string;
  date: string;
  source?: string;
  version?: number;
  currency_ref?: CurrencyRow | null;
};

export type FxRateRow = FxRateItem;

export type CurrencyItem = {
  code: string;
  name?: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  symbol: string;
  decimal_places?: number;
  exponent?: number;
  is_active?: boolean;
  is_base?: boolean;
  accounts_count?: number;
  journal_entries_count?: number;
  exchange_rates_count?: number;
  fx_rates_count?: number;
  accounts?: AccountItem[];
  exchange_rates?: FxRateItem[];
};

// --- Account & COA Models ---
export type AccountItem = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  type: string;
  nature: string;
  currency_code?: string;
  is_postable?: boolean;
};

export type AccountOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  currency_code?: string;
  nature?: string;
  is_control?: boolean;
};

export type AccountTypeSubItem = {
  id: string;
  code: string;
  name?: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  normal_balance?: 'debit' | 'credit' | string;
  statement_type?: 'balance_sheet' | 'income_statement' | string;
  is_contra?: boolean;
  is_active?: boolean;
};

export type AccountCategoryItem = {
  id: string;
  code: string;
  name?: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  nature?: 'debit' | 'credit' | string;
  normal_balance: 'debit' | 'credit';
  statement_type: 'balance_sheet' | 'income_statement';
  is_contra: boolean;
  sort_order?: number;
  is_system?: boolean;
  is_active?: boolean;
  account_types_count?: number;
  types?: AccountTypeSubItem[];
  account_types?: AccountTypeSubItem[];
};

export type AccountCategoryRow = AccountCategoryItem;

export type AccountGroupSubItem = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  type: string;
  statement_section: string;
};

export type AccountSubItem = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  type: string;
  nature: string;
  is_control: boolean;
  currency: string;
};

export type AccountTypeRow = {
  id: string;
  category_id?: number;
  code: string;
  name_en?: string;
  name_ar?: string;
  category_code?: string;
  category_name_en?: string;
  category_name_ar?: string;
  nature?: 'debit' | 'credit' | string;
  accounts_count?: number;
};

export type AccountTypeItem = {
  id: string;
  category_id?: number;
  account_category_id?: string | null;
  code: string;
  name: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  normal_balance: 'debit' | 'credit';
  statement_type?: 'balance_sheet' | 'income_statement';
  category: string;
  category_code?: string;
  category_name_en?: string;
  category_name_ar?: string;
  nature?: 'debit' | 'credit' | string;
  is_contra: boolean;
  sort_order?: number;
  is_system?: boolean;
  is_active: boolean;
  accountCategory?: AccountCategoryItem | null;
  groups_count?: number;
  accounts_count?: number;
  groups?: AccountGroupSubItem[];
  accounts?: AccountSubItem[];
};

export type AccountRow = {
  id: string;
  code: string;
  name?: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  type_id?: number;
  type_code?: string;
  type_name_en?: string;
  type_name_ar?: string;
  account_type_id?: string | null;
  accountType?: AccountTypeItem | null;
  type: string;
  nature: string;
  account_group_id?: string | null;
  currency_code?: string;
  currency: string;
  is_control?: boolean;
  allow_manual_posting?: boolean;
  is_postable?: boolean;
  is_active?: boolean;
  group?: AccountGroupRow | null;
  opening_debit?: number;
  opening_credit?: number;
};

export type AccountGroupRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  name_en?: string;
  name_ar?: string;
  nature?: string;
  account_type_id?: string | null;
  accountType?: AccountTypeItem | null;
  type: string;
  statement_section?: string | null;
  parent_id?: string | null;
  sort_order: number;
  is_active: boolean;
  types?: {
    id: number;
    code: string;
    name_en: string;
    name_ar: string;
    accounts: AccountRow[];
  }[];
  accounts?: AccountRow[];
  children?: AccountGroupRow[];
};

// --- Period & Accounting Posting Models ---
export type PeriodRow = {
  id: string;
  year?: number;
  period_number?: number;
  month: number;
  start_date: string;
  end_date: string;
  is_closed?: boolean;
  status: string;
  closed_at?: string | null;
  closed_by?: number | string | null;
};

export type PeriodOption = {
  id: string;
  period_number?: number;
  month: number;
  year?: number;
  start_date: string;
  end_date: string;
  is_closed?: boolean;
  fiscal_year?: {
    year: number;
  } | null;
};

export type FiscalYearRow = {
  id?: string;
  year: number;
  is_closed?: boolean;
  status: string;
  total_periods?: number;
  closed_periods?: number;
  start_date: string;
  end_date: string;
  periods?: PeriodRow[];
};

export type OpeningBalanceRow = {
  id?: string;
  account_id?: string;
  currency_code?: string;
  debit?: number;
  credit?: number;
  debit_minor: number;
  credit_minor: number;
  fx_rate?: number;
  debit_base?: number;
  credit_base?: number;
  status: string;
  posted_at?: string | null;
};

export type JournalLineRow = {
  id?: string;
  line_no?: number;
  account_id?: string;
  branch_id?: string | null;
  account_code?: string;
  account_name_en?: string;
  account_name_ar?: string;
  account?: any;
  branch?: BranchRow | null;
  currency_code?: string;
  debit?: number;
  credit?: number;
  debit_minor: number;
  credit_minor: number;
  fx_rate?: number;
  debit_base?: number;
  credit_base?: number;
  memo?: string | null;
};

export type JournalRow = {
  id: string;
  entry_number?: string;
  number?: string | null;
  entry_date: string;
  period_id?: string;
  status: 'draft' | 'posted' | 'reversed' | string;
  description?: string | null;
  reference?: string | null;
  currency?: string;
  period?: any;
  createdBy?: any;
  posted_at?: string | null;
  posted_by?: number | string | null;
  lines?: JournalLineRow[];
};

export type LedgerRow = {
  id: string;
  journal_id?: string;
  branch_id?: string | null;
  entry_number?: string;
  entry_date: string;
  account_id?: string;
  account_code?: string;
  account_name_en?: string;
  account_name_ar?: string;
  currency_code?: string;
  currency?: string;
  debit?: number;
  credit?: number;
  debit_minor: number;
  credit_minor: number;
  fx_rate?: number;
  debit_base?: number;
  credit_base?: number;
  memo?: string | null;
  account?: any;
  branch?: BranchRow | null;
  journalEntry?: any;
};

export type TbRow = {
  account_id: string;
  account_code: string;
  account_name?: Record<string, string> | string;
  account_name_en?: string;
  account_name_ar?: string;
  currency_code?: string;
  type: string;
  nature: string;
  total_debit?: number;
  total_credit?: number;
  debit_balance: number;
  credit_balance: number;
  opening_debit_base?: number;
  opening_credit_base?: number;
  period_debit_base?: number;
  period_credit_base?: number;
  ending_debit_base?: number;
  ending_credit_base?: number;
};

// --- Audit Log Types ---
export type AuditLogRow = {
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

export type PaginatedAuditLogs = {
  data: AuditLogRow[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};
