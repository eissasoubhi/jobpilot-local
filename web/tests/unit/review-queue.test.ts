import { describe, expect, it } from 'vitest';

import {
  clampReviewQueueIndex,
  currentReviewQueueItem,
  isReadyToSubmitReviewItem,
  nextReviewQueueIndexAfterDecision,
} from '@/lib/review-queue';
import type { Application } from '@/lib/types';

describe('Review Queue helpers', () => {
  it('keeps only applications that are ready to submit', () => {
    const application = (status: string) => ({ status }) as Application;

    expect(isReadyToSubmitReviewItem(application('READY_TO_SUBMIT'))).toBe(true);
    expect(isReadyToSubmitReviewItem(application('SUBMITTED'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('INTERVIEW'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('REJECTED'))).toBe(false);
    expect(isReadyToSubmitReviewItem(application('IGNORED_NOT_MATCH'))).toBe(false);
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
