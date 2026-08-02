'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Job } from '@/lib/types';
import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';

export default function DashboardPage() {
  const [data, setData] = useState<{counts:Record<string,number>; recentJobs:Job[]} | null>(null);
  const [error, setError] = useState('');
  useEffect(() => { api<any>('/dashboard').then(setData).catch(e => setError(e.message)); }, []);
  if (error) return <><PageHeader title="Tableau de bord" /> <ErrorBox message={error} /></>;
  if (!data) return <Loading />;
  const stats = [
    ['Offres détectées', data.counts.jobs], ['Prêtes à envoyer', data.counts.prepared],
    ['Candidatures envoyées', data.counts.submitted], ['Positionnements', data.counts.positionings],
  ];
  return <>
    <PageHeader title="Tableau de bord" description="Vue d’ensemble de ta recherche et de tes candidatures." />
    <div className="grid cols-4">{stats.map(([label,value]) => <Card key={String(label)} className="stat-card"><span>{label}</span><strong>{value}</strong></Card>)}</div>
    <div style={{height:18}} />
    <div className="grid cols-2">
      <Card>
        <h2 className="section-title">Offres récentes</h2>
        {data.recentJobs.length === 0 ? <div className="empty">Aucune offre importée.</div> : data.recentJobs.map(job => <div className="list-row" key={job.id}>
          <div><h3>{job.title}</h3><div className="muted small">{job.company || 'Entreprise non renseignée'} · {job.location || 'Lieu non renseigné'}</div><div style={{marginTop:7}}><Badge tone={job.status === 'PREPARED' ? 'good' : 'blue'}>{job.status}</Badge></div></div>
          <div className="score">{job.score}</div>
        </div>)}
      </Card>
      <Card>
        <h2 className="section-title">Règles actives</h2>
        <div className="stack">
          <div className="notice">Les offres sont triées par fraîcheur, puis par score.</div>
          <div className="notice">Score ≥ 50 : préparation automatique.</div>
          <div className="notice">CV français pour une offre francophone, CV anglais pour une offre anglophone.</div>
          <div className="notice warning">L’envoi final reste en confirmation manuelle.</div>
        </div>
      </Card>
    </div>
  </>;
}
