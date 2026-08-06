'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type SourceRow = {
  code: string;
  name: string;
  offers: number;
  applications: number;
  submitted: number;
  responses: number;
  interviews: number;
  rejections: number;
  applicationRate: number;
  responseRate: number;
  interviewRate: number;
  tjmProposalCount: number;
  salaryProposalCount: number;
  averageProposedTjm: number | null;
  averageProposedSalary: number | null;
};

type Report = {
  sources: SourceRow[];
  totals: { offers: number; applications: number; sources: number };
};

function rate(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
}

function amount(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

export default function SourceReportingPage() {
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    void api<Report>('/reporting/source-conversion')
      .then((result) => {
        if (active) setReport(result);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  if (error !== '') {
    return <><PageHeader title="Conversion par source" /><ErrorBox message={error} /></>;
  }
  if (report === null) return <Loading />;

  return (
    <>
      <PageHeader
        title="Conversion par source"
        description="Mesure en lecture seule des offres, candidatures, réponses, entretiens et propositions de rémunération attribués à chaque source."
      />

      <div className="grid cols-3">
        <Card className="stat-card"><span>Sources observées</span><strong>{report.totals.sources}</strong></Card>
        <Card className="stat-card"><span>Offres canoniques</span><strong>{report.totals.offers}</strong></Card>
        <Card className="stat-card"><span>Candidatures</span><strong>{report.totals.applications}</strong></Card>
      </div>

      <div style={{ height: 18 }} />
      <Card>
        <div className="notice" style={{ marginBottom: 14 }}>
          Une offre multi-sources est attribuée à chacune de ses sources. Les lignes ne doivent donc pas être additionnées pour retrouver le total canonique.
        </div>
        {report.sources.length === 0 ? (
          <Empty>Aucune donnée de source disponible.</Empty>
        ) : report.sources.map((source) => (
          <div className="list-row" key={source.code}>
            <div style={{ flex: 1 }}>
              <div className="actions" style={{ marginBottom: 6 }}>
                <Badge tone="blue">{source.name}</Badge>
                <Badge>{source.code}</Badge>
              </div>
              <div className="actions">
                <Badge>{source.offers} offre(s)</Badge>
                <Badge>{source.applications} candidature(s)</Badge>
                <Badge tone="good">{source.submitted} envoyée(s)</Badge>
                <Badge>{source.responses} réponse(s)</Badge>
                <Badge tone="blue">{source.interviews} entretien(s)</Badge>
                {source.rejections > 0 && <Badge tone="warn">{source.rejections} refus</Badge>}
              </div>
              <div className="small muted" style={{ marginTop: 9 }}>
                Taux de candidature : {rate(source.applicationRate)} · Réponse après envoi : {rate(source.responseRate)} · Entretien après envoi : {rate(source.interviewRate)}
              </div>
              {(source.averageProposedTjm !== null || source.averageProposedSalary !== null) && (
                <div className="small" style={{ marginTop: 7 }}>
                  {source.averageProposedTjm !== null && (
                    <>TJM proposé moyen : <strong>{amount(source.averageProposedTjm)} €</strong> ({source.tjmProposalCount} offre(s))</>
                  )}
                  {source.averageProposedTjm !== null && source.averageProposedSalary !== null && ' · '}
                  {source.averageProposedSalary !== null && (
                    <>Salaire proposé moyen : <strong>{amount(source.averageProposedSalary)} € brut/an</strong> ({source.salaryProposalCount} offre(s))</>
                  )}
                </div>
              )}
            </div>
          </div>
        ))}
      </Card>
    </>
  );
}
