export type ConnectorRoadmapStatus =
  | 'PLANNED'
  | 'UNDER_REVIEW'
  | 'EMAIL_OR_EXTENSION_ONLY';

export type ConnectorRoadmapMode = 'API' | 'GMAIL' | 'EXTENSION';

export interface ConnectorRoadmapEntry {
  code: string;
  name: string;
  status: ConnectorRoadmapStatus;
  modes: readonly ConnectorRoadmapMode[];
  note: string;
}

/**
 * Product roadmap only. These entries are not operational connectors and must
 * never be used to enable or trigger background collection.
 */
export const connectorRoadmap: readonly ConnectorRoadmapEntry[] = [
  {
    code: 'france-travail',
    name: 'France Travail',
    status: 'PLANNED',
    modes: ['API'],
    note: 'Official API integration requires validated access and credentials before implementation.',
  },
  {
    code: 'free-work',
    name: 'Free-Work',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Offers are imported from recognized email alerts or from a page opened by the user.',
  },
  {
    code: 'apec',
    name: 'APEC',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The authorized ingestion channel must be confirmed before any scheduled collection is added.',
  },
  {
    code: 'hellowork',
    name: 'HelloWork',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The authorized ingestion channel must be confirmed before any scheduled collection is added.',
  },
  {
    code: 'welcome-to-the-jungle',
    name: 'Welcome to the Jungle',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The authorized ingestion channel must be confirmed before any scheduled collection is added.',
  },
  {
    code: 'linkedin',
    name: 'LinkedIn',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'No automated login or background scraping; use recognized alerts or user-assisted import.',
  },
  {
    code: 'indeed',
    name: 'Indeed',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'No automated login or background scraping; use recognized alerts or user-assisted import.',
  },
  {
    code: 'lesjeudis',
    name: 'LesJeudis',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The authorized ingestion channel must be confirmed before any scheduled collection is added.',
  },
  {
    code: 'le-hibou',
    name: 'Le Hibou',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The authorized ingestion channel must be confirmed before any scheduled collection is added.',
  },
] as const;
