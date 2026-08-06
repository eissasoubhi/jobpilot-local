'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type FixedCriterion = {
  key: string;
  label: string;
  value: string;
};

type ConnectorSearchCriteria = {
  code: string;
  name: string;
  scope: 'GLOBAL';
  targetJobs: string[];
  skills: string[];
  effectiveQueries: string[];
  fixedCriteria: FixedCriterion[];
  limits: {
    maxItemsPerList: number;
    maxItemLength: number;
    maxEffectiveQueries: number;
  };
  note: string;
};

export function parseCriteriaLines(value: string): string[] {
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

interface ConnectorSearchCriteriaPanelProps {
  connectorCode: string;
}

export function ConnectorSearchCriteriaPanel({ connectorCode }: ConnectorSearchCriteriaPanelProps) {
  const [criteria, setCriteria] = useState<ConnectorSearchCriteria | null>(null);
  const [editing, setEditing] = useState(false);
  const [targetJobs, setTargetJobs] = useState('');
  const [skills, setSkills] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const response = await api<ConnectorSearchCriteria>(
        `/connectors/${encodeURIComponent(connectorCode)}/criteria`,
      );
      setCriteria(response);
      setTargetJobs(response.targetJobs.join('\n'));
      setSkills(response.skills.join('\n'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, [connectorCode]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<ConnectorSearchCriteria>(
        `/connectors/${encodeURIComponent(connectorCode)}/criteria`,
        {
          method: 'PUT',
          body: JSON.stringify({
            targetJobs: parseCriteriaLines(targetJobs),
            skills: parseCriteriaLines(skills),
          }),
        },
      );
      setCriteria(response);
      setTargetJobs(response.targetJobs.join('\n'));
      setSkills(response.skills.join('\n'));
      setEditing(false);
      setMessage('Les critères de recherche ont été enregistrés.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  if (criteria === null && error === '') {
    return <p className="small muted" style={{ marginTop: 14 }}>Chargement des critères de recherche…</p>;
  }

  return (
    <section aria-labelledby={`connector-criteria-${connectorCode}`} style={{ marginTop: 18 }}>
      <div className="notice">
        <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h4 id={`connector-criteria-${connectorCode}`} style={{ margin: 0 }}>
              Critères de recherche
            </h4>
            {criteria && <p className="small" style={{ marginBottom: 0 }}>{criteria.note}</p>}
          </div>
          {criteria && !editing && (
            <button
              className="btn secondary small"
              type="button"
              onClick={() => {
                setEditing(true);
                setMessage('');
              }}
            >
              Modifier les critères
            </button>
          )}
        </div>

        {error !== '' && <ErrorBox message={error} />}
        {message !== '' && <div className="success-box" role="status">{message}</div>}

        {criteria && !editing && (
          <>
            <div style={{ marginTop: 14 }}>
              <strong className="small">Requêtes réellement envoyées à France Travail</strong>
              <div className="actions" style={{ marginTop: 7 }}>
                {criteria.effectiveQueries.length === 0 ? (
                  <Badge tone="warn">Aucune requête exploitable</Badge>
                ) : criteria.effectiveQueries.map((query) => (
                  <Badge key={query} tone="blue">{query}</Badge>
                ))}
              </div>
            </div>

            <div className="grid two" style={{ marginTop: 14 }}>
              <div>
                <strong className="small">Intitulés ciblés</strong>
                <div className="small muted" style={{ marginTop: 5 }}>
                  {criteria.targetJobs.length > 0 ? criteria.targetJobs.join(' · ') : 'Aucun'}
                </div>
              </div>
              <div>
                <strong className="small">Compétences de repli</strong>
                <div className="small muted" style={{ marginTop: 5 }}>
                  {criteria.skills.length > 0 ? criteria.skills.join(' · ') : 'Aucune'}
                </div>
              </div>
            </div>

            <div className="actions" style={{ marginTop: 12 }}>
              {criteria.fixedCriteria.map((criterion) => (
                <Badge key={criterion.key}>{criterion.label} : {criterion.value}</Badge>
              ))}
            </div>
          </>
        )}

        {criteria && editing && (
          <div className="stack" style={{ marginTop: 14 }}>
            <label htmlFor={`connector-target-jobs-${connectorCode}`}>
              Intitulés ciblés — un par ligne
            </label>
            <textarea
              id={`connector-target-jobs-${connectorCode}`}
              value={targetJobs}
              rows={7}
              placeholder="Senior PHP/Symfony\nBackend PHP/Symfony\nFull-Stack Symfony/React"
              disabled={saving}
              onChange={(event) => setTargetJobs(event.target.value)}
            />
            <span className="small muted">
              France Travail retire certains termes génériques comme « senior » ou « developer » avant l’envoi.
            </span>

            <label htmlFor={`connector-skills-${connectorCode}`}>
              Compétences de repli — une par ligne
            </label>
            <textarea
              id={`connector-skills-${connectorCode}`}
              value={skills}
              rows={5}
              placeholder="PHP\nSymfony\nReact"
              disabled={saving}
              onChange={(event) => setSkills(event.target.value)}
            />
            <span className="small muted">
              Elles sont utilisées uniquement lorsqu’aucun intitulé ne produit de requête exploitable.
            </span>

            <div className="actions">
              <button className="btn small" type="button" disabled={saving} onClick={() => void save()}>
                {saving ? 'Enregistrement…' : 'Enregistrer les critères'}
              </button>
              <button
                className="btn secondary small"
                type="button"
                disabled={saving}
                onClick={() => {
                  setEditing(false);
                  setTargetJobs(criteria.targetJobs.join('\n'));
                  setSkills(criteria.skills.join('\n'));
                  setError('');
                }}
              >
                Annuler
              </button>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
