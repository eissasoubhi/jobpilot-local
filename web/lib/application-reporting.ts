import type { Application } from '@/lib/types';

export type ApplicationReportingSummary = {
  total: number;
  submitted: number;
  interviews: number;
  rejected: number;
  active: number;
  submissionRate: number;
  interviewRate: number;
  rejectionRate: number;
  bySource: Array<{ source: string; total: number; submitted: number; interviews: number; rejected: number }>;
};

const submittedStatuses = new Set(['SUBMITTED', 'APPLICATION_CONFIRMED', 'INTERVIEW', 'OFFER_RECEIVED', 'REJECTED']);

function percentage(value: number, total: number): number {
  return total === 0 ? 0 : Math.round((value * 1000) / total) / 10;
}

export function buildApplicationReporting(applications: Application[]): ApplicationReportingSummary {
  let submitted = 0;
  let interviews = 0;
  let rejected = 0;
  const sources = new Map<string, { source: string; total: number; submitted: number; interviews: number; rejected: number }>();

  for (const application of applications) {
    const isSubmitted = Boolean(application.submittedAt) || submittedStatuses.has(application.status);
    const isInterview = application.status === 'INTERVIEW';
    const isRejected = application.status === 'REJECTED';
    submitted += Number(isSubmitted);
    interviews += Number(isInterview);
    rejected += Number(isRejected);

    const source = application.jobOffer.sources[0]?.sourceName || application.jobOffer.source || 'Source inconnue';
    const row = sources.get(source) ?? { source, total: 0, submitted: 0, interviews: 0, rejected: 0 };
    row.total += 1;
    row.submitted += Number(isSubmitted);
    row.interviews += Number(isInterview);
    row.rejected += Number(isRejected);
    sources.set(source, row);
  }

  return {
    total: applications.length,
    submitted,
    interviews,
    rejected,
    active: Math.max(0, applications.length - rejected),
    submissionRate: percentage(submitted, applications.length),
    interviewRate: percentage(interviews, submitted),
    rejectionRate: percentage(rejected, submitted),
    bySource: [...sources.values()].sort((left, right) => right.total - left.total || left.source.localeCompare(right.source)),
  };
}
