'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

export default function ApplicationsPage() {
  const [items, setItems] = useState<Application[] | null>(null);
  const [selected, setSelected] = useState<Application | null>(null);
  const [error, setError] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      setItems(await api<Application[]>('/applications'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (): Promise<void> => {
    if (selected === null) return;

    try {
      const updated = await api<Application>(`/applications/${selected.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status: selected.status,
          message: selected.message,
          coverLetter: selected.coverLetter,
          compensationAnswer: selected.compensationAnswer,
          confirmationRef: selected.confirmationRef,
        }),
      });
      setSelected(updated);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  return (
    <>
      <PageHeader
        title="Candidatures"
        description="Relis, ajuste puis confirme l’envoi depuis la plateforme concernée."
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {items === null ? (
          <Loading />
        ) : items.length === 0 ? (
          <Empty>Aucune candidature préparée.</Empty>
        ) : (
          items.map((application) => (
            <div className="list-row" key={application.id}>
              <div>
                <h3>{application.jobOffer.title}</h3>
                <div className="muted small">
                  {application.jobOffer.company || 'Entreprise non renseignée'} · Score{' '}
                  {application.jobOffer.score} · {application.jobOffer.language.toUpperCase()}
                </div>
                <div className="actions" style={{ marginTop: 8 }}>
                  <Badge tone={application.status === 'SUBMITTED' ? 'good' : 'blue'}>
                    {application.status}
                  </Badge>
                  {application.cvDocument && <Badge>{application.cvDocument.name}</Badge>}
                  {application.compensationAnswer && (
                    <Badge tone="good">{application.compensationAnswer}</Badge>
                  )}
                </div>
              </div>
              <button
                className="btn secondary small"
                type="button"
                onClick={() => setSelected(application)}
              >
                Ouvrir
              </button>
            </div>
          ))
        )}
      </Card>

      {selected && (
        <div className="modal-backdrop" onMouseDown={() => setSelected(null)}>
          <div
            className="modal"
            role="dialog"
            aria-modal="true"
            aria-label={`Candidature ${selected.jobOffer.title}`}
            onMouseDown={(event) => event.stopPropagation()}
          >
            <PageHeader
              title={selected.jobOffer.title}
              description={selected.jobOffer.company}
              actions={
                <button className="btn secondary" type="button" onClick={() => setSelected(null)}>
                  Fermer
                </button>
              }
            />
            <div className="stack">
              <label>
                Statut
                <select
                  value={selected.status}
                  onChange={(event) => setSelected({ ...selected, status: event.target.value })}
                >
                  <option value="READY_TO_SUBMIT">Prête à envoyer</option>
                  <option value="SUBMITTED">Envoyée</option>
                  <option value="RECRUITER_REPLIED">Réponse recruteur</option>
                  <option value="INTERVIEW">Entretien</option>
                  <option value="REJECTED">Refusée</option>
                  <option value="OFFER_RECEIVED">Offre reçue</option>
                </select>
              </label>
              <label>
                Message
                <textarea
                  value={selected.message}
                  onChange={(event) => setSelected({ ...selected, message: event.target.value })}
                />
              </label>
              <label>
                Lettre de motivation
                <textarea
                  style={{ minHeight: 200 }}
                  value={selected.coverLetter}
                  onChange={(event) => setSelected({ ...selected, coverLetter: event.target.value })}
                />
              </label>
              <label>
                Réponse rémunération
                <input
                  value={selected.compensationAnswer ?? ''}
                  onChange={(event) =>
                    setSelected({ ...selected, compensationAnswer: event.target.value })
                  }
                />
              </label>
              <label>
                Confirmation / référence
                <input
                  value={selected.confirmationRef ?? ''}
                  onChange={(event) =>
                    setSelected({ ...selected, confirmationRef: event.target.value })
                  }
                />
              </label>
              <button className="btn" type="button" onClick={() => void save()}>
                Enregistrer
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
