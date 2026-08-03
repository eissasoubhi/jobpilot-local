'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function companyName(application: Application): string {
  return application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';
}

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
        description="Vérifie l’offre concernée, relis la candidature, puis confirme l’envoi depuis la plateforme d’origine."
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
              <div style={{ flex: 1 }}>
                <h3>{application.jobOffer.title}</h3>
                <div className="muted small">
                  {companyName(application)} · {application.jobOffer.location || 'Lieu non renseigné'} ·{' '}
                  {application.jobOffer.contractType || 'Contrat non renseigné'}
                </div>
                <div className="actions" style={{ marginTop: 8 }}>
                  <Badge tone={application.status === 'SUBMITTED' ? 'good' : 'blue'}>
                    {application.status}
                  </Badge>
                  <Badge tone="blue">Score {application.jobOffer.score}</Badge>
                  <Badge>{application.jobOffer.language.toUpperCase()}</Badge>
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
                Examiner la candidature
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
              title="Candidature préparée"
              description="Contrôle d’abord l’offre concernée avant de modifier ou confirmer la candidature."
              actions={
                <button className="btn secondary" type="button" onClick={() => setSelected(null)}>
                  Fermer
                </button>
              }
            />

            <section className="card" aria-labelledby="application-job-title">
              <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                  <div className="small muted" style={{ fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.04em' }}>
                    Offre concernée
                  </div>
                  <h2 id="application-job-title" style={{ margin: '7px 0 5px', fontSize: 21 }}>
                    {selected.jobOffer.title}
                  </h2>
                  <div>
                    <strong>{companyName(selected)}</strong>
                    {selected.jobOffer.clientName && selected.jobOffer.clientName !== selected.jobOffer.company && (
                      <span className="muted"> · Client final : {selected.jobOffer.clientName}</span>
                    )}
                  </div>
                </div>
                <div className="score" aria-label={`Score ${selected.jobOffer.score}`}>
                  {selected.jobOffer.score}
                </div>
              </div>

              <div className="actions" style={{ marginTop: 13 }}>
                <Badge>{selected.jobOffer.location || 'Lieu non renseigné'}</Badge>
                <Badge>{selected.jobOffer.contractType || 'Contrat non renseigné'}</Badge>
                <Badge>{selected.jobOffer.workMode || 'Mode de travail non renseigné'}</Badge>
                <Badge tone="blue">Source : {selected.jobOffer.source || 'inconnue'}</Badge>
                <Badge>{selected.jobOffer.language.toUpperCase()}</Badge>
              </div>

              <div className="actions" style={{ marginTop: 14 }}>
                {selected.jobOffer.sourceUrl ? (
                  <a
                    className="btn secondary small"
                    href={selected.jobOffer.sourceUrl}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Ouvrir l’offre originale
                  </a>
                ) : (
                  <span className="small muted">Aucun lien vers l’annonce originale n’est disponible.</span>
                )}
                {selected.cvDocument && (
                  <a
                    className="btn secondary small"
                    href={selected.cvDocument.downloadUrl}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Ouvrir le CV sélectionné
                  </a>
                )}
              </div>

              <details style={{ marginTop: 15 }}>
                <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>
                  Afficher la description complète de l’offre
                </summary>
                <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 12 }}>
                  {selected.jobOffer.description || 'Description non disponible.'}
                </div>
              </details>
            </section>

            <div className="notice warning" style={{ marginTop: 14 }}>
              Vérifie le poste, l’entreprise, la rémunération et le CV avant de marquer la candidature comme envoyée.
            </div>

            <div className="stack" style={{ marginTop: 16 }}>
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
                Enregistrer les modifications
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
