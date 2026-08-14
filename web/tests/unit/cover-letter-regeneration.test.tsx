import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: apiMock,
}));

function application(manuallyEdited = false): Application {
  return {
    id: 61,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Message court.',
    coverLetter: 'Lettre de motivation actuellement affichée.',
    coverLetterManuallyEdited: manuallyEdited,
    coverLetterEditedAt: manuallyEdited ? '2026-08-14T18:00:00+02:00' : null,
    updatedAt: '2026-08-14T19:00:00+02:00',
    jobOffer: {
      id: 9,
      source: 'France Travail',
      title: 'Développeur Symfony',
      company: 'Example',
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
  } as Application & { coverLetterManuallyEdited?: boolean; coverLetterEditedAt?: string | null };
}

beforeEach(() => {
  vi.restoreAllMocks();
  apiMock.mockReset();
});

describe('CoverLetterDrawer regeneration', () => {
  it('shows character count and a customizable maximum length', () => {
    render(
      <CoverLetterDrawer
        application={application()}
        open
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByText(/mots · .*caractères/)).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Longueur maximale de la lettre' })).toHaveValue(1500);
    expect(screen.getByRole('button', { name: 'Régénérer' })).toBeInTheDocument();
  });

  it('regenerates the cover letter with the requested maximum length', async () => {
    const original = application();
    const updated = { ...application(), coverLetter: 'Nouvelle lettre plus concise.' } as Application;
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(updated);

    render(
      <CoverLetterDrawer
        application={original}
        open
        onClose={vi.fn()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Longueur maximale de la lettre' }), {
      target: { value: '900' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/61/cover-letter/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 900 }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
    expect(screen.getByRole('status')).toHaveTextContent('limite de 900 caractères');
  });

  it('asks for confirmation before overwriting a manually edited letter', () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

    render(
      <CoverLetterDrawer
        application={application(true)}
        open
        onClose={vi.fn()}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    expect(confirm).toHaveBeenCalledWith(expect.stringContaining('remplacera la version modifiée manuellement'));
    expect(apiMock).not.toHaveBeenCalled();
  });
});
