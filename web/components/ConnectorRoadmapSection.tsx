import styles from './ConnectorRoadmapSection.module.css';
import { Badge, Card, DataList, DataListItem, DataToolbar } from '@/components/UI';
import {
  connectorRoadmap,
  type ConnectorRoadmapEntry,
  type ConnectorRoadmapMode,
  type ConnectorRoadmapStatus,
} from '@/lib/connector-roadmap';

function statusLabel(status: ConnectorRoadmapStatus): string {
  return {
    OPERATIONAL: 'Opérationnel',
    PLANNED: 'Canal officiel planifié',
    UNDER_REVIEW: 'Canal en revue',
    EMAIL_OR_EXTENSION_ONLY: 'Gmail ou import assisté uniquement',
  }[status];
}

function statusTone(status: ConnectorRoadmapStatus): 'good' | 'blue' | 'warn' {
  if (status === 'OPERATIONAL') return 'good';
  if (status === 'PLANNED') return 'blue';
  return 'warn';
}

function modeLabel(mode: ConnectorRoadmapMode): string {
  return {
    API: 'API officielle',
    XML: 'Flux XML partenaire',
    SCRAPING_HTTP: 'Scraping HTTP public',
    GMAIL: 'Alertes Gmail',
    EXTENSION: 'Import assisté',
  }[mode];
}

function roadmapExplanation(entry: ConnectorRoadmapEntry): string {
  if (entry.status === 'OPERATIONAL') {
    return 'Le connecteur existe déjà. Sa configuration, son activation, sa santé et ses actions sont affichées dans la section opérationnelle de cette page.';
  }

  if (entry.status === 'PLANNED') {
    return 'Un canal officiel réutilisable a été identifié. Le connecteur reste inactif jusqu’à l’obtention des accès nécessaires, son implémentation, sa configuration et ses tests.';
  }

  if (entry.status === 'EMAIL_OR_EXTENSION_ONLY') {
    return 'Aucune collecte automatique en arrière-plan : seules les alertes reconnues ou une importation déclenchée explicitement par l’utilisateur sont admises.';
  }

  return 'Aucune collecte planifiée tant que la revue technique et de conformité de cette source n’est pas terminée.';
}

export function ConnectorRoadmapSection() {
  const operationalCount = connectorRoadmap.filter((entry) => entry.status === 'OPERATIONAL').length;
  const plannedCount = connectorRoadmap.filter((entry) => entry.status === 'PLANNED').length;
  const restrictedCount = connectorRoadmap.filter((entry) => entry.status === 'EMAIL_OR_EXTENSION_ONLY').length;
  const reviewCount = connectorRoadmap.filter((entry) => entry.status === 'UNDER_REVIEW').length;

  return (
    <section aria-labelledby="connector-roadmap-title" className={styles.section}>
      <DataToolbar>
        <h2 className={`section-title ${styles.toolbarTitle}`} id="connector-roadmap-title">
          Matrice des plateformes suivies
        </h2>
        <p className={`muted ${styles.toolbarDescription}`}>
          Cette matrice couvre les plateformes demandées et indique le canal réellement disponible. Une ligne informative ne crée jamais un connecteur, une synchronisation ou un droit de scraper.
        </p>
      </DataToolbar>

      <div className={styles.summaryBadges} aria-label="Résumé de la couverture des plateformes">
        <Badge tone="good">{operationalCount} opérationnelle(s)</Badge>
        <Badge tone="blue">{plannedCount} canal/canaux officiel(s) planifié(s)</Badge>
        <Badge tone="warn">{restrictedCount} restreinte(s)</Badge>
        <Badge>{reviewCount} en revue</Badge>
      </div>

      <Card>
        <DataList aria-label="Matrice des plateformes suivies" data-testid="connector-roadmap-list">
          {connectorRoadmap.map((entry) => (
            <DataListItem
              data-roadmap-connector={entry.code}
              data-testid={`roadmap-connector-${entry.code}`}
              key={entry.code}
            >
              <div className={styles.itemContent}>
                <div className={styles.badges}>
                  <Badge tone={statusTone(entry.status)}>{statusLabel(entry.status)}</Badge>
                  {entry.modes.map((mode) => (
                    <Badge key={mode} tone="neutral">{modeLabel(mode)}</Badge>
                  ))}
                </div>

                <strong className={styles.title}>{entry.name}</strong>
                <div className={`muted small ${styles.code}`}>Code : <code>{entry.code}</code></div>
                <p className={`small ${styles.explanation}`}>{roadmapExplanation(entry)}</p>
                <p className={`small muted ${styles.note}`}>{entry.note}</p>
                <p className={`small ${styles.nextStep}`}>
                  <strong>Étape suivante :</strong> {entry.nextStep}
                </p>
              </div>
            </DataListItem>
          ))}
        </DataList>
      </Card>
    </section>
  );
}
