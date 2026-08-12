'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';

import SourcePresetPanel from './SourcePresetPanel';
import styles from './scraping.module.css';

export default function CustomScrapingSettingsLayout({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const registryActive = pathname === '/parametres/scraping';
  const searchesActive = pathname.startsWith('/parametres/scraping/recherches');

  return (
    <div className={styles.page}>
      <nav className="actions" aria-label="Navigation des paramètres de scraping" style={{ marginBottom: 16 }}>
        <Link className={`btn ${registryActive ? '' : 'secondary'}`} href="/parametres/scraping">
          Sources
        </Link>
        <Link className={`btn ${searchesActive ? '' : 'secondary'}`} href="/parametres/scraping/recherches">
          Recherches & diagnostics
        </Link>
      </nav>

      {children}
      {registryActive && <SourcePresetPanel />}
    </div>
  );
}
