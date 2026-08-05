import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { CrmOrganizationAnnotationEditor } from '@/components/CrmOrganizationAnnotationEditor';
import type { CrmOrganization } from '@/lib/types';

const organization: CrmOrganization = {
  key: 'acme consulting',
  name: 'ACME Consulting France',
  sourceName: 'Acme Consulting',
  annotation: {
    displayName: 'ACME Consulting France',
    note: 'Relancer dans une semaine.',
    updatedAt: '2026-08-05T12:00:00+00:00',
  },
  roles: ['AGENCY', 'COMPANY'],
  offerCount: 1,
  applicationCount: 1,
  positioningCount: 1,
  messageCount: 1,
  contactCount: 0,
  applicationStatuses: { INTERVIEW: 1 },
  positioningStatuses: { AGREEMENT_GIVEN: 1 },
  lastActivityAt: '2026-08-05T10:00:00+00:00',
  contacts: [],
  latestOffers: [],
};

afterEach(() => {
  vi.restoreAllMocks();
});

describe('CrmOrganizationAnnotationEditor', () => {
  it('prefills the saved annotation and preserves the generated source identity', () => {
    render(
      <CrmOrganizationAnnotationEditor
        organization={organization}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByText('Acme Consulting')).toBeInTheDocument();
    expect(screen.getByTestId('crm-organization-key')).toHaveTextContent('acme consulting');
    expect(screen.getByLabelText('Nom affiché dans le CRM')).toHaveValue('ACME Consulting France');
    expect(screen.getByLabelText('Note interne')).toHaveValue('Relancer dans une semaine.');
  });

  it('submits the edited display name and note separately from source data', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <CrmOrganizationAnnotationEditor
        organization={organization}
        onSave={onSave}
        onClose={vi.fn()}
      />,
    );

    fireEvent.change(screen.getByLabelText('Nom affiché dans le CRM'), {
      target: { value: 'ACME France' },
    });
    fireEvent.change(screen.getByLabelText('Note interne'), {
      target: { value: 'Contact fiable pour Symfony.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer la fiche CRM' }));

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith({
        displayName: 'ACME France',
        note: 'Contact fiable pour Symfony.',
      });
    });
  });

  it('clears both fields only after explicit confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <CrmOrganizationAnnotationEditor
        organization={organization}
        onSave={onSave}
        onClose={vi.fn()}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Effacer les corrections' }));

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith({ displayName: '', note: '' });
    });
    expect(screen.getByLabelText('Nom affiché dans le CRM')).toHaveValue('');
    expect(screen.getByLabelText('Note interne')).toHaveValue('');
  });

  it('keeps the editor open and exposes a failed save', async () => {
    const onSave = vi.fn().mockRejectedValue(new Error('Enregistrement impossible.'));
    render(
      <CrmOrganizationAnnotationEditor
        organization={organization}
        onSave={onSave}
        onClose={vi.fn()}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer la fiche CRM' }));

    expect(await screen.findByText('Enregistrement impossible.')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('disables clearing when no annotation exists', () => {
    render(
      <CrmOrganizationAnnotationEditor
        organization={{
          ...organization,
          name: 'Acme Consulting',
          annotation: null,
        }}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: 'Effacer les corrections' })).toBeDisabled();
  });
});
