'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { Card } from '@/components/UI';
import { api } from '@/lib/api';

import styles from './MarketSkillsCard.module.css';

type SkillSignal = {
  label: string;
  count: number;
  coveragePercent: number;
};

type MarketSkillsData = {
  periodDays: number;
  matchingThreshold: number;
  analyzedJobs: number;
  configuredSkillsCount: number;
  demanded: SkillSignal[];
  matching: SkillSignal[];
  unconfigured: SkillSignal[];
};

function isMarketSkillsData(value: unknown): value is MarketSkillsData {
  if (typeof value !== 'object' || value === null) return false;

  const candidate = value as Partial<MarketSkillsData>;
  return typeof candidate.periodDays === 'number'
    && typeof candidate.matchingThreshold === 'number'
    && typeof candidate.analyzedJobs === 'number'
    && typeof candidate.configuredSkillsCount === 'number'
    && Array.isArray(candidate.demanded)
    && Array.isArray(candidate.matching)
    && Array.isArray(candidate.unconfigured);
}

function SignalList({ items }: { items: SkillSignal[] }) {
  if (items.length === 0) {
    return <span className={styles.emptySignal}>Aucun signal suffisant.</span>;
  }

  return (
    <div className={styles.signalList}>
      {items.map((item) => (
        <div className={styles.signalRow} key={item.label}>
          <span>{item.label}</span>
          <strong>{item.count} offre(s) · {new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(item.coveragePercent)} %</strong>
        </div>
      ))}
    </div>
  );
}

export function MarketSkillsCard() {
  const [data, setData] = useState<MarketSkillsData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;

    void api<unknown>('/dashboard/market-skills')
      .then((result) => {
        if (!active) return;

        if (!isMarketSkillsData(result)) {
          setFailed(true);
          return;
        }

        setData(result);
        setFailed(false);
      })
      .catch(() => {
        if (active) {
          setFailed(true);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  return (
    <Card>
      <div className={styles.heading}>
        <div>
          <span className={styles.eyebrow}>Tendances des offres</span>
          <h2>Compétences récurrentes</h2>
        </div>
        <Link className={styles.link} href="/profil">Profil & compétences →</Link>
      </div>

      {failed ? (
        <div className={styles.emptyState}>Les tendances de compétences ne sont pas disponibles pour le moment.</div>
      ) : data === null ? (
        <div className={styles.loadingState}>Analyse des tendances…</div>
      ) : data.analyzedJobs === 0 ? (
        <div className={styles.emptyState}>Pas encore assez d’offres qualifiées sur la période pour dégager une tendance.</div>
      ) : (
        <>
          <div className={styles.columns}>
            <section>
              <h3>Demandées</h3>
              <p>Technologies les plus fréquentes dans les offres qualifiées.</p>
              <SignalList items={data.demanded} />
            </section>
            <section>
              <h3>Déjà dans le profil</h3>
              <p>Technologies demandées également renseignées dans ton profil JobPilot.</p>
              <SignalList items={data.matching} />
            </section>
            <section>
              <h3>Non configurées dans le profil</h3>
              <p>Technologies détectées dans les offres mais absentes de la configuration du profil.</p>
              <SignalList items={data.unconfigured} />
            </section>
          </div>
          <p className={styles.note}>
            Basé sur {data.analyzedJobs} offre(s) des {data.periodDays} derniers jours avec score ≥ {data.matchingThreshold}. « Non configurée » indique seulement que la technologie n’est pas renseignée dans le profil JobPilot ; cela ne signifie pas que tu ne la maîtrises pas.
          </p>
        </>
      )}
    </Card>
  );
}
