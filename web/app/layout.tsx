import type { Metadata } from 'next';
import type { ReactNode } from 'react';

import { Shell } from '@/components/Shell';

import './globals.css';

export const metadata: Metadata = {
  title: 'JobPilot Local',
  description: 'Gestion locale des candidatures',
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="fr" suppressHydrationWarning>
      <body suppressHydrationWarning>
        <Shell>{children}</Shell>
      </body>
    </html>
  );
}
