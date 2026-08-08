'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type AiQuota = {
  rpm: number;
  tpm: number;
  rpd: number;
  safetyPercent: number;
};

type AiSettings = {
  provider: 'gemini';
  enabled: boolean;
  model: string;
  apiKeyConfigured: boolean;
  quota: AiQuota;
  quotaUsage: {
    rpmLimit: number;
    tpmLimit: number;
    rpdLimit: number;
  };
};

export const GEMINI_PAID_TIER1_MODEL = 'gemini-3.5-flash-lite';

/**
 * Active Tier 1 limits shown by Google AI Studio for the user's
 * gemini-3.5-flash-lite project on 2026-08-09.
 *
 * These are provider ceilings, not a promise that Google will keep the same
 * values forever. JobPilot still applies its local safety percentage before
 * allowing a provider call.
 */
export const GEMINI_PAID_TIER1_PRESET: AiQuota = {
  rpm: 4_000,
  tpm: 4_000_000,
  rpd: 10_000,
  safetyPercent: 80,
};

function isPaidPreset(quota: AiQuota): boolean {
  return quota.rpm === GEMINI_PAID_TIER1_PRESET.rpm
    && quota.tpm === GEMINI_PAID_TIER1_PRESET.tpm
    && quota.rpd === GEMINI_PAID_TIER1_PRESET.rpd
    && quota.safetyPercent === GEMINI_PAID_TIER1_PRESET.safetyPercent;
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

export function GeminiPaidQuotaPresetPanel() {
  const [settings, setSettings] = useState<AiSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;

    void api<AiSettings>('/settings/ai')
      .then((response) => {
        if (active) setSettings(response);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const applyPaidPreset = async (): Promise<void> => {
    if (settings === null || settings.model !== GEMINI_PAID_TIER1_MODEL) return;

    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify({
          quotaRpm: GEMINI_PAID_TIER1_PRESET.rpm,
          quotaTpm: GEMINI_PAID_TIER1_PRESET.tpm,
          quotaRpd: GEMINI_PAID_TIER1_PRESET.rpd,
          quotaSafetyPercent: GEMINI_PAID_TIER1_PRESET.safetyPercent,
        }),
      });
      setSettings(response);
      setMessage('Limites Tier 1 Gemini 3.5 Flash-Lite appliquées. Les prochains appels utilisent immédiatement ces garde-fous locaux.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const modelMatches = settings?.model === GEMINI_PAID_TIER1_MODEL;
  const active = settings !== null && modelMatches && isPaidPreset(settings.quota);
  const usableRpm = Math.floor(GEMINI_PAID_TIER1_PRESET.rpm * GEMINI_PAID_TIER1_PRESET.safetyPercent / 100);
  const usableTpm = Math.floor(GEMINI_PAID_TIER1_PRESET.tpm * GEMINI_PAID_TIER1_PRESET.safetyPercent / 100);
  const usableRpd = Math.floor(GEMINI_PAID_TIER1_PRESET.rpd * GEMINI_PAID_TIER1_PRESET.safetyPercent / 100);

  return (
    <div style={{ marginTop: 18 }}>
      <Card>
        <div className="actions" style={{ justifyContent: 'space-between' }}>
          <div>
            <h2 className="section-title" style={{ marginBottom: 6 }}>Gemini Tier 1 — Gemini 3.5 Flash-Lite</h2>
            <p className="muted" style={{ margin: 0 }}>
              Aligne JobPilot sur les limites actives visibles dans AI Studio pour ce modèle, tout en conservant une marge locale.
            </p>
          </div>
          <Badge tone={active ? 'good' : modelMatches ? 'neutral' : 'warn'}>
            {active ? 'Tier 1 actif' : modelMatches ? 'Preset disponible' : 'Modèle différent'}
          </Badge>
        </div>

        <div className="stack" style={{ marginTop: 16 }}>
          <div className="notice">
            <strong>Limites AI Studio observées :</strong>{' '}
            {formatNumber(GEMINI_PAID_TIER1_PRESET.rpm)} RPM · {formatNumber(GEMINI_PAID_TIER1_PRESET.tpm)} TPM ·{' '}
            {formatNumber(GEMINI_PAID_TIER1_PRESET.rpd)} RPD.
            Avec la marge JobPilot de {GEMINI_PAID_TIER1_PRESET.safetyPercent} %, le plafond local devient{' '}
            {formatNumber(usableRpm)} RPM · {formatNumber(usableTpm)} TPM · {formatNumber(usableRpd)} RPD.
          </div>

          <div className="notice warning">
            <strong>Preset lié au modèle et au tier actuels.</strong> Google peut modifier les limites selon le projet, le modèle ou le tier.
            Si AI Studio change, mets à jour les champs RPM/TPM/RPD ou ce preset avant de l’utiliser.
          </div>

          {settings !== null && !modelMatches && (
            <div className="notice warning" role="alert">
              Le modèle actif est <code>{settings.model}</code>. Ce preset est réservé à <code>{GEMINI_PAID_TIER1_MODEL}</code> et ne sera pas appliqué automatiquement.
            </div>
          )}

          {settings !== null && (
            <div className="small muted">
              Configuration actuelle : {settings.quota.rpm} RPM · {formatNumber(settings.quota.tpm)} TPM ·{' '}
              {formatNumber(settings.quota.rpd)} RPD · {settings.quota.safetyPercent} % utilisables.
              {settings.apiKeyConfigured ? ' Clé Gemini configurée.' : ' Clé Gemini non configurée.'}
            </div>
          )}

          {error !== '' && <ErrorBox message={error} />}
          {message !== '' && <div className="notice" role="status">{message}</div>}

          <div className="actions">
            <button
              className="btn"
              type="button"
              disabled={loading || saving || active || !modelMatches}
              onClick={() => void applyPaidPreset()}
            >
              {saving
                ? 'Application…'
                : active
                  ? 'Limites Tier 1 déjà actives'
                  : modelMatches
                    ? 'Appliquer les limites Tier 1 observées'
                    : 'Preset indisponible pour ce modèle'}
            </button>
            <span className="small muted">Le cache IA et le fallback déterministe restent inchangés.</span>
          </div>
        </div>
      </Card>
    </div>
  );
}
