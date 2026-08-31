import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { PageHeader, SettingsLink } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

const sections = [
  { key: 'company', href: '/settings/company' },
  { key: 'branches', href: '/settings/branches' },
  { key: 'numbering', href: '/settings/numbering' },
  { key: 'branchApprovalRules', href: '/settings/branch-approval-rules' },
  { key: 'users', href: '/settings/users' },
] as const;

export default function SettingsIndex({ locale }: SharedPageProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.title} />
      <PageHeader title={dict.app.settings.title} description={dict.app.settings.description} />
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {sections.map((section) => (
          <SettingsLink
            key={section.href}
            href={section.href}
            title={dict.app.settings.sections[section.key].title}
            description={dict.app.settings.sections[section.key].description}
          />
        ))}
      </div>
    </AppLayout>
  );
}
