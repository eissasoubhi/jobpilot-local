'use client';

import { Badge } from '@/components/UI';

import styles from './ReviewQueueTechnologyComparison.module.css';

export type JobProfileComparison = {
  source: 'AI_REUSED' | 'DETERMINISTIC';
  aiDecision?: string | null;
  aiConfidence?: number | null;
  technologies: string[];
  primaryTechnologies: string[];
  secondaryTechnologies: string[];
  matchingTechnologies: string[];
  missingTechnologies: string[];
  missingMustHaves: string[];
  missingNiceToHaves: string[];
};

type Props = {
  comparison?: JobProfileComparison | null;
};

type ChipTone = 'primary' | 'match' | 'missing' | 'secondary';

function ChipList({ items, tone, emptyLabel }: { items: string[]; tone: ChipTone; emptyLabel: string }) {
  if (items.length === 0) {
    return <span className={styles.empty}>{emptyLabel}</span>;
  }

  return (
    <div className={styles.chips}>
      {items.map((item) => (
        <span key={`${tone}-${item}`} className={`${styles.chip} ${styles[tone]}`}>{item}</span>
      ))}
    </div>
  );
}

export function ReviewQueueTechnologyComparison({ comparison }: Props) {
  if (!comparison) return null;

  const hasAiMetadata = comparison.source === 'AI_REUSED'
    && comparison.aiDecision
    && comparison.aiConfidence !== null
    && comparison.aiConfidence !== undefined;

  return (
    <section className={styles.panel} aria-label="Comparaison des technologies avec le profil">
      <div className={styles.header}>
        <div>
          <div className="review-queue-eyebrow">Environnement & profil</div>
          <h3>Adéquation technique</h3>
        </div>
        <div className={styles.analysisBadges}>
          <Badge tone={comparison.source === 'AI_REUSED' ? 'blue' : 'neutral'}>
            {comparison.source === 'AI_REUSED' ? 'Analyse IA réutilisée' : 'Analyse locale'}
          </Badge>
          {hasAiMetadata && (
            <Badge tone={comparison.aiDecision === 'MATCH' ? 'good' : comparison.aiDecision === 'NO_MATCH' ? 'bad' : 'warn'}>
              {comparison.aiDecision} · {comparison.aiConfidence}%
            </Badge>
          )}
        </div>
      </div>

      <div className={styles.grid}>
        <div className={styles.group}>
          <strong>Stack principale</strong>
          <ChipList
            items={comparison.primaryTechnologies}
            tone="primary"
            emptyLabel="Stack principale non identifiée"
          />
        </div>

        <div className={styles.group}>
          <strong>En commun avec mon profil</strong>
          <ChipList
            items={comparison.matchingTechnologies}
            tone="match"
            emptyLabel="Aucune technologie commune détectée"
          />
        </div>

        <div className={styles.group}>
          <strong>Manques obligatoires</strong>
          <ChipList
            items={comparison.missingMustHaves}
            tone="missing"
            emptyLabel="Aucun manque obligatoire détecté"
          />
        </div>

        <div className={styles.group}>
          <strong>Autres technologies absentes</strong>
          <ChipList
            items={comparison.missingNiceToHaves}
            tone="secondary"
            emptyLabel="Aucun autre écart détecté"
          />
        </div>
      </div>

      {comparison.secondaryTechnologies.length > 0 && (
        <div className={styles.secondaryRow}>
          <span>Secondaire / contexte</span>
          <ChipList items={comparison.secondaryTechnologies} tone="secondary" emptyLabel="" />
        </div>
      )}
    </section>
  );
}
