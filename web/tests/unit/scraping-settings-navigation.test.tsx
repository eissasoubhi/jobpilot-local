import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import CustomScrapingSettingsLayout from '@/app/parametres/scraping/layout';

const navigation = vi.hoisted(() => ({ pathname: '/parametres/scraping' }));

vi.mock('next/navigation', () => ({
  usePathname: () => navigation.pathname,
}));

vi.mock('@/app/parametres/scraping/SourcePresetPanel', () => ({
  default: () => <div data-testid="source-presets">Presets</div>,
}));

afterEach(() => cleanup());

describe('CustomScrapingSettingsLayout', () => {
  it('keeps source presets on the registry screen', () => {
    navigation.pathname = '/parametres/scraping';

    render(<CustomScrapingSettingsLayout><div>Registry</div></CustomScrapingSettingsLayout>);

    expect(screen.getByRole('link', { name: 'Sources' })).toHaveAttribute('href', '/parametres/scraping');
    expect(screen.getByRole('link', { name: 'Recherches & diagnostics' })).toHaveAttribute('href', '/parametres/scraping/recherches');
    expect(screen.getByTestId('source-presets')).toBeInTheDocument();
  });

  it('hides presets on the dedicated diagnostics screen', () => {
    navigation.pathname = '/parametres/scraping/recherches';

    render(<CustomScrapingSettingsLayout><div>Diagnostics</div></CustomScrapingSettingsLayout>);

    expect(screen.getByText('Diagnostics')).toBeInTheDocument();
    expect(screen.queryByTestId('source-presets')).not.toBeInTheDocument();
  });
});
