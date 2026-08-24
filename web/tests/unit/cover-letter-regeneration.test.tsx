import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import type { Application } from '@/lib/types';

const { apiMock, copyMock } = vi.hoisted(() => ({ apiMock: vi.fn(), copyMock: vi.fn() }));

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
  copyMock.mockReset();
  Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: copyMock },
  });
});

describe('Motivation drawer', () => {
  it('groups the cover letter and short message into two tabs', () => {
    render(
      <CoverLetterDrawer
        application={application()}
        open
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByRole('dialog', { name: 'Motivation' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Lettre de motivation' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: 'Message court' })).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByText(/mots · .*caractères/)).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Longueur maximale de la lettre' })).toHaveValue(1500);

    fireEvent.click(screen.getByRole('tab', { name: 'Message court' }));

    expect(screen.getByRole('tab', { name: 'Message court' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByText('Message court.')).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' })).toHaveValue(400);
  });

  it('can open directly on the short-message tab', () => {
    render(
      <CoverLetterDrawer
        application={application()}
        open
        initialTab="message"
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByRole('tab', { name: 'Message court' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByText('Message court.')).toBeInTheDocument();
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

  it('regenerates and copies the short message from its tab', async () => {
    const updated = { ...application(), message: 'Nouveau message court.' } as Application;
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(updated);
    copyMock.mockResolvedValueOnce(undefined);

    const { rerender } = render(
      <CoverLetterDrawer
        application={application()}
        open
        initialTab="message"
        onClose={vi.fn()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' }), {
      target: { value: '250' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/61/message/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 250 }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);

    rerender(
      <CoverLetterDrawer
        application={updated}
        open
        initialTab="message"
        onClose={vi.fn()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Copier' }));
    await waitFor(() => expect(copyMock).toHaveBeenCalledWith('Nouveau message court.'));
  });

  it('offers a direct reduction action when the current message exceeds the selected limit', async () => {
    const original = { ...application(), message: 'x'.repeat(581) } as Application;
    const updated = { ...original, message: 'x'.repeat(398) } as Application;
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(updated);

    render(
      <CoverLetterDrawer
        application={original}
        open
        initialTab="message"
        onClose={vi.fn()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    expect(screen.getByRole('button', { name: 'Réduire à 400' })).toBeInTheDocument();
    expect(screen.getByText(/581 caractères et dépasse la limite choisie de 400/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réduire à 400' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/61/message/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 400 }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
  });

  it('does not present reduction as required when the selected limit already fits the message', () => {
    const original = { ...application(), message: 'x'.repeat(581) } as Application;

    render(
      <CoverLetterDrawer
        application={original}
        open
        initialTab="message"
        onClose={vi.fn()}
      />,
    );

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' }), {
      target: { value: '600' },
    });

    expect(screen.getByRole('button', { name: 'Régénérer' })).toBeInTheDocument();
    expect(screen.queryByText(/dépasse la limite choisie/)).not.toBeInTheDocument();
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
