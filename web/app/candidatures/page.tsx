'use client';

import { useCallback, useEffect, useState } from 'react';

import { ApplicationStatusFilter } from '@/components/ApplicationStatusFilter';
import { CoverLetterEditor } from '@/components/CoverLetterEditor';
import { Modal } from '@/components/Modal';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  DataList,
  DataListItem,
  DataToolbar,
  Empty,
  ErrorBox,
  InlineFeedback,
  PageHeader,
} from '@/components/UI';
import { api } from '@/lib/api';
import {
  applicationBadgeLabel,
  applicationStatusLabel,
  applicationStatusTone,
  filterApplications,
  type ApplicationStatusFilter as ApplicationStatusFilterValue,
} from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

import styles from './page.module.css';

function companyName(application: Application): string {
  return application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';
}

function ApplicationsSkeleton() {
  return (
    <SkeletonGroup label="Chargement des candidatures">
      <DataToolbar aria-hidden="true">
        <Skeleton width={280} height={38} />
      </DataToolbar>
      <DataList aria-hidden="true">
        {[0, 1, 2].map((index) => (
          <DataListItem key={index}>
            <div className={styles.skeletonBody}>
              <Skeleton width="46%" height={22} />
              <Skeleton width="62%" height={16} className="mt-2" />
              <div className={styles.skeletonBadges}>
                <Skeleton width={92} height={24} />
                <Skeleton width={72} height={24} />
                <Skeleton width={84} height={24} />
              </div>
            </div>
            <Skeleton width={128} height={34} />
          </DataListItem>
        ))}
      </DataList>
    </SkeletonGroup>
  );
}

export default function ApplicationsPage() {
  const [items, setItems] = useState<Application[] | null>(null);
  const [selected, setSelected] = useState<Application | null>(null);
  const [statusFilter, setStatusFilter] = useState<ApplicationStatusFilterValue>('ALL');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [saving, setSaving] = useState(false);

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

  const save = async (
    statusOverride?: string,
    successMessage = 'Modifications enregistrées.',
  ): Promise<void> => {
    if (selected === null) return;

    setSaving(true);

    try {
      const updated = await api<Application>(`/applications/${selected.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status: statusOverride ?? selected.status,
          message: selected.message,
          coverLetter: selected.coverLetter,
          compensationAnswer: selected.compensationAnswer,
          confirmationRef: selected.confirmationRef,
        }),
      });
      setSelected(updated);
      setNotice(successMessage);
      setError('');
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const markSubmitted = async (): Promise<void> => {
    if (selected === null || selected.status === 'SUBMITTED') return;

    await save(
      'SUBMITTED',
      'Candidature marquée comme envoyée. La date d’envoi a été enregistrée dans JobPilot.',
    );
  };

  const copyText = async (label: string, value: string): Promise<void> => {
    try {
      await navigator.clipboard.writeText(value);
      setNotice(`${label} copié dans le presse-papiers.`);
      setError('');
    } catch {
      setError(`Impossible de copier ${label.toLowerCase()}.`);
    }
  };

  const openApplication = (application: Application): void => {
    setSelected(application);
    setNotice('');
    setError('');
  };

  const filteredItems = items === null ? null : filterApplications(items, statusFilter);

  return (
    <>
      <PageHeader
        title="Candidatures"
        description="Suis et filtre les candidatures préparées, envoyées manuellement ou transmises automatiquement par un canal officiel autorisé."
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {items === null || filteredItems === null ? (
          <ApplicationsSkeleton />
        ) : items.length === 0 ? (
          <Empty>Aucune candidature préparée.</Empty>
        ) : (
          <>
            <DataToolbar>
              <ApplicationStatusFilter
                applications={items}
                value={statusFilter}
                onChange={setStatusFilter}
              />
            </DataToolbar>

            {filteredItems.length === 0 ? (
              <Empty>
                Aucune candidature dans le statut « {applicationStatusLabel(statusFilter)} ».
              </Empty>
            ) : (
              <DataList aria-label="Candidatures filtrées">
                {filteredItems.map((application) => (
                  <DataListItem key={application.id}>
                    <div className={styles.applicationMain}>
                      <h3 className={styles.applicationTitle}>{application.jobOffer.title}</h3>
                      <div className={`muted small ${styles.applicationMeta}`}>
                        {companyName(application)} · {application.jobOffer.location || 'Lieu non renseigné'} ·{' '}
                        {application.jobOffer.contractType || 'Contrat non renseigné'}
                      </div>
                      <div className={styles.metaBadges}>
                        <Badge tone={applicationStatusTone(application.status)}>{applicationBadgeLabel(application)}</Badge>
                        <Badge tone="blue">Score {application.jobOffer.score}</Badge>
                        <Badge>{application.jobOffer.language.toUpperCase()}</Badge>
                        {application.cvDocument && <Badge>{application.cvDocument.name}</Badge>}
                        {application.jobOffer.applicationEmail && <Badge>{application.jobOffer.applicationEmail}</Badge>}
                        {application.compensationAnswer && <Badge tone="good">{application.compensationAnswer}</Badge>}
                      </div>
                      {application.submissionError && (
                        <div className={`small ${styles.submissionError}`}>
                          <strong>Erreur :</strong> {application.submissionError}
                        </div>
                      )}
                    </div>
                    <div className={styles.listAction}>
                      <Button
                        size="small"
                        variant="secondary"
                        onClick={() => openApplication(application)}
                      >
                        {application.status === 'SUBMITTED' ? 'Voir le suivi' : 'Examiner et postuler'}
                      </Button>
                    </div>
                  </DataListItem>
                ))}
              </DataList>
            )}
          </>
        )}
      </Card>

      {selected && (
        <Modal
          ariaLabel={`Candidature ${selected.jobOffer.title}`}
          onClose={() => setSelected(null)}
        >
          <PageHeader
            title={selected.status === 'SUBMITTED' ? 'Suivi de la candidature' : 'Candidature préparée'}
            description="JobPilot prépare les éléments, utilise Gmail uniquement lorsque les conditions d’envoi automatique sont réunies, et conserve le suivi."
            actions={(
              <Button variant="secondary" onClick={() => setSelected(null)}>
                Fermer
              </Button>
            )}
          />

          {notice !== '' && <InlineFeedback tone="success">{notice}</InlineFeedback>}
          {error !== '' && <ErrorBox message={error} />}

          {selected.status === 'SUBMITTED' && selected.channel === 'Gmail automatique' ? (
            <InlineFeedback>
              <strong>Candidature envoyée automatiquement par Gmail.</strong>{' '}
              {selected.gmailMessageId && <>Identifiant Gmail : <code>{selected.gmailMessageId}</code>.</>}
            </InlineFeedback>
          ) : selected.status === 'SUBMISSION_PENDING' ? (
            <InlineFeedback tone="warning">
              <strong>Envoi Gmail en cours.</strong> JobPilot bloque toute nouvelle tentative pour éviter un doublon pendant cette étape.
            </InlineFeedback>
          ) : selected.status === 'SUBMISSION_FAILED' ? (
            <InlineFeedback tone="warning">
              <strong>L’envoi automatique a échoué.</strong>{' '}
              {selected.submissionError || 'Consulte les logs de l’API, puis utilise le parcours manuel ci-dessous.'}
            </InlineFeedback>
          ) : (
            <InlineFeedback tone="warning">
              <strong>JobPilot n’envoie pas automatiquement la candidature.</strong>{' '}
              Il prépare le CV et le message, ainsi qu’une lettre uniquement lorsque l’offre la demande. Tu dois ouvrir le site d’origine, compléter ou coller les informations, puis valider l’envoi sur ce site lorsqu’aucun e-mail officiel utilisable n’est disponible.
            </InlineFeedback>
          )}

          <section className="card" aria-labelledby="application-job-title">
            <div className={styles.offerHeader}>
              <div>
                <div className={`small muted ${styles.offerEyebrow}`}>Offre concernée</div>
                <h2 id="application-job-title" className={styles.offerTitle}>{selected.jobOffer.title}</h2>
                <div>
                  <strong>{companyName(selected)}</strong>
                  {selected.jobOffer.clientName && selected.jobOffer.clientName !== selected.jobOffer.company && (
                    <span className="muted"> · Client final : {selected.jobOffer.clientName}</span>
                  )}
                </div>
              </div>
              <div className="score" aria-label={`Score ${selected.jobOffer.score}`}>{selected.jobOffer.score}</div>
            </div>

            <div className={styles.offerBadges}>
              <Badge>{selected.jobOffer.location || 'Lieu non renseigné'}</Badge>
              <Badge>{selected.jobOffer.contractType || 'Contrat non renseigné'}</Badge>
              <Badge>{selected.jobOffer.workMode || 'Mode de travail non renseigné'}</Badge>
              <Badge tone="blue">Source : {selected.jobOffer.source || 'inconnue'}</Badge>
              <Badge>{selected.jobOffer.language.toUpperCase()}</Badge>
              {selected.jobOffer.applicationEmail && <Badge tone="good">Destinataire : {selected.jobOffer.applicationEmail}</Badge>}
            </div>

            <div className={styles.offerActions}>
              {selected.cvDocument && (
                <ButtonLink
                  href={selected.cvDocument.downloadUrl}
                  target="_blank"
                  rel="noreferrer"
                  variant="secondary"
                  size="small"
                >
                  Ouvrir le CV sélectionné
                </ButtonLink>
              )}
            </div>

            <details className={styles.offerDetails}>
              <summary className="small">Afficher la description complète de l’offre</summary>
              <div className={`small ${styles.offerDescription}`}>
                {selected.jobOffer.description || 'Description non disponible.'}
              </div>
            </details>
          </section>

          <div className={`stack ${styles.editorStack}`}>
            <div>
              <div className={styles.sectionActions}>
                <strong className="small">Message</strong>
                <Button
                  variant="secondary"
                  size="small"
                  onClick={() => void copyText('Message', selected.message)}
                >
                  Copier le message
                </Button>
              </div>
              <textarea aria-label="Message" value={selected.message} onChange={(event) => setSelected({ ...selected, message: event.target.value })} />
            </div>

            <CoverLetterEditor
              key={selected.id}
              value={selected.coverLetter}
              onChange={(coverLetter) => setSelected({ ...selected, coverLetter })}
              onCopy={(coverLetter) => copyText('Lettre de motivation', coverLetter)}
            />

            <label>
              Réponse rémunération
              <input value={selected.compensationAnswer ?? ''} onChange={(event) => setSelected({ ...selected, compensationAnswer: event.target.value })} />
            </label>

            <label>
              Confirmation / référence obtenue après l’envoi
              <input value={selected.confirmationRef ?? ''} onChange={(event) => setSelected({ ...selected, confirmationRef: event.target.value })} />
            </label>

            <label>
              Statut de suivi dans JobPilot
              <select value={selected.status} onChange={(event) => setSelected({ ...selected, status: event.target.value })} disabled={selected.status === 'SUBMISSION_PENDING'}>
                <option value="READY_TO_SUBMIT">Prête à envoyer</option>
                <option value="SUBMISSION_FAILED">Échec de l’envoi automatique</option>
                <option value="SUBMITTED">Envoyée</option>
                <option value="RECRUITER_REPLIED">Réponse recruteur</option>
                <option value="INTERVIEW">Entretien</option>
                <option value="REJECTED">Refusée</option>
                <option value="OFFER_RECEIVED">Offre reçue</option>
              </select>
              <span className="small muted">Une modification manuelle du statut sert au suivi et ne déclenche aucun envoi externe.</span>
            </label>
          </div>

          {selected.status !== 'SUBMISSION_PENDING' && !(selected.status === 'SUBMITTED' && selected.channel === 'Gmail automatique') && (
            <section className={`card ${styles.submissionSection}`} aria-labelledby="submission-steps-title">
              <h2 id="submission-steps-title" className="section-title">Finaliser la candidature</h2>
              <div className="stack">
                <div>
                  <strong>1. Enregistre tes modifications</strong>
                  <div className="small muted">Le message, la lettre, la rémunération et la référence restent sauvegardés localement.</div>
                </div>
                <Button
                  variant="secondary"
                  loading={saving}
                  onClick={() => void save(undefined, 'Modifications enregistrées. Tu peux maintenant postuler sur la plateforme d’origine.')}
                >
                  {saving ? 'Enregistrement…' : 'Étape 1 — Enregistrer mes modifications'}
                </Button>

                <div>
                  <strong>2. Envoie la candidature sur le site de l’annonce</strong>
                  <div className="small muted">Une nouvelle fenêtre s’ouvre. JobPilot ne remplit ni ne valide automatiquement le formulaire externe.</div>
                </div>
                {selected.jobOffer.sourceUrl ? (
                  <ButtonLink
                    href={selected.jobOffer.sourceUrl}
                    target="_blank"
                    rel="noreferrer"
                    onClick={() => setNotice('Plateforme ouverte dans un nouvel onglet. Reviens ici après avoir réellement validé l’envoi.')}
                  >
                    Étape 2 — Ouvrir la plateforme pour postuler
                  </ButtonLink>
                ) : (
                  <InlineFeedback tone="warning">
                    Aucun lien vers l’annonce originale n’est disponible. Recherche l’offre manuellement avec son titre et son entreprise, puis utilise les éléments préparés ci-dessus.
                  </InlineFeedback>
                )}

                <div>
                  <strong>3. Marque la candidature comme envoyée</strong>
                  <div className="small muted">Après l’envoi réel sur la plateforme, ce bouton met immédiatement à jour le suivi dans JobPilot sans ouvrir de confirmation.</div>
                </div>
                <Button
                  loading={saving}
                  disabled={selected.status === 'SUBMITTED'}
                  onClick={() => void markSubmitted()}
                >
                  {selected.status === 'SUBMITTED' ? 'Candidature déjà marquée comme envoyée' : saving ? 'Enregistrement…' : 'Étape 3 — J’ai envoyé la candidature'}
                </Button>
              </div>
            </section>
          )}
        </Modal>
      )}
    </>
  );
}
