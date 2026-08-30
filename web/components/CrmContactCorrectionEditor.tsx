'use client';

import { useState } from 'react';

import { Modal } from '@/components/Modal';
import { Button, ErrorBox, FormField } from '@/components/UI';
import { getErrorMessage } from '@/lib/errors';
import type { CrmContact, CrmOrganization } from '@/lib/types';

export type CrmContactCorrectionPayload = { name: string; email: string; phone: string };
export type EditableCrmContact = CrmContact & {
  sourceName?: string | null;
  sourceEmail?: string | null;
  sourcePhone?: string | null;
  correction?: {
    correctedName?: string | null;
    correctedEmail?: string | null;
    correctedPhone?: string | null;
    updatedAt?: string | null;
  } | null;
};

type Props = {
  organization: CrmOrganization;
  contact: EditableCrmContact;
  onClose: () => void;
  onSave: (payload: CrmContactCorrectionPayload) => Promise<void>;
};

export function CrmContactCorrectionEditor({ organization, contact, onClose, onSave }: Props) {
  const [name, setName] = useState(contact.name ?? '');
  const [email, setEmail] = useState(contact.email ?? '');
  const [phone, setPhone] = useState(contact.phone ?? '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const submit = async (): Promise<void> => {
    setSaving(true);
    setError('');
    try {
      await onSave({ name: name.trim(), email: email.trim(), phone: phone.trim() });
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal ariaLabel={`Corriger le contact CRM ${contact.name}`} onClose={onClose}>
      <h2>Corriger un contact CRM</h2>
      <p className="small muted">Organisation : <strong>{organization.name}</strong><br />Clé stable : <code>{contact.key}</code></p>
      <div className="notice">Les valeurs sources restent intactes. Laisser les trois champs vides efface uniquement la correction locale.</div>
      {error !== '' && <ErrorBox message={error} />}
      <div className="stack" style={{ marginTop: 14 }}>
        <FormField label="Nom">
          <input value={name} disabled={saving} onChange={(event) => setName(event.target.value)} />
        </FormField>
        <FormField label="E-mail">
          <input type="email" value={email} disabled={saving} onChange={(event) => setEmail(event.target.value)} />
        </FormField>
        <FormField label="Téléphone">
          <input value={phone} disabled={saving} onChange={(event) => setPhone(event.target.value)} />
        </FormField>
        <div className="small muted">Sources : {contact.sourceName || '—'} · {contact.sourceEmail || '—'} · {contact.sourcePhone || '—'}</div>
        <div className="actions">
          <Button size="small" loading={saving} onClick={() => void submit()}>
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
          <Button variant="secondary" size="small" disabled={saving} onClick={onClose}>Annuler</Button>
        </div>
      </div>
    </Modal>
  );
}
