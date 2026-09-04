import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, DataToolbar, PageHeader } from '@/components/UI';

import styles from './page.module.css';

export default function ApplicationsLoading() {
  return (
    <SkeletonGroup label="Chargement des candidatures">
      <PageHeader
        title="Candidatures"
        description="Suis et filtre les candidatures préparées, envoyées manuellement ou transmises automatiquement par un canal officiel autorisé."
      />

      <Card>
        <DataToolbar aria-hidden="true">
          <Skeleton width={280} height={38} />
        </DataToolbar>
        <DataList aria-hidden="true">
          {[0, 1, 2].map((index) => (
            <DataListItem key={index}>
              <div className={styles.skeletonBody}>
                <Skeleton width="46%" height={22} />
                <Skeleton width="62%" height={16} className="mt-2" />
                <div className={styles.skeletonBadges}>
                  <Skeleton width={92} height={24} />
                  <Skeleton width={72} height={24} />
                  <Skeleton width={84} height={24} />
                </div>
              </div>
              <Skeleton width={128} height={34} />
            </DataListItem>
          ))}
        </DataList>
      </Card>
    </SkeletonGroup>
  );
}
