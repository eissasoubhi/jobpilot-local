import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import type { JobProfileComparison } from '@/components/ReviewQueueTechnologyComparison';
import type { Application } from '@/lib/types';

const { apiMock, copyMock } = vi.hoisted(() => ({
  apiMock: vi.fn(),
  copyMock: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: apiMock,
}));

function application(overrides: Partial<Application> = {}): Application {
  return {
    id: 42,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Ce message préparé est disponible dans la Review Queue.',
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

function withCoverLetterState(
  item: Application,
  manuallyEdited: boolean,
  editedAt: string | null,
): Application {
  const editable = item as Application & {
    coverLetterManuallyEdited?: boolean;
    coverLetterEditedAt?: string | null;
  };
  editable.coverLetterManuallyEdited = manuallyEdited;
  editable.coverLetterEditedAt = editedAt;

  return editable;
}

describe('ReviewQueueApplicationCard', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    apiMock.mockReset();
    copyMock.mockReset();
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: copyMock },
    });
  });

  it('prioritizes the decision while exposing compact application readiness', () => {
    render(<ReviewQueueApplicationCard application={applicationWithComparison()} />);

    expect(screen.getByRole('article', { name: /Développeur Front-end React/ })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Développeur Front-end React / TypeScript', level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Example')).toBeInTheDocument();
    expect(screen.getByText('Paris 9e')).toBeInTheDocument();
    expect(screen.getByText('Hybride')).toBeInTheDocument();
    expect(screen.getByText('Résumé de la mission')).toBeInTheDocument();
    expect(screen.getByText(/Mission React, TypeScript et Next.js/)).toBeInTheDocument();
    expect(screen.getByText('CDI')).toBeInTheDocument();
    expect(screen.getByText('88%')).toBeInTheDocument();
    expect(screen.getByText('Pourquoi cette note ?')).toBeInTheDocument();
    expect(screen.getByText('React correspond à un poste cible.')).toBeInTheDocument();
    expect(screen.getByText('Compatibilité technique')).toBeInTheDocument();
    expect(screen.getByText('Analyse existante')).toBeInTheDocument();
    expect(screen.getByText('Correspondance · 94%')).toBeInTheDocument();
    expect(screen.getByText('Correspondances')).toBeInTheDocument();
    expect(screen.getByText('Manques bloquants')).toBeInTheDocument();
    expect(screen.getByText('Aucun point d’attention')).toBeInTheDocument();
    expect(screen.getAllByText('React').length).toBeGreaterThan(0);
    expect(screen.getAllByText('TypeScript').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Kubernetes').length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: 'Ne correspond pas' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'J’ai envoyé' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Envoyée' })).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled();
    expect(screen.getByRole('link', { name: 'Ouvrir l’offre' })).toHaveAttribute('href', 'https://example.test/jobs/7');
    expect(screen.getByRole('link', { name: 'Ouvrir le CV' })).toHaveAttribute('href', '/api/cvs/3/download');
    expect(screen.queryByText('Ce message préparé est disponible dans la Review Queue.')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Candidature prête' })).toBeInTheDocument();
    expect(screen.getByText('Message court')).toBeInTheDocument();
    expect(screen.getByText('Lettre de motivation')).toBeInTheDocument();
    expect(screen.getByText('Prête · 4 mots')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Voir les textes' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Message court' })).toBeInTheDocument();
    expect(screen.queryByText('Lettre de motivation préparée.')).not.toBeInTheDocument();
    expect(screen.queryByText('PDF')).not.toBeInTheDocument();
    expect(screen.queryByText('Word (.docx)')).not.toBeInTheDocument();
    expect(screen.getAllByText('Prête à envoyer')).toHaveLength(2);
    expect(screen.queryByText('PRÊTE À ENVOYER')).not.toBeInTheDocument();
  });

  it('opens the motivation drawer without replacing the mission context', () => {
    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Voir les textes' }));

    const dialog = screen.getByRole('dialog', { name: 'Motivation' });
    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByRole('tab', { name: 'Lettre de motivation' })).toHaveAttribute('aria-selected', 'true');
    expect(within(dialog).getByText('Lettre de motivation préparée.')).toBeInTheDocument();
    expect(within(dialog).getByText(/mots · .*caractères/)).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'PDF' })).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Word (.docx)' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Résumé de la mission' })).toBeInTheDocument();

    fireEvent.keyDown(dialog, { key: 'Escape' });
    expect(screen.queryByRole('dialog', { name: 'Motivation' })).not.toBeInTheDocument();
  });

  it('keeps a long mission compact until the user expands it', () => {
    const item = application();
    item.jobOffer.description = Array.from(
      { length: 24 },
      (_, index) => `Ligne ${index + 1} de la mission Symfony React TypeScript.`,
    ).join('\n');

    render(<ReviewQueueApplicationCard application={item} />);

    const expand = screen.getByRole('button', { name: 'Voir le détail' });
    expect(expand).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByText(/Ligne 24 de la mission/)).not.toBeInTheDocument();

    fireEvent.click(expand);
    expect(screen.getByRole('button', { name: 'Réduire' })).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByText(/Ligne 24 de la mission/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réduire' }));
    expect(screen.getByRole('button', { name: 'Voir le détail' })).toHaveAttribute('aria-expanded', 'false');
  });

  it('warns when a ready application has a weak match score without duplicating the tracking status', () => {
    const item = application();
    item.jobOffer.score = 40;

    render(<ReviewQueueApplicationCard application={item} />);

    expect(screen.getByText('À examiner')).toBeInTheDocument();
    expect(screen.getByText('Match faible · 40%')).toBeInTheDocument();
    expect(screen.getByText(/Correspondance faible/)).toBeInTheDocument();
    expect(screen.queryByText('PRÊTE À ENVOYER')).not.toBeInTheDocument();
  });

  it('keeps a selected status local until Save is clicked', async () => {
    const interview = application({ status: 'INTERVIEW' });
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(interview);

    render(
      <ReviewQueueApplicationCard
        application={application()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.change(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' }), {
      target: { value: 'INTERVIEW' },
    });

    expect(apiMock).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeEnabled();

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'INTERVIEW',
        message: 'Ce message préparé est disponible dans la Review Queue.',
        coverLetter: 'Lettre de motivation préparée.',
        compensationAnswer: '55 000 € brut annuel',
      }),
    }));
    expect(onApplicationUpdated).toHaveBeenCalledWith(interview);
    expect(await screen.findByRole('status')).toHaveTextContent('Statut de suivi enregistré.');
  });

  it('edits and saves a cover letter from the drawer without changing the application status', async () => {
    const updated = withCoverLetterState(
      application({ coverLetter: 'Lettre personnalisée.' }),
      true,
      '2026-08-11T03:00:00+00:00',
    );
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(updated);

    render(
      <ReviewQueueApplicationCard
        application={application()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Voir les textes' }));
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    const editor = screen.getByRole('textbox', { name: 'Texte de la lettre' });
    expect(editor).toHaveValue('Lettre de motivation préparée.');
    fireEvent.change(editor, { target: { value: 'Lettre personnalisée.' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42/cover-letter', {
      method: 'PATCH',
      body: JSON.stringify({ coverLetter: 'Lettre personnalisée.' }),
    }));
    expect(updated.status).toBe('READY_TO_SUBMIT');
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
    expect(await screen.findByText('Lettre personnalisée.')).toBeInTheDocument();
    expect(screen.getByText(/Modifiée manuellement/)).toBeInTheDocument();
    expect(screen.getByText('Lettre de motivation enregistrée.')).toBeInTheDocument();
  });

  it('cancels an unsaved cover letter edit in the drawer', () => {
    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Voir les textes' }));
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    const editor = screen.getByRole('textbox', { name: 'Texte de la lettre' });
    fireEvent.change(editor, { target: { value: 'Brouillon non sauvegardé.' } });
    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

    expect(screen.queryByRole('textbox', { name: 'Texte de la lettre' })).not.toBeInTheDocument();
    expect(screen.getByText('Lettre de motivation préparée.')).toBeInTheDocument();
    expect(apiMock).not.toHaveBeenCalled();
  });

  it('copies the saved cover letter from the motivation drawer', async () => {
    copyMock.mockResolvedValueOnce(undefined);
    render(<ReviewQueueApplicationCard application={application()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Voir les textes' }));
    fireEvent.click(screen.getByRole('button', { name: 'Copier' }));

    await waitFor(() => expect(copyMock).toHaveBeenCalledWith('Lettre de motivation préparée.'));
    expect(screen.getByText('Lettre de motivation copiée.')).toBeInTheDocument();
  });

  it('resets a manual cover letter to the latest generated version from the drawer', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const manual = withCoverLetterState(
      application({ coverLetter: 'Texte manuel.' }),
      true,
      '2026-08-11T03:00:00+00:00',
    );
    const reset = withCoverLetterState(
      application({ coverLetter: 'Dernière version générée.' }),
      false,
      null,
    );
    apiMock.mockResolvedValueOnce(reset);

    render(<ReviewQueueApplicationCard application={manual} />);
    fireEvent.click(screen.getByRole('button', { name: 'Voir les textes' }));
    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/applications/42/cover-letter/reset', {
      method: 'POST',
    }));
    expect(await screen.findByText('Dernière version générée.')).toBeInTheDocument();
    expect(screen.getByText('Générée automatiquement par JobPilot')).toBeInTheDocument();
  });
});