import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/ReviewQueueApplicationCard', () => ({
  ReviewQueueApplicationCard: ({
    application,
    onApplicationUpdated,
  }: {
    application: Application;
    onApplicationUpdated?: (application: Application) => void;
  }) => (
    <div>
      <div>Carte complète {application.jobOffer.title}</div>
      <input aria-label="Édition locale" />
      <button
        type="button"
        onClick={() => onApplicationUpdated?.({ ...application, status: 'INTERVIEW' })}
      >
        Décider {application.jobOffer.title}
      </button>
    </div>
  ),
}));

function application(id: number, title: string, status: string): Application {
  return {
    id,
    channel: 'Préparation locale',
    status,
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-08T00:00:00+02:00',
    jobOffer: {
      id,
      source: 'Test',
      title,
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Description',
      score: 80,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

describe('ReviewQueuePage', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows only ready-to-submit applications in the slider', async () => {
    apiMock.mockResolvedValueOnce([
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Already sent role', 'SUBMITTED'),
      application(3, 'Interview role', 'INTERVIEW'),
      application(4, 'Rejected role', 'REJECTED'),
      application(5, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ]);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());
    expect(screen.getByText('2 prêtes à envoyer')).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Navigation dans la Review Queue' })).toHaveClass('review-queue-slider');
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.queryByText('Already sent role')).not.toBeInTheDocument();
    expect(screen.queryByText('Interview role')).not.toBeInTheDocument();
    expect(screen.queryByText('Rejected role')).not.toBeInTheDocument();
    expect(screen.queryByText('Carte complète Second Symfony role')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Suivante' }));

    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('2 / 2')).toBeInTheDocument();
  });

  it('navigates with left and right arrow keys outside interactive controls', async () => {
    apiMock.mockResolvedValueOnce([
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ]);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());

    fireEvent.keyDown(window, { key: 'ArrowRight' });
    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();

    fireEvent.keyDown(window, { key: 'ArrowLeft' });
    expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument();

    const input = screen.getByRole('textbox', { name: 'Édition locale' });
    input.focus();
    fireEvent.keyDown(input, { key: 'ArrowRight' });
    expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument();
  });

  it('automatically advances when a saved status leaves ready-to-submit', async () => {
    apiMock.mockResolvedValueOnce([
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
      application(3, 'Third Symfony role', 'READY_TO_SUBMIT'),
    ]);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Décider First Symfony role' }));

    expect(screen.queryByText('Carte complète First Symfony role')).not.toBeInTheDocument();
    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
  });

  it('wraps to the first remaining ready offer after deciding the last queue item', async () => {
    apiMock.mockResolvedValueOnce([
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
      application(3, 'Third Symfony role', 'READY_TO_SUBMIT'),
    ]);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Suivante' }));
    fireEvent.click(screen.getByRole('button', { name: 'Suivante' }));
    expect(screen.getByText('Carte complète Third Symfony role')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Décider Third Symfony role' }));

    expect(screen.queryByText('Carte complète Third Symfony role')).not.toBeInTheDocument();
    expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
  });
});
