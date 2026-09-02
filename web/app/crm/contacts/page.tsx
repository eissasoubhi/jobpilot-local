'use client';

import { useEffect, useMemo, useState } from 'react';

import {
  CrmContactCorrectionEditor,
  type CrmContactCorrectionPayload,
  type EditableCrmContact,
} from '@/components/CrmContactCorrectionEditor';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import {
  Badge,
  Button,
  Card,
  DataList,
  DataListItem,
  DataToolbar,
  Empty,
  ErrorBox,
  FormField,
  InlineFeedback,
  PageHeader,
} from '@/components/UI';
import { api } from '@/lib/api';
import { downloadCrmContactsCsv } from '@/lib/crm-contact-export';
import { filterCrmContacts, type CrmContactFilter } from '@/lib/crm-contact-filters';
import { getErrorMessage } from '@/lib/errors';
import type { CrmDirectory, CrmOrganization } from '@/lib/types';

import styles from './page.module.css';

type Selection = { organization: CrmOrganization; contact: EditableCrmContact };
type ContactEntry = { organization: CrmOrganization; organizationName: string; contact: EditableCrmContact };

function CrmContactsSkeleton() {
  return (
    <SkeletonGroup label="Chargement des contacts CRM">
      <Card>
        <div className="form-grid" aria-hidden="true">
          <div>
            <Skeleton width="62%" height={16} />
            <div className="mt-8"><Skeleton height={42} /></div>
          </div>
          <div>
            <Skeleton width="42%" height={16} />
            <div className="mt-8"><Skeleton height={42} /></div>
          </div>
        </div>
        <div className={`actions ${styles.summary}`} aria-hidden="true">
          <Skeleton width={92} height={28} />
          <Skeleton width={104} height={28} />
          <Skeleton width={96} height={28} />
        </div>
      </Card>
      <Card>
        <div className={styles.skeletonList} aria-hidden="true">
          {[0, 1, 2].map((index) => (
            <div key={index} className={styles.skeletonItem}>
              <div className={styles.skeletonMain}>
                <Skeleton width="48%" height={18} />
                <Skeleton width="68%" height={14} />
                <Skeleton width="54%" height={24} />
              </div>
              <Skeleton width={138} height={34} />
            </div>
          ))}
        </div>
      </Card>
    </SkeletonGroup>
  );
}

export default function CrmContactsPage() {
  const [directory, setDirectory] = useState<CrmDirectory | null>(null);
  const [selection, setSelection] = useState<Selection | null>(null);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<CrmContactFilter>('ALL');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = async (): Promise<void> => {
    try {
      setDirectory(await api<CrmDirectory>('/crm/organizations'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  useEffect(() => { void load(); }, []);

  const save = async (payload: CrmContactCorrectionPayload): Promise<void> => {
    if (!selection) return;
    await api(
      `/crm/organizations/${encodeURIComponent(selection.organization.key)}/contacts/${encodeURIComponent(selection.contact.key)}/correction`,
      { method: 'PUT', body: JSON.stringify(payload) },
    );
    const cleared = payload.name === '' && payload.email === '' && payload.phone === '';
    setSelection(null);
    setNotice(cleared ? 'La correction locale a été effacée.' : 'La correction du contact a été enregistrée.');
    await load();
  };

  const contacts = useMemo<ContactEntry[]>(() => directory?.organizations.flatMap((organization) =>
    organization.contacts.map((contact) => ({
      organization,
      organizationName: organization.name,
      contact: contact as EditableCrmContact,
    })),
  ) ?? [], [directory]);

  const visibleContacts = useMemo(
    () => filterCrmContacts(contacts, search, filter),
    [contacts, filter, search],
  );
  const correctedCount = contacts.filter(({ contact }) => contact.correction != null).length;

  const exportVisibleContacts = (): void => {
    downloadCrmContactsCsv(visibleContacts);
    setNotice(`${visibleContacts.length} contact(s) visible(s) ont été exporté(s) en CSV.`);
  };

  return (
    <>
      <PageHeader title="Corrections des contacts CRM" description="Corrige le nom, l’e-mail ou le téléphone affiché sans modifier les données sources." />
      {notice !== '' && (
        <div className={styles.feedback}>
          <InlineFeedback tone="success">{notice}</InlineFeedback>
        </div>
      )}
      {error !== '' && <ErrorBox message={error} />}
      {directory === null && error === '' ? <CrmContactsSkeleton /> : contacts.length === 0 ? (
        <Card><Empty>Aucun contact CRM validé n’est disponible.</Empty></Card>
      ) : (
        <>
          <Card>
            <DataToolbar
              actions={(
                <Button
                  variant="secondary"
                  size="small"
                  disabled={visibleContacts.length === 0}
                  onClick={exportVisibleContacts}
                >
                  Exporter les contacts affichés
                </Button>
              )}
            >
              <div className="form-grid">
                <FormField label="Rechercher un contact ou une organisation">
                  <input
                    type="search"
                    value={search}
                    placeholder="Nom, e-mail, téléphone ou société"
                    onChange={(event) => setSearch(event.target.value)}
                  />
                </FormField>
                <FormField label="État de correction">
                  <select value={filter} onChange={(event) => setFilter(event.target.value as CrmContactFilter)}>
                    <option value="ALL">Tous les contacts</option>
                    <option value="CORRECTED">Corrigés localement</option>
                    <option value="UNCORRECTED">Sans correction locale</option>
                  </select>
                </FormField>
              </div>
              <div className={`actions ${styles.summary}`}>
                <Badge>{contacts.length} contact(s)</Badge>
                <Badge tone={correctedCount > 0 ? 'warn' : 'neutral'}>{correctedCount} corrigé(s)</Badge>
                <Badge tone="blue">{visibleContacts.length} affiché(s)</Badge>
              </div>
              <p className={`small muted ${styles.helperText}`}>
                L’export contient uniquement les résultats actuellement filtrés. Les valeurs affichées et les valeurs sources restent séparées.
              </p>
            </DataToolbar>
          </Card>

          {visibleContacts.length === 0 ? (
            <Card><Empty>Aucun contact ne correspond aux filtres actuels.</Empty></Card>
          ) : (
            <Card>
              <DataList aria-label="Contacts CRM filtrés">
                {visibleContacts.map(({ organization, contact }) => {
                  const corrected = contact.correction != null;
                  return (
                    <DataListItem key={`${organization.key}-${contact.key}`}>
                      <div className={styles.contactMain}>
                        <strong>{contact.name || contact.email || contact.phone || 'Contact sans libellé'}</strong>
                        <div className={`small muted ${styles.organizationLine}`}>{organization.name} · <code>{contact.key}</code></div>
                        <div className={`actions ${styles.badges}`}>
                          {corrected && <Badge tone="warn">Corrigé localement</Badge>}
                          {contact.email && <Badge>{contact.email}</Badge>}
                          {contact.phone && <Badge>{contact.phone}</Badge>}
                        </div>
                        {corrected && (
                          <div className={`small muted ${styles.sourceLine}`}>
                            Sources : {contact.sourceName || '—'} · {contact.sourceEmail || '—'} · {contact.sourcePhone || '—'}
                          </div>
                        )}
                      </div>
                      <Button variant="secondary" size="small" onClick={() => { setSelection({ organization, contact }); setNotice(''); }}>
                        {corrected ? 'Modifier la correction' : 'Corriger le contact'}
                      </Button>
                    </DataListItem>
                  );
                })}
              </DataList>
            </Card>
          )}
        </>
      )}
      {selection && <CrmContactCorrectionEditor organization={selection.organization} contact={selection.contact} onClose={() => setSelection(null)} onSave={save} />}
    </>
  );
}
