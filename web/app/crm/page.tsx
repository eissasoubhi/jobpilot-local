'use client';

import { useEffect, useMemo, useState } from 'react';

import { CrmOrganizationCard } from '@/components/CrmOrganizationCard';
import { Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import {
  filterCrmOrganizations,
  type CrmRoleFilter,
} from '@/lib/crm';
import { getErrorMessage } from '@/lib/errors';
import type { CrmDirectory } from '@/lib/types';

export default function CrmPage() {
  const [directory, setDirectory] = useState<CrmDirectory | null>(null);
  const [query, setQuery] = useState('');
  const [role, setRole] = useState<CrmRoleFilter>('ALL');
  const [error, setError] = useState('');

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

  return (
    <>
      <PageHeader
        title="CRM"
        description="Retrouve les entreprises, intermédiaires, clients finaux et contacts déjà présents dans tes candidatures, positionnements et messages associés."
      />

      {error !== '' && <ErrorBox message={error} />}

      <div className="notice" style={{ marginBottom: 16 }}>
        <strong>Annuaire local en lecture seule.</strong>{' '}
        JobPilot regroupe uniquement les données déjà enregistrées. Aucun profil externe n’est recherché et aucune identité de recruteur n’est inventée.
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
              <div className="small muted">Résultats affichés</div>
              <div className="metric">{filteredOrganizations.length}</div>
            </Card>
          </div>

          <Card>
            <div className="grid two">
              <label>
                Rechercher une organisation, un contact ou une offre
                <input
                  type="search"
                  value={query}
                  placeholder="Entreprise, email, téléphone, mission…"
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
              <CrmOrganizationCard key={organization.key} organization={organization} />
            ))}
          </div>
        </>
      ) : null}
    </>
  );
}
