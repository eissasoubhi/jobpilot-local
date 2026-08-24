import type { Metadata } from 'next';
import type { ReactNode } from 'react';

import { OffersSelectiveSyncPanel } from '@/components/OffersSelectiveSyncPanel';
import { Shell } from '@/components/Shell';
import { SyncRunPanel } from '@/components/SyncRunPanel';

import './globals.css';

export const metadata: Metadata = {
  title: 'JobPilot Local',
  description: 'Gestion locale des candidatures',
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="fr" suppressHydrationWarning>
      <body suppressHydrationWarning>
        <Shell>
          <OffersSelectiveSyncPanel />
          <SyncRunPanel />
          {children}
        </Shell>
      </body>
    </html>
  );
}
