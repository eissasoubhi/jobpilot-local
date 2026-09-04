import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, PageHeader } from '@/components/UI';

import styles from './page.module.css';

export default function ReportingLoading() {
  return (
    <>
      <PageHeader
        title="Reporting candidatures"
        description="Indicateurs locaux calculés uniquement depuis les candidatures déjà enregistrées dans JobPilot."
      />
      <SkeletonGroup label="Chargement du reporting candidatures">
        <div className={styles.summaryGrid} aria-hidden="true">
          {[0, 1].map((index) => (
            <Card className={styles.summaryCard} key={index}>
              <div className={styles.skeletonTitle}>
                <Skeleton width={index === 0 ? '42%' : '48%'} height={22} />
              </div>
              <div className={styles.badgeCluster}>
                <Skeleton width={92} height={24} />
                <Skeleton width={104} height={24} />
                <Skeleton width={82} height={24} />
              </div>
            </Card>
          ))}
        </div>

        <Card>
          <Skeleton width="34%" height={24} />
          <DataList aria-hidden="true" className={styles.sourceList}>
            {[0, 1, 2].map((index) => (
              <DataListItem key={index}>
                <div className={styles.sourceRow}>
                  <Skeleton width="28%" height={18} />
                  <div className={styles.badgeCluster}>
                    <Skeleton width={96} height={24} />
                    <Skeleton width={92} height={24} />
                    <Skeleton width={88} height={24} />
                  </div>
                </div>
              </DataListItem>
            ))}
          </DataList>
        </Card>
      </SkeletonGroup>
    </>
  );
}
