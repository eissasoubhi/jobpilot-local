'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type ConversionRow = {
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
  averageMatchingScore: number;
  strongMatches: number;
  strongMatchRate: number;
  tjmProposalCount: number;
  salaryProposalCount: number;
  averageProposedTjm: number | null;
  averageProposedSalary: number | null;
};

type Report = {
  sources: ConversionRow[];
  contractTypes: ConversionRow[];
  workModes: ConversionRow[];
  totals: { offers: number; applications: number; sources: number; contractTypes: number; workModes: number };
};

function rate(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
}

function score(value: number): string {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value);
}

function amount(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

function ConversionRows({ rows, emptyMessage }: { rows: ConversionRow[]; emptyMessage: string }) {
  if (rows.length === 0) return <Empty>{emptyMessage}</Empty>;

  return rows.map((row) => (
    <div className="list-row" key={row.code}>
      <div style={{ flex: 1 }}>
        <div className="actions" style={{ marginBottom: 6 }}>
          <Badge tone="blue">{row.name}</Badge>
          <Badge>{row.code}</Badge>
        </div>
        <div className="actions">
          <Badge>{row.offers} offre(s)</Badge>
          <Badge>{row.applications} candidature(s)</Badge>
          <Badge tone="good">{row.submitted} envoyée(s)</Badge>
          <Badge>{row.responses} réponse(s)</Badge>
          <Badge tone="blue">{row.interviews} entretien(s)</Badge>
          {row.rejections > 0 && <Badge tone="warn">{row.rejections} refus</Badge>}
        </div>
        <div className="small muted" style={{ marginTop: 9 }}>
          Taux de candidature : {rate(row.applicationRate)} · Réponse après envoi : {rate(row.responseRate)} · Entretien après envoi : {rate(row.interviewRate)}
        </div>
        <div className="small" style={{ marginTop: 7 }}>
          Score moyen : <strong>{score(row.averageMatchingScore)} / 100</strong> · Matching ≥ 60 : <strong>{row.strongMatches} offre(s)</strong> ({rate(row.strongMatchRate)})
        </div>
        {(row.averageProposedTjm !== null || row.averageProposedSalary !== null) && (
          <div className="small" style={{ marginTop: 7 }}>
            {row.averageProposedTjm !== null && (
              <>TJM proposé moyen : <strong>{amount(row.averageProposedTjm)} €</strong> ({row.tjmProposalCount} offre(s))</>
            )}
            {row.averageProposedTjm !== null && row.averageProposedSalary !== null && ' · '}
            {row.averageProposedSalary !== null && (
              <>Salaire proposé moyen : <strong>{amount(row.averageProposedSalary)} € brut/an</strong> ({row.salaryProposalCount} offre(s))</>
            )}
          </div>
        )}
      </div>
    </div>
  ));
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
    return <><PageHeader title="Conversion" /><ErrorBox message={error} /></>;
  }
  if (report === null) return <Loading />;

  return (
    <>
      <PageHeader
        title="Conversion"
        description="Mesure en lecture seule de la conversion, de la qualité du matching et des propositions de rémunération par source, type de contrat et mode de travail."
      />

      <div className="grid cols-3">
        <Card className="stat-card"><span>Sources observées</span><strong>{report.totals.sources}</strong></Card>
        <Card className="stat-card"><span>Types de contrat</span><strong>{report.totals.contractTypes}</strong></Card>
        <Card className="stat-card"><span>Modes de travail</span><strong>{report.totals.workModes}</strong></Card>
        <Card className="stat-card"><span>Offres / candidatures</span><strong>{report.totals.offers} / {report.totals.applications}</strong></Card>
      </div>

      <div style={{ height: 18 }} />
      <Card>
        <h2 style={{ marginTop: 0 }}>Par source</h2>
        <div className="notice" style={{ marginBottom: 14 }}>
          Une offre multi-sources est attribuée à chacune de ses sources. Les lignes ne doivent donc pas être additionnées pour retrouver le total canonique.
        </div>
        <ConversionRows rows={report.sources} emptyMessage="Aucune donnée de source disponible." />
      </Card>

      <div style={{ height: 18 }} />
      <Card>
        <h2 style={{ marginTop: 0 }}>Par type de contrat</h2>
        <div className="notice" style={{ marginBottom: 14 }}>
          Chaque offre canonique apparaît une seule fois dans son type de contrat actuel. Les valeurs absentes sont regroupées sous « Non renseigné ».
        </div>
        <ConversionRows rows={report.contractTypes} emptyMessage="Aucune donnée de contrat disponible." />
      </Card>

      <div style={{ height: 18 }} />
      <Card>
        <h2 style={{ marginTop: 0 }}>Par mode de travail</h2>
        <div className="notice" style={{ marginBottom: 14 }}>
          Chaque offre canonique apparaît une seule fois dans son mode de travail actuel. Les libellés stockés restent distincts et les valeurs absentes sont regroupées sous « Non renseigné ».
        </div>
        <ConversionRows rows={report.workModes} emptyMessage="Aucune donnée de mode de travail disponible." />
      </Card>
    </>
  );
}
