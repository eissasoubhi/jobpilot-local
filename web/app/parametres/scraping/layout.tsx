import type { ReactNode } from 'react';

import SourcePresetPanel from './SourcePresetPanel';
import styles from './scraping.module.css';

export default function CustomScrapingSettingsLayout({ children }: { children: ReactNode }) {
  return (
    <div className={styles.page}>
      {children}
      <SourcePresetPanel />
    </div>
  );
}
