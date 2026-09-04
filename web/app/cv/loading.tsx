import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, PageHeader } from '@/components/UI';

import styles from './cv.module.css';

export default function CvLoading() {
  return (
    <>
      <PageHeader
        title="Mes CV"
        description="L’application choisit le document adapté, sans modifier son contenu."
      />
      <SkeletonGroup label="Chargement de la bibliothèque de CV">
        <div className={styles.layout} aria-hidden="true">
          <Card>
            <Skeleton width="34%" height={24} />
            <div className="stack">
              {[0, 1, 2, 3, 4].map((index) => (
                <div key={index} className={styles.skeletonContent}>
                  <Skeleton width="38%" height={14} />
                  <Skeleton height={40} />
                </div>
              ))}
              <Skeleton width="62%" height={22} />
              <Skeleton width={112} height={40} />
            </div>
          </Card>

          <Card>
            <Skeleton width="42%" height={24} />
            <DataList aria-hidden="true">
              {[0, 1, 2].map((index) => (
                <DataListItem key={index} className={styles.documentItem}>
                  <div className={styles.skeletonContent}>
                    <Skeleton width="58%" height={22} />
                    <div className={styles.skeletonBadges}>
                      <Skeleton width="74%" height={16} />
                    </div>
                    <div className={styles.skeletonBadges}>
                      <Skeleton width={82} height={24} />
                      <Skeleton width={92} height={24} />
                    </div>
                  </div>
                  <div className={styles.skeletonActions}>
                    <Skeleton width={96} height={34} />
                    <Skeleton width={84} height={34} />
                  </div>
                </DataListItem>
              ))}
            </DataList>
          </Card>
        </div>
      </SkeletonGroup>
    </>
  );
}
