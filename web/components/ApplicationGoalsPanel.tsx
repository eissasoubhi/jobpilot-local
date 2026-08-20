'use client';

import { useEffect, useState } from 'react';

import { api } from '@/lib/api';
import {
  applicationGoalProgressWidth,
  enabledApplicationGoalPeriods,
  type ApplicationGoalSnapshot,
} from '@/lib/application-goals';
import { getErrorMessage } from '@/lib/errors';

import styles from './ApplicationGoals.module.css';

export function ApplicationGoalsPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [snapshot, setSnapshot] = useState<ApplicationGoalSnapshot | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    void api<ApplicationGoalSnapshot>('/application-goals')
      .then((result) => {
        if (!active) return;
        setSnapshot(result);
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => { active = false; };
  }, [refreshKey]);

  const periods = snapshot === null ? [] : enabledApplicationGoalPeriods(snapshot);

  return (
    <section className={styles.compactPanel} aria-label="Progression des objectifs de candidatures">
      {snapshot === null && error === '' ? (
        <p className={styles.compactState}>Chargement…</p>
      ) : error !== '' ? (
        <p className={styles.compactError} role="alert">{error}</p>
      ) : periods.length === 0 ? (
        <p className={styles.compactState}>Aucun objectif actif.</p>
      ) : (
        <div className={styles.compactPeriods}>
          {periods.map((period) => (
            <article className={styles.compactPeriod} key={period.period}>
              <div className={styles.compactPeriodHeader}>
                <strong>{period.label}</strong>
                <span>{period.achieved}/{period.target} · {period.percent}%</span>
              </div>
              <div className={styles.compactTrack} aria-hidden="true">
                <span
                  className={period.completed ? styles.compactTrackCompleted : undefined}
                  style={{ width: `${applicationGoalProgressWidth(period)}%` }}
                />
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
