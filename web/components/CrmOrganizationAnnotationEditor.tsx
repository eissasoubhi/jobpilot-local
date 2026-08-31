'use client';

import { FormEvent, useState } from 'react';

import { Modal } from '@/components/Modal';
import { Button, ErrorBox, FormField, PageHeader } from '@/components/UI';
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
    <Modal ariaLabel={`Modifier la fiche CRM ${organization.name}`} onClose={onClose}>
      <PageHeader
        title="Modifier la fiche CRM"
        description="Ajoute un nom d’affichage et une note locale sans modifier les offres, positionnements ou messages d’origine."
        actions={(
          <Button variant="secondary" disabled={saving} onClick={onClose}>
            Fermer
          </Button>
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
        <FormField
          label="Nom affiché dans le CRM"
          hint={<>Laisse vide pour afficher le nom détecté automatiquement. {displayName.length}/255</>}
        >
          <input
            id="crm-organization-display-name"
            value={displayName}
            maxLength={255}
            placeholder={organization.sourceName}
            onChange={(event) => setDisplayName(event.target.value.replace(/[\r\n]/g, ' '))}
          />
        </FormField>

        <FormField
          label="Note interne"
          hint={<>Visible uniquement dans le CRM local. {note.length}/5000</>}
        >
          <textarea
            id="crm-organization-note"
            value={note}
            maxLength={5000}
            style={{ minHeight: 180 }}
            placeholder="Contexte utile, qualité de la relation, prochaine action…"
            onChange={(event) => setNote(event.target.value)}
          />
        </FormField>

        <div className="actions" style={{ justifyContent: 'space-between' }}>
          <Button
            variant="secondary"
            disabled={saving || !hasAnnotation}
            onClick={() => void clear()}
          >
            Effacer les corrections
          </Button>
          <Button type="submit" loading={saving}>
            {saving ? 'Enregistrement…' : 'Enregistrer la fiche CRM'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
