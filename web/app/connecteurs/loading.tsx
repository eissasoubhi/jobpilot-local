import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Card, DataList, DataListItem, PageHeader } from '@/components/UI';

export default function ConnectorsLoading() {
  return (
    <>
      <PageHeader
        title="Connecteurs"
        description="État, autorisation, santé d’extraction, qualité des champs, limites et historique des sources d’offres."
      />

      <Card>
        <SkeletonGroup label="Chargement des connecteurs">
          <DataList aria-hidden="true">
            {[0, 1, 2].map((item) => (
              <DataListItem key={item}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="actions" style={{ marginBottom: 8 }}>
                    <Skeleton width={76} height={24} />
                    <Skeleton width={92} height={24} />
                    <Skeleton width={112} height={24} />
                  </div>
                  <Skeleton width="42%" height={22} />
                  <div style={{ marginTop: 10 }}><Skeleton width="68%" /></div>
                  <div style={{ marginTop: 12 }}><Skeleton width="88%" height={32} /></div>
                  <div style={{ marginTop: 14 }}><Skeleton width={220} height={34} /></div>
                </div>
              </DataListItem>
            ))}
          </DataList>
        </SkeletonGroup>
      </Card>

      <h2 className="section-title" style={{ marginTop: 30 }}>Historique récent</h2>
      <p className="muted" style={{ marginTop: -6 }}>Les vingt dernières exécutions, manuelles ou planifiées.</p>
      <Card>
        <SkeletonGroup label="Chargement de l’historique des synchronisations">
          <DataList aria-hidden="true">
            {[0, 1, 2].map((item) => (
              <DataListItem key={item}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="actions" style={{ marginBottom: 6 }}>
                    <Skeleton width={74} height={24} />
                    <Skeleton width={88} height={24} />
                    <Skeleton width={66} height={24} />
                  </div>
                  <Skeleton width="36%" height={22} />
                  <div style={{ marginTop: 8 }}><Skeleton width="28%" /></div>
                  <div style={{ marginTop: 10 }}><Skeleton width="76%" height={28} /></div>
                </div>
              </DataListItem>
            ))}
          </DataList>
        </SkeletonGroup>
      </Card>
    </>
  );
}
