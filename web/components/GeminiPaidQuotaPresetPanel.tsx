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

export const GEMINI_PAID_BALANCED_PRESET: AiQuota = {
  rpm: 60,
  tpm: 1_000_000,
  rpd: 2_000,
  safetyPercent: 80,
};

function isPaidPreset(quota: AiQuota): boolean {
  return quota.rpm === GEMINI_PAID_BALANCED_PRESET.rpm
    && quota.tpm === GEMINI_PAID_BALANCED_PRESET.tpm
    && quota.rpd === GEMINI_PAID_BALANCED_PRESET.rpd
    && quota.safetyPercent === GEMINI_PAID_BALANCED_PRESET.safetyPercent;
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
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify({
          quotaRpm: GEMINI_PAID_BALANCED_PRESET.rpm,
          quotaTpm: GEMINI_PAID_BALANCED_PRESET.tpm,
          quotaRpd: GEMINI_PAID_BALANCED_PRESET.rpd,
          quotaSafetyPercent: GEMINI_PAID_BALANCED_PRESET.safetyPercent,
        }),
      });
      setSettings(response);
      setMessage('Profil Gemini payant appliqué. Les prochains appels utiliseront immédiatement ces garde-fous locaux.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const active = settings !== null && isPaidPreset(settings.quota);

  return (
    <div style={{ marginTop: 18 }}>
      <Card>
        <div className="actions" style={{ justifyContent: 'space-between' }}>
          <div>
            <h2 className="section-title" style={{ marginBottom: 6 }}>Gemini payant — profil de quota JobPilot</h2>
            <p className="muted" style={{ margin: 0 }}>
              Augmente le débit local autorisé pour le matching et le filtrage des offres, tout en gardant une marge de sécurité.
            </p>
          </div>
          <Badge tone={active ? 'good' : 'neutral'}>
            {active ? 'Profil payant actif' : 'Profil disponible'}
          </Badge>
        </div>

        <div className="stack" style={{ marginTop: 16 }}>
          <div className="notice">
            <strong>Preset équilibré :</strong>{' '}
            {GEMINI_PAID_BALANCED_PRESET.rpm} RPM · {formatNumber(GEMINI_PAID_BALANCED_PRESET.tpm)} TPM ·{' '}
            {formatNumber(GEMINI_PAID_BALANCED_PRESET.rpd)} RPD · marge {GEMINI_PAID_BALANCED_PRESET.safetyPercent} %.
            JobPilot utilisera au maximum 48 RPM, 800 000 TPM et 1 600 RPD.
          </div>

          <div className="notice warning">
            <strong>Ce preset n’est pas le quota contractuel Google.</strong> Les limites Gemini réelles varient selon le modèle, le projet et le tier.
            Si AI Studio affiche une limite plus basse pour ton projet, recopie cette valeur dans les champs de quota au-dessus.
          </div>

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
              disabled={loading || saving || active}
              onClick={() => void applyPaidPreset()}
            >
              {saving ? 'Application…' : active ? 'Profil payant déjà actif' : 'Appliquer le profil payant recommandé'}
            </button>
            <span className="small muted">Le cache IA et le fallback déterministe restent inchangés.</span>
          </div>
        </div>
      </Card>
    </div>
  );
}
