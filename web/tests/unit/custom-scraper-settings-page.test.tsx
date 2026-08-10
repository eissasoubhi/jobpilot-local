import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CustomScrapingSettingsPage from '@/app/parametres/scraping/page';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const source = {
  id: 42,
  name: 'Example Jobs',
  domain: 'jobs.example.test',
  listingUrl: 'https://jobs.example.test/jobs',
  detailExampleUrl: null,
  mode: 'AUTO',
  enabled: true,
  authorizationConfirmed: true,
  authorizationCheckedAt: '2026-08-10',
  authorizationReference: 'Autorisation vérifiée.',
  syncIntervalMinutes: 360,
  maxPages: 5,
  maxDetails: 20,
};

const preview = {
  configuredMode: 'AUTO',
  recommendedMode: 'HTTP',
  effectiveMode: 'HTTP',
  requiresBrowser: false,
  candidateCount: 2,
  reliableCount: 1,
  detailLimit: 10,
  detailEnriched: 1,
  detailError: null,
  signals: {},
  http: {
    requestedUrl: source.listingUrl,
    finalUrl: source.listingUrl,
    statusCode: 200,
    responseBytes: 12345,
    networkRequests: 2,
    fromCache: false,
  },
  candidates: [
    {
      sourceUrl: 'https://jobs.example.test/jobs/symfony',
      externalId: 'link-1',
      title: 'Senior Symfony Developer',
      company: 'Acme France',
      location: 'Paris',
      contractType: 'Freelance',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Mission Symfony 6.4 et API Platform.',
      publishedAt: null,
      salaryMin: null,
      salaryMax: null,
      tjmMin: 450,
      tjmMax: 500,
      rawData: {
        extractionMethod: 'JOB_LINK',
        detailExtractionMethod: 'JSON_LD',
        detailEnriched: true,
        quality: {
          reliable: true,
          score: 86,
          reasons: [
            'Titre exploitable.',
            'URL HTTPS du domaine autorisé.',
            'Données Schema.org JobPosting détectées.',
          ],
        },
      },
    },
    {
      sourceUrl: 'https://jobs.example.test/jobs/react',
      externalId: 'link-2',
      title: 'React Developer',
      company: 'Example Jobs',
      location: '',
      contractType: '',
      workMode: '',
      language: 'fr',
      description: '',
      publishedAt: null,
      salaryMin: null,
      salaryMax: null,
      tjmMin: null,
      tjmMax: null,
      rawData: {
        extractionMethod: 'JOB_LINK',
        needsDetailFetch: true,
        quality: {
          reliable: false,
          score: 47,
          reasons: [
            'Titre exploitable.',
            'URL HTTPS du domaine autorisé.',
            'Description trop courte pour un import automatique.',
          ],
        },
      },
    },
  ],
};

describe('CustomScrapingSettingsPage', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('previews extraction reliability without importing candidates', async () => {
    apiMock.mockResolvedValueOnce([source]);
    apiMock.mockResolvedValueOnce(preview);

    render(<CustomScrapingSettingsPage />);

    await waitFor(() => expect(screen.getByText('Example Jobs')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Prévisualiser les offres' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/custom-scrapers/42/preview', { method: 'POST' }));
    expect(screen.getByText('2 candidat(s)')).toBeInTheDocument();
    expect(screen.getByText('1 éligible(s) à l’import')).toBeInTheDocument();
    expect(screen.getByText('1/10 fiche(s) enrichie(s)')).toBeInTheDocument();
    expect(screen.getByText('Senior Symfony Developer')).toBeInTheDocument();
    expect(screen.getByText('Acme France · Freelance · Paris · Hybride · TJM 450–500 €')).toBeInTheDocument();
    expect(screen.getByText('Mission Symfony 6.4 et API Platform.')).toBeInTheDocument();
    expect(screen.getByText('JobPosting')).toBeInTheDocument();
    expect(screen.getByText('Fiable · 86/100')).toBeInTheDocument();
    expect(screen.getByText('React Developer')).toBeInTheDocument();
    expect(screen.getByText('Lien détecté')).toBeInTheDocument();
    expect(screen.getByText('À vérifier · 47/100')).toBeInTheDocument();
    expect(screen.getByText(/ce n’est pas le score de compatibilité avec ton profil/i)).toBeInTheDocument();
    expect(screen.getByText('2 requête(s) cible · HTTP 200 · aucune offre enregistrée')).toBeInTheDocument();
    expect(screen.getAllByText(/^Qualité :/)).toHaveLength(2);
  });
});
