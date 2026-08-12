import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  CustomScraperSearchDiagnosticsPanel,
  type SearchableCustomScraperSource,
} from '@/app/parametres/scraping/CustomScraperSearchDiagnosticsPanel';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const source: SearchableCustomScraperSource = {
  id: 42,
  name: 'Example Jobs',
  searchUrlTemplate: 'https://jobs.example.com/search?q={keyword}',
  searchKeywords: ['PHP', 'Symfony'],
  maxPages: 5,
  authorizationConfirmed: true,
};

describe('CustomScraperSearchDiagnosticsPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows generated URLs and the bounded page budget without contacting the source site', async () => {
    apiMock.mockResolvedValueOnce({
      configured: true,
      searchCount: 2,
      maxPagesPerSearch: 5,
      requestedMaxListingRequests: 10,
      globalPageBudget: 10,
      budgetLimited: false,
      searches: [
        { keyword: 'PHP', url: 'https://jobs.example.com/search?q=PHP', pageLimit: 5 },
        { keyword: 'Symfony', url: 'https://jobs.example.com/search?q=Symfony', pageLimit: 5 },
      ],
    });

    render(<CustomScraperSearchDiagnosticsPanel source={source} onUpdated={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Voir le plan' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/custom-scrapers/42/search-plan'));
    expect(screen.getByText('https://jobs.example.com/search?q=PHP')).toBeInTheDocument();
    expect(screen.getByText('https://jobs.example.com/search?q=Symfony')).toBeInTheDocument();
    expect(screen.getByText('Pages autorisées')).toBeInTheDocument();
    expect(screen.getAllByText('10', { selector: 'strong' })).toHaveLength(2);
  });

  it('renders concise per-keyword HTTP diagnostics and deduplication metrics', async () => {
    apiMock.mockResolvedValueOnce({
      searchCount: 2,
      executedSearchCount: 2,
      requestedMaxListingRequests: 10,
      globalPageBudget: 10,
      budgetLimited: false,
      networkRequests: 3,
      durationMs: 1250,
      rawCandidateCount: 8,
      duplicateCount: 3,
      candidateCount: 5,
      requiresBrowser: false,
      stoppedEarly: false,
      globalError: null,
      diagnostics: [
        {
          keyword: 'PHP',
          requestedUrl: 'https://jobs.example.com/search?q=PHP',
          pageLimit: 5,
          pagesFetched: 2,
          rawCandidateCount: 5,
          recommendedMode: 'HTTP',
          statusCodes: [200, 200],
          lastStatusCode: 200,
          durationMs: 700,
          stopReason: 'NO_NEXT_PAGE',
          error: null,
        },
        {
          keyword: 'Symfony',
          requestedUrl: 'https://jobs.example.com/search?q=Symfony',
          pageLimit: 5,
          pagesFetched: 1,
          rawCandidateCount: 3,
          recommendedMode: 'HTTP',
          statusCodes: [200],
          lastStatusCode: 200,
          durationMs: 550,
          stopReason: 'NO_NEXT_PAGE',
          error: null,
        },
      ],
    });

    render(<CustomScraperSearchDiagnosticsPanel source={source} onUpdated={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Tester les recherches' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/custom-scrapers/42/search-preview', { method: 'POST' }));
    expect(screen.getByText('Offres brutes')).toBeInTheDocument();
    expect(screen.getByText('Doublons')).toBeInTheDocument();
    expect(screen.getByText('Offres uniques')).toBeInTheDocument();
    expect(screen.getAllByText('HTTP 200')).toHaveLength(2);
    expect(screen.getByText('1,3 s')).toBeInTheDocument();
    expect(screen.getAllByText(/Fin des résultats/)).toHaveLength(2);
  });

  it('edits keywords as chips and requires saving before a network test', async () => {
    const onUpdated = vi.fn();
    apiMock.mockResolvedValueOnce({
      ...source,
      searchKeywords: ['PHP', 'Symfony', 'React.js'],
    });

    render(<CustomScraperSearchDiagnosticsPanel source={source} onUpdated={onUpdated} />);

    fireEvent.change(screen.getByLabelText('Ajouter un mot-clé'), { target: { value: 'React.js' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter' }));

    expect(screen.getByRole('button', { name: 'Retirer le mot-clé React.js' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tester les recherches' })).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/custom-scrapers/42', {
      method: 'PATCH',
      body: JSON.stringify({
        searchUrlTemplate: 'https://jobs.example.com/search?q={keyword}',
        searchKeywords: ['PHP', 'Symfony', 'React.js'],
      }),
    }));
    expect(onUpdated).toHaveBeenCalledWith(expect.objectContaining({ searchKeywords: ['PHP', 'Symfony', 'React.js'] }));
  });
});
