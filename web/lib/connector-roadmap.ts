export type ConnectorRoadmapStatus =
  | 'OPERATIONAL'
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
  nextStep: string;
}

/**
 * Product acquisition matrix. Only entries marked OPERATIONAL correspond to
 * real background connectors. Every other entry is informational and must
 * never enable collection or expose operational controls by itself.
 */
export const connectorRoadmap: readonly ConnectorRoadmapEntry[] = [
  {
    code: 'linkedin',
    name: 'LinkedIn',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'No automated login or background scraping. Recognized alerts and explicit user-assisted imports remain the supported channels.',
    nextStep: 'Improve alert recognition and assisted import coverage without automating a private session.',
  },
  {
    code: 'malt',
    name: 'Malt',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No scheduled acquisition channel has been approved in JobPilot.',
    nextStep: 'Confirm an official partner API, export or authorized alert channel before implementation.',
  },
  {
    code: 'free-work',
    name: 'Free-Work',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'The public visibility of offers does not authorize scheduled database extraction. Use recognized alerts or a page imported deliberately by the user.',
    nextStep: 'Request explicit authorization or an official feed before any background collector is considered.',
  },
  {
    code: 'apec',
    name: 'Apec',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Recognized email alerts and user-assisted imports are possible; no scheduled API or public-feed connector has been approved.',
    nextStep: 'Confirm an official reusable vacancies channel and its usage conditions.',
  },
  {
    code: 'collective-work',
    name: 'Collective.work',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'creme-de-la-creme',
    name: 'Crème de la Crème',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'freelance-republik',
    name: 'FreelanceRepublik',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'comet',
    name: 'Comet',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'cherry-pick',
    name: 'Cherry Pick',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'le-hibou',
    name: 'LeHibou',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Recognized alerts and explicit user-assisted imports are available; scheduled collection still requires a confirmed authorized channel.',
    nextStep: 'Confirm an official API, export or feed before enabling background collection.',
  },
  {
    code: 'mindquest',
    name: 'Mindquest',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'we-love-devs',
    name: 'WeLoveDevs',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'sept-lieues',
    name: 'Sept Lieues',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'jean-michel',
    name: 'Jean-Michel.io',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'welcome-to-the-jungle',
    name: 'Welcome to the Jungle',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Recognized email alerts and user-assisted imports are possible; no scheduled acquisition channel has been approved.',
    nextStep: 'Confirm an official reusable vacancies channel and its usage conditions.',
  },
  {
    code: 'cadremploi',
    name: 'Cadremploi',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'hellowork',
    name: 'HelloWork',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Recognized email alerts and user-assisted imports are possible; no scheduled acquisition channel has been approved.',
    nextStep: 'Confirm an official reusable vacancies channel and its usage conditions.',
  },
  {
    code: 'jobijoba',
    name: 'Jobijoba',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'eures',
    name: 'EURES',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'The official portal exposes a large public European vacancy catalog, but JobPilot has not yet confirmed a reusable official vacancies API or feed.',
    nextStep: 'Identify and validate an official machine-readable acquisition channel before implementation.',
  },
  {
    code: 'freelance-informatique',
    name: 'Freelance-Informatique',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'indeed',
    name: 'Indeed',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'No automated login or background scraping. Recognized alerts and explicit user-assisted imports remain the supported channels.',
    nextStep: 'Improve alert recognition and assisted import coverage without automating a private session.',
  },
  {
    code: 'adzuna',
    name: 'Adzuna',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'An official API connector is registered and can be synchronized when its credentials are configured.',
    nextStep: 'Monitor field quality, quotas and search effectiveness.',
  },
  {
    code: 'kicklox',
    name: 'Kicklox',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'talent-com',
    name: 'Talent.com',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'smartrecruiters',
    name: 'SmartRecruiters',
    status: 'PLANNED',
    modes: ['API'],
    note: 'The official Posting API exposes active public postings by company and supports query, location and work-mode filters.',
    nextStep: 'Implement a configurable connector with authorized API access and a bounded list of company identifiers.',
  },
  {
    code: 'getyourjob',
    name: 'GetYourJob',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'le-studio-tech',
    name: 'Le Studio Tech',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'meteojob',
    name: 'Meteojob',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'michael-page',
    name: 'Michael Page',
    status: 'UNDER_REVIEW',
    modes: [],
    note: 'No authorized background acquisition channel has been confirmed.',
    nextStep: 'Review official integrations, feeds and public usage conditions.',
  },
  {
    code: 'france-travail',
    name: 'France Travail',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'The official Offres d’emploi v2 API connector is registered and can be synchronized when its application credentials are configured.',
    nextStep: 'Tune global keywords using the per-query diagnostics already available in JobPilot.',
  },
  {
    code: 'lesjeudis',
    name: 'LesJeudis',
    status: 'UNDER_REVIEW',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Recognized alerts and explicit user-assisted imports are available; scheduled collection still requires a confirmed authorized channel.',
    nextStep: 'Confirm an official API, export or feed before enabling background collection.',
  },
] as const;
