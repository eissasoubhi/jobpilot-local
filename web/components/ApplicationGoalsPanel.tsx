'use client';

import { useEffect, useState } from 'react';

import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { ProgressBar } from '@/components/UI';
import { api } from '@/lib/api';
import {
  applicationGoalPaceTone,
  applicationGoalProgressWidth,
  enabledApplicationGoalPeriods,
  type ApplicationGoalPaceTone,
  type ApplicationGoalSnapshot,
} from '@/lib/application-goals';
import { getErrorMessage } from '@/lib/errors';

import styles from './ApplicationGoals.module.css';

function progressTone(tone: ApplicationGoalPaceTone): 'neutral' | 'good' | 'warn' | 'bad' {
  if (tone === 'completed') return 'good';
  if (tone === 'warning') return 'warn';
  if (tone === 'critical') return 'bad';
  return 'neutral';
}

export function ApplicationGoalsPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [snapshot, setSnapshot] = useState<ApplicationGoalSnapshot | null>(null);
  const [error, setError] = useState('');
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    let active = true;
    void api<ApplicationGoalSnapshot>('/application-goals')
      .then((result) => {
        if (!active) return;
        setSnapshot(result);
        setNow(Date.now());
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => { active = false; };
  }, [refreshKey]);

  useEffect(() => {
    const interval = window.setInterval(() => setNow(Date.now()), 60_000);
    return () => window.clearInterval(interval);
  }, []);

  const periods = snapshot === null ? [] : enabledApplicationGoalPeriods(snapshot);

  return (
    <section className={styles.compactPanel} aria-label="Progression des objectifs de candidatures">
      {snapshot === null && error === '' ? (
        <SkeletonGroup
          className={styles.compactPeriods}
          label="Chargement des objectifs de candidatures"
        >
          {[0, 1, 2].map((index) => (
            <div className={styles.compactPeriod} key={index}>
              <div className={styles.compactPeriodHeader}>
                <Skeleton width="45%" height={12} />
                <Skeleton width={54} height={11} />
              </div>
              <Skeleton width="100%" height={6} />
            </div>
          ))}
        </SkeletonGroup>
      ) : error !== '' ? (
        <p className={styles.compactError} role="alert">{error}</p>
      ) : periods.length === 0 ? (
        <p className={styles.compactState}>Aucun objectif actif.</p>
      ) : (
        <div className={styles.compactPeriods}>
          {periods.map((period) => {
            const paceTone = applicationGoalPaceTone(period, now);

            return (
              <article
                className={styles.compactPeriod}
                data-pace-tone={paceTone}
                key={period.period}
              >
                <div className={styles.compactPeriodHeader}>
                  <strong>{period.label}</strong>
                  <span>{period.achieved}/{period.target} · {period.percent}%</span>
                </div>
                <ProgressBar
                  value={applicationGoalProgressWidth(period)}
                  label={`Progression de l’objectif ${period.label}`}
                  valueText={`${period.achieved} sur ${period.target} · ${period.percent}%`}
                  tone={progressTone(paceTone)}
                  size="compact"
                />
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
