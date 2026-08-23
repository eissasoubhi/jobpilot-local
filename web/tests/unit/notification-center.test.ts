import { describe, expect, it } from 'vitest';

import { deriveProductNotifications } from '@/components/NotificationCenter';
import type { SourceConnector } from '@/lib/types';

function connector(overrides: Partial<SourceConnector> = {}): SourceConnector {
  return {
    id: 1,
    code: 'source',
    name: 'Source',
    mode: 'SCRAPING_HTTP',
    enabled: true,
    configured: true,
    configurationMessage: null,
    collectionAllowed: true,
    policy: {
      complianceStatus: 'ALLOWED',
      complianceLabel: 'Autorisé',
      collectionAllowed: true,
      minimumDelayMilliseconds: 0,
      respectsRobotsTxt: true,
    },
    parserVersion: null,
    health: {
      status: 'HEALTHY',
      label: 'Sain',
      alert: false,
      sampleSize: 3,
      consecutiveZeroRuns: 0,
      reasons: [],
    },
    fieldQuality: {
      received: 0,
      missingRequiredRecords: 0,
      fields: {},
      warnings: [],
    },
    status: 'SUCCESS',
    lastSyncedAt: null,
    lastSuccessfulAt: null,
    nextSyncAt: null,
    due: false,
    lastResult: { received: 0, imported: 0, merged: 0, duplicates: 0, failed: 0 },
    lastError: null,
    updatedAt: '2026-08-23T18:00:00+00:00',
    ...overrides,
  };
}

const gmailStatus = {
  connected: true,
  readPermission: true,
  sendPermission: true,
  configured: true,
  missingVariables: [],
  startUrl: '/api/integrations/gmail/start',
};

describe('deriveProductNotifications', () => {
  it('turns a revoked Gmail token into an actionable reconnect notification', () => {
    const notifications = deriveProductNotifications([
      connector({
        code: 'gmail',
        name: 'Gmail',
        mode: 'GMAIL',
        status: 'ERROR',
        health: {
          status: 'BROKEN',
          label: 'Cassé',
          alert: true,
          sampleSize: 2,
          consecutiveZeroRuns: 1,
          reasons: ['Token has been expired or revoked.'],
        },
        lastError: 'Token has been expired or revoked.',
      }),
    ], gmailStatus);

    expect(notifications).toHaveLength(1);
    expect(notifications[0]).toMatchObject({
      id: 'gmail-reconnect',
      severity: 'action',
      title: 'Gmail doit être reconnecté',
      actionLabel: 'Reconnecter Gmail',
      actionHref: '/api/integrations/gmail/start',
    });
  });

  it.each([
    ['cadremploi', 'La source custom-scraper-4 a répondu avec le statut HTTP 403.'],
    ['collective.work', 'Protection anti-automatisation détectée pour custom-scraper-6 (Cloudflare challenge).'],
  ])('classifies %s as an external limitation instead of missing configuration', (code, error) => {
    const notifications = deriveProductNotifications([
      connector({
        code,
        name: code,
        status: 'ERROR',
        lastError: error,
        health: {
          status: 'BROKEN',
          label: 'Cassé',
          alert: true,
          sampleSize: 3,
          consecutiveZeroRuns: 2,
          reasons: [error],
        },
      }),
    ], gmailStatus);

    expect(notifications).toHaveLength(1);
    expect(notifications[0].severity).toBe('info');
    expect(notifications[0].title).toContain('limité par la source');
    expect(notifications[0].actionLabel).toBe('Voir le connecteur');
    expect(notifications[0].message).not.toContain('clé');
  });

  it('asks for configuration only when an enabled connector is actually misconfigured', () => {
    const notifications = deriveProductNotifications([
      connector({
        code: 'adzuna',
        name: 'Adzuna',
        configured: false,
        configurationMessage: 'Identifiants Adzuna manquants.',
      }),
    ], gmailStatus);

    expect(notifications[0]).toMatchObject({
      id: 'connector-config:adzuna',
      severity: 'action',
      actionLabel: 'Ouvrir la configuration',
    });
  });

  it('does not notify for disabled connectors', () => {
    expect(deriveProductNotifications([
      connector({
        enabled: false,
        configured: false,
        status: 'ERROR',
        lastError: 'Erreur volontairement ignorée.',
      }),
    ], gmailStatus)).toEqual([]);
  });
});
