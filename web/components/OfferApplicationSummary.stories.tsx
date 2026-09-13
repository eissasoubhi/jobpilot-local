import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import type { Application } from '@/lib/types';

function application(overrides: Partial<Application> = {}): Application {
  return {
    id: 41,
    status: 'READY_TO_SUBMIT',
    channel: 'Manuel',
    message: 'Bonjour, je souhaite vous proposer ma candidature pour ce poste.',
    coverLetter: 'Cette opportunité correspond à mon expérience Symfony et React.',
    compensationAnswer: '55 k€ brut annuel',
    updatedAt: '2026-09-13T04:30:00Z',
    jobOffer: {
      id: 41,
      source: 'Storybook',
      sourceCode: 'storybook',
      sourceUrl: 'https://example.com/jobs/41',
      title: 'Senior Full-Stack Symfony / React',
      company: 'Entreprise exemple',
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description:
        'Mission de démonstration avec une description suffisamment longue pour vérifier la hiérarchie de lecture et le comportement du panneau d’examen.',
      score: 88,
      scoreReasons: [
        'Symfony et React correspondent au profil.',
        'Le mode hybride est compatible avec les préférences.',
      ],
      status: 'ACTIVE',
    },
    ...overrides,
  };
}

const meta = {
  title: 'Offres/Offer application summary',
  component: OfferApplicationSummary,
  parameters: {
    layout: 'padded',
    docs: {
      description: {
        component:
          'Résumé de candidature préparée : rend le prochain choix évident, garde les éléments préparés vérifiables et distingue clairement le suivi local JobPilot d’un envoi externe.',
      },
    },
  },
  args: {
    application: application(),
    onApplicationUpdated: () => undefined,
  },
} satisfies Meta<typeof OfferApplicationSummary>;

export default meta;
type Story = StoryObj<typeof meta>;

export const ReadyToSubmit: Story = {};

export const PreparationIncomplete: Story = {
  args: {
    application: application({
      id: 42,
      message: '',
      coverLetter: '',
      compensationAnswer: '',
      jobOffer: {
        ...application().jobOffer,
        id: 42,
        sourceUrl: '',
        title: 'Développeur Symfony',
        score: 74,
        scoreReasons: [],
      },
    }),
  },
};

export const AlreadySubmitted: Story = {
  args: {
    application: application({
      id: 43,
      status: 'SUBMITTED',
      confirmationRef: 'REF-2026-0913',
      jobOffer: {
        ...application().jobOffer,
        id: 43,
        title: 'Software Engineer PHP / React',
        score: 91,
      },
    }),
  },
};
