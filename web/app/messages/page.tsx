'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';

import { InboxSenderClassificationCorrection } from '@/components/InboxSenderClassificationCorrection';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Badge, Button, ButtonLink, Card, DataList, DataListItem, DataToolbar, Empty, ErrorBox, FormField, InlineFeedback, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import { transactionActionCopy } from '@/lib/message-action-completion';
import {
  filterMessages,
  sortMessagesByUrgency,
  urgencyCounts,
  type MessageFilter,
  type MessageUrgency,
} from '@/lib/message-urgency';

import styles from './page.module.css';

type MessageCategory =
  | 'JOB_ALERT'
  | 'MARKETING'
  | 'RECRUITER_OPPORTUNITY'
  | 'RECRUITER_INFORMATIONAL'
  | 'APPLICATION_CONFIRMATION'
  | 'APPLICATION_REPLY'
  | 'INTERVIEW_REQUEST'
  | 'REJECTION'
  | 'INFORMATION_REQUEST'
  | 'UNKNOWN';

type Message = {
  id: number;
  gmailMessageId: string;
  threadId: string;
  gmailUrl: string | null;
  sender: string;
  recipient: string;
  replyTo: string | null;
  subject: string;
  snippet: string;
  bodyText: string | null;
  receivedAt: string;
  category: MessageCategory;
  classificationReason: string | null;
  sourcePlatform: string | null;
  actionRequired: boolean;
  processed: boolean;
  application: { id: number; status: string } | null;
  jobOffer: { id: number; title: string; company: string } | null;
  matchedAt: string | null;
  urgency: MessageUrgency;
};

type GmailStatus = {
  connected: boolean;
  readPermission: boolean;
  readPermissionMessage: string | null;
};

type SyncResult = {
  found: number;
  imported: number;
  duplicates: number;
  failed: number;
  offersFound: number;
  offersImported: number;
  associated: number;
  actionRequired: number;
  skipped: boolean;
};

const categoryLabels: Record<MessageCategory, string> = {
  JOB_ALERT: 'Alerte emploi',
  MARKETING: 'Newsletter / promotion',
  RECRUITER_OPPORTUNITY: 'Proposition recruteur',
  RECRUITER_INFORMATIONAL: 'Info recruteur',
  APPLICATION_CONFIRMATION: 'Candidature reçue',
  APPLICATION_REPLY: 'Réponse candidature',
  INTERVIEW_REQUEST: 'Entretien',
  REJECTION: 'Refus',
  INFORMATION_REQUEST: 'Informations demandées',
  UNKNOWN: 'À classer',
};

const categoryTone = (category: MessageCategory): 'good' | 'warn' | 'bad' | 'blue' | 'neutral' => {
  if (category === 'INTERVIEW_REQUEST') return 'good';
  if (category === 'REJECTION') return 'bad';
  if (category === 'INFORMATION_REQUEST' || category === 'RECRUITER_OPPORTUNITY') return 'warn';
  if (category === 'MARKETING' || category === 'RECRUITER_INFORMATIONAL' || category === 'UNKNOWN') return 'neutral';
  return 'blue';
};

function MessagesSkeleton() {
  return (
    <SkeletonGroup label="Chargement de la messagerie Gmail">
      <DataList aria-hidden="true">
        {[0, 1, 2].map((index) => (
          <DataListItem key={index}>
            <div className={styles.skeletonBody}>
              <div className={`actions ${styles.skeletonMeta}`}>
                <Skeleton width={84} height={24} />
                <Skeleton width={116} height={24} />
                <Skeleton width={132} height={16} />
              </div>
              <Skeleton width="72%" height={22} />
              <div className={styles.skeletonLine}><Skeleton width="38%" height={16} /></div>
              <div className={styles.skeletonSnippet}><Skeleton width="92%" height={16} /></div>
              <div className={styles.skeletonSnippetSecondary}><Skeleton width="76%" height={16} /></div>
              <div className={`actions ${styles.skeletonActions}`}>
                <Skeleton width={128} height={32} />
                <Skeleton width={156} height={32} />
              </div>
            </div>
          </DataListItem>
        ))}
      </DataList>
    </SkeletonGroup>
  );
}

export default function MessagesPage() {
  const [items, setItems] = useState<Message[] | null>(null);
  const [status, setStatus] = useState<GmailStatus | null>(null);
  const [filter, setFilter] = useState<MessageFilter>('ALL');
  const [busyId, setBusyId] = useState<number | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const [gmailStatus, messages] = await Promise.all([
        api<GmailStatus>('/integrations/gmail/status'),
        api<Message[]>('/integrations/gmail/messages?limit=250'),
      ]);
      setStatus(gmailStatus);
      setItems(messages);
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    const requestedFilter = new URLSearchParams(window.location.search).get('filter');
    if (requestedFilter) setFilter(requestedFilter);
    void load();
  }, [load]);

  const counts = useMemo(() => {
    const messages = items ?? [];
    const urgency = urgencyCounts(messages);
    return {
      ...urgency,
      interviews: messages.filter((message) => message.category === 'INTERVIEW_REQUEST' && !message.processed).length,
      recruiter: messages.filter((message) => message.category === 'RECRUITER_OPPORTUNITY' && !message.processed).length,
    };
  }, [items]);

  const visibleItems = useMemo(
    () => sortMessagesByUrgency(filterMessages(items ?? [], filter)),
    [filter, items],
  );

  const sync = async (): Promise<void> => {
    setSyncing(true);
    setInfo('');
    try {
      const result = await api<SyncResult>('/integrations/gmail/sync', { method: 'POST' });
      setInfo(
        `${result.imported} nouveau(x) message(s), ${result.offersImported} offre(s) importée(s), `
        + `${result.associated} message(s) associé(s) et ${result.actionRequired} action(s) détectée(s).`,
      );
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSyncing(false);
    }
  };

  const markProcessed = async (message: Message): Promise<void> => {
    setBusyId(message.id);
    try {
      await api(`/integrations/gmail/messages/${message.id}/processed`, {
        method: 'PATCH',
        body: JSON.stringify({ processed: !message.processed }),
      });
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setBusyId(null);
    }
  };

  const connected = status?.connected ?? false;
  const readable = connected && (status?.readPermission ?? false);

  return (
    <>
      <PageHeader
        title="Messagerie"
        description="Inbox intelligente qui met en avant les entretiens, informations demandées, propositions directes et réponses qui nécessitent une action rapide."
        actions={readable ? (
          <Button loading={syncing} onClick={() => void sync()}>
            {syncing ? 'Synchronisation…' : 'Synchroniser Gmail'}
          </Button>
        ) : (
          <ButtonLink href="/api/integrations/gmail/start">
            {connected ? 'Reconnecter Gmail' : 'Connecter Gmail'}
          </ButtonLink>
        )}
      />

      {status?.readPermissionMessage && connected && !readable && (
        <InlineFeedback tone="warning">{status.readPermissionMessage}</InlineFeedback>
      )}
      {info !== '' && <InlineFeedback tone="success">{info}</InlineFeedback>}
      {error !== '' && <ErrorBox message={error} />}

      {items !== null && counts.urgent > 0 && (
        <InlineFeedback className="mt-16" tone="warning">
          <div className={styles.urgentFeedbackRow}>
            <div>
              <strong>{counts.urgent} message(s) urgent(s) à traiter.</strong>{' '}
              Les raisons sont affichées sur chaque message ; JobPilot ne répond jamais automatiquement à ta place.
            </div>
            <Button size="small" onClick={() => setFilter('URGENT')}>
              Voir les urgences
            </Button>
          </div>
        </InlineFeedback>
      )}

      <div className={styles.statsGrid}>
        <Card className="stat-card"><span>Urgents</span><strong>{counts.urgent}</strong></Card>
        <Card className="stat-card"><span>À traiter</span><strong>{counts.actionRequired}</strong></Card>
        <Card className="stat-card"><span>Entretiens</span><strong>{counts.interviews}</strong></Card>
        <Card className="stat-card"><span>Propositions recruteurs</span><strong>{counts.recruiter}</strong></Card>
      </div>

      <Card>
        <DataToolbar>
          <div className={styles.filterField}>
            <FormField label="Afficher">
              <select value={filter} onChange={(event) => setFilter(event.target.value)}>
                <option value="ALL">Tous les messages</option>
                <option value="URGENT">Urgents uniquement</option>
                <option value="PRIORITY">Urgents et prioritaires</option>
                <option value="ACTION_REQUIRED">Actions à traiter</option>
                {Object.entries(categoryLabels).map(([value, label]) => (
                  <option value={value} key={value}>{label}</option>
                ))}
              </select>
            </FormField>
          </div>
        </DataToolbar>

        {items === null ? (
          <MessagesSkeleton />
        ) : visibleItems.length === 0 ? (
          <Empty>
            {connected
              ? 'Aucun message ne correspond à ce filtre. Lance une synchronisation ou change le filtre.'
              : 'Connecte Gmail depuis les paramètres.'}
          </Empty>
        ) : (
          <DataList aria-label="Messages Gmail">
            {visibleItems.map((message) => {
              const transactionAction = transactionActionCopy(message.category);

              return (
                <DataListItem key={message.id}>
                  <div className={styles.messageContent}>
                    <div className="actions">
                      {message.urgency.level === 'URGENT' && <Badge tone="bad">Urgent</Badge>}
                      {message.urgency.level === 'PRIORITY' && <Badge tone="warn">Prioritaire</Badge>}
                      <Badge tone={categoryTone(message.category)}>{categoryLabels[message.category]}</Badge>
                      {message.sourcePlatform && <Badge tone="blue">{message.sourcePlatform}</Badge>}
                      {message.urgency.level === 'NORMAL' && message.actionRequired && !message.processed && (
                        <Badge tone="warn">Action requise</Badge>
                      )}
                      {message.processed && (
                        <Badge tone="good">{transactionAction?.completedBadge ?? 'Traité'}</Badge>
                      )}
                      <span className="muted small">
                        {new Date(message.receivedAt).toLocaleString('fr-FR')}
                      </span>
                    </div>

                    <h3>{message.subject || '(sans objet)'}</h3>
                    <div className="muted small">{message.sender}</div>
                    <p className="small">{message.snippet}</p>

                    {message.urgency.level !== 'NORMAL' && (
                      <div className={`notice warning ${styles.urgencyNotice}`}>
                        {message.urgency.recommendedAction && <strong>{message.urgency.recommendedAction}.</strong>}{' '}
                        {message.urgency.reasons.join(' ')}
                      </div>
                    )}

                    {message.classificationReason && (
                      <div className={`muted small ${styles.classificationReason}`}>Analyse : {message.classificationReason}</div>
                    )}

                    {message.jobOffer && (
                      <div className={`notice ${styles.associatedOffer}`}>
                        Offre associée : <strong>{message.jobOffer.title}</strong> — {message.jobOffer.company}
                        {message.application && <> · Candidature #{message.application.id} : {message.application.status}</>}
                      </div>
                    )}

                    {message.bodyText && message.bodyText !== message.snippet && (
                      <details className={styles.details}>
                        <summary className="small">Afficher le contenu analysé</summary>
                        <pre className="message-body">{message.bodyText}</pre>
                      </details>
                    )}

                    <div className={`actions ${styles.messageActions}`}>
                      {message.gmailUrl && (
                        <ButtonLink
                          href={message.gmailUrl}
                          target="_blank"
                          rel="noreferrer"
                          variant={message.urgency.actionRequired ? 'primary' : 'secondary'}
                          size="small"
                        >
                          Ouvrir dans Gmail
                        </ButtonLink>
                      )}
                      <Button
                        variant="secondary"
                        size="small"
                        loading={busyId === message.id}
                        disabled={busyId !== null}
                        onClick={() => void markProcessed(message)}
                      >
                        {busyId === message.id
                          ? 'Enregistrement…'
                          : transactionAction
                            ? message.processed
                              ? transactionAction.reopenLabel
                              : transactionAction.completeLabel
                            : message.processed
                              ? 'Remettre à traiter'
                              : 'Marquer comme traité'}
                      </Button>
                      <InboxSenderClassificationCorrection
                        messageId={message.id}
                        sender={message.sender}
                        category={message.category}
                        onSaved={load}
                      />
                    </div>

                    {transactionAction && !message.processed && (
                      <div className={`muted small ${styles.transactionHelp}`}>
                        {transactionAction.help}
                      </div>
                    )}
                  </div>
                </DataListItem>
              );
            })}
          </DataList>
        )}
      </Card>
    </>
  );
}
