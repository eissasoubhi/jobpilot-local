import type { Application } from '@/lib/types';

export type OfferInboxView = 'actionable' | 'submitted';

export function matchesOfferInboxView(
  application: Application | undefined,
  view: OfferInboxView,
): boolean {
  const submitted = application?.status === 'SUBMITTED';

  return view === 'submitted' ? submitted : !submitted;
}
