export type ConnectorRoadmapStatus =
  | 'OPERATIONAL'
  | 'PLANNED'
  | 'UNDER_REVIEW'
  | 'EMAIL_OR_EXTENSION_ONLY';

export type ConnectorRoadmapMode = 'API' | 'XML' | 'SCRAPING_HTTP' | 'GMAIL' | 'EXTENSION';

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
  {
    code: 'apec',
    name: 'Apec',
    status: 'PLANNED',
    modes: ['XML', 'GMAIL', 'EXTENSION'],
    note: 'Apec propose officiellement aux sites tiers un export de ses offres via flux XML standardisé, organisé par convention de partenariat. JobPilot n’active aucun scraping Apec en arrière-plan sans cet accord ; Gmail et l’import assisté restent disponibles entre-temps.',
    nextStep: 'Demander une convention de partenariat Apec pour accéder au flux XML officiel, puis implémenter le connecteur uniquement après accord.',
  },
  {
    code: 'collective-work',
    name: 'Collective.work',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU Collective.work interdisent explicitement le re-postage, le scraping, l’utilisation automatisée et l’utilisation à grand volume de la plateforme. Les opportunités publiques restent donc accessibles uniquement via des canaux assistés dans JobPilot.',
    nextStep: 'Conserver Gmail et l’import volontaire ; réexaminer uniquement après autorisation écrite Collective.work ou publication d’un canal officiel réutilisable.',
  },
  underReview('creme-de-la-creme', 'Crème de la Crème'),
  underReview('freelance-republik', 'FreelanceRepublik'),
  underReview('comet', 'Comet'),
  underReview('cherry-pick', 'Cherry Pick'),
  {
    code: 'le-hibou',
    name: 'LeHibou',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Aucun API/flux officiel de lecture des missions n’a été identifié. Les CGU LeHibou encadrent l’accès aux Services autour d’un compte utilisateur et indiquent que l’accès au Site et aux Services est réservé aux utilisateurs inscrits. JobPilot n’automatise donc aucune session privée et ne déduit pas un droit de scraping des pages SEO publiques.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après canal officiel réutilisable ou autorisation écrite LeHibou.',
  },
  underReview('mindquest', 'Mindquest'),
  {
    code: 'we-love-devs',
    name: 'WeLoveDevs',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les offres sont visibles publiquement, mais les CGU actuelles restreignent la copie, le stockage et les usages détournant la plateforme de sa finalité. Aucun scraping planifié JobPilot n’est activé.',
    nextStep: 'Obtenir une autorisation écrite ou un canal officiel réutilisable avant toute collecte automatique en arrière-plan.',
  },
  underReview('sept-lieues', 'Sept Lieues'),
  underReview('jean-michel', 'Jean-Michel.io'),
  {
    code: 'welcome-to-the-jungle',
    name: 'Welcome to the Jungle',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU Welcome to the Jungle du 27/04/2026 interdisent l’utilisation de scripts, robots, crawlers, extensions/modules de navigateur ou autres technologies pour extraire automatiquement les données du Site. L’exception limitée aux moteurs de recherche publics ne s’applique pas à JobPilot.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après accord écrit WTTJ ou publication d’un canal officiel de lecture/réutilisation.',
  },
  {
    code: 'cadremploi',
    name: 'Cadremploi',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU Cadremploi/Figaro Classifieds protègent les bases de données, limitent le contenu à un usage privé et interdisent sans autorisation écrite préalable certaines collectes ou extractions automatiques par robots, logiciels ou dispositifs automatiques. La visibilité publique des offres ne suffit donc pas à autoriser un scraper planifié.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après autorisation écrite Figaro Classifieds ou obtention d’un flux/canal officiel de redistribution.',
  },
  {
    code: 'hellowork',
    name: 'HelloWork',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU HelloWork interdisent les systèmes automatisés de screen/web scraping, à des fins commerciales ou non, sauf convention de licence écrite autorisant expressément l’extraction. JobPilot n’active donc aucun scraper planifié.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après licence écrite HelloWork ou canal officiel de lecture/réutilisation.',
  },
  {
    code: 'jobijoba',
    name: 'Jobijoba',
    status: 'PLANNED',
    modes: ['API'],
    note: 'Jobijoba propose officiellement aux sites et applications un programme d’affiliation pour intégrer ses offres via flux, API ou widget. JobPilot cible l’API ou un flux officiel d’affiliation et n’active aucun scraping Jobijoba.',
    nextStep: 'Obtenir les spécifications, accès, conditions de redistribution et quotas du programme d’affiliation Jobijoba avant de développer un connecteur exécutable.',
  },
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
  {
    code: 'talent-com',
    name: 'Talent.com',
    status: 'PLANNED',
    modes: ['API'],
    note: 'Talent.com propose officiellement aux publisher partners un Job API self-service pour compléter un job board avec ses offres, ainsi que des flux XML selon les accords du programme Publisher. JobPilot ne confond pas ce canal de lecture avec les flux XML ATS destinés à publier des offres vers Talent.com et n’active aucun scraping Talent.com.',
    nextStep: 'Obtenir l’accès Publisher Job API, la documentation, les credentials, quotas et droits applicables à JobPilot avant de développer un connecteur exécutable.',
  },
  {
    code: 'smartrecruiters',
    name: 'SmartRecruiters',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'Le connecteur de la Posting API officielle est enregistré. Il reste en configuration requise tant que le jeton et les identifiants d’entreprises ne sont pas renseignés.',
    nextStep: 'Configurer une liste bornée d’entreprises, puis surveiller la qualité des champs et les quotas.',
  },
  underReview('getyourjob', 'GetYourJob'),
  {
    code: 'le-studio-tech',
    name: 'Le Studio Tech',
    status: 'OPERATIONAL',
    modes: ['SCRAPING_HTTP'],
    note: 'Les missions publiques sont collectées sans session via le transport HTTP contrôlé. Le connecteur respecte robots.txt, les quotas locaux et conserve le lien vers la plateforme.',
    nextStep: 'Surveiller la stabilité du parseur HTML, la qualité des champs et les éventuelles évolutions des règles de collecte.',
  },
  {
    code: 'meteojob',
    name: 'Meteojob',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU Meteojob/CleverConnect limitent l’usage du contenu à un cadre personnel et privé et encadrent ou interdisent l’extraction des bases et le scraping pour d’autres finalités. Aucun canal public officiel de lecture/réutilisation n’a été identifié pour JobPilot.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après accord écrit CleverConnect ou identification d’un canal officiel de redistribution.',
  },
  underReview('michael-page', 'Michael Page'),
  {
    code: 'france-travail',
    name: 'France Travail',
    status: 'OPERATIONAL',
    modes: ['API'],
    note: 'Le connecteur de l’API officielle Offres d’emploi v2 est enregistré et peut être synchronisé lorsque les identifiants de l’application sont configurés.',
    nextStep: 'Ajuster les mots-clés globaux grâce aux diagnostics par requête déjà disponibles.',
  },
  {
    code: 'lesjeudis',
    name: 'LesJeudis',
    status: 'EMAIL_OR_EXTENSION_ONLY',
    modes: ['GMAIL', 'EXTENSION'],
    note: 'Les CGU LesJeudis mises à jour le 20/01/2026 interdisent explicitement l’utilisation d’un logiciel robot ou de tout autre procédé automatisé de scraping, ainsi que le contournement des protections internes. JobPilot n’active donc aucun scraper planifié.',
    nextStep: 'Conserver Gmail et l’import assisté ; réexaminer uniquement après accord écrit du Groupe LesJeudis ou publication d’un canal officiel de lecture/réutilisation.',
  },
] as const;
