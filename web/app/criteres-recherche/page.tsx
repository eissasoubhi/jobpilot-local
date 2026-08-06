'use client';

import { ConnectorSearchCriteriaPanel } from '@/components/ConnectorSearchCriteriaPanel';
import { GlobalSearchCriteriaPanel } from '@/components/GlobalSearchCriteriaPanel';
import { Card, PageHeader } from '@/components/UI';

export default function SearchCriteriaPage() {
  return (
    <>
      <PageHeader
        title="Critères de recherche"
        description="Consulte et modifie les clés globales utilisées pour collecter, filtrer, scorer et préparer les offres de toutes les sources."
      />

      <div className="stack">
        <Card>
          <h2 className="section-title">Critères globaux — toutes les sources</h2>
          <p className="muted">
            Ces valeurs sont partagées par les connecteurs compatibles. La page distingue les clés envoyées aux plateformes des règles appliquées uniquement dans JobPilot.
          </p>
          <GlobalSearchCriteriaPanel />
        </Card>

        <Card>
          <h2 className="section-title">Aperçu et diagnostic France Travail</h2>
          <p className="muted">
            Les requêtes affichées correspondent exactement au paramètre <code>motsCles</code> envoyé à l’API Offres d’emploi v2.
            Le bouton de modification historique ci-dessous met à jour les mêmes valeurs globales <code>targetJobs</code> et <code>skills</code> affichées dans la section précédente.
          </p>
          <ConnectorSearchCriteriaPanel connectorCode="france-travail" />
        </Card>
      </div>
    </>
  );
}
