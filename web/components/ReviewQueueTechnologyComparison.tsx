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

function decisionLabel(value: string): string {
  if (value === 'MATCH') return 'Correspondance';
  if (value === 'NO_MATCH') return 'À vérifier';
  return value;
}

export function ReviewQueueTechnologyComparison({ comparison }: Props) {
  if (!comparison) return null;

  const hasAiMetadata = comparison.source === 'AI_REUSED'
    && comparison.aiDecision
    && comparison.aiConfidence !== null
    && comparison.aiConfidence !== undefined;

  return (
    <section className={styles.panel} aria-label="Compatibilité technique avec le profil">
      <div className={styles.header}>
        <div>
          <div className="review-queue-eyebrow">Environnement & profil</div>
          <h3>Compatibilité technique</h3>
        </div>
        <div className={styles.analysisBadges}>
          <Badge tone={comparison.source === 'AI_REUSED' ? 'blue' : 'neutral'}>
            {comparison.source === 'AI_REUSED' ? 'Analyse existante' : 'Analyse locale'}
          </Badge>
          {hasAiMetadata && (
            <Badge tone={comparison.aiDecision === 'MATCH' ? 'good' : comparison.aiDecision === 'NO_MATCH' ? 'bad' : 'warn'}>
              {decisionLabel(comparison.aiDecision ?? '')} · {comparison.aiConfidence}%
            </Badge>
          )}
        </div>
      </div>

      <div className={styles.grid}>
        <div className={styles.group}>
          <strong>Compétences clés</strong>
          <ChipList
            items={comparison.primaryTechnologies}
            tone="primary"
            emptyLabel="Aucune compétence clé identifiée"
          />
        </div>

        <div className={styles.group}>
          <strong>Correspondances</strong>
          <ChipList
            items={comparison.matchingTechnologies}
            tone="match"
            emptyLabel="Aucune correspondance détectée"
          />
        </div>

        <div className={styles.group}>
          <strong>Manques bloquants</strong>
          <ChipList
            items={comparison.missingMustHaves}
            tone="missing"
            emptyLabel="Aucun manque bloquant"
          />
        </div>

        <div className={styles.group}>
          <strong>À noter</strong>
          <ChipList
            items={comparison.missingNiceToHaves}
            tone="secondary"
            emptyLabel="Aucun point d’attention"
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