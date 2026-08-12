'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';

import {
  CrmOrganizationAnnotationEditor,
  type CrmOrganizationAnnotationPayload,
} from '@/components/CrmOrganizationAnnotationEditor';
import { CrmOrganizationCard } from '@/components/CrmOrganizationCard';
import { Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import {
  filterCrmOrganizations,
  type CrmRoleFilter,
} from '@/lib/crm';
import { crmQueryFromSearch } from '@/lib/crm-navigation';
import { getErrorMessage } from '@/lib/errors';
import type { CrmDirectory, CrmOrganization } from '@/lib/types';

export default function CrmPage() {
  const [directory, setDirectory] = useState<CrmDirectory | null>(null);
  const [selectedOrganization, setSelectedOrganization] = useState<CrmOrganization | null>(null);
  const [query, setQuery] = useState('');
  const [role, setRole] = useState<CrmRoleFilter>('ALL');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const refreshDirectory = useCallback(async (): Promise<void> => {
    const response = await api<CrmDirectory>('/crm/organizations');
    setDirectory(response);
    setError('');
  }, []);

  useEffect(() => {
    const contextQuery = crmQueryFromSearch(window.location.search);
    if (contextQuery !== '') setQuery(contextQuery);
  }, []);

  useEffect(() => {
    let active = true;

    void api<CrmDirectory>('/crm/organizations')
      .then((response) => {
        if (!active) return;
        setDirectory(response);
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (!active) return;
        setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const filteredOrganizations = useMemo(
    () => filterCrmOrganizations(directory?.organizations ?? [], query, role),
    [directory, query, role],
  );

  const saveAnnotation = async (
    organization: CrmOrganization,
    payload: CrmOrganizationAnnotationPayload,
  ): Promise<void> => {
    await api(`/crm/organizations/${encodeURIComponent(organization.key)}/annotation`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
    await refreshDirectory();

    const annotationCleared = payload.displayName.trim() === '' && payload.note.trim() === '';
    setSelectedOrganization(null);
    setNotice(
      annotationCleared
        ? `Les corrections CRM de ${organization.name} ont été effacées. Les données sources sont restées intactes.`
        : `La fiche CRM de ${payload.displayName.trim() || organization.name} a été enregistrée.`,
    );
  };

  return (
    <>
      <PageHeader
        title="CRM"
        description="Retrouve les entreprises, intermédiaires, clients finaux et contacts déjà présents dans tes candidatures, positionnements et messages associés."
      />

      {notice !== '' && <div className="notice" style={{ marginBottom: 16 }}>{notice}</div>}
      {error !== '' && <ErrorBox message={error} />}

      <div className="notice" style={{ marginBottom: 16 }}>
        <strong>Données sources protégées.</strong>{' '}
        Tu peux ajouter une note ou corriger le nom affiché dans le CRM. JobPilot conserve toujours la clé et le nom détecté d’origine, sans modifier les offres, positionnements ou messages associés.
      </div>

      {directory === null && error === '' ? (
        <Card><Loading /></Card>
      ) : directory !== null ? (
        <>
          <div className="grid three" aria-label="Résumé CRM">
            <Card>
              <div className="small muted">Organisations</div>
              <div className="metric">{directory.organizationCount}</div>
            </Card>
            <Card>
              <div className="small muted">Contacts validés</div>
              <div className="metric">{directory.contactCount}</div>
            </Card>
            <Card>
              <div className="small muted">Fiches annotées</div>
              <div className="metric">{directory.annotationCount}</div>
            </Card>
            <Card>
              <div className="small muted">Résultats affichés</div>
              <div className="metric">{filteredOrganizations.length}</div>
            </Card>
          </div>

          <Card>
            <div className="grid two">
              <label>
                Rechercher une organisation, un contact, une note ou une offre
                <input
                  type="search"
                  value={query}
                  placeholder="Entreprise, note, email, téléphone, mission…"
                  onChange={(event) => setQuery(event.target.value)}
                />
              </label>
              <label>
                Rôle de l’organisation
                <select value={role} onChange={(event) => setRole(event.target.value as CrmRoleFilter)}>
                  <option value="ALL">Tous les rôles</option>
                  <option value="COMPANY">Entreprises</option>
                  <option value="AGENCY">Intermédiaires</option>
                  <option value="CLIENT">Clients finaux</option>
                </select>
              </label>
            </div>
          </Card>

          <div className="stack" data-testid="crm-organization-list">
            {filteredOrganizations.length === 0 ? (
              <Card>
                <Empty>Aucune organisation ne correspond à ces critères.</Empty>
              </Card>
            ) : filteredOrganizations.map((organization) => (
              <CrmOrganizationCard
                key={organization.key}
                organization={organization}
                onEditAnnotation={(item) => {
                  setSelectedOrganization(item);
                  setNotice('');
                }}
              />
            ))}
          </div>
        </>
      ) : null}

      {selectedOrganization && (
        <CrmOrganizationAnnotationEditor
          key={`${selectedOrganization.key}-${selectedOrganization.annotation?.updatedAt ?? 'new'}`}
          organization={selectedOrganization}
          onClose={() => setSelectedOrganization(null)}
          onSave={(payload) => saveAnnotation(selectedOrganization, payload)}
        />
      )}
    </>
  );
}
