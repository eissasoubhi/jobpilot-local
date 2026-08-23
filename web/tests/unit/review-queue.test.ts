import { describe, expect, it } from 'vitest';

import {
  buildReviewQueue,
  clampReviewQueueIndex,
  currentReviewQueueItem,
  isOfferWithinReviewWindow,
  isReadyToSubmitReviewItem,
  nextReviewQueueIndexAfterDecision,
} from '@/lib/review-queue';
import type { Application, Job } from '@/lib/types';

describe('Review Queue helpers', () => {
  it('keeps only applications that are ready to submit', () => {
    const application = (status: string) => ({ status }) as Application;

    expect(isReadyToSubmitReviewItem(application('READY_TO_SUBMIT'))).toBe(true);
    expect(isReadyToSubmitReviewItem(application('SUBMITTED'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('INTERVIEW'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('REJECTED'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('IGNORED_NOT_MATCH'))).toBe(false);
  });

  it('keeps offers through day 30 and expires them after that', () => {
    const now = new Date('2026-08-23T21:00:00.000Z');
    const job = (publishedAt: string): Job => ({ publishedAt }) as Job;

    expect(isOfferWithinReviewWindow(job('2026-07-25T21:00:00.000Z'), now)).toBe(true);
    expect(isOfferWithinReviewWindow(job('2026-07-24T21:00:00.000Z'), now)).toBe(true);
    expect(isOfferWithinReviewWindow(job('2026-07-23T21:00:00.000Z'), now)).toBe(false);
  });

  it('falls back to discoveredAt when publication date is unknown', () => {
    const now = new Date('2026-08-23T21:00:00.000Z');

    expect(isOfferWithinReviewWindow({ discoveredAt: '2026-07-23T20:59:59.000Z' } as Job, now)).toBe(false);
    expect(isOfferWithinReviewWindow({ discoveredAt: '2026-08-01T10:00:00.000Z' } as Job, now)).toBe(true);
  });

  it('keeps unknown dates neutral instead of inventing staleness', () => {
    expect(isOfferWithinReviewWindow({} as Job, new Date('2026-08-23T21:00:00.000Z'))).toBe(true);
  });

  it('removes expired ready applications from the active queue without changing their status', () => {
    const now = new Date('2026-08-23T21:00:00.000Z');
    const freshJob = { id: 1, publishedAt: '2026-08-10T10:00:00.000Z' } as Job;
    const oldJob = { id: 2, publishedAt: '2026-07-01T10:00:00.000Z' } as Job;
    const freshApplication = { id: 11, status: 'READY_TO_SUBMIT', jobOffer: freshJob } as Application;
    const oldApplication = { id: 12, status: 'READY_TO_SUBMIT', jobOffer: oldJob } as Application;

    const queue = buildReviewQueue([oldApplication, freshApplication], [oldJob, freshJob], now);

    expect(queue).toEqual([freshApplication]);
    expect(oldApplication.status).toBe('READY_TO_SUBMIT');
  });

  it('keeps the current index inside queue bounds', () => {
    expect(clampReviewQueueIndex(-1, 3)).toBe(0);
    expect(clampReviewQueueIndex(1, 3)).toBe(1);
    expect(clampReviewQueueIndex(7, 3)).toBe(2);
    expect(clampReviewQueueIndex(2, 0)).toBe(0);
  });

  it('returns one current item and gracefully handles an empty queue', () => {
    const offers = ['first', 'second', 'third'];

    expect(currentReviewQueueItem(offers, 0)).toBe('first');
    expect(currentReviewQueueItem(offers, 2)).toBe('third');
    expect(currentReviewQueueItem(offers, 99)).toBe('third');
    expect(currentReviewQueueItem([], 0)).toBeUndefined();
  });

  it('keeps the shifted next item selected after a decision', () => {
    expect(nextReviewQueueIndexAfterDecision(0, 3)).toBe(0);
    expect(nextReviewQueueIndexAfterDecision(1, 3)).toBe(1);
  });

  it('wraps to the first remaining item after deciding the last one', () => {
    expect(nextReviewQueueIndexAfterDecision(2, 3)).toBe(0);
    expect(nextReviewQueueIndexAfterDecision(0, 1)).toBe(0);
  });
});
