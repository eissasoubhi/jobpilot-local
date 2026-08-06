import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  ConnectorSearchCriteriaPanel,
  parseCriteriaLines,
} from '@/components/ConnectorSearchCriteriaPanel';

const initialCriteria = {
  code: 'france-travail',
  name: 'France Travail',
  scope: 'GLOBAL',
  targetJobs: ['Senior Symfony Developer', 'Backend PHP/Symfony'],
  skills: ['PHP', 'Symfony'],
  effectiveQueries: ['Symfony', 'Backend PHP Symfony'],
  latestSearchDiagnostics: {
    startedAt: '2026-08-06T09:00:00+02:00',
    requestedQueries: 2,
    completedQueries: 2,
    queriesWithResults: 1,
    queriesWithoutResults: 1,
    received: 3,
    uniqueOffers: 2,
    matchesCurrentCriteria: true,
    queries: [
      {
        query: 'Symfony',
        statusCode: 204,
        outcome: 'NO_RESULTS',
        received: 0,
        uniqueOffersAdded: 0,
      },
      {
        query: 'Backend PHP Symfony',
        statusCode: 206,
        outcome: 'RESULTS',
        received: 3,
        uniqueOffersAdded: 2,
      },
    ],
  },
  fixedCriteria: [
    { key: 'sort', label: 'Tri', value: 'Offres les plus récentes' },
    { key: 'limit', label: 'Limite', value: '6 requêtes maximum par synchronisation' },
  ],
  limits: {
    maxItemsPerList: 20,
    maxItemLength: 120,
    maxEffectiveQueries: 6,
  },
  note: 'Ces intitulés et compétences sont les critères globaux de JobPilot.',
};

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('ConnectorSearchCriteriaPanel', () => {
  it('normalizes lines and preserves the first casing of duplicates', () => {
    expect(parseCriteriaLines(' PHP  \nphp\n Symfony \n\nReact')).toEqual([
      'PHP',
      'Symfony',
      'React',
    ]);
  });

  it('shows effective queries, latest performance and saves edited criteria', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => initialCriteria,
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          ...initialCriteria,
          targetJobs: ['Full-Stack Symfony/React'],
          skills: ['PHP', 'React'],
          effectiveQueries: ['Full Stack Symfony React'],
          latestSearchDiagnostics: {
            ...initialCriteria.latestSearchDiagnostics,
            matchesCurrentCriteria: false,
          },
        }),
      });
    vi.stubGlobal('fetch', fetchMock);

    render(<ConnectorSearchCriteriaPanel connectorCode="france-travail" />);

    expect(await screen.findByText('Requêtes réellement envoyées à France Travail')).toBeInTheDocument();
    expect(screen.getByText('Symfony', { selector: 'span.badge.blue' })).toBeInTheDocument();
    expect(screen.getByText('Backend PHP Symfony', { selector: 'span.badge.blue' })).toBeInTheDocument();
    expect(screen.getByText('Tri : Offres les plus récentes')).toBeInTheDocument();
    expect(screen.getByText('Performance de la dernière synchronisation')).toBeInTheDocument();
    expect(screen.getByText('Aucun résultat')).toBeInTheDocument();
    expect(screen.getByText('3 offre(s) reçue(s)')).toBeInTheDocument();
    expect(screen.getByText('2 nouvelle(s) offre(s) unique(s)')).toBeInTheDocument();
    expect(screen.getByText('Correspond aux critères actuels')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Modifier les critères' }));
    fireEvent.change(screen.getByLabelText('Intitulés ciblés — un par ligne'), {
      target: { value: ' Full-Stack Symfony/React \nfull-stack symfony/react' },
    });
    fireEvent.change(screen.getByLabelText('Compétences de repli — une par ligne'), {
      target: { value: 'PHP\nReact\nphp' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les critères' }));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    const secondCall = fetchMock.mock.calls[1];
    expect(secondCall).toBeDefined();
    const request = secondCall?.[1] as RequestInit;
    expect(request.method).toBe('PUT');
    expect(JSON.parse(String(request.body))).toEqual({
      targetJobs: ['Full-Stack Symfony/React'],
      skills: ['PHP', 'React'],
    });

    expect(await screen.findByText('Les critères de recherche ont été enregistrés.')).toBeInTheDocument();
    expect(screen.getByText('Full Stack Symfony React', { selector: 'span.badge.blue' })).toBeInTheDocument();
    expect(screen.getByText('Critères modifiés depuis ce test')).toBeInTheDocument();
  });
});
