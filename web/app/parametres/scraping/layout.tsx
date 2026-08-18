import Link from 'next/link';
import type { ReactNode } from 'react';

import SourcePresetPanel from './SourcePresetPanel';
import styles from './scraping.module.css';

export default function CustomScrapingSettingsLayout({ children }: { children: ReactNode }) {
  return (
    <div className={styles.page}>
      <nav aria-label="Navigation scraping" style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 14 }}>
        <Link className="btn secondary" href="/parametres/scraping">Sources</Link>
        <Link className="btn secondary" href="/parametres/scraping/diagnostics">Diagnostics multi-recherche</Link>
      </nav>
      {children}
      <SourcePresetPanel />
    </div>
  );
}
