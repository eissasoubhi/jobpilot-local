import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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

  it('keeps the card compact and moves the short text into the motivation drawer', () => {
    render(<ReviewQueueApplicationCard application={application()} />);

    expect(screen.queryByText('Message court déjà préparé.')).not.toBeInTheDocument();
    expect(screen.getByText('Message court')).toBeInTheDocument();
    expect(screen.getByText('27 caractères')).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton', { name: 'Longueur maximale du message court' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Message court' }));

    const dialog = screen.getByRole('dialog', { name: 'Motivation' });
    expect(within(dialog).getByRole('tab', { name: 'Message court' })).toHaveAttribute('aria-selected', 'true');
    expect(within(dialog).getByText('Message court déjà préparé.')).toBeInTheDocument();
    expect(within(dialog).getByRole('spinbutton', { name: 'Longueur maximale du message court' })).toHaveValue(400);
  });

  it('regenerates the short message from the drawer with the chosen maximum length', async () => {
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

    fireEvent.click(screen.getByRole('button', { name: 'Message court' }));
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Longueur maximale du message court' }), {
      target: { value: '250' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Régénérer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/51/message/regenerate', {
      method: 'POST',
      body: JSON.stringify({ maxCharacters: 250, targetCompany: '' }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
    expect(await screen.findByText('Nouveau message de motivation court.')).toBeInTheDocument();
  });

  it('copies the short message from its drawer tab', async () => {
    copyMock.mockResolvedValueOnce(undefined);
    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Message court' }));
    fireEvent.click(screen.getByRole('button', { name: 'Copier' }));

    await waitFor(() => expect(copyMock).toHaveBeenCalledWith('Message court déjà préparé.'));
    expect(screen.getByText('Message court copié.')).toBeInTheDocument();
  });

  it('shows an over-400 warning inside the short-message tab instead of expanding the card', () => {
    render(<ReviewQueueApplicationCard application={application('x'.repeat(401))} />);

    expect(screen.getByText('401 caractères · à réduire')).toBeInTheDocument();
    expect(screen.queryByText(/Ce message dépasse 400 caractères/)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Message court' }));
    expect(screen.getByText(/Ce message dépasse 400 caractères/)).toBeInTheDocument();
  });
});
