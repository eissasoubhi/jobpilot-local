'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { api } from '@/lib/api';

type AiQuotaUsage = {
  rpmUsed: number;
  tpmUsed: number;
  rpdUsed: number;
  rpmLimit: number;
  tpmLimit: number;
  rpdLimit: number;
};

type AiSettings = {
  provider: string;
  enabled: boolean;
  model: string;
  apiKeyConfigured: boolean;
  quotaUsage?: AiQuotaUsage;
};

function percentage(used: number, limit: number): number {
  if (!Number.isFinite(used) || !Number.isFinite(limit) || limit <= 0) return 0;
  return Math.min(100, Math.max(0, Math.round((used / limit) * 100)));
}

export function AiSidebarStatus() {
  const [settings, setSettings] = useState<AiSettings | null>(null);
  const [unavailable, setUnavailable] = useState(false);

  useEffect(() => {
    let active = true;

    const refresh = (): void => {
      void api<AiSettings>('/settings/ai')
        .then((response) => {
          if (!active) return;
          setSettings(response);
          setUnavailable(false);
        })
        .catch(() => {
          if (active) setUnavailable(true);
        });
    };

    refresh();
    const timer = window.setInterval(refresh, 15000);

    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, []);

  const usage = settings?.quotaUsage;
  const rpmPercent = usage ? percentage(usage.rpmUsed, usage.rpmLimit) : 0;
  const tpmPercent = usage ? percentage(usage.tpmUsed, usage.tpmLimit) : 0;
  const rpdPercent = usage ? percentage(usage.rpdUsed, usage.rpdLimit) : 0;
  const maxPercent = Math.max(rpmPercent, tpmPercent, rpdPercent);
  const aiActive = Boolean(settings?.enabled && settings.apiKeyConfigured);
  const needsKey = Boolean(settings?.enabled && !settings.apiKeyConfigured);
  const quotaReached = aiActive && maxPercent >= 100;

  const label = unavailable
    ? 'État IA indisponible'
    : quotaReached
      ? 'IA active · quota atteint'
      : aiActive
        ? 'IA active'
        : needsKey
          ? 'IA activée · clé manquante'
          : 'IA désactivée';

  const stateClass = quotaReached || needsKey
    ? 'is-warning'
    : aiActive
      ? 'is-active'
      : 'is-inactive';

  return (
    <Link
      href="/parametres/integrations"
      className={`ai-sidebar-status ${stateClass}`}
      aria-label={`${label}. Ouvrir la configuration IA`}
      title={usage
        ? `RPM ${usage.rpmUsed}/${usage.rpmLimit} · TPM ${usage.tpmUsed}/${usage.tpmLimit} · RPD ${usage.rpdUsed}/${usage.rpdLimit}`
        : label}
    >
      <div className="ai-sidebar-status-header">
        <span className="ai-status-dot" aria-hidden="true" />
        <strong>{label}</strong>
      </div>
      {settings && (
        <div className="ai-sidebar-model">
          {settings.provider === 'gemini' ? 'Gemini' : settings.provider} · {settings.model}
        </div>
      )}
      {usage && (
        <>
          <div className="ai-sidebar-quota-line">
            <span>Quota max</span>
            <strong>{maxPercent} %</strong>
          </div>
          <div className="ai-quota-track" aria-label={`Utilisation maximale du quota IA ${maxPercent} %`}>
            <span style={{ width: `${maxPercent}%` }} />
          </div>
          <div className="ai-sidebar-quota-detail">
            RPM {rpmPercent}% · TPM {tpmPercent}% · Jour {rpdPercent}%
          </div>
        </>
      )}
      {!settings && !unavailable && <div className="ai-sidebar-model">Vérification…</div>}
    </Link>
  );
}
