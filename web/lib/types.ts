export type Profile = {
  fullName: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  addressLine1: string;
  addressLine2?: string | null;
  city: string;
  postalCode: string;
  region: string;
  country: string;
  countryCode: string;
  currentJobTitle: string;
  mobility: string;
  preferredLocations: string[];
  workAuthorisation: string;
  availability: string;
  noticePeriod: string;
  yearsOfExperience: number;
  technologyExperience: Record<string, number>;
  languages: { language: string; level: string }[];
  acceptedContracts: string[];
  workModePreference: string;
  desiredSalary?: number | null;
  desiredTjm?: number | null;
  linkedinUrl?: string | null;
  githubUrl?: string | null;
  portfolioUrl?: string | null;
  professionalUrls: string[];
};

export type AutofillProfile = {
  schemaVersion: number;
  identity: Pick<Profile, 'fullName' | 'firstName' | 'lastName' | 'email' | 'phone'>;
  address: {
    line1: string;
    line2?: string | null;
    city: string;
    postalCode: string;
    region: string;
    country: string;
    countryCode: string;
  };
  professional: {
    currentJobTitle: string;
    yearsOfExperience: number;
    technologyExperience: Record<string, number>;
    languages: Profile['languages'];
    linkedinUrl?: string | null;
    githubUrl?: string | null;
    portfolioUrl?: string | null;
    otherUrls: string[];
  };
  preferences: {
    mobility: string;
    preferredLocations: string[];
    acceptedContracts: string[];
    workModePreference: string;
    availability: string;
    noticePeriod: string;
    desiredSalary?: number | null;
    desiredTjm?: number | null;
  };
  screening: {
    workAuthorisation: string;
  };
  updatedAt: string;
};

export type Settings = {
  interfaceLanguage: string; targetJobs: string[]; exclusions: string[]; skills: string[];
  matchingThreshold: number; defaultIdfTjm: number; defaultOutsideIdfTjm: number;
  defaultRemoteTjm: number; minimumFreelanceTjm: number; maximumTjm: number;
  minimumCdiSalary: number; salaryIncludesTotalCompensation: boolean; cddSalaryRule?: string | null;
  autoPrepare: boolean; autoSubmitEnabled: boolean; autoSubmitThreshold: number;
  autoSubmitDailyLimit: number; finalSubmissionMode: string;
};

export type Cv = { id: number; name: string; originalName: string; language: string; category: string; tags: string[]; active: boolean; defaultForLanguage: boolean; size: number; downloadUrl: string };

export type JobSourceOccurrence = {
  id?: number | null;
  sourceCode: string;
  sourceName: string;
  externalId?: string | null;
  sourceUrl?: string | null;
  matchType: 'PRIMARY' | 'EXACT_SOURCE_ID' | 'EXACT_URL' | 'EXACT_FINGERPRINT' | 'SIMILARITY' | 'LEGACY' | string;
  matchScore: number;
  matchReasons: string[];
  publishedAt?: string | null;
  firstSeenAt: string;
  lastSeenAt: string;
};

export type Job = {
  id: number; source: string; sourceCode?: string; sourceUrl?: string; title: string; company: string; clientName?: string;
  sources: JobSourceOccurrence[]; sourceCount: number;
  applicationEmail?: string; location: string; contractType: string; workMode: string; language: string; description: string;
  publishedAt?: string; discoveredAt?: string; ageHours?: number; salaryMin?: number; salaryMax?: number;
  tjmFixed?: number; tjmMin?: number; tjmMax?: number; proposedTjm?: number; proposedSalary?: number;
  score: number; scoreReasons: string[]; status: string; recommendedCv?: Cv;
};

export type Application = {
  id: number;
  jobOffer: Job;
  channel: string;
  status: string;
  submittedAt?: string;
  cvDocument?: Cv;
  message: string;
  coverLetter: string;
  compensationAnswer?: string;
  confirmationRef?: string;
  gmailMessageId?: string;
  submissionError?: string;
  submissionAttemptedAt?: string;
  updatedAt: string;
};

export type ConnectorResult = {
  received: number;
  imported: number;
  merged: number;
  duplicates: number;
  failed: number;
};

export type ConnectorPolicy = {
  complianceStatus: 'ALLOWED' | 'AUTHORIZED_ONLY' | 'EMAIL_OR_EXTENSION_ONLY' | 'DISABLED' | 'UNDER_REVIEW';
  complianceLabel: string;
  collectionAllowed: boolean;
  reviewedAt?: string | null;
  note?: string | null;
  maxRequestsPerSync?: number | null;
  dailyQuota?: number | null;
  minimumDelayMilliseconds: number;
  respectsRobotsTxt: boolean;
};

export type ConnectorHealth = {
  status: 'HEALTHY' | 'WATCH' | 'DEGRADED' | 'BROKEN' | 'NO_DATA';
  label: string;
  alert: boolean;
  sampleSize: number;
  consecutiveZeroRuns: number;
  lastExtractionRate?: number | null;
  baselineAverageReceived?: number | null;
  reasons: string[];
};

export type ConnectorFieldQuality = {
  received: number;
  requiredCompleteness?: number | null;
  recommendedCompleteness?: number | null;
  overallCompleteness?: number | null;
  missingRequiredRecords: number;
  fields: Record<string, {
    category: 'required' | 'recommended';
    present: number;
    missing: number;
    rate?: number | null;
  }>;
  warnings: string[];
};

export type SourceConnector = {
  id: number;
  code: string;
  name: string;
  mode: 'API' | 'RSS' | 'SCRAPING_HTTP' | 'SCRAPING_BROWSER' | 'GMAIL' | 'EXTENSION' | 'MANUAL';
  enabled: boolean;
  configured: boolean;
  configurationMessage?: string | null;
  collectionAllowed: boolean;
  policy: ConnectorPolicy;
  parserVersion?: string | null;
  health: ConnectorHealth;
  fieldQuality: ConnectorFieldQuality;
  status: string;
  lastSyncedAt?: string | null;
  lastSuccessfulAt?: string | null;
  nextSyncAt?: string | null;
  due: boolean;
  lastResult: ConnectorResult;
  lastError?: string | null;
  updatedAt: string;
};

export type ConnectorSyncRun = {
  id: number;
  connector: { code: string; name: string };
  trigger: string;
  status: string;
  startedAt: string;
  finishedAt?: string | null;
  durationMs?: number | null;
  received: number;
  imported: number;
  merged: number;
  duplicates: number;
  failed: number;
  error?: string | null;
  details: {
    errors?: string[];
    parserVersion?: string | null;
    normalizationRate?: number | null;
    zeroResults?: boolean;
    fieldQuality?: ConnectorFieldQuality;
  };
};

export type Positioning = { id:number; finalClient:string; agency:string; recruiterName:string; recruiterEmail?:string; missionTitle:string; description:string; callForTenderReference?:string; proposedTjm?:number; acceptedTjm?:number; location:string; remotePolicy:string; agreementGivenAt?:string; status:string; agreementEmailSubject?:string; agreementEmailBody?:string; mailtoUrl?:string };

export type CrmContactRole = 'RECRUITER' | 'APPLICATION_ADDRESS' | 'INBOX_CONTACT';
export type CrmOrganizationRole = 'COMPANY' | 'AGENCY' | 'CLIENT';

export type CrmContact = {
  key: string;
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  roles: CrmContactRole[];
  messageCount: number;
  lastContactAt?: string | null;
};

export type CrmOfferSummary = {
  id?: number | null;
  title: string;
  status: string;
  score: number;
  sourceUrl?: string | null;
};

export type CrmOrganizationAnnotation = {
  displayName?: string | null;
  note?: string | null;
  updatedAt?: string | null;
};

export type CrmOrganization = {
  key: string;
  name: string;
  sourceName: string;
  annotation?: CrmOrganizationAnnotation | null;
  roles: CrmOrganizationRole[];
  offerCount: number;
  applicationCount: number;
  positioningCount: number;
  messageCount: number;
  contactCount: number;
  applicationStatuses: Record<string, number>;
  positioningStatuses: Record<string, number>;
  lastActivityAt?: string | null;
  contacts: CrmContact[];
  latestOffers: CrmOfferSummary[];
};

export type CrmDirectory = {
  generatedAt: string;
  organizationCount: number;
  contactCount: number;
  annotationCount: number;
  organizations: CrmOrganization[];
};
