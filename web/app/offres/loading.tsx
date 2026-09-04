import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, DataToolbar, PageHeader } from '@/components/UI';

export default function OffersLoading() {
  return (
    <SkeletonGroup label="Chargement des offres">
      <PageHeader
        title="Offres"
        description="Examine l’offre, son score et les éléments de candidature déjà préparés depuis un seul espace."
        actions={(
          <div className="actions" aria-hidden="true">
            <Skeleton width={168} height={38} />
            <Skeleton width={172} height={38} />
            <Skeleton width={132} height={38} />
          </div>
        )}
      />

      <Card>
        <div className="stack" aria-hidden="true">
          <Skeleton width={260} height={22} />
          <Skeleton width="72%" height={16} />
          <div className="actions">
            <Skeleton width={104} height={24} />
            <Skeleton width={96} height={24} />
            <Skeleton width={120} height={24} />
          </div>
        </div>
      </Card>

      <Card>
        <DataToolbar aria-hidden="true">
          <Skeleton width={360} height={38} />
        </DataToolbar>
      </Card>

      <Card>
        <DataList aria-hidden="true">
          {[0, 1, 2].map((index) => (
            <DataListItem key={index}>
              <div className="stack" style={{ flex: 1, minWidth: 0 }}>
                <div className="actions">
                  <Skeleton width={90} height={24} />
                  <Skeleton width={52} height={24} />
                  <Skeleton width={104} height={24} />
                  <Skeleton width={86} height={24} />
                </div>
                <Skeleton width="48%" height={24} />
                <Skeleton width="66%" height={16} />
                <div className="actions">
                  <Skeleton width={128} height={34} />
                  <Skeleton width={84} height={34} />
                </div>
              </div>
              <Skeleton width={58} height={58} />
            </DataListItem>
          ))}
        </DataList>
      </Card>
    </SkeletonGroup>
  );
}
