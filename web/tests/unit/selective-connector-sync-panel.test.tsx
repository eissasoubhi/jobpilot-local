import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { SelectiveConnectorSyncPanel } from '@/components/SelectiveConnectorSyncPanel';
import type { SourceConnector } from '@/lib/types';

function connector(overrides: Partial<SourceConnector> & Pick<SourceConnector, 'code' | 'name'>): SourceConnector {
  const { code, name, ...rest } = overrides;

  return {
    id: 1,
    code,
    name,
    mode: 'API',
    enabled: true,
    configured: true,
    configurationMessage: null,
    collectionAllowed: true,
    policy: {
      complianceStatus: 'ALLOWED',
      complianceLabel: 'Autorisé',
      collectionAllowed: true,
      reviewedAt: null,
      note: null,
      maxRequestsPerSync: null,
      dailyQuota: null,
      minimumDelayMilliseconds: 0,
      respectsRobotsTxt: true,
    },
    parserVersion: null,
    health: {
      status: 'HEALTHY',
      label: 'Sain',
      alert: false,
      sampleSize: 1,
      consecutiveZeroRuns: 0,
      lastExtractionRate: 100,
      baselineAverageReceived: 10,
      reasons: [],
    },
    fieldQuality: {
      received: 0,
      requiredCompleteness: null,
      recommendedCompleteness: null,
      overallCompleteness: null,
      missingRequiredRecords: 0,
      fields: {},
      warnings: [],
    },
    status: 'READY',
    lastSyncedAt: null,
    lastSuccessfulAt: null,
    nextSyncAt: null,
    due: true,
    lastResult: { received: 0, imported: 0, merged: 0, duplicates: 0, failed: 0 },
    lastError: null,
    updatedAt: '2026-08-24T05:00:00Z',
    ...rest,
  };
}

const connectors = [
  connector({ code: 'apec', name: 'Apec' }),
  connector({ code: 'adzuna', name: 'Adzuna' }),
  connector({ code: 'indeed', name: 'Indeed', enabled: false }),
  connector({
    code: 'linkedin',
    name: 'LinkedIn',
    collectionAllowed: false,
    policy: {
      complianceStatus: 'EMAIL_OR_EXTENSION_ONLY',
      complianceLabel: 'Extension uniquement',
      collectionAllowed: false,
      reviewedAt: null,
      note: 'Automatisation directe interdite',
      maxRequestsPerSync: null,
      dailyQuota: null,
      minimumDelayMilliseconds: 0,
      respectsRobotsTxt: true,
    },
  }),
];

describe('SelectiveConnectorSyncPanel', () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback: FrameRequestCallback) => {
      callback(0);
      return 1;
    });
  });

  it('keeps ineligible connectors visible but impossible to select', () => {
    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Choisir les connecteurs' }));

    expect(screen.getByRole('checkbox', { name: 'Synchroniser Apec' })).toBeEnabled();
    expect(screen.getByRole('checkbox', { name: 'Synchroniser Adzuna' })).toBeEnabled();
    expect(screen.getByRole('checkbox', { name: 'Synchroniser Indeed' })).toBeDisabled();
    expect(screen.getByRole('checkbox', { name: 'Synchroniser LinkedIn' })).toBeDisabled();
    expect(screen.getByText('Connecteur désactivé')).toBeInTheDocument();
    expect(screen.getByText('Automatisation directe interdite')).toBeInTheDocument();
  });

  it('submits exactly the selected eligible connector codes', () => {
    const synchronize = vi.fn();
    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={synchronize} />);

    fireEvent.click(screen.getByRole('button', { name: 'Choisir les connecteurs' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Synchroniser Adzuna' }));

    expect(screen.getByRole('button', { name: 'Synchroniser 1 connecteur' })).toBeEnabled();
    fireEvent.click(screen.getByRole('button', { name: 'Synchroniser 1 connecteur' }));

    expect(synchronize).toHaveBeenCalledWith(['apec']);
    expect(window.localStorage.getItem('jobpilot.manualSyncConnectorCodes')).toBe('["apec"]');
  });

  it('supports select-none, select-all and blocks an empty manual run', () => {
    const synchronize = vi.fn();
    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={synchronize} />);

    fireEvent.click(screen.getByRole('button', { name: 'Choisir les connecteurs' }));
    fireEvent.click(screen.getByRole('button', { name: 'Tout désélectionner' }));

    expect(screen.getByRole('button', { name: 'Synchroniser 0 connecteur' })).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Tout sélectionner' }));
    expect(screen.getByRole('button', { name: 'Synchroniser 2 connecteurs' })).toBeEnabled();
  });

  it('keeps the quick action as a full eligible synchronization', () => {
    const synchronize = vi.fn();
    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={synchronize} />);

    fireEvent.click(screen.getByRole('button', { name: 'Tout synchroniser' }));

    expect(synchronize).toHaveBeenCalledTimes(1);
    expect(synchronize).toHaveBeenCalledWith();
  });

  it('restores only still-eligible remembered connector codes', () => {
    window.localStorage.setItem('jobpilot.manualSyncConnectorCodes', JSON.stringify(['adzuna', 'indeed', 'unknown']));

    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Choisir les connecteurs' }));

    expect(screen.getByRole('checkbox', { name: 'Synchroniser Apec' })).not.toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Synchroniser Adzuna' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Synchroniser Indeed' })).not.toBeChecked();
  });

  it('moves focus into the selector and closes it with Escape while restoring the trigger focus', () => {
    render(<SelectiveConnectorSyncPanel connectors={connectors} syncing={false} onSynchronize={vi.fn()} />);

    const trigger = screen.getByRole('button', { name: 'Choisir les connecteurs' });
    fireEvent.click(trigger);

    expect(screen.getByRole('checkbox', { name: 'Synchroniser Apec' })).toHaveFocus();
    expect(screen.getByRole('dialog', { name: 'Choisir les connecteurs à synchroniser' })).toBeInTheDocument();

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Choisir les connecteurs à synchroniser' })).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});
