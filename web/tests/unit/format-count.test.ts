import { describe, expect, it } from 'vitest';

import { formatCount } from '@/lib/formatCount';

describe('formatCount', () => {
  it('uses the singular form only for exactly one item', () => {
    expect(formatCount(1, 'offre reçue', 'offres reçues')).toBe('1 offre reçue');
    expect(formatCount(0, 'offre reçue', 'offres reçues')).toBe('0 offres reçues');
    expect(formatCount(2, 'offre reçue', 'offres reçues')).toBe('2 offres reçues');
  });
});
