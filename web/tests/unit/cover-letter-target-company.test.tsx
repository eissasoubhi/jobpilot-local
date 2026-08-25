import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: apiMock,
}));

vi.mock('@/lib/clipboard', () => ({
  copyTextToClipboard: vi.fn(),
}));

function application(): Application {
  return {
    id: 61,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Message court.',
    coverLetter: 'Lettre de motivation.',
    updatedAt: '2026-08-25T02:00:00+02:00',
    jobOffer: {
      id: 9,
      source: 'Indeed',
      sourceCode: 'indeed-assisted',
      sourceUrl: 'https://fr.indeed.com/viewjob?jk=123',
      title: 'Développeur Symfony',
      company: 'Indeed',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Symfony PHP.',
      score: 90,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

beforeEach(() => {
  apiMock.mockReset();
});

describe('Motivation target company', () => {
  it('does not prefill the source platform and sends the explicit employer override', async () => {
    const original = application();
    const updated = { ...original, coverLetter: 'Lettre ciblée Proton.' } as Application;
    apiMock.mockResolvedValueOnce(updated);

    render(
      <CoverLetterDrawer
        application={original}
        open
        onClose={vi.fn()}
      />,
    );

    const companyInput = screen.getByRole('textbox', { name: 'Entreprise ciblée pour la motivation' });
    expect(companyInput).toHaveValue('');
    expect(screen.getByText(/Indeed ne sera pas utilisé comme nom d’entreprise/)).toBeInTheDocument();

    fireEvent.change(companyInput, { target: { value: 'Proton' } });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/61/cover-letter/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 1500, targetCompany: 'Proton' }),
    }));
  });

  it('uses the same employer override for the short message tab', async () => {
    const original = application();
    const updated = { ...original, message: 'Message ciblé Proton.' } as Application;
    apiMock.mockResolvedValueOnce(updated);

    render(
      <CoverLetterDrawer
        application={original}
        open
        initialTab="message"
        onClose={vi.fn()}
      />,
    );

    fireEvent.change(screen.getByRole('textbox', { name: 'Entreprise ciblée pour la motivation' }), {
      target: { value: 'Proton' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/61/message/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 400, targetCompany: 'Proton' }),
    }));
  });
});
