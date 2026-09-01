import type { PaginationLink } from '../Types';
import { PaginationControls } from './Primitives';

type PaginatorPayload = {
  current_page: number;
  last_page: number;
  links: PaginationLink[];
  total?: number;
};

type LocatedPaginator = {
  key: string;
  paginator: PaginatorPayload;
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isPaginator(value: unknown): value is PaginatorPayload {
  if (!isRecord(value)) return false;

  return Number.isInteger(value.current_page)
    && Number.isInteger(value.last_page)
    && Array.isArray(value.links)
    && value.links.every((link) => isRecord(link)
      && (typeof link.url === 'string' || link.url === null)
      && typeof link.label === 'string'
      && typeof link.active === 'boolean');
}

function findPaginators(value: Record<string, unknown>): LocatedPaginator[] {
  return Object.entries(value)
    .filter((entry): entry is [string, PaginatorPayload] => isPaginator(entry[1]))
    .filter(([, paginator]) => paginator.last_page > 1)
    .map(([key, paginator]) => ({ key, paginator }));
}

export default function UniversalPagination({
  locale,
  mode,
  pageProps,
}: {
  locale: string;
  mode: 'auto' | 'manual' | 'none';
  pageProps: Record<string, unknown>;
}) {
  if (mode !== 'auto') return null;

  const paginators = findPaginators(pageProps);
  if (paginators.length !== 1) return null;

  const totalLabel = locale === 'ar' ? 'إجمالي السجلات:' : 'Total records:';

  return (
    <div data-universal-pagination className="mt-6 space-y-3 print:hidden">
      {paginators.map(({ key, paginator }) => (
        <PaginationControls
          key={key}
          links={paginator.links}
          total={paginator.total}
          totalLabel={totalLabel}
          className="shadow-sm"
        />
      ))}
    </div>
  );
}
