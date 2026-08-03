'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type Message = {
  id: number;
  sender: string;
  subject: string;
  snippet: string;
  receivedAt: string;
  category: string;
};

export default function MessagesPage() {
  const [items, setItems] = useState<Message[] | null>(null);
  const [connected, setConnected] = useState(false);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const [status, messages] = await Promise.all([
        api<{ connected: boolean }>('/integrations/gmail/status'),
        api<Message[]>('/integrations/gmail/messages'),
      ]);
      setConnected(status.connected);
      setItems(messages);
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const sync = async (): Promise<void> => {
    try {
      const result = await api<{ imported: number; found: number }>(
        '/integrations/gmail/sync',
        { method: 'POST' },
      );
      setInfo(`${result.imported} nouveau(x) message(s) importé(s) sur ${result.found} trouvé(s).`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  return (
    <>
      <PageHeader
        title="Messagerie"
        description="Alertes d’offres, réponses recruteurs, confirmations et entretiens."
        actions={connected ? (
          <button className="btn" type="button" onClick={() => void sync()}>
            Synchroniser Gmail
          </button>
        ) : (
          <a className="btn" href="/api/integrations/gmail/start">Connecter Gmail</a>
        )}
      />
      {info !== '' && <div className="notice">{info}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />
      <Card>
        {items === null ? (
          <Loading />
        ) : items.length === 0 ? (
          <Empty>
            {connected
              ? 'Aucun message importé. Lance une synchronisation.'
              : 'Connecte Gmail depuis les paramètres.'}
          </Empty>
        ) : (
          items.map((message) => (
            <div className="list-row" key={message.id}>
              <div>
                <div className="actions">
                  <Badge
                    tone={
                      message.category === 'INTERVIEW_REQUEST'
                        ? 'good'
                        : message.category === 'REJECTION'
                          ? 'bad'
                          : 'blue'
                    }
                  >
                    {message.category}
                  </Badge>
                  <span className="muted small">
                    {new Date(message.receivedAt).toLocaleString('fr-FR')}
                  </span>
                </div>
                <h3>{message.subject || '(sans objet)'}</h3>
                <div className="muted small">{message.sender}</div>
                <p className="small">{message.snippet}</p>
              </div>
            </div>
          ))
        )}
      </Card>
    </>
  );
}
