'use client';

import { useEffect, useState } from 'react';

import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Badge, Button, ErrorBox, FormField, InlineFeedback } from '@/components/UI';
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

function GlobalSearchCriteriaSkeleton() {
  return (
    <SkeletonGroup label="Chargement des critères globaux">
      <div className="stack" aria-hidden="true">
        <Skeleton width={220} height={22} />
        <Skeleton width="82%" height={16} />
        <Skeleton width="68%" height={16} />

        {criterionDefinitions.map((criterion) => (
          <div className="list-row" key={criterion.key}>
            <div style={{ flex: 1 }}>
              <div className="actions">
                <Skeleton width={110} height={24} />
                <Skeleton width={82} height={24} />
                <Skeleton width={150} height={24} />
              </div>
              <div style={{ marginTop: 9 }}><Skeleton width="34%" height={16} /></div>
              <div style={{ marginTop: 7 }}><Skeleton width="88%" height={14} /></div>
            </div>
          </div>
        ))}

        <div style={{ marginTop: 8 }}><Skeleton width={210} height={14} /></div>
        <Skeleton height={118} />
        <Skeleton width={200} height={14} />
        <Skeleton height={102} />
        <Skeleton width={190} height={14} />
        <Skeleton height={86} />
        <Skeleton width={210} height={14} />
        <Skeleton width={180} height={40} />
      </div>
    </SkeletonGroup>
  );
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
    return <GlobalSearchCriteriaSkeleton />;
  }

  return (
    <section aria-labelledby="global-search-criteria-title">
      <h3 id="global-search-criteria-title" style={{ marginTop: 0 }}>Clés réellement utilisées</h3>
      <p className="muted">
        JobPilot transmet actuellement seulement <code>targetJobs</code> et <code>skills</code> aux connecteurs.
        Les exclusions, le seuil et les préférences d’éligibilité du Profil sont appliqués localement après récupération des offres.
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
      {message !== '' && <InlineFeedback tone="success">{message}</InlineFeedback>}

      {settings && (
        <div className="stack" style={{ marginTop: 18 }}>
          <FormField label="Postes ciblés globaux — un par ligne">
            <textarea
              id="global-target-jobs"
              rows={7}
              value={targetJobs}
              disabled={saving}
              placeholder="Senior PHP/Symfony\nBackend PHP/Symfony\nFull-Stack Symfony/React"
              onChange={(event) => setTargetJobs(event.target.value)}
            />
          </FormField>

          <FormField label="Compétences globales — une par ligne">
            <textarea
              id="global-skills"
              rows={6}
              value={skills}
              disabled={saving}
              placeholder="PHP\nSymfony\nReact\nVue"
              onChange={(event) => setSkills(event.target.value)}
            />
          </FormField>

          <FormField label="Exclusions locales — une par ligne">
            <textarea
              id="global-exclusions"
              rows={5}
              value={exclusions}
              disabled={saving}
              placeholder="Stage\nAlternance\nWordPress uniquement"
              onChange={(event) => setExclusions(event.target.value)}
            />
          </FormField>

          <FormField label="Seuil de préparation automatique">
            <input
              id="global-matching-threshold"
              type="number"
              min="0"
              max="100"
              value={matchingThreshold}
              disabled={saving}
              onChange={(event) => setMatchingThreshold(Number(event.target.value))}
            />
          </FormField>

          <div className="notice">
            <strong>Préférences du profil actives :</strong>{' '}
            les contrats acceptés et la préférence télétravail / site sont des filtres locaux d’éligibilité.
            Une offre hors de ces critères n’est ni préparée ni envoyée automatiquement. La mobilité et la ville restent des informations de profil et des signaux de classement, pas des filtres d’exclusion stricts.
          </div>

          <div className="actions">
            <Button loading={saving} onClick={() => void save()}>
              Enregistrer les critères globaux
            </Button>
          </div>
        </div>
      )}
    </section>
  );
}