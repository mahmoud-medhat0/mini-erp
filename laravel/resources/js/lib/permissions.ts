import { usePage } from '@inertiajs/react';

export function usePermissions(): string[] {
  const { props } = usePage<{ auth: { permissions: string[] } }>();
  return props.auth?.permissions ?? [];
}

export function useCan(): (permission: string) => boolean {
  const permissions = usePermissions();
  return (permission: string) => permissions.includes(permission);
}

export function useCanAny(): (permissions: string[]) => boolean {
  const owned = usePermissions();
  return (permissions: string[]) => permissions.some((p) => owned.includes(p));
}
