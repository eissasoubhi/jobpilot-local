import type { Application, Job } from '@/lib/types';

export function isReadyToSubmitReviewItem(application: Application): boolean {
  return application.status === 'READY_TO_SUBMIT';
}

export function buildReviewQueue(applications: Application[], jobs: Job[]): Application[] {
  const jobOrder = new Map(jobs.map((job, index) => [job.id, index]));

  return applications
    .map((application, originalIndex) => ({ application, originalIndex }))
    .filter(({ application }) => isReadyToSubmitReviewItem(application))
    .sort((left, right) => {
      const leftOrder = jobOrder.get(left.application.jobOffer.id);
      const rightOrder = jobOrder.get(right.application.jobOffer.id);

      if (leftOrder === undefined && rightOrder === undefined) {
        return left.originalIndex - right.originalIndex;
      }
      if (leftOrder === undefined) return 1;
      if (rightOrder === undefined) return -1;

      return leftOrder - rightOrder || left.originalIndex - right.originalIndex;
    })
    .map(({ application }) => application);
}

export function clampReviewQueueIndex(index: number, length: number): number {
  if (length <= 0) return 0;
  return Math.min(Math.max(index, 0), length - 1);
}

export function currentReviewQueueItem<T>(items: T[], index: number): T | undefined {
  if (items.length === 0) return undefined;
  return items[clampReviewQueueIndex(index, items.length)];
}

export function nextReviewQueueIndexAfterDecision(index: number, lengthBeforeDecision: number): number {
  const remainingLength = Math.max(lengthBeforeDecision - 1, 0);

  if (remainingLength === 0) return 0;
  if (index < remainingLength) return index;

  return 0;
}
