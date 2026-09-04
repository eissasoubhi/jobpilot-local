import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card } from '@/components/UI';

import styles from './review.module.css';

export default function ReviewQueueLoading() {
  return (
    <SkeletonGroup label="Chargement de la Review Queue" className="review-queue-page">
      <header className="review-queue-compact-header">
        <div className="review-queue-compact-title">
          <h1>Review Queue</h1>
          <Skeleton width={132} height={18} />
        </div>
        <div className="actions" aria-hidden="true">
          <Skeleton width={104} height={36} />
        </div>
      </header>

      <Card>
        <div className="stack" aria-hidden="true">
          <Skeleton width="28%" height={20} />
          <Skeleton width="62%" height={16} />
          <Skeleton width="100%" height={10} />
        </div>
      </Card>

      <div className={`review-queue-workspace ${styles.workspace}`} aria-hidden="true">
        <Card>
          <div className="stack">
            <Skeleton width="72%" height={28} />
            <Skeleton width="44%" height={18} />
            <Skeleton width="100%" height={16} />
            <Skeleton width="94%" height={16} />
            <Skeleton width="76%" height={16} />
            <Skeleton width="100%" height={88} />
          </div>
        </Card>

        <div className={styles.decisionBar}>
          <Skeleton width="100%" height={48} />
          <Skeleton width="100%" height={48} />
          <Skeleton width="100%" height={48} />
          <Skeleton width="100%" height={48} />
          <Skeleton width="100%" height={48} />
        </div>
      </div>
    </SkeletonGroup>
  );
}
