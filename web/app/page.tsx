'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Job } from '@/lib/types';

type DashboardData = {
  counts: Record<string, number>;
  recentJobs: Job[];
};

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<DashboardData>('/dashboard')
      .then((result) => {
        if (active) {
          setData(result);
          setError('');
        }
      })
      .catch((caughtError: unknown) => {
        if (active) {
          setError(getErrorMessage(caughtError));
        }
      });

    return () => {
      active = false;
    };
  }, []);

  if (error !== '') {
    return (
      <>
        <PageHeader title="Tableau de bord" />
        <ErrorBox message={error} />
      </>
    );
  }

  if (data === null) {
    return <Loading />;
  }

  const stats = [
    ['Offres détectées', data.counts.jobs ?? 0],
    ['Prêtes à envoyer', data.counts.prepared ?? 0],
    ['Candidatures envoyées', data.counts.submitted ?? 0],
    ['Positionnements', data.counts.positionings ?? 0],
  ] as const;

  return (
    <>
      <PageHeader
        title="Tableau de bord"
        description="Vue d’ensemble de ta recherche et de tes candidatures."
      />

      <div className="grid cols-4">
        {stats.map(([label, value]) => (
          <Card key={label} className="stat-card">
            <span>{label}</span>
            <strong>{value}</strong>
          </Card>
        ))}
      </div>

      <div style={{ height: 18 }} />

      <div className="grid cols-2">
        <Card>
          <h2 className="section-title">Offres récentes</h2>
          {data.recentJobs.length === 0 ? (
            <div className="empty">Aucune offre importée.</div>
          ) : (
            data.recentJobs.map((job) => (
              <div className="list-row" key={job.id}>
                <div>
                  <h3>{job.title}</h3>
                  <div className="muted small">
                    {job.company || 'Entreprise non renseignée'} ·{' '}
                    {job.location || 'Lieu non renseigné'}
                  </div>
                  <div style={{ marginTop: 7 }}>
                    <Badge tone={job.status === 'PREPARED' ? 'good' : 'blue'}>
                      {job.status}
                    </Badge>
                  </div>
                </div>
                <div className="score" aria-label={`Score ${job.score}`}>
                  {job.score}
                </div>
              </div>
            ))
          )}
        </Card>

        <Card>
          <h2 className="section-title">Règles actives</h2>
          <div className="stack">
            <div className="notice">Les offres sont triées par fraîcheur, puis par score.</div>
            <div className="notice">Score ≥ 50 : préparation automatique.</div>
            <div className="notice">
              CV français pour une offre francophone, CV anglais pour une offre anglophone.
            </div>
            <div className="notice warning">L’envoi final reste en confirmation manuelle.</div>
          </div>
        </Card>
      </div>
    </>
  );
}
