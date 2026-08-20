'use client';

import { FormEvent, useEffect, useState } from 'react';

import { api } from '@/lib/api';
import {
  applicationGoalProgressWidth,
  enabledApplicationGoalPeriods,
  type ApplicationGoalSnapshot,
} from '@/lib/application-goals';
import { getErrorMessage } from '@/lib/errors';

import styles from './ApplicationGoals.module.css';

type GoalDraft = {
  daily: string;
  weekly: string;
  monthly: string;
};

function draftFromSnapshot(snapshot: ApplicationGoalSnapshot): GoalDraft {
  return {
    daily: String(snapshot.config.daily),
    weekly: String(snapshot.config.weekly),
    monthly: String(snapshot.config.monthly),
  };
}

function toGoalValue(value: string): number {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
}

export function ApplicationGoalsPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [snapshot, setSnapshot] = useState<ApplicationGoalSnapshot | null>(null);
  const [draft, setDraft] = useState<GoalDraft>({ daily: '0', weekly: '0', monthly: '0' });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    let active = true;
    void api<ApplicationGoalSnapshot>('/application-goals')
      .then((result) => {
        if (!active) return;
        setSnapshot(result);
        setDraft(draftFromSnapshot(result));
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => { active = false; };
  }, [refreshKey]);

  const save = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (saving) return;

    setSaving(true);
    setSaved(false);
    setError('');

    try {
      const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const result = await api<ApplicationGoalSnapshot>('/application-goals', {
        method: 'PUT',
        body: JSON.stringify({
          daily: toGoalValue(draft.daily),
          weekly: toGoalValue(draft.weekly),
          monthly: toGoalValue(draft.monthly),
          timezone: browserTimezone || snapshot?.config.timezone || 'Europe/Paris',
        }),
      });
      setSnapshot(result);
      setDraft(draftFromSnapshot(result));
      setSaved(true);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const periods = snapshot === null ? [] : enabledApplicationGoalPeriods(snapshot);

  return (
    <section className={styles.panel} aria-labelledby="application-goals-title">
      <div className={styles.heading}>
        <div>
          <h2 id="application-goals-title">Objectifs de candidatures</h2>
          <p>Suit uniquement les candidatures réellement marquées comme envoyées.</p>
        </div>
      </div>

      {snapshot === null && error === '' ? (
        <p className={styles.empty}>Chargement des objectifs…</p>
      ) : periods.length === 0 ? (
        <p className={styles.empty}>Aucun objectif actif. Configure ton rythme ci-dessous.</p>
      ) : (
        <div className={styles.periods}>
          {periods.map((period) => (
            <article className={`${styles.period} ${period.completed ? styles.completed : ''}`} key={period.period}>
              <div className={styles.periodHeader}>
                <strong>{period.label}</strong>
                <span>{period.percent}%</span>
              </div>
              <div className={styles.track} aria-hidden="true">
                <span style={{ width: `${applicationGoalProgressWidth(period)}%` }} />
              </div>
              <div className={styles.periodMeta}>
                <span>{period.achieved} / {period.target} envoyée(s)</span>
                <span>{period.completed ? 'Atteint' : `${period.remaining} restante(s)`}</span>
              </div>
            </article>
          ))}
        </div>
      )}

      <form className={styles.form} onSubmit={(event) => void save(event)}>
        <label>
          Objectif / jour
          <input
            aria-label="Objectif journalier de candidatures"
            min="0"
            max="100"
            inputMode="numeric"
            type="number"
            value={draft.daily}
            onChange={(event) => setDraft((current) => ({ ...current, daily: event.target.value }))}
          />
        </label>
        <label>
          Objectif / semaine
          <input
            aria-label="Objectif hebdomadaire de candidatures"
            min="0"
            max="500"
            inputMode="numeric"
            type="number"
            value={draft.weekly}
            onChange={(event) => setDraft((current) => ({ ...current, weekly: event.target.value }))}
          />
        </label>
        <label>
          Objectif / mois
          <input
            aria-label="Objectif mensuel de candidatures"
            min="0"
            max="2000"
            inputMode="numeric"
            type="number"
            value={draft.monthly}
            onChange={(event) => setDraft((current) => ({ ...current, monthly: event.target.value }))}
          />
        </label>
        <button className="btn small" disabled={saving} type="submit">
          {saving ? 'Enregistrement…' : 'Enregistrer'}
        </button>
      </form>

      <p className={styles.hint}>0 désactive une cadence · semaine du lundi au dimanche · fuseau horaire du navigateur.</p>
      {error !== '' && <p className={styles.error} role="alert">{error}</p>}
      {saved && <p className={styles.saved} role="status">Objectifs enregistrés.</p>}
    </section>
  );
}
