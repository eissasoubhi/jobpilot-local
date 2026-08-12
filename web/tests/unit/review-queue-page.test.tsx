import type { Ref } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application, Job } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/ReviewQueueApplicationCard', () => ({
  ReviewQueueApplicationCard: ({
    application,
    headingRef,
    onApplicationUpdated,
  }: {
    application: Application;
    headingRef?: Ref<HTMLHeadingElement>;
    onApplicationUpdated?: (application: Application) => void;
  }) => (
    <div>
      <h2 ref={headingRef} tabIndex={-1}>Carte complète {application.jobOffer.title}</h2>
      <input aria-label="Édition locale" />
      <button
        type="button"
        onClick={() => onApplicationUpdated?.({ ...application, status: 'INTERVIEW' })}
      >
        Changer le statut {application.jobOffer.title}
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

function jobsInOrder(applications: Application[], order?: number[]): Job[] {
  const byId = new Map(applications.map((item) => [item.jobOffer.id, item.jobOffer]));
  const ids = order ?? applications.map((item) => item.jobOffer.id);

  return ids.flatMap((id) => {
    const job = byId.get(id);
    return job ? [job] : [];
  });
}

function mockInitialLoad(applications: Application[], order?: number[]): void {
  apiMock
    .mockResolvedValueOnce(applications)
    .mockResolvedValueOnce(jobsInOrder(applications, order));
}

describe('ReviewQueuePage', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows only ready-to-submit applications in the exact relative order of the Offers page', async () => {
    const applications = [
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Already sent role', 'SUBMITTED'),
      application(3, 'Interview role', 'INTERVIEW'),
      application(4, 'Rejected role', 'REJECTED'),
      application(5, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ];
    mockInitialLoad(applications, [5, 2, 1, 3, 4]);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument());
    expect(screen.getByText('2 prêtes à envoyer')).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Décision et navigation dans la Review Queue' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ne correspond pas' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Envoyée' })).toBeInTheDocument();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Offre 1 sur 2 : Second Symfony role');
    expect(screen.queryByText('Already sent role')).not.toBeInTheDocument();
    expect(screen.queryByText('Interview role')).not.toBeInTheDocument();
    expect(screen.queryByText('Rejected role')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Suivante' }));

    expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument();
    expect(screen.getByText('2 / 2')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Carte complète First Symfony role' })).toHaveFocus());
    expect(screen.getByRole('status')).toHaveTextContent('Offre 2 sur 2 : First Symfony role');
  });

  it('marks the current application as submitted, advances and focuses the next offer', async () => {
    const applications = [
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ];
    mockInitialLoad(applications);
    apiMock.mockResolvedValueOnce(application(1, 'First Symfony role', 'SUBMITTED'));

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Envoyée' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/applications/1', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'SUBMITTED',
        message: '',
        coverLetter: '',
      }),
    }));
    expect(screen.queryByText('Carte complète First Symfony role')).not.toBeInTheDocument();
    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('1 / 1')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Carte complète Second Symfony role' })).toHaveFocus());
    expect(screen.getByRole('status')).toHaveTextContent('Offre 1 sur 1 : Second Symfony role');
  });

  it('marks the current application as not matching and immediately advances to the next ready item', async () => {
    const applications = [
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ];
    mockInitialLoad(applications);
    apiMock.mockResolvedValueOnce(application(1, 'First Symfony role', 'IGNORED_NOT_MATCH'));

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Ne correspond pas' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/applications/1', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'IGNORED_NOT_MATCH',
        message: '',
        coverLetter: '',
      }),
    }));
    expect(screen.queryByText('Carte complète First Symfony role')).not.toBeInTheDocument();
    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('1 / 1')).toBeInTheDocument();
  });

  it('navigates with left and right arrow keys outside interactive controls and moves focus with the offer', async () => {
    const applications = [
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ];
    mockInitialLoad(applications);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());

    fireEvent.keyDown(window, { key: 'ArrowRight' });
    await waitFor(() => expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Carte complète Second Symfony role' })).toHaveFocus());

    fireEvent.keyDown(window, { key: 'ArrowLeft' });
    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Carte complète First Symfony role' })).toHaveFocus());

    const input = screen.getByRole('textbox', { name: 'Édition locale' });
    input.focus();
    fireEvent.keyDown(input, { key: 'ArrowRight' });
    expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument();
    expect(input).toHaveFocus();
  });

  it('removes an application from the ready queue after another persisted status change', async () => {
    const applications = [
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Second Symfony role', 'READY_TO_SUBMIT'),
      application(3, 'Third Symfony role', 'READY_TO_SUBMIT'),
    ];
    mockInitialLoad(applications);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète First Symfony role')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Changer le statut First Symfony role' }));

    expect(screen.queryByText('Carte complète First Symfony role')).not.toBeInTheDocument();
    expect(screen.getByText('Carte complète Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Carte complète Second Symfony role' })).toHaveFocus());
  });
});
