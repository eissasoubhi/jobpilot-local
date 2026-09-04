import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, PageHeader } from '@/components/UI';

import styles from './settings.module.css';

export default function SettingsLoading() {
  return (
    <SkeletonGroup label="Chargement des paramètres">
      <PageHeader
        title="Paramètres"
        description="Règles de recherche, score, rémunération, envoi et intégrations."
        actions={<Skeleton width={120} height={38} />}
      />

      <div className={styles.sectionGap} aria-hidden="true" />

      <div className="grid cols-2" aria-hidden="true">
        {[0, 1, 2, 3, 4, 5].map((index) => (
          <Card key={index}>
            <div className="stack">
              <Skeleton width={index % 2 === 0 ? 180 : 132} height={24} />
              <Skeleton width="72%" height={16} />
              <Skeleton width="100%" height={44} />
              <Skeleton width="100%" height={44} />
              {index < 2 && <Skeleton width="100%" height={92} />}
            </div>
          </Card>
        ))}
      </div>
    </SkeletonGroup>
  );
}
