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

  it('offers an exclusive, accessible quick-filter radio group', () => {
    const onChange = vi.fn();
    render(
      <ApplicationStatusFilter
        applications={applications}
        value="ALL"
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('radiogroup', { name: 'Filtres rapides des candidatures' })).toBeInTheDocument();

    const all = screen.getByRole('radio', { name: 'Toutes les candidatures (4)' });
    const ready = screen.getByRole('radio', { name: 'Prêtes à envoyer (2)' });
    const submitted = screen.getByRole('radio', { name: 'Envoyées (1)' });

    expect(all).toHaveAttribute('aria-checked', 'true');
    expect(all).toHaveAttribute('tabindex', '0');
    expect(ready).toHaveAttribute('aria-checked', 'false');
    expect(ready).toHaveAttribute('tabindex', '-1');
    expect(submitted).toHaveAttribute('aria-checked', 'false');
    expect(screen.getByText('4 candidature(s) affichée(s) sur 4.')).toBeInTheDocument();

    fireEvent.click(ready);
    expect(onChange).toHaveBeenCalledWith('READY_TO_SUBMIT');

    fireEvent.click(submitted);
    expect(onChange).toHaveBeenCalledWith('SUBMITTED');
  });

  it('supports radio-group arrow navigation without adding extra tab stops', () => {
    const onChange = vi.fn();
    render(
      <ApplicationStatusFilter
        applications={applications}
        value="ALL"
        onChange={onChange}
      />,
    );

    const all = screen.getByRole('radio', { name: 'Toutes les candidatures (4)' });
    const ready = screen.getByRole('radio', { name: 'Prêtes à envoyer (2)' });
    const submitted = screen.getByRole('radio', { name: 'Envoyées (1)' });

    all.focus();
    fireEvent.keyDown(all, { key: 'ArrowDown' });
    expect(onChange).toHaveBeenLastCalledWith('READY_TO_SUBMIT');
    expect(ready).toHaveFocus();

    fireEvent.keyDown(ready, { key: 'End' });
    expect(onChange).toHaveBeenLastCalledWith('SUBMITTED');
    expect(submitted).toHaveFocus();
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
