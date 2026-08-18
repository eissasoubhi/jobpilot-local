import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import CustomScraperSearchDiagnosticsPage from '@/app/parametres/scraping/diagnostics/page';

const sources = [
  {
    id: 7,
    name: 'Example Jobs',
    domain: 'jobs.example.com',
    mode: 'AUTO',
    enabled: true,
    authorizationConfirmed: true,
  },
];

const preview = {
  searchCount: 2,
  executedSearchCount: 2,
  requestedMaxListingRequests: 8,
  globalPageBudget: 6,
  budgetLimited: true,
  networkRequests: 4,
  durationMs: 350,
  rawCandidateCount: 5,
  duplicateCount: 2,
  candidateCount: 3,
  requiresBrowser: false,
  stoppedEarly: false,
  globalError: null,
  diagnostics: [
    {
      keyword: 'Symfony',
      requestedUrl: 'https://jobs.example.com/search?q=Symfony',
      pageLimit: 3,
      pagesFetched: 2,
      rawCandidateCount: 3,
      recommendedMode: 'HTTP',
      statusCodes: [200, 200],
      lastStatusCode: 200,
      durationMs: 120,
      stopReason: 'NO_NEXT_PAGE',
      error: null,
      history: [
        {
          page: 1,
          url: 'https://jobs.example.com/search?q=Symfony',
          statusCode: 200,
          nextUrl: 'https://jobs.example.com/search?q=Symfony&page=2',
          strategy: 'LINK_REL_NEXT',
          confidence: 1,
        },
      ],
    },
    {
      keyword: 'PHP',
      requestedUrl: 'https://jobs.example.com/search?q=PHP',
      pageLimit: 3,
      pagesFetched: 2,
      rawCandidateCount: 2,
      recommendedMode: 'HTTP',
      statusCodes: [200, 200],
      lastStatusCode: 200,
      durationMs: 110,
      stopReason: 'PAGE_LIMIT_REACHED',
      error: null,
      history: [],
    },
  ],
  candidates: [
    {
      sourceUrl: 'https://jobs.example.com/offres/1',
      title: 'Senior Symfony Developer',
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'HYBRID',
      rawData: { discoveredByKeywords: ['Symfony', 'PHP'] },
    },
  ],
};

describe('CustomScraperSearchDiagnosticsPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('runs a manual multi-search preview and exposes budget, pagination and deduplication diagnostics', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response(JSON.stringify(sources), { status: 200, headers: { 'Content-Type': 'application/json' } }))
      .mockResolvedValueOnce(new Response(JSON.stringify(preview), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    vi.stubGlobal('fetch', fetchMock);

    render(<CustomScraperSearchDiagnosticsPage />);

    const runButton = await screen.findByRole('button', { name: 'Exécuter le preview multi-recherche' });
    expect(runButton).toBeEnabled();
    fireEvent.click(runButton);

    await waitFor(() => expect(screen.getByText('2/2')).toBeInTheDocument());

    const budgetMetric = screen.getByText('Budget pages').parentElement;
    const uniqueMetric = screen.getByText('Offres uniques').parentElement;
    expect(budgetMetric).not.toBeNull();
    expect(uniqueMetric).not.toBeNull();
    expect(within(budgetMetric as HTMLElement).getByText('6')).toBeInTheDocument();
    expect(within(uniqueMetric as HTMLElement).getByText('3')).toBeInTheDocument();
    expect(screen.getByText('2 doublon(s) fusionné(s)')).toBeInTheDocument();
    expect(screen.getByText('Symfony')).toBeInTheDocument();
    expect(screen.getByText('Aucune page suivante détectée')).toBeInTheDocument();
    expect(screen.getByText('Senior Symfony Developer')).toBeInTheDocument();
    expect(screen.getByText('via Symfony, PHP')).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('does not allow a network preview before authorization is confirmed', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify([
      { ...sources[0], authorizationConfirmed: false },
    ]), { status: 200, headers: { 'Content-Type': 'application/json' } })));

    render(<CustomScraperSearchDiagnosticsPage />);

    const runButton = await screen.findByRole('button', { name: 'Exécuter le preview multi-recherche' });
    expect(runButton).toBeDisabled();
    expect(screen.getByText(/Confirme d’abord l’autorisation de collecte/)).toBeInTheDocument();
  });
});
