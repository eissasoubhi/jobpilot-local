'use client';

import { useId, useState } from 'react';

import { Button, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type Props = {
  messageId: number;
  sender: string;
  category: string;
  onSaved: () => Promise<void> | void;
};

export function InboxSenderClassificationCorrection({ messageId, sender, category, onSaved }: Props) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const hintId = useId();

  if (category !== 'RECRUITER_OPPORTUNITY' && category !== 'UNKNOWN') return null;

  const save = async (): Promise<void> => {
    if (busy) return;
    setBusy(true);
    setError('');

    try {
      await api(`/integrations/gmail/messages/${messageId}/sender-classification`, {
        method: 'PUT',
        body: JSON.stringify({ category: 'JOB_ALERT' }),
      });
      await onSaved();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <Button
        variant="secondary"
        size="small"
        loading={busy}
        onClick={() => void save()}
        title={`Toujours classer ${sender} comme alerte emploi`}
        aria-describedby={hintId}
      >
        {busy ? 'Enregistrement…' : 'Ce n’est pas un recruteur'}
      </Button>
      <div id={hintId} className="muted small" style={{ marginTop: 6 }}>
        Cette correction sera réutilisée pour les prochains messages de cet expéditeur.
      </div>
      {error !== '' && (
        <div style={{ marginTop: 6 }}>
          <ErrorBox message={error} />
        </div>
      )}
    </div>
  );
}
