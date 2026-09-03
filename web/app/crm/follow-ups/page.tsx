'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';

import { Badge, Button, Card, DataList, DataListItem, DataToolbar, Empty, ErrorBox, FormField, InlineFeedback, PageHeader } from '@/components/UI';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { api } from '@/lib/api';
import { formatFollowUpDate, followUpDueLabel, type CrmFollowUpStatus, type CrmFollowUpTask } from '@/lib/crm-follow-ups';
import { getErrorMessage } from '@/lib/errors';
import type { CrmDirectory } from '@/lib/types';

import styles from './page.module.css';

export default function CrmFollowUpsPage() {
  const [tasks, setTasks] = useState<CrmFollowUpTask[] | null>(null);
  const [directory, setDirectory] = useState<CrmDirectory | null>(null);
  const [status, setStatus] = useState<CrmFollowUpStatus>('open');
  const [organizationKey, setOrganizationKey] = useState('');
  const [contactKey, setContactKey] = useState('');
  const [title, setTitle] = useState('');
  const [note, setNote] = useState('');
  const [dueAt, setDueAt] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const [loadedTasks, loadedDirectory] = await Promise.all([
        api<CrmFollowUpTask[]>(`/crm/follow-ups?status=${status}`),
        api<CrmDirectory>('/crm/organizations'),
      ]);
      setTasks(loadedTasks);
      setDirectory(loadedDirectory);
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, [status]);

  useEffect(() => { void load(); }, [load]);

  const selectedOrganization = useMemo(
    () => directory?.organizations.find((organization) => organization.key === organizationKey) ?? null,
    [directory, organizationKey],
  );

  const createTask = async (): Promise<void> => {
    if (organizationKey === '' || title.trim() === '' || dueAt === '') return;
    setBusy(true); setError(''); setNotice('');
    try {
      await api(`/crm/organizations/${encodeURIComponent(organizationKey)}/follow-ups`, {
        method: 'POST',
        body: JSON.stringify({ contactKey: contactKey || null, title: title.trim(), note: note.trim(), dueAt }),
      });
      setTitle(''); setNote(''); setDueAt(''); setContactKey('');
      setNotice('La tâche de relance a été créée.');
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally { setBusy(false); }
  };

  const setCompleted = async (task: CrmFollowUpTask, completed: boolean): Promise<void> => {
    setBusy(true); setError(''); setNotice('');
    try {
      await api(`/crm/follow-ups/${task.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ completed }),
      });
      setNotice(completed ? 'La relance est terminée.' : 'La relance a été rouverte.');
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally { setBusy(false); }
  };

  return (
    <>
      <PageHeader title="Relances CRM" description="Planifie et suis des rappels locaux sans envoyer automatiquement de message." />
      {notice !== '' && <InlineFeedback tone="success" className={styles.feedback}>{notice}</InlineFeedback>}
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        <h2 className="section-title">Nouvelle relance</h2>
        {directory === null ? (
          <SkeletonGroup label="Chargement du formulaire de relance" className={styles.formSkeleton}>
            <div className={styles.formSkeletonGrid}>
              {Array.from({ length: 4 }, (_, index) => (
                <div className={styles.skeletonField} key={index}>
                  <Skeleton height={14} width="38%" />
                  <Skeleton height={42} />
                </div>
              ))}
            </div>
            <div className={styles.skeletonField}>
              <Skeleton height={14} width="24%" />
              <Skeleton height={84} />
            </div>
            <Skeleton height={40} width={150} />
          </SkeletonGroup>
        ) : directory.organizations.length === 0 ? <Empty>Aucune organisation CRM disponible.</Empty> : (
          <div className={styles.formStack}>
            <div className={styles.formGrid}>
              <FormField label="Organisation">
                <select
                  id="follow-up-organization"
                  value={organizationKey}
                  disabled={busy}
                  onChange={(event) => { setOrganizationKey(event.target.value); setContactKey(''); }}
                >
                  <option value="">Sélectionner une organisation</option>
                  {directory.organizations.map((organization) => <option key={organization.key} value={organization.key}>{organization.name}</option>)}
                </select>
              </FormField>
              <FormField label="Contact facultatif">
                <select
                  id="follow-up-contact"
                  value={contactKey}
                  disabled={busy || selectedOrganization === null}
                  onChange={(event) => setContactKey(event.target.value)}
                >
                  <option value="">Toute l’organisation</option>
                  {selectedOrganization?.contacts.map((contact) => (
                    <option key={contact.key} value={contact.key}>{contact.name || contact.email || contact.phone || contact.key}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Date de relance">
                <input id="follow-up-due-at" type="date" value={dueAt} disabled={busy} onChange={(event) => setDueAt(event.target.value)} />
              </FormField>
              <FormField label="Titre">
                <input id="follow-up-title" value={title} maxLength={180} disabled={busy} onChange={(event) => setTitle(event.target.value)} />
              </FormField>
            </div>
            <FormField label="Note facultative">
              <textarea id="follow-up-note" value={note} maxLength={2000} disabled={busy} onChange={(event) => setNote(event.target.value)} />
            </FormField>
            <div className={styles.primaryAction}>
              <Button
                type="button"
                loading={busy}
                disabled={organizationKey === '' || title.trim() === '' || dueAt === ''}
                onClick={() => void createTask()}
              >
                {busy ? 'Enregistrement…' : 'Créer la relance'}
              </Button>
            </div>
          </div>
        )}
      </Card>

      <Card>
        <DataToolbar
          actions={(
            <FormField label="État">
              <select id="follow-up-status" value={status} onChange={(event) => setStatus(event.target.value as CrmFollowUpStatus)}>
                <option value="open">Ouvertes</option>
                <option value="completed">Terminées</option>
                <option value="all">Toutes</option>
              </select>
            </FormField>
          )}
        >
          <h2 className={`section-title ${styles.toolbarTitle}`}>Tâches</h2>
        </DataToolbar>
        {tasks === null ? (
          <SkeletonGroup label="Chargement des relances CRM" className={styles.listSkeleton}>
            {Array.from({ length: 3 }, (_, index) => (
              <div className={styles.skeletonItem} key={index}>
                <div className={styles.skeletonMain}>
                  <Skeleton height={20} width="34%" />
                  <Skeleton height={18} width="62%" />
                  <Skeleton height={14} width="48%" />
                </div>
                <Skeleton height={36} width={128} />
              </div>
            ))}
          </SkeletonGroup>
        ) : tasks.length === 0 ? <Empty>Aucune relance dans cette vue.</Empty> : (
          <DataList aria-label="Relances CRM">
            {tasks.map((task) => {
              const organization = directory?.organizations.find((item) => item.key === task.organizationKey);
              const contact = organization?.contacts.find((item) => item.key === task.contactKey);
              const due = followUpDueLabel(task);
              return <DataListItem key={task.id}>
                <div className={styles.taskMain}>
                  <div className={styles.taskBadges}><Badge tone={due === 'OVERDUE' ? 'bad' : due === 'TODAY' ? 'warn' : due === 'COMPLETED' ? 'good' : 'blue'}>{due === 'OVERDUE' ? 'En retard' : due === 'TODAY' ? 'Aujourd’hui' : due === 'COMPLETED' ? 'Terminée' : 'À venir'}</Badge><Badge>{formatFollowUpDate(task.dueAt)}</Badge></div>
                  <strong className={styles.taskTitle}>{task.title}</strong>
                  <div className={`small muted ${styles.taskMeta}`}>{organization?.name ?? task.organizationKey}{contact ? ` · ${contact.name || contact.email || contact.key}` : ''}</div>
                  {task.note && <p className={`small ${styles.taskNote}`}>{task.note}</p>}
                </div>
                <div className={styles.taskAction}>
                  <Button variant="secondary" size="small" type="button" disabled={busy} onClick={() => void setCompleted(task, !task.completed)}>{task.completed ? 'Rouvrir' : 'Marquer terminée'}</Button>
                </div>
              </DataListItem>;
            })}
          </DataList>
        )}
      </Card>
    </>
  );
}
