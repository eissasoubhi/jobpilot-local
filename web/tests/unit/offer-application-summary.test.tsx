import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import type { Application } from '@/lib/types';

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
    expect(dialog).toHaveTextContent('TJM proposé : 500 €');

    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('does not claim that optional material is ready when it is empty', () => {
    render(<OfferApplicationSummary application={application({ coverLetter: '', compensationAnswer: '' })} />);

    expect(screen.queryByText('Lettre prête')).not.toBeInTheDocument();
    expect(screen.queryByText('Rémunération prête')).not.toBeInTheDocument();
  });
});
