'use client';

import { useEffect, useMemo, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { buildApplicationReporting } from '@/lib/application-reporting';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function rate(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
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
        <Loading />
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
            {summary.bySource.map((row) => (
              <div className="list-row" key={row.source}>
                <div style={{ flex: 1 }}>
                  <strong>{row.source}</strong>
                  <div className="actions" style={{ marginTop: 8 }}>
                    <Badge>{row.total} candidature(s)</Badge>
                    <Badge tone="good">{row.submitted} envoyée(s)</Badge>
                    <Badge tone="blue">{row.interviews} entretien(s)</Badge>
                    <Badge tone="bad">{row.rejected} refus</Badge>
                  </div>
                </div>
              </div>
            ))}
          </Card>

          <div className="notice warning">
            Les taux reposent uniquement sur les statuts actuellement stockés. JobPilot ne déduit pas une réponse, un entretien ou un refus qui n’a pas été enregistré.
          </div>
        </div>
      )}
    </>
  );
}
