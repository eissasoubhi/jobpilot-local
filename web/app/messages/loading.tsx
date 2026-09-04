import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, DataToolbar, PageHeader } from '@/components/UI';

export default function MessagesLoading() {
  return (
    <SkeletonGroup label="Chargement de la messagerie Gmail">
      <PageHeader
        title="Messagerie"
        description="Inbox intelligente qui met en avant les entretiens, informations demandées, propositions directes et réponses qui nécessitent une action rapide."
        actions={<Skeleton width={164} height={38} />}
      />

      <div className="grid grid-4" aria-hidden="true">
        {[0, 1, 2, 3].map((index) => (
          <Card className="stat-card" key={index}>
            <Skeleton width={112} height={16} />
            <Skeleton width={42} height={32} />
          </Card>
        ))}
      </div>

      <Card>
        <DataToolbar aria-hidden="true">
          <Skeleton width={240} height={38} />
        </DataToolbar>

        <DataList aria-hidden="true">
          {[0, 1, 2].map((index) => (
            <DataListItem key={index}>
              <div className="stack" style={{ flex: 1, minWidth: 0 }}>
                <div className="actions">
                  <Skeleton width={84} height={24} />
                  <Skeleton width={116} height={24} />
                  <Skeleton width={132} height={16} />
                </div>
                <Skeleton width="72%" height={22} />
                <Skeleton width="38%" height={16} />
                <Skeleton width="92%" height={16} />
                <Skeleton width="76%" height={16} />
                <div className="actions">
                  <Skeleton width={128} height={32} />
                  <Skeleton width={156} height={32} />
                </div>
              </div>
            </DataListItem>
          ))}
        </DataList>
      </Card>
    </SkeletonGroup>
  );
}
