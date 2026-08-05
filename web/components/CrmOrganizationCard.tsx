import { Badge, Card } from '@/components/UI';
import {
  crmContactRoleLabel,
  crmOrganizationRoleLabel,
} from '@/lib/crm';
import type { CrmOrganization } from '@/lib/types';

function formatDate(value?: string | null): string {
  if (!value) return 'Aucune activité datée';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Date inconnue';

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    READY_TO_SUBMIT: 'Prête à envoyer',
    SUBMITTED: 'Envoyée',
    APPLICATION_CONFIRMED: 'Confirmée',
    RESPONSE_RECEIVED: 'Réponse reçue',
    INFORMATION_REQUESTED: 'Informations demandées',
    INTERVIEW: 'Entretien',
    REJECTED: 'Refusée',
    OFFER_RECEIVED: 'Offre reçue',
    AGREEMENT_GIVEN: 'Accord donné',
    MISSION_DETECTED: 'Mission détectée',
  };

  return labels[status] ?? status.replaceAll('_', ' ').toLocaleLowerCase('fr');
}

function statusEntries(statuses: Record<string, number>): Array<[string, number]> {
  return Object.entries(statuses).sort((left, right) => right[1] - left[1]);
}

interface CrmOrganizationCardProps {
  organization: CrmOrganization;
  onEditAnnotation?: (organization: CrmOrganization) => void;
}

export function CrmOrganizationCard({
  organization,
  onEditAnnotation,
}: CrmOrganizationCardProps) {
  const hasCorrectedName = organization.name !== organization.sourceName;
  const note = organization.annotation?.note?.trim() ?? '';

  return (
    <Card>
      <article aria-labelledby={`crm-organization-${organization.key}`}>
        <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h2 id={`crm-organization-${organization.key}`} style={{ marginBottom: 7 }}>
              {organization.name}
            </h2>
            {hasCorrectedName && (
              <div className="small muted" style={{ marginBottom: 7 }}>
                Nom source : {organization.sourceName}
              </div>
            )}
            <div className="actions">
              {organization.roles.map((role) => (
                <Badge key={role} tone="blue">{crmOrganizationRoleLabel(role)}</Badge>
              ))}
              {hasCorrectedName && <Badge tone="warn">Nom corrigé</Badge>}
              {note !== '' && <Badge tone="good">Note CRM</Badge>}
            </div>
          </div>
          <div className="small muted" style={{ textAlign: 'right' }}>
            Dernière activité<br />
            <strong>{formatDate(organization.lastActivityAt)}</strong>
          </div>
        </div>

        {note !== '' && (
          <div className="notice" style={{ marginTop: 14, whiteSpace: 'pre-wrap' }}>
            <strong>Note CRM</strong>
            <div style={{ marginTop: 5 }}>{note}</div>
            {organization.annotation?.updatedAt && (
              <div className="small muted" style={{ marginTop: 7 }}>
                Mise à jour : {formatDate(organization.annotation.updatedAt)}
              </div>
            )}
          </div>
        )}

        {onEditAnnotation && (
          <div className="actions" style={{ marginTop: 12 }}>
            <button
              className="btn secondary small"
              type="button"
              onClick={() => onEditAnnotation(organization)}
            >
              {organization.annotation ? 'Modifier la fiche CRM' : 'Ajouter une note CRM'}
            </button>
          </div>
        )}

        <div className="actions" style={{ marginTop: 14 }}>
          <Badge>{organization.offerCount} offre{organization.offerCount > 1 ? 's' : ''}</Badge>
          <Badge>{organization.applicationCount} candidature{organization.applicationCount > 1 ? 's' : ''}</Badge>
          <Badge>{organization.positioningCount} positionnement{organization.positioningCount > 1 ? 's' : ''}</Badge>
          <Badge>{organization.messageCount} message{organization.messageCount > 1 ? 's' : ''}</Badge>
          <Badge tone={organization.contactCount > 0 ? 'good' : 'neutral'}>
            {organization.contactCount} contact{organization.contactCount > 1 ? 's' : ''}
          </Badge>
        </div>

        <div className="grid two" style={{ marginTop: 18 }}>
          <section aria-labelledby={`crm-contacts-${organization.key}`}>
            <h3 id={`crm-contacts-${organization.key}`}>Contacts validés</h3>
            {organization.contacts.length === 0 ? (
              <div className="notice" style={{ marginTop: 9 }}>
                Aucun contact validé pour cette organisation.
              </div>
            ) : (
              <div className="stack" style={{ marginTop: 9 }}>
                {organization.contacts.map((contact) => (
                  <div className="list-row" key={contact.key} style={{ paddingTop: 9, paddingBottom: 9 }}>
                    <div style={{ flex: 1 }}>
                      <strong>{contact.name || contact.email || contact.phone}</strong>
                      <div className="small" style={{ marginTop: 4 }}>
                        {contact.email && <a href={`mailto:${contact.email}`}>{contact.email}</a>}
                        {contact.email && contact.phone && ' · '}
                        {contact.phone && <a href={`tel:${contact.phone}`}>{contact.phone}</a>}
                      </div>
                      <div className="actions" style={{ marginTop: 7 }}>
                        {contact.roles.map((role) => (
                          <Badge key={role} tone="neutral">{crmContactRoleLabel(role)}</Badge>
                        ))}
                        {contact.messageCount > 0 && (
                          <Badge tone="blue">{contact.messageCount} message{contact.messageCount > 1 ? 's' : ''}</Badge>
                        )}
                      </div>
                      {contact.lastContactAt && (
                        <div className="small muted" style={{ marginTop: 6 }}>
                          Dernier contact : {formatDate(contact.lastContactAt)}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          <section aria-labelledby={`crm-workflow-${organization.key}`}>
            <h3 id={`crm-workflow-${organization.key}`}>État du parcours</h3>
            {statusEntries(organization.applicationStatuses).length === 0
              && statusEntries(organization.positioningStatuses).length === 0 ? (
                <div className="notice" style={{ marginTop: 9 }}>Aucun statut de suivi disponible.</div>
              ) : (
                <div className="stack" style={{ marginTop: 9 }}>
                  {statusEntries(organization.applicationStatuses).length > 0 && (
                    <div>
                      <strong className="small">Candidatures</strong>
                      <div className="actions" style={{ marginTop: 7 }}>
                        {statusEntries(organization.applicationStatuses).map(([status, count]) => (
                          <Badge key={status} tone="blue">{statusLabel(status)} · {count}</Badge>
                        ))}
                      </div>
                    </div>
                  )}
                  {statusEntries(organization.positioningStatuses).length > 0 && (
                    <div>
                      <strong className="small">Positionnements</strong>
                      <div className="actions" style={{ marginTop: 7 }}>
                        {statusEntries(organization.positioningStatuses).map(([status, count]) => (
                          <Badge key={status}>{statusLabel(status)} · {count}</Badge>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
          </section>
        </div>

        <section aria-labelledby={`crm-offers-${organization.key}`} style={{ marginTop: 18 }}>
          <h3 id={`crm-offers-${organization.key}`}>Offres récentes associées</h3>
          {organization.latestOffers.length === 0 ? (
            <div className="small muted" style={{ marginTop: 7 }}>Aucune offre associée.</div>
          ) : (
            <div className="stack" style={{ marginTop: 7 }}>
              {organization.latestOffers.map((offer, index) => (
                <div className="list-row" key={`${offer.id ?? 'offer'}-${offer.title}-${index}`} style={{ paddingTop: 8, paddingBottom: 8 }}>
                  <div style={{ flex: 1 }}>
                    <strong>{offer.title}</strong>
                    <div className="actions" style={{ marginTop: 6 }}>
                      <Badge tone="blue">Score {offer.score}</Badge>
                      <Badge>{statusLabel(offer.status)}</Badge>
                    </div>
                  </div>
                  {offer.sourceUrl && (
                    <a className="btn secondary small" href={offer.sourceUrl} target="_blank" rel="noreferrer">
                      Ouvrir l’offre
                    </a>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>
      </article>
    </Card>
  );
}
