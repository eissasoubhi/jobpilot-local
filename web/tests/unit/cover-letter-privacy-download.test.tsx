import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import type { Application } from '@/lib/types';

const { downloadMock } = vi.hoisted(() => ({ downloadMock: vi.fn() }));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: vi.fn(),
}));
vi.mock('@/lib/privacy-download', () => ({
  downloadWithCleanProvenance: downloadMock,
}));

function application(): Application {
  return {
    id: 51,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Message court.',
    coverLetter: 'Lettre de motivation préparée.',
    updatedAt: '2026-08-24T10:00:00+02:00',
    jobOffer: {
      id: 8,
      source: 'France Travail',
      sourceUrl: 'https://example.test/job/8',
      title: 'Développeur Symfony',
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Symfony PHP API Platform.',
      score: 92,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  };
}

describe('CoverLetterDrawer privacy downloads', () => {
  beforeEach(() => {
    downloadMock.mockReset();
  });

  it.each([
    ['PDF', 'pdf'],
    ['Word (.docx)', 'docx'],
  ] as const)('routes %s exports through the privacy-clean helper', async (label, format) => {
    downloadMock.mockResolvedValueOnce({ privacyClean: true, fallbackUsed: false });

    render(
      <CoverLetterDrawer
        application={application()}
        open
        onClose={vi.fn()}
      />,
    );

    const dialog = screen.getByRole('dialog', { name: 'Motivation' });
    fireEvent.click(within(dialog).getByText('Télécharger'));
    fireEvent.click(within(dialog).getByRole('button', { name: label }));

    await waitFor(() => expect(downloadMock).toHaveBeenCalledWith({
      url: `/api/applications/51/cover-letter/download/${format}`,
      filename: `lettre-motivation-51.${format}`,
    }));
    expect(within(dialog).getByText(`Téléchargement ${format.toUpperCase()} préparé sans provenance JobPilot.`)).toBeInTheDocument();
  });

  it('makes the explicit browser fallback visible instead of claiming privacy-clean success', async () => {
    downloadMock.mockResolvedValueOnce({ privacyClean: false, fallbackUsed: true });

    render(
      <CoverLetterDrawer
        application={application()}
        open
        onClose={vi.fn()}
      />,
    );

    fireEvent.click(screen.getByText('Télécharger'));
    fireEvent.click(screen.getByRole('button', { name: 'PDF' }));

    expect(await screen.findByText('Téléchargement PDF lancé avec le mécanisme navigateur standard.')).toBeInTheDocument();
  });
});
