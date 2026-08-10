import type { ReactNode } from 'react';

import SourcePresetPanel from './SourcePresetPanel';

export default function CustomScrapingSettingsLayout({ children }: { children: ReactNode }) {
  return (
    <>
      {children}
      <SourcePresetPanel />
    </>
  );
}
