import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import type { JobProfileComparison } from '@/components/ReviewQueueTechnologyComparison';
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

function applicationWithComparison(): Application {
  const item = application() as Application & { profileComparison?: JobProfileComparison };
  item.profileComparison = {
    source: 'AI_REUSED',
    aiDecision: 'MATCH',
    aiConfidence: 94,
    technologies: ['React', 'Next.js', 'TypeScript', 'Kubernetes'],
    primaryTechnologies: ['React', 'TypeScript'],
    secondaryTechnologies: ['Next.js', 'Kubernetes'],
    matchingTechnologies: ['React', 'Next.js', 'TypeScript'],
    missingTechnologies: ['Kubernetes'],
    missingMustHaves: ['Kubernetes'],
    missingNiceToHaves: [],
  };

  return item;
}

describe('ReviewQueueApplicationCard', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('keeps mission context and secondary actions in the card while primary decisions live in the bottom bar', () => {
    render(<ReviewQueueApplicationCard application={applicationWithComparison()} />);

    expect(screen.getByRole('article', { name: /Développeur Front-end React/ })).toBeInTheDocument();
    expect(screen.getByText('Description de la mission')).toBeInTheDocument();
    expect(screen.getByText(/Mission React, TypeScript et Next.js/)).toBeInTheDocument();
    expect(screen.getByText('CDI')).toBeInTheDocument();
    expect(screen.getByText('Contrat : CDI')).toBeInTheDocument();
    expect(screen.getByText('88%')).toBeInTheDocument();
    expect(screen.getByText('Pourquoi ce score ?')).toBeInTheDocument();
    expect(screen.getByText('React correspond à un poste cible.')).toBeInTheDocument();
    expect(screen.getByText('Adéquation technique')).toBeInTheDocument();
    expect(screen.getByText('Analyse IA réutilisée')).toBeInTheDocument();
    expect(screen.getByText('MATCH · 94%')).toBeInTheDocument();
    expect(screen.getByText('En commun avec mon profil')).toBeInTheDocument();
    expect(screen.getByText('Manques obligatoires')).toBeInTheDocument();
    expect(screen.getAllByText('React').length).toBeGreaterThan(0);
    expect(screen.getAllByText('TypeScript').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Kubernetes').length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: 'Ne correspond pas' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'J’ai envoyé' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Envoyée' })).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Appliquer' })).toBeDisabled();
    expect(screen.getByRole('link', { name: 'Ouvrir la plateforme' })).toHaveAttribute('href', 'https://example.test/jobs/7');
    expect(screen.getByRole('link', { name: 'Ouvrir le CV' })).toHaveAttribute('href', '/api/cvs/3/download');
    expect(screen.queryByText('Ce message préparé ne doit pas occuper la Review Queue.')).not.toBeInTheDocument();
  });

  it('keeps a selected status local until Apply is clicked', async () => {
    const interview = application({ status: 'INTERVIEW' });
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(interview);

    render(
      <ReviewQueueApplicationCard
        application={application()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    expect(screen.getByText('PRÊTE À ENVOYER')).toBeInTheDocument();
    fireEvent.change(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' }), {
      target: { value: 'INTERVIEW' },
    });

    expect(apiMock).not.toHaveBeenCalled();
    expect(screen.getByText('PRÊTE À ENVOYER')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Appliquer' })).toBeEnabled();

    fireEvent.click(screen.getByRole('button', { name: 'Appliquer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'INTERVIEW',
        message: 'Ce message préparé ne doit pas occuper la Review Queue.',
        coverLetter: 'Lettre de motivation préparée.',
        compensationAnswer: '55 000 € brut annuel',
      }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(interview);
    expect(await screen.findByRole('status')).toHaveTextContent('Statut de suivi enregistré dans JobPilot.');
  });
});
