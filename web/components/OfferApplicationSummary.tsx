import { Badge } from '@/components/UI';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import type { Application } from '@/lib/types';

export function OfferApplicationSummary({ application }: { application: Application }) {
  const hasMessage = application.message.trim() !== '';
  const hasCoverLetter = application.coverLetter.trim() !== '';
  const hasCompensation = (application.compensationAnswer ?? '').trim() !== '';

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

      <details style={{ marginTop: 10 }}>
        <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>
          Examiner les éléments préparés
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
      </details>
    </section>
  );
}
