import type { Metadata } from 'next';
import type { ReactNode } from 'react';

import { OffersSelectiveSyncPanel } from '@/components/OffersSelectiveSyncPanel';
import { OffersSyncRefreshBoundary } from '@/components/OffersSyncRefreshBoundary';
import { Shell } from '@/components/Shell';
import { SyncRunPanel } from '@/components/SyncRunPanel';

import './globals.css';
import './design-tokens.css';
import './accessibility.css';

export const metadata: Metadata = {
  title: 'JobPilot',
  description: 'Gestion des candidatures',
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="fr" suppressHydrationWarning>
      <body suppressHydrationWarning>
        <Shell>
          <OffersSelectiveSyncPanel />
          <SyncRunPanel />
          <OffersSyncRefreshBoundary>{children}</OffersSyncRefreshBoundary>
        </Shell>
      </body>
    </html>
  );
}
