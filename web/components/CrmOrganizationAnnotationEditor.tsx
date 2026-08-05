'use client';

import { FormEvent, useState } from 'react';

import { ErrorBox, PageHeader } from '@/components/UI';
import { getErrorMessage } from '@/lib/errors';
import type { CrmOrganization } from '@/lib/types';

export type CrmOrganizationAnnotationPayload = {
  displayName: string;
  note: string;
};

interface CrmOrganizationAnnotationEditorProps {
  organization: CrmOrganization;
  onSave: (payload: CrmOrganizationAnnotationPayload) => Promise<void>;
  onClose: () => void;
}

export function CrmOrganizationAnnotationEditor({
  organization,
  onSave,
  onClose,
}: CrmOrganizationAnnotationEditorProps) {
  const [displayName, setDisplayName] = useState(organization.annotation?.displayName ?? '');
  const [note, setNote] = useState(organization.annotation?.note ?? '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const submit = async (payload: CrmOrganizationAnnotationPayload): Promise<void> => {
    setSaving(true);
    setError('');

    try {
      await onSave(payload);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const save = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    await submit({ displayName, note });
  };

  const clear = async (): Promise<void> => {
    const confirmed = window.confirm(
      'Effacer le nom affiché et la note CRM ? Les données sources resteront intactes.',
    );
    if (!confirmed) return;

    setDisplayName('');
    setNote('');
    await submit({ displayName: '', note: '' });
  };

  const hasAnnotation = Boolean(
    organization.annotation?.displayName?.trim()
      || organization.annotation?.note?.trim(),
  );

  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <div
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-label={`Modifier la fiche CRM ${organization.name}`}
        onMouseDown={(event) => event.stopPropagation()}
      >
        <PageHeader
          title="Modifier la fiche CRM"
          description="Ajoute un nom d’affichage et une note locale sans modifier les offres, positionnements ou messages d’origine."
          actions={(
            <button className="btn secondary" type="button" disabled={saving} onClick={onClose}>
              Fermer
            </button>
          )}
        />

        {error !== '' && <ErrorBox message={error} />}

        <div className="notice" style={{ marginBottom: 16 }}>
          <strong>Nom détecté dans les données sources :</strong> {organization.sourceName}
          <div className="small muted" style={{ marginTop: 5 }}>
            La clé stable{' '}
            <code data-testid="crm-organization-key">{organization.key}</code>{' '}
            et le nom source ne seront pas modifiés.
          </div>
        </div>

        <form className="stack" onSubmit={(event) => void save(event)}>
          <div>
            <label htmlFor="crm-organization-display-name">Nom affiché dans le CRM</label>
            <input
              id="crm-organization-display-name"
              aria-label="Nom affiché dans le CRM"
              value={displayName}
              maxLength={255}
              placeholder={organization.sourceName}
              onChange={(event) => setDisplayName(event.target.value.replace(/[\r\n]/g, ' '))}
            />
            <span className="small muted">
              Laisse vide pour afficher le nom détecté automatiquement. {displayName.length}/255
            </span>
          </div>

          <div>
            <label htmlFor="crm-organization-note">Note interne</label>
            <textarea
              id="crm-organization-note"
              aria-label="Note interne"
              value={note}
              maxLength={5000}
              style={{ minHeight: 180 }}
              placeholder="Contexte utile, qualité de la relation, prochaine action…"
              onChange={(event) => setNote(event.target.value)}
            />
            <span className="small muted">
              Visible uniquement dans le CRM local. {note.length}/5000
            </span>
          </div>

          <div className="actions" style={{ justifyContent: 'space-between' }}>
            <button
              className="btn secondary"
              type="button"
              disabled={saving || !hasAnnotation}
              onClick={() => void clear()}
            >
              Effacer les corrections
            </button>
            <button className="btn" type="submit" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer la fiche CRM'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
