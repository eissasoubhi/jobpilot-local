import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Shell } from '@/components/Shell';

vi.mock('next/navigation', () => ({
  usePathname: () => '/offres',
}));

vi.mock('@/components/AiSidebarStatus', () => ({ AiSidebarStatus: () => null }));
vi.mock('@/components/ApplicationGoalAlerts', () => ({ ApplicationGoalAlerts: () => null }));
vi.mock('@/components/ApplicationGoalsSettings', () => ({ ApplicationGoalsSettings: () => null }));
vi.mock('@/components/CatalogResetPanel', () => ({ CatalogResetPanel: () => null }));
vi.mock('@/components/GeminiPaidQuotaPresetPanel', () => ({ GeminiPaidQuotaPresetPanel: () => null }));
vi.mock('@/components/NotificationCenter', () => ({ NotificationCenter: () => null }));
vi.mock('@/components/ProfileCleanupPanel', () => ({ ProfileCleanupPanel: () => null }));

describe('Shell mobile navigation', () => {
  it('keeps the navigation collapsed until the menu button opens it', () => {
    render(<Shell><div>Contenu Offres</div></Shell>);

    const navigation = screen.getByRole('navigation', { name: 'Navigation principale' });
    const openButton = screen.getByRole('button', { name: 'Menu' });

    expect(openButton).toHaveAttribute('aria-expanded', 'false');
    expect(navigation).not.toHaveClass('is-open');
    expect(screen.getByText('Contenu Offres')).toBeInTheDocument();

    fireEvent.click(openButton);

    expect(screen.getByRole('button', { name: 'Fermer' })).toHaveAttribute('aria-expanded', 'true');
    expect(navigation).toHaveClass('is-open');
  });
});
