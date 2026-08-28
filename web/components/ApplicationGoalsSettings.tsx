'use client';

import { type FormEvent, useEffect, useState } from 'react';

import { Button, Card } from '@/components/UI';
import { api } from '@/lib/api';
import type { ApplicationGoalSnapshot } from '@/lib/application-goals';
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

export function ApplicationGoalsSettings() {
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
  }, []);

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
      window.dispatchEvent(new Event('jobpilot:application-goals-changed'));
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className={styles.settingsSection}>
      <Card>
        <h2 className="section-title">Objectifs de candidatures</h2>
        <p className="muted">Configure ici le rythme de candidatures. La Review Queue affiche uniquement la progression.</p>

        {snapshot === null && error === '' ? (
          <p className={styles.settingsHint}>Chargement des objectifs…</p>
        ) : (
          <form className={styles.settingsForm} onSubmit={(event) => void save(event)}>
            <div className={styles.settingsGrid}>
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
            </div>

            <div className={styles.settingsFooter}>
              <p className={styles.settingsHint}>0 désactive une cadence · semaine du lundi au dimanche · fuseau horaire du navigateur.</p>
              <Button loading={saving} size="small" type="submit">
                {saving ? 'Enregistrement…' : 'Enregistrer les objectifs'}
              </Button>
            </div>

            {error !== '' && <p className={styles.error} role="alert">{error}</p>}
            {saved && <p className={styles.saved} role="status">Objectifs enregistrés.</p>}
          </form>
        )}
      </Card>
    </div>
  );
}
