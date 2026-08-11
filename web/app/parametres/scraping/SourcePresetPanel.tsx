'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './scraping.module.css';

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
  reviewDueAt: string;
  reviewFresh: boolean;
  reviewTtlDays: number;
  reviewDaysRemaining: number;
  reviewRenewalRecommended: boolean;
  syncIntervalMinutes: number;
  maxPages: number;
  maxDetails: number;
  gmailSupported: boolean;
  gmailPlatformCode: string;
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
    if (!preset.reviewFresh) {
      setError('La revue JobPilot de cette source a expiré. Revalide les conditions publiques avant de l’ajouter.');
      return;
    }
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
    <div className={styles.presetSection}>
      <Card>
        <h2 className="section-title">Sources suggérées</h2>
        <p className={styles.presetIntro}>
          Le catalogue sépare la faisabilité technique du droit de collecter. Les plateformes bloquées en scraping restent utilisables via Gmail ou import assisté. Les revues de conformité expirent après 90 jours.
        </p>
        {error !== '' && <ErrorBox message={error} />}
        {presets !== null && (
          <div className={styles.presetGrid}>
            {presets.map((preset) => {
              const assistedOnly = preset.complianceStatus === 'ASSISTED_ONLY';
              const reference = references[preset.slug] ?? '';
              const isAdded = added[preset.slug] !== undefined;

              return (
                <div className={`notice ${styles.presetCard}`} key={preset.slug}>
                  <div className={styles.presetHeader}>
                    <h3 className={styles.presetTitle}>{preset.name}</h3>
                    <div className={styles.presetBadges}>
                      <Badge tone={assistedOnly ? 'warn' : 'blue'}>{preset.complianceLabel}</Badge>
                      {!preset.reviewFresh && <Badge tone="warn">Revue expirée</Badge>}
                      {preset.reviewRenewalRecommended && (
                        <Badge tone="warn">Revue à renouveler · {preset.reviewDaysRemaining} j</Badge>
                      )}
                      {preset.gmailSupported && <Badge tone="good">Gmail pris en charge</Badge>}
                      <Badge tone="blue">{modeLabel(preset.mode)}</Badge>
                    </div>
                  </div>

                  <p className={styles.presetReason}>{preset.reason}</p>
                  <div className={styles.recommendation}>
                    <strong>Action recommandée</strong><br />
                    {preset.recommendedAction}
                  </div>
                  <div className={styles.presetMeta}>
                    <span>Revue JobPilot : {preset.reviewedAt} · échéance : {preset.reviewDueAt}</span>
                    <a href={preset.termsUrl} target="_blank" rel="noreferrer">Référence publique consultée</a>
                  </div>

                  {preset.reviewRenewalRecommended && preset.reviewFresh && (
                    <div className={styles.warningBox}>
                      Revue à renouveler dans {preset.reviewDaysRemaining} jour{preset.reviewDaysRemaining === 1 ? '' : 's'}. L’ajout reste possible jusque-là si les autres conditions sont remplies.
                    </div>
                  )}

                  {preset.gmailSupported && (
                    <div className={styles.gmailBox}>
                      JobPilot reconnaît déjà les liens <strong>{preset.gmailPlatformCode}</strong> reçus dans les alertes Gmail.
                      <div className={styles.compactActions}>
                        <a className="btn secondary" href="/messages">Ouvrir Gmail JobPilot</a>
                      </div>
                    </div>
                  )}

                  {assistedOnly ? (
                    <div className={styles.warningBox}>
                      Scraping automatique non proposé. Utilise une alerte e-mail, Gmail JobPilot, l’extension/import manuel ou un accès officiellement autorisé.
                    </div>
                  ) : !preset.reviewFresh ? (
                    <div className={styles.warningBox}>
                      La revue de conformité JobPilot a dépassé {preset.reviewTtlDays} jours. Relis la référence publique avant tout ajout ou activation. Gmail reste utilisable entre-temps.
                    </div>
                  ) : isAdded ? (
                    <div className={styles.successBox}>
                      <strong>{added[preset.slug]}</strong> a été ajoutée <strong>désactivée</strong>. Teste puis prévisualise la source avant activation.
                    </div>
                  ) : (
                    <div className={styles.authorizationForm}>
                      <label>
                        Référence de ton autorisation
                        <input
                          value={reference}
                          onChange={(event) => setReferences((current) => ({ ...current, [preset.slug]: event.target.value }))}
                          placeholder="Accord écrit, ticket support, contrat ou lien précis"
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
                      <div className={styles.compactActions}>
                        <button
                          className="btn secondary"
                          type="button"
                          disabled={adding !== null || !preset.canPrefill || !(confirmed[preset.slug] ?? false) || reference.trim() === ''}
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
