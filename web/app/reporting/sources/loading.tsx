import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, PageHeader } from '@/components/UI';

import styles from './page.module.css';

function ConversionListSkeleton() {
  return (
    <DataList aria-hidden="true">
      {[0, 1, 2].map((index) => (
        <DataListItem key={index}>
          <div className={styles.rowContent}>
            <div className={`${styles.badgeRow} ${styles.rowHeading}`}>
              <Skeleton width={112} height={24} />
              <Skeleton width={64} height={24} />
            </div>
            <div className={styles.badgeRow}>
              <Skeleton width={88} height={24} />
              <Skeleton width={118} height={24} />
              <Skeleton width={94} height={24} />
              <Skeleton width={92} height={24} />
            </div>
            <div className={styles.skeletonMetric}><Skeleton width="76%" height={16} /></div>
            <div className={styles.skeletonMetric}><Skeleton width="62%" height={16} /></div>
          </div>
        </DataListItem>
      ))}
    </DataList>
  );
}

export default function SourceReportingLoading() {
  return (
    <>
      <PageHeader
        title="Conversion"
        description="Mesure en lecture seule de la conversion, de la qualité du matching et des propositions de rémunération par source, type de contrat et mode de travail."
      />
      <SkeletonGroup label="Chargement du reporting de conversion">
        <div className={styles.statsGrid} aria-hidden="true">
          {[0, 1, 2, 3].map((index) => (
            <Card className="stat-card" key={index}>
              <Skeleton width="64%" height={16} />
              <div className={styles.skeletonMetric}><Skeleton width={72} height={32} /></div>
            </Card>
          ))}
        </div>

        <div className={`stack ${styles.sections}`} aria-hidden="true">
          {[0, 1, 2].map((index) => (
            <Card key={index}>
              <Skeleton width={180} height={24} />
              <div className={styles.skeletonSectionIntro}><Skeleton width="82%" height={18} /></div>
              <ConversionListSkeleton />
            </Card>
          ))}
        </div>
      </SkeletonGroup>
    </>
  );
}
