'use client';

import { useEffect, useMemo, useState } from 'react';

import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Badge, Card, DataList, DataListItem, Empty, ErrorBox, InlineFeedback, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { buildApplicationReporting } from '@/lib/application-reporting';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function rate(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
}

function ReportingSkeleton() {
  return (
    <SkeletonGroup label="Chargement des indicateurs de candidature">
      <div className="grid cols-2">
        <Card>
          <Skeleton width="42%" height={22} />
          <div className="actions" style={{ marginTop: 12 }}>
            <Skeleton width={92} height={24} />
            <Skeleton width={104} height={24} />
            <Skeleton width={82} height={24} />
          </div>
        </Card>
        <Card>
          <Skeleton width="48%" height={22} />
          <div className="actions" style={{ marginTop: 12 }}>
            <Skeleton width={96} height={24} />
            <Skeleton width={76} height={24} />
            <Skeleton width={110} height={24} />
          </div>
        </Card>
      </div>

      <Card>
        <Skeleton width="34%" height={24} />
        <DataList aria-hidden="true" style={{ marginTop: 16 }}>
          {[0, 1, 2].map((index) => (
            <DataListItem key={index}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <Skeleton width="28%" height={18} />
                <div className="actions" style={{ marginTop: 10 }}>
                  <Skeleton width={96} height={24} />
                  <Skeleton width={92} height={24} />
                  <Skeleton width={88} height={24} />
                </div>
              </div>
            </DataListItem>
          ))}
        </DataList>
      </Card>
    </SkeletonGroup>
  );
}

export default function ReportingPage() {
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    void api<Application[]>('/applications')
      .then((items) => {
        if (!active) return;
        setApplications(items);
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });
    return () => { active = false; };
  }, []);

  const summary = useMemo(
    () => applications ? buildApplicationReporting(applications) : null,
    [applications],
  );

  return (
    <>
      <PageHeader
        title="Reporting candidatures"
        description="Indicateurs locaux calculés uniquement depuis les candidatures déjà enregistrées dans JobPilot."
      />
      {error !== '' && <ErrorBox message={error} />}
      {summary === null ? (
        <ReportingSkeleton />
      ) : summary.total === 0 ? (
        <Card><Empty>Aucune candidature n’est disponible pour calculer les indicateurs.</Empty></Card>
      ) : (
        <div className="stack">
          <div className="grid cols-2">
            <Card><h3>Candidatures</h3><div className="actions"><Badge tone="blue">{summary.total} préparée(s)</Badge><Badge tone="good">{summary.submitted} envoyée(s)</Badge><Badge>{rate(summary.submissionRate)} envoyées</Badge></div></Card>
            <Card><h3>Résultats connus</h3><div className="actions"><Badge tone="good">{summary.interviews} entretien(s)</Badge><Badge tone="bad">{summary.rejected} refus</Badge><Badge>{summary.active} non refusée(s)</Badge></div></Card>
          </div>

          <Card>
            <h2 className="section-title">Conversion par source</h2>
            <DataList aria-label="Conversion des candidatures par source">
              {summary.bySource.map((row) => (
                <DataListItem key={row.source}>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <strong>{row.source}</strong>
                    <div className="actions" style={{ marginTop: 8 }}>
                      <Badge>{row.total} candidature(s)</Badge>
                      <Badge tone="good">{row.submitted} envoyée(s)</Badge>
                      <Badge tone="blue">{row.interviews} entretien(s)</Badge>
                      <Badge tone="bad">{row.rejected} refus</Badge>
                    </div>
                  </div>
                </DataListItem>
              ))}
            </DataList>
          </Card>

          <InlineFeedback tone="warning">
            Les taux reposent uniquement sur les statuts actuellement stockés. JobPilot ne déduit pas une réponse, un entretien ou un refus qui n’a pas été enregistré.
          </InlineFeedback>
        </div>
      )}
    </>
  );
}
