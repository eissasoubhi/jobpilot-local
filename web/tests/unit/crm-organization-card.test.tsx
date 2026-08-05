import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { CrmOrganizationCard } from '@/components/CrmOrganizationCard';
import type { CrmOrganization } from '@/lib/types';

const organization: CrmOrganization = {
  key: 'acme consulting',
  name: 'Acme Consulting',
  sourceName: 'Acme Consulting',
  annotation: null,
  roles: ['AGENCY', 'COMPANY'],
  offerCount: 2,
  applicationCount: 1,
  positioningCount: 1,
  messageCount: 3,
  contactCount: 1,
  applicationStatuses: { INTERVIEW: 1 },
  positioningStatuses: { AGREEMENT_GIVEN: 1 },
  lastActivityAt: '2026-08-05T10:00:00+00:00',
  contacts: [{
    key: 'jane@acme.test',
    name: 'Jane Recruiter',
    email: 'jane@acme.test',
    phone: '+33 6 00 00 00 00',
    roles: ['INBOX_CONTACT', 'RECRUITER'],
    messageCount: 2,
    lastContactAt: '2026-08-05T09:00:00+00:00',
  }],
  latestOffers: [{
    id: 42,
    title: 'Senior Symfony Developer',
    status: 'READY_TO_SUBMIT',
    score: 88,
    sourceUrl: 'https://example.test/jobs/42',
  }],
};

describe('CrmOrganizationCard', () => {
  it('shows organization roles, workflow counts and validated contact links', () => {
    render(<CrmOrganizationCard organization={organization} />);

    expect(screen.getByRole('heading', { name: 'Acme Consulting' })).toBeInTheDocument();
    expect(screen.getByText('Intermédiaire')).toBeInTheDocument();
    expect(screen.getByText('Entreprise')).toBeInTheDocument();
    expect(screen.getByText('2 offres')).toBeInTheDocument();
    expect(screen.getByText('1 candidature')).toBeInTheDocument();
    expect(screen.getByText('3 messages')).toBeInTheDocument();
    expect(screen.getByText('Entretien · 1')).toBeInTheDocument();
    expect(screen.getByText('Accord donné · 1')).toBeInTheDocument();

    const contacts = screen.getByRole('heading', { name: 'Contacts validés' }).closest('section');
    expect(contacts).not.toBeNull();
    expect(within(contacts as HTMLElement).getByText('Jane Recruiter')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'jane@acme.test' })).toHaveAttribute('href', 'mailto:jane@acme.test');
    expect(screen.getByRole('link', { name: '+33 6 00 00 00 00' })).toHaveAttribute('href', 'tel:+33 6 00 00 00 00');
    expect(screen.getByText('Recruteur')).toBeInTheDocument();
    expect(screen.getByText('Correspondant Gmail')).toBeInTheDocument();

    expect(screen.getByRole('link', { name: 'Ouvrir l’offre' })).toHaveAttribute('href', 'https://example.test/jobs/42');
    expect(screen.getByText('Score 88')).toBeInTheDocument();
  });

  it('shows corrected names and notes while preserving the source name', () => {
    const onEdit = vi.fn();
    const annotatedOrganization: CrmOrganization = {
      ...organization,
      name: 'ACME Consulting France',
      sourceName: 'Acme Consulting',
      annotation: {
        displayName: 'ACME Consulting France',
        note: 'Contact prioritaire pour les missions Symfony.',
        updatedAt: '2026-08-05T12:00:00+00:00',
      },
    };

    render(
      <CrmOrganizationCard
        organization={annotatedOrganization}
        onEditAnnotation={onEdit}
      />,
    );

    expect(screen.getByRole('heading', { name: 'ACME Consulting France' })).toBeInTheDocument();
    expect(screen.getByText('Nom source : Acme Consulting')).toBeInTheDocument();
    expect(screen.getByText('Nom corrigé')).toBeInTheDocument();
    expect(screen.getAllByText('Note CRM')).toHaveLength(2);
    expect(screen.getByText('Contact prioritaire pour les missions Symfony.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Modifier la fiche CRM' }));
    expect(onEdit).toHaveBeenCalledWith(annotatedOrganization);
  });

  it('offers a note action without pretending a source correction already exists', () => {
    const onEdit = vi.fn();
    render(<CrmOrganizationCard organization={organization} onEditAnnotation={onEdit} />);

    expect(screen.queryByText('Nom corrigé')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter une note CRM' }));
    expect(onEdit).toHaveBeenCalledWith(organization);
  });

  it('states clearly when an organization has no validated contact', () => {
    render(
      <CrmOrganizationCard
        organization={{
          ...organization,
          key: 'final client',
          name: 'Final Client',
          sourceName: 'Final Client',
          roles: ['CLIENT'],
          contactCount: 0,
          contacts: [],
        }}
      />,
    );

    expect(screen.getByText('Client final')).toBeInTheDocument();
    expect(screen.getByText('Aucun contact validé pour cette organisation.')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /@/ })).not.toBeInTheDocument();
  });
});
