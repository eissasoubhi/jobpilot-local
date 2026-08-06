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

const DEFAULT_REVIEW_NOTE = 'Aucun canal autorisé de collecte en arrière-plan n’est confirmé dans JobPilot.';
const DEFAULT_REVIEW_STEP = 'Examiner les intégrations officielles, les flux disponibles et les conditions de réutilisation.';

function underReview(
  code: string,
  name: string,
  options: Partial<Pick<ConnectorRoadmapEntry, 'modes' | 'note' | 'nextStep'>> = {},
): ConnectorRoadmapEntry {
  return {
    code,
    name,
    status: 'UNDER_REVIEW',
    modes: options.modes ?? [],
    note: options.note ?? DEFAULT_REVIEW_NOTE,
    nextStep: options.nextStep ?? DEFAULT_REVIEW_STEP,
  };
}

/**
 * Matrice produit des plateformes. Seules les entrées OPERATIONAL correspondent
 * à de vrais connecteurs exécutables. Toutes les autres entrées sont purement
 * informatives et ne doivent jamais activer une collecte ou afficher de faux
 * contrôles opérationnels.
 */
export const connectorRoadmap: readonly ConnectorRoadmapEntry[] = [
  {
    code: 'linkedin',
    name: 'LinkedIn',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Aucune connexion automatisée ni aucun scraping en arrière-plan. Les alertes reconnues et les imports déclenchés explicitement par l’utilisateur restent les canaux pris en charge.',
    nextStep: 'Améliorer la reconnaissance des alertes et l’import assisté sans automatiser une session privée.',
  },
  underReview('malt', 'Malt', {
    nextStep: 'Confirmer une API partenaire, un export ou un canal d’alerte officiellement réutilisable.',
  }),
  {
    code: 'free-work',
    name: 'Free-Work',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'La visibilité publique des offres n’autorise pas une extraction planifiée de la base. JobPilot utilise uniquement les alertes reconnues ou une page importée volontairement par l’utilisateur.',
    nextStep: 'Obtenir une autorisation explicite ou un flux officiel avant d’envisager une collecte en arrière-plan.',
  },
  underReview('apec', 'Apec', {
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les alertes e-mail reconnues et l’import assisté sont possibles, mais aucun connecteur API ou flux public planifié n’est approuvé.',
    nextStep: 'Confirmer un canal officiel d’offres réutilisable et ses conditions d’utilisation.',
  }),
  underReview('collective-work', 'Collective.work'),
  underReview('creme-de-la-creme', 'Crème de la Crème'),
  underReview('freelance-republik', 'FreelanceRepublik'),
  underReview('comet', 'Comet'),
  underReview('cherry-pick', 'Cherry Pick'),
  underReview('le-hibou', 'LeHibou', {
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les alertes reconnues et l’import assisté sont disponibles. La collecte planifiée exige encore un canal officiellement autorisé.',
    nextStep: 'Confirmer une API, un export ou un flux officiel avant d’activer une collecte en arrière-plan.',
  }),
  underReview('mindquest', 'Mindquest'),
  underReview('we-love-devs', 'WeLoveDevs'),
  underReview('sept-lieues', 'Sept Lieues'),
  underReview('jean-michel', 'Jean-Michel.io'),
  underReview('welcome-to-the-jungle', 'Welcome to the Jungle', {
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les alertes e-mail reconnues et l’import assisté sont possibles, mais aucun canal de collecte planifiée n’est approuvé.',
    nextStep: 'Confirmer un canal officiel d’offres réutilisable et ses conditions d’utilisation.',
  }),
  underReview('cadremploi', 'Cadremploi'),
  underReview('hellowork', 'HelloWork', {
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les alertes e-mail reconnues et l’import assisté sont possibles, mais aucun canal de collecte planifiée n’est approuvé.',
    nextStep: 'Confirmer un canal officiel d’offres réutilisable et ses conditions d’utilisation.',
  }),
  underReview('jobijoba', 'Jobijoba'),
  underReview('eures', 'EURES', {
    note: 'Le portail officiel expose un important catalogue public européen, mais JobPilot n’a pas encore confirmé une API ou un flux officiel d’offres réutilisable.',
    nextStep: 'Identifier et valider un canal officiel lisible par machine avant toute implémentation.',
  }),
  underReview('freelance-informatique', 'Freelance-Informatique'),
  {
    code: 'indeed',
    name: 'Indeed',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Aucune connexion automatisée ni aucun scraping en arrière-plan. Les alertes reconnues et les imports déclenchés explicitement par l’utilisateur restent les canaux pris en charge.',
    nextStep: 'Améliorer la reconnaissance des alertes et l’import assisté sans automatiser une session privée.',
  },
  {
    code: 'adzuna',
    name: 'Adzuna',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'Un connecteur API est enregistré et peut être synchronisé lorsque ses identifiants sont configurés.',
    nextStep: 'Surveiller la qualité des champs, les quotas et l’efficacité des recherches.',
  },
  underReview('kicklox', 'Kicklox'),
  underReview('talent-com', 'Talent.com'),
  {
    code: 'smartrecruiters',
    name: 'SmartRecruiters',
    status: 'PLANNED',
    modes: ['API'],
    note: 'La Posting API officielle expose les offres publiques actives d’une entreprise et fournit des filtres de recherche, de lieu et de mode de travail.',
    nextStep: 'Développer un connecteur configurable avec un accès API autorisé et une liste bornée d’identifiants d’entreprises.',
  },
  underReview('getyourjob', 'GetYourJob'),
  underReview('le-studio-tech', 'Le Studio Tech'),
  underReview('meteojob', 'Meteojob'),
  underReview('michael-page', 'Michael Page'),
  {
    code: 'france-travail',
    name: 'France Travail',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'Le connecteur de l’API officielle Offres d’emploi v2 est enregistré et peut être synchronisé lorsque les identifiants de l’application sont configurés.',
    nextStep: 'Ajuster les mots-clés globaux grâce aux diagnostics par requête déjà disponibles.',
  },
  underReview('lesjeudis', 'LesJeudis', {
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les alertes reconnues et l’import assisté sont disponibles. La collecte planifiée exige encore un canal officiellement autorisé.',
    nextStep: 'Confirmer une API, un export ou un flux officiel avant d’activer une collecte en arrière-plan.',
  }),
] as const;
