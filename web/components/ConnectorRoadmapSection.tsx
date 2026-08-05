import { Badge, Card } from '@/components/UI';
import {
  connectorRoadmap,
  type ConnectorRoadmapEntry,
  type ConnectorRoadmapMode,
  type ConnectorRoadmapStatus,
} from '@/lib/connector-roadmap';

function statusLabel(status: ConnectorRoadmapStatus): string {
  return {
    PLANNED: 'Planifié',
    UNDER_REVIEW: 'En revue',
    EMAIL_OR_EXTENSION_ONLY: 'Gmail ou extension uniquement',
  }[status];
}

function statusTone(status: ConnectorRoadmapStatus): 'blue' | 'warn' {
  return status === 'PLANNED' ? 'blue' : 'warn';
}

function modeLabel(mode: ConnectorRoadmapMode): string {
  return {
    API: 'API officielle',
    GMAIL: 'Alertes Gmail',
    EXTENSION: 'Import assisté',
  }[mode];
}

function roadmapExplanation(entry: ConnectorRoadmapEntry): string {
  if (entry.status === 'PLANNED') {
    return 'Intégration prévue uniquement après validation de l’accès et des identifiants de l’API officielle.';
  }

  if (entry.status === 'EMAIL_OR_EXTENSION_ONLY') {
    return 'Collecte limitée aux alertes Gmail reconnues ou à une page importée volontairement par l’utilisateur.';
  }

  return 'Aucune collecte planifiée tant que la revue technique et de conformité de cette source n’est pas terminée.';
}

export function ConnectorRoadmapSection() {
  return (
    <section aria-labelledby="connector-roadmap-title" style={{ marginTop: 30 }}>
      <h2 className="section-title" id="connector-roadmap-title">Sources prévues et restreintes</h2>
      <p className="muted" style={{ marginTop: -6 }}>
        Ces sources ne sont pas des connecteurs opérationnels. Elles ne peuvent être ni activées, ni testées, ni synchronisées depuis cette page.
      </p>

      <div className="stack" data-testid="connector-roadmap-list">
        {connectorRoadmap.map((entry) => (
          <Card key={entry.code}>
            <div
              className="list-row"
              data-roadmap-connector={entry.code}
              data-testid={`roadmap-connector-${entry.code}`}
              style={{ alignItems: 'flex-start', paddingTop: 0, paddingBottom: 0 }}
            >
              <div style={{ flex: 1 }}>
                <div className="actions" style={{ marginBottom: 8 }}>
                  <Badge tone={statusTone(entry.status)}>{statusLabel(entry.status)}</Badge>
                  {entry.modes.map((mode) => (
                    <Badge key={mode} tone="neutral">{modeLabel(mode)}</Badge>
                  ))}
                </div>

                <h3>{entry.name}</h3>
                <div className="muted small">Code roadmap : <code>{entry.code}</code></div>
                <p className="small" style={{ marginBottom: 0 }}>
                  {roadmapExplanation(entry)}
                </p>
              </div>
            </div>
          </Card>
        ))}
      </div>
    </section>
  );
}
