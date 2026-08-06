import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ApplicationStatusFilter } from '@/components/ApplicationStatusFilter';
import type { Application } from '@/lib/types';

function application(id: number, status: string): Application {
  return {
    id,
    channel: 'Préparation locale',
    status,
    message: 'Message',
    coverLetter: '',
    updatedAt: '2026-08-06T12:00:00+02:00',
    jobOffer: {
      id,
      source: 'Test',
      title: `Poste ${id}`,
      company: `Entreprise ${id}`,
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Description',
      score: 80,
      scoreReasons: [],
      status: 'ELIGIBLE',
    },
  };
}

describe('ApplicationStatusFilter', () => {
  const applications = [
    application(1, 'READY_TO_SUBMIT'),
    application(2, 'READY_TO_SUBMIT'),
    application(3, 'SUBMITTED'),
    application(4, 'REJECTED'),
  ];

  it('offers quick filters for all, prepared and submitted applications', () => {
    const onChange = vi.fn();
    render(
      <ApplicationStatusFilter
        applications={applications}
        value="ALL"
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Toutes les candidatures (4)' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Prêtes à envoyer (2)' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Envoyées (1)' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByText('4 candidature(s) affichée(s) sur 4.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Prêtes à envoyer (2)' }));
    expect(onChange).toHaveBeenCalledWith('READY_TO_SUBMIT');

    fireEvent.click(screen.getByRole('button', { name: 'Envoyées (1)' }));
    expect(onChange).toHaveBeenCalledWith('SUBMITTED');
  });

  it('allows every tracking status to be selected from the dropdown', () => {
    const onChange = vi.fn();
    render(
      <ApplicationStatusFilter
        applications={applications}
        value="SUBMITTED"
        onChange={onChange}
      />,
    );

    const select = screen.getByLabelText('Filtrer les candidatures par statut');
    expect(select).toHaveValue('SUBMITTED');
    expect(screen.getByRole('option', { name: 'Refusées (1)' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Entretiens (0)' })).toBeInTheDocument();
    expect(screen.getByText('1 candidature(s) affichée(s) sur 4.')).toBeInTheDocument();

    fireEvent.change(select, { target: { value: 'REJECTED' } });
    expect(onChange).toHaveBeenCalledWith('REJECTED');
  });

  it('keeps a selected custom status visible after its last application disappears', () => {
    render(
      <ApplicationStatusFilter
        applications={applications}
        value="CUSTOM_REVIEW"
        onChange={vi.fn()}
      />,
    );

    expect(screen.getByLabelText('Filtrer les candidatures par statut')).toHaveValue('CUSTOM_REVIEW');
    expect(screen.getByRole('option', { name: 'Custom review (0)' })).toBeInTheDocument();
    expect(screen.getByText('0 candidature(s) affichée(s) sur 4.')).toBeInTheDocument();
  });
});
