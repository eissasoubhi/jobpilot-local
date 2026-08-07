'use client';

import { useEffect, useState } from 'react';

import { Badge } from '@/components/UI';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import type { Application } from '@/lib/types';

export function OfferApplicationSummary({ application }: { application: Application }) {
  const [reviewOpen, setReviewOpen] = useState(false);
  const hasMessage = application.message.trim() !== '';
  const hasCoverLetter = application.coverLetter.trim() !== '';
  const hasCompensation = (application.compensationAnswer ?? '').trim() !== '';

  useEffect(() => {
    if (!reviewOpen) return undefined;

    const closeOnEscape = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') setReviewOpen(false);
    };

    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [reviewOpen]);

  return (
    <section
      className="notice"
      aria-label={`Candidature préparée pour ${application.jobOffer.title}`}
      style={{ marginTop: 12 }}
    >
      <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <strong>Candidature</strong>
        <Badge tone={applicationStatusTone(application.status)}>
          {applicationBadgeLabel(application)}
        </Badge>
      </div>

      <div className="actions" style={{ marginTop: 8 }}>
        {application.cvDocument && <Badge tone="good">CV prêt</Badge>}
        {hasMessage && <Badge tone="good">Message prêt</Badge>}
        {hasCoverLetter && <Badge tone="good">Lettre prête</Badge>}
        {hasCompensation && <Badge tone="good">Rémunération prête</Badge>}
      </div>

      <div className="actions" style={{ marginTop: 10 }}>
        <button className="btn secondary small" type="button" onClick={() => setReviewOpen(true)}>
          Examiner
        </button>
        {application.jobOffer.sourceUrl && (
          <a
            className="btn small"
            href={application.jobOffer.sourceUrl}
            target="_blank"
            rel="noreferrer"
          >
            Ouvrir la plateforme pour postuler
          </a>
        )}
      </div>

      <details style={{ marginTop: 10 }}>
        <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>
          Aperçu rapide des éléments préparés
        </summary>

        <div className="stack" style={{ gap: 12, marginTop: 12 }}>
          {application.cvDocument && (
            <div>
              <div className="small muted">CV sélectionné</div>
              <div className="actions" style={{ marginTop: 5 }}>
                <strong className="small">{application.cvDocument.name}</strong>
                <a
                  className="btn secondary small"
                  href={application.cvDocument.downloadUrl}
                  target="_blank"
                  rel="noreferrer"
                >
                  Ouvrir le CV
                </a>
              </div>
            </div>
          )}

          {hasMessage && (
            <div>
              <div className="small muted">Message préparé</div>
              <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.55, marginTop: 5 }}>
                {application.message}
              </div>
            </div>
          )}

          {hasCoverLetter && (
            <div>
              <div className="small muted">Lettre de motivation demandée</div>
              <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.55, marginTop: 5 }}>
                {application.coverLetter}
              </div>
            </div>
          )}

          {hasCompensation && (
            <div>
              <div className="small muted">Réponse rémunération</div>
              <strong className="small">{application.compensationAnswer}</strong>
            </div>
          )}
        </div>
      </details>

      {reviewOpen && (
        <>
          <button
            type="button"
            aria-label="Fermer l’examen de l’offre"
            onClick={() => setReviewOpen(false)}
            style={{
              position: 'fixed',
              inset: 0,
              zIndex: 40,
              border: 0,
              background: 'rgba(15, 23, 42, 0.38)',
              cursor: 'default',
            }}
          />
          <aside
            role="dialog"
            aria-modal="true"
            aria-labelledby={`offer-review-title-${application.id}`}
            style={{
              position: 'fixed',
              top: 0,
              right: 0,
              bottom: 0,
              zIndex: 50,
              width: 'min(680px, 94vw)',
              overflowY: 'auto',
              background: 'var(--surface, #fff)',
              boxShadow: '-16px 0 40px rgba(15, 23, 42, 0.18)',
              padding: 24,
            }}
          >
            <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <div>
                <div className="small muted">Examen de l’offre</div>
                <h2 id={`offer-review-title-${application.id}`} style={{ marginTop: 4 }}>
                  {application.jobOffer.title}
                </h2>
                <div className="small muted">
                  {application.jobOffer.company || 'Entreprise non renseignée'} · {application.jobOffer.location || 'Lieu non renseigné'} · {application.jobOffer.workMode || 'Mode non renseigné'}
                </div>
              </div>
              <button className="btn secondary small" type="button" onClick={() => setReviewOpen(false)}>
                Fermer
              </button>
            </div>

            <div className="stack" style={{ gap: 18, marginTop: 22 }}>
              <section>
                <div className="actions">
                  <Badge tone="blue">Score : {application.jobOffer.score} %</Badge>
                  <Badge>{application.jobOffer.contractType || 'Contrat inconnu'}</Badge>
                  <Badge tone={applicationStatusTone(application.status)}>{applicationBadgeLabel(application)}</Badge>
                </div>
              </section>

              <section>
                <strong>Description</strong>
                <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 7 }}>
                  {application.jobOffer.description || 'Description non disponible.'}
                </div>
              </section>

              <section>
                <strong>Pourquoi ce score ?</strong>
                {application.jobOffer.scoreReasons.length > 0 ? (
                  <ul style={{ marginBottom: 0 }}>
                    {application.jobOffer.scoreReasons.map((reason) => <li className="small" key={reason}>{reason}</li>)}
                  </ul>
                ) : (
                  <div className="small muted" style={{ marginTop: 7 }}>Aucune explication détaillée disponible.</div>
                )}
              </section>

              {application.cvDocument && (
                <section>
                  <strong>CV sélectionné</strong>
                  <div className="actions" style={{ marginTop: 7 }}>
                    <span className="small">{application.cvDocument.name}</span>
                    <a className="btn secondary small" href={application.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
                      Ouvrir le CV
                    </a>
                  </div>
                </section>
              )}

              {hasMessage && (
                <section>
                  <strong>Message préparé</strong>
                  <div className="notice small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 7 }}>
                    {application.message}
                  </div>
                </section>
              )}

              {hasCoverLetter && (
                <section>
                  <strong>Lettre de motivation demandée</strong>
                  <div className="notice small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 7 }}>
                    {application.coverLetter}
                  </div>
                </section>
              )}

              {hasCompensation && (
                <section>
                  <strong>Rémunération</strong>
                  <div className="small" style={{ marginTop: 7 }}>{application.compensationAnswer}</div>
                </section>
              )}

              {application.jobOffer.sourceUrl && (
                <section className="actions">
                  <a className="btn" href={application.jobOffer.sourceUrl} target="_blank" rel="noreferrer">
                    Ouvrir la plateforme pour postuler
                  </a>
                </section>
              )}
            </div>
          </aside>
        </>
      )}
    </section>
  );
}
