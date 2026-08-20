'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { api } from '@/lib/api';
import type { ApplicationGoalSnapshot } from '@/lib/application-goals';

import styles from './ApplicationGoals.module.css';

export function ApplicationGoalAlerts() {
  const [snapshot, setSnapshot] = useState<ApplicationGoalSnapshot | null>(null);

  useEffect(() => {
    let active = true;

    const load = (): void => {
      void api<ApplicationGoalSnapshot>('/application-goals')
        .then((result) => {
          if (active) setSnapshot(result);
        })
        .catch(() => {
          // Goal reminders must never block the rest of the application.
        });
    };

    load();
    const interval = window.setInterval(load, 60_000);
    window.addEventListener('jobpilot:application-goals-changed', load);

    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener('jobpilot:application-goals-changed', load);
    };
  }, []);

  if (snapshot === null) return null;

  const daily = snapshot.periods.daily;
  const showDailyReminder = daily.enabled && !daily.completed;
  if (snapshot.missed.length === 0 && !showDailyReminder) return null;

  return (
    <section className={styles.alerts} aria-label="Alertes d’objectifs de candidatures">
      {snapshot.missed.map((missed) => (
        <div className={styles.alert} role="alert" key={`${missed.period}-${missed.start}`}>
          <div className={styles.alertCopy}>
            <strong>{missed.label}</strong>
            <span>
              {missed.achieved} / {missed.target} candidature(s) envoyée(s) · déficit de {missed.remaining}.
            </span>
          </div>
          <Link href="/offres/review">Rattraper dans la Review Queue →</Link>
        </div>
      ))}

      {showDailyReminder && (
        <div className={`${styles.alert} ${styles.reminder}`} role="status">
          <div className={styles.alertCopy}>
            <strong>Objectif du jour : {daily.achieved} / {daily.target}</strong>
            <span>Il te reste {daily.remaining} candidature(s) à envoyer aujourd’hui.</span>
          </div>
          <Link href="/offres/review">Continuer →</Link>
        </div>
      )}
    </section>
  );
}
