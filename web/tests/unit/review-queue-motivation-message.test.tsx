import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import type { Application } from '@/lib/types';

const { apiMock, copyMock } = vi.hoisted(() => ({
  apiMock: vi.fn(),
  copyMock: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: apiMock,
}));
vi.mock('@/components/CoverLetterDrawer', () => ({ CoverLetterDrawer: () => null }));
vi.mock('@/components/ReviewQueueTechnologyComparison', () => ({ ReviewQueueTechnologyComparison: () => null }));
vi.mock('@/lib/job-publication', () => ({
  offerPublicationTiming: () => ({ label: 'Publiée il y a 1 jour', exactLabel: null, stale: false }),
}));

function application(message = 'Message court déjà préparé.'): Application {
  return {
    id: 51,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message,
    coverLetter: 'Lettre longue préparée.',
    updatedAt: '2026-08-14T19:00:00+02:00',
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

describe('Review Queue motivation message', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    apiMock.mockReset();
    copyMock.mockReset();
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: copyMock },
    });
  });

  it('shows the short message with a default 400-character target', () => {
    render(<ReviewQueueApplicationCard application={application()} />);

    expect(screen.getByText('Message court de motivation')).toBeInTheDocument();
    expect(screen.getByText('Message court déjà préparé.')).toBeInTheDocument();
    expect(screen.getByText('27 caractères')).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' })).toHaveValue(400);
    expect(screen.getByRole('button', { name: 'Régénérer le message' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copier le message' })).toBeInTheDocument();
  });

  it('regenerates only the message with the chosen maximum length', async () => {
    const original = application();
    const updated = application('Nouveau message de motivation court.');
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(updated);

    render(
      <ReviewQueueApplicationCard
        application={original}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' }), {
      target: { value: '250' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer le message' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/51/message/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 250 }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
    expect(await screen.findByText('Nouveau message de motivation court.')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('limite de 250 caractères');
  });

  it('copies the short message independently from the cover letter', async () => {
    copyMock.mockResolvedValueOnce(undefined);
    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Copier le message' }));

    await waitFor(() => expect(copyMock).toHaveBeenCalledWith('Message court déjà préparé.'));
    expect(screen.getByRole('status')).toHaveTextContent('Message court copié.');
  });

  it('warns when an existing message exceeds the common 400-character limit', () => {
    render(<ReviewQueueApplicationCard application={application('x'.repeat(401))} />);

    expect(screen.getByText('401 caractères')).toBeInTheDocument();
    expect(screen.getByText(/Ce message dépasse 400 caractères/)).toBeInTheDocument();
  });
});
