'use client';

import { ConnectorSearchCriteriaPanel } from '@/components/ConnectorSearchCriteriaPanel';
import { Card, PageHeader } from '@/components/UI';

export default function SearchCriteriaPage() {
  return (
    <>
      <PageHeader
        title="Critères de recherche"
        description="Consulte et modifie les intitulés, compétences et mots-clés réellement utilisés pour récupérer les offres."
      />

      <Card>
        <h2 className="section-title">France Travail</h2>
        <p className="muted">
          Les requêtes affichées correspondent exactement au paramètre <code>motsCles</code> envoyé à l’API Offres d’emploi v2.
        </p>
        <ConnectorSearchCriteriaPanel connectorCode="france-travail" />
      </Card>
    </>
  );
}
