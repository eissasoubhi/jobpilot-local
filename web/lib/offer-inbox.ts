import type { Application } from '@/lib/types';

export type OfferInboxView = 'actionable' | 'submitted';

export function matchesOfferInboxView(
  application: Application | undefined,
  view: OfferInboxView,
): boolean {
  const submitted = application?.status === 'SUBMITTED';
  const ignored = application?.status === 'IGNORED_NOT_MATCH';

  if (view === 'submitted') return submitted;

  return !submitted && !ignored;
}
