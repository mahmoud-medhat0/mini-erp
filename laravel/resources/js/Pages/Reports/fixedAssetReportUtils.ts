export type LocalizedName = Record<string, string> | string | null | undefined;

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
};

export type FixedAssetReportAsset = {
  id: string;
  asset_number: string;
  name: LocalizedName;
  currency: string;
  cost_minor: number;
  salvage_value_minor: number;
  opening_accumulated_depreciation_minor: number;
  posted_accumulated_depreciation_minor: number;
  total_accumulated_depreciation_minor: number;
  net_book_value_minor: number;
  useful_life_months: number;
  acquisition_date?: string | null;
  in_service_date?: string | null;
  status: string;
  category?: {
    id: string;
    code: string;
    name: LocalizedName;
  } | null;
};

export function localizedName(name: LocalizedName, locale: string): string {
  if (!name) return '';
  if (typeof name === 'string') return name;
  return name[locale] || name.en || Object.values(name)[0] || '';
}

export function fallbackText(value: string | number | null | undefined, fallback: string): string {
  if (value === null || value === undefined || value === '') return fallback;
  return String(value);
}

export function formatMinor(minor: number | null | undefined, currency = ''): string {
  const amount = Number.isFinite(minor) ? Number(minor) : 0;
  const sign = amount < 0 ? '-' : '';
  const digits = String(Math.abs(Math.trunc(amount))).padStart(3, '0');
  const major = digits.slice(0, -2) || '0';
  const fractional = digits.slice(-2);
  const grouped = major.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  return `${sign}${grouped}.${fractional}${currency ? ` ${currency}` : ''}`;
}

export function fixedAssetStatusLabel(status: string, dict: any): string {
  const keys: Record<string, string> = {
    draft: 'fixedAssetStatusDraft',
    active: 'fixedAssetStatusActive',
    fully_depreciated: 'fixedAssetStatusFullyDepreciated',
    disposed: 'fixedAssetStatusDisposed',
  };

  const key = keys[status];
  return key ? dict.app.fixedAssets[key] : status;
}

export function depreciationStatusLabel(status: string, dict: any): string {
  const keys: Record<string, string> = {
    planned: 'scheduleStatusPlanned',
    posted: 'scheduleStatusPosted',
    reversed: 'scheduleStatusReversed',
    skipped: 'scheduleStatusSkipped',
  };

  const key = keys[status];
  return key ? dict.app.pages.reports[key] : status;
}

export function runStatusLabel(status: string, dict: any): string {
  const keys: Record<string, string> = {
    posted: 'scheduleStatusPosted',
    reversed: 'scheduleStatusReversed',
  };

  const key = keys[status];
  return key ? dict.app.pages.reports[key] : status;
}

export function disposalTypeLabel(type: string, dict: any): string {
  const keys: Record<string, string> = {
    sale: 'disposalTypeSale',
    scrap: 'disposalTypeScrap',
    retirement: 'disposalTypeRetirement',
  };

  const key = keys[type];
  return key ? dict.app.pages.reports[key] : type;
}

export function statusTone(status: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (status === 'posted' || status === 'active') return 'ok';
  if (status === 'reversed' || status === 'disposed') return 'danger';
  if (status === 'planned' || status === 'draft') return 'warning';
  return 'muted';
}
