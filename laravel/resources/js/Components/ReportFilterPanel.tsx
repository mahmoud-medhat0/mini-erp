import type { ReactNode } from 'react';

import { Card } from './Primitives';

type ReportFilterPanelProps = {
  children: ReactNode;
  actions: ReactNode;
  activeFilterCount: number;
  activeFilterLabel: string;
};

export default function ReportFilterPanel({
  children,
  actions,
  activeFilterCount,
  activeFilterLabel,
}: ReportFilterPanelProps) {
  return (
    <Card className="p-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-6 xl:items-end">
        {children}
      </div>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--border)] pt-4">
        <div aria-live="polite" className="flex min-h-9 items-center gap-2 text-xs font-semibold text-[var(--text-secondary)]">
          <span className="inline-flex min-w-7 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--background)] px-2 py-1 font-mono text-[var(--text-primary)]">
            {activeFilterCount}
          </span>
          <span>{activeFilterLabel}</span>
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          {actions}
        </div>
      </div>
    </Card>
  );
}
