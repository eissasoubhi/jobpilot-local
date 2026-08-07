import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({
  apiMock: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: apiMock,
}));

function application(overrides: Partial<Application> = {}): Application {
  return {
    id: 42,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Bonjour, je suis intéressé par cette mission Symfony.',
    coverLetter: 'Lettre adaptée à la mission.',
    compensationAnswer: 'TJM proposé : 500 €',
    updatedAt: '2026-08-07T00:00:00+02:00',
    cvDocument: {
      id: 3,
      name: 'CV Symfony FR',
      originalName: 'cv.pdf',
      language: 'fr',
      category: 'Backend',
      tags: ['Symfony'],
      active: true,
      defaultForLanguage: true,
      size: 1234,
      downloadUrl: '/api/cvs/3/download',
    },
    jobOffer: {
      id: 7,
      source: 'Test',
      sourceUrl: 'https://example.test/jobs/7',
      title: 'Senior Symfony Developer',
      company: 'Example',
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'Freelance',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Mission Symfony avec API Platform et Docker.',
      score: 82,
      scoreReasons: ['Symfony correspond au profil.', 'Docker est demandé.'],
      status: 'PREPARED',
    },
    ...overrides,
  };
}

describe('OfferApplicationSummary', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows the prepared application material directly on the offer workspace', () => {
    render(<OfferApplicationSummary application={application()} />);

    expect(screen.getByText('PRÊTE À ENVOYER')).toBeInTheDocument();
    expect(screen.getByText('CV prêt')).toBeInTheDocument();
    expect(screen.getByText('Message prêt')).toBeInTheDocument();
    expect(screen.getByText('Lettre prête')).toBeInTheDocument();
    expect(screen.getByText('Rémunération prête')).toBeInTheDocument();
    expect(screen.getByText(/je suis intéressé par cette mission Symfony/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ouvrir le CV' })).toHaveAttribute('href', '/api/cvs/3/download');
    expect(screen.getByRole('link', { name: 'Ouvrir la plateforme pour postuler' })).toHaveAttribute(
      'href',
      'https://example.test/jobs/7',
    );
  });

  it('opens a review drawer with offer and preparation context without navigating away', () => {
    render(<OfferApplicationSummary application={application()} />);

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Examiner' }));

    const dialog = screen.getByRole('dialog', { name: 'Senior Symfony Developer' });
    expect(dialog).toBeInTheDocument();
    expect(dialog).toHaveTextContent('Mission Symfony avec API Platform et Docker.');
    expect(dialog).toHaveTextContent('Score : 82 %');
    expect(dialog).toHaveTextContent('Symfony correspond au profil.');
    expect(dialog).toHaveTextContent('CV Symfony FR');
    expect(screen.getByRole('textbox', { name: 'Réponse rémunération' })).toHaveValue('TJM proposé : 500 €');

    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('edits and saves prepared application material from the review drawer', async () => {
    const updated = application({
      message: 'Message raccourci.',
      coverLetter: 'Lettre mise à jour.',
      compensationAnswer: 'TJM proposé : 510 €',
      confirmationRef: 'REF-123',
    });
    apiMock.mockResolvedValueOnce(updated);

    render(<OfferApplicationSummary application={application()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Examiner' }));

    fireEvent.change(screen.getByRole('textbox', { name: 'Message préparé' }), {
      target: { value: 'Message raccourci.' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: 'Lettre de motivation demandée' }), {
      target: { value: 'Lettre mise à jour.' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: 'Réponse rémunération' }), {
      target: { value: 'TJM proposé : 510 €' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: 'Confirmation / référence après envoi' }), {
      target: { value: 'REF-123' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les modifications' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledTimes(1));
    expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'READY_TO_SUBMIT',
        message: 'Message raccourci.',
        coverLetter: 'Lettre mise à jour.',
        compensationAnswer: 'TJM proposé : 510 €',
        confirmationRef: 'REF-123',
      }),
    });
    expect(await screen.findByRole('status')).toHaveTextContent('Modifications enregistrées dans JobPilot.');
    expect(screen.getByRole('textbox', { name: 'Message préparé' })).toHaveValue('Message raccourci.');
  });

  it('updates the manual tracking status from the review drawer', async () => {
    const updated = application({ status: 'INTERVIEW' });
    apiMock.mockResolvedValueOnce(updated);

    render(<OfferApplicationSummary application={application()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Examiner' }));

    fireEvent.change(screen.getByRole('combobox', { name: 'Statut de suivi dans JobPilot' }), {
      target: { value: 'INTERVIEW' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le statut' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledTimes(1));
    expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'INTERVIEW',
        message: 'Bonjour, je suis intéressé par cette mission Symfony.',
        coverLetter: 'Lettre adaptée à la mission.',
        compensationAnswer: 'TJM proposé : 500 €',
      }),
    });
    expect(await screen.findByRole('status')).toHaveTextContent('Statut de suivi enregistré dans JobPilot.');
    expect(screen.getAllByText('ENTRETIEN')).toHaveLength(2);
  });

  it('marks the application as submitted from the review drawer without external submission', async () => {
    const submitted = application({ status: 'SUBMITTED' });
    apiMock.mockResolvedValueOnce(submitted);

    render(<OfferApplicationSummary application={application()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Examiner' }));
    fireEvent.click(screen.getByRole('button', { name: 'J’ai envoyé la candidature' }));

    await waitFor(() => {
      expect(apiMock).toHaveBeenCalledTimes(1);
    });

    expect(apiMock).toHaveBeenCalledWith('/applications/42', {
      method: 'PATCH',
      body: JSON.stringify({
        status: 'SUBMITTED',
        message: 'Bonjour, je suis intéressé par cette mission Symfony.',
        coverLetter: 'Lettre adaptée à la mission.',
        compensationAnswer: 'TJM proposé : 500 €',
      }),
    });
    expect(await screen.findByRole('status')).toHaveTextContent('Candidature marquée comme envoyée');
    expect(screen.getByRole('button', { name: 'Candidature déjà marquée comme envoyée' })).toBeDisabled();
    expect(screen.getAllByRole('link', { name: 'Ouvrir la plateforme pour postuler' })).toHaveLength(2);
    for (const link of screen.getAllByRole('link', { name: 'Ouvrir la plateforme pour postuler' })) {
      expect(link).toHaveAttribute('href', 'https://example.test/jobs/7');
    }
  });

  it('does not claim that optional material is ready when it is empty', () => {
    render(<OfferApplicationSummary application={application({ coverLetter: '', compensationAnswer: '' })} />);

    expect(screen.queryByText('Lettre prête')).not.toBeInTheDocument();
    expect(screen.queryByText('Rémunération prête')).not.toBeInTheDocument();
  });
});
