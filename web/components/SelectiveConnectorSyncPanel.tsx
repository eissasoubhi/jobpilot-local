'use client';

import Link from 'next/link';
import { useEffect, useMemo, useRef, useState } from 'react';

import type { SourceConnector } from '@/lib/types';

import { Button, FloatingPanel } from './UI';

type Props = {
  connectors: SourceConnector[];
  syncing: boolean;
  onSynchronize: (connectorCodes?: string[]) => void | Promise<void>;
};

const STORAGE_KEY = 'jobpilot.manualSyncConnectorCodes';

function isEligible(connector: SourceConnector): boolean {
  return connector.enabled && connector.configured && connector.collectionAllowed;
}

function ineligibleReason(connector: SourceConnector): string | null {
  if (!connector.enabled) return 'Connecteur désactivé';
  if (!connector.configured) return connector.configurationMessage || 'Configuration requise';
  if (!connector.collectionAllowed) {
    return connector.policy.note || connector.policy.complianceLabel || 'Collecte bloquée par politique';
  }

  return null;
}

export function SelectiveConnectorSyncPanel({ connectors, syncing, onSynchronize }: Props) {
  const eligibleCodes = useMemo(
    () => connectors.filter(isEligible).map((connector) => connector.code),
    [connectors],
  );
  const [open, setOpen] = useState(false);
  const [selectedCodes, setSelectedCodes] = useState<string[]>([]);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const firstEligibleRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const eligible = new Set(eligibleCodes);
    let initial = eligibleCodes;

    try {
      const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
      if (Array.isArray(stored)) {
        const remembered = stored.filter((code): code is string => typeof code === 'string' && eligible.has(code));
        if (remembered.length > 0) initial = remembered;
      }
    } catch {
      // Corrupt browser preference must never block manual synchronization.
    }

    setSelectedCodes(initial);
  }, [eligibleCodes]);

  useEffect(() => {
    if (!open) return;

    firstEligibleRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key !== 'Escape') return;
      event.preventDefault();
      setOpen(false);
      window.requestAnimationFrame(() => triggerRef.current?.focus());
    };

    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open]);

  const selected = new Set(selectedCodes);

  const closePanel = (): void => {
    setOpen(false);
    window.requestAnimationFrame(() => triggerRef.current?.focus());
  };

  const toggle = (code: string): void => {
    setSelectedCodes((current) => (
      current.includes(code) ? current.filter((item) => item !== code) : [...current, code]
    ));
  };

  const selectAll = (): void => setSelectedCodes(eligibleCodes);
  const selectNone = (): void => setSelectedCodes([]);

  const synchronizeSelected = (): void => {
    if (selectedCodes.length === 0 || syncing) return;

    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedCodes));
    void onSynchronize(selectedCodes);
    setOpen(false);
  };

  return (
    <div style={{ position: 'relative' }}>
      <div className="actions">
        <Button
          variant="secondary"
          disabled={syncing || eligibleCodes.length === 0}
          onClick={() => void onSynchronize()}
        >
          {syncing ? 'Synchronisation…' : 'Tout synchroniser'}
        </Button>
        <Button
          ref={triggerRef}
          variant="secondary"
          aria-expanded={open}
          aria-controls="selective-connector-sync-panel"
          disabled={syncing || connectors.length === 0}
          onClick={() => setOpen((current) => !current)}
        >
          Choisir les connecteurs
        </Button>
      </div>

      {open && (
        <FloatingPanel
          id="selective-connector-sync-panel"
          role="dialog"
          ariaLabel="Choisir les connecteurs à synchroniser"
          style={{
            position: 'absolute',
            right: 0,
            zIndex: 20,
            width: 'min(520px, calc(100vw - 32px))',
            marginTop: 8,
          }}
        >
          <div className="actions" style={{ justifyContent: 'space-between', marginBottom: 12 }}>
            <strong>Connecteurs du prochain run</strong>
            <div className="actions">
              <Button variant="secondary" size="small" onClick={selectAll}>Tout sélectionner</Button>
              <Button variant="secondary" size="small" onClick={selectNone}>Tout désélectionner</Button>
            </div>
          </div>

          <div className="stack" style={{ gap: 8 }}>
            {connectors.map((connector) => {
              const eligible = isEligible(connector);
              const reason = ineligibleReason(connector);

              return (
                <label
                  key={connector.code}
                  style={{
                    display: 'flex',
                    gap: 10,
                    alignItems: 'flex-start',
                    padding: 10,
                    border: '1px solid var(--line)',
                    borderRadius: 10,
                    cursor: eligible ? 'pointer' : 'not-allowed',
                    opacity: eligible ? 1 : 0.72,
                  }}
                >
                  <input
                    ref={eligible && connector.code === eligibleCodes[0] ? firstEligibleRef : undefined}
                    type="checkbox"
                    aria-label={`Synchroniser ${connector.name}`}
                    checked={eligible && selected.has(connector.code)}
                    disabled={!eligible}
                    onChange={() => toggle(connector.code)}
                  />
                  <span style={{ flex: 1 }}>
                    <strong>{connector.name}</strong>
                    {reason && <span className="small muted" style={{ display: 'block', marginTop: 3 }}>{reason}</span>}
                    {!eligible && (
                      <Link href="/connecteurs" className="small" style={{ display: 'inline-block', marginTop: 4 }}>
                        Gérer ce connecteur
                      </Link>
                    )}
                  </span>
                </label>
              );
            })}
          </div>

          <div className="actions" style={{ justifyContent: 'flex-end', marginTop: 14 }}>
            <Button variant="secondary" onClick={closePanel}>Annuler</Button>
            <Button
              disabled={syncing || selectedCodes.length === 0}
              onClick={synchronizeSelected}
            >
              Synchroniser {selectedCodes.length} connecteur{selectedCodes.length > 1 ? 's' : ''}
            </Button>
          </div>
        </FloatingPanel>
      )}
    </div>
  );
}
