import { render, screen } from '@testing-library/react';
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
      description: 'Description',
      score: 82,
      scoreReasons: [],
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

  it('does not claim that optional material is ready when it is empty', () => {
    render(<OfferApplicationSummary application={application({ coverLetter: '', compensationAnswer: '' })} />);

    expect(screen.queryByText('Lettre prête')).not.toBeInTheDocument();
    expect(screen.queryByText('Rémunération prête')).not.toBeInTheDocument();
  });
});
