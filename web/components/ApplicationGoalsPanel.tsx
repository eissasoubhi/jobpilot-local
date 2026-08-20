'use client';

import { useEffect, useState } from 'react';

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

function paceTrackClass(tone: ApplicationGoalPaceTone): string | undefined {
  if (tone === 'completed') return styles.compactTrackCompleted;
  if (tone === 'warning') return styles.compactTrackWarning;
  if (tone === 'critical') return styles.compactTrackCritical;
  return undefined;
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
        <p className={styles.compactState}>Chargement…</p>
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
                <div className={styles.compactTrack} aria-hidden="true">
                  <span
                    className={paceTrackClass(paceTone)}
                    style={{ width: `${applicationGoalProgressWidth(period)}%` }}
                  />
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
