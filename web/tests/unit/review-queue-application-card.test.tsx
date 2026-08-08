import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

function application(overrides: Partial<Application> = {}): Application {
  return {
    id: 42,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Ce message préparé ne doit pas occuper la Review Queue.',
    coverLetter: 'Lettre de motivation préparée.',
    compensationAnswer: '55 000 € brut annuel',
    updatedAt: '2026-08-08T00:00:00+02:00',
    cvDocument: {
      id: 3,
      name: 'CV React FR',
      originalName: 'cv.pdf',
      language: 'fr',
      category: 'Frontend',
      tags: ['React'],
      active: true,
      defaultForLanguage: true,
      size: 1234,
      downloadUrl: '/api/cvs/3/download',
    },
    jobOffer: {
      id: 7,
      source: 'France Travail',
      sourceUrl: 'https://example.test/jobs/7',
      title: 'Développeur Front-end React / TypeScript',
      company: 'Example',
      sources: [],
      sourceCount: 1,
      location: 'Paris 9e',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Mission React, TypeScript et Next.js avec conception d’interfaces accessibles.',
      score: 88,
      scoreReasons: ['React correspond à un poste cible.', 'TypeScript correspond aux compétences configurées.'],
      status: 'PREPARED',
    },
    ...overrides,
  };
}

describe('ReviewQueueApplicationCard', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows mission, contract, actions and score immediately without the prepared message body', () => {
    render(<ReviewQueueApplicationCard application={application()} />);

    expect(screen.getByRole('article', { name: /Développeur Front-end React/ })).toBeInTheDocument();
    expect(screen.getByText('Description de la mission')).toBeInTheDocument();
    expect(screen.getByText(/Mission React, TypeScript et Next.js/)).toBeInTheDocument();
    expect(screen.getByText('CDI')).toBeInTheDocument();
    expect(screen.getByText('Contrat : CDI')).toBeInTheDocument();
    expect(screen.getByText('88%')).toBeInTheDocument();
    expect(screen.getByText('Pourquoi ce score ?')).toBeInTheDocument();
    expect(screen.getByText('React correspond à un poste cible.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ne correspond pas à mon profil' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ouvrir la plateforme' })).toHaveAttribute('href', 'https://example.test/jobs/7');
    expect(screen.getByRole('link', { name: 'Ouvrir le CV' })).toHaveAttribute('href', '/api/cvs/3/download');
    expect(screen.queryByText('Ce message préparé ne doit pas occuper la Review Queue.')).not.toBeInTheDocument();
  });

  it('marks a non-matching offer locally and notifies the queue', async () => {
    const ignored = application({ status: 'IGNORED_NOT_MATCH' });
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(ignored);

    render(
      <ReviewQueueApplicationCard
        application={application()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Ne correspond pas à mon profil' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'IGNORED_NOT_MATCH',
        message: 'Ce message préparé ne doit pas occuper la Review Queue.',
        coverLetter: 'Lettre de motivation préparée.',
        compensationAnswer: '55 000 € brut annuel',
      }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(ignored);
  });

  it('persists a selected tracking status from the inline action bar', async () => {
    const interview = application({ status: 'INTERVIEW' });
    apiMock.mockResolvedValueOnce(interview);

    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.change(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' }), {
      target: { value: 'INTERVIEW' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le statut' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'INTERVIEW',
        message: 'Ce message préparé ne doit pas occuper la Review Queue.',
        coverLetter: 'Lettre de motivation préparée.',
        compensationAnswer: '55 000 € brut annuel',
      }),
    }));
    expect(await screen.findByRole('status')).toHaveTextContent('Statut de suivi enregistré dans JobPilot.');
  });
});
