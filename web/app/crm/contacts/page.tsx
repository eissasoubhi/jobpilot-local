'use client';

import { useEffect, useMemo, useState } from 'react';

import {
  CrmContactCorrectionEditor,
  type CrmContactCorrectionPayload,
  type EditableCrmContact,
} from '@/components/CrmContactCorrectionEditor';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { downloadCrmContactsCsv } from '@/lib/crm-contact-export';
import { filterCrmContacts, type CrmContactFilter } from '@/lib/crm-contact-filters';
import { getErrorMessage } from '@/lib/errors';
import type { CrmDirectory, CrmOrganization } from '@/lib/types';

type Selection = { organization: CrmOrganization; contact: EditableCrmContact };
type ContactEntry = { organization: CrmOrganization; organizationName: string; contact: EditableCrmContact };

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
      {notice !== '' && <div className="notice" style={{ marginBottom: 16 }}>{notice}</div>}
      {error !== '' && <ErrorBox message={error} />}
      {directory === null && error === '' ? <Loading /> : contacts.length === 0 ? (
        <Card><Empty>Aucun contact CRM validé n’est disponible.</Empty></Card>
      ) : (
        <>
          <Card>
            <div className="form-grid">
              <label>
                Rechercher un contact ou une organisation
                <input
                  type="search"
                  value={search}
                  placeholder="Nom, e-mail, téléphone ou société"
                  onChange={(event) => setSearch(event.target.value)}
                />
              </label>
              <label>
                État de correction
                <select value={filter} onChange={(event) => setFilter(event.target.value as CrmContactFilter)}>
                  <option value="ALL">Tous les contacts</option>
                  <option value="CORRECTED">Corrigés localement</option>
                  <option value="UNCORRECTED">Sans correction locale</option>
                </select>
              </label>
            </div>
            <div className="actions" style={{ marginTop: 12 }}>
              <Badge>{contacts.length} contact(s)</Badge>
              <Badge tone={correctedCount > 0 ? 'warn' : 'neutral'}>{correctedCount} corrigé(s)</Badge>
              <Badge tone="blue">{visibleContacts.length} affiché(s)</Badge>
              <button
                className="btn secondary small"
                type="button"
                disabled={visibleContacts.length === 0}
                onClick={exportVisibleContacts}
              >
                Exporter les contacts affichés
              </button>
            </div>
            <p className="small muted" style={{ marginBottom: 0, marginTop: 10 }}>
              L’export contient uniquement les résultats actuellement filtrés. Les valeurs affichées et les valeurs sources restent séparées.
            </p>
          </Card>

          {visibleContacts.length === 0 ? (
            <Card><Empty>Aucun contact ne correspond aux filtres actuels.</Empty></Card>
          ) : (
            <div className="stack">
              {visibleContacts.map(({ organization, contact }) => {
                const corrected = contact.correction != null;
                return (
                  <Card key={`${organization.key}-${contact.key}`}>
                    <div className="list-row" style={{ padding: 0 }}>
                      <div style={{ flex: 1 }}>
                        <strong>{contact.name || contact.email || contact.phone || 'Contact sans libellé'}</strong>
                        <div className="small muted" style={{ marginTop: 4 }}>{organization.name} · <code>{contact.key}</code></div>
                        <div className="actions" style={{ marginTop: 7 }}>
                          {corrected && <Badge tone="warn">Corrigé localement</Badge>}
                          {contact.email && <Badge>{contact.email}</Badge>}
                          {contact.phone && <Badge>{contact.phone}</Badge>}
                        </div>
                        {corrected && (
                          <div className="small muted" style={{ marginTop: 7 }}>
                            Sources : {contact.sourceName || '—'} · {contact.sourceEmail || '—'} · {contact.sourcePhone || '—'}
                          </div>
                        )}
                      </div>
                      <button className="btn secondary small" type="button" onClick={() => { setSelection({ organization, contact }); setNotice(''); }}>
                        {corrected ? 'Modifier la correction' : 'Corriger le contact'}
                      </button>
                    </div>
                  </Card>
                );
              })}
            </div>
          )}
        </>
      )}
      {selection && <CrmContactCorrectionEditor organization={selection.organization} contact={selection.contact} onClose={() => setSelection(null)} onSave={save} />}
    </>
  );
}
