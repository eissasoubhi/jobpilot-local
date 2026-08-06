'use client';

import { useEffect, useState } from 'react';

import { Badge, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Settings } from '@/lib/types';

type CriterionDefinition = {
  key: 'targetJobs' | 'skills' | 'exclusions' | 'matchingThreshold';
  label: string;
  stage: 'COLLECTE' | 'MATCHING' | 'AUTOMATISATION';
  sentToConnectors: boolean;
  description: string;
};

const criterionDefinitions: readonly CriterionDefinition[] = [
  {
    key: 'targetJobs',
    label: 'Postes ciblés',
    stage: 'COLLECTE',
    sentToConnectors: true,
    description: 'Liste transmise à chaque connecteur. Les connecteurs compatibles la transforment en requêtes distantes ou en filtre local.',
  },
  {
    key: 'skills',
    label: 'Compétences',
    stage: 'COLLECTE',
    sentToConnectors: true,
    description: 'Liste transmise à chaque connecteur. Elle sert aussi au score de matching et comme solution de repli lorsqu’aucun intitulé n’est exploitable.',
  },
  {
    key: 'exclusions',
    label: 'Exclusions',
    stage: 'MATCHING',
    sentToConnectors: false,
    description: 'Filtre local après import. Une exclusion trouvée dans le titre ou la description rejette l’offre avec un score nul.',
  },
  {
    key: 'matchingThreshold',
    label: 'Seuil de préparation automatique',
    stage: 'AUTOMATISATION',
    sentToConnectors: false,
    description: 'Seuil local de 0 à 100 utilisé après le calcul du score. Il ne modifie pas les résultats renvoyés par les plateformes.',
  },
] as const;

export function parseGlobalCriteriaLines(value: string): string[] {
  const unique = new Map<string, string>();

  for (const line of value.split(/\r?\n/)) {
    const normalized = line.replace(/\s+/g, ' ').trim();
    const key = normalized.toLocaleLowerCase('fr');
    if (normalized !== '' && !unique.has(key)) {
      unique.set(key, normalized);
    }
  }

  return [...unique.values()];
}

export function GlobalSearchCriteriaPanel() {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [targetJobs, setTargetJobs] = useState('');
  const [skills, setSkills] = useState('');
  const [exclusions, setExclusions] = useState('');
  const [matchingThreshold, setMatchingThreshold] = useState(50);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;

    void api<Settings>('/settings')
      .then((response) => {
        if (!active) return;
        setSettings(response);
        setTargetJobs(response.targetJobs.join('\n'));
        setSkills(response.skills.join('\n'));
        setExclusions(response.exclusions.join('\n'));
        setMatchingThreshold(response.matchingThreshold);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const save = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<Settings>('/settings', {
        method: 'PUT',
        body: JSON.stringify({
          targetJobs: parseGlobalCriteriaLines(targetJobs),
          skills: parseGlobalCriteriaLines(skills),
          exclusions: parseGlobalCriteriaLines(exclusions),
          matchingThreshold: Math.min(100, Math.max(0, matchingThreshold)),
        }),
      });
      setSettings(response);
      setTargetJobs(response.targetJobs.join('\n'));
      setSkills(response.skills.join('\n'));
      setExclusions(response.exclusions.join('\n'));
      setMatchingThreshold(response.matchingThreshold);
      setMessage('Les critères globaux ont été enregistrés. Les prochains tests et synchronisations utiliseront ces valeurs.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  if (settings === null && error === '') {
    return <p className="small muted">Chargement des critères globaux…</p>;
  }

  return (
    <section aria-labelledby="global-search-criteria-title">
      <h3 id="global-search-criteria-title" style={{ marginTop: 0 }}>Clés réellement utilisées</h3>
      <p className="muted">
        JobPilot transmet actuellement seulement <code>targetJobs</code> et <code>skills</code> aux connecteurs.
        Les exclusions et le seuil sont appliqués localement après récupération des offres.
      </p>

      <div className="stack">
        {criterionDefinitions.map((criterion) => (
          <div className="list-row" key={criterion.key}>
            <div style={{ flex: 1 }}>
              <div className="actions">
                <code>{criterion.key}</code>
                <Badge tone={criterion.stage === 'COLLECTE' ? 'blue' : 'neutral'}>{criterion.stage}</Badge>
                <Badge tone={criterion.sentToConnectors ? 'good' : 'warn'}>
                  {criterion.sentToConnectors ? 'Transmis aux connecteurs' : 'Traitement local'}
                </Badge>
              </div>
              <strong className="small" style={{ display: 'block', marginTop: 7 }}>{criterion.label}</strong>
              <div className="small muted" style={{ marginTop: 4 }}>{criterion.description}</div>
            </div>
          </div>
        ))}
      </div>

      {error !== '' && <ErrorBox message={error} />}
      {message !== '' && <div className="success-box" role="status">{message}</div>}

      {settings && (
        <div className="stack" style={{ marginTop: 18 }}>
          <label htmlFor="global-target-jobs">
            Postes ciblés globaux — un par ligne
          </label>
          <textarea
            id="global-target-jobs"
            rows={7}
            value={targetJobs}
            disabled={saving}
            placeholder="Senior PHP/Symfony\nBackend PHP/Symfony\nFull-Stack Symfony/React"
            onChange={(event) => setTargetJobs(event.target.value)}
          />

          <label htmlFor="global-skills">
            Compétences globales — une par ligne
          </label>
          <textarea
            id="global-skills"
            rows={6}
            value={skills}
            disabled={saving}
            placeholder="PHP\nSymfony\nReact\nVue"
            onChange={(event) => setSkills(event.target.value)}
          />

          <label htmlFor="global-exclusions">
            Exclusions locales — une par ligne
          </label>
          <textarea
            id="global-exclusions"
            rows={5}
            value={exclusions}
            disabled={saving}
            placeholder="Stage\nAlternance\nWordPress uniquement"
            onChange={(event) => setExclusions(event.target.value)}
          />

          <label htmlFor="global-matching-threshold">
            Seuil de préparation automatique
          </label>
          <input
            id="global-matching-threshold"
            type="number"
            min="0"
            max="100"
            value={matchingThreshold}
            disabled={saving}
            onChange={(event) => setMatchingThreshold(Number(event.target.value))}
          />

          <div className="notice">
            <strong>Données de profil non encore utilisées comme filtres :</strong>{' '}
            contrats acceptés, mobilité, ville et préférence de télétravail restent modifiables dans le profil,
            mais ne sont actuellement ni envoyés aux plateformes ni intégrés au score. Cette limite est affichée ici pour éviter un faux sentiment de filtrage.
          </div>

          <div className="actions">
            <button className="btn" type="button" disabled={saving} onClick={() => void save()}>
              {saving ? 'Enregistrement…' : 'Enregistrer les critères globaux'}
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
