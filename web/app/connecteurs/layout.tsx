import type { ReactNode } from 'react';

import { ConnectorRoadmapSection } from '@/components/ConnectorRoadmapSection';

export default function ConnectorsLayout({ children }: { children: ReactNode }) {
  return (
    <>
      {children}
      <ConnectorRoadmapSection />
    </>
  );
}
