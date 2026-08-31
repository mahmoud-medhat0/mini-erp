import { getTranslations } from 'next-intl/server';
import { PageHeader, EmptyState } from '@/ui/primitives';

/**
 * Dashboard shell. Deliberately shows an EMPTY STATE rather than fake KPIs — real
 * figures come from posted accounting data in later phases (no mock numbers).
 */
export default async function DashboardPage() {
  const t = await getTranslations();
  return (
    <div>
      <PageHeader title={t('nav.dashboard')} />
      <EmptyState
        title={t('state.notAvailable')}
        description="KPIs will populate from posted accounting data once the accounting core (Phase 2) is live. No mock figures are shown."
      />
    </div>
  );
}
