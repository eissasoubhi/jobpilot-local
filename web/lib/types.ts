export type Profile = {
  fullName: string; email: string; phone: string; city: string; postalCode: string;
  mobility: string; workAuthorisation: string; availability: string; noticePeriod: string;
  yearsOfExperience: number; languages: { language: string; level: string }[];
  acceptedContracts: string[]; workModePreference: string; linkedinUrl?: string; portfolioUrl?: string;
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

export type Job = {
  id: number; source: string; sourceUrl?: string; title: string; company: string; clientName?: string;
  applicationEmail?: string; location: string; contractType: string; workMode: string; language: string; description: string;
  publishedAt?: string; ageHours?: number; salaryMin?: number; salaryMax?: number;
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
  duplicates: number;
  failed: number;
};

export type SourceConnector = {
  id: number;
  code: string;
  name: string;
  mode: 'API' | 'RSS' | 'SCRAPING_HTTP' | 'SCRAPING_BROWSER' | 'GMAIL' | 'EXTENSION' | 'MANUAL';
  enabled: boolean;
  configured: boolean;
  configurationMessage?: string | null;
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
  duplicates: number;
  failed: number;
  error?: string | null;
  details: { errors?: string[] };
};

export type Positioning = { id:number; finalClient:string; agency:string; recruiterName:string; recruiterEmail?:string; missionTitle:string; description:string; callForTenderReference?:string; proposedTjm?:number; acceptedTjm?:number; location:string; remotePolicy:string; agreementGivenAt?:string; status:string; agreementEmailSubject?:string; agreementEmailBody?:string; mailtoUrl?:string };
