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

type SearchQueryDiagnostic = {
  query: string;
  statusCode: number;
  outcome: 'RESULTS' | 'NO_RESULTS' | 'ERROR';
  received: number;
  uniqueOffersAdded: number;
};

type SearchDiagnostics = {
  startedAt: string;
  requestedQueries: number;
  completedQueries: number;
  queriesWithResults: number;
  queriesWithoutResults: number;
  received: number;
  uniqueOffers: number;
  matchesCurrentCriteria: boolean;
  queries: SearchQueryDiagnostic[];
};

type ConnectorSearchCriteria = {
  code: string;
  name: string;
  scope: 'GLOBAL';
  targetJobs: string[];
  skills: string[];
  effectiveQueries: string[];
  latestSearchDiagnostics?: SearchDiagnostics | null;
  fixedCriteria: FixedCriterion[];
  limits: {
    maxItemsPerList: number;
    maxItemLength: number;
    maxEffectiveQueries: number;
  };
  note: string;
};

type ConnectorSyncResult = {
  skipped?: boolean;
  message?: string;
};

function formatDate(value: string): string {
  if (value.trim() === '') return 'Date inconnue';

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

function diagnosticTone(outcome: SearchQueryDiagnostic['outcome']): 'good' | 'warn' | 'bad' {
  if (outcome === 'RESULTS') return 'good';
  if (outcome === 'ERROR') return 'bad';
  return 'warn';
}

function diagnosticLabel(diagnostic: SearchQueryDiagnostic): string {
  if (diagnostic.outcome === 'RESULTS') return `${diagnostic.received} offre(s) reçue(s)`;
  if (diagnostic.outcome === 'ERROR') return `Erreur HTTP ${diagnostic.statusCode}`;
  return 'Aucun résultat';
}

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
  allowGlobalEditing?: boolean;
}

export function ConnectorSearchCriteriaPanel({
  connectorCode,
  allowGlobalEditing = true,
}: ConnectorSearchCriteriaPanelProps) {
  const [criteria, setCriteria] = useState<ConnectorSearchCriteria | null>(null);
  const [editing, setEditing] = useState(false);
  const [targetJobs, setTargetJobs] = useState('');
  const [skills, setSkills] = useState('');
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
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

  const testCriteria = async (): Promise<void> => {
    setTesting(true);
    setError('');
    setMessage('');

    try {
      const result = await api<ConnectorSyncResult>(
        `/connectors/${encodeURIComponent(connectorCode)}/sync`,
        { method: 'POST' },
      );

      if (result.skipped) {
        setError(result.message ?? 'La synchronisation n’a pas pu être lancée.');
        return;
      }

      await load();
      setMessage(result.message ?? 'Les critères ont été testés et les diagnostics ont été actualisés.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setTesting(false);
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
            {criteria && (
              <p className="small" style={{ marginBottom: 0 }}>
                {allowGlobalEditing
                  ? criteria.note
                  : 'Aperçu calculé à partir des critères globaux enregistrés dans la section précédente.'}
              </p>
            )}
          </div>
          {criteria && !editing && (
            <div className="actions">
              <button
                className="btn small"
                type="button"
                disabled={testing}
                onClick={() => void testCriteria()}
              >
                {testing ? 'Test en cours…' : 'Tester ces critères maintenant'}
              </button>
              {allowGlobalEditing && (
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={testing}
                  onClick={() => {
                    setEditing(true);
                    setMessage('');
                  }}
                >
                  Modifier les critères
                </button>
              )}
            </div>
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

            <div style={{ marginTop: 16 }}>
              <strong className="small">Performance de la dernière synchronisation</strong>
              {criteria.latestSearchDiagnostics ? (
                <div style={{ marginTop: 7 }}>
                  <div className="actions">
                    <Badge>{formatDate(criteria.latestSearchDiagnostics.startedAt)}</Badge>
                    <Badge>{criteria.latestSearchDiagnostics.completedQueries}/{criteria.latestSearchDiagnostics.requestedQueries} requête(s) exécutée(s)</Badge>
                    <Badge tone="good">{criteria.latestSearchDiagnostics.queriesWithResults} avec résultat(s)</Badge>
                    <Badge tone={criteria.latestSearchDiagnostics.queriesWithoutResults > 0 ? 'warn' : 'neutral'}>
                      {criteria.latestSearchDiagnostics.queriesWithoutResults} vide(s)
                    </Badge>
                    <Badge>{criteria.latestSearchDiagnostics.uniqueOffers} offre(s) unique(s)</Badge>
                    <Badge tone={criteria.latestSearchDiagnostics.matchesCurrentCriteria ? 'good' : 'warn'}>
                      {criteria.latestSearchDiagnostics.matchesCurrentCriteria
                        ? 'Correspond aux critères actuels'
                        : 'Critères modifiés depuis ce test'}
                    </Badge>
                  </div>

                  <div className="stack" style={{ marginTop: 10 }}>
                    {criteria.latestSearchDiagnostics.queries.map((diagnostic) => (
                      <div className="list-row" key={`${diagnostic.query}-${diagnostic.statusCode}`}>
                        <div style={{ flex: 1 }}>
                          <code>{diagnostic.query}</code>
                          <div className="actions" style={{ marginTop: 6 }}>
                            <Badge tone={diagnosticTone(diagnostic.outcome)}>
                              {diagnosticLabel(diagnostic)}
                            </Badge>
                            <Badge>HTTP {diagnostic.statusCode}</Badge>
                            {diagnostic.uniqueOffersAdded > 0 && (
                              <Badge tone="blue">{diagnostic.uniqueOffersAdded} nouvelle(s) offre(s) unique(s)</Badge>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="small muted" style={{ marginBottom: 0 }}>
                  Aucun diagnostic disponible. Lance un test avec le bouton ci-dessus.
                </p>
              )}
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

        {criteria && editing && allowGlobalEditing && (
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
