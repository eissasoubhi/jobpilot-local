import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ConnectorDeadLettersSection } from '@/components/ConnectorDeadLettersSection';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const incident = {
  id: 7,
  connectorCode: 'custom-scraper-42',
  stage: 'IMPORT',
  fingerprint: 'secret-internal-fingerprint',
  state: 'OPEN',
  failureCount: 3,
  externalId: 'JOB-42',
  sourceUrl: 'https://jobs.example.test/jobs/42',
  title: 'Développeur PHP Symfony',
  errorClass: 'InvalidArgumentException',
  errorMessage: 'Offre sans description exploitable.',
  firstFailedAt: '2026-08-10T20:00:00+02:00',
  lastFailedAt: '2026-08-11T00:30:00+02:00',
  resolvedAt: null,
};

describe('ConnectorDeadLettersSection', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows open incidents and resolves them without exposing the internal fingerprint', async () => {
    apiMock.mockResolvedValueOnce([incident]);
    apiMock.mockResolvedValueOnce({ ...incident, state: 'RESOLVED', resolvedAt: '2026-08-11T01:00:00+02:00' });
    apiMock.mockResolvedValueOnce([]);

    render(<ConnectorDeadLettersSection />);

    await waitFor(() => expect(screen.getByText('Développeur PHP Symfony')).toBeInTheDocument());
    expect(screen.getByText('3 échec(s)')).toBeInTheDocument();
    expect(screen.getByText('Import')).toBeInTheDocument();
    expect(screen.getByText('custom-scraper-42')).toBeInTheDocument();
    expect(screen.getByText('Offre sans description exploitable.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ouvrir la fiche source' })).toHaveAttribute(
      'href',
      'https://jobs.example.test/jobs/42',
    );
    expect(screen.queryByText('secret-internal-fingerprint')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Marquer résolu' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/connectors/dead-letters/7/resolve', { method: 'POST' }));
    await waitFor(() => expect(screen.queryByText('Développeur PHP Symfony')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent('Incident custom-scraper-42 marqué comme résolu.');
    expect(apiMock).toHaveBeenLastCalledWith('/connectors/dead-letters?state=OPEN&limit=50');
  });
});
