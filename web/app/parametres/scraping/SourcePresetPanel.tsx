'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type ScraperMode = 'AUTO' | 'HTTP' | 'BROWSER';
type ComplianceStatus = 'AUTHORIZATION_REQUIRED' | 'ASSISTED_ONLY';

type ScraperPreset = {
  slug: string;
  name: string;
  listingUrl: string;
  mode: ScraperMode;
  complianceStatus: ComplianceStatus;
  complianceLabel: string;
  canPrefill: boolean;
  reason: string;
  recommendedAction: string;
  termsUrl: string;
  reviewedAt: string;
  syncIntervalMinutes: number;
  maxPages: number;
  maxDetails: number;
};

type CreatedSource = {
  id: number;
  name: string;
};

function modeLabel(mode: ScraperMode): string {
  if (mode === 'HTTP') return 'HTTP';
  if (mode === 'BROWSER') return 'Browser';
  return 'Auto';
}

export default function SourcePresetPanel() {
  const [presets, setPresets] = useState<ScraperPreset[] | null>(null);
  const [confirmed, setConfirmed] = useState<Record<string, boolean>>({});
  const [references, setReferences] = useState<Record<string, string>>({});
  const [added, setAdded] = useState<Record<string, string>>({});
  const [adding, setAdding] = useState<string | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    void api<ScraperPreset[]>('/custom-scrapers/presets')
      .then((result) => {
        setPresets(result);
        setError('');
      })
      .catch((caughtError: unknown) => setError(getErrorMessage(caughtError)));
  }, []);

  const addPreset = async (preset: ScraperPreset): Promise<void> => {
    const reference = (references[preset.slug] ?? '').trim();
    if (!preset.canPrefill || !confirmed[preset.slug] || reference === '') {
      setError('Confirme l’autorisation et indique sa référence avant d’ajouter cette source.');
      return;
    }

    setAdding(preset.slug);
    setError('');
    try {
      const created = await api<CreatedSource>('/custom-scrapers', {
        method: 'POST',
        body: JSON.stringify({
          name: preset.name,
          listingUrl: preset.listingUrl,
          mode: preset.mode,
          enabled: false,
          authorizationConfirmed: true,
          authorizationReference: `${reference} — preset revu le ${preset.reviewedAt}`,
          syncIntervalMinutes: preset.syncIntervalMinutes,
          maxPages: preset.maxPages,
          maxDetails: preset.maxDetails,
        }),
      });
      setAdded((current) => ({ ...current, [preset.slug]: created.name }));
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setAdding(null);
    }
  };

  if (presets === null && error === '') {
    return null;
  }

  return (
    <div style={{ marginTop: 18 }}>
      <Card>
        <h2 className="section-title">Sources suggérées</h2>
        <p className="muted">
          Ce catalogue distingue la faisabilité technique de l’autorisation de collecte. Une page publique ou un robots.txt permissif ne remplace pas les conditions d’utilisation du site.
        </p>
        {error !== '' && <ErrorBox message={error} />}
        {presets !== null && (
          <div className="stack" style={{ marginTop: 12 }}>
            {presets.map((preset) => {
              const assistedOnly = preset.complianceStatus === 'ASSISTED_ONLY';
              const reference = references[preset.slug] ?? '';
              const isAdded = added[preset.slug] !== undefined;

              return (
                <div className="notice" key={preset.slug}>
                  <div className="actions" style={{ justifyContent: 'space-between' }}>
                    <strong>{preset.name}</strong>
                    <div className="actions">
                      <Badge tone={assistedOnly ? 'warn' : 'blue'}>{preset.complianceLabel}</Badge>
                      <Badge tone="blue">{modeLabel(preset.mode)}</Badge>
                    </div>
                  </div>
                  <p style={{ marginBottom: 6 }}>{preset.reason}</p>
                  <div className="muted">Action recommandée : {preset.recommendedAction}</div>
                  <div className="muted" style={{ marginTop: 6 }}>
                    Revue JobPilot : {preset.reviewedAt} · <a href={preset.termsUrl} target="_blank" rel="noreferrer">référence publique consultée</a>
                  </div>

                  {assistedOnly ? (
                    <div className="notice warning" style={{ marginTop: 10 }}>
                      JobPilot ne propose pas de bouton d’activation automatique pour cette plateforme. Utilise Gmail, une extension/import manuel ou un accès officiellement autorisé.
                    </div>
                  ) : isAdded ? (
                    <div className="notice" style={{ marginTop: 10 }}>
                      <strong>{added[preset.slug]}</strong> a été ajoutée <strong>désactivée</strong>. Recharge la page si nécessaire, puis utilise « Tester le site » et « Prévisualiser les offres » avant de l’activer.
                    </div>
                  ) : (
                    <div className="stack" style={{ marginTop: 10 }}>
                      <label>
                        Référence de ton autorisation
                        <input
                          value={reference}
                          onChange={(event) => setReferences((current) => ({ ...current, [preset.slug]: event.target.value }))}
                          placeholder="Ex. accord écrit, ticket support, contrat ou lien précis"
                        />
                      </label>
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          checked={confirmed[preset.slug] ?? false}
                          onChange={(event) => setConfirmed((current) => ({ ...current, [preset.slug]: event.target.checked }))}
                        />
                        Je confirme avoir une autorisation applicable à cette collecte automatisée.
                      </label>
                      <div className="actions">
                        <button
                          className="btn secondary"
                          type="button"
                          disabled={adding !== null || !(confirmed[preset.slug] ?? false) || reference.trim() === ''}
                          onClick={() => void addPreset(preset)}
                        >
                          {adding === preset.slug ? 'Ajout…' : 'Ajouter désactivée'}
                        </button>
                        <a href={preset.listingUrl} target="_blank" rel="noreferrer">Voir la page cible</a>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </Card>
    </div>
  );
}
