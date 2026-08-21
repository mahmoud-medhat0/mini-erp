import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import type { SharedPageProps } from '../Types/page';

type FoundationProps = SharedPageProps & {
  status: string;
  database: 'ok' | 'unavailable' | 'unknown' | 'not_checked';
};

export default function Foundation({ status, database }: FoundationProps) {
  const { props } = usePage<FoundationProps>();
  const { post, processing } = useForm({});
  const isReady = database === 'ok';

  function logout(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/logout');
  }

  return (
    <>
      <Head title="Foundation" />
      <main className="min-h-screen bg-[var(--background)] text-[var(--text-primary)]">
        <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-6 py-8">
          <header className="flex items-center justify-between border-b border-[var(--border)] pb-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">
                Laravel + Inertia
              </p>
              <h1 className="m-0 text-xl font-bold">Mini ERP</h1>
            </div>
            <div className="flex items-center gap-3">
              {props.auth.user ? (
                <span className="text-sm text-[var(--text-secondary)]">{props.auth.user.email}</span>
              ) : null}
              <form onSubmit={logout}>
                <button
                  type="submit"
                  disabled={processing}
                  className="h-9 rounded-sm border border-[var(--border)] px-3 text-sm font-semibold text-[var(--text-secondary)] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Logout
                </button>
              </form>
            </div>
          </header>

          <section className="grid flex-1 content-center gap-5 py-10">
            <div className="rounded-md border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h2 className="m-0 text-lg font-semibold">Migration foundation</h2>
                  <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--text-secondary)]">
                    Laravel session authentication is active beside the existing Next.js reference app. No ERP module
                    data is mocked here.
                  </p>
                </div>
                <span
                  className={[
                    'rounded-sm px-3 py-1 text-sm font-semibold',
                    isReady
                      ? 'bg-[var(--success-subtle)] text-[var(--success)]'
                      : 'border border-[var(--border)] text-[var(--text-secondary)]',
                  ].join(' ')}
                >
                  {database === 'ok' ? 'PostgreSQL OK' : 'DB health at /health'}
                </span>
              </div>

              <dl className="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                <div className="border-t border-[var(--border)] pt-3">
                  <dt className="text-[var(--text-muted)]">Phase</dt>
                  <dd className="m-0 font-semibold">{status}</dd>
                </div>
                <div className="border-t border-[var(--border)] pt-3">
                  <dt className="text-[var(--text-muted)]">Locale</dt>
                  <dd className="m-0 font-semibold">{props.locale}</dd>
                </div>
                <div className="border-t border-[var(--border)] pt-3">
                  <dt className="text-[var(--text-muted)]">Theme</dt>
                  <dd className="m-0 font-semibold">{props.theme}</dd>
                </div>
              </dl>
            </div>
          </section>
        </div>
      </main>
    </>
  );
}
