'use client';

import { useEffect, useState } from 'react';

import {
  CustomScraperSearchDiagnosticsPanel,
  type SearchableCustomScraperSource,
} from '@/app/parametres/scraping/CustomScraperSearchDiagnosticsPanel';
import { Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

export default function CustomScraperSearchesPage() {
  const [sources, setSources] = useState<SearchableCustomScraperSource[] | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<SearchableCustomScraperSource[]>('/custom-scrapers')
      .then((result) => {
        if (!active) return;
        setSources(result);
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (!active) return;
        setSources([]);
        setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const updateSource = (updated: SearchableCustomScraperSource): void => {
    setSources((current) => (current ?? []).map((source) => (
      source.id === updated.id ? { ...source, ...updated } : source
    )));
  };

  return (
    <>
      <PageHeader
        title="Recherches & diagnostics"
        description="Configure les mots-clés de chaque source, vérifie les URL finales sans réseau, puis teste la collecte publique avec quotas, déduplication et diagnostics par recherche."
      />

      <div className="notice" style={{ marginBottom: 16 }}>
        <strong>Ordre recommandé :</strong>{' '}
        enregistre les mots-clés → vérifie le plan → lance le test réseau. Les réglages techniques restent séparés du registre principal pour garder l’écran Sources simple.
      </div>

      {error !== '' && <ErrorBox message={error} />}

      {sources === null && error === '' ? (
        <Card><Loading /></Card>
      ) : sources !== null && sources.length === 0 ? (
        <Card>
          <Empty>Ajoute d’abord une source autorisée dans l’onglet Sources.</Empty>
        </Card>
      ) : sources !== null ? (
        <div className="stack">
          {sources.map((source) => (
            <Card key={source.id}>
              <div className="actions" style={{ justifyContent: 'space-between', marginBottom: 12 }}>
                <div>
                  <h2 className="section-title" style={{ marginBottom: 4 }}>{source.name}</h2>
                  <div className="muted">Jusqu’à {source.maxPages} page(s) configurée(s) par recherche avant application du budget global.</div>
                </div>
              </div>
              <CustomScraperSearchDiagnosticsPanel source={source} onUpdated={updateSource} />
            </Card>
          ))}
        </div>
      ) : null}
    </>
  );
}
