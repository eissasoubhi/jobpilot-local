'use client';

import { useState } from 'react';

import { ErrorBox } from '@/components/UI';
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
    <div className="modal-backdrop" role="presentation">
      <section className="modal" role="dialog" aria-modal="true" aria-labelledby="crm-contact-editor-title">
        <h2 id="crm-contact-editor-title">Corriger un contact CRM</h2>
        <p className="small muted">Organisation : <strong>{organization.name}</strong><br />Clé stable : <code>{contact.key}</code></p>
        <div className="notice">Les valeurs sources restent intactes. Laisser les trois champs vides efface uniquement la correction locale.</div>
        {error !== '' && <ErrorBox message={error} />}
        <div className="stack" style={{ marginTop: 14 }}>
          <label>Nom<input value={name} disabled={saving} onChange={(event) => setName(event.target.value)} /></label>
          <label>E-mail<input type="email" value={email} disabled={saving} onChange={(event) => setEmail(event.target.value)} /></label>
          <label>Téléphone<input value={phone} disabled={saving} onChange={(event) => setPhone(event.target.value)} /></label>
          <div className="small muted">Sources : {contact.sourceName || '—'} · {contact.sourceEmail || '—'} · {contact.sourcePhone || '—'}</div>
          <div className="actions">
            <button className="btn small" type="button" disabled={saving} onClick={() => void submit()}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
            <button className="btn secondary small" type="button" disabled={saving} onClick={onClose}>Annuler</button>
          </div>
        </div>
      </section>
    </div>
  );
}
