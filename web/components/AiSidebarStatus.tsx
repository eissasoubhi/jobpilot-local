'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import type { AiQuotaUsage } from '@/lib/aiUsage';
import { api } from '@/lib/api';

import styles from './AiSidebarStatus.module.css';

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
    const timer = window.setInterval(refresh, 30_000);

    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, []);

  const usage = settings?.quotaUsage;
  const maxPercent = usage
    ? Math.max(
        percentage(usage.rpmUsed, usage.rpmLimit),
        percentage(usage.tpmUsed, usage.tpmLimit),
        percentage(usage.rpdUsed, usage.rpdLimit),
      )
    : 0;
  const active = Boolean(settings?.enabled && settings.apiKeyConfigured);
  const needsKey = Boolean(settings?.enabled && !settings.apiKeyConfigured);
  const quotaReached = active && maxPercent >= 100;
  const warning = quotaReached || needsKey || unavailable;

  const state = unavailable
    ? 'indisponible'
    : quotaReached
      ? 'quota atteint'
      : active
        ? 'active'
        : needsKey
          ? 'clé manquante'
          : 'désactivée';
  const provider = settings?.provider === 'gemini' ? 'Gemini' : settings?.provider;
  const detail = settings ? `${provider ?? 'IA'} · ${state}` : 'Vérification…';
  const ariaLabel = settings?.model
    ? `IA ${state}. ${provider ?? settings.provider}, modèle ${settings.model}. Ouvrir les statistiques d’utilisation IA.`
    : `IA ${state}. Ouvrir les statistiques d’utilisation IA.`;

  return (
    <Link
      href="/ia"
      className={`${styles.status} ${active ? styles.active : ''} ${warning ? styles.warning : ''}`}
      aria-label={ariaLabel}
      title={usage
        ? `${settings?.model ?? ''} · RPM ${usage.rpmUsed}/${usage.rpmLimit} · TPM ${usage.tpmUsed}/${usage.tpmLimit} · RPD ${usage.rpdUsed}/${usage.rpdLimit}`
        : ariaLabel}
    >
      <span className={styles.dot} aria-hidden="true" />
      <span className={styles.copy}>
        <strong>IA</strong>
        <span>{detail}</span>
      </span>
      {maxPercent >= 80 && <span className={styles.quota}>{maxPercent}%</span>}
    </Link>
  );
}
