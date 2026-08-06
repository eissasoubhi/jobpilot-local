import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import {
  CrmContactCorrectionEditor,
  type EditableCrmContact,
} from '@/components/CrmContactCorrectionEditor';
import type { CrmOrganization } from '@/lib/types';

const organization: CrmOrganization = {
  key: 'acme', name: 'Acme', sourceName: 'ACME', roles: ['COMPANY'],
  offerCount: 0, applicationCount: 0, positioningCount: 0, messageCount: 0,
  contactCount: 1, applicationStatuses: {}, positioningStatuses: {}, contacts: [], latestOffers: [],
};

const contact: EditableCrmContact = {
  key: 'contact-1', name: 'Corrected Name', email: 'corrected@example.com', phone: '+33102030405',
  sourceName: 'Source Name', sourceEmail: 'source@example.com', sourcePhone: '+33100000000',
  roles: ['RECRUITER'], messageCount: 0,
};

describe('CrmContactCorrectionEditor', () => {
  it('shows source values and submits trimmed corrections', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(<CrmContactCorrectionEditor organization={organization} contact={contact} onClose={vi.fn()} onSave={onSave} />);

    expect(screen.getByText(/Source Name/)).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Nom'), { target: { value: '  New Name  ' } });
    fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: ' new@example.com ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(onSave).toHaveBeenCalledWith({
      name: 'New Name', email: 'new@example.com', phone: '+33102030405',
    }));
  });

  it('can submit empty values to clear the local correction', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(<CrmContactCorrectionEditor organization={organization} contact={contact} onClose={vi.fn()} onSave={onSave} />);

    fireEvent.change(screen.getByLabelText('Nom'), { target: { value: '' } });
    fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: '' } });
    fireEvent.change(screen.getByLabelText('Téléphone'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(onSave).toHaveBeenCalledWith({ name: '', email: '', phone: '' }));
  });
});
