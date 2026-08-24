'use client';

import { usePathname } from 'next/navigation';
import { type ReactNode, useEffect, useState } from 'react';

type Props = { children: ReactNode };

export function OffersSyncRefreshBoundary({ children }: Props) {
  const pathname = usePathname();
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    if (pathname !== '/offres') return;

    const refresh = (): void => setRevision((current) => current + 1);
    window.addEventListener('jobpilot:offers-sync-completed', refresh);

    return () => window.removeEventListener('jobpilot:offers-sync-completed', refresh);
  }, [pathname]);

  return <div key={`${pathname}:${revision}`}>{children}</div>;
}
