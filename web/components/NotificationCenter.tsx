'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { api } from '@/lib/api';
import type { SourceConnector } from '@/lib/types';

import styles from './NotificationCenter.module.css';

type NotificationSeverity = 'critical' | 'action' | 'warning' | 'info';

type GmailStatus = {
  connected: boolean;
  readPermission: boolean;
  readPermissionMessage?: string | null;
  sendPermission: boolean;
  sendPermissionMessage?: string | null;
  configured: boolean;
  missingVariables: string[];
  startUrl: string;
};

export type ProductNotification = {
  id: string;
  severity: NotificationSeverity;
  title: string;
  message: string;
  actionLabel?: string;
  actionHref?: string;
};

const STORAGE_KEY = 'jobpilot.notification-center.seen.v1';
const POLL_INTERVAL_MS = 30_000;

const severityWeight: Record<NotificationSeverity, number> = {
  critical: 4,
  action: 3,
  warning: 2,
  info: 1,
};

function normalizedError(connector: SourceConnector): string {
  return (connector.lastError ?? connector.health.reasons[0] ?? '').trim();
}

function isExternalCollectionBlock(connector: SourceConnector): boolean {
  const value = normalizedError(connector).toLocaleLowerCase('fr-FR');

  return connector.status === 'COMPLIANCE_BLOCKED'
    || value.includes('cloudflare')
    || value.includes('anti-automatisation')
    || value.includes('robots.txt')
    || value.includes('statut http 403')
    || value.includes('http 403')
    || value.includes('interdit la collecte');
}

function isRevokedGmailToken(message: string): boolean {
  const value = message.toLocaleLowerCase('fr-FR');
  return value.includes('expired or revoked')
    || value.includes('jeton gmail a expiré')
    || value.includes('gmail a refusé le jeton')
    || value.includes('token has been expired');
}

export function deriveProductNotifications(
  connectors: SourceConnector[],
  gmailStatus: GmailStatus | null,
): ProductNotification[] {
  const notifications = new Map<string, ProductNotification>();
  const gmailConnector = connectors.find((connector) => connector.mode === 'GMAIL' || connector.code.toLowerCase() === 'gmail');
  const gmailError = gmailConnector ? normalizedError(gmailConnector) : '';

  if (gmailConnector?.enabled && gmailStatus !== null) {
    if (!gmailStatus.configured) {
      notifications.set('gmail-configuration', {
        id: 'gmail-configuration',
        severity: 'action',
        title: 'Configuration Gmail incomplète',
        message: gmailStatus.missingVariables.length > 0
          ? `Variables manquantes : ${gmailStatus.missingVariables.join(', ')}.`
          : 'La configuration OAuth Google doit être complétée.',
        actionLabel: 'Configurer Gmail',
        actionHref: '/parametres',
      });
    } else if (!gmailStatus.connected || isRevokedGmailToken(gmailError)) {
      notifications.set('gmail-reconnect', {
        id: 'gmail-reconnect',
        severity: 'action',
        title: 'Gmail doit être reconnecté',
        message: isRevokedGmailToken(gmailError)
          ? 'Le jeton Google a expiré ou a été révoqué. La synchronisation Gmail est interrompue jusqu’à une nouvelle autorisation.'
          : 'Le connecteur Gmail est activé mais aucun compte Google valide n’est connecté.',
        actionLabel: 'Reconnecter Gmail',
        actionHref: gmailStatus.startUrl,
      });
    } else if (!gmailStatus.readPermission) {
      notifications.set('gmail-read-permission', {
        id: 'gmail-read-permission',
        severity: 'action',
        title: 'Lecture Gmail non autorisée',
        message: gmailStatus.readPermissionMessage ?? 'Reconnecte Gmail en acceptant le droit gmail.readonly.',
        actionLabel: 'Reconnecter Gmail',
        actionHref: gmailStatus.startUrl,
      });
    } else if (!gmailStatus.sendPermission) {
      notifications.set('gmail-send-permission', {
        id: 'gmail-send-permission',
        severity: 'warning',
        title: 'Envoi Gmail non autorisé',
        message: gmailStatus.sendPermissionMessage ?? 'La lecture fonctionne, mais gmail.send manque pour les envois par e-mail.',
        actionLabel: 'Autoriser l’envoi',
        actionHref: gmailStatus.startUrl,
      });
    }
  }

  for (const connector of connectors) {
    if (!connector.enabled) continue;

    if (!connector.configured) {
      notifications.set(`connector-config:${connector.code}`, {
        id: `connector-config:${connector.code}`,
        severity: 'action',
        title: `${connector.name} nécessite une configuration`,
        message: connector.configurationMessage ?? 'Ce connecteur est activé mais sa configuration est incomplète.',
        actionLabel: 'Ouvrir la configuration',
        actionHref: '/parametres/integrations',
      });
      continue;
    }

    const error = normalizedError(connector);
    if (isExternalCollectionBlock(connector)) {
      notifications.set(`connector-external:${connector.code}`, {
        id: `connector-external:${connector.code}`,
        severity: 'info',
        title: `${connector.name} est limité par la source`,
        message: error !== ''
          ? error
          : 'La collecte est bloquée par la politique ou la protection du site. Aucune clé locale supplémentaire n’est attendue.',
        actionLabel: 'Voir le connecteur',
        actionHref: '/connecteurs',
      });
      continue;
    }

    if (connector.health.alert || connector.health.status === 'BROKEN' || connector.status === 'FAILED' || connector.status === 'ERROR') {
      if (connector === gmailConnector && notifications.has('gmail-reconnect')) continue;
      notifications.set(`connector-health:${connector.code}`, {
        id: `connector-health:${connector.code}`,
        severity: connector.health.status === 'BROKEN' ? 'critical' : 'warning',
        title: `${connector.name} rencontre un problème`,
        message: error !== '' ? error : connector.health.label,
        actionLabel: 'Voir le connecteur',
        actionHref: '/connecteurs',
      });
    }
  }

  return [...notifications.values()].sort((left, right) => {
    const severityDifference = severityWeight[right.severity] - severityWeight[left.severity];
    return severityDifference !== 0 ? severityDifference : left.title.localeCompare(right.title, 'fr');
  });
}

function notificationFingerprint(notification: ProductNotification): string {
  return `${notification.id}\u0000${notification.message}`;
}

function readSeenFingerprints(): Set<string> {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY);
    if (!value) return new Set();
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? new Set(parsed.filter((item): item is string => typeof item === 'string')) : new Set();
  } catch {
    return new Set();
  }
}

function writeSeenFingerprints(fingerprints: Set<string>): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify([...fingerprints].slice(-100)));
  } catch {
    // Le centre reste utilisable même si le stockage navigateur est indisponible.
  }
}

function severityLabel(severity: NotificationSeverity): string {
  return {
    critical: 'Critique',
    action: 'Action requise',
    warning: 'Avertissement',
    info: 'Information',
  }[severity];
}

export function NotificationCenter() {
  const [notifications, setNotifications] = useState<ProductNotification[]>([]);
  const [seen, setSeen] = useState<Set<string>>(new Set());
  const [open, setOpen] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const buttonRef = useRef<HTMLButtonElement | null>(null);
  const panelRef = useRef<HTMLDivElement | null>(null);

  const load = useCallback(async (): Promise<void> => {
    const [connectorResult, gmailResult] = await Promise.allSettled([
      api<SourceConnector[]>('/connectors'),
      api<GmailStatus>('/integrations/gmail/status'),
    ]);

    if (connectorResult.status === 'rejected') {
      setNotifications([{
        id: 'runtime-api-unavailable',
        severity: 'critical',
        title: 'État de JobPilot indisponible',
        message: 'Le centre de notifications ne peut pas lire l’état des connecteurs. Vérifie que l’API locale est disponible.',
        actionLabel: 'Voir les connecteurs',
        actionHref: '/connecteurs',
      }]);
      setLoaded(true);
      return;
    }

    setNotifications(deriveProductNotifications(
      connectorResult.value,
      gmailResult.status === 'fulfilled' ? gmailResult.value : null,
    ));
    setLoaded(true);
  }, []);

  useEffect(() => {
    setSeen(readSeenFingerprints());
    void load();

    const interval = window.setInterval(() => void load(), POLL_INTERVAL_MS);
    const onVisibility = () => {
      if (document.visibilityState === 'visible') void load();
    };
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
      window.clearInterval(interval);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [load]);

  const unseen = useMemo(
    () => notifications.filter((notification) => !seen.has(notificationFingerprint(notification))),
    [notifications, seen],
  );

  const markCurrentAsSeen = useCallback(() => {
    setSeen((current) => {
      const next = new Set(current);
      for (const notification of notifications) next.add(notificationFingerprint(notification));
      writeSeenFingerprints(next);
      return next;
    });
  }, [notifications]);

  const toggle = () => {
    setOpen((current) => {
      const next = !current;
      if (next) window.setTimeout(markCurrentAsSeen, 0);
      return next;
    });
  };

  useEffect(() => {
    if (!open) return;
    panelRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return;
      setOpen(false);
      buttonRef.current?.focus();
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open]);

  const urgentUnseen = unseen.filter((notification) => notification.severity !== 'info');

  return (
    <div className={styles.root}>
      {loaded && !open && urgentUnseen.length > 0 && (
        <div className={styles.toast} role="status" aria-live="polite">
          <strong>{urgentUnseen.length === 1 ? urgentUnseen[0].title : `${urgentUnseen.length} problèmes nécessitent ton attention`}</strong>
          <span>{urgentUnseen.length === 1 ? urgentUnseen[0].message : 'Ouvre les notifications pour voir les actions recommandées.'}</span>
          <button type="button" onClick={toggle}>Voir</button>
        </div>
      )}

      <button
        ref={buttonRef}
        type="button"
        className={styles.bell}
        aria-label={`Notifications${unseen.length > 0 ? `, ${unseen.length} nouvelle${unseen.length > 1 ? 's' : ''}` : ''}`}
        aria-expanded={open}
        aria-controls="jobpilot-notification-panel"
        onClick={toggle}
      >
        <span aria-hidden="true">🔔</span>
        {unseen.length > 0 && <span className={styles.count}>{unseen.length > 99 ? '99+' : unseen.length}</span>}
      </button>

      {open && (
        <div
          id="jobpilot-notification-panel"
          ref={panelRef}
          className={styles.panel}
          role="dialog"
          aria-label="Centre de notifications"
          tabIndex={-1}
        >
          <div className={styles.header}>
            <div>
              <strong>Notifications</strong>
              <span>{notifications.length} problème{notifications.length > 1 ? 's' : ''} actif{notifications.length > 1 ? 's' : ''}</span>
            </div>
            <button type="button" className={styles.close} onClick={() => setOpen(false)} aria-label="Fermer les notifications">×</button>
          </div>

          <div className={styles.list}>
            {notifications.length === 0 ? (
              <div className={styles.empty}>Tout va bien. Aucun problème actif détecté.</div>
            ) : notifications.map((notification) => (
              <article className={styles.item} data-severity={notification.severity} key={notification.id}>
                <div className={styles.itemTopLine}>
                  <span className={styles.severity}>{severityLabel(notification.severity)}</span>
                  {!seen.has(notificationFingerprint(notification)) && <span className={styles.new}>Nouveau</span>}
                </div>
                <strong>{notification.title}</strong>
                <p>{notification.message}</p>
                {notification.actionHref && notification.actionLabel && (
                  <a className={styles.action} href={notification.actionHref}>{notification.actionLabel}</a>
                )}
              </article>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
