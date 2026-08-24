import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { OffersSyncRefreshBoundary } from '@/components/OffersSyncRefreshBoundary';

const pathnameMock = vi.fn(() => '/offres');

vi.mock('next/navigation', () => ({
  usePathname: () => pathnameMock(),
}));

function StatefulChild() {
  const [value, setValue] = useState(0);

  return <button type="button" onClick={() => setValue((current) => current + 1)}>Valeur {value}</button>;
}

describe('OffersSyncRefreshBoundary', () => {
  beforeEach(() => {
    pathnameMock.mockReturnValue('/offres');
  });

  it('remounts the offers subtree when a sync completion event is emitted', () => {
    render(<OffersSyncRefreshBoundary><StatefulChild /></OffersSyncRefreshBoundary>);

    fireEvent.click(screen.getByRole('button', { name: 'Valeur 0' }));
    expect(screen.getByRole('button', { name: 'Valeur 1' })).toBeInTheDocument();

    act(() => {
      window.dispatchEvent(new CustomEvent('jobpilot:offers-sync-completed'));
    });

    expect(screen.getByRole('button', { name: 'Valeur 0' })).toBeInTheDocument();
  });

  it('does not remount unrelated workspaces', () => {
    pathnameMock.mockReturnValue('/candidatures');
    render(<OffersSyncRefreshBoundary><StatefulChild /></OffersSyncRefreshBoundary>);

    fireEvent.click(screen.getByRole('button', { name: 'Valeur 0' }));
    act(() => {
      window.dispatchEvent(new CustomEvent('jobpilot:offers-sync-completed'));
    });

    expect(screen.getByRole('button', { name: 'Valeur 1' })).toBeInTheDocument();
  });
});
